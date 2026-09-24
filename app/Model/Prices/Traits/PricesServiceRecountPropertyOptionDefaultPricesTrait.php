<?php


trait PricesServiceRecountPropertyOptionDefaultPricesTrait
{
	/**
	 * @param PriceGroupsEntity|NULL $onlyForPriceGroup
	 * @param CurrenciesEntity|NULL $onlyForCurrency
	 * @param ProductConfiguratorParametersEntity|NULL $onlyForProductConfiguratorParameter
	 * @return void
	 */
	public function recountPropertyOptionDefaultPrices(PriceGroupsEntity $onlyForPriceGroup = NULL, CurrenciesEntity $onlyForCurrency = NULL, ProductConfiguratorParametersEntity $onlyForProductConfiguratorParameter = NULL): void
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

				$pricesSelection = $this->getPricesSelection($this->propertyOptionDefaultPricesRepo, $priceGroup, $this->getDefaultCurrency());
				if($onlyForProductConfiguratorParameter instanceof ProductConfiguratorParametersEntity){
					$pricesSelection = $pricesSelection->where('product_configurator_parameters_id', $onlyForProductConfiguratorParameter->id);
				}

				$sets = $this->getNewPriceRowSets($currenciesSettings, $currency, $pricesSelection);

				$selectionDelete = $this->getPricesSelection($this->propertyOptionDefaultPricesRepo, $priceGroup, $currency);
				if($onlyForProductConfiguratorParameter instanceof ProductConfiguratorParametersEntity){
					$selectionDelete = $selectionDelete->where('product_configurator_parameters_id', $onlyForProductConfiguratorParameter->id);
				}
				$selectionDelete->delete();

				$this->insertPriceSets($this->propertyOptionDefaultPricesRepo, $sets);
			}
		}

	}
}
