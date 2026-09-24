<?php
namespace App\FrontModule\Traits;



trait BaseIndexBannersTrait
{

	/** @var \IndexBannersRepository @inject */
	public $indexBannersRepo;


    /**
     * @return mixed
     * @throws \Throwable
     */
	public function getIndexBanner()
	{
		$indexBanner = $this->cacheService->load($this->indexBannersRepo->getTableName());

		if(is_null($indexBanner))
		{
			$row = $this->indexBannersRepo->findAllActive('pos_order')->limit(1)->fetch();
			$indexBanner = $this->cacheService->save(
				$this->indexBannersRepo->getTableName(),
				(object)($row ? $row->toArray() : []),
				[\CacheTags::INDEX_BANNERS]
			);
		}

		return $indexBanner;
	}

}
