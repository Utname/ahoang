<?php

namespace App\AdminModule\Presenters;

use Nette\Application\UI\Form;

class TestimonialsPresenter extends BaseModulePresenter
{
	/** @var \TestimonialsRepository @inject */
	public $repository;

	/** @var \TestimonialsService @inject */
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
		$form->addText('name', 'admin.terms.fullName')->setRequired(TRUE);
		$form->addText('rank', 'admin.terms.rank');

		$form->addTextArea('text', 'admin.terms.text');

		$form->addUpload('image', 'admin.terms.image');

		return parent::setFormFields($form);
	}
}
