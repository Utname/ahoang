<?php

namespace App\AdminModule\Presenters;

use Nette\Application\UI\Form;

class GalleriesPresenter extends BaseModulePresenter
{
	/** @var \GalleriesRepository @inject */
	public $repository;

	/** @var \GalleriesService @inject */
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
		$form->addTextArea('text', 'admin.terms.text');

		return parent::setFormFields($form);
	}
}
