<?php

namespace App\AdminModule\Presenters;

use Nette\Application\UI\Form;

class BlogCategoriesPresenter extends BaseModulePresenter
{
	/** @var \BlogCategoriesRepository @inject */
	public $repository;

	/** @var \BlogCategoriesService @inject */
	public $service;

	/** @var \PagesRepository @inject */
	public $pagesRepo;


	public function startup()
	{
		parent::startup();

		$this->template->blogPage = $this->pagesRepo->findEntityBySystem('blog');
	}


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

		return parent::setFormFields($form);
	}
}
