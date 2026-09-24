<?php

use Nette\Database\Table\ActiveRow;

class CurrenciesEntity extends BaseEntity
{
	const CZK_ID = 1;
	const ROUNDING_MATH = 1;
	const ROUNDING_UP = 2;
	const ROUNDING_DOWN = 3;
	const SYMBOL_POSITION_AFTER = 1;
	const SYMBOL_POSITION_BEFORE = 2;
	const DECIMALS_SEPARATOR_COMMA = 1;
	const DECIMALS_SEPARATOR_POINT = 2;
	const THOUSANDS_SEPARATOR_SPACE = 1;
	const THOUSANDS_SEPARATOR_COMMA = 2;
	const THOUSANDS_SEPARATOR_POINT = 3;
	const THOUSANDS_SEPARATOR_NONE = 4;
	const EXCHANGE_RATE_SOURCE_FIX = 1;
	const EXCHANGE_RATE_SOURCE_CNB = 2;

	/** @var array */
	protected array $priceGroupSettings = [];


	/**
	 * @return bool
	 */
	public function canBeDeleted(): bool
	{
		$invoiceDefaultCurrencyId = $GLOBALS['settings']->invoices_default_currencies_id ?? 0;

		return !$this->is_default && $invoiceDefaultCurrencyId != $this->id;
	}


	/**
	 * @param PriceGroupsEntity $priceGroupsEntity
	 * @return mixed
	 */
	public function isAutoRecount(PriceGroupsEntity $priceGroupsEntity)
	{
		return $this->getPriceGroupSettings($priceGroupsEntity, 'auto_recount_by_default_currency');
	}


	/**
	 * @param PriceGroupsEntity $priceGroupsEntity
	 * @return mixed
	 */
	public function getAutoRecountExchangeRateCorrection(PriceGroupsEntity $priceGroupsEntity): mixed
	{
		return $this->getPriceGroupSettings($priceGroupsEntity, 'auto_recount_exchange_rate_correction');
	}


	/**
	 * @param PriceGroupsEntity $priceGroupsEntity
	 * @param string $key
	 * @return mixed
	 */
	public function getPriceGroupSettings(PriceGroupsEntity $priceGroupsEntity, string $key): mixed
	{
		if(!isset($this->priceGroupSettings[$priceGroupsEntity->id])){
			$this->priceGroupSettings[$priceGroupsEntity->id] = [];

			$row = $this->getRow()
				->related('price_groups_currencies_settings', 'currencies_id')
				->where('price_groups_id', $priceGroupsEntity->id)
				->limit(1)->fetch();

			if($row instanceof ActiveRow){
				$this->priceGroupSettings[$priceGroupsEntity->id] = $row->toArray();
			}
		}

		if(isset($this->priceGroupSettings[$priceGroupsEntity->id][$key])){
			return $this->priceGroupSettings[$priceGroupsEntity->id][$key];
		}
		return NULL;
	}


	/**
	 * @param string $date
	 * @return float|null
	 */
	public function getExchangeRateFromDate(string $date): ?float
	{
		$row = $this->getRow()->related('exchange_rates_history')->where('date', $date)->order('id DESC')->limit(1)->fetch();

		return $row?->exchange_rate;
	}
}
