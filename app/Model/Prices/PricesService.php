<?php

use Nette\Database\Explorer;
use Nette\Database\Table\Selection;

class PricesService
{
	use \Nette\SmartObject;
	use PricesServiceRecountHelperTrait;
	use PricesServiceRecountPropertyOptionDefaultPricesTrait;
	use PricesServiceRecountPropertyOptionPricesTrait;
	use PricesServiceRecountProductPricesTrait;
	use PricesServiceRecountShippingFreeFromPricesTrait;
	use PricesServiceRecountVoucherRequiredOrderValuesTrait;
	use PricesServiceRecountVoucherDiscountValuesTrait;
	use PricesServiceProductPricesHistoryTrait;

	/**
	 * @param Explorer $database
	 * @param SettingsService $settingsService
	 * @param CurrenciesRepository $currenciesRepository
	 * @param PriceGroupsRepository $priceGroupsRepository
	 * @param ProductPricesRepository $productPricesRepo
	 */
	public function __construct(
		protected Explorer $database,
		protected SettingsService $settingsService,
		protected CurrenciesRepository $currenciesRepository,
		protected PriceGroupsRepository $priceGroupsRepository,
		protected ProductPricesRepository $productPricesRepo,
	)
	{
	}


	/**
	 * @param PriceGroupsEntity|NULL $onlyForPriceGroup
	 * @param CurrenciesEntity|NULL $onlyForCurrency
	 * @return void
	 */
	public function recountPrices(PriceGroupsEntity $onlyForPriceGroup = NULL, CurrenciesEntity $onlyForCurrency = NULL): void
	{
		$this->recountProductPrices(onlyForPriceGroup: $onlyForPriceGroup, onlyForCurrency: $onlyForCurrency);
		$this->recountPropertyOptionPrices(onlyForPriceGroup: $onlyForPriceGroup, onlyForCurrency: $onlyForCurrency);
		$this->recountPropertyOptionDefaultPrices(onlyForPriceGroup: $onlyForPriceGroup, onlyForCurrency: $onlyForCurrency);
		$this->recountVoucherRequiredOrderValues(onlyForPriceGroup: $onlyForPriceGroup, onlyForCurrency: $onlyForCurrency);
		$this->recountVoucherDiscountValues(onlyForPriceGroup: $onlyForPriceGroup, onlyForCurrency: $onlyForCurrency);
		$this->recountShippingFreeFromPrices(onlyForPriceGroup: $onlyForPriceGroup, onlyForCurrency: $onlyForCurrency);
	}


	/**
	 * Disabled: relies on a `product_prices_history` table + `product_variants`/`price_groups_id` columns
	 * that don't exist in the current schema (leftover from an abandoned pricing refactor — see
	 * PricesServiceProductPricesHistoryTrait::logProductPricesHistory()). Calling that method unconditionally
	 * fatals (undefined $productPricesHistoryRepo), which broke saving/copying a Price Group and CSV price
	 * import. No-op until that feature is actually built.
	 * @param PriceGroupsEntity|NULL $onlyForPriceGroup
	 * @param CurrenciesEntity|NULL $onlyForCurrency
	 * @return void
	 */
	public function logPricesHistory(PriceGroupsEntity $onlyForPriceGroup = NULL, CurrenciesEntity $onlyForCurrency = NULL): void
	{
	}

	/**
	 * @param string $tableName
	 * @param array $deleteBy
	 * @param array $newSets
	 * @return void
	 */
	public function updatePricesFromSets(string $tableName, array $deleteBy, array $newSets): void
	{
		$this->database->transaction(function() use ($tableName, $deleteBy, $newSets) {
			$selection = $this->database->table($tableName);
			foreach($deleteBy as $deleteByKey => $deleteByVal){
				$selection->where($deleteByKey, $deleteByVal);
			}
			$selection->delete();

			if (!empty($newSets)) {
				foreach(array_chunk($newSets, 1000) as $newSet){
					$this->database->table($tableName)->insert($newSet);
				}
			}
		});
	}
}
