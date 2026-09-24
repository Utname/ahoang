<?php

namespace App\FrontModule\Traits;

use App\FrontModule\Components\ContentBlocksControl;

trait BaseContentBlocksTrait
{
	/** @var ContentBlocksControl @inject */
	public $contentBlocksControl;

	/**
	 * @return ContentBlocksControl
	 */
	protected function createComponentContentBlocks(): ContentBlocksControl
	{
		return $this->getContentBlocksControl();
	}


	/**
	 * @return ContentBlocksControl
	 */
	public function getContentBlocksControl(): ContentBlocksControl
	{
		return $this->contentBlocksControl;
	}
}
