<?php


class ProductPricesEntity extends BaseEntity
{
	/** @var int */
	protected $priceTypesId;

	/** @var ProductsEntity */
	protected $product;

	/** @var CurrenciesEntity */
	protected $currency;


	/**
	 * @return ProductsEntity|null
	 */
	public function getProduct(): ?ProductsEntity
	{
		if (!$this->product && $this->products_id && $row = $this->getRow()->ref('products')) {
			$this->product = ProductsRepository::getEntity($row, $row->related('products_mld'));
		}

		return $this->product;
	}


	/**
	 * @return CurrenciesEntity|null
	 */
	public function getCurrency(): ?CurrenciesEntity
	{
		if (!$this->currency && $this->currencies_id && $row = $this->getRow()->ref('currencies')) {
			$this->currency = CurrenciesRepository::getEntity($row, $row->related('currencies_mld'));
		}

		return $this->currency;
	}


	/**
	 * @param bool $getSecondary
	 * @param int|NULL $priceTypesId
	 * @param TaxRatesEntity|NULL $taxRatesEntity
	 * @return float
	 */
	public function getOriginPrice(bool $getSecondary = FALSE, int $priceTypesId = NULL, TaxRatesEntity $taxRatesEntity = NULL): float
	{
		$price = floatval($this->price);

		if ($getSecondary) {
			if (is_null($priceTypesId)) {
				$priceTypesId = $this->getPriceTypesId();
			}

			if (is_null($taxRatesEntity)) {
				$taxRatesEntity = $this->getTaxRate();
			}

			if ($priceTypesId === PriceGroupsEntity::PRICE_TYPE_INCL_VAT) {
				$price = $price / ($taxRatesEntity?->getRatio() ?: 1);
			}
			else {
				$price = $price * ($taxRatesEntity?->getRatio() ?: 1);
			}
		}

		return $price;
	}


	/**
	 * @param bool $getSecondary
	 * @param int|null $priceTypesId
	 * @param TaxRatesEntity|null $taxRatesEntity
	 * @param bool $withPriceResets
	 * @return float
	 */
	public function getFinalPrice(bool $getSecondary = FALSE, int $priceTypesId = NULL, TaxRatesEntity $taxRatesEntity = NULL, bool $withPriceResets = FALSE): float
	{
		if ($withPriceResets && $this->getProductVariant()->getProduct()->product_configurators_id && $this->getProductVariant()
				->getProduct()->is_price_reset_by_configurator) {
			return 0;
		}

		$price = $this->isAction() ? floatval($this->price_action) : floatval($this->price);

		if ($getSecondary) {
			if (is_null($priceTypesId)) {
				$priceTypesId = $this->getPriceTypesId();
			}

			if (is_null($taxRatesEntity)) {
				$taxRatesEntity = $this->getTaxRate();
			}

			if ($priceTypesId === PriceGroupsEntity::PRICE_TYPE_INCL_VAT) {
				$price = $price / ($taxRatesEntity?->getRatio() ?: 1);
			}
			else {
				$price = $price * ($taxRatesEntity?->getRatio() ?: 1);
			}
		}

		return $price;
	}


	/**
	 * @return bool
	 */
	public function isAction(): bool
	{
		if (!$this->is_action) {
			return FALSE;
		}

		return TRUE;
	}


	/**
	 * @param $branchId
	 * @param CurrenciesEntity|NULL $currency
	 * @param $priceAction
	 * @return mixed|\Nette\Database\Table\ActiveRow|null
	 */
	public function getPrice($branchId = NULL, CurrenciesEntity $currency = NULL, $priceAction = FALSE)
	{
		foreach ($this->getRow()->related('product_prices') as $row) {
			if ($row->branches_id == $branchId && $row->currencies_id == $currency->id) {
				return ProductPricesRepository::getEntity($row);
			}
		}

		return NULL;
	}


	/**
	 * @param bool $onlyValid
	 * @return int
	 */
	public function getActionPercents(bool $isWithLog = TRUE): int
	{
		/** @var ProductsEntity $product */
		if (!$product = $this->getProduct()) {
			return 0;
		}

		if (!$priceObj = $product->{$isWithLog ? 'getPriceWithLog' : 'getPrice'}($this->branches_id)) {
			return 0;
		}

		if (!isset($priceObj->is_valid_action) || !$priceObj->is_valid_action) {
			return 0;
		}

		return round(100 - ($priceObj->price_action / ($priceObj->price / 100)));
	}
}
