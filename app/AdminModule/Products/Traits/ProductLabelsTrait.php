<?php

namespace App\AdminModule\Traits;

use App\AdminModule\Presenters\BulkActionsControl;
use App\AdminModule\Presenters\ProductActionsControl;
use App\AdminModule\Presenters\ProductLabelsControl;
use BaseEntity;
use Nette\Application\AbortException;
use Nette\Application\UI\Form;
use Nette\Application\UI\Multiplier;
use Nette\Database\Table\Selection;

trait ProductLabelsTrait
{
	/**
	 * @return Multiplier
	 */
	protected function createComponentProductLabels(): Multiplier
	{
		return new Multiplier(function($itemId) {
			$control = new  ProductLabelsControl($this->repository, $this->productLabelsRepo, $this->productLabelHeadersRepo);
			$control->setItemId($itemId);
			$branchesId = $this->session->filters[$this->getName()]['custom__branches'] ?? $this->adminUser->default_branches_id ?: ($this->settings->default_branch ?? 1);
			$control->setBranchesId($branchesId);

			return $control;
		});
	}


	/**
	 * @return Form
	 */
	protected function createComponentLabelsForm(): Form
	{
		$form = new Form();
		$form->setTranslator($this->translator);

		$form->addText('products_id');
		$form->addText('branches_id');
		$form->addText('type_id');

		$form->addText('name', 'admin.terms.name')->setRequired();
		$form->addText('count', 'admin.terms.numberOfPrints')->setRequired()->addRule(Form::INTEGER)->addRule(Form::MIN, NULL, 0);

		$form->addSelect('product_label_headers_id', 'admin.terms.header', $this->productLabelHeadersPairs)->setRequired();
		// "Admin - Produkty v2" editor: per-label Složení and "Cena za"; Rozmrazeno is a product flag
		$form->addTextArea('ingredients', 'admin.pv.ingredients')->setNullable();
		$form->addSelect('price_unit', 'admin.pv.priceUnit', array_combine(\ProductsEntity::PRICE_UNITS, \ProductsEntity::PRICE_UNITS))
			->setTranslator(NULL)
			->setPrompt($this->trans('admin.pv.priceUnitProduct'));
		$form->addCheckbox('rozmrazeno', NULL);
		$form->addSelect('is_more_species', 'admin.terms.moreSpecies', $this->global['bool'])->setDefaultValue(0);
		$form->addSelect('origin_countries_id', 'admin.terms.countryOrigin', $this->countriesPairs)
			->setTranslator(NULL)
			->setPrompt('-');
		$form->addText('sale_text', 'admin.terms.labelSaleText');
		$form->addText('sale_percent', 'admin.terms.labelSalePercent')
			->setHtmlAttribute('type', 'number')
			->addCondition(Form::FILLED, TRUE)
			->addRule(Form::INTEGER);
		$form->addSelect('price_text', 'admin.terms.labelPriceText', $this->global['productLabelPriceTypes']);
		$form->addCheckbox('hide_validity', NULL);
		$form->addCheckbox('hide_ingredients', NULL);
		// Pečivo only (CACH-LAM-CODE.md §2 "thawed?: boolean"): controls the
		// "Rozmrazeno" badge, which must not print for bread that wasn't frozen.
		$form->addCheckbox('thawed', 'admin.terms.thawed');
		$form->addText('note_1', 'admin.terms.note1');
		$form->addText('note_2', 'admin.terms.note2');
		$form->addText('note_3', 'admin.terms.note3');

		$form->addSelect('default_units_id', NULL, \ProductsEntity::UNITS)->setTranslator(NULL)->setPrompt('-');
		$form->addText('default_units_weight', 'admin.terms.defaultPriceWeight')
			->setNullable()
			->addCondition(Form::FILLED, TRUE)
			->addRule(Form::FLOAT);

		$form->addSubmit('submit', 'admin.buttons.save');

		$form->onSuccess[] = [$this, 'labelsFormSubmitted'];

		return $form;
	}


	/**
	 * @param Form $form
	 * @return void
	 * @throws AbortException
	 * @throws \Nette\Utils\UnknownImageFileException
	 */
	public function labelsFormSubmitted(Form $form)
	{
		$values = $this->getEditFormValues($form, $this->getAction());
		$values['datetime_printed'] = NULL;
		$values['admin_users_id'] = $this->adminUser->id;
		if (isset($values['default_units_weight']) && $values['default_units_weight'] === '') {
			$values['default_units_weight'] = NULL;
		}
		if (isset($values['default_units_id']) && $values['default_units_id'] === '') {
			$values['default_units_id'] = NULL;
		}
		if (isset($values['origin_countries_id']) && $values['origin_countries_id'] === '') {
			$values['origin_countries_id'] = NULL;
		}
		$values['ingredients'] = trim((string)($values['ingredients'] ?? '')) !== '' ? trim($values['ingredients']) : NULL;
		$values['price_unit'] = ($values['price_unit'] ?? '') !== '' ? $values['price_unit'] : NULL;
		// Rozmrazeno belongs to the product (all its labels); the label keeps a copy
		$rozmrazeno = !empty($values['rozmrazeno']);
		unset($values['rozmrazeno']);
		$values['thawed'] = $rozmrazeno;
		$this->repository->getDatabase()->table('products')->where('id', (int)$values['products_id'])->update(['rozmrazeno' => $rozmrazeno ? 1 : 0]);
		if ((int)$values['count'] <= 0) {
			$values['count'] = 0;
		}

		// Texts and the header are shared by every template of the product; the count
		// belongs to the template the editor was opened from.
		$productsId = (int)$values['products_id'];
		$branchesId = (int)$values['branches_id'];
		$typeId = (int)$values['type_id'];
		$headerId = (int)$values['product_label_headers_id'];
		$this->productLabelsRepo->saveShared($productsId, $branchesId, (int)$this->adminUser->id, $values, $headerId);

		$entity = $this->productLabelsRepo->findEntityBy([
			'products_id' => $productsId,
			'branches_id' => $branchesId,
			'type_id' => $typeId,
			'admin_users_id' => $this->adminUser->id,
		]);
		$product = $this->repository->findEntity($productsId);
		if (!$entity && $product) {
			$entity = $this->productLabelsRepo->insertForProduct($product, $branchesId, (int)$this->adminUser->id, $typeId, 0);
		}
		if ($entity) {
			// a header changed in the editor goes to every template (as in the chip/card select)
			if ($product && (int)$entity->product_label_headers_id !== $headerId) {
				$this->productLabelsRepo->setHeaderForProduct($product, $branchesId, (int)$this->adminUser->id, $headerId);
			}
			$entity->setValues([
				'count' => (int)$values['count'],
				'product_label_headers_id' => $headerId,
				'datetime_printed' => NULL,
			]);
			$this->productLabelsRepo->update($entity);
		}

		$this->redrawProductRow($productsId);
	}


	/**
	 * Redraws the product's row parts (price, chips, labels panel) and the print bar,
	 * or reloads the page for a non-ajax request.
	 */
	protected function redrawProductRow(int $productsId): void
	{
		if (!$this->isAjax()) {
			$this->redirect('this');
		}
		$this['productLabels-'.$productsId]->redrawAll();
	}


	/**
	 * "Obnovit z produktu": every template of the product prints the product's own data
	 * again (counts and headers are kept).
	 */
	public function handleResetLabel(int $productsId, int $typeId): void
	{
		$branchesId = (int)($this->session->filters[$this->getName()]['custom__branches'] ?? 0);
		$product = $this->repository->findEntity($productsId);
		if ($product) {
			$this->productLabelsRepo->saveShared($productsId, $branchesId, (int)$this->adminUser->id, [
				'name' => $product->name,
				'sale_text' => '',
				'sale_percent' => 0,
				'is_more_species' => 0,
				'origin_countries_id' => $product->country_origin ?: NULL,
				'hide_validity' => 0,
				'hide_ingredients' => 0,
				'ingredients' => NULL,
				'price_unit' => NULL,
				'note_1' => $product->label_note_1,
				'note_2' => $product->label_note_2,
				'note_3' => $product->label_note_3,
				'default_units_id' => $product->default_units_id,
				'default_units_weight' => $product->default_units_weight,
			]);
		}

		$this->handleLabel($productsId, $typeId);
		$this->redrawProductRow($productsId);
	}


	/**
	 * @return void
	 */
	public function handleLabel(int $productsId, int $typeId)
	{
		/** @var \ProductsEntity $productLabel */
		$productLabel = $this->repository->findEntity($productsId);
		$branchesId = $this->session->filters[$this->getName()]['custom__branches'] ?? $this->adminUser->default_branches_id ?: ($this->settings->default_branch ?? 1);

		if ($productLabel && ($productPrice = $productLabel->getPrice($branchesId))) {
			if (!$productLabel->getUnitValue($productPrice->price)) {
				$this['labelsForm']['price_text']->setItems(array_intersect_key($this->global['productLabelPriceTypes'], [\ProductLabelsEntity::PRICE_TYPE_CZK => '']));
			}

			$headerId = \ProductLabelsEntity::isAkceType($typeId) && isset($this->productLabelHeadersPairs[\ProductLabelsEntity::HEADER_TYPE_ACTION])
				? \ProductLabelsEntity::HEADER_TYPE_ACTION
				: array_key_first($this->productLabelHeadersPairs);
			$this['labelsForm']->setDefaults([
				'products_id' => $productsId,
				'branches_id' => $branchesId,
				'type_id' => $typeId,
				'product_label_headers_id' => $headerId,
				'sale_text' => '',
				'sale_percent' => 0,
				'ingredients' => NULL,
				'price_unit' => NULL,
				'rozmrazeno' => $productLabel->isRozmrazeno(),
				'is_more_species' => 0,
				'price_text' => \ProductLabelsEntity::PRICE_TYPE_CZK,
				'origin_countries_id' => $productLabel->country_origin ?: NULL,
				'name' => $productLabel->name,
				'count' => 1,
				'note_1' => $productLabel->label_note_1,
				'note_2' => $productLabel->label_note_2,
				'note_3' => $productLabel->label_note_3,
				'hide_validity' => 0,
				'hide_ingredients' => 0,
				'default_units_id' => $productLabel->default_units_id,
				'default_units_weight' => $productLabel->default_units_weight,
			], TRUE);

			// Texts come from this template's label, or from the product's shared texts
			// for a template without one; count and header only from the template's label.
			// A label keeps its edits after printing; only the count starts again.
			$typeLabel = $this->productLabelsRepo->findEntityBy([
				'products_id' => $productsId,
				'branches_id' => $branchesId,
				'type_id' => $typeId,
				'admin_users_id' => $this->adminUser->id
			]);
			if ($entity = $typeLabel ?: $this->productLabelsRepo->findShared($productsId, (int)$branchesId, (int)$this->adminUser->id)) {
				$fields = $this['labelsForm']->getComponents();
				$values = array_intersect_key($entity->getValues(TRUE), is_array($fields) ? $fields : iterator_to_array($fields));
				$values['type_id'] = $typeId;
				$values['product_label_headers_id'] = $typeLabel ? $typeLabel->product_label_headers_id : $headerId;
				$values['count'] = $typeLabel && $typeLabel->isToPrint() ? (int)$typeLabel->count : 1;
				$values['rozmrazeno'] = $productLabel->isRozmrazeno();
				// new columns: read through the row (Nette column auto-detection)
				$values['ingredients'] = $entity->getRow()->ingredients ?? NULL;
				$values['price_unit'] = in_array($entity->getRow()->price_unit ?? NULL, \ProductsEntity::PRICE_UNITS, TRUE) ? $entity->getRow()->price_unit : NULL;
				$this['labelsForm']->setDefaults($values);
			}

			$this->template->productLabel = $productLabel;
			$this->template->labelTypeId = $typeId;
			$this->template->labelIsAkce = \ProductLabelsEntity::isAkceType($typeId);
			$this->template->labelAutoPct = $productPrice->price_action && (float)$productPrice->price > (float)$productPrice->price_action
				? (int)round(((float)$productPrice->price - (float)$productPrice->price_action) / (float)$productPrice->price * 100)
				: 0;
		}

		$this->redrawControl('labelsForm');
	}


	/**
	 * @return Form
	 */
	protected function createComponentMultiLabelsForm(): Form
	{
		$form = new Form();
		$form->setTranslator($this->translator);

		$form->addText('products_id');
		$form->addText('branches_id');

		$form->addText('name', 'admin.terms.name')->setRequired();

		$form->addSelect('product_label_headers_id', 'admin.terms.header', $this->productLabelHeadersPairs)->setRequired();
		$form->addSelect('is_more_species', 'admin.terms.moreSpecies', $this->global['bool'])->setDefaultValue(0);
		$form->addSelect('origin_countries_id', 'admin.terms.countryOrigin', $this->countriesPairs)
			->setTranslator(NULL)
			->setPrompt('-');
		$form->addText('sale_text', 'admin.terms.labelSaleText');
		$form->addText('sale_percent', 'admin.terms.labelSalePercent')
			->setHtmlAttribute('type', 'number')
			->addCondition(Form::FILLED, TRUE)
			->addRule(Form::INTEGER);
		$form->addSelect('price_text', 'admin.terms.labelPriceText', $this->global['productLabelPriceTypes']);
		$form->addCheckbox('hide_validity', NULL);
		$form->addCheckbox('hide_ingredients', NULL);
		$form->addCheckbox('thawed', 'admin.terms.thawed');
		$form->addText('note_1', 'admin.terms.note1');
		$form->addText('note_2', 'admin.terms.note2');
		$form->addText('note_3', 'admin.terms.note3');

		$form->addSelect('default_units_id', NULL, \ProductsEntity::UNITS)->setTranslator(NULL)->setPrompt('-');
		$form->addText('default_units_weight', 'admin.terms.defaultPriceWeight')
			->setNullable()
			->addCondition(Form::FILLED, TRUE)
			->addRule(Form::FLOAT);

		foreach (\ProductLabelsEntity::TYPES_ARR as $productLabelTypeId => $productLabelType) {
			if (\ProductLabelsEntity::isLockedType($productLabelTypeId)) {
				continue;
			}
			$form->addText('label_type_count__'.$productLabelTypeId, $productLabelType['term'])
				->setRequired()
				->addRule(Form::INTEGER)
				->addRule(Form::MIN, NULL, 0)
				->setDefaultValue(0);
		}

		$form->addSubmit('submit', 'admin.buttons.save');

		$form->onSuccess[] = [$this, 'multiLabelsFormSubmitted'];

		return $form;
	}


	/**
	 * @param Form $form
	 * @return void
	 * @throws AbortException
	 * @throws \Nette\Utils\UnknownImageFileException
	 */
	public function multiLabelsFormSubmitted(Form $form)
	{
		$values = $this->getEditFormValues($form, $this->getAction());
		$values['datetime_printed'] = NULL;
		$values['count'] = 0;
		$values['admin_users_id'] = $this->adminUser->id;
		if (isset($values['default_units_weight']) && $values['default_units_weight'] === '') {
			$values['default_units_weight'] = NULL;
		}
		if (isset($values['default_units_id']) && $values['default_units_id'] === '') {
			$values['default_units_id'] = NULL;
		}
		if (isset($values['origin_countries_id']) && $values['origin_countries_id'] === '') {
			$values['origin_countries_id'] = NULL;
		}
		if ($entity = $this->productLabelsRepo->findEntityBy([
			'products_id' => $values['products_id'],
			'branches_id' => $values['branches_id'],
			'type_id' => 0,
			'admin_users_id' => $this->adminUser->id
		])) {
			$entity->setValues($values);
			$this->productLabelsRepo->update($entity);
		}
		else {
			$this->productLabelsRepo->insert($values);
		}

		foreach ($this->global['productLabelTypes'] as $productLabelTypeId => $productLabelType) {
			if (!\ProductLabelsEntity::isLockedType($productLabelTypeId) && isset($values['label_type_count__'.$productLabelTypeId])) {
				$values['datetime_printed'] = NULL;
				$values['count'] = $values['label_type_count__'.$productLabelTypeId];
				$values['type_id'] = $productLabelTypeId;
				if ($entity = $this->productLabelsRepo->findEntityBy([
					'products_id' => $values['products_id'],
					'branches_id' => $values['branches_id'],
					'type_id' => $productLabelTypeId,
					'admin_users_id' => $this->adminUser->id
				])) {
					$entity->setValues($values);
					$this->productLabelsRepo->update($entity);
				}
				else {
					$this->productLabelsRepo->insert($values);
				}
			}
		}

		$this->redrawProductRow((int)$values['products_id']);
	}


	/**
	 * @return void
	 */
	public function handleMultiLabel(int $productsId)
	{
		/** @var \ProductsEntity $productLabel */
		$productLabel = $this->repository->findEntity($productsId);
		$branchesId = $this->session->filters[$this->getName()]['custom__branches'] ?? $this->adminUser->default_branches_id ?: ($this->settings->default_branch ?? 1);

		if ($productLabel && ($productPrice = $productLabel->getPrice($branchesId))) {
			if (!$productLabel->getUnitValue($productPrice->price)) {
				$this['multiLabelsForm']['price_text']->setItems(array_intersect_key($this->global['productLabelPriceTypes'], [\ProductLabelsEntity::PRICE_TYPE_CZK => '']));
			}

			$this['multiLabelsForm']->setDefaults([
				'products_id' => $productsId,
				'branches_id' => $branchesId,
				'sale_text' => '',
				'sale_percent' => $productPrice->getActionPercents(TRUE),
				'is_more_species' => 0,
				'price_text' => \ProductLabelsEntity::PRICE_TYPE_CZK,
				'origin_countries_id' => $productLabel->country_origin ?: NULL,
				'name' => $productLabel->name,
				'note_1' => $productLabel->label_note_1,
				'note_2' => $productLabel->label_note_2,
				'note_3' => $productLabel->label_note_3,
				'default_units_id' => $productLabel->default_units_id,
				'default_units_weight' => $productLabel->default_units_weight,
			], TRUE);

			foreach ($this->global['productLabelTypes'] as $productLabelTypeId => $productLabelType) {
				if (!\ProductLabelsEntity::isLockedType($productLabelTypeId)) {
					$this['multiLabelsForm']->setDefaults(['label_type_count__'.$productLabelTypeId => 0]);
				}
			}

			if ($entity = $this->productLabelsRepo->findEntityBy([
				'products_id' => $productsId,
				'branches_id' => $branchesId,
				'type_id' => 0,
				'admin_users_id' => $this->adminUser->id
			])) {
				if (!$entity->datetime_printed && $this->productLabelsRepo->findAll()->where([
						'products_id' => $productsId,
						'branches_id' => $branchesId,
						'type_id > ?' => 0,
						'admin_users_id' => $this->adminUser->id,
						'count > ?' => 0
					])->where('datetime_printed IS NULL')->count()) {
					$this['multiLabelsForm']->setDefaults($entity->getValues(TRUE));

					foreach ($this->global['productLabelTypes'] as $productLabelTypeId => $productLabelType) {
						if (!\ProductLabelsEntity::isLockedType($productLabelTypeId) && ($productLabelItem = $this->productLabelsRepo->findEntityBy([
								'products_id' => $productLabel->id,
								'type_id' => $productLabelTypeId,
								'admin_users_id' => $this->adminUser->id
							])) && $productLabelItem->isToPrint()) {
							$this['multiLabelsForm']->setDefaults(['label_type_count__'.$productLabelTypeId => $productLabelItem->count]);
						}
					}
				}
			}

			$this->template->productLabel = $productLabel;
		}

		$this->redrawControl('multiLabelsForm');
	}


	/**
	 * @param $itemId
	 * @return void
	 * @throws AbortException
	 */
	public function handleDeleteLabel($itemId)
	{
		$product = NULL;
		if ($item = $this->productLabelsRepo->findEntity($itemId)) {
			$product = $item->getProduct();
			$item->count = 0;
			$this->productLabelsRepo->update($item);
		}

		if (!$product) {
			$this->redirect('this');
		}
		$this->redrawProductRow((int)$product->id);
	}


	/**
	 * Product IDs (ticked in the product list) whose A4 label is printed with the new HTML template.
	 * Everything else keeps using the legacy TCPDF A4 layout.
	 *
	 * @return int[]
	 */
	public function getNewA4ProductIds(): array
	{
		$dbIds = [];
		try {
			if (in_array('use_new_template', $this->repository->getTableColumnNames('products'), TRUE)) {
				$dbIds = array_map('intval', array_keys($this->repository->getDatabase()->table('products')
					->where('use_new_template', 1)
					->fetchPairs('id', 'id')));
			}
		} catch (\Throwable $e) {
		}

		$sessionIds = $this->session->newA4TemplateProducts;
		$sessionIds = is_array($sessionIds) ? array_map('intval', array_keys(array_filter($sessionIds))) : [];

		return array_values(array_unique(array_merge($dbIds, $sessionIds)));
	}


	/**
	 * @param int $productsId
	 * @param bool $enabled TRUE = print A4 with the new template, FALSE = legacy TCPDF
	 * @return void
	 */
	public function handleToggleNewA4Template(int $productsId, bool $enabled = FALSE): void
	{
		try {
			if (in_array('use_new_template', $this->repository->getTableColumnNames('products'), TRUE)) {
				$this->repository->getDatabase()->table('products')
					->where('id', $productsId)
					->update(['use_new_template' => $enabled ? 1 : 0]);
			}
		} catch (\Throwable $e) {
		}

		$ids = $this->session->newA4TemplateProducts;
		$ids = is_array($ids) ? $ids : [];
		if ($enabled) {
			$ids[$productsId] = TRUE;
		}
		else {
			unset($ids[$productsId]);
		}
		$this->session->newA4TemplateProducts = $ids;

		$this->payload->newA4Template = ['productsId' => $productsId, 'enabled' => $enabled];
		$this->sendPayload();
	}


	/**
	 * @return void
	 * @throws AbortException
	 */
	public function handleToPrint()
	{
		$this->session->filters[$this->getName()]['custom__is_action'] = NULL;
		$this->session->filters[$this->getName()]['custom__name'] = '';
		$this->session->filters[$this->getName()]['custom__code_ean'] = '';
		$this->session->filters[$this->getName()]['custom__is_active'] = NULL;
		$this->session->filters[$this->getName()]['custom__labels'] = TRUE;

		$this->redirect('this');
	}


	/**
	 * @param $itemId
	 * @param $printed
	 * @param string|null $overridePrice Raw "Nền tảng" input value, not yet saved to product_prices.
	 * @param string|null $overridePriceAction Raw "Hoạt động" input value, not yet saved.
	 * @param string|null $overrideActionFrom Raw "Hành động từ" input value (d.m.Y), not yet saved.
	 * @param string|null $overrideActionTo Raw "Hành động cho đến khi" input value (d.m.Y), not yet saved.
	 * @return void
	 * @throws AbortException
	 */
	public function handleDownloadPdf($itemId = NULL, $printed = TRUE, $overridePrice = NULL, $overridePriceAction = NULL, $overrideActionFrom = NULL, $overrideActionTo = NULL, $priceOverrides = NULL)
	{
		// Lets "Xem trước"/"Tải xuống" reflect price/date fields the admin just typed in
		// the product list row but hasn't clicked the save (floppy disk) icon for yet —
		// without writing anything to product_prices.
		$priceOverride = $this->parsePriceOverrides($priceOverrides);
		if ($itemId && ($overridePrice !== NULL || $overridePriceAction !== NULL || $overrideActionFrom !== NULL || $overrideActionTo !== NULL)) {
			$labelProductId = (int)($this->productLabelsRepo->findEntity($itemId)?->products_id ?? 0);
			if ($labelProductId) {
				$priceOverride[$labelProductId] = $this->normalizePriceOverride([
					'price' => $overridePrice,
					'price_action' => $overridePriceAction,
					'action_from' => $overrideActionFrom,
					'action_to' => $overrideActionTo,
				]);
			}
		}
		$priceOverride = $priceOverride ?: NULL;

		$isDownload = !in_array($printed, [FALSE, 0, '0', 'false', 'FALSE', ''], TRUE);

		if ($file = $this->productLabelsService->generatePdf($this->session->filters[$this->getName()]['custom__branches'], (bool)$isDownload, $itemId ? [$itemId] : [], $priceOverride, $this->getNewA4ProductIds())) {
			$this->session->labelsIsPrint = TRUE;
			if ($file instanceof \TCPDF) {
				$file->Output(date('Y_m_d').'-'.time().'.pdf', $isDownload ? 'D' : 'I');
				exit;
			}
			elseif ($file instanceof \Dompdf\Dompdf) {
				header('Content-Type: application/pdf');
				header('Content-Disposition: ' . ($isDownload ? 'attachment' : 'inline') . '; filename="' . date('Y_m_d') . '-' . time() . '.pdf"');
				// Every template edit needs to show up on the very next open of this
				// same URL — no explicit cache header meant browsers were free to
				// serve a stale PDF (e.g. a re-focused tab, back/forward cache)
				// after the template was fixed server-side.
				header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
				header('Pragma: no-cache');
				echo $file->output();
				exit;
			}
			elseif (file_exists(\ProductLabelsEntity::ZIP_FILE_PATH.'/'.\ProductLabelsEntity::ZIP_FILE_NAME)) {
				header("Content-type: application/zip");
				header("Content-Disposition: attachment; filename=".date('Y_m_d')."-".time().".zip");
				readfile(\ProductLabelsEntity::ZIP_FILE_PATH.'/'.\ProductLabelsEntity::ZIP_FILE_NAME);
				exit;
			}
		}

		echo '<script>window.close();</script>';
		exit;
	}


	/**
	 * Download / print / preview 1 single label for a product & label size.
	 *
	 * @param int $productsId
	 * @param int $typeId
	 * @param bool $isDownload TRUE = download attachment, FALSE = preview inline in browser
	 * @return void
	 * @throws AbortException
	 */
	public function handleDownloadSingleProductLabelPdf(int $productsId, int $typeId, bool $isDownload = TRUE, $priceOverrides = NULL): void
	{
		$branchesId = $this->session->filters[$this->getName()]['custom__branches'] ?? 0;
		$adminUserId = $this->adminUser->id ?? 0;

		$label = $this->productLabelsRepo->findEntityBy([
			'products_id' => $productsId,
			'branches_id' => $branchesId,
			'admin_users_id' => $adminUserId,
			'type_id' => $typeId,
		]);

		// a preview renders the label once without queueing it (count 0)
		if (!$label && !\ProductLabelsEntity::isLockedType($typeId) && ($product = $this->repository->findEntity($productsId))) {
			$label = $this->productLabelsRepo->insertForProduct($product, $branchesId, $adminUserId, $typeId, 0);
		}

		if ($label) {
			if ($isDownload && (int)$label->count <= 0) {
				$this->flashMessage($this->trans('admin.terms.noLabelsToPrint') ?? 'Vui lòng chọn số lượng tem lớn hơn 0 để tải.', 'warning');
				$this->redirect('this');
			}
			$this->handleDownloadPdf($label->id, $isDownload, NULL, NULL, NULL, NULL, $priceOverrides);
		}
	}


	/**
	 * Preview or download all label types for a single product row.
	 *
	 * @param int $productsId
	 * @param bool $isDownload TRUE = download attachment, FALSE = preview inline in browser
	 * @param string|null $overridePrice
	 * @param string|null $overridePriceAction
	 * @param string|null $overrideActionFrom
	 * @param string|null $overrideActionTo
	 * @return void
	 * @throws AbortException
	 */
	public function handleDownloadProductAllLabelsPdf(int $productsId, bool $isDownload = TRUE, $overridePrice = NULL, $overridePriceAction = NULL, $overrideActionFrom = NULL, $overrideActionTo = NULL, $priceOverrides = NULL): void
	{
		$branchesId = (int)($this->session->filters[$this->getName()]['custom__branches'] ?? 0);
		$adminUserId = (int)($this->adminUser->id ?? 0);
		$product = $this->repository->findEntity($productsId);

		if (!$product) {
			$this->redirect('this');
		}

		$priceOverride = $this->parsePriceOverrides($priceOverrides);
		if ($overridePrice !== NULL || $overridePriceAction !== NULL || $overrideActionFrom !== NULL || $overrideActionTo !== NULL) {
			$priceOverride[$productsId] = $this->normalizePriceOverride([
				'price' => $overridePrice,
				'price_action' => $overridePriceAction,
				'action_from' => $overrideActionFrom,
				'action_to' => $overrideActionTo,
			]);
		}
		$priceOverride = $priceOverride ?: NULL;

		// Queued labels; a preview without any shows every template once (count 0,
		// nothing is queued).
		$labelIds = [];
		$allIds = [];
		foreach (array_keys(\ProductLabelsEntity::TYPES_ARR) as $typeId) {
			if (\ProductLabelsEntity::isLockedType($typeId)) {
				continue;
			}
			$label = $this->productLabelsRepo->findEntityBy([
				'products_id' => $productsId,
				'branches_id' => $branchesId,
				'admin_users_id' => $adminUserId,
				'type_id' => $typeId,
			]);
			if ($label && $label->isToPrint()) {
				$labelIds[] = $label->id;
			}
			elseif (!$isDownload) {
				$label = $label ?: $this->productLabelsRepo->insertForProduct($product, $branchesId, $adminUserId, $typeId, 0);
				$allIds[] = $label->id;
			}
		}
		if (!$labelIds && !$isDownload) {
			$labelIds = $allIds;
		}

		if (empty($labelIds)) {
			if ($isDownload) {
				$this->flashMessage($this->trans('admin.terms.noLabelsToPrint') ?? 'Sản phẩm này chưa được chọn số lượng tem để tải.', 'warning');
				$this->redirect('this');
			}
		}

		$file = $this->productLabelsService->generatePdf($branchesId, (bool)$isDownload, $labelIds, $priceOverride, $this->getNewA4ProductIds());
		if ($file) {
			$this->session->labelsIsPrint = TRUE;
			$filename = \Nette\Utils\Strings::webalize($product->name) . '-labels-' . date('Y_m_d') . '-' . time() . '.pdf';
			$disposition = $isDownload ? 'attachment' : 'inline';
			$tcpdfMode = $isDownload ? 'D' : 'I';
			if ($file instanceof \TCPDF) {
				$file->Output($filename, $tcpdfMode);
				exit;
			}
			elseif ($file instanceof \Dompdf\Dompdf) {
				header('Content-Type: application/pdf');
				header('Content-Disposition: ' . $disposition . '; filename="' . $filename . '"');
				echo $file->output();
				exit;
			}
		}

		echo '<script>window.close();</script>';
		exit;
	}


	/**
	 * @return void
	 */
	public function handleRemovePrint()
	{
		$this->productLabelsRepo->findAll()
			->where('branches_id', ($this->session->filters[$this->getName()]['custom__branches'] ?? 0))
			->where('admin_users_id', $this->adminUser->id)
			->update([
				'datetime_printed' => date('Y-m-d H:i:s'),
				'count' => 0
			]);

		$this->redirect('this');
	}


	/**
	 * @param $idItem
	 * @param $productsId
	 * @param string|NULL $form
	 * @return void
	 * @throws AbortException
	 */
	public function handleSetLabelActionType($idItem = NULL, $productsId = NULL, string $form = NULL)
	{
		$branchesId = $this->session->filters[$this->getName()]['custom__branches'];

		if ($idItem && $productsId && $form && ($item = $this->productLabelActionTypesRepo->findEntity($idItem)) && ($product = $this->repository->findEntity($productsId))) {
			$productPrice = $product->getPrice($branchesId);
			$this->payload->forms[$form]['sale_text'] = $item->getFormattedValue($productPrice);
		}

		$this->sendPayload();
	}


	/**
	 * @return array
	 */
	public function getQueuedPrintSummary(): array
	{
		$branchesId = (int)($this->session->filters[$this->getName()]['custom__branches'] ?? 0);
		$adminUserId = (int)($this->adminUser->id ?? 0);

		$labels = $this->productLabelsRepo->findAll()
			->where('branches_id', $branchesId)
			->where('admin_users_id', $adminUserId)
			->where('datetime_printed IS NULL')
			->where('count > ?', 0)
			->where('type_id > ?', 0);

		$totalLabels = 0;
		$productIds = [];
		$grouped = [];
		foreach (\ProductLabelsEntity::TYPES_ARR as $typeId => $def) {
			$grouped[$typeId] = [
				'typeId' => $typeId,
				'name' => $this->global['productLabelTypes'][$typeId] ?? (string)$typeId,
				'dims' => $def['dims'].' mm',
				'perPage' => $def['perPage'],
				'isAkce' => $def['group'] === \ProductLabelsEntity::GROUP_AKCE,
				'items' => [],
				'count' => 0,
			];
		}

		foreach ($labels as $row) {
			$label = \ProductLabelsRepository::getEntity($row);
			$count = (int)$label->count;
			$totalLabels += $count;
			$productIds[$label->products_id] = TRUE;

			if (isset($grouped[$label->type_id])) {
				$grouped[$label->type_id]['count'] += $count;
				$product = $label->getProduct();
				$price = $product ? $product->getPrice($branchesId) : NULL;
				$grouped[$label->type_id]['items'][] = [
					'label' => $label,
					'product' => $product,
					'price' => $price,
					'count' => $count,
				];
			}
		}

		foreach ($grouped as $typeId => &$group) {
			$group['pages'] = (int)ceil($group['count'] / max(1, $group['perPage']));
		}

		return [
			'totalLabels' => $totalLabels,
			'selectedProductsCount' => count($productIds),
			'groups' => array_filter($grouped, static fn($g) => $g['count'] > 0),
			'allGroups' => $grouped,
		];
	}


	/**
	 * "Vymazat": empties the print queue; the page reloads (also on ajax) so every
	 * row shows its new counts.
	 */
	public function handleClearAllPrintCounts()
	{
		$this->productLabelsRepo->findAll()
			->where('branches_id', (int)($this->session->filters[$this->getName()]['custom__branches'] ?? 0))
			->where('admin_users_id', (int)($this->adminUser->id ?? 0))
			->where('datetime_printed IS NULL')
			->update(['count' => 0]);

		$this->redirect('this');
	}


	/**
	 * Náhled tisku v3 of the admin's queue: cards and links for the shared
	 * __components/PrintPreview/printPreview.latte.
	 * @param array{totalLabels: int, groups: array} $summary getQueuedPrintSummary()
	 */
	public function buildPrintPreview(array $summary): array
	{
		$branchesId = (int)($this->session->filters[$this->getName()]['custom__branches'] ?? 0);
		$groups = [];
		$pages = 0;
		foreach ($summary['groups'] as $typeId => $group) {
			$cards = [];
			foreach ($group['items'] as $it) {
				/** @var \ProductLabelsEntity $label */
				$label = $it['label'];
				$product = $it['product'];
				$productsId = (int)$label->products_id;
				$texts = array_filter([
					'name' => $label->name !== ($product->name ?? NULL) ? $label->name : NULL,
					'is_more_species' => $label->is_more_species,
					'ingredients' => $label->getRow()->ingredients ?? NULL,
					'thawed' => $label->thawed,
					'price_unit' => $label->getRow()->price_unit ?? NULL,
				]);
				$cards[] = [
					'qty' => (int)$it['count'],
					'headerId' => (int)$label->product_label_headers_id,
					'originId' => (int)($label->origin_countries_id ?: ($product->country_origin ?? 0)),
					// a component link, so its parameters get the component prefix
					'headerUrl' => $this['productLabels-' . $productsId]->link('setHeader!', ['typeId' => (int)$typeId, 'headerId' => 999999]),
					'originUrl' => $this->link('setLabelOrigin!', ['productsId' => $productsId, 'countryId' => 999999]),
					'decUrl' => $this->link('decrementQueue!', ['labelId' => (int)$label->id]),
					'incUrl' => $this->link('incrementQueue!', ['labelId' => (int)$label->id]),
					'removeUrl' => $this->link('removeFromQueue!', ['labelId' => (int)$label->id]),
					'editUrl' => $this->link('label!', ['productsId' => $productsId, 'typeId' => (int)$typeId]),
					'editTarget' => '#modal-labelsForm',
					'viewUrl' => $this->link('downloadSingleProductLabelPdf!', ['productsId' => $productsId, 'typeId' => (int)$typeId, 'isDownload' => 0]),
					'pdfUrl' => $this->link('downloadSingleProductLabelPdf!', ['productsId' => $productsId, 'typeId' => (int)$typeId, 'isDownload' => 1]),
				] + \PrintPreviewInfo::of($product, $branchesId, (int)$typeId, $texts);
			}
			$pages += (int)$group['pages'];
			$groups[] = [
				'typeId' => (int)$typeId,
				'name' => $group['name'],
				'dims' => $group['dims'],
				'isAkce' => $group['isAkce'],
				'count' => (int)$group['count'],
				'pages' => (int)$group['pages'],
				'perPage' => (int)$group['perPage'],
				'viewUrl' => $this->link('downloadPdfGroup!', ['typeId' => (int)$typeId, 'isDownload' => 0]),
				'pdfUrl' => $this->link('downloadPdfGroup!', ['typeId' => (int)$typeId, 'isDownload' => 1]),
				'cards' => $cards,
			];
		}

		return [
			'totalCount' => (int)$summary['totalLabels'],
			'totalPages' => $pages,
			'clearUrl' => $this->link('clearAllPrintCounts!'),
			// the queue as a list of Tisk z mobilu (e.g. printed on another PC)
			'sendUrl' => $this->aps->has('PrintLists_edit') ? $this->link('queueToPrintList!') : NULL,
			'clearConfirm' => $this->trans('admin.terms.reallyRemoveLabels'),
			'viewAllUrl' => $this->link('downloadPdf!', ['itemId' => NULL, 'printed' => 0]),
			'downloadAllUrl' => $this->link('downloadPdf!', ['itemId' => NULL, 'printed' => 1]),
			'headersPairs' => $this->productLabelHeadersPairs,
			'countriesPairs' => $this->countriesPairs,
			'groups' => $groups,
		];
	}


	/** "Odeslat do Tisk z mobilu": the queue becomes a list there and is emptied here. */
	public function handleQueueToPrintList(): void
	{
		if (!$this->aps->has('PrintLists_edit')) {
			$this->redirect('this');
		}
		$branchesId = (int)($this->session->filters[$this->getName()]['custom__branches'] ?? 0);
		$listId = $this->printListsService->createFromQueue($branchesId, (int)$this->adminUser->id);
		if (!$listId) {
			$this->flashMessage($this->trans('admin.pl.queueEmpty'), 'danger');
			$this->redirect('this');
		}
		$this->getSession('printLists')->branch = $branchesId;
		$this->flashMessage(sprintf($this->trans('admin.pl.fromQueueDone'), $this->printListsService->get($branchesId, $listId)->list_no), 'success');
		$this->redirect('PrintLists:default', ['list' => $listId, 'filter' => 'waiting', 'date' => '']);
	}


	/** Původ chosen in a Náhled tisku card: shared by the product's labels, like the ✎ texts. */
	public function handleSetLabelOrigin(int $productsId, int $countryId = 0): void
	{
		$branchesId = (int)($this->session->filters[$this->getName()]['custom__branches'] ?? 0);
		$this->productLabelsRepo->saveShared($productsId, $branchesId, (int)$this->adminUser->id, ['origin_countries_id' => $countryId ?: NULL]);
		$this->redrawProductRow($productsId);
	}


	/** +1 / −1 / remove in the print preview modal. */
	public function handleIncrementQueue(int $labelId)
	{
		$this->changeQueuedCount($labelId, 1);
	}


	public function handleDecrementQueue(int $labelId)
	{
		$this->changeQueuedCount($labelId, -1);
	}


	public function handleRemoveFromQueue(int $labelId)
	{
		$this->changeQueuedCount($labelId, NULL);
	}


	/** @param int|null $delta NULL = remove from the queue */
	private function changeQueuedCount(int $labelId, ?int $delta): void
	{
		$label = $this->productLabelsRepo->findEntity($labelId);
		if (!$label) {
			$this->redirect('this');
		}
		$label->count = $delta === NULL ? 0 : max(0, (int)$label->count + $delta);
		$label->datetime_printed = NULL;
		$this->productLabelsRepo->update($label);

		$this->redrawProductRow((int)$label->products_id);
	}

	/**
	 * Sticky bar "+ {template} k vybraným": one more label of $typeId for every ticked
	 * product; a product with a running action gets the Akce twin (A4 -> A4 Akce …).
	 *
	 * @param string $ids comma separated product IDs
	 */
	public function handleBulkAddLabels(string $ids = '', int $typeId = 0): void
	{
		$branchesId = (int)($this->session->filters[$this->getName()]['custom__branches'] ?? 0);
		$added = 0;
		foreach (array_unique(array_filter(array_map('intval', explode(',', $ids)))) as $productsId) {
			$control = $this['productLabels-'.$productsId];
			$product = $control->getItem();
			if (!$product) {
				continue;
			}
			$running = ProductLabelsControl::getActionStatus($product->getPrice($branchesId)) === ProductLabelsControl::STATUS_RUNNING;
			$control->changeCount($running ? (ProductLabelsControl::AKCE_TWINS[$typeId] ?? $typeId) : $typeId, 1);
			$control->redrawAll();
			$added++;
		}

		$this->payload->pvToast = sprintf($this->trans('admin.pv.addedTo'), $added);
		if (!$this->isAjax()) {
			$this->redirect('this');
		}
		$this->redrawControl('printSummary');
		$this->redrawControl('printPreviewSnippet');
	}


	/**
	 * Filter pills: Vše / Probíhá akce / Naplánované / K tisku.
	 */
	public function handleStatusFilter(string $status = ''): void
	{
		$this->session->filters[$this->getName()]['custom__akce_status'] = in_array($status, [ProductLabelsControl::STATUS_RUNNING, ProductLabelsControl::STATUS_PLANNED], TRUE) ? $status : NULL;
		$this->session->filters[$this->getName()]['custom__labels'] = $status === 'toprint' ? 1 : NULL;
		$this->getPaginatorControl()->setPage(1);
		$this->redirect('this');
	}


	/**
	 * Counts on the filter pills, for the whole branch (not the current filter).
	 *
	 * @return array{all: int, running: int, planned: int, toprint: int}
	 */
	public function getStatusCounts(): array
	{
		$branchesId = (int)($this->session->filters[$this->getName()]['custom__branches'] ?? 0);
		$today = date('Y-m-d');
		$db = $this->repository->getDatabase();
		$action = 'price_action > 0 AND price > price_action AND action_from IS NOT NULL';

		return [
			'all' => $this->repository->findAll()->count('DISTINCT products.id'),
			'running' => (int)$db->table('product_prices')->where('branches_id', $branchesId)
				->where("$action AND action_from <= ? AND (action_to IS NULL OR action_to >= ?)", $today, $today)
				->count('DISTINCT products_id'),
			'planned' => (int)$db->table('product_prices')->where('branches_id', $branchesId)
				->where("$action AND action_from > ?", $today)
				->count('DISTINCT products_id'),
			'toprint' => (int)$db->table('product_labels')->where('branches_id', $branchesId)
				->where('admin_users_id', $this->adminUser->id)
				->where('datetime_printed IS NULL AND count > 0 AND type_id > 0')
				->count('DISTINCT products_id'),
		];
	}


	/**
	 * Unsaved price/date values typed in the product list rows, sent by the print links
	 * as JSON {productId: {price, price_action, action_from, action_to}} (see
	 * www/js/admin/custom.js). Returns them keyed by product ID; nothing is saved.
	 *
	 * @return array<int, array{price: ?float, price_action: ?float, action_from: ?string, action_to: ?string}>
	 */
	private function parsePriceOverrides($json): array
	{
		$data = is_string($json) && $json !== '' ? json_decode($json, TRUE) : NULL;
		if (!is_array($data)) {
			return [];
		}

		$overrides = [];
		foreach ($data as $productId => $values) {
			if ((int)$productId > 0 && is_array($values)) {
				$overrides[(int)$productId] = $this->normalizePriceOverride($values);
			}
		}

		return $overrides;
	}


	/**
	 * Same conventions as ProductPricesTrait::handleUpdatePricesAll() (the real save):
	 * empty = no value, dates as d.m.Y. A decimal comma is accepted too.
	 *
	 * @return array{price: ?float, price_action: ?float, action_from: ?string, action_to: ?string}
	 */
	private function normalizePriceOverride(array $values): array
	{
		$number = static function ($value): ?float {
			$value = trim(str_replace([' ', ','], ['', '.'], (string)($value ?? '')));
			return $value === '' || !is_numeric($value) ? NULL : (float)$value;
		};
		$date = static function ($value): ?string {
			$dt = $value ? \DateTime::createFromFormat('!d.m.Y', trim((string)$value)) : FALSE;
			return $dt ? $dt->format('Y-m-d') : NULL;
		};

		return [
			'price' => $number($values['price'] ?? NULL),
			'price_action' => $number($values['price_action'] ?? NULL),
			'action_from' => $date($values['action_from'] ?? NULL),
			'action_to' => $date($values['action_to'] ?? NULL),
		];
	}


	/**
	 * @param int $typeId
	 * @param bool $isDownload TRUE = download attachment, FALSE = preview inline in browser
	 * @return void
	 */
	public function handleDownloadPdfGroup(int $typeId, bool $isDownload = TRUE, $priceOverrides = NULL)
	{
		$branchesId = (int)($this->session->filters[$this->getName()]['custom__branches'] ?? 0);
		$adminUserId = (int)($this->adminUser->id ?? 0);

		$labelIds = $this->productLabelsRepo->findAll()
			->where('branches_id', $branchesId)
			->where('admin_users_id', $adminUserId)
			->where('type_id', $typeId)
			->where('datetime_printed IS NULL')
			->where('count > ?', 0)
			->fetchPairs(NULL, 'id');

		if (!$labelIds) {
			$this->flashMessage($this->trans('admin.terms.noLabelsToPrint') ?? 'Không có nhãn nào cần in.', 'warning');
			$this->redirect('this');
		}

		$file = $this->productLabelsService->generatePdf($branchesId, (bool)$isDownload, array_values($labelIds), $this->parsePriceOverrides($priceOverrides) ?: NULL, $this->getNewA4ProductIds());
		if ($file) {
			$this->session->labelsIsPrint = TRUE;
			$typeName = $this->global['productLabelTypes'][$typeId] ?? ('group-'.$typeId);
			$filename = \Nette\Utils\Strings::webalize($typeName) . '-' . date('Y_m_d') . '-' . time() . '.pdf';
			$disposition = $isDownload ? 'attachment' : 'inline';
			$tcpdfMode = $isDownload ? 'D' : 'I';
			if ($file instanceof \TCPDF) {
				$file->Output($filename, $tcpdfMode);
				exit;
			}
			elseif ($file instanceof \Dompdf\Dompdf) {
				header('Content-Type: application/pdf');
				header('Content-Disposition: ' . $disposition . '; filename="' . $filename . '"');
				echo $file->output();
				exit;
			}
		}

		echo '<script>window.close();</script>';
		exit;
	}
}
