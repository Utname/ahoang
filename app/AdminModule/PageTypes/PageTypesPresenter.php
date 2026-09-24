<?php

namespace App\AdminModule\Presenters;

use Nette\Application\UI\Form;

class PageTypesPresenter extends BaseModulePresenter
{
	/** @var \PageTypesRepository @inject */
	public $repository;

	/** @var \PageTypesService @inject */
	public $service;

	/** @var array */
	protected $moduleBulkActions = [
		BulkActionsControl::ACTION_DELETE,
	];


	/**
	 *
	 */
	public function actionDefault()
	{
		$this['filters-form']->addText('name', 'admin.terms.name');
		$this['filters-form']->addText('presenter', 'admin.terms.presenter');
		$this['filters-form']->addText('action', 'admin.terms.action');

		parent::actionDefault();
	}


	/**
	 * @param Form $form
	 * @return Form
	 */
	protected function setFormFields(Form $form): Form
	{
		$form->addText('name', 'admin.terms.name')->setRequired(TRUE);
		$form->addText('presenter', 'admin.terms.presenter')->setRequired(TRUE);
		$form->addText('action', 'admin.terms.action');

		return parent::setFormFields($form);
	}
}