<?php

namespace App\AdminModule\Presenters;

use App\AdminModule\Traits\ProductPricesImportExportTrait;
use Nette\Application\AbortException;
use Nette\Application\UI\Form;
use Nette\Utils\Strings;
use Picqer\Barcode\Barcode;

class ProductLabelsPresenter extends BaseModulePresenter
{
	const TYPE_DESCRIPTION_1 = 1;
	const TYPE_DESCRIPTION_2 = 2;
	const TYPE_DESCRIPTION_3 = 3;
	const TYPE_PRICE_MANUAL_PRICE = 1;
	const TYPE_PRICE_MANUAL_INSTRUCTIONS = 2;

	/** @var \ProductsService @inject */
	public $service;

	/** @var \ProductsRepository @inject */
	public $repository;

	/** @var \BranchesRepository @inject */
	public $branchesRepo;

	/** @var \BranchesEntity */
	protected $branch;

	/** @var array */
	protected $branchesPairs = [];

	/** @var array */
	protected $branches = [];

	/** @var \PrintLabelsRepository @inject */
	public $printLabelsRepo;

	/** @var \ProductNutritionValuesRepository @inject */
	public $productNutritionValuesRepo;

	/** @var array */
	protected $descriptionDefaultCount = [
		self::TYPE_DESCRIPTION_1 => 18,
		self::TYPE_DESCRIPTION_2 => 1,
		self::TYPE_DESCRIPTION_3 => 24,
	];


	/**
	 * @throws \ReflectionException
	 */
	protected function startup()
	{
		parent::startup();

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
				->where('id', count($adminUserBranchesIds) ? $adminUserBranchesIds : [0])
				->order('pos_order')
				->fetchPairs('id', 'name');
		}

		if (!count($this->branches)) {
			$this->redirect('Homepage:');
		}

		$this->template->branches = $this->branches;
	}


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

		$this['filters-form']->addSelect('custom__branches', '', $this->branchesPairs);
	}


	/**
	 * @param $id
	 * @return void
	 */
	public function actionBarcodeDescription($id = NULL)
	{
		if (!$id) {
			$this->template->windowClose = TRUE;
		}

		$this->template->type = $id;
	}


	/**
	 * @return Form
	 */
	protected function createComponentBarcodePriceForm(): Form
	{
		$form = new Form();
		$form->setTranslator($this->translator);
		$form->addText('barcode', 'admin.terms.barcode');

		$form->addSubmit('submit', 'admin.formButtons.save');
		$form->onSuccess[] = [$this, 'barcodePriceFormSubmitted'];

		return $form;
	}


	/**
	 * @param Form $form
	 * @return void
	 */
	public function barcodePriceFormSubmitted(Form $form)
	{
		$values = $form->getValues();

		$product = NULL;
		$weight = NULL;
		if ($values->barcode && $this->settings->barcode_prefixes) {
			$allowedPrefix = NULL;
			foreach (explode(',', $this->settings->barcode_prefixes) as $prefix) {
				if (Strings::startsWith($values->barcode, $prefix)) {
					$allowedPrefix = $prefix;
				}
			}

			if ($allowedPrefix) {
				$ean = Strings::substring($values->barcode, strlen($allowedPrefix), $this->settings->barcode_plu_length);
				$weight = intval(Strings::substring($values->barcode, strlen($allowedPrefix) + $this->settings->barcode_plu_length + $this->settings->barcode_checksum_1_length, $this->settings->barcode_weight_length));

				if ($ean && $weight) {
					$product = $this->repository->findAll()
						->where('code_ean = ? OR id IN (SELECT products_id FROM product_codes WHERE value = ?)', $ean, $ean)
						->limit(1)
						->fetch();
				}
			}
		}

		$printed = FALSE;
		if ($product) {
			$product = \ProductsRepository::getEntity($product);
			$this->payload->forms['barcodePriceForm']['barcode'] = '';

			$branch = $this->branchesRepo->findEntity($this->session->filters[$this->getName()]['custom__branches']);
			$itemPrice = $product->getPrice($branch->id);
			$unitPrice = $itemPrice ? $product->getUnitValue($itemPrice->price_action ?: $itemPrice->price, \ProductsEntity::UNITS_TYPE_KILOGRAMS, 1) : NULL;
			$totalPrice = $itemPrice ? $product->getUnitValue($itemPrice->price_action ?: $itemPrice->price, \ProductsEntity::UNITS_TYPE_GRAMS, $weight) : NULL;

			if ($unitPrice && $totalPrice) {
				if ($this->printLabelsRepo->insert([
					'type_id' => \PrintLabelsEntity::TYPE_PRICE,
					'admin_users_id' => $this->adminUser->id,
					'data' => json_encode((object)[
						'code' => $values->barcode,
						'productId' => $product->id,
						'productName' => $product->name,
						'weight' => $weight,
						'unitPrice' => $unitPrice->price,
						'totalPrice' => $totalPrice->price,
					])
				])) {
					$printed = TRUE;
				}
			}
		}

		if (!$printed) {
			$this->flashMessage('Zkontrolujte zadaný čárový kód nebo nastavení produktu', 'danger');
		}

		$this->redrawControl('flashMessages');
		$this->redrawControl('barcodePricesList');
	}


	/**
	 * @param bool $delete
	 * @return array
	 */
	public function getBarcodePricesList(bool $delete = FALSE): array
	{
		$return = \PrintLabelsRepository::getEntities($this->printLabelsRepo->findAll()
			->where('admin_users_id', $this->adminUser->id)
			->where('type_id', \PrintLabelsEntity::TYPE_PRICE)
			->order('datetime'));

		if ($delete) {
			foreach ($return as $item) {
				$this->printLabelsRepo->findAll()->where('id', $item->id)->delete();
			}
		}

		return $return;
	}


	/**
	 * @return Form
	 */
	protected function createComponentBarcodeDescriptionForm(): Form
	{
		$form = new Form();
		$form->setTranslator($this->translator);
		$form->addText('barcode', 'admin.terms.barcode');
		$form->addRadioList('type', 'admin.terms.type', [
			self::TYPE_DESCRIPTION_2 => 'Cenový štítek',
			self::TYPE_DESCRIPTION_1 => 'Arch 3x6',
			self::TYPE_DESCRIPTION_3 => 'Arch 3x8'
		])->setDefaultValue(self::TYPE_DESCRIPTION_2);
		$form->addRadioList('is_seller', 'admin.terms.seller', $this->global['bool'])->setDefaultValue(1);
		$form->addRadioList('is_consumption', 'admin.terms.consumptionType', $this->global['bool'])->setDefaultValue(1);
		$form->addText('count', 'admin.terms.count')
			->addRule(Form::INTEGER)
			->setDefaultValue($this->descriptionDefaultCount[self::TYPE_DESCRIPTION_1]);

		$form->addSubmit('submit', 'admin.formButtons.save');
		$form->onSuccess[] = [$this, 'barcodeDescriptionFormSubmitted'];

		return $form;
	}


	/**
	 * @param Form $form
	 * @return void
	 */
	public function barcodeDescriptionFormSubmitted(Form $form)
	{
		$values = $form->getValues();

		$product = NULL;
		if ($values->barcode && $values->count) {
			$product = $this->repository->findAll()
				->where('code_ean = ? OR id IN (SELECT products_id FROM product_codes WHERE value = ?)', $values->barcode, $values->barcode)
				->limit(1)
				->fetch();
		}

		$printed = FALSE;
		if ($product) {
			$product = \ProductsRepository::getEntity($product);
			$this->payload->forms['barcodeDescriptionForm']['barcode'] = '';
			$this->payload->forms['barcodeDescriptionForm']['count'] = ($this->descriptionDefaultCount[$values->type] ?? 0);

			$description = $this->appendNutritionLine($product->ingredients, $product);
			if ($values->type == self::TYPE_DESCRIPTION_2) {
				$description = trim($description);
				if ($description !== '' && substr($description, -1) !== '.') {
					$description .= '.';
				}
				$description .= ' Prodávající: Fikko market.'
					.($product->getCountry()?->name ? ' Země původu: '.$product->getCountry()->name.'.' : '')
					.($product->getDefaultCode() ? ' EAN: '.$product->getDefaultCode().'.' : '');
			}

			if ($this->printLabelsRepo->insert([
				'type_id' => \PrintLabelsEntity::TYPE_DESCRIPTION.$values->type,
				'admin_users_id' => $this->adminUser->id,
				'data' => json_encode((object)[
					'code' => $values->barcode,
					'productId' => $product->id,
					'productName' => $product->name,
					'productEan' => $product->getDefaultCode() ?: '',
					'productCountry' => $product->getCountry()?->name ?: '',
					'isSeller' => $values->is_seller,
					'consumption' => $values->is_consumption && isset($this->global['consumptionTypes'][$product->consumption_types_id]) ? $this->global['consumptionTypes'][$product->consumption_types_id] : '',
					'count' => $values->count,
					'description' => $description,
				])
			])) {
				$printed = TRUE;
			}
		}

		if (!$printed) {
			$this->flashMessage('Zkontrolujte zadaný čárový kód', 'danger');
		}

		$this->redrawControl('flashMessages');
		$this->redrawControl('barcodeDescriptionsSheetList');
		$this->redrawControl('barcodeDescriptionsSheet2List');
		$this->redrawControl('barcodeDescriptionsPriceList');
		$this->redrawControl('barcodeDescriptionsSheetListHeader');
		$this->redrawControl('barcodeDescriptionsSheet2ListHeader');
		$this->redrawControl('barcodeDescriptionsPriceListHeader');
	}


	/**
	 * @param int|NULL $type
	 * @param bool $delete
	 * @return array
	 */
	public function getBarcodeDescriptionsList(int $type = NULL, bool $delete = FALSE): array
	{
		if (!$type) {
			return [];
		}

		$return = \PrintLabelsRepository::getEntities($this->printLabelsRepo->findAll()
			->where('admin_users_id', $this->adminUser->id)
			->where('type_id', \PrintLabelsEntity::TYPE_DESCRIPTION.$type)
			->order('datetime'));

		if ($delete) {
			foreach ($return as $item) {
				$this->printLabelsRepo->findAll()->where('id', $item->id)->delete();
			}
		}

		return $return;
	}


	/**
	 * @return Form
	 */
	protected function createComponentBarcodePriceManualForm(): Form
	{
		$form = new Form();
		$form->setTranslator($this->translator);
		$form->addText('barcode', 'admin.terms.barcode');
		$form->addText('weight', 'admin.terms.weight_2')->setRequired();
		$form->addRadioList('is_consumption', 'admin.terms.consumptionType', [0 => 'Ne'] + $this->global['consumptionDateTypes'])
			->setDefaultValue(0);
		$form->addText('date', 'admin.terms.date')
			->setDefaultValue(date('d.m.Y'))
			->addConditionOn($form['is_consumption'], Form::IS_IN, array_keys($this->global['consumptionDateTypes']))
			->setRequired();
		$form->addRadioList('is_ingredients', 'admin.terms.ingredients_manual', $this->global['bool'])->setDefaultValue(0);

		$form->addSubmit('submit', 'admin.formButtons.save');
		$form->onSuccess[] = [$this, 'barcodePriceManualFormSubmitted'];

		return $form;
	}


	/**
	 * @param Form $form
	 * @return void
	 */
	public function barcodePriceManualFormSubmitted(Form $form)
	{
		$values = $form->getValues();

		$product = NULL;
		$weight = NULL;
		if ($values->barcode && intval($values->weight)) {
			$ean = $values->barcode;
			$weight = intval($values->weight);

			if ($ean && $weight) {
				$product = $this->repository->findAll()
					->where('code_ean = ? OR id IN (SELECT products_id FROM product_codes WHERE value = ?)', $ean, $ean)
					->limit(1)
					->fetch();
			}
		}

		$printed = FALSE;
		if ($product) {
			$product = \ProductsRepository::getEntity($product);
			$this->payload->forms['barcodePriceManualForm']['barcode'] = '';
			$this->payload->forms['barcodePriceManualForm']['weight'] = '';

			$branch = $this->branchesRepo->findEntity($this->session->filters[$this->getName()]['custom__branches']);
			$itemPrice = $product->getPrice($branch->id);
			$unitPrice = $itemPrice ? $product->getUnitValue($itemPrice->price_action ?: $itemPrice->price, \ProductsEntity::UNITS_TYPE_KILOGRAMS, 1) : NULL;
			$totalPrice = $itemPrice ? $product->getUnitValue($itemPrice->price_action ?: $itemPrice->price, \ProductsEntity::UNITS_TYPE_GRAMS, $weight) : NULL;

			if ($unitPrice && $totalPrice) {
				if ($this->printLabelsRepo->insert([
					'type_id' => \PrintLabelsEntity::TYPE_PRICE_MANUAL.self::TYPE_PRICE_MANUAL_PRICE,
					'admin_users_id' => $this->adminUser->id,
					'data' => json_encode((object)[
						'code' => $values->barcode,
						'productId' => $product->id,
						'productName' => $product->name,
						'productEan' => $product->getDefaultCode() ? $this->generateWeightEAN('29'.$product->getDefaultCode().str_pad($weight, 6, '0', STR_PAD_LEFT)) : '',
						'productCountry' => $product->getCountry()?->name ?: '',
						'weight' => $weight,
						'unitPrice' => $unitPrice->price,
						'totalPrice' => $totalPrice->price,
						'consumption' => $values->is_consumption && isset($this->global['consumptionDateTypes'][$values->is_consumption]) ? $this->global['consumptionDateTypes'][$values->is_consumption].': '.$values->date : '',
					])
				])) {
					if ($values->is_ingredients) {
						$description = $this->appendNutritionLine($product->ingredients, $product);

						$this->printLabelsRepo->insert([
							'type_id' => \PrintLabelsEntity::TYPE_PRICE_MANUAL.self::TYPE_PRICE_MANUAL_INSTRUCTIONS,
							'admin_users_id' => $this->adminUser->id,
							'data' => json_encode((object)[
								'code' => $values->barcode,
								'productId' => $product->id,
								'productName' => $product->name,
								'description' => $description,
							])
						]);
					}

					$printed = TRUE;
				}
			}
		}

		if (!$printed) {
			$this->flashMessage('Zkontrolujte zadaný čárový kód nebo nastavení produktu', 'danger');
		}

		$this->redrawControl('flashMessages');
		$this->redrawControl('barcodePricesManualList');
	}


	/**
	 * @param bool $delete
	 * @return array
	 */
	public function getBarcodePricesManualList(bool $delete = FALSE): array
	{
		$return = \PrintLabelsRepository::getEntities($this->printLabelsRepo->findAll()
			->where('admin_users_id', $this->adminUser->id)
			->where('type_id LIKE ?', \PrintLabelsEntity::TYPE_PRICE_MANUAL."%")
			->order('datetime'));

		if ($delete) {
			foreach ($return as $item) {
				$this->printLabelsRepo->findAll()->where('id', $item->id)->delete();
			}
		}

		return $return;
	}


	/**
	 * @return void
	 */
	public function handleDeleteBarcodes()
	{
		$ids = $this->getParameter('ids');

		if (!$ids || !is_array($ids)) {
			$this->flashMessage($this->trans('admin.bulkActions.form.error'), 'danger');
			$this->redrawControl('flashMessages');

			return;
		}

		foreach ($ids as $id) {
			$this->printLabelsRepo->delete($id);
		}

		$this->flashMessage($this->trans('admin.bulkActions.form.success'), 'success');

		$this->redrawControl('flashMessages');
		$this->redrawControl('barcodePricesList');
		$this->redrawControl('barcodeDescriptionsSheetList');
		$this->redrawControl('barcodeDescriptionsSheet2List');
		$this->redrawControl('barcodeDescriptionsPriceList');
		$this->redrawControl('barcodeDescriptionsSheetListHeader');
		$this->redrawControl('barcodeDescriptionsSheet2ListHeader');
		$this->redrawControl('barcodeDescriptionsPriceListHeader');
		$this->redrawControl('barcodePricesManualList');
	}


	/**
	 * Sestaví inline řádek "Nutriční hodnoty na <referenční množství>: ..." z vyplněných hodnot produktu.
	 */
	protected function buildNutritionLine(\ProductsEntity $product): string
	{
		$rows = $this->productNutritionValuesRepo->findAll()
			->where('products_id', $product->id)
			->where('value != ?', '')
			->order('nutrition_values.pos_order, nutrition_values.id');

		$parts = [];
		foreach ($rows as $row) {
			if (!$nutritionRow = $row->ref('nutrition_values')) {
				continue;
			}
			$parts[] = $nutritionRow->name.': '.$row->value.($nutritionRow->unit !== '' ? ' '.$nutritionRow->unit : '');
		}

		if (!count($parts)) {
			return '';
		}

		$reference = $product->nutrition_reference_amount && $product->nutrition_reference_unit
			? rtrim(rtrim(number_format((float)$product->nutrition_reference_amount, 2, ',', ''), '0'), ',').' '.$product->nutrition_reference_unit
			: '100g';

		return $this->trans('admin.terms.nutritionValuesReference').' '.$reference.': '.implode(', ', $parts);
	}


	/**
	 * Připojí za text složení inline řádek s nutričními hodnotami (pokud jsou u produktu vyplněné).
	 */
	protected function appendNutritionLine(?string $description, \ProductsEntity $product): string
	{
		$description = (string)$description;

		$nutritionLine = $this->buildNutritionLine($product);
		if ($nutritionLine === '') {
			return $description;
		}

		$description = trim($description);
		if ($description !== '' && substr($description, -1) !== '.') {
			$description .= '.';
		}

		return ($description !== '' ? $description.' ' : '').$nutritionLine;
	}


	/**
	 * @param $code
	 * @return string
	 */
	function generateWeightEAN($code): string
	{
		$weightflag = TRUE;
		$sum = 0;
		for ($i = strlen($code) - 1; $i >= 0; $i--) {
			$sum += (int)$code[$i] * ($weightflag ? 3 : 1);
			$weightflag = !$weightflag;
		}
		$code .= (10 - ($sum % 10)) % 10;

		return $code;
	}


	/**
	 * @param string|NULL $ean
	 * @return string|null
	 * @throws \Picqer\Barcode\Exceptions\InvalidCharacterException
	 * @throws \Picqer\Barcode\Exceptions\InvalidCheckDigitException
	 * @throws \Picqer\Barcode\Exceptions\InvalidLengthException
	 */
	public function getBarcode(string $ean = NULL): ?string
	{
		if (!$ean) {
			return NULL;
		}

		$generator = new \Picqer\Barcode\BarcodeGeneratorSVG();

		return $generator->getBarcode($ean, $generator::TYPE_EAN_13, 2, 28);
	}


	/**
	 * @return void
	 */
	public function handleChangeDescriptionType($type = NULL)
	{
		if ($type) {
			$this->payload->forms['barcodeDescriptionForm']['count'] = ($this->descriptionDefaultCount[$type] ?? 0);
		}

		$this->sendPayload();
	}


	/**
	 * @return void
	 */
	public function handleReload()
	{
		$this->redrawControl();
	}
}
