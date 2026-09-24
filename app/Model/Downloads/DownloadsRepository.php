<?php


class DownloadsRepository extends BaseRepositoryML
{
	protected $tableName = 'downloads';
	public $cleanCacheTags = [\CacheTags::DOWNLOADS];
}
