<?php

namespace App\AdminModule\Presenters;

use Nette\Application\UI\Form;

class EmailTemplatesPresenter extends BaseModulePresenter
{
	/** @var \EmailTemplatesRepository @inject */
	public $repository;

	/** @var \EmailTemplatesService @inject */
	public $service;

	/** @var string[]  */
	protected $moduleSortingOptions = [
		'name' => 'name',
		'name__desc' => 'name DESC',
		'subject' => 'subject',
		'subject__desc' => 'subject DESC',
		'email_from' => 'email_from',
		'email_from__desc' => 'email_from DESC',
	];

	/** @var array */
	protected $moduleBulkActions = [
		BulkActionsControl::ACTION_DELETE,
	];


	/** @var SettingsControl @inject */
	public $settingsControl;

	/**
	 *
	 */
	public function actionDefault()
	{
		$this['filters-form']->addText('name', 'admin.terms.name', 50, 255);
		$this['filters-form']->addText('subject', 'admin.terms.subject', 50, 255);
		$this['filters-form']->addText('email_from', 'admin.terms.sender', 50, 255);
		$this['filters-form']->addText('email_to', 'admin.terms.recipients', 50, 255);

		parent::actionDefault();
	}


	/**
	 * @param Form $form
	 * @return Form
	 */
	protected function setFormFields(Form $form): Form
	{
		$form->addText('name', 'admin.terms.name', 50, 255)->setRequired(TRUE);
		$form->addText('subject', 'admin.terms.subject', 50, 255)->setRequired(TRUE);
		$form->addText('email_from', 'admin.terms.sender', 50, 255);
		$form->addText('email_to', 'admin.terms.recipients', 50, 255);
		$form->addTextArea('text', 'admin.terms.template');
		$form->addText('system', 'admin.terms.system', 50, 255);

		return parent::setFormFields($form);
	}

	/**
	 * @return SettingsControl
	 */
	public function createComponentSettings(): SettingsControl
	{
		return $this->settingsControl;
	}
}
