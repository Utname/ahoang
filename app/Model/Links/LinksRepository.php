<?php


class LinksRepository extends BaseRepository
{
	protected $tableName = 'links';
	public $cleanCacheTags = [\CacheTags::LINKS];
}
