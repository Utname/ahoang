<?php

namespace App\FrontModule\Traits;

use App\FrontModule\Components\BreadcrumbsControl;

trait BaseBreadcrumbsTrait
{
	/** @var BreadcrumbsControl */
	protected $controlBreadcrumbs;


	/**
	 *
	 */
	protected function processBreadcrumbs(): void
	{
		$this->controlBreadcrumbs = $this->getComponent('breadcrumbs');

		if ($homepage = $this->getPage('home')) {
			$this->controlBreadcrumbs->addPart($homepage->getBreadcrumbs(), $homepage->full_url);
		}

		if ($this->page && (!$homepage || $this->page->id !== $homepage->id)) {
			if ($this->page->pos_level > 1) {
				foreach ($this->pagesRepo->getEntities($this->pagesRepo->getParents($this->page)) as $pageParent) {
					$this->controlBreadcrumbs->addPart($pageParent->getBreadcrumbs(), $pageParent->full_url);
				}
			}

			$this->controlBreadcrumbs->addPart($this->page->getBreadcrumbs(), $this->page->full_url);
			$this->controlBreadcrumbs->setTitle($this->page->getTitle());
		}
	}


	/**
	 * @return BreadcrumbsControl
	 */
	protected function createComponentBreadcrumbs(): BreadcrumbsControl
	{
		$control = new BreadcrumbsControl();

		return $control;
	}
}
