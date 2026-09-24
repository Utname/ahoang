<?php

namespace App\AdminModule\Presenters;

use App\AdminModule\Traits\BlogQuickCreatorsTrait;
use BaseEntity;
use Nette\Application\UI\Form;

class BlogPresenter extends BaseModulePresenter
{
	use BlogQuickCreatorsTrait;

	/** @var \BlogRepository @inject */
	public $repository;

	/** @var \BlogService @inject */
	public $service;

	/** @var string[]  */
	protected $moduleSortingOptions = [
		'date' => 'date',
		'date__desc' => 'date DESC',
		'name' => 'name',
		'name__desc' => 'name DESC',
	];

	/** @var \BlogCategoriesRepository @inject */
	public $blogCategoriesRepo;

	/** @var array */
	protected $categoryPairs = [];


	/**
	 * @throws \ReflectionException
	 */
	public function startup()
	{
		parent::startup();
		$this->categoryPairs = $this->blogCategoriesRepo->findAll()->order('pos_order')->fetchPairs('id', 'name');
	}


	/**
	 * @return void
	 */
	public function renderEdit()
	{
		$this->template->categories = $this->blogCategoriesRepo->findAllEntities();
	}

	/**
	 *
	 */
	public function actionDefault()
	{
		$this['filters-form']->addText('blog__date', 'admin.terms.date');
		$this['filters-form']->addText('name', 'admin.terms.name');
		$this['filters-form']->addSelect('custom__category', 'admin.terms.categories', $this->categoryPairs)->setPrompt('-');

		$this['filters']->setCustomFilters(function($selection, $filters) {
			if (isset($filters['category']) && $filters['category']) {
				$selection->where('blog.id IN (SELECT blog_id FROM blog_blog_categories_rel WHERE blog_categories_id = ?)', $filters['category']);
			}
		});

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
		$form->addText('date', 'admin.terms.date')->setRequired(TRUE)->setDefaultValue(date('d.m.Y'));
		$form->addTextArea('perex', 'admin.terms.perex');
		$form->addUpload('image', 'admin.terms.image');

		return parent::setFormFields($form);
	}

	/**
	 * @param BaseEntity $entity
	 * @param Form $form
	 */
	protected function afterEntitySave(BaseEntity $entity, Form $form): void
	{
		parent::afterEntitySave($entity, $form);

		/** @var \BlogEntity $product */
		if (!$blog = $entity) {
			return;
		}

		$values = $form->getHttpData();

		// categories
		$this->service->resetCategories($blog);

		if (isset($values['categories'])) {
			foreach ($values['categories'] as $id => $val) {
				/** @var \BlogCategoriesEntity $category */
				if (!isset($val) || !$category = $this->blogCategoriesRepo->findEntity($id)) {
					continue;
				}
				$this->service->addCategory($blog, $category);
			}
		}


	}
}
