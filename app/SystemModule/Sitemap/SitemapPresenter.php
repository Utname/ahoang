<?php

namespace App\SystemModule\Presenters;

use App\SystemModule\Url;
use Nette\Application\UI\Presenter;
use Nette\Database\Table\ActiveRow;

class SitemapPresenter extends BasePresenter
{
	/** @var \PagesRepository @inject */
	public $pagesRepo;

	/** @var \BlogCategoriesRepository @inject */
	public $blogCategoriesRepo;

	/** @var \BlogRepository @inject */
	public $blogRepo;

	/**
	 * @return void
	 * @throws \Exception
	 */
	public function renderDefault()
	{
		$urls = [];

		$blogPage = NULL;

		//pages
		foreach($this->pagesRepo->findAllActive()->order('pos_left') as $row){
			if($row->system == 'blog'){
				$blogPage = $row;
			}
			$urls[] = new Url($this->globalContainer->getUrlScript()->getHostUrl().$row->full_url, new \DateTime(), 'always', 1);
		}

		//blog
		if($blogPage instanceof ActiveRow){
			//categories
			foreach($this->blogCategoriesRepo->findAllActive()->order('pos_order') as $row){
				$urls[] = new Url($this->globalContainer->getUrlScript()->getHostUrl().$blogPage->full_url.'/'.$row->slug, new \DateTime(), 'always', 1);
			}
			//items
			foreach($this->blogRepo->findAllActive()->order('date DESC') as $row){
				$urls[] = new Url($this->globalContainer->getUrlScript()->getHostUrl().$blogPage->full_url.'/'.$row->slug, new \DateTime(), 'always', 1);
			}
		}

		$this->template->urls = $urls;

		$this->getHttpResponse()->setContentType('text/xml');

	}
}
