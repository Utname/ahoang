<?php


trait PricesServiceRecountPropertyOptionPricesTrait
{
	/**
	 * @param PriceGroupsEntity|NULL $onlyForPriceGroup
	 * @param CurrenciesEntity|NULL $onlyForCurrency
	 * @param ProductsEntity|NULL $onlyForProduct
	 * @return void
	 */
	public function recountPropertyOptionPrices(PriceGroupsEntity $onlyForPriceGroup = NULL, CurrenciesEntity $onlyForCurrency = NULL, ProductsEntity $onlyForProduct = NULL): void
	{
		if(!$this->getDefaultCurrency() instanceof CurrenciesEntity){
			return;
		}

		$priceGroups = $this->getPriceGroupsForRecount($onlyForPriceGroup);
		if(empty($priceGroups)){
			return;
		}

		$currencies = $this->getCurrenciesForRecount($onlyForCurrency);
		if(empty($currencies)){
			return;
		}

		/** @var PriceGroupsEntity $priceGroup */
		foreach($priceGroups as $priceGroup){
			$currenciesSettings = $priceGroup->getAllCurrenciesSettings();
			/** @var CurrenciesEntity $currency */
			foreach($currencies as $currency){
				if(!isset($currenciesSettings[$currency->id]) || !$currenciesSettings[$currency->id]['auto_recount_by_default_currency']){
					continue;
				}

				$pricesSelection = $this->getPricesSelection($this->propertyOptionPricesRepo, $priceGroup, $this->getDefaultCurrency());
				if($onlyForProduct instanceof ProductsEntity){
					$pricesSelection = $pricesSelection->where('products_id', $onlyForProduct->id);
				}

				$sets = $this->getNewPriceRowSets($currenciesSettings, $currency, $pricesSelection);

				$selectionDelete = $this->getPricesSelection($this->propertyOptionPricesRepo, $priceGroup, $currency);
				if($onlyForProduct instanceof ProductsEntity){
					$selectionDelete = $selectionDelete->where('products_id', $onlyForProduct->id);
				}
				$selectionDelete->delete();

				$this->insertPriceSets($this->propertyOptionPricesRepo, $sets);
			}
		}

	}
}
