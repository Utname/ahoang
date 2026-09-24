<?php

namespace App\AdminModule\Traits;

use Nette\Application\UI\Form;

trait ContentParametersTrait
{
	/** @var \ContentParametersRepository @inject */
	public $contentParametersRepo;


	/**
	 * @return Form
	 */
	protected function createComponentContentParametersForm(): Form
	{
		$form = new Form();
		$form->setTranslator($this->translator);

		$form->addText('id');
		$form->addText('name', 'admin.terms.name', 50, 255)->setRequired(TRUE);
		$form->addText('system', 'admin.terms.system', 50, 255)->setRequired(TRUE);
		$form->addSelect('types_id', $this->trans('admin.terms.type'), $this->parameterTypesPairs)->setTranslator(NULL);

		$form->addSubmit('submit', 'admin.terms.save');

		$form->onSuccess[] = [$this, 'processContentParametersForm'];

		return $form;
	}


	/**
	 * @param Form $form
	 */
	public function processContentParametersForm(Form $form): void
	{
		$values = $form->getValues();

		$set = [
			'contents_id' => $this->entity->id,
			'types_id' => $values->types_id,
			'name' => $values->name,
			'system' => $values->system
		];

		if ($values->id && $item = $this->contentParametersRepo->findEntity($values->id)) {
			$item->setValues($set);
			$this->contentParametersRepo->update($item);
		}
		else {
			$this->contentParametersRepo->insert($set);
		}

		$this->cacheService->deleteByTags($this->contentParametersRepo->cleanCacheTags);

		$this->setParametersFormFields($this->getComponent('editForm'));

		$this->flashMessage($this->trans('admin.messages.successSave'), 'success');

		$this->redrawControl('contentParametersList');
		$this->redrawControl('contentParametersValues');
		$this->redrawControl('flashMessages');
	}


	/**
	 *
	 */
	public function handleCreateContentParameter(): void
	{
		$this['contentParametersForm']->setDefaults([
			'id' => NULL
		], TRUE);

		$this->redrawControl('modalWrapper');
		$this->redrawControl('contentParametersForm');
		$this->redrawControl('flashMessages');
	}


	/**
	 * @param int $itemId
	 */
	public function handleEditContentParameter(int $itemId): void
	{
		$item = $this->contentParametersRepo->findEntity($itemId);

		$this['contentParametersForm']->setDefaults([
			'id' => $item->id,
			'name' => $item->name,
			'system' => $item->system,
			'types_id' => $item->types_id
		], TRUE);

		$this->redrawControl('modalWrapper');
		$this->redrawControl('contentParametersForm');
		$this->redrawControl('flashMessages');
	}


	/**
	 * @param int $itemId
	 */
	public function handleCopyContentParameter(int $itemId): void
	{
		$item = $this->contentParametersRepo->findEntity($itemId);

		$this['contentParametersForm']->setDefaults([
			'id' => NULL,
			'name' => $item->name,
			'system' => $item->system,
			'types_id' => $item->types_id
		], TRUE);

		$this->redrawControl('modalWrapper');
		$this->redrawControl('contentParametersForm');
		$this->redrawControl('flashMessages');
	}


	/**
	 * @param int $itemId
	 */
	public function handleDeleteContentParameter(int $itemId): void
	{
		$this->contentParametersRepo->delete($itemId);

		$this->cacheService->deleteByTags($this->contentParametersRepo->cleanCacheTags);

		$this->flashMessage($this->trans('admin.messages.successDelete'), 'success');

		$this->redrawControl('contentParametersList');
		$this->redrawControl('contentParametersValues');
		$this->redrawControl('flashMessages');
	}


	/**
	 * @param int $itemId
	 * @param int $aboveId
	 */
	public function handleMoveContentParameterAbove(int $itemId, int $aboveId): void
	{
		if ($this->contentParametersRepo->isSortable()) {
			$this->contentParametersRepo->moveAbove($itemId, $aboveId);
			$this->cacheService->deleteByTags($this->contentParametersRepo->cleanCacheTags);
		}

		$this->redrawControl('contentParametersList');
		$this->redrawControl('contentParametersValues');
		$this->redrawControl('flashMessages');
	}


	/**
	 * @param int $itemId
	 * @param int $belowId
	 */
	public function handleMoveContentParameterBelow(int $itemId, int $belowId): void
	{
		if ($this->contentParametersRepo->isSortable()) {
			$this->contentParametersRepo->moveBelow($itemId, $belowId);
			$this->cacheService->deleteByTags($this->contentParametersRepo->cleanCacheTags);
		}

		$this->redrawControl('contentParametersList');
		$this->redrawControl('contentParametersValues');
		$this->redrawControl('flashMessages');
	}


	/**
	 * @param int $itemId
	 * @param string|null $data
	 */
	public function handleSetContentParametersData(int $itemId, string $data = NULL): void
	{
		if (!$item = $this->contentParametersRepo->findEntity($itemId)) {
			return;
		}

		$item->data = $data;
		$this->contentParametersRepo->update($item);
		$this->cacheService->deleteByTags($this->contentParametersRepo->cleanCacheTags);

		$this->redrawControl('contentParametersValues');
	}
}
