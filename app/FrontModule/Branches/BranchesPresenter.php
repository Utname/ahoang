<?php

namespace App\FrontModule\Presenters;

use App\FrontModule\Components\PaginatorControl;
use App\FrontModule\Traits\BaseContentBlocksTrait;
use Nette\Utils\Paginator;

class BranchesPresenter extends BasePresenter
{
	/** @var \BranchesRepository @inject */
	public $branchesRepo;

	/**
	 *
	 */
	public function actionDefault()
	{
		if ($slug = $this->getParameter('slug')) {
			$this->setView('detail');
		}

		//items select
		$items = $this->branchesRepo->findAllActive()
			->order('pos_order');

		//template variables
		$this->template->items = $this->branchesRepo->getEntities($items);
	}


	/**
	 *
	 */
	public function renderDetail()
	{
		$urlPars = explode('-', $this->getParameter('slug'));
		$item = $this->branchesRepo->findActiveEntityBy(['id' => $urlPars[0]]);
		if(!$item instanceof \BranchesEntity){
			throw new \Exception('Page not found.', 404);
		}

		$this->controlMetaInfo->setTitle($item->getMetaTitle())
			->setDescription($item->getMetaDescription())
			->setImage($item->getMetaImage());

		$this->template->item = $item;
	}
}
