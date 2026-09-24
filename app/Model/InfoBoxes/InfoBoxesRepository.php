<?php


class InfoBoxesRepository extends BaseRepositoryML
{
	protected $tableName = 'info_boxes';
	public $cleanCacheTags = [\CacheTags::INFO_BOXES];
}
