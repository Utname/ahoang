<?php


trait PriceGroupsServiceSettingsTrait
{
	/**
	 * @param PriceGroupsEntity $priceGroup
	 * @return void
	 */
	public function resetSettings(PriceGroupsEntity $priceGroup): void
	{
		$this->database->table('price_groups_currencies_settings')->where('price_groups_id', $priceGroup->id)->delete();
	}


	/**
	 * @param PriceGroupsEntity $priceGroup
	 * @param CurrenciesEntity $currency
	 * @param float $autoRecountExchangeRateCorrection
	 * @return void
	 */
	public function setIsAutoRecount(PriceGroupsEntity $priceGroup, CurrenciesEntity $currency, float $autoRecountExchangeRateCorrection): void
	{
		$set = [
			'price_groups_id' => $priceGroup->id,
			'currencies_id' => $currency->id,
			'auto_recount_by_default_currency' => 1,
			'auto_recount_exchange_rate_correction' => $autoRecountExchangeRateCorrection
		];

		//if duplicate, do nothing
		try{
			$this->database->transaction(function() use ($set) {
				$this->database->table('price_groups_currencies_settings')->insert($set);
			});
		}
		catch(Exception){}
	}
}
