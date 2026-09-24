<?php

namespace App\SystemModule\Presenters;

use App\SystemModule\Traits\BaseCronTrait;

class CronPresenter extends BasePresenter
{
	use BaseCronTrait;

	/** @var \ProductPricesRepository @inject */
	public $productPricesRepo;

	/** @var \ProductsService @inject */
	public $productsService;


	/**
	 * @return void
	 */
	public function processProductPrices(): void
	{
		$today = date('Y-m-d');
		$condition = 'price IS NOT NULL AND price_action IS NOT NULL AND price_action > 0 AND price > price_action AND action_from IS NOT NULL AND action_from <= ? AND (action_to IS NULL OR action_to >= ?)';

		// 1) Activate: rows that should be in action now but have is_action = FALSE
		$toActivate = $this->productPricesRepo->findAll()
			->where('is_action = ?', 0)
			->where($condition, $today, $today);

		$activateIds = [];
		foreach ($toActivate as $row) {
			// Log only if we have a valid action price
			if ($row->price_action !== null && (float)$row->price_action > 0) {
				$this->productsService->logProductPrice((int)$row->products_id, (int)$row->branches_id, (int)$row->currencies_id, (float)$row->price_action, (float)$row->price, $row->action_from?->format('Y-m-d'), $row->action_to?->format('Y-m-d'));
			}
			$activateIds[] = (int)$row->id;
		}
		if (!empty($activateIds)) {
			$this->productPricesRepo->findAll()->where('id', $activateIds)->update(['is_action' => TRUE]);
		}

		// 2) Deactivate: rows that are currently marked as action but shouldn't be now
		$toDeactivate = $this->productPricesRepo->findAll()
			->where('is_action = ?', 1)
			->where('NOT (' . $condition . ')', $today, $today);

		$deactivateIds = [];
		foreach ($toDeactivate as $row) {
			// Log only if we have a base price to switch to
			if ($row->price !== null) {
				$this->productsService->logProductPrice((int)$row->products_id, (int)$row->branches_id, (int)$row->currencies_id, (float)$row->price, (float)$row->price_action, $row->action_from?->format('Y-m-d'), $row->action_to?->format('Y-m-d'));
			}
			$deactivateIds[] = (int)$row->id;
		}
		if (!empty($deactivateIds)) {
			$this->productPricesRepo->findAll()->where('id', $deactivateIds)->update(['is_action' => FALSE]);
		}

		self::sendInfoMessage('Product prices processed');
	}
}
