<?php


class IndexBannersRepository extends BaseRepositoryML
{
	protected $tableName = 'index_banners';
	public $cleanCacheTags = [\CacheTags::INDEX_BANNERS];
}
