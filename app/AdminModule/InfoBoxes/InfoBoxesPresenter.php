<?php

namespace App\AdminModule\Presenters;

use Nette\Application\UI\Form;

class InfoBoxesPresenter extends BaseModulePresenter
{
	/** @var \InfoBoxesRepository @inject */
	public $repository;

	/** @var \InfoBoxesService @inject */
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
		$form->addText('icon', 'admin.terms.icon');

		$form->addText('title', 'admin.terms.title');
		$form->addTextArea('text', 'admin.terms.text');

		return parent::setFormFields($form);
	}
}
