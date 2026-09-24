<?php

namespace App\FrontModule\Traits;

use App\FrontModule\Components\MetaInfoControl;

trait BaseMetaInfoTrait
{
	/** @var MetaInfoControl */
	protected $controlMetaInfo;


	/**
	 *
	 */
	protected function processMetaTags(): void
	{
		$this->controlMetaInfo = $this->getComponent('metaInfo');

		$this->controlMetaInfo
			//name
			->setWebsiteName($this->globalContainer->getWebsiteName())
			//defaults
			->setDefaultTitle($this->getSettings('meta_title'))
			->setDefaultDescription($this->getSettings('meta_desc'))
			->setDefaultImage($this->getSettings('meta_image'))
			//page
			->setTitle($this->page->getMetaTitle())
			->setDescription($this->page->getMetaDescription())
			->setImage($this->page->getMetaImage())
			->setNoindex($this->page->system === 'products');
	}


	/**
	 * @return MetaInfoControl
	 */
	protected function createComponentMetaInfo(): MetaInfoControl
	{
		return new MetaInfoControl();
	}
}
