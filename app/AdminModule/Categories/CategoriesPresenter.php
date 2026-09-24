<?php

namespace App\AdminModule\Presenters;

use Nette\Application\UI\Form;

class CategoriesPresenter extends BaseTreeModulePresenter
{
	/** @var \CategoriesRepository @inject */
	public $repository;

	/** @var \CategoriesService @inject */
	public $service;

	/** @var array */
	protected $moduleBulkActions = [
		BulkActionsControl::ACTION_SET_ACTIVE,
		BulkActionsControl::ACTION_DELETE,
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
		$form->addText('slug', 'admin.terms.slug')->setRequired(TRUE);
		$form->addSelect('is_active', $this->trans('admin.terms.isActive'), $this->global['bool'])->setTranslator(NULL);
		$form->addUpload('image', 'admin.terms.image');

		return parent::setFormFields($form);
	}


}
