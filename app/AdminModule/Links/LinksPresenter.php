<?php

namespace App\AdminModule\Presenters;

use Nette\Application\UI\Form;

class LinksPresenter extends BaseModulePresenter
{
	/** @var \LinksRepository @inject */
	public $repository;

	/** @var \LinksService @inject */
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
		$form->addText('link', 'admin.terms.link')->setRequired(TRUE);
		$form->addText('icon', 'admin.terms.icon');

		return parent::setFormFields($form);
	}
}
