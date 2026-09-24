<?php

namespace App\AdminModule\Presenters;

use Nette\Application\UI\Form;

class FaqPresenter extends BaseModulePresenter
{
	/** @var \FaqRepository @inject */
	public $repository;

	/** @var \FaqService @inject */
	public $service;

	/**
	 *
	 */
	public function actionDefault()
	{
		$this['filters-form']->addText('name', 'admin.terms.fullName');

		parent::actionDefault();
	}


	/**
	 * @param Form $form
	 * @return Form
	 */
	protected function setFormFields(Form $form): Form
	{
		$form->addText('name', 'admin.terms.question')->setRequired(TRUE);

		$form->addTextArea('text', 'admin.terms.answer');

		return parent::setFormFields($form);
	}
}
