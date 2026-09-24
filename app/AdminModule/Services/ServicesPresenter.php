<?php

namespace App\AdminModule\Presenters;

use Nette\Application\UI\Form;

class ServicesPresenter extends BaseModulePresenter
{
	/** @var \ServicesRepository @inject */
	public $repository;

	/** @var \ServicesService @inject */
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
		$form->addTextArea('description', 'admin.terms.description');


		$form->addText('title', 'admin.terms.title');
		$form->addTextArea('text', 'admin.terms.text');

		$form->addUpload('image', 'admin.terms.image');

		return parent::setFormFields($form);
	}
}
