<?php


class PriceGroupsEntity extends BaseEntity
{
	const PRICE_TYPE_INCL_VAT = 1;
	const PRICE_TYPE_EXCL_VAT = 2;

	const DISPLAY_TYPE_DEFAULT = 1;
	const DISPLAY_TYPE_BOTH = 2;

	/** @var null|array  */
	protected ?array $currenciesSettings = NULL;


	/**
	 * @return bool
	 */
	public function isDefaultInclVat(): bool
	{
		return $this->default_price_types_id === \PriceGroupsEntity::PRICE_TYPE_INCL_VAT;
	}


	/**
	 * @return bool
	 */
	public function isSecondaryPriceVisible(): bool
	{
		return $this->display_types_id === \PriceGroupsEntity::DISPLAY_TYPE_BOTH;
	}


	/**
	 * @return array
	 */
	public function getAllCurrenciesSettings(): array
	{
		if(is_null($this->currenciesSettings)){
			$this->currenciesSettings = [];
			foreach($this->getRow()->related('price_groups_currencies_settings', 'price_groups_id') as $rel){
				$this->currenciesSettings[$rel->currencies_id] = $rel->toArray();
			}
		}
		return $this->currenciesSettings;
	}
}
