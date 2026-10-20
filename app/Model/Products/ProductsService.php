<?php


use Nette\Application\UI\Form;

class ProductsService
{
	use \Nette\SmartObject;
	use ProductsServiceCategoriesTrait;

    /**
     * @param \Nette\Database\Explorer $database
     * @param BranchesRepository $branchesRepo
     * @param ProductPricesRepository $productPricesRepo
     * @param CurrenciesRepository $currenciesRepo
     * @param PricesService $pricesService
     * @param ProductsRepository $productsRepo
     * @param GlobalContainer $globalContainer
     */
	public function __construct(
		protected \Nette\Database\Explorer $database,
		protected BranchesRepository $branchesRepo,
		protected ProductPricesRepository $productPricesRepo,
		protected CurrenciesRepository $currenciesRepo,
		protected PricesService $pricesService,
		protected ProductsRepository $productsRepo,
		protected GlobalContainer $globalContainer,
	)
	{
	}

    public function updatePrices(ProductsEntity $product, \Nette\Application\UI\Form $form): ProductsEntity
    {
        $values = $form->getValues();

        // prices
        $sets = [];
		$price = NULL;
		$isPrice = FALSE;

	    foreach ($this->branchesRepo->findAllEntities('pos_order') as $branch) {
		    foreach ($this->currenciesRepo->findAllEntities('pos_order') as $currency) {
			    $var = 'branch_prices_'.$branch->id.'_'.$currency->id;
			    if (!$isPrice) {
				    $price = isset($values[$var.'_price']) && $values[$var.'_price'] !== '' ? floatval(str_replace(',', '.', $values[$var.'_price'])) : NULL;
					if (!is_null($price)) {
						$isPrice = TRUE;
					}
			    }
		    }
	    }

        foreach ($this->branchesRepo->findAllEntities('pos_order') as $branch) {
            foreach ($this->currenciesRepo->findAllEntities('pos_order') as $currency) {
                $var = 'branch_prices_'.$branch->id.'_'.$currency->id;

				$isAction = FALSE;
				if ($price && isset($values[$var.'_price_action']) && $values[$var.'_price_action'] !== '' && floatval(str_replace(',', '.', $values[$var.'_price_action'])) > 0 && $price > floatval(str_replace(',', '.', $values[$var.'_price_action'])) && isset($values[$var.'_action_from']) && $values[$var.'_action_from'] !== '' && date('Y-m-d', strtotime($values[$var.'_action_from'])) <= date('Y-m-d') && ((!isset($values[$var.'_action_to']) || $values[$var.'_action_to'] === '') || date('Y-m-d', strtotime($values[$var.'_action_to'])) >= date('Y-m-d'))) {
					$isAction = TRUE;
				}

                $sets[] = [
                    'products_id' => $product->id,
                    'branches_id' => $branch->id,
                    'currencies_id' => $currency->id,
                    'price' => $price,
                    'price_action' => isset($values[$var.'_price_action']) && $values[$var.'_price_action'] !== '' ? floatval(str_replace(',', '.', $values[$var.'_price_action'])) : NULL,
                    'is_action' => $isAction,
                    'action_from' => isset($values[$var.'_action_from']) && $values[$var.'_action_from'] !== '' ? date('Y-m-d', strtotime($values[$var.'_action_from'])) : NULL,
                    'action_to' => isset($values[$var.'_action_to']) && $values[$var.'_action_to'] !== '' ? date('Y-m-d', strtotime($values[$var.'_action_to'])) : NULL,
                ];
            }
        }

        // log price changes per branch/currency before saving (same logic as updatePricesAll)
        foreach ($sets as $s) {
            $logPrice = null;
            $isActionPrice = FALSE;
            if (!is_null($s['price_action']) && $s['price_action'] > 0 && $s['is_action']) {
                $logPrice = (float)$s['price_action'];
                $isActionPrice = TRUE;
            } elseif (!is_null($s['price'])) {
                $logPrice = (float)$s['price'];
            }
            if (!is_null($logPrice)) {
                $this->logProductPrice((int)$s['products_id'], (int)$s['branches_id'], (int)$s['currencies_id'], $logPrice, NULL, $isActionPrice ? $s['action_from'] : NULL, $isActionPrice ? $s['action_to'] : NULL);
            }
        }

        //insert prices
        $this->pricesService->updatePricesFromSets($this->productPricesRepo->getTableName(), ['products_id' => $product->id], $sets);


        return $this->productsRepo->findEntity($product->id);
    }


	/**
	 * @param int $productsId
	 * @param int $branchesId
	 * @param int $currenciesId
	 * @param float $newPrice
	 * @param float|null $originPrice
	 * @param string|null $actionFrom Y-m-d, validity start of the price/action being logged (if any)
	 * @param string|null $actionTo Y-m-d, validity end of the price/action being logged (if any)
	 * @return float|null
	 */
	public function logProductPrice(int $productsId, int $branchesId, int $currenciesId, float $newPrice, float $originPrice = NULL, ?string $actionFrom = NULL, ?string $actionTo = NULL): ?float
	{
		/** @var ProductsEntity $product */
		if ($product = $this->productsRepo->findEntity($productsId)) {
			if (is_null($originPrice)) {
				$originPriceObj = $product->getPrice($branchesId);
				$originPrice = 0;
				if (isset($originPriceObj->price_action) && (!empty($originPriceObj->is_valid_action) || !empty($originPriceObj->is_label_action))) {
					$originPrice = $originPriceObj->price_action;
				}
				elseif (isset($originPriceObj->price)) {
					$originPrice = $originPriceObj->price;
				}
			}

			if ($originPrice != $newPrice) {
				$priceRef = $this->getPriceRef($productsId, $branchesId, $currenciesId, $originPrice, $newPrice);

				$this->database->table('product_prices_log')->insert([
					'products_id' => $productsId,
					'branches_id' => $branchesId,
					'currencies_id' => $currenciesId,
					'datetime' => (new \DateTime('now'))->format('Y-m-d H:i:s'),
					'price_origin' => $originPrice,
					'price_new' => $newPrice,
					'price_ref' => $priceRef,
					'action_from' => $actionFrom ?: NULL,
					'action_to' => $actionTo ?: NULL,
				]);

				return $priceRef;
			}
		}

		return NULL;
	}


	/**
	 * @param int $productsId
	 * @param int $branchesId
	 * @param int $currenciesId
	 * @param float $originPrice
	 * @param float $newPrice
	 * @return float
	 */
	public function getPriceRef(int $productsId, int $branchesId, int $currenciesId, float $originPrice, float $newPrice): float
	{
		if (!$originPrice) {
			return $originPrice;
		}

		if (!$newPrice) {
			return $originPrice;
		}

		if ($originPrice === $newPrice) {
			return $originPrice;
		}

		if ($newPrice > $originPrice) {
			$lowestPrice = $this->getLowestPriceIn30Days($productsId, $branchesId, $currenciesId);

			return $lowestPrice ?: $originPrice;
		}

		$lastPriceChange = $this->database->table('product_prices_log')
			->where('products_id', $productsId)
			->where('branches_id', $branchesId)
			->where('currencies_id', $currenciesId)
			->order('datetime DESC')
			->limit(1)
			->fetch();

		if (!$lastPriceChange) {
			return $originPrice;
		}
		elseif ($lastPriceChange->price_new < $lastPriceChange->price_origin && $lastPriceChange->price_ref) {
			return $lastPriceChange->price_ref;
		}
		elseif ($lastPriceChange->price_new > $lastPriceChange->price_origin) {
			$lowestPrice = $this->getLowestPriceIn30Days($productsId, $branchesId, $currenciesId);;

			return $lowestPrice ?: $originPrice;
		}

		return $originPrice;
	}


	/**
	 * @param int $productsId
	 * @param int $branchesId
	 * @param int $currenciesId
	 * @return float
	 */
	public function getLowestPriceIn30Days(int $productsId, int $branchesId, int $currenciesId): float
	{
		$dateTime = new \DateTime('now');
		$dateTime->modify('-30 days');

		$lowestPrice = $this->database->query("
				SELECT
					MIN(LEAST(price_origin, price_new)) price
				FROM
				    product_prices_log
				WHERE
				    products_id = ?
				  	AND branches_id = ?
				  	AND currencies_id = ?
					AND DATE(datetime) >= ?
					AND price_origin > 0
			", $productsId, $branchesId, $currenciesId, $dateTime->format('Y-m-d'))->fetch();

		if (!$lowestPrice || !$lowestPrice->price) {
			return 0;
		}

		return $lowestPrice->price;

	}
}
