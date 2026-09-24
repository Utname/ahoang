<?php

namespace App\AdminModule\Presenters;

use Nette\Application\UI\Form;

class AdminIpAddressesPresenter extends BaseModulePresenter
{
	/** @var \AdminIpAddressesRepository @inject */
	public $repository;

	/** @var \AdminIpAddressesService @inject */
	public $service;


	/**
	 *
	 */
	public function actionDefault()
	{
		$this['filters-form']->addText('ip_address', 'admin.terms.ipAddress');
		$this['filters-form']->addText('note', 'admin.terms.note');

		parent::actionDefault();
	}


	/**
	 * @param Form $form
	 * @return Form
	 */
	protected function setFormFields(Form $form): Form
	{
		$form->addText('ip_address', 'admin.terms.ipAddress')->setRequired(TRUE);
		$form->addText('note', 'admin.terms.note');

		return parent::setFormFields($form);
	}
}
