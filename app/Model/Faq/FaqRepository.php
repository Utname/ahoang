<?php


class FaqRepository extends BaseRepositoryML
{
	protected $tableName = 'faq';
	public $cleanCacheTags = [\CacheTags::FAQ];
}
