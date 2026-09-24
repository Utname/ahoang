<?php

namespace App\FrontModule\Presenters;

use App\FrontModule\Components\PaginatorControl;
use App\FrontModule\Traits\BaseContentBlocksTrait;
use Nette\Utils\Paginator;

class ProductsPresenter extends BasePresenter
{
	use BaseContentBlocksTrait;

	/** @var \ProductsRepository @inject */
    public $productsRepository;

	/** @var \CategoriesRepository @inject */
	public $categoriesRepository;

    /** @var \ProductsEntity */
    public $products = NULL;

    /** @var \BranchesRepository @inject */
    public $branchesRepo;

    /** @var \BranchesEntity */
    public $branches;

    /** @var \BranchesEntity */
    public $branch = NULL;

	/** @var int */
	protected $rowsOnPage = 6;

    /** @var string */
    public $slug;


	/**
	 *
	 */
	public function actionDefault()
	{

        $this->slug = pathinfo($this->getParameter('slug') ? $this->getParameter('slug') : '', PATHINFO_BASENAME);
        $this->branch = $this->branchesRepo->findActiveEntityBy(['slug' => $this->slug]);

        if($this->slug && !$this->branch){
            $this->setView('detail');
        }else{

            $this->branches = $this->branchesRepo->findAllActiveEntities('pos_order');

            $this->template->branches = $this->branches;
        }

		$this->template->branch = $this->branch;

	}


	public function renderDefault()
	{
		$productCategories = \CategoriesRepository::getEntities($this->categoriesRepository->findAll()->where('pos_level', 1)->order('pos_left'));

		if (!$this->isAjax() || ($this->sessionSection->productsCategoryId && !isset($productCategories[$this->sessionSection->productsCategoryId]))) {
			$this->sessionSection->productsCategoryId = NULL;
		}

		if ($this->branch) {
			$products = $this->branch->getProducts($this->sessionSection->productsCategoryId ?: NULL);

			$this->template->products = $products;
		}

		$this->template->productCategories = $productCategories;
		$this->template->productsCategoryId = $this->sessionSection->productsCategoryId;

		if ($this->isAjax()) {
			$this->redrawControl('products');
		}
	}


	/**
	 *
	 */
	public function renderDetail()
	{
        [$branchSlug, $productId]= explode("/", $this->getParameter("slug"));

        $this->branch = $this->branchesRepo->findActiveEntityBy(['slug' => $branchSlug]);
		$item = $this->productsRepository->findActiveEntityBy(['id' => $this->slug]);
		if(!$item instanceof \ProductsEntity){
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

		$this->template->branch = $this->branch;
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


	public function handleSetCategory($id = NULL)
	{
		$this->sessionSection->productsCategoryId = $id;
	}
}
