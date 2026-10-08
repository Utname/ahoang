<?php


class ProductLabelsEntity extends BaseEntity
{
	const ZIP_FILE_NAME = 'labels.zip';
	const ZIP_FILE_PATH = FS_DIR . '/temp/pdf';
	const TYPE_A4 = 1;
	const TYPE_A5 = 2;
	const TYPE_SIZE_1 = 3;
	const TYPE_SIZE_2 = 4;
	const TYPE_SIZE_3 = 5;
	const TYPE_SIZE_2B = 6;
	const TYPE_SIZE_2_AKCE = 7;
	const TYPE_PECIVO = 8;
	const TYPES_ARR = [
		self::TYPE_A4 => [
			'term' => 'admin.globals.productLabelTypes.'.self::TYPE_A4,
			'img' => ''
		],
		self::TYPE_A5 => [
			'term' => 'admin.globals.productLabelTypes.'.self::TYPE_A5,
			'img' => ''
		],
		self::TYPE_SIZE_1 => [
			'term' => 'admin.globals.productLabelTypes.'.self::TYPE_SIZE_1,
			'img' => ''
		],
		self::TYPE_SIZE_2 => [
			'term' => 'admin.globals.productLabelTypes.'.self::TYPE_SIZE_2,
			'img' => ''
		],
		self::TYPE_SIZE_3 => [
			'term' => 'admin.globals.productLabelTypes.'.self::TYPE_SIZE_3,
			'img' => ''
		],
		self::TYPE_SIZE_2B => [
			'term' => 'admin.globals.productLabelTypes.'.self::TYPE_SIZE_2B,
			'img' => ''
		],
		self::TYPE_SIZE_2_AKCE => [
			'term' => 'admin.globals.productLabelTypes.'.self::TYPE_SIZE_2_AKCE,
			'img' => ''
		],
		self::TYPE_PECIVO => [
			'term' => 'admin.globals.productLabelTypes.'.self::TYPE_PECIVO,
			'img' => ''
		],
	];

	const CONSUMPTION_TYPE_DATE_TO = 1;
	const CONSUMPTION_TYPE_MIN_DATE = 2;
	const CONSUMPTION_TYPES = [
		self::CONSUMPTION_TYPE_DATE_TO => 'admin.globals.consumptionTypes.'.self::CONSUMPTION_TYPE_DATE_TO,
		self::CONSUMPTION_TYPE_MIN_DATE => 'admin.globals.consumptionTypes.'.self::CONSUMPTION_TYPE_MIN_DATE,
	];

	const CONSUMPTION_DATE_TYPE_CONSUME = 1;
	const CONSUMPTION_DATE_TYPE_DURABILITY = 2;
	const CONSUMPTION_DATE_TYPES = [
		self::CONSUMPTION_DATE_TYPE_CONSUME => 'admin.globals.consumptionDateTypes.'.self::CONSUMPTION_DATE_TYPE_CONSUME,
		self::CONSUMPTION_DATE_TYPE_DURABILITY => 'admin.globals.consumptionDateTypes.'.self::CONSUMPTION_DATE_TYPE_DURABILITY,
	];

	const HEADER_TYPE_GREAT_PRICE = 1;
	const HEADER_TYPE_ACTION = 2;
	const HEADER_TYPE_FIKKO_PRICE = 3;
	const HEADER_TYPE_OUR_PRICE = 4;
	const HEADER_TYPE_SALE = 5;
	const HEADER_TYPE_ACTION_FIKKO_CARD = 6;
	const HEADER_TYPES_ARR = [
		self::HEADER_TYPE_GREAT_PRICE => [
			'term' => 'admin.globals.productLabelHeaderTypes.'.self::HEADER_TYPE_GREAT_PRICE,
		],
		self::HEADER_TYPE_ACTION => [
			'term' => 'admin.globals.productLabelHeaderTypes.'.self::HEADER_TYPE_ACTION,
		],
		self::HEADER_TYPE_FIKKO_PRICE => [
			'term' => 'admin.globals.productLabelHeaderTypes.'.self::HEADER_TYPE_FIKKO_PRICE,
		],
		self::HEADER_TYPE_OUR_PRICE => [
			'term' => 'admin.globals.productLabelHeaderTypes.'.self::HEADER_TYPE_OUR_PRICE,
		],
		self::HEADER_TYPE_SALE => [
			'term' => 'admin.globals.productLabelHeaderTypes.'.self::HEADER_TYPE_SALE,
		],
		self::HEADER_TYPE_ACTION_FIKKO_CARD => [
			'term' => 'admin.globals.productLabelHeaderTypes.'.self::HEADER_TYPE_ACTION_FIKKO_CARD,
		],
	];

	const PRICE_TYPE_CZK = 1;
	const PRICE_TYPE_CZK_100g = 2;
	const PRICE_TYPES_ARR = [
		self::PRICE_TYPE_CZK => [
			'term' => 'admin.globals.productLabelPriceTypes.'.self::PRICE_TYPE_CZK,
		],
		self::PRICE_TYPE_CZK_100g => [
			'term' => 'admin.globals.productLabelPriceTypes.'.self::PRICE_TYPE_CZK_100g,
		],
	];

	/** @var ProductsEntity */
	protected $product;

	/** @var BranchesEntity */
	protected $branch;

	/** @var CountriesEntity */
	protected $country;

	/** @var ProductLabelHeadersEntity */
	protected $header;


	/**
	 * @return ProductsEntity|null
	 */
	public function getProduct(): ?ProductsEntity
	{
		if (!$this->product && $this->products_id && $row = $this->getRow()->ref('products')) {
			$this->product = ProductsRepository::getEntity($row, $row->related('products_mld'));
		}

		return $this->product;
	}


	/**
	 * @return ProductLabelHeadersEntity|null
	 */
	public function getHeader(): ?ProductLabelHeadersEntity
	{
		if (!$this->header && $this->product_label_headers_id && $row = $this->getRow()->ref('product_label_headers')) {
			$this->header = ProductLabelHeadersRepository::getEntity($row, $row->related('product_label_headers_mld'));
		}

		return $this->header;
	}


	/**
	 * @return BranchesEntity|null
	 */
	public function getBranch(): ?BranchesEntity
	{
		if (!$this->branch && $this->branches_id && $row = $this->getRow()->ref('branches')) {
			$this->branch = BranchesRepository::getEntity($row);
		}

		return $this->branch;
	}


	/**
	 * @return CountriesEntity|null
	 */
	public function getCountry(): ?CountriesEntity
	{
		if ($this->origin_countries_id && $row = $this->getRow()->ref('countries', "origin_countries_id")) {
			$this->country = CountriesRepository::getEntity($row, $row->related('countries_mld'));
		}

		return $this->country;
	}


	/**
	 * @return bool
	 */
	public function isToPrint(): bool
	{
		return !$this->datetime_printed && $this->count;
	}
}
