<?php

namespace App\FrontModule\Presenters;

use App\FrontModule\Traits\BaseContentBlocksTrait;

class ContentPresenter extends BasePresenter
{
	use BaseContentBlocksTrait;

	/**
	 * @throws \Nette\Application\BadRequestException
	 */
	public function startup()
	{
		parent::startup();

		if ($content = $this->page->getContent()) {
			$this->getContentBlocksControl()->setContent($content);
		}
	}
}
