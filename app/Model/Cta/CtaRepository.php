<?php


class CtaRepository extends BaseRepositoryML
{
	protected $tableName = 'cta';
	public $cleanCacheTags = [\CacheTags::CTA];
}
