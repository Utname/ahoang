<?php


class TestimonialsRepository extends BaseRepositoryML
{
	protected $tableName = 'testimonials';
	public $cleanCacheTags = [\CacheTags::TESTIMONIALS];
}
