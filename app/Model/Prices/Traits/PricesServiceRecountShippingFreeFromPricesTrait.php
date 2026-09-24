<?php


trait PricesServiceRecountShippingFreeFromPricesTrait
{
	/**
	 * @param PriceGroupsEntity|NULL $onlyForPriceGroup
	 * @param CurrenciesEntity|NULL $onlyForCurrency
	 * @param ShippingTypesEntity|NULL $onlyForShippingType
	 * @return void
	 */
	public function recountShippingFreeFromPrices(PriceGroupsEntity $onlyForPriceGroup = NULL, CurrenciesEntity $onlyForCurrency = NULL, ShippingTypesEntity $onlyForShippingType = NULL): void
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

				$pricesSelection = $this->getPricesSelection($this->shippingTypePricesRepo, $priceGroup, $this->getDefaultCurrency());
				//only for free from
				$pricesSelection->where('price_types_id', ShippingTypePricesEntity::TYPE_FREE_FROM);
				if($onlyForShippingType instanceof ShippingTypesEntity){
					$pricesSelection = $pricesSelection->where('shipping_types_id', $onlyForShippingType->id);
				}

				$sets = $this->getNewPriceRowSets($currenciesSettings, $currency, $pricesSelection);

				$selectionDelete = $this->getPricesSelection($this->shippingTypePricesRepo, $priceGroup, $currency);
				//only for free from
				$pricesSelection->where('price_types_id', ShippingTypePricesEntity::TYPE_FREE_FROM);
				if($onlyForShippingType instanceof ShippingTypesEntity){
					$selectionDelete = $selectionDelete->where('shipping_types_id', $onlyForShippingType->id);
				}
				$selectionDelete->delete();


				$this->insertPriceSets($this->shippingTypePricesRepo, $sets);
			}
		}

	}
}
