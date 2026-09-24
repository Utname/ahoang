<?php


trait PricesServiceRecountProductPricesTrait
{
	/**
	 * @param PriceGroupsEntity|NULL $onlyForPriceGroup
	 * @param CurrenciesEntity|NULL $onlyForCurrency
	 * @param ProductVariantsEntity|NULL $onlyForVariant
	 * @return void
	 */
	public function recountProductPrices(PriceGroupsEntity $onlyForPriceGroup = NULL, CurrenciesEntity $onlyForCurrency = NULL, ProductVariantsEntity $onlyForVariant = NULL): void
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

				$pricesSelection = $this->getPricesSelection($this->productPricesRepo, $priceGroup, $this->getDefaultCurrency());
				if($onlyForVariant instanceof ProductVariantsEntity){
					$pricesSelection = $pricesSelection->where('product_variants_id', $onlyForVariant->id);
				}

				$sets = $this->getNewPriceRowSets($currenciesSettings, $currency, $pricesSelection);

				$selectionDelete = $this->getPricesSelection($this->productPricesRepo, $priceGroup, $currency);
				if($onlyForVariant instanceof ProductVariantsEntity){
					$selectionDelete = $selectionDelete->where('product_variants_id', $onlyForVariant->id);
				}
				$selectionDelete->delete();

				$this->insertPriceSets($this->productPricesRepo, $sets);
			}
		}

	}
}
