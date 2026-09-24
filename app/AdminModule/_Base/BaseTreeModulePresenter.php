<?php

namespace App\AdminModule\Presenters;

use BaseEntity;
use Nette\Application\AbortException;
use Nette\Application\UI\Form;
use Nette\Database\Table\ActiveRow;

class BaseTreeModulePresenter extends BaseModulePresenter
{
	/**
	 *
	 */
	protected function startup()
	{
		parent::startup();

		if (!$this->repository->findAll()->count('*')) {
			$this->repository->makeRoot();
		}
	}


	/**
	 *
	 */
	public function renderDefault()
	{
		$this['filters-form']->setDefaults($this->filters);

		$this->selection = $this->repository->findAll()
			->where($this->repository->getTableName().'.pos_level > ?', 0)
			->order('pos_left');

		$this['filters']->applyFilters($this->selection);

		$this->entities = $this->repository->getEntities($this->selection);

		$this->template->items = $this->entities;
	}


	/**
	 * @param $id
	 * @throws AbortException
	 */
	public function actionMove($id)
	{
		$this->checkPermission('edit');

		$this->entity = $this->repository->getEntity($this->repository->find($id));
	}


	/**
	 * @param $id
	 * @return void
	 * @throws AbortException
	 */
	public function actionCopy($id)
	{
		$this->checkPermission('create');

		$this->entity = $this->repository->getEntity($this->repository->find($id));
		$parent = $this->repository->getParent($this->entity);
		if($parent instanceof ActiveRow){
			$this->parentEntity = $this->repository->getEntity($parent);
		}

		$this['editForm']->setDefaults($this->getDefaultFormValues());

		$this->setView('edit');
	}


	/**
	 * @param array $values
	 * @return BaseEntity
	 */
	protected function processCreateForm(array $values): BaseEntity
	{
		if (!$this->parentEntity && $parentRow = $this->repository->getRoot()) {
			$this->parentEntity = $this->repository->getEntity($parentRow);
		}

		return $this->repository->getEntity($this->repository->find($this->repository->insertChild($this->parentEntity, $values)->id));
	}


	/**
	 * @return Form
	 */
	protected function createComponentMoveForm(): Form
	{
		$otherItems = $this->repository->findAll()
			->where('pos_level > ?', 0)
			->where('id != ?', $this->entity->id)
			->where('NOT (pos_left > ? AND pos_right < ?)', $this->entity->pos_left, $this->entity->pos_right)
			->order('pos_left');

		$relatedItems = [];
		foreach ($this->repository->getEntities($otherItems) as $entity) {
			$relatedItems[$entity->id] = $entity->getOffsetName();
		}

		$relationshipsPairs = [
			'before' => $this->trans('admin.moveRelationships.before'),
			'to' => $this->trans('admin.moveRelationships.to')
		];

		$form = new Form();
		$form->setTranslator($this->translator);

		$form->addSelect('relationship', $this->trans('admin.terms.move'), $relationshipsPairs)->setTranslator(NULL);
		$form->addSelect('related_item', $this->trans('admin.terms.item'), $relatedItems)->setTranslator(NULL);

		$form->addSubmit('submit', 'admin.buttons.move');

		$form->onSuccess[] = [$this, 'moveFormSubmitted'];

		return $form;
	}


	/**
	 * @param Form $form
	 * @throws AbortException
	 */
	public function moveFormSubmitted(Form $form): void
	{
		$values = $form->getValues();

		if (!$values->related_item || !$relatedItemRow = $this->repository->find($values->related_item)) {
			$this->flashMessage($this->trans('admin.messages.error'), 'error');
			$this->redirect('default');
		}

		$relatedItemEntity = $this->repository->getEntity($relatedItemRow);

		if ($this->entity && $relatedItemEntity) {
			if ($values->relationship == 'before') {
				$this->repository->moveBefore($this->entity, $relatedItemEntity);
			}
			elseif ($values->relationship == 'to') {
				$this->repository->moveTo($this->entity, $relatedItemEntity);
			}

			$this->flashMessage($this->trans('admin.messages.successSave'), 'success');
			$this->redirect('default');
		}

		$this->flashMessage($this->trans('admin.messages.error'), 'error');
		$this->redirect('default');
	}
}
