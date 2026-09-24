<?php

namespace App\AdminModule\Traits;

trait ProductPricesFiltersDropdownTrait
{
	/** @var \LabelsRepository @inject */
	public $labelsRepo;

	/** @var array */
	protected $labelsPairs = [];

	/** @var \SuppliersRepository @inject */
	public $suppliersRepo;

	/** @var array */
	protected $suppliersPairs = [];

	/** @var \BrandsRepository @inject */
	public $brandsRepo;

	/** @var array */
	protected $brandsPairs = [];

	/** @var \ProductSeriesRepository @inject */
	public $productSeriesRepo;

	/** @var array */
	protected  $productSeriesPairs = [];

	/** @var array */
	protected $typesPairs = [];

	/** @var array */
	protected $stockStatePairs = [];

	/** @var \WarrantiesRepository @inject */
	public $warrantiesRepo;

	/** @var array */
	protected $warrantiesPairs = [];

	/** @var \ProductTemplatesRepository @inject */
	public $productTemplatesRepo;

	/** @var array */
	protected $productTemplatesPairs = [];

	/**
	 * @return void
	 */
	public function startupProductPricesFiltersDropdownTrait()
	{
		$this->labelsPairs = $this->labelsRepo->findAll()->order('pos_order')->fetchPairs('id', 'name');
		$this->suppliersPairs = $this->suppliersRepo->findAll()->order('name')->fetchPairs('id', 'name');
		$this->brandsPairs = $this->brandsRepo->findAll()->order('name')->fetchPairs('id', 'name');
		$this->productSeriesPairs = $this->productSeriesRepo->findAll()->order('name')->fetchPairs('id', 'name');
		$this->typesPairs = $this->global['product_types'];
		$this->stockStatePairs = $this->global['stock_states'];
		$this->warrantiesPairs = $this->warrantiesRepo->findAll()->order('pos_order')->fetchPairs('id', 'name');
		$this->productTemplatesPairs = $this->productTemplatesRepo->findAll()->order('pos_order')->fetchPairs('id', 'name');
	}


	/**
	 * @return void
	 */
	public function actionDefaultProductPricesFiltersDropdownTrait()
	{
		$this['filters-form']->addText('custom__name');
		$this['filters-form']->addText('custom__code');
		$this['filters-form']->addText('custom__ean');

		$labels = [];
		foreach($this->labelsPairs as $id => $name){
			$labels[$id.'_1'] = $name.' - '. $this->trans('admin.globals.bool.true');
			$labels[$id.'_0'] = $name.' - '. $this->trans('admin.globals.bool.false');
		}
		$this['filters-form']->addSelect('custom__label', NULL, $labels)->setPrompt('-');

		$this['filters-form']->addSelect('custom__supplier', NULL, $this->suppliersPairs)->setPrompt('-');
		$this['filters-form']->addSelect('custom__brand', NULL, $this->brandsPairs)->setPrompt('-');
		$this['filters-form']->addSelect('custom__product_serie', NULL, $this->productSeriesPairs)->setPrompt('-');
		$this['filters-form']->addSelect('custom__type', NULL, $this->typesPairs)->setPrompt('-');
		$this['filters-form']->addSelect('custom__stock_state', '', $this->stockStatePairs)->setPrompt('-');
		$this['filters-form']->addSelect('custom__warranty', '', $this->warrantiesPairs)->setPrompt('-');
		$this['filters-form']->addSelect('custom__active', '', $this->global['active'])->setPrompt('-');
		$this['filters-form']->addSelect('custom__product_template', '', $this->productTemplatesPairs)->setPrompt('-');
		$this['filters-form']->addSelect('custom__heureka_active', '', $this->global['bool'])->setPrompt('-');
		$this['filters-form']->addSelect('custom__zbozi_active', '', $this->global['bool'])->setPrompt('-');
		$this['filters-form']->addSelect('custom__has_variants', '', $this->global['bool'])->setPrompt('-');
	}


	/**
	 * @param $selection
	 * @param $filters
	 * @return void
	 */
	public function setCustomFiltersProductPricesFiltersDropdownTrait($selection, $filters)
	{
		if (isset($filters['name']) && $filters['name']) {
			$like = '%'.$filters['name'].'%';
			$selection->where('product_variants.products.id IN (SELECT products_id FROM products_mld WHERE name LIKE ?) OR name LIKE ?', $like, $like);
		}

		if (isset($filters['code']) && $filters['code']) {
			$like = '%'.$filters['code'].'%';
			$selection->where('product_variants.products.code LIKE ?', $like);
		}

		if (isset($filters['ean']) && $filters['ean']) {
			$like = '%'.$filters['ean'].'%';
			$selection->where('product_variants.products.code_ean LIKE ?', $like);
		}

		if (isset($filters['label']) && $filters['label']) {
			[$labelId, $state] = explode('_', $filters['label']);
			$selection->where('product_variants.products.id '.($state ? 'IN' : 'NOT IN').' (SELECT products_id FROM products_labels_rel WHERE labels_id = ?)', $labelId);
		}

		if (isset($filters['supplier']) && $filters['supplier']) {
			$selection->where('product_variants.products.suppliers_id', $filters['supplier']);
		}

		if (isset($filters['brand']) && $filters['brand']) {
			$selection->where('product_variants.products.brands_id', $filters['brand']);
		}

		if (isset($filters['product_serie']) && $filters['product_serie']) {
			$selection->where('product_variants.products.product_series_id', $filters['product_serie']);
		}

		if (isset($filters['warranty']) && $filters['warranty']) {
			$selection->where('product_variants.products.warranties_id', $filters['warranty']);
		}

		if (isset($filters['product_template']) && $filters['product_template']) {
			$selection->where('product_variants.products.product_templates_id', $filters['product_template']);
		}

		if (isset($filters['heureka_active'])) {
			$selection->where('product_variants.products.heureka_is_active', $filters['heureka_active']);
		}

		if (isset($filters['zbozi_active'])) {
			$selection->where('product_variants.products.zbozi_is_active', $filters['zbozi_active']);
		}

		if (isset($filters['has_variants'])) {
			$selection->where('product_variants.products.has_variants', $filters['has_variants']);
		}

		if (isset($filters['type']) && $filters['type']) {
			$selection->where('product_variants.products.types_id', $filters['type']);
		}

		if (isset($filters['stock_state'])) {
			$selection->where('product_variants.id '.($filters['stock_state'] ? 'IN' : 'NOT IN').' (SELECT product_variants_id FROM product_stocks WHERE stock > 0)');
		}

		if (isset($filters['active'])) {
			$selection->where('CASE WHEN product_variants.products.id IN (SELECT products_id FROM products_mld WHERE is_active = ? AND languages_id = ?) THEN product_variants.id IN (SELECT product_variants_id FROM product_variants_mld WHERE is_active = ? AND languages_id = ?) ELSE product_variants.products.id IN (SELECT products_id FROM products_mld WHERE is_active = ? AND languages_id = ?) END', $filters['active'], $GLOBALS['lang'], $filters['active'], $GLOBALS['lang'], $filters['active'], $GLOBALS['lang']);
		}
	}
}
