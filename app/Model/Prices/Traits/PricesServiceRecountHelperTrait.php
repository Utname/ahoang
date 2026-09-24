<?php

use Nette\Database\Table\Selection;

trait PricesServiceRecountHelperTrait
{
	/** @var null|CurrenciesEntity */
	protected ?CurrenciesEntity $defaultCurrency = NULL;

	/**
	 * @param float|NULL $price
	 * @param float|NULL $exchangeRate
	 * @param float|NULL $exchangeRateCorrection
	 * @return float|null
	 */
	protected function recountPrice(float $price = NULL, float $exchangeRate = NULL, float $exchangeRateCorrection = NULL): ?float
	{
		if(is_null($price)){
			return NULL;
		}

		if(is_null($exchangeRate)){
			return $price;
		}

		if(!is_null($exchangeRateCorrection)){
			$exchangeRate += $exchangeRateCorrection;
		}

		return $price / $exchangeRate;
	}


	/**
	 * @return CurrenciesEntity|null
	 */
	public function getDefaultCurrency(): ?CurrenciesEntity
	{
		if(is_null($this->defaultCurrency)){
			$this->defaultCurrency = $this->currenciesRepository->findDefault();
		}
		return $this->defaultCurrency;
	}


	/**
	 * @param PriceGroupsEntity|NULL $onlyForPriceGroup
	 * @return array
	 */
	public function getPriceGroupsForRecount(PriceGroupsEntity $onlyForPriceGroup = NULL): array
	{
		$priceGroups = [];
		if($onlyForPriceGroup instanceof PriceGroupsEntity){
			$priceGroups[$onlyForPriceGroup->id] = $onlyForPriceGroup;
		}
		else{
			$priceGroups = $this->priceGroupsRepository->findAllEntities();
		}
		return $priceGroups;
	}


	/**
	 * @param CurrenciesEntity|NULL $onlyForCurrency
	 * @return array
	 */
	public function getCurrenciesForRecount(CurrenciesEntity $onlyForCurrency = NULL): array
	{
		$currencies = [];
		if($onlyForCurrency instanceof CurrenciesEntity){
			$currencies[$onlyForCurrency->id] = $onlyForCurrency;
		}
		else{
			$currencies = $this->currenciesRepository->findEntitiesBy(['is_default' => FALSE]);
		}
		return $currencies;
	}


	/**
	 * @param array $currenciesSettings
	 * @param CurrenciesEntity $currency
	 * @param Selection $pricesSelection
	 * @return array
	 */
	public function getNewPriceRowSets(array $currenciesSettings, CurrenciesEntity $currency, Selection $pricesSelection): array
	{
		$exchangeRateCorrection = $currenciesSettings[$currency->id]['auto_recount_exchange_rate_correction'] ?? NULL;
		$sets = [];
		/** @var ActiveRow $priceRow */
		foreach($pricesSelection as $priceRow){
			$set = $priceRow->toArray();
			unset($set['id']);
			$set['currencies_id'] = $currency->id;
			foreach($set as $key => $value){
				if(in_array($key, ['price', 'price_action', 'value'])){
					$set[$key] = $this->recountPrice($value, $currency->exchange_rate, $exchangeRateCorrection);
				}
			}
			$sets[] = $set;
		}
		return $sets;
	}


	/**
	 * @param BaseRepository $repository
	 * @param array $sets
	 * @return void
	 */
	public function insertPriceSets(BaseRepository $repository, array $sets): void
	{
		foreach(array_chunk($sets, 500) as $indexSet){
			$this->database->table($repository->getTableName())
				->insert($indexSet);
		}
	}


	/**
	 * @param BaseRepository $repository
	 * @param PriceGroupsEntity $priceGroupsEntity
	 * @param CurrenciesEntity $currenciesEntity
	 * @return Selection
	 */
	public function getPricesSelection(BaseRepository $repository, PriceGroupsEntity $priceGroupsEntity, CurrenciesEntity $currenciesEntity): Selection
	{
		return $this->database
			->table($repository->getTableName())
			->select('*')
			->where('currencies_id', $currenciesEntity->id)
			->where('price_groups_id', $priceGroupsEntity->id);
	}
}
