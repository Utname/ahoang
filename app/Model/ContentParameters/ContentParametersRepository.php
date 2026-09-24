<?php


class ContentParametersRepository extends BaseRepositoryML
{
	protected $tableName = 'content_parameters';
	public $cleanCacheTags = [\CacheTags::CONTENTS];
}
