<?php


class SlidersRepository extends BaseRepositoryML
{
	protected $tableName = 'sliders';
	public $cleanCacheTags = [\CacheTags::SLIDERS];
}
