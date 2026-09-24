<?php

use Nette\Database\Explorer;
use Nette\SmartObject;

class PriceGroupsService
{
	use SmartObject;
	use PriceGroupsServiceSettingsTrait;

	/**
	 * @param Explorer $database
	 * @param PriceGroupsRepository $priceGroupsRepo
	 */
	public function __construct(
		protected Explorer $database,
		protected PriceGroupsRepository $priceGroupsRepo,
	)
	{
	}


	/**
	 * @param PriceGroupsEntity $priceGroup
	 * @return bool
	 */
	public function setAsDefault(PriceGroupsEntity $priceGroup): bool
	{
		$this->database->transaction(function() use ($priceGroup) {
			if ($defaultPriceGroup = $this->priceGroupsRepo->findDefault()) {
				$defaultPriceGroup->is_default = FALSE;
				$this->priceGroupsRepo->update($defaultPriceGroup);
			}

			$priceGroup->is_default = TRUE;
			$this->priceGroupsRepo->update($priceGroup);
		});

		return TRUE;
	}


	/**
	 * Disabled: every table this touched (product_prices.price_groups_id, shipping_type_prices,
	 * payment_method_prices, property_option_default_prices) either lacks the referenced column or
	 * doesn't exist at all in the current schema (leftover from an abandoned pricing refactor) — calling
	 * this unconditionally fataled the moment a Price Group was copied in Admin. No-op until a real,
	 * schema-matching copy mechanism is built.
	 * @param PriceGroupsEntity $copyFromEntity
	 * @param PriceGroupsEntity $copyToEntity
	 * @return void
	 */
	public function copyPrices(PriceGroupsEntity $copyFromEntity, PriceGroupsEntity $copyToEntity): void
	{
	}
}
