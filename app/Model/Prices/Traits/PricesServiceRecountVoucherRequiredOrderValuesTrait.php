<?php


trait PricesServiceRecountVoucherRequiredOrderValuesTrait
{
	/**
	 * @param PriceGroupsEntity|NULL $onlyForPriceGroup
	 * @param CurrenciesEntity|NULL $onlyForCurrency
	 * @param VouchersEntity|NULL $onlyForVoucher
	 * @return void
	 */
	public function recountVoucherRequiredOrderValues(PriceGroupsEntity $onlyForPriceGroup = NULL, CurrenciesEntity $onlyForCurrency = NULL, VouchersEntity $onlyForVoucher = NULL): void
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

				$pricesSelection = $this->getPricesSelection($this->voucherRequiredOrderValuesRepo, $priceGroup, $this->getDefaultCurrency());
				if($onlyForVoucher instanceof VouchersEntity){
					$pricesSelection = $pricesSelection->where('vouchers_id', $onlyForVoucher->id);
				}

				$sets = $this->getNewPriceRowSets($currenciesSettings, $currency, $pricesSelection);

				$selectionDelete = $this->getPricesSelection($this->voucherRequiredOrderValuesRepo, $priceGroup, $currency);
				if($onlyForVoucher instanceof VouchersEntity){
					$selectionDelete = $selectionDelete->where('vouchers_id', $onlyForVoucher->id);
				}
				$selectionDelete->delete();

				$this->insertPriceSets($this->voucherRequiredOrderValuesRepo, $sets);
			}
		}

	}
}
