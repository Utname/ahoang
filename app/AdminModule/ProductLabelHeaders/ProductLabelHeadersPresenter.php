<?php

namespace App\AdminModule\Presenters;

use Nette\Application\UI\Form;

class ProductLabelHeadersPresenter extends BaseModulePresenter
{
	/** @var \ProductLabelHeadersRepository @inject */
	public $repository;

	/** @var \ProductLabelHeadersService @inject */
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

		return parent::setFormFields($form);
	}
}
