<?php


/**
 * "Cena a akce" of a product in one branch (Produkty v2 row, Tisk z mobilu row): saves the
 * price row, logs it (product_prices_log, the 30-day rule) and copies a new regular price
 * to the other branches the admin may edit that have no running action.
 * Moved unchanged from ProductPricesTrait::handleUpdatePricesAll().
 */
final class ProductPriceSaver
{
	public function __construct(
		private ProductsRepository $productsRepo,
		private ProductPricesRepository $productPricesRepo,
		private ProductsService $productsService,
		private GlobalContainer $globalContainer,
	)
	{
	}


	/**
	 * Values as typed in the row ('' = empty, dates d.m.Y).
	 * @param list<int> $branchIds branches of the admin; the regular price is copied to them
	 */
	public function save(int $productsId, int $branchId, array $branchIds, ?int $itemId, $price, $priceAction, $actionFrom, $actionTo, ?string $priceUnit): void
	{
		// "Cena za" of the "Cena a akce" panel (products.price_unit)
		if ($priceUnit !== NULL && in_array($priceUnit, ProductsEntity::PRICE_UNITS, TRUE)) {
			$this->productsRepo->getDatabase()->table('products')->where('id', $productsId)->update(['price_unit' => $priceUnit]);
		}

		$currencyId = (int)$this->globalContainer->getDefaultCurrency()->id;
		$product = $this->productsRepo->findEntity($productsId);

		$set = [];
		$set['price'] = $price !== NULL && $price !== '' ? (float)$price : NULL;
		$set['price_action'] = $priceAction !== NULL && $priceAction !== '' ? (float)$priceAction : NULL;

		// dates d.m.Y → Y-m-d
		$set['action_from'] = $this->isoDate($actionFrom);
		$set['action_to'] = $this->isoDate($actionTo);

		// the dates are optional: an action price without them runs (ProductPricesEntity::actionState())
		$set['is_action'] = ProductPricesEntity::actionState($set['price'], $set['price_action'], $set['action_from'], $set['action_to']) === 'running';

		$entity = $itemId ? $this->productPricesRepo->findEntity($itemId) : NULL;
		if (!$entity) {
			$entity = $this->productPricesRepo->findEntityBy([
				'products_id' => $productsId,
				'branches_id' => $branchId,
				'currencies_id' => $currencyId,
			]);
		}

		$logPrice = $set['is_action'] ? $set['price_action'] : $set['price'];
		$this->productsService->logProductPrice($productsId, $branchId, $currencyId, $logPrice, NULL, $set['is_action'] ? $set['action_from'] : NULL, $set['is_action'] ? $set['action_to'] : NULL);

		if ($entity instanceof ProductPricesEntity) {
			// only the mutable columns: keep the foreign keys as they are
			$entity->setValues($set);
			$this->productPricesRepo->update($entity);
		}
		else {
			$this->productPricesRepo->insert([
				'branches_id' => $branchId,
				'currencies_id' => $currencyId,
				'products_id' => $productsId,
			] + $set);
		}

		// a regular price goes to the admin's other branches too (not while their action runs)
		if ($set['price'] !== NULL && $product) {
			foreach ($branchIds as $otherBranchId) {
				if ((int)$otherBranchId === $branchId) {
					continue;
				}
				$branchPrice = $product->getPrice((int)$otherBranchId);
				if (!empty($branchPrice->is_valid_action) || !empty($branchPrice->is_label_action)) {
					continue;
				}
				$this->productsService->logProductPrice($productsId, (int)$otherBranchId, $currencyId, $set['price']);
				$this->productPricesRepo->findAll()->where([
					'branches_id' => (int)$otherBranchId,
					'currencies_id' => $currencyId,
					'products_id' => $productsId,
				])->update(['price' => $set['price']]);
			}
		}
	}


	private function isoDate($value): ?string
	{
		if (!$value) {
			return NULL;
		}
		$date = \DateTime::createFromFormat('d.m.Y', (string)$value);

		return $date ? $date->format('Y-m-d') : NULL;
	}
}
