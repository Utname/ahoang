<?php

namespace App\AdminModule\Traits;

trait BaseCacheTrait
{
	/** @var array  */
	public $cleanModuleCacheTags = [];

	/** @var \CacheService @inject */
	public $cacheService;


	/**
	 * @return void
	 */
	public function startupBaseCacheTrait()
	{
		if(isset($this->repository)){
			/** @var \BaseRepository $repository */
			$repository = $this->repository;

			if(!empty($repository->cleanCacheTags)){
				$this->cleanModuleCacheTags = array_merge($this->cleanModuleCacheTags, $repository->cleanCacheTags);
			}
		}
	}

	/**
	 *
	 */
	public function cleanRelatedModuleCache()
	{
		$this->cacheService->deleteByTags($this->cleanModuleCacheTags);
	}
}
