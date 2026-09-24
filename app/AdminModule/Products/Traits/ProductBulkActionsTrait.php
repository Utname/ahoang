<?php

namespace App\AdminModule\Traits;


use App\AdminModule\Presenters\BulkActionsControl;
use BaseEntity;
use Nette\Database\Table\Selection;

trait ProductBulkActionsTrait
{

	/**
	 * @return void
	 */
	public function actionProductBulkActionsTrait()
	{
		$this->getBulkActionsControl()
			->setBulkActionPermissionId(BulkActionsControl::ACTION_SET_ACTIVE, 'edit')
			->setBulkActionData(BulkActionsControl::ACTION_SET_ACTIVE, $this->global['bool'])
			->addBulkActionSubmit(BulkActionsControl::ACTION_SET_ACTIVE, BulkActionsControl::SUBMIT_STATE_SAVE)
			->setBulkActionOuterCallback(BulkActionsControl::ACTION_SET_ACTIVE, function(Selection $selection, mixed $value, int $stateId){
				$itemIds = $selection->fetchPairs(NULL, 'id');
				return (bool)$this->database->table($this->repository->getTableName())->where('id', $itemIds)
					->where('is_active != ?', $value)
					->update([
						'is_active' => $value
					]);
			});

	}
}
