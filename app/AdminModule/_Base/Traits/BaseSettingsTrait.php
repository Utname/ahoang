<?php

namespace App\AdminModule\Traits;

trait BaseSettingsTrait
{
	/** @var \SettingsRepository @inject */
	public $settingsRepo;

	/** @var \stdClass */
	protected $settings;

	/** @var \AdminIpAddressesRepository @inject */
	public $ipAddressesRepo;

	/** @var array */
	protected $global = [];


	/**
	 *
	 */
	protected function processSettings(): void
	{
		$this->loadSettings();
		$this->createGlobalSettings();
		// TEMPORARILY DISABLED at user's request (2026-09-24) — re-enable by
		// uncommenting the line below. This is the only thing gating admin
		// access by IP; while it's off, anyone who can log in can do so from
		// any IP.
		// $this->checkIpAddress();
	}


	/**
	 *
	 */
	protected function loadSettings(): void
	{
		$this->template->settings = $GLOBALS['settings'] = $this->settings = (object)$this->settingsRepo->findAll()->fetchPairs('id', 'value');
	}


	/**
	 * @return void
	 */
	protected function checkIpAddress(): void
	{
		if (isset($GLOBALS['container']) && $GLOBALS['container']->isDevMode()) {
			return;
		}

		if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
			$ip = $_SERVER['HTTP_CLIENT_IP'];
		} elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
			$ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
		} else {
			$ip = $_SERVER['REMOTE_ADDR'];
		}

		if (!$ip || !$this->ipAddressesRepo->findEntityBy(['ip_address' => $ip])) {
			echo 'Vaše ip adresa '.$ip.' není mezi povolenými.'; exit;
		}
	}


	/**
	 *
	 */
	protected function createGlobalSettings(): void
	{
		$this->global = [
			'bool' => [
				TRUE => $this->trans('admin.globals.bool.true'),
				FALSE => $this->trans('admin.globals.bool.false')
			],
			'active' => [
				TRUE => $this->trans('admin.globals.active.true'),
				FALSE => $this->trans('admin.globals.active.false')
			],
			'index_banner_types' => [
				\IndexBannersEntity::INDEX_BANNERS_TYPE_SHOW_ONCE_PER_DAY => $this->trans('admin.globals.index_banner_types.'.\IndexBannersEntity::INDEX_BANNERS_TYPE_SHOW_ONCE_PER_DAY),
				\IndexBannersEntity::INDEX_BANNERS_TYPE_SHOW_EVERY_VISIT => $this->trans('admin.globals.index_banner_types.'.\IndexBannersEntity::INDEX_BANNERS_TYPE_SHOW_EVERY_VISIT)
			],
			'price_types' => [
				\PriceGroupsEntity::PRICE_TYPE_INCL_VAT => $this->trans('admin.terms.inclVat'),
				\PriceGroupsEntity::PRICE_TYPE_EXCL_VAT => $this->trans('admin.terms.exclVat')
			]
		];

		foreach (\ProductLabelsEntity::TYPES_ARR as $id => $data) {
			$this->global['productLabelTypes'][$id] = $this->trans($data['term']);
		}

		foreach (\ProductLabelsEntity::CONSUMPTION_TYPES as $id => $term) {
			$this->global['consumptionTypes'][$id] = $this->trans($term);
		}

		foreach (\ProductLabelsEntity::CONSUMPTION_DATE_TYPES as $id => $term) {
			$this->global['consumptionDateTypes'][$id] = $this->trans($term);
		}

		foreach (\ProductLabelsEntity::HEADER_TYPES_ARR as $id => $data) {
			$this->global['productLabelHeaderTypes'][$id] = $this->trans($data['term']);
		}

		foreach (\ProductLabelsEntity::PRICE_TYPES_ARR as $id => $data) {
			$this->global['productLabelPriceTypes'][$id] = $this->trans($data['term']);
		}

		$this->template->global = $GLOBALS['global'] = $this->global;
	}
}
