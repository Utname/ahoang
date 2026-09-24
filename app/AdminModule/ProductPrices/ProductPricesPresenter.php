<?php

namespace App\AdminModule\Presenters;

use App\AdminModule\Traits\ProductPricesImportExportTrait;
use Nette\Application\AbortException;
use Nette\Application\UI\Form;
use Nette\Utils\Strings;

class ProductPricesPresenter extends BaseModulePresenter
{
	use ProductPricesImportExportTrait;

	/** @var \ProductsService @inject */
	public $service;

	/** @var \ProductsRepository @inject */
	public $repository;

	/** @var string */
	protected $orderBy = '';

	/** @var \CategoriesRepository @inject */
	public $categoriesRepo;

	/** @var array */
	protected $categoriesPairs = [];

	/** @var \BranchesRepository @inject */
	public $branchesRepo;

	/** @var array */
	protected $branchesPairs = [];

	/** @var \CurrenciesRepository @inject */
	public $currenciesRepo;

	/** @var \PricesService @inject */
	public $pricesService;

	/** @var array */
	protected $currenciesPairs = [];

	/** @var \BranchesEntity */
	public $branches;

	/** @var \CurrenciesEntity */
	public $currency;

	/** @var \ProductPricesRepository @inject */
	public $productPricesRepository;

	/** @var \CountriesRepository @inject */
	public $countriesRepo;

	/** @var array */
	protected $countriesPairs = [];


	/**
	 * @throws \ReflectionException
	 */
	protected function startup()
	{
		parent::startup();

		$this->template->branchesPairs = $this->branchesPairs = $this->branchesRepo->findAll()
			->order('pos_order')
			->fetchPairs('id', 'name');
		$this->currenciesPairs = $this->currenciesRepo->findAll()->order('pos_order')->fetchPairs('id', 'name');
		$this->countriesPairs = $this->countriesRepo->findAll()->order('pos_order')->fetchPairs('id', 'name');
	}


	/**
	 *
	 */
	public function actionDefault()
	{
		if (!isset($this->session->filters[$this->getName()]['custom__branches']) || is_null($this->session->filters[$this->getName()]['custom__branches'])) {
			$this->session->filters[$this->getName()]['custom__branches'] = $this->settings->default_branch;
		}

		if (!isset($this->session->filters[$this->getName()]['custom__currency']) || is_null($this->session->filters[$this->getName()]['custom__currency'])) {
			$this->session->filters[$this->getName()]['custom__currency'] = $this->globalContainer->getDefaultCurrency()->id;
		}

		$this->template->branches = $this->branches = $this->branchesRepo->findEntity($this->session->filters[$this->getName()]['custom__branches']);
		$this->template->currency = $this->currency = $this->currenciesRepo->findEntity($this->session->filters[$this->getName()]['custom__currency']);

		$this['filters-form']->addSelect('custom__branches', '', $this->branchesPairs);
		$this['filters-form']->addSelect('custom__currency', '', $this->currenciesPairs);

		// Filters DropDown
		$this['filters-form']->addSelect('custom__is_active', '', $this->global['bool'])->setPrompt('-');
		$this['filters-form']->addSelect('custom__is_action', '', $this->global['bool'])->setPrompt('-');
		$this['filters-form']->addText('custom__name', '');
		$this['filters-form']->addText('custom__code_ean', '');

		$this['filters']->setCustomFilters(function($selection, $filters) {

			if (isset($filters['is_action'])) {
				if ($filters['is_action']) {
					$selection->where('products:product_prices.branches_id = ? AND products:product_prices.action_from IS NOT NULL AND products:product_prices.price IS NOT NULL AND products:product_prices.price_action IS NOT NULL AND products:product_prices.price_action > 0 AND products:product_prices.price > products:product_prices.price_action AND (products:product_prices.action_from >= ? OR (products:product_prices.action_to IS NOT NULL AND products:product_prices.action_to >= ?))', $filters['branches'], date('Y-m-d'), date('Y-m-d'));
				}
				else {
					$actionsIds = $this->repository->findAll()->where('products:product_prices.branches_id = ? AND products:product_prices.action_from IS NOT NULL AND products:product_prices.price IS NOT NULL AND products:product_prices.price_action IS NOT NULL AND products:product_prices.price_action > 0 AND products:product_prices.price > products:product_prices.price_action AND (products:product_prices.action_from >= ? OR (products:product_prices.action_to IS NOT NULL AND products:product_prices.action_to >= ?))', $filters['branches'], date('Y-m-d'), date('Y-m-d'))->fetchPairs(NULL, 'id');
					$selection->where('products_id NOT IN (?)', $actionsIds);
				}
			}

			if (isset($filters['name']) && $filters['name']) {
				$selection->where('products_mld.name LIKE ?', '%'.$filters['name'].'%');
			}

			if (isset($filters['code_ean']) && $filters['code_ean']) {
				$selection->where('products.code_ean = ?', $filters['code_ean']);
			}

			if (isset($filters['is_active'])) {
				$selection->where('products_mld.is_active = ?', $filters['is_active']);
			}
		});

		parent::actionDefault();
	}


	/**
	 * @return void
	 */
	public function renderDefault()
	{
		parent::renderDefault();

		/** @var PaginatorControl $paginator */
		$paginator = $this['paginator'];
		$this->template->canChangePage = ($paginator->getPaginator()->getPage() < $paginator->getPaginator()->getLastPage());
	}


	/**
	 * @return Form
	 */
	protected function createComponentPricesForm(): Form
	{
		$form = new Form();
		$form->setTranslator($this->translator);

		$form->addSubmit('submit', 'admin.formButtons.save');
		$form->addSubmit('submit_next_page', 'admin.formButtons.saveAndGoToNextPage');

		$form->onSuccess[] = [$this, 'pricesFormSubmitted'];

		return $form;
	}


	/**
	 * @param Form $form
	 * @throws AbortException
	 */
	public function pricesFormSubmitted(Form $form)
	{
		$values = $form->getHttpData();

		foreach ($values as $key => $val) {
			if (Strings::startsWith($key, 'price__')) {
				[$nothing, $productId] = explode('__', $key);

				$isAction = FALSE;
				if ($val && $values['price_action__'.$productId] && $val > $values['price_action__'.$productId] && $values['action_from__'.$productId] && date('Y-m-d', strtotime($values['action_from__'.$productId])) <= date('Y-m-d') && (!$values['action_to__'.$productId] || date('Y-m-d', strtotime($values['action_to__'.$productId])) >= date('Y-m-d'))) {
					$isAction = TRUE;
				}

				$set = [
					'branches_id' => $this->session->filters[$this->getName()]['custom__branches'],
					'currencies_id' => $this->session->filters[$this->getName()]['custom__currency'],
					'products_id' => $productId,
					'price' => $val,
					'price_action' => $values['price_action__'.$productId] !== '' ? $values['price_action__'.$productId] : NULL,
					'is_action' => $isAction,
					'action_from' => $values['action_from__'.$productId] ? date('Y-m-d', strtotime($values['action_from__'.$productId])) : NULL,
					'action_to' => $values['action_to__'.$productId] ? date('Y-m-d', strtotime($values['action_to__'.$productId])) : NULL,
				];

				// Log price change for the main branch (before DB write so origin price is read correctly)
				$logPrice = $isAction ? (float)$values['price_action__'.$productId] : (float)$val;
				if ($set['price']) {
					$this->service->logProductPrice((int)$productId, (int)$set['branches_id'], (int)$set['currencies_id'], $logPrice, NULL, $isAction ? $set['action_from'] : NULL, $isAction ? $set['action_to'] : NULL);
				}

				$productsPriceEntity = $this->productPricesRepository->findEntityBy([
					'products_id' => $productId,
					'branches_id' => $set['branches_id'],
					'currencies_id' => $set['currencies_id'],
				]);
				if ($productsPriceEntity instanceof \ProductPricesEntity) {
					if ($set['price']) {
						$productsPriceEntity->setValues($set);
						$this->productPricesRepository->update($productsPriceEntity);
					}
					else {
						$this->productPricesRepository->delete($productsPriceEntity->id);
					}
				}
				else {
					if ($set['price']) {
						$this->productPricesRepository->insert($set);
					}
				}

				// Log & propagate base price to other branches
				if ($val) {
					foreach ($this->branchesRepo->findAllEntities('pos_order') as $branch) {
						if ($branch->id == $set['branches_id']) continue;
						$product = $this->repository->findEntity((int)$productId);
						$branchPrice = $product ? $product->getPrice($branch->id) : null;
						if ($branchPrice && isset($branchPrice->is_valid_action) && $branchPrice->is_valid_action) continue;
						$this->service->logProductPrice((int)$productId, (int)$branch->id, (int)$set['currencies_id'], (float)$val);
					}
				}
				$this->productPricesRepository->findAll()->where([
					'branches_id != ' => $this->session->filters[$this->getName()]['custom__branches'],
					'currencies_id' => $this->session->filters[$this->getName()]['custom__currency'],
					'products_id' => $productId,
				])->update([
					'price' => $val
				]);
			}
		}

		if ($form['submit_next_page']->isSubmittedBy()) {
			$this->getPaginatorControl()->setPage($this->getPaginatorControl()->getPage() + 1);
		}

		$this->redirect('this');
	}


	/**
	 * @return PricesBulkEditor
	 */
	public function createComponentPricesBulkEditor(): PricesBulkEditor
	{
		$control = new PricesBulkEditor($this->aps, $this->translator, $this->priceGroupsRepo, $this->currenciesRepo, $this->pricesService, $this->database);
		if (isset($this->session->filters[$this->getName()]['custom__price_group']) && $this->session->filters[$this->getName()]['custom__price_group']) {
			$control->setDefaultPriceGroupIds([$this->session->filters[$this->getName()]['custom__price_group']]);
		}
		if (isset($this->session->filters[$this->getName()]['custom__currency']) && $this->session->filters[$this->getName()]['custom__currency']) {
			$control->setDefaultCurrencyIds([$this->session->filters[$this->getName()]['custom__currency']]);
		}

		return $control;
	}


	/**
	 * @return PricesHistoryBulkEditor
	 */
	public function createComponentPricesHistoryBulkEditor(): PricesHistoryBulkEditor
	{
		$control = new PricesHistoryBulkEditor(settings: $this->settings, adminPermissionsService: $this->aps, translator: $this->translator, database: $this->database, productPricesHistoryRepo: $this->productPricesHistoryRepo);

		if ($this->session->filters[$this->getName()]['custom__price_group'] ?? NULL) {
			$priceGroup = $this->priceGroupsRepo->findEntity($this->session->filters[$this->getName()]['custom__price_group']);
			$control->setPriceGroup($priceGroup);
		}

		if ($this->session->filters[$this->getName()]['custom__currency'] ?? NULL) {
			$currency = $this->currenciesRepo->findEntity($this->session->filters[$this->getName()]['custom__currency']);
			$control->setCurrency($currency);
		}

		return $control;
	}


	/**
	 * @return PricesHistoryEditor
	 */
	public function createComponentPricesHistoryEditor(): PricesHistoryEditor
	{
		$control = new PricesHistoryEditor(type: PricesHistoryEditor::TYPE_PRODUCT_VARIANT, settings: $this->settings, adminPermissionsService: $this->aps, translator: $this->translator, priceGroupsRepo: $this->priceGroupsRepo, currenciesRepo: $this->currenciesRepo, productPricesHistoryRepo: $this->productPricesHistoryRepo, database: $this->database);

		return $control;
	}


	/**
	 * @param $item
	 * @param $itemId
	 * @param $value
	 * @param $branchId
	 * @param $currencyId
	 * @return true
	 */
	public function handleUpdatePrices($item, $itemId, $value, $branchId, $currencyId)
	{
		[$column, $idProduct] = explode("__", $item);

		if ($column == "action_from" || $column == "action_to") {
			$parsedValueDate = \DateTime::createFromFormat('d.m.Y', $value);

			if ($parsedValueDate) {
				$value = $parsedValueDate->format('Y-m-d');
			}
		}
		if (($column == 'price' || $column == 'price_action') && $value === '') {
			$value = NULL;
		}
		$itemEntity = $this->productPricesRepository->findEntity($itemId);
		if (!is_null($itemEntity) && $itemEntity) {

			$itemEntity->setValue($column, $value);
			$itemEntity->setValue('is_action', FALSE);
			if ($itemEntity->action_from && $itemEntity->price_action && $itemEntity->price_action > 0 && $itemEntity->price && $itemEntity->price > $itemEntity->price_action && date('Y-m-d', strtotime($itemEntity->action_from)) <= date('Y-m-d') && (!$itemEntity->action_to || date('Y-m-d', strtotime($itemEntity->action_to)) >= date('Y-m-d'))) {
				$itemEntity->setValue('is_action', TRUE);
			}

			// Log price change before DB write (origin price is read from current DB state)
			$logPrice = $itemEntity->is_action ? (float)$itemEntity->price_action : (float)$itemEntity->price;
			if ($logPrice && $itemEntity->price) {
				$this->service->logProductPrice((int)$idProduct, (int)$itemEntity->branches_id, (int)$itemEntity->currencies_id, $logPrice, NULL, $itemEntity->is_action ? $itemEntity->action_from?->format('Y-m-d') : NULL, $itemEntity->is_action ? $itemEntity->action_to?->format('Y-m-d') : NULL);
			}

			$this->productPricesRepository->update($itemEntity);
		}
		else {
			$newValue = [
				"branches_id" => $branchId,
				"products_id" => $idProduct,
				"currencies_id" => $currencyId,
				$column => $value
			];

			$newValue['is_action'] = FALSE;
			if ($column == 'action_from' && $value && $value <= date('Y-m-d')) {
				$newValue['is_action'] = TRUE;
			}

			$this->productPricesRepository->insert($newValue);
		}

		return TRUE;
	}


	/**
	 * @return Form
	 */
	protected function createComponentMultiPricesForm(): Form
	{
		$form = new Form();
		$form->setTranslator($this->translator);

		foreach ($this->branchesPairs as $branchesId => $branchesName) {
			$form->addCheckbox('branch__'.$branchesId, $branchesName)->setDefaultValue(0);
		}

		$form->addSelect('is_action', 'admin.terms.action', $this->global['bool'])->setPrompt('-');
		$form->addText('price', 'admin.terms.price')
			->setHtmlAttribute('type', 'number')
			->setHtmlAttribute('step', '0.01')
			->addCondition(Form::FILLED, TRUE)
			->addRule(Form::Float);
		$form->addText('price_action', 'admin.terms.actionPrice')
			->setHtmlAttribute('type', 'number')
			->setHtmlAttribute('step', '0.01')
			->addCondition(Form::FILLED, TRUE)
			->addRule(Form::Float);

		$form->addText('action_from', 'admin.terms.actionFrom');
		$form->addText('action_to', 'admin.terms.actionFrom');

		$form->addSubmit('submit', 'admin.buttons.save');

		$form->onSuccess[] = [$this, 'multiPricesFormSubmitted'];

		return $form;
	}


	/**
	 * @param Form $form
	 * @return void
	 */
	public function multiPricesFormSubmitted(Form $form)
	{
		$values = $form->getValues(TRUE);

		$branchesIds = [];
		foreach ($values as $key => $val) {
			if (strpos($key, 'branch__') !== FALSE && $val) {
				[$prefix, $id] = explode('__', $key);
				$branchesIds[] = $id;
			}
		}

		$this->selection = $this->repository->findAll();
		$this->applyFilters();

		$price = strlen($values['price']) ? $values['price'] : NULL;
		$priceAction = strlen($values['price_action']) ? $values['price_action'] : NULL;
		$actionFrom = strlen($values['action_from']) ? date('Y-m-d', strtotime($values['action_from'])) : NULL;
		$actionTo = strlen($values['action_to']) ? date('Y-m-d', strtotime($values['action_to'])) : NULL;

		if (count($branchesIds) && (!is_null($price) || !is_null($priceAction) || !is_null($actionFrom) || !is_null($actionTo))) {
			foreach ($this->repository->getEntities($this->selection) as $product) {
				foreach ($branchesIds as $branchesId) {
					$set = [
						'branches_id' => $branchesId,
						'currencies_id' => $this->session->filters[$this->getName()]['custom__currency'],
						'products_id' => $product->id,
					];

					if (!is_null($price)) {
						$set['price'] = $price;
					}

					if (!is_null($priceAction)) {
						$set['price_action'] = $priceAction;
					}

					if (!is_null($actionFrom)) {
						$set['action_from'] = $actionFrom;
					}

					if (!is_null($actionTo)) {
						$set['action_to'] = $actionTo;
					}

					$productsPriceEntity = $this->productPricesRepository->findEntityBy([
						'products_id' => $product->id,
						'branches_id' => $branchesId,
						'currencies_id' => $set['currencies_id'],
					]);
					if ($productsPriceEntity instanceof \ProductPricesEntity) {
						$set['is_action'] = FALSE;
						$productsPriceEntity->setValues($set);

						if ($productsPriceEntity->price && $productsPriceEntity->price_action && $productsPriceEntity->price > $productsPriceEntity->price_action && $productsPriceEntity->action_from && date('Y-m-d', strtotime($productsPriceEntity->action_from)) <= date('Y-m-d') && (!$productsPriceEntity->action_to || date('Y-m-d', strtotime($productsPriceEntity->action_to)) >= date('Y-m-d'))) {
							$set['is_action'] = TRUE;
						}

						$productsPriceEntity->setValues($set);

						// Log price change before DB write
						$logPrice = $set['is_action'] && !empty($set['price_action']) ? (float)$set['price_action'] : (isset($set['price']) ? (float)$set['price'] : NULL);
						if (!is_null($logPrice)) {
							$this->service->logProductPrice((int)$product->id, (int)$branchesId, (int)$set['currencies_id'], $logPrice, NULL, $set['is_action'] ? ($set['action_from'] ?? NULL) : NULL, $set['is_action'] ? ($set['action_to'] ?? NULL) : NULL);
						}

						$this->productPricesRepository->update($productsPriceEntity);
					}
					else {
						$set['is_action'] = FALSE;
						if (!is_null($price) && !is_null($priceAction) && $price > $priceAction && !is_null($actionFrom) && $actionFrom <= date('Y-m-d') && (is_null($actionTo) || $actionTo >= date('Y-m-d'))) {
							$set['is_action'] = TRUE;
						}

						// Log price change before DB write (new price row)
						$logPrice = $set['is_action'] && !empty($set['price_action']) ? (float)$set['price_action'] : (isset($set['price']) ? (float)$set['price'] : NULL);
						if (!is_null($logPrice)) {
							$this->service->logProductPrice((int)$product->id, (int)$branchesId, (int)$set['currencies_id'], $logPrice, NULL, $set['is_action'] ? ($set['action_from'] ?? NULL) : NULL, $set['is_action'] ? ($set['action_to'] ?? NULL) : NULL);
						}

						$this->productPricesRepository->insert($set);
					}
				}

				if (!is_null($price)) {
					foreach (array_diff_key($this->branchesPairs, array_flip($branchesIds)) as $branchesDiffId => $branchesDiffName) {
						$productsPriceEntity = $this->productPricesRepository->findEntityBy([
							'products_id' => $product->id,
							'branches_id' => $branchesDiffId,
							'currencies_id' => $this->session->filters[$this->getName()]['custom__currency'],
						]);

						// Log price change before DB write (propagated flat price to other branches)
						$this->service->logProductPrice((int)$product->id, (int)$branchesDiffId, (int)$this->session->filters[$this->getName()]['custom__currency'], (float)$price);

						if ($productsPriceEntity instanceof \ProductPricesEntity) {
							$productsPriceEntity->setValues(['price' => $price]);
							$this->productPricesRepository->update($productsPriceEntity);
						}
						else {
							$this->productPricesRepository->insert([
								'products_id' => $product->id,
								'branches_id' => $branchesDiffId,
								'currencies_id' => $this->session->filters[$this->getName()]['custom__currency'],
								'price' => $price
							]);
						}
					}
				}
			}
		}

		$this->redirect('this');
	}


	/**
	 * @return void
	 */
	public function handleMultiPrices()
	{
		$this['multiPricesForm']->setDefaults([], TRUE);
		if (($branchesId = $this->session->filters[$this->getName()]['custom__branches']) && isset($this['multiPricesForm']['branch__'.$branchesId])) {
			$this['multiPricesForm']->setDefaults(['branch__'.$branchesId => 1]);
		}

		$this->redrawControl('multiPricesForm');
	}


	/**
	 * @return Form
	 */
	protected function createComponentProductsForm(): Form
	{
		$form = new Form();
		$form->setTranslator($this->translator);

		$form->addText('code_ean', 'admin.terms.ean');
		$form->addText('name', 'admin.terms.name')->setRequired(TRUE);
		$form->addText('slug', 'admin.terms.slug')->setRequired(TRUE);
		$form->addSelect('country_origin', 'admin.terms.countryOrigin', $this->countriesPairs)->setTranslator(NULL);
		$form->addSelect('units_id', 'admin.terms.unit', \ProductsEntity::UNITS)->setTranslator(NULL);
		$form->addText('weight', 'admin.terms.weight');
		$form->addSelect('is_active', $this->trans('admin.terms.isActive'), $this->global['bool'])->setTranslator(NULL);
		$form->addTextArea('perex', 'admin.terms.perex');
		$form->addTextArea('text', 'admin.terms.text');

		$form->addSubmit('submit', 'admin.buttons.save');

		$form->onSuccess[] = [$this, 'productsFormSubmitted'];

		return $form;
	}


	/**
	 * @param Form $form
	 * @return void
	 * @throws AbortException
	 * @throws \Nette\Utils\UnknownImageFileException
	 */
	public function productsFormSubmitted(Form $form)
	{
		$values = $this->getEditFormValues($form, $this->getAction());
		$entity = $this->processCreateForm($values);

		$this->session->filters[$this->getName()]['custom__is_active'] = NULL;
		$this->session->filters[$this->getName()]['custom__is_acttion'] = NULL;
		$this->session->filters[$this->getName()]['custom__code_ean'] = $entity->code_ean;
		$this->session->filters[$this->getName()]['custom__name'] = $entity->name;

		$this->flashMessage($this->trans('admin.messages.successSave'), 'success');
		$this->redirect('this');
	}


	/**
	 * @return void
	 */
	public function handleProduct()
	{
		$this['productsForm']->setDefaults([
			'code_ean' => $this->session->filters[$this->getName()]['custom__code_ean'],
			'name' => $this->session->filters[$this->getName()]['custom__name'],
			'slug' => Strings::webalize($this->session->filters[$this->getName()]['custom__name']),
		], TRUE);
		$this->redrawControl('productsForm');
	}
}
