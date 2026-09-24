<?php

namespace App\AdminModule\Presenters;

use Nette\Application\AbortException;
use Nette\Application\UI\Form;
use Nette\Database\Table\ActiveRow;
use Nette\Http\SessionSection;
use Nette\Localization\Translator;

class BulkActionsControl extends \Nette\Application\UI\Control
{

	const ACTION_DELETE = 1;
	const ACTION_SET_ACTIVE = 2;
	const ACTION_SEND_NEWSLETTER = 14;
	const ACTION_SUBSCRIBE_TO_ECOMAIL = 17;
	const ACTION_SET_XML_STRUCTURE_TYPE = 20;
	const ACTION_PRINT = 21;

	const ACTIONS = [
		self::ACTION_SET_ACTIVE => 'admin.bulkActions.actions.'.self::ACTION_SET_ACTIVE,
		self::ACTION_SET_XML_STRUCTURE_TYPE => 'admin.bulkActions.actions.'.self::ACTION_SET_XML_STRUCTURE_TYPE,
		self::ACTION_PRINT => 'admin.bulkActions.actions.'.self::ACTION_PRINT,
		self::ACTION_SEND_NEWSLETTER => 'admin.bulkActions.actions.'.self::ACTION_SEND_NEWSLETTER,
		self::ACTION_SUBSCRIBE_TO_ECOMAIL => 'admin.bulkActions.actions.'.self::ACTION_SUBSCRIBE_TO_ECOMAIL,
		self::ACTION_DELETE => 'admin.bulkActions.actions.'.self::ACTION_DELETE,
	];


	const APPLY_TO_SELECTED = 1;
	const APPLY_TO_FILTERED = 2;
	const APPLY_TO = [
		self::APPLY_TO_SELECTED => 'admin.bulkActions.applyTo.'.self::APPLY_TO_SELECTED,
		self::APPLY_TO_FILTERED => 'admin.bulkActions.applyTo.'.self::APPLY_TO_FILTERED,
	];


	const SUBMIT_STATE_SAVE = 1;
	const SUBMIT_STATE_SET = 2;
	const SUBMIT_STATE_REMOVE = 3;
	const SUBMIT_STATE_DELETE = 4;
	const SUBMIT_STATES = [
		self::SUBMIT_STATE_SAVE,
		self::SUBMIT_STATE_SET,
		self::SUBMIT_STATE_REMOVE,
		self::SUBMIT_STATE_DELETE,
	];

	/** @var string */
	protected $sessionKey = 'bulkActions';

	/** @var array */
	protected $bulkActionsCallbacks = [];

	/** @var array */
	protected $bulkActionsOuterCallbacks = [];

	/** @var array  */
	protected $bulkActionsData = [];

	/** @var array  */
	protected $bulkActionsPermissionIds = [];

	/** @var array  */
	protected $bulkActionsPrompts = [];

	/** @var array  */
	protected $bulkActionsSubmits = [];


	/**
	 * @param SessionSection $session
	 * @param Translator $translator
	 * @param \AdminPermissionsService $adminPermissionsService
	 * @param \BaseRepository $repository
	 * @param array $allowedActions
	 */
	public function __construct(protected SessionSection $session, protected Translator $translator, protected \AdminPermissionsService $adminPermissionsService, protected \BaseRepository $repository, protected array $allowedActions)
	{
	}


	/**
	 *
	 */
	public function render()
	{
		$this->template->aps = $this->adminPermissionsService;
		$this->template->repository = $this->repository;

		$this->template->setFile(__DIR__.'/BulkActions.latte');
		$this->template->render();
	}


	/**
	 *
	 */
	public function renderButton()
	{
		$this->template->aps = $this->adminPermissionsService;
		$this->template->repository = $this->repository;

		$this->template->setFile(__DIR__.'/BulkActionsButton.latte');
		$this->template->render();
	}


	/**
	 * @param $item
	 */
	public function renderCheckbox($item)
	{
		$this->template->aps = $this->adminPermissionsService;
		$this->template->item = $item;

		$this->template->setFile(__DIR__.'/BulkActionsCheckbox.latte');
		$this->template->render();
	}


	/**
	 *
	 */
	public function renderHeadCheckbox()
	{
		$this->template->setFile(__DIR__.'/BulkActionsHeadCheckbox.latte');
		$this->template->render();
	}


	/**
	 * @return Form
	 */
	protected function createComponentBulkActionsForm(): Form
	{
		$form = new Form();
		$form->setTranslator($this->translator);

		$form->addSelect('apply_to', $this->translator->translate('admin.bulkActions.form.applyTo'), self::APPLY_TO);
		$form->addSelect('action', $this->translator->translate('admin.bulkActions.form.action'), $this->getAllowedActionsPrepared())->setTranslator($this->translator);

		foreach(self::ACTIONS as $actionId => $actionName){
			if($data = $this->getBulkActionData($actionId)){
				if(is_array($data)){
					$form->addSelect('action_value__'.$actionId, $this->translator->translate('admin.bulkActions.actionsData.'.$actionId), $data)
						->setPrompt($this->getBulkActionPrompt($actionId))
						->setTranslator($this->translator);
				}
				if(is_string($data)){
					if (\DateTime::createFromFormat('d.m.Y', $data) !== false) {
						$form->addText('action_value__'.$actionId, $this->translator->translate('admin.bulkActions.actionsData.'.$actionId))->setHtmlAttribute('class', 'form-control date-picker')->setDefaultValue($data);
					}
					else{
						$form->addText('action_value__'.$actionId, $this->translator->translate('admin.bulkActions.actionsData.'.$actionId))->setHtmlAttribute('class', 'form-control')->setDefaultValue($data);
					}
				}

			}
		}

		$form->addHidden('selected_ids');

		foreach(self::SUBMIT_STATES as $submitStateId){
			$form->addSubmit('submit_'.$submitStateId, '');
		}
		$form->onSuccess[] = [$this, 'bulkActionsFormSubmitted'];

		return $form;
	}


	/**
	 * @param Form $form
	 * @throws AbortException
	 */
	public function bulkActionsFormSubmitted(Form $form)
	{
		$values = $form->getValues();

		$stateId = self::SUBMIT_STATE_SAVE;
		if(!is_bool($form->isSubmitted()) && $submittedByName = $form->isSubmitted()->getName()){
			[$nothing, $possibleStateId] = explode('_', $submittedByName);
			$possibleStateId = intval($possibleStateId);
			if(isset(self::SUBMIT_STATES[$possibleStateId])){
				$stateId = $possibleStateId;
			}
		}


		$limit = 5000;
		$offset = 0;
		$anyItemsModified = FALSE;
		do
		{
			$selection = $this->repository->findAll();
			if($values['apply_to'] == self::APPLY_TO_SELECTED){
				$itemIds = explode(',', $values['selected_ids']);
				if(!empty($itemIds)){
					$selection->where('id', $itemIds);
				}
			}
			if($values['apply_to'] == self::APPLY_TO_FILTERED){
				$this->getPresenter()['filters']->applyFilters($selection);
			}

			$selection->limit($limit, $offset);
			$itemIds = $selection->fetchPairs(NULL, 'id');
			$offset += $limit;

			if($callback = $this->getBulkActionOuterCallback($values['action'])){
				switch ($values['action']) {
					default: //other basic actions with required custom callback
						$anyItemsModifiedFromCallback = $callback($selection, isset($values['action_value__'.$values['action']]) ? $values['action_value__'.$values['action']] : NULL, $stateId);
						break;
				}
				$anyItemsModified = !$anyItemsModified ? $anyItemsModifiedFromCallback : $anyItemsModified;
			}
			else{
				/** @var ActiveRow $item */
				foreach($selection as $item){
					$anyItemsModified = !$anyItemsModified ? TRUE : $anyItemsModified;
					$entity = $this->repository::getEntity($item);
					switch ($values['action']) {
						case self::ACTION_DELETE:
							if($callback = $this->getBulkActionCallback($values['action'])){
								$callback($entity, NULL, $stateId);
							}
							else{
								$this->repository->delete($entity->id);
							}
							break;
						case self::ACTION_SET_ACTIVE:
							if($callback = $this->getBulkActionCallback($values['action'])){
								$callback($entity, $values['action_value__'.self::ACTION_SET_ACTIVE], $stateId);
							}
							else{
								$entity->is_active = $values['action_value__'.$values['action']];
								if($this->repository->isWithActiveDate()){
									$entity->is_active_from = NULL;
									$entity->is_active_to = NULL;
								}
								$this->repository->update($entity);
							}
							break;
						default: //other basic actions with required custom callback
							if($callback = $this->getBulkActionCallback($values['action'])){
								$callback($entity, isset($values['action_value__'.$values['action']]) ? $values['action_value__'.$values['action']] : NULL, $stateId);
							}
							break;
					}
				}
			}
		}
		while (count($itemIds) === $limit);


		if($anyItemsModified){
			/** @var BasePresenter $presenter */
			$presenter = $this->getPresenter();
			$presenter->cleanRelatedModuleCache();

			$this->getPresenter()->flashMessage($this->translator->translate('admin.bulkActions.form.success'), 'success');
		}
		else{
			$this->getPresenter()->flashMessage($this->translator->translate('admin.bulkActions.form.error'), 'error');
		}



		$this->session->categoryPickerSelectedIds[$this->getSessionKey()] = [];

		$this->getPresenter()->redirect('this');
	}

	/**
	 * @return array
	 */
	public function getAllowedActionsPrepared(): array
	{
		$preparedArray = [];
		foreach($this->allowedActions as $id){
			//categories don't need submit, has its own buttons
			if(isset(self::ACTIONS[$id]) && (isset($this->bulkActionsSubmits[$id]))){
				if($premissionId = $this->getBulkActionPermissionId($id)){
					if($this->adminPermissionsService->has($premissionId)){
						$preparedArray[$id] = self::ACTIONS[$id];
					}
				}
				else{
					$preparedArray[$id] = self::ACTIONS[$id];
				}
			}
		}
		return $preparedArray;
	}


	/**
	 * @param int $stateId
	 * @return bool
	 */
	public static function isStateSave(int $stateId): bool
	{
		return ($stateId == self::SUBMIT_STATE_SAVE);
	}


	/**
	 * @param int $stateId
	 * @return bool
	 */
	public static function isStateSet(int $stateId): bool
	{
		return ($stateId == self::SUBMIT_STATE_SET);
	}


	/**
	 * @param int $stateId
	 * @return bool
	 */
	public static function isStateRemove(int $stateId): bool
	{
		return ($stateId == self::SUBMIT_STATE_REMOVE);
	}


	/**
	 * @param int $stateId
	 * @return bool
	 */
	public static function isStateDelete(int $stateId): bool
	{
		return ($stateId == self::SUBMIT_STATE_DELETE);
	}


	/**
	 * @param int $stateId
	 * @return bool
	 */
	public static function isStateSaveOrSet(int $stateId): bool
	{
		return (in_array($stateId, [self::SUBMIT_STATE_SAVE, self::SUBMIT_STATE_SET]));
	}


	/**
	 * @param int $stateId
	 * @return bool
	 */
	public static function isStateRemoveOrDelete(int $stateId): bool
	{
		return (in_array($stateId, [self::SUBMIT_STATE_REMOVE, self::SUBMIT_STATE_DELETE]));
	}


	/**
	 * @param int $actionId
	 * @param mixed $data
	 * @return $this
	 */
	public function setBulkActionData(int $actionId, mixed $data): self
	{
		$this->bulkActionsData[$actionId] = $data;
		return $this;
	}


	/**
	 * @param int $actionId
	 * @return mixed
	 */
	public function getBulkActionData(int $actionId): mixed
	{
		return isset($this->bulkActionsData[$actionId]) ? $this->bulkActionsData[$actionId] : [];
	}


	/**
	 * @param int $actionId
	 * @param string $prompt
	 * @return $this
	 */
	public function setBulkActionPrompt(int $actionId, string $prompt): self
	{
		$this->bulkActionsPrompts[$actionId] = $prompt;
		return $this;
	}


	/**
	 * @param int $actionId
	 * @return mixed
	 */
	public function getBulkActionPrompt(int $actionId): mixed
	{
		return isset($this->bulkActionsPrompts[$actionId]) ? $this->bulkActionsPrompts[$actionId] : FALSE;
	}


	/**
	 * @param int $actionId
	 * @param string $permissionId
	 * @return $this
	 */
	public function setBulkActionPermissionId(int $actionId, string $permissionId): self
	{
		$this->bulkActionsPermissionIds[$actionId] = $permissionId;
		return $this;
	}


	/**
	 * @param int $actionId
	 * @return string|null
	 */
	public function getBulkActionPermissionId(int $actionId): ?string
	{
		return isset($this->bulkActionsPermissionIds[$actionId]) ? $this->bulkActionsPermissionIds[$actionId] : NULL;
	}


	/**
	 * @param int $actionId
	 * @param int $submitStateId
	 * @return $this
	 */
	public function addBulkActionSubmit(int $actionId, int $submitStateId): self
	{
		$this->bulkActionsSubmits[$actionId][] = $submitStateId;
		return $this;
	}


	/**
	 * @param int $submitStateId
	 * @return string
	 */
	public function getSubmitStateShowOn(int $submitStateId): string
	{
		$showOnActionIds = [];
		foreach($this->bulkActionsSubmits as $actionId => $submitStateIds){
			if(is_array($submitStateIds) && in_array($submitStateId, $submitStateIds)){
				$showOnActionIds[] = $actionId;
			}
		}
		return implode('|', $showOnActionIds);
	}


	/**
	 * @param int $bulkActionId
	 * @param callable $callback
	 * @return $this
	 */
	public function setBulkActionCallback(int $bulkActionId, callable $callback): self
	{
		$this->bulkActionsCallbacks[$bulkActionId] = $callback;
		return $this;
	}


	/**
	 * @param int $bulkActionId
	 * @return callable|null
	 */
	public function getBulkActionCallback(int $bulkActionId): ?callable
	{
		return isset($this->bulkActionsCallbacks[$bulkActionId]) ? $this->bulkActionsCallbacks[$bulkActionId] : NULL;
	}


	/**
	 * @param int $bulkActionId
	 * @param callable $callback
	 * @return $this
	 */
	public function setBulkActionOuterCallback(int $bulkActionId, callable $callback): self
	{
		$this->bulkActionsOuterCallbacks[$bulkActionId] = $callback;
		return $this;
	}


	/**
	 * @param int $bulkActionId
	 * @return callable|null
	 */
	public function getBulkActionOuterCallback(int $bulkActionId): ?callable
	{
		return isset($this->bulkActionsOuterCallbacks[$bulkActionId]) ? $this->bulkActionsOuterCallbacks[$bulkActionId] : NULL;
	}


	/**
	 * @return string
	 */
	public function getSessionKey(): string
	{
		return $this->sessionKey;
	}


	/**
	 * @param string $sessionKey
	 * @return $this
	 */
	public function setSessionKey(string $sessionKey): self
	{
		$this->sessionKey = $sessionKey;
		return $this;
	}



}
