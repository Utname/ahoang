<?php


class TeamRepository extends BaseRepositoryML
{
	protected $tableName = 'team';
	public $cleanCacheTags = [\CacheTags::TEAM];
}
