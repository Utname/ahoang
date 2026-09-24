<?php


class PromoBoxesRepository extends BaseRepositoryML
{
	protected $tableName = 'promo_boxes';
	public $cleanCacheTags = [\CacheTags::PROMO_BOXES];
}
