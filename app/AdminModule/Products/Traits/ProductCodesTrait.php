<?php

namespace App\AdminModule\Traits;

use Nette\Application\UI\Form;

trait ProductCodesTrait
{
	/** @var \ProductCodesRepository @inject */
	public $productCodesRepo;

	/** @var \ProductCodesService @inject */
	public $productCodesService;


	/**
	 * @return Form
	 */
	protected function createComponentCodesForm(): Form
	{
		$form = new Form();
		$form->setTranslator($this->translator);

		$form->addText('id');
		$form->addText('value', 'admin.terms.value')->setRequired(TRUE);

		$form->addSubmit('submit', 'admin.terms.save');

		$form->onSuccess[] = [$this, 'processCodesForm'];

		return $form;
	}


	/**
	 * @param Form $form
	 */
	public function processCodesForm(Form $form): void
	{
		$values = $this->getEditFormValues($form, $this->getAction());

		$set = [
			'products_id' => $this->entity->id,
			'value' => $values['value'],
		];

		if ($values['id'] && $item = $this->productCodesRepo->findEntity($values['id'])) {
			$item->setValues($set);
			$this->productCodesRepo->update($item);
		}
		else {
			$item = $this->productCodesRepo->getEntity($this->productCodesRepo->insert($set));
		}

		$this->flashMessage($this->trans('admin.messages.successSave'), 'success');

		$this->redrawControl('codesList');
		$this->redrawControl('flashMessages');
	}


	/**
	 * @return void
	 */
	public function handleCreateCode(): void
	{
		$this['codesForm']->setDefaults([
			'value' => '',
		], TRUE);

		$this->redrawControl('modalWrapper');
		$this->redrawControl('codesForm');
		$this->redrawControl('flashMessages');
	}


	/**
	 * @param int $itemId
	 */
	public function handleEditCode(int $itemId): void
	{
		/** @var \ProductCodesEntity $item */
		$item = $this->productCodesRepo->findEntity($itemId);

		$this['codesForm']->setDefaults($item->getValues(TRUE), TRUE)->setEntity($item);

		$this->redrawControl('modalWrapper');
		$this->redrawControl('codesForm');
		$this->redrawControl('flashMessages');
	}


	/**
	 * @param int $itemId
	 */
	public function handleDeleteCode(int $itemId): void
	{
		$this->productCodesRepo->delete($itemId);

		$this->flashMessage($this->trans('admin.messages.successDelete'), 'success');

		$this->redrawControl('codesList');
		$this->redrawControl('flashMessages');
	}
}
