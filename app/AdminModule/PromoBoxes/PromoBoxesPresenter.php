<?php

namespace App\AdminModule\Presenters;

use Nette\Application\UI\Form;

class PromoBoxesPresenter extends BaseModulePresenter
{
	/** @var \PromoBoxesRepository @inject */
	public $repository;

	/** @var \PromoBoxesService @inject */
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
		$form->addSelect('is_active', $this->trans('admin.terms.isActive'), $this->global['bool'])->setTranslator(NULL);
		$form->addUpload('image', 'admin.terms.image');
		$form->addTextArea('text', 'admin.terms.text');
		$form->addText('link', 'admin.terms.link');

		$form->addSelect('is_dark', $this->trans('admin.terms.darkMode'), $this->global['bool'])
			->setDefaultValue(0)
			->setTranslator(NULL);

		return parent::setFormFields($form);
	}
}
