<?php

namespace App\AdminModule\Presenters;

use Nette\Application\UI\Form;

class PartnersPresenter extends BaseModulePresenter
{
	/** @var \PartnersRepository @inject */
	public $repository;

	/** @var \PartnersService @inject */
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
		$form->addText('link', 'admin.terms.link');

		$form->addUpload('image', 'admin.terms.image');

		return parent::setFormFields($form);
	}
}
