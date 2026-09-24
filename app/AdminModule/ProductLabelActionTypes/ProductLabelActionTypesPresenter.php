<?php

namespace App\AdminModule\Presenters;

use Nette\Application\UI\Form;

class ProductLabelActionTypesPresenter extends BaseModulePresenter
{
	/** @var \ProductLabelActionTypesRepository @inject */
	public $repository;

	/** @var \ProductLabelActionTypesService @inject */
	public $service;

	/**
	 *
	 */
	public function actionDefault()
	{
		$this['filters-form']->addText('name', 'admin.terms.name');

		parent::actionDefault();
	}


	/**
	 * @param Form $form
	 * @return Form
	 */
	protected function setFormFields(Form $form): Form
	{
		$form->addText('name', 'admin.terms.name')->setRequired(TRUE);
		$form->addText('value', 'admin.terms.value')->setRequired(TRUE);

		return parent::setFormFields($form);
	}
}
