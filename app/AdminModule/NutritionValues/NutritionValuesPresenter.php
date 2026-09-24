<?php

namespace App\AdminModule\Presenters;

use Nette\Application\UI\Form;

class NutritionValuesPresenter extends BaseModulePresenter
{
	/** @var \NutritionValuesRepository @inject */
	public $repository;

	/** @var \NutritionValuesService @inject */
	public $service;


	public function actionDefault()
	{
		$this['filters-form']->addText('name', 'admin.terms.name');

		parent::actionDefault();
	}


	protected function setFormFields(Form $form): Form
	{
		$form->addText('name', 'admin.terms.name')->setRequired(TRUE);
		$form->addText('unit', 'admin.terms.unit');
		$form->addText('pos_order', 'admin.terms.posOrder')
			->setDefaultValue(0)
			->addRule(Form::FLOAT);

		return parent::setFormFields($form);
	}
}
