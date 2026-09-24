<?php
namespace App\FrontModule\Traits;

use App\FrontModule\Components\LinksControl;
use LinksRepository;

trait BaseLinksTrait
{

	/** @var LinksRepository @inject */
	public $linksRepo;


	/**
	 * @return LinksControl
	 */
	protected function createComponentLinks(): LinksControl
	{
		return new LinksControl(linksRepo: $this->linksRepo, cacheService: $this->cacheService);
	}

}
