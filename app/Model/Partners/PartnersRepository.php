<?php


class PartnersRepository extends BaseRepositoryML
{
	protected $tableName = 'partners';
	public $cleanCacheTags = [\CacheTags::PARTNERS];
}
