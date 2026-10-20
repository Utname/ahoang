<?php


class ProductsEntity extends BaseEntity
{
	const UNITS_SHORT = [
		self::UNITS_TYPE_PIECE => "Ks",
		self::UNITS_TYPE_LITERS => "L",
		self::UNITS_TYPE_MILLILITERS => "ml",
		self::UNITS_TYPE_KILOGRAMS => "Kg",
		self::UNITS_TYPE_GRAMS => "g",
		self::UNITS_TYPE_PACKAGE => "Balení",
		self::UNITS_TYPE_CENTIMETER => "cm",
	];
	const UNITS = [
		self::UNITS_TYPE_PIECE => "Kus",
		self::UNITS_TYPE_LITERS => "Litrů",
		self::UNITS_TYPE_MILLILITERS => "Mililitrů",
		self::UNITS_TYPE_KILOGRAMS => "Kilogramů",
		self::UNITS_TYPE_GRAMS => "Gramů",
		self::UNITS_TYPE_PACKAGE => "Balení",
		self::UNITS_TYPE_CENTIMETER => "Cm",
	];
	/** "Cena za" printed next to the label price (products.price_unit, product_labels.price_unit) */
	const PRICE_UNITS = ['Kč', 'Kč/ks', 'Kč/balení', 'Kč/kg', 'Kč/100 g', 'Kč/l'];

	const UNITS_TYPE_PIECE = 0;
	const UNITS_TYPE_LITERS = 1;
	const UNITS_TYPE_MILLILITERS = 2;
	const UNITS_TYPE_KILOGRAMS = 3;
	const UNITS_TYPE_GRAMS = 4;
	const UNITS_TYPE_PACKAGE = 5;
	const UNITS_TYPE_CENTIMETER = 6;

	const NUTRITION_REFERENCE_UNITS = [
		'g' => 'g',
		'ml' => 'ml',
		'kg' => 'kg',
		'l' => 'l',
		'porce' => 'porce',
		'ks' => 'ks',
	];

	/** @var array */
	protected $prices;

	/** @var array */
	protected $pricesLog;

	/** @var array */
	protected $productLabels;

	/** @var CountriesEntity */
	protected $country;

	/** @var array */
	protected $productCodes;


	/**
	 * @return string
	 */
	public function getUrl(): string
	{
		return '/'.$GLOBALS['lang'].'/'.$this->slug.'/'.$this->id;
	}


	/**
	 * @return string
	 */
	public function getMetaDescription(): string
	{
		return $this->meta_desc ?: strip_tags($this->perex ?: '');
	}


	/**
	 * @return array
	 */
	public function getCategoriesIds(): array
	{
		return $this->getRow()->related('products_categories_rel')->fetchPairs(NULL, 'categories_id');
	}


	/**
	 * @return array
	 */
	public function getHeadCategoriesIds(): array
	{
		return $this->getRow()
			->related('products_categories_rel')
			->where('categories.pos_level', 1)
			->fetchPairs(NULL, 'categories_id');
	}


	/**
	 * @return array
	 */
	public function getCategories(): array
	{
		$entities = [];
		$related = $this->getRow()->related('products_categories_rel')->order('categories.pos_left');

		foreach ($related as $rel) {
			$row = $rel->ref('categories');
			$entities[$row->id] = CategoriesRepository::getEntity($row, $row->related('categories_mld'));
		}

		return $entities;
	}


	/**
	 * @return CategoriesEntity|null
	 */
	public function getCategory(): ?CategoriesEntity
	{
		$category = NULL;
		foreach ($this->getCategories() as $c) {
			if (!$category || $c->pos_level > $category->pos_level) {
				$category = $c;
			}
		}

		return $category;
	}


	/**
	 * "Cena za" (products.price_unit). Read through the row: Nette's column
	 * auto-detection leaves a newly used column out of the entity's copied values.
	 */
	public function getPriceUnit(): string
	{
		$unit = $this->getRow()->price_unit ?? NULL;

		return in_array($unit, self::PRICE_UNITS, TRUE) ? $unit : 'Kč';
	}


	/** Rozmrazeno (products.rozmrazeno), a flag for all labels of the product. */
	public function isRozmrazeno(): bool
	{
		return !empty($this->getRow()->rozmrazeno);
	}


	/**
	 * @param $branchId
	 * @return mixed|null
	 */
	public function getPrice($branchId)
	{

		if (is_null($this->prices) || !isset($this->prices[$branchId][$this->id])) {
			$this->prices = [];

			foreach ($this->getRow()->related('product_prices') as $row) {
				$this->prices[$row->branches_id][$row->products_id] = ProductPricesRepository::getEntity($row);
				$this->prices[$row->branches_id][$row->products_id]->is_valid_action = FALSE;
				// labels: an action price runs also without dates (ProductPricesEntity::actionState())
				$this->prices[$row->branches_id][$row->products_id]->is_label_action = ProductPricesEntity::actionState($row->price, $row->price_action, $row->action_from, $row->action_to) === 'running';
				if ($row->action_from && $row->price_action && $row->price && $row->price > $row->price_action && ($row->action_from->format('Y-m-d') <= date('Y-m-d') && (!$row->action_to || ($row->action_to && $row->action_to->format('Y-m-d') >= date('Y-m-d'))))) {
					$this->prices[$row->branches_id][$row->products_id]->is_valid_action = TRUE;
				}
			}
		}

		if (isset($this->prices[$branchId][$this->id])) {
			return $this->prices[$branchId][$this->id];
		}

		return NULL;
	}


	/**
	 * @param $branchId
	 * @return mixed|null
	 */
	public function getPriceWithLog($branchId)
	{

		if (is_null($this->pricesLog) || !isset($this->pricesLog[$branchId][$this->id])) {
			$this->pricesLog = [];

			foreach ($this->getRow()->related('product_prices') as $row) {
				$this->pricesLog[$row->branches_id][$row->products_id] = ProductPricesRepository::getEntity($row);
				$this->pricesLog[$row->branches_id][$row->products_id]->is_valid_action = FALSE;
				$this->pricesLog[$row->branches_id][$row->products_id]->is_label_action = ProductPricesEntity::actionState($row->price, $row->price_action, $row->action_from, $row->action_to) === 'running';
				if ($row->action_from && $row->price_action && $row->price && $row->price > $row->price_action && ($row->action_from->format('Y-m-d') <= date('Y-m-d') && (!$row->action_to || ($row->action_to && $row->action_to->format('Y-m-d') >= date('Y-m-d'))))) {
					$this->pricesLog[$row->branches_id][$row->products_id]->is_valid_action = TRUE;

					if ($priceLog = $this->getRow()
						->related('product_prices_log')
						->where('price_ref > ?', 0)
						->where('branches_id', $row->branches_id)
						->order('datetime DESC')
						->limit(1)
						->fetch()) {
						$this->pricesLog[$row->branches_id][$row->products_id]->price = $priceLog->price_ref;
					}
				}
			}
		}

		if (isset($this->pricesLog[$branchId][$this->id])) {
			return $this->pricesLog[$branchId][$this->id];
		}

		return NULL;
	}


	public function getPrices(): array
	{

		if (is_null($this->prices)) {
			$this->prices = [];

			foreach ($this->getRow()->related('product_prices') as $row) {
				$this->prices[$row->branches_id][$row->currencies_id] = ProductPricesRepository::getEntity($row);
			}
		}

		return $this->prices;
	}


	/**
	 * Returns the reference price from 30-day history for the given branch,
	 * or NULL if the reference price equals the base price (or no action is active).
	 */
	public function getRefPrice($branchId): ?float
	{
		$price = $this->getPrice($branchId);
		$priceWithLog = $this->getPriceWithLog($branchId);

		if (!$price || !$priceWithLog || (!$priceWithLog->is_valid_action && empty($priceWithLog->is_label_action))) {
			return null;
		}

		$basePrice = (float)$price->price;
		$refPrice = (float)$priceWithLog->price;
		if (!$priceWithLog->is_valid_action) {
			// an action without dates (labels): getPriceWithLog() swaps the reference in
			// only for the website rule, so read it from the log here
			$refPrice = (float)($this->getRow()->related('product_prices_log')
				->where('price_ref > ?', 0)
				->where('branches_id', $branchId)
				->order('datetime DESC')
				->limit(1)
				->fetchField('price_ref') ?: 0);
		}

		if ($refPrice > 0 && abs($refPrice - $basePrice) > 0.01) {
			return $refPrice;
		}

		return null;
	}


	/**
	 * @param $branchId
	 * @return string|null
	 */
	public function getPercentDiscount($branchId)
	{
		$priceValues = $this->getPriceWithLog($branchId);

		if ($priceValues && $priceValues->price_action < $priceValues->price) {
			$discountPercentage = floor(((($priceValues->price - $priceValues->price_action) / $priceValues->price) * 100));

			return "-".round($discountPercentage, 2)." %";
		}
		else {
			return NULL;
		}
	}


	/**
	 * @param $branchId
	 * @param CurrenciesEntity|NULL $currency
	 * @param $priceAction
	 * @return mixed|\Nette\Database\Table\ActiveRow|null
	 */
	/*public function getPrice($branchId = NULL, CurrenciesEntity $currency = NULL, $priceAction = false)
	{
        foreach ($this->getRow()->related('product_prices') as $row) {
            if($row->branches_id == $branchId && $row->currencies_id == $currency->id){
                return ProductPricesRepository::getEntity($row);
            }
        }

        return NULL;
	}*/

	/**
	 * @param PriceGroupsEntity|null $priceGroup
	 * @param CurrenciesEntity|null $currency
	 * @return bool
	 */
	public function isInAction(?PriceGroupsEntity $priceGroup = NULL, ?CurrenciesEntity $currency = NULL): bool
	{
		if (!$variant = $this->getDefaultVariant()) {
			return FALSE;
		}

		return $variant->isInAction($priceGroup, $currency);
	}


	/**
	 * @param PriceGroupsEntity|null $priceGroup
	 * @param CurrenciesEntity|null $currency
	 * @return BaseEntity|null
	 */
	public function getHistoricalLowestPrice(?PriceGroupsEntity $priceGroup = NULL, ?CurrenciesEntity $currency = NULL): ?BaseEntity
	{
		if (!$variant = $this->getDefaultVariant()) {
			return NULL;
		}

		return $variant->getHistoricalLowestPrice($priceGroup, $currency);
	}


	/**
	 * @return string|null
	 */
	public function getImageFile(): ?string
	{
		return $this->getImage() ? $this->getImage()->file : NULL;
	}


	/**
	 * @param float|NULL $price
	 * @param int|NULL $defaultUnitsId
	 * @param int $defaultUnitsWeight
	 * @return stdClass|null
	 */
	public function getUnitValue(float $price = NULL, int $defaultUnitsId = NULL, int $defaultUnitsWeight = NULL): ?stdClass
	{
		if (is_null($price)) {
			return NULL;
		}

		$factors = [
			self::UNITS_TYPE_PIECE.'_'.self::UNITS_TYPE_PIECE => 1,
			self::UNITS_TYPE_LITERS.'_'.self::UNITS_TYPE_LITERS => 1,
			self::UNITS_TYPE_LITERS.'_'.self::UNITS_TYPE_MILLILITERS => 1000,
			self::UNITS_TYPE_MILLILITERS.'_'.self::UNITS_TYPE_LITERS => 0.001,
			self::UNITS_TYPE_MILLILITERS.'_'.self::UNITS_TYPE_MILLILITERS => 1,
			self::UNITS_TYPE_KILOGRAMS.'_'.self::UNITS_TYPE_KILOGRAMS => 1,
			self::UNITS_TYPE_KILOGRAMS.'_'.self::UNITS_TYPE_GRAMS => 1000,
			self::UNITS_TYPE_GRAMS.'_'.self::UNITS_TYPE_KILOGRAMS => 0.001,
			self::UNITS_TYPE_GRAMS.'_'.self::UNITS_TYPE_GRAMS => 1,
		];

		$defaultUnitCoverts = [
			self::UNITS_TYPE_PIECE => self::UNITS_TYPE_PIECE,
			self::UNITS_TYPE_LITERS => self::UNITS_TYPE_MILLILITERS,
			self::UNITS_TYPE_MILLILITERS => self::UNITS_TYPE_MILLILITERS,
			self::UNITS_TYPE_KILOGRAMS => self::UNITS_TYPE_GRAMS,
			self::UNITS_TYPE_GRAMS => self::UNITS_TYPE_GRAMS
		];

		$unitsId = $this->units_id;
		$defaultUnitsId = $defaultUnitsId ?: $this->default_units_id;
		$defaultUnitsWeight = $defaultUnitsWeight ?: ($this->default_units_weight ?: 100);

		if ($unitsId && !$defaultUnitsId && isset($defaultUnitCoverts[$unitsId])) {
			$defaultUnitsId = $defaultUnitCoverts[$unitsId];
		}

		if ($unitsId && $this->weight && $defaultUnitsId && isset($factors[$unitsId.'_'.$defaultUnitsId])) {
			$unit = new stdClass();

			$basicUnitCount = $this->weight * $factors[$unitsId.'_'.$defaultUnitsId];

			$unit->price = ($price / $basicUnitCount) * $defaultUnitsWeight;
			$unit->unitShort = self::UNITS_SHORT[$defaultUnitsId];
			$unit->basicUnit = self::UNITS_SHORT[$unitsId];
			$unit->unitWeight = $defaultUnitsWeight;

			return $unit;
		}

		return NULL;
	}


	/**
	 * @return mixed
	 */
	public function getCountry(): ?CountriesEntity
	{
		if (!$this->country && $this->country_origin && $row = $this->getRow()->ref('countries', "country_origin")) {
			$this->country = CountriesRepository::getEntity($row, $row->related('countries_mld'));
		}

		return $this->country;
	}


	/**
	 * @param array $branchesPairs
	 * @param string|NULL $column
	 * @return string
	 */
	public function formatBranchTooltip(array $branchesPairs = [], string $column = NULL): string
	{
		$return = [];

		if (!$column) {
			return '';
		}

		foreach ($branchesPairs as $branchesId => $branchesName) {
			$value = '-';
			if ($price = $this->getPrice($branchesId)) {
				switch ($column) {
					case 'is_action':
						$value = $price->$column ? 'Ano' : 'Ne';
						break;
					case 'price_action':
						$value = number_format(floatval($price->$column), 2, ',', ' ');
						break;
					case 'action_from':
						$value = $price->action_from?->format('d.m.Y') ?: '';
						break;
					case 'action_to':
						$value = $price->action_to?->format('d.m.Y') ?: '';
						break;
				}
			}
			$return[] = '<div class="clearfix w-200"><div class="pull-left w-100 text-left">'.$branchesName.'</div><div class="pull-left text-left">: '.$value.'</div></div>';
		}

		return implode('', $return);
	}


	/**
	 * @param int|NULL $branchesId
	 * @return array
	 */
	public function getProductLabels(int $branchesId = NULL): array
	{
		if (!$branchesId || !isset($GLOBALS['adminUser']) || !$GLOBALS['adminUser']) {
			return [];
		}

		if (is_null($this->productLabels)) {
			$this->productLabels = [];

			foreach ($this->getRow()
				->related('product_labels')
				->where('admin_users_id IS NOT NULL')
				->where('admin_users_id', $GLOBALS['adminUser']->id)
				->where('branches_id', $branchesId) as $row) {
				$this->productLabels[$row->type_id] = ProductLabelsRepository::getEntity($row);
			}
		}

		return $this->productLabels;
	}


	/**
	 * @return array
	 */
	public function getCodes(): array
	{
		if (is_null($this->productCodes)) {
			$this->productCodes = [];

			foreach ($this->getRow()
				->related('product_codes') as $row) {
				$this->productCodes[$row->id] = ProductCodesRepository::getEntity($row);
			}
		}

		return $this->productCodes;
	}


	/**
	 * @return string|null
	 */
	public function getDefaultCode(): ?string
	{
		if ($this->code_ean) {
			return $this->code_ean;
		}

		foreach ($this->getCodes() as $code) {
			if ($code->value) {
				return $code->value;
			}
		}

		return NULL;
	}
}
