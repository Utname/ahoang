<?php

namespace App\AdminModule\Traits;

use App\AdminModule\Presenters\BulkActionsControl;

trait BaseBulkActionsTrait
{

	/** @var array */
	protected $moduleBulkActions = [
		BulkActionsControl::ACTION_SET_ACTIVE,
		BulkActionsControl::ACTION_DELETE,
	];


	/**
	 * @return BulkActionsControl
	 */
	protected function createComponentBulkActions(): BulkActionsControl
	{
		$control = new BulkActionsControl($this->session, $this->translator, $this->aps, $this->repository, $this->moduleBulkActions);
		$control->setSessionKey($this->getName());

		//base set active state settings
		$control
			->setBulkActionPermissionId(BulkActionsControl::ACTION_SET_ACTIVE, 'edit')
			->setBulkActionData(BulkActionsControl::ACTION_SET_ACTIVE, $this->global['bool'])
			->addBulkActionSubmit(BulkActionsControl::ACTION_SET_ACTIVE, BulkActionsControl::SUBMIT_STATE_SAVE);


		//base delete settings
		$control
			->setBulkActionPermissionId(BulkActionsControl::ACTION_DELETE, 'delete')
			->addBulkActionSubmit(BulkActionsControl::ACTION_DELETE, BulkActionsControl::SUBMIT_STATE_DELETE);
		return $control;
	}


	/**
	 * @return BulkActionsControl
	 */
	public function getBulkActionsControl(): BulkActionsControl
	{
		return $this['bulkActions'];
	}
}
