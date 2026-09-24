<?php

namespace App\FrontModule\Presenters;

use App\FrontModule\Components\PaginatorControl;
use App\FrontModule\Traits\BaseContentBlocksTrait;
use Nette\Utils\Paginator;

class BlogPresenter extends BasePresenter
{
	use BaseContentBlocksTrait;

	/** @var \BlogRepository @inject */
	public $blogRepository;

	/** @var \BlogCategoriesRepository @inject */
	public $blogCategoriesRepository;

	/** @var int */
	protected $rowsOnPage = 6;

	/** @var \BlogCategoriesEntity */
	protected $category = NULL;

	/**
	 *
	 */
	public function actionDefault()
	{

		// category
		if ($categoriesId = intval($this->getParameter('category'))) {
			$this->category = $this->blogCategoriesRepository->findActiveEntityBy([
				'id' => $categoriesId
			]);
		}


		if ($slug = $this->getParameter('slug')) {
			$this->setView('detail');
		}

		//items select
		$items = $this->blogRepository->findAllActive()
			->order('date DESC');

		//categories select
		$categories = $this->blogCategoriesRepository->findAllActive('pos_order')
			->where('id IN (SELECT blog_categories_id FROM blog_blog_categories_rel WHERE blog_id IN ?)', $items->fetchPairs(NULL, 'id'));

		//active category filter & meta & breadcrumbs
		if($this->category instanceof \BlogCategoriesEntity){
			$items->where('id IN (SELECT blog_id FROM blog_blog_categories_rel WHERE blog_categories_id = ?)', $this->category->id);

			$this->controlMetaInfo->setTitle($this->category->getMetaTitle())
				->setDescription($this->category->getMetaDescription())
				->setImage($this->category->getMetaImage());


			$this->controlBreadcrumbs->addPart($this->category->getBreadcrumbs(), $this->category->getUrl())
				->setTitle($this->category->getTitle());
		}

		//pagination
		$paginator = new Paginator();
		$paginator->setItemCount($items ? $items->count() : 0);
		$paginator->setItemsPerPage($this->rowsOnPage);

		/** @var PaginatorControl $paginatorControl */
		$paginatorControl = $this['paginator'];
		$paginatorControl->setPaginator($paginator);
		$paginatorControl->applyPaging($items);


		//template variables
		$this->template->items = $this->blogRepository->getEntities($items);
		$this->template->categories = $this->blogCategoriesRepository->getEntities($categories);
		$this->template->category = $this->category;
	}


	/**
	 *
	 */
	public function renderDetail()
	{
		$item = $this->blogRepository->findActiveEntityBy(['slug' => $this->getParameter('slug')]);
		if(!$item instanceof \BlogEntity){
			throw new \Exception('Page not found.', 404);
		}

		$this->controlMetaInfo->setTitle($item->getMetaTitle())
			->setDescription($item->getMetaDescription())
			->setImage($item->getMetaImage());


		$this->controlBreadcrumbs->addPart($item->getBreadcrumbs(), $item->getUrl())
			->setTitle($item->getTitle());

		if ($content = $item->getContent()) {
			$this->getContentBlocksControl()->setContent($content);
		}

		$this->template->item = $item;
	}


	/**
	 * @return PaginatorControl
	 */
	protected function createComponentPaginator(): PaginatorControl
	{
		$control = new PaginatorControl();
		$control->setSession($this->sessionSection);
		$control->setSessionKey($this->getName().($this->category instanceof \BlogCategoriesEntity ? ('-'.$this->category->id) : ''));

		return $control;
	}
}
