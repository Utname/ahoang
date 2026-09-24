<?php

namespace App\FrontModule\Components;

use LinksRepository;
use Nette\Utils\Strings;

class LinksControl extends BaseControl
{
	/** @var array */
	protected $links;


	/**
	 * @param LinksRepository $linksRepo
	 * @param \CacheService $cacheService
	 */
	public function __construct(protected LinksRepository $linksRepo, protected \CacheService $cacheService)
	{
	}


	/**
     *
     */
    public function render($params = [])
    {
        $this->template->type = (isset($params['type']) && $params['type']) ? $params['type'] : 'unset';

        parent::render();
    }


	/**
	 * @return array
	 * @throws \Throwable
	 */
	public function getLinks(): array
	{
		$this->links = $this->cacheService->load($this->linksRepo->getTableName());

		if(is_null($this->links))
		{
			$this->links = $this->cacheService->saveDatabaseSelection(
				$this->linksRepo->getTableName(),
				$this->linksRepo->findAllActive('pos_order'),
				[\CacheTags::LINKS]
			);
		}

		return $this->links;
	}


	/**
	 * @param string $icon
	 * @param string|null $type
	 * @return string
	 */
	public function getIcon(string $icon, string $type = NULL): string
	{
		return $type ? str_replace(['fas', 'fab', 'far', 'fal'], $type, $icon) : $icon;
	}


	/**
	 * @param string $name
	 * @return string
	 */
	public function getClassName(string $name): string
	{
		$name = Strings::webalize($name);
		$name = str_replace('-', '', $name);
		return $name;
	}





}
