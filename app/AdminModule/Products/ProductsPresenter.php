<?php

namespace App\AdminModule\Presenters;

use App\AdminModule\Traits\ProductBulkActionsTrait;
use App\AdminModule\Traits\ProductCodesTrait;
use App\AdminModule\Traits\ProductCopyTrait;
use App\AdminModule\Traits\ProductFormTrait;
use App\AdminModule\Traits\ProductImportTrait;
use App\AdminModule\Traits\ProductLabelsTrait;
use App\AdminModule\Traits\ProductPricesTrait;
use App\AdminModule\Traits\ProductQuickCreatorsTrait;
use BaseEntity;
use kcfinder\image;
use Nette\Application\AbortException;
use Nette\Application\UI\Form;
use Nette\Database\Connection;
use Nette\Database\Context;
use Nette\Database\Structure;
use Nette\Forms\Controls\SelectBox;
use Nette\Http\FileUpload;
use Nette\Utils\Random;
use Nette\Utils\Strings;
use Nette\Utils\UnknownImageFileException;
use Segments\ContentsEntity;

class ProductsPresenter extends BaseModulePresenter
{
	use ProductQuickCreatorsTrait;
	use ProductBulkActionsTrait;
	use ProductCopyTrait;
	use ProductPricesTrait;
	use ProductFormTrait;
	use ProductImportTrait;
	use ProductLabelsTrait;
	use ProductCodesTrait;

	/** @var array */
	protected $moduleBulkActions = [
		BulkActionsControl::ACTION_SET_ACTIVE,
		BulkActionsControl::ACTION_DELETE,
	];

	/** @var \ProductsRepository @inject */
	public $repository;

	/** @var \ProductsService @inject */
	public $service;

	/** @var \CategoriesRepository @inject */
	public $categoriesRepo;

	/** @var \BranchesRepository @inject */
	public $branchesRepo;

	/** @var \ProductPricesRepository @inject */
	public $productPricesRepository;

	/** @var \CountriesRepository @inject */
	public $countriesRepo;

	/** @var \CurrenciesRepository @inject */
	public $currenciesRepo;

	/** @var \PricesService @inject */
	public $pricesService;

	/** @var \ProductLabelsService @inject */
	public $productLabelsService;

	/** @var \ProductLabelsRepository @inject */
	public $productLabelsRepo;

	/** @var \ProductLabelHeadersRepository @inject */
	public $productLabelHeadersRepo;

	/** @var SettingsControl @inject */
	public $settingsControl;

	/** @var \BranchesEntity */
	protected $branch;

	/** @var \CurrenciesEntity */
	public $currency;

	/** @var \ProductLabelActionTypesRepository @inject */
	public $productLabelActionTypesRepo;

	/** @var \ProductPricesLogRepository @inject */
	public $productPricesLogRepo;

	/** @var \NutritionValuesRepository @inject */
	public $nutritionValuesRepo;

	/** @var \ProductNutritionValuesRepository @inject */
	public $productNutritionValuesRepo;

	/** @var array */
	protected $branchesPairs = [];

	/** @var array */
	protected $countriesPairs = [];

	/** @var array */
	protected $branches = [];

	/** @var array */
	protected $currencies = [];

	/** @var array */
	protected $categoriesPairs = [];

	/** @var array */
	protected $categories = [];

	/** @var array */
	protected $categoriesPairsMain = [];

	/** @var array */
	protected $currenciesPairs = [];

	/** @var array */
	protected $productLabelHeadersPairs = [];

	/** @var array */
	protected $productLabelActionTypesPairs = [];

	/** @var bool */
	protected $newProductModal = FALSE;


	/**
	 * @throws \ReflectionException
	 */
	protected function startup()
	{
		parent::startup();

		// NOTE: sorting by 'price'/'stock' was removed — it relied on product_variants/product_prices.price_groups_id/
		// product_stocks, none of which exist in the current schema (leftover from an abandoned pricing refactor).
		$this->moduleSortingOptions = [
			'name' => 'name',
			'name__desc' => 'name DESC',
			'code__desc' => 'products.code DESC',
			'created_at' => 'products.created_at, products.id',
			'created_at__desc' => 'products.created_at DESC, products.id DESC',
		];

		$this->countriesPairs = $this->countriesRepo->findAll()->order('pos_order')->fetchPairs('id', 'name');

		$this->categoriesPairs = $this->categoriesRepo->findAll()
			->select('id, CONCAT(REPEAT(" - - - ", pos_level - 1), name) AS name')
			->where('pos_level')
			->order('pos_left')
			->fetchPairs('id', 'name');

		$this->categoriesPairsMain = $this->categoriesRepo->findAll()
			->where('pos_level', 1)
			->order('pos_left')
			->fetchPairs('id', 'name');

		$this->template->productLabelActionTypesPairs = $this->productLabelActionTypesPairs = $this->productLabelActionTypesRepo->findAll()->order('id')->fetchPairs('id', 'name');

		if ($this->adminUser->is_all_branches_permission) {
			$this->branches = $this->branchesRepo->findAllEntities('pos_order');
			$this->template->branchesPairs = $this->branchesPairs = $this->branchesRepo->findAll()
				->order('pos_order')
				->fetchPairs('id', 'name');
		}
		else {
			$adminUserBranchesIds = $this->adminUser->getBranchesIds();
			$this->branches = \BranchesRepository::getEntities($this->branchesRepo->findAll()
				->where('id', count($adminUserBranchesIds) ? $adminUserBranchesIds : [0])
				->order('pos_order'));
			$this->template->branchesPairs = $this->branchesPairs = $this->branchesRepo->findAll()
				->where('id', count($adminUserBranchesIds) ? $adminUserBranchesIds : [0])->order('pos_order')
				->fetchPairs('id', 'name');
		}

		if (!count($this->branches)) {
			$this->redirect('Homepage:');
		}

		$this->currencies = $this->currenciesRepo->findAllEntities('pos_order');
		$this->currenciesPairs = $this->currenciesRepo->findAll()->order('pos_order')->fetchPairs('id', 'name');
		$this->productLabelHeadersPairs = $this->productLabelHeadersRepo->findAll()->order('pos_order')->fetchPairs('id', 'name');

		$this->template->categoriesPairs = $this->categoriesPairs;
		$this->template->branches = $this->branches;
		$this->template->currencies = $this->currencies;
	}


	/**
	 *
	 */
	public function actionDefault()
	{
		if (!isset($this->session->filters[$this->getName()]['custom__branches']) || is_null($this->session->filters[$this->getName()]['custom__branches'])) {
			$this->session->filters[$this->getName()] = [];
			$this->filters['custom__branches'] = $this->session->filters[$this->getName()]['custom__branches'] = $this->adminUser->default_branches_id ?: $this->settings->default_branch;
		}

		if ($this->session->filters[$this->getName()]['custom__branches'] && !isset($this->branchesPairs[$this->session->filters[$this->getName()]['custom__branches']])) {
			$this->filters['custom__branches'] = $this->session->filters[$this->getName()]['custom__branches'] = array_key_first($this->branchesPairs);
		}

		$this->template->branch = $this->branch = $this->branchesRepo->findEntity($this->session->filters[$this->getName()]['custom__branches']);
		$this->template->currency = $this->currency = $this->globalContainer->getDefaultCurrency();

		$this['filters-form']->addSelect('custom__branches', '', $this->branchesPairs);

		$this['filters-form']->addSelect('custom__category', '', [0 => '- '.$this->trans('admin.terms.withoutCategory').' -'] + $this->categoriesPairs)
			->setPrompt('-');

		$this['filters-form']->addSelect('custom__labels', '', $this->global['bool'])->setPrompt('-');
		$this['filters-form']->addSelect('custom__is_active', '', $this->global['bool'])->setPrompt('-');
		$this['filters-form']->addSelect('custom__is_action', '', $this->global['bool'])->setPrompt('-');
		$this['filters-form']->addText('custom__name', '');

		$this['filters']->setCustomFilters(function($selection, $filters) {
			if (isset($filters['category'])) {
				if (!$filters['category']) {
					$selection->where('products.id NOT IN (SELECT products_id FROM products_categories_rel)');
				}
				else {
					$selection->where('products.id IN (SELECT products_id FROM products_categories_rel WHERE categories_id = ?)', $filters['category']);
				}
			}

			if (isset($filters['is_action'])) {
				if ($filters['is_action']) {
					$selection->where('products:product_prices.branches_id = ? AND products:product_prices.action_from IS NOT NULL AND products:product_prices.price IS NOT NULL AND products:product_prices.price_action IS NOT NULL AND products:product_prices.price_action > 0 AND products:product_prices.price > products:product_prices.price_action AND (products:product_prices.action_from >= ? OR (products:product_prices.action_to IS NOT NULL AND products:product_prices.action_to >= ?))', $filters['branches'], date('Y-m-d'), date('Y-m-d'));
				}
				else {
					$actionsIds = $this->repository->findAll()
						->where('products:product_prices.branches_id = ? AND products:product_prices.action_from IS NOT NULL AND products:product_prices.price IS NOT NULL AND products:product_prices.price_action IS NOT NULL AND products:product_prices.price_action > 0 AND products:product_prices.price > products:product_prices.price_action AND (products:product_prices.action_from >= ? OR (products:product_prices.action_to IS NOT NULL AND products:product_prices.action_to >= ?))', $filters['branches'], date('Y-m-d'), date('Y-m-d'))
						->fetchPairs(NULL, 'id');
					$selection->where('products_id NOT IN (?)', $actionsIds);
				}
			}

			if (isset($filters['name']) && $filters['name']) {
				$searchPars = [];
				foreach (explode(' ', $filters['name']) as $searchPar) {
					$searchPars[] = '%'.$searchPar.'%';
				}

				$selection->where('products.code_ean = ? OR products.id IN (SELECT products_id FROM product_codes WHERE value = ?) OR ('.implode(' AND ', array_fill(0, count($searchPars), 'products_mld.name LIKE ?')).')', $filters['name'], $filters['name'], ...$searchPars);
			}

			if (isset($filters['is_active'])) {
				$selection->where('products_mld.is_active = ?', $filters['is_active']);
			}

			if (isset($filters['labels'])) {
				if ($filters['labels']) {
					$selection->where('products.id IN (SELECT products_id FROM product_labels WHERE branches_id = ? AND datetime_printed IS NULL AND count > ?)', $filters['branches'] ?? 0, 0);
				}
				else {
					$selection->where('products.id NOT IN (SELECT products_id FROM product_labels WHERE branches_id = ? AND datetime_printed IS NULL AND count > ?)', $filters['branches'] ?? 0, 0);
				}
			}
		});

		$this['filters']->setOnFilterChange(function (FiltersControl $filtersControl, Form $filtersForm) {
			$values = $filtersForm->getValues(TRUE);

			if (isset($values['custom__name']) && $values['custom__name'] && is_numeric($values['custom__name'])) {
				$this->session->newProductModalSearch = TRUE;
				$this->session->newProductModalEan = $values['custom__name'];
			}

			$this->getPaginatorControl()->setPage(1);
			$this->redirect('this');
		});

		$this->actionProductBulkActionsTrait();

		parent::actionDefault();
	}


	/**
	 * @return void
	 */
	public function renderEdit()
	{
		$this->template->categoriesPairsMain = $this->categoriesPairsMain = $this->categoriesRepo->findAll()
			->where('pos_level', 1)
			->order('pos_left')
			->fetchPairs('id', 'name');

		$this->template->categories = $this->categories = $this->categoriesRepo->findAllEntities();
	}


	/**
	 * @return void
	 */
	public function renderDefault()
	{
		parent::renderDefault();

		$newProductModalSearch = FALSE;
		$newProductModalEan = NULL;
		$newProductModalCount = 0;
		if ($this->session->newProductModalSearch) {
			$newProductModalSearch = TRUE;
			$newProductModalEan = $this->session->newProductModalEan;
			$newProductModalCount = $this->repository->findAll()
				->where('products.code_ean = ? OR products.id IN (SELECT products_id FROM product_codes WHERE value = ?)', $newProductModalEan, $newProductModalEan)
				->count();
			$this->session->newProductModalSearch = FALSE;
			$this->session->newProductModalEan = NULL;
		}

		/** @var PaginatorControl $paginator */
		$paginator = $this['paginator'];
		$this->template->canChangePage = ($paginator->getPaginator()->getPage() < $paginator->getPaginator()->getLastPage());

		$this->template->categories = $this->categories = $this->categoriesRepo->findAllEntities();
		$this->template->newProductModal = $this->newProductModal || ($newProductModalSearch && !$newProductModalCount);
		$this->template->newProductModalEan = $newProductModalSearch && !$newProductModalCount ? $newProductModalEan : NULL;
	}


	/**
	 * @param Form $form
	 * @return Form
	 */
	protected function setFormFields(Form $form): Form
	{
		$form->addHidden('close_tab', $this->getParameter('ct') ? 1 : 0);
		$form->addText('code_ean', 'admin.terms.ean');
		$form->addText('name', 'admin.terms.name')->setRequired(TRUE);
		$form->addText('slug', 'admin.terms.slug')->setRequired(TRUE);
		$form->addSelect('country_origin', 'admin.terms.countryOrigin', $this->countriesPairs)->setTranslator(NULL);
		$form->addSelect('is_active', $this->trans('admin.terms.isActive'), $this->global['bool'])->setTranslator(NULL);
		$form->addTextArea('perex', 'admin.terms.perex');
		$form->addTextArea('text', 'admin.terms.text');

		$form->addSelect('units_id', NULL, \ProductsEntity::UNITS)->setTranslator(NULL);
		$form->addText('weight', 'admin.terms.weight')
			->setNullable()
			->addCondition(Form::FILLED, TRUE)
			->addRule(Form::FLOAT);
		$form->addSelect('default_units_id', NULL, \ProductsEntity::UNITS)->setTranslator(NULL)->setPrompt('-');
		$form->addText('default_units_weight', 'admin.terms.defaultPriceWeight')
			->setNullable()
			->addCondition(Form::FILLED, TRUE)
			->addRule(Form::FLOAT);

		$form->addText('label_note_1', 'admin.terms.note1');
		$form->addText('label_note_2', 'admin.terms.note2');
		$form->addText('label_note_3', 'admin.terms.note3');

		$form->addTextArea('ingredients', 'admin.terms.ingredients_manual');
		$form->addSelect('consumption_types_id', $this->trans('admin.terms.consumptionType'), $this->global['consumptionTypes'])
			->setTranslator(NULL)
			->setPrompt('-');

		$form->addText('nutrition_reference_amount', 'admin.terms.nutritionReferenceAmount')
			->setNullable()
			->addCondition(Form::FILLED, TRUE)
			->addRule(Form::FLOAT);
		$form->addSelect('nutrition_reference_unit', 'admin.terms.nutritionReferenceUnit', \ProductsEntity::NUTRITION_REFERENCE_UNITS)
			->setTranslator(NULL)
			->setPrompt('-');

		foreach ($this->nutritionValuesRepo->findAll()->order('pos_order, id') as $nutritionValue) {
			$form->addText('nutrition_value__'.$nutritionValue->id, $nutritionValue->name);
		}

		$i = 0;
		foreach ($this->branches as $branch) {
			foreach ($this->currencies as $currency) {
				$i++;
				$form->addText('branch_prices_'.$branch->id.'_'.$currency->id.'_price');
				$form->addText('branch_prices_'.$branch->id.'_'.$currency->id.'_price_action');
				$form->addCheckbox('branch_prices_'.$branch->id.'_'.$currency->id.'_is_action');
				$form->addText('branch_prices_'.$branch->id.'_'.$currency->id.'_action_from');
				$form->addText('branch_prices_'.$branch->id.'_'.$currency->id.'_action_to');

				if ($i > 1) {
					$form['branch_prices_'.$branch->id.'_'.$currency->id.'_price']->setHtmlAttribute('readonly', 'readonly');
				}
			}
		}

		if ($this->entity) {
			$this->setVariantFormsDefaults($form);
		}

		return parent::setFormFields($form);
	}


	protected function setVariantFormsDefaults(Form &$form): Form
	{
		// prices
		$prices = $this->entity->getPrices();

		foreach ($this->branches as $branch) {
			foreach ($this->currencies as $currency) {
				if (!isset($prices[$branch->id][$currency->id])) {
					continue;
				}

				if (!$price = $prices[$branch->id][$currency->id]) {
					continue;
				}

				$form['branch_prices_'.$branch->id.'_'.$currency->id.'_price']->setDefaultValue($price->price);
				$form['branch_prices_'.$branch->id.'_'.$currency->id.'_price_action']->setDefaultValue($price->price_action);
				$form['branch_prices_'.$branch->id.'_'.$currency->id.'_is_action']->setDefaultValue($price->is_action);
				$form['branch_prices_'.$branch->id.'_'.$currency->id.'_action_from']->setDefaultValue($price->action_from ? $price->action_from->format('d.m.Y') : '');
				$form['branch_prices_'.$branch->id.'_'.$currency->id.'_action_to']->setDefaultValue($price->action_to ? $price->action_to->format('d.m.Y') : '');
			}
		}

		$nutritionValues = $this->productNutritionValuesRepo->findBy(['products_id' => $this->entity->id])
			->fetchPairs('nutrition_values_id', 'value');
		foreach ($nutritionValues as $nutritionValueId => $value) {
			if (isset($form['nutrition_value__'.$nutritionValueId])) {
				$form['nutrition_value__'.$nutritionValueId]->setDefaultValue($value);
			}
		}

		return $form;
	}


	/**
	 * @param BaseEntity $entity
	 * @param Form $form
	 * @return void
	 * @throws \Exception
	 */
	protected function afterEntitySave(BaseEntity $entity, Form $form): void
	{
		parent::afterEntitySave($entity, $form);

		/** @var \ProductsEntity $product */
		if (!$product = $entity) {
			return;
		}

		$values = $form->getHttpData();

		$this->service->updatePrices($product, $form);

		foreach ($this->nutritionValuesRepo->findAll() as $nutritionValue) {
			$key = 'nutrition_value__'.$nutritionValue->id;
			$rawValue = isset($values[$key]) ? trim((string)$values[$key]) : '';

			$existing = $this->productNutritionValuesRepo->findEntityBy([
				'products_id' => $product->id,
				'nutrition_values_id' => $nutritionValue->id,
			]);

			if ($rawValue === '') {
				if ($existing) {
					$this->productNutritionValuesRepo->delete($existing->id);
				}
				continue;
			}

			if ($existing) {
				$existing->setValue('value', $rawValue);
				$this->productNutritionValuesRepo->update($existing);
			}
			else {
				$this->productNutritionValuesRepo->insert([
					'products_id' => $product->id,
					'nutrition_values_id' => $nutritionValue->id,
					'value' => $rawValue,
				]);
			}
		}

		// categories
		$this->service->resetCategories([$product->id]);

		if (isset($values['categories'])) {
			foreach ($values['categories'] as $id => $val) {
				/** @var \CategoriesEntity $category */
				if (!isset($val) || !$category = $this->categoriesRepo->findEntity($id)) {
					continue;
				}

				$this->service->addCategory($product, $category);
			}
		}

		if (isset($values['image_external_url']) && $values['image_external_url']) {
			if ($imageContent = @file_get_contents($values['image_external_url'], FALSE, stream_context_create([
				"ssl" => [
					"verify_peer" => FALSE,
					"verify_peer_name" => FALSE,
				],
			]))) {
				$extension = Strings::lower(pathinfo($values['image_external_url'], PATHINFO_EXTENSION));
				$filename = Strings::substring(md5(microtime().Random::generate(12)), 0, 16).'.'.$extension;
				file_put_contents(FS_DIR.'/images/'.\ImagesService::getImageDir($filename).'/'.$filename, $imageContent);
				$file = new \stdClass();
				$file->file = $filename;

				$entity->images .= ($entity->images ? '; ' : '').json_encode($file);
				$this->repository->update($entity);
			}
		}

		if (isset($values['close_tab']) && intval($values['close_tab'])) {
			$this->redirect('edit', [$this->entity->id, ($form['submit_close']->isSubmittedBy() ? 'fct' : 'ct') => 1]);
		}
	}


	/**
	 * @return SettingsControl
	 */
	public function createComponentSettings(): SettingsControl
	{
		return $this->settingsControl;
	}


	/**
	 * @return void
	 * @throws AbortException
	 */
	public function handleReload()
	{
		if ($this->session->labelsIsPrint) {
			$this->session->labelsIsPrint = NULL;
			$this->redirect('this');
		}

		exit;
	}


	/**
	 * @param int|null $priceGroupsId
	 * @return void
	 */
	public function handleProductsAutocomplete(): void
	{
		$search = $this->getParameter('term');

		$data = [];

		foreach (\ProductsRepository::getEntities($this->repository->findAll()
			->where('code_ean LIKE ? OR id IN (SELECT products_id FROM product_codes WHERE value LIKE ?) OR MATCH(products_mld.name) AGAINST(? IN NATURAL LANGUAGE MODE)', "%".$search."%", "%".$search."%", $search)
			->limit(20)) as $product) {
			$data[] = [
				'id' => $product->code_ean,
				'label' => $product->name,
				'data' => []
			];
		}

		echo json_encode($data);
		exit;
	}
}
