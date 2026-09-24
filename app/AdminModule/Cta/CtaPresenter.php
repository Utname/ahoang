<?php

namespace App\AdminModule\Presenters;

use Nette\Application\UI\Form;

class CtaPresenter extends BaseModulePresenter
{
	/** @var \CtaRepository @inject */
	public $repository;

	/** @var \CtaService @inject */
	public $service;

	/** @var string[]  */
	protected $moduleSortingOptions = [
		'name' => 'name',
		'name__desc' => 'name DESC',
	];

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

		$form->addText('title', 'admin.terms.title');
		$form->addTextArea('text', 'admin.terms.text');

		$form->addText('button_text', 'admin.terms.buttonText');
		$form->addText('button_link', 'admin.terms.buttonLink');

		$form->addText('bg_color', 'admin.terms.bg_color')->setDefaultValue('#ffffff');
		$form->addText('text_color', 'admin.terms.text_color')->setDefaultValue('#000000');

		$form->addUpload('image', 'admin.terms.image');

		return parent::setFormFields($form);
	}
}
