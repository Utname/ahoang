<?php

namespace App\AdminModule\Presenters;

use Nette\Application\UI\Form;

class IndexBannersPresenter extends BaseModulePresenter
{
	/** @var \IndexBannersRepository @inject */
	public $repository;

	/** @var \IndexBannersService @inject */
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
		$form->addSelect('types_id', $this->trans('admin.terms.displayType'), $this->global['index_banner_types'])->setTranslator(NULL);
		$form->addUpload('image', 'admin.terms.image');
		$form->addText('link', 'admin.terms.link');
		$form->addTextArea('text', 'admin.terms.text');

		return parent::setFormFields($form);
	}
}
