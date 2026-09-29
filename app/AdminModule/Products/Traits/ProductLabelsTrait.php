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
			$control->setBranchesId($this->session->filters[$this->getName()]['custom__branches']);

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
		$form->addText('count', 'admin.terms.numberOfPrints')->setRequired()->addRule(Form::INTEGER)->addRule(Form::MIN, NULL, 1);

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
		$form->addText('note_1', 'admin.terms.note1');
		$form->addText('note_2', 'admin.terms.note2');
		$form->addText('note_3', 'admin.terms.note3');

		$form->addSelect('default_units_id', NULL, \ProductsEntity::UNITS)->setTranslator(NULL)->setPrompt('-');
		$form->addText('default_units_weight', 'admin.terms.defaultPriceWeight');

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
		if ($entity = $this->productLabelsRepo->findEntityBy([
			'products_id' => $values['products_id'],
			'branches_id' => $values['branches_id'],
			'type_id' => $values['type_id'],
			'admin_users_id' => $this->adminUser->id
		])) {
			$entity->setValues($values);
			$this->productLabelsRepo->update($entity);
		}
		else {
			$this->productLabelsRepo->insert($values);
		}

		if (isset($this['productActions-'.$values['products_id']])) {
			$this['productLabels-'.$values['products_id']]->redrawControl('list');
		}
		else {
			$this->redirect('this');
		}
	}


	/**
	 * @return void
	 */
	public function handleLabel(int $productsId, int $typeId)
	{
		/** @var \ProductsEntity $productLabel */
		$productLabel = $this->repository->findEntity($productsId);
		$branchesId = $this->session->filters[$this->getName()]['custom__branches'];

		if ($productLabel && ($productPrice = $productLabel->getPrice($branchesId))) {
			if (!$productLabel->getUnitValue($productPrice->price)) {
				$this['labelsForm']['price_text']->setItems(array_intersect_key($this->global['productLabelPriceTypes'], [\ProductLabelsEntity::PRICE_TYPE_CZK => '']));
			}

			$this['labelsForm']->setDefaults([
				'products_id' => $productsId,
				'branches_id' => $branchesId,
				'type_id' => $typeId,
				'sale_text' => '',
				'sale_percent' => $productPrice->getActionPercents(TRUE),
				'is_more_species' => 0,
				'price_text' => \ProductLabelsEntity::PRICE_TYPE_CZK,
				'origin_countries_id' => $productLabel->country_origin ?: NULL,
				'name' => $productLabel->name,
				'count' => 1,
				'note_1' => $productLabel->label_note_1,
				'note_2' => $productLabel->label_note_2,
				'note_3' => $productLabel->label_note_3,
				'default_units_id' => $productLabel->default_units_id,
				'default_units_weight' => $productLabel->default_units_weight,
			], TRUE);

			if ($entity = $this->productLabelsRepo->findEntityBy([
				'products_id' => $productsId,
				'branches_id' => $this->session->filters[$this->getName()]['custom__branches'],
				'type_id' => $typeId,
				'admin_users_id' => $this->adminUser->id
			])) {
				if ($entity->isToPrint()) {
					$this['labelsForm']->setDefaults($entity->getValues(TRUE));
				}
			}

			$this->template->productLabel = $productLabel;
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
		$form->addText('note_1', 'admin.terms.note1');
		$form->addText('note_2', 'admin.terms.note2');
		$form->addText('note_3', 'admin.terms.note3');

		$form->addSelect('default_units_id', NULL, \ProductsEntity::UNITS)->setTranslator(NULL)->setPrompt('-');
		$form->addText('default_units_weight', 'admin.terms.defaultPriceWeight');

		foreach (\ProductLabelsEntity::TYPES_ARR as $productLabelTypeId => $productLabelType) {
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
			if (isset($values['label_type_count__'.$productLabelTypeId])) {
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

		if (isset($this['productActions-'.$values['products_id']])) {
			$this['productLabels-'.$values['products_id']]->redrawControl('list');
		}
		else {
			$this->redirect('this');
		}
	}


	/**
	 * @return void
	 */
	public function handleMultiLabel(int $productsId)
	{
		/** @var \ProductsEntity $productLabel */
		$productLabel = $this->repository->findEntity($productsId);
		$branchesId = $this->session->filters[$this->getName()]['custom__branches'];

		if ($productLabel && ($productPrice = $productLabel->getPrice($branchesId))) {
			if (!$productLabel->getUnitValue($productPrice->price)) {
				$this['labelsForm']['price_text']->setItems(array_intersect_key($this->global['productLabelPriceTypes'], [\ProductLabelsEntity::PRICE_TYPE_CZK => '']));
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
				$this['multiLabelsForm']->setDefaults(['label_type_count__'.$productLabelTypeId => 0]);
			}

			if ($entity = $this->productLabelsRepo->findEntityBy([
				'products_id' => $productsId,
				'branches_id' => $this->session->filters[$this->getName()]['custom__branches'],
				'type_id' => 0,
				'admin_users_id' => $this->adminUser->id
			])) {
				if (!$entity->datetime_printed && $this->productLabelsRepo->findAll()->where([
						'products_id' => $productsId,
						'branches_id' => $this->session->filters[$this->getName()]['custom__branches'],
						'type_id > ?' => 0,
						'admin_users_id' => $this->adminUser->id,
						'count > ?' => 0
					])->where('datetime_printed IS NULL')->count()) {
					$this['multiLabelsForm']->setDefaults($entity->getValues(TRUE));

					foreach ($this->global['productLabelTypes'] as $productLabelTypeId => $productLabelType) {
						if (($productLabelItem = $this->productLabelsRepo->findEntityBy([
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

		if ($product && isset($this['productActions-'.$product->id])) {
			$this['productLabels-'.$product->id]->redrawControl('list');
		}
		else {
			$this->redirect('this');
		}
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
	public function handleDownloadPdf($itemId = NULL, $printed = TRUE, $overridePrice = NULL, $overridePriceAction = NULL, $overrideActionFrom = NULL, $overrideActionTo = NULL)
	{
		// Lets "Xem trước"/"Tải xuống" reflect price/date fields the admin just typed in
		// the product list row but hasn't clicked the save (floppy disk) icon for yet —
		// without writing anything to product_prices. Same 'd.m.Y' parsing convention as
		// ProductPricesTrait::handleUpdatePricesAll(), which is what a real save uses.
		$priceOverride = NULL;
		if ($overridePrice !== NULL || $overridePriceAction !== NULL || $overrideActionFrom !== NULL || $overrideActionTo !== NULL) {
			$parseDate = static function (?string $value): ?string {
				if (!$value) {
					return NULL;
				}
				$dt = \DateTime::createFromFormat('d.m.Y', $value);
				return $dt ? $dt->format('Y-m-d') : NULL;
			};
			$priceOverride = [
				'price' => $overridePrice !== NULL && $overridePrice !== '' ? (float)$overridePrice : NULL,
				'price_action' => $overridePriceAction !== NULL && $overridePriceAction !== '' ? (float)$overridePriceAction : NULL,
				'action_from' => $parseDate($overrideActionFrom),
				'action_to' => $parseDate($overrideActionTo),
			];
		}

		$isDownload = !in_array($printed, [FALSE, 0, '0', 'false', 'FALSE', ''], TRUE);

		if ($file = $this->productLabelsService->generatePdf($this->session->filters[$this->getName()]['custom__branches'], (bool)$isDownload, $itemId ? [$itemId] : [], $priceOverride)) {
			$this->session->labelsIsPrint = TRUE;
			if ($file instanceof \TCPDF) {
				$file->Output(date('Y_m_d').'-'.time().'.pdf', $isDownload ? 'D' : 'I');
				exit;
			}
			elseif ($file instanceof \Dompdf\Dompdf) {
				header('Content-Type: application/pdf');
				header('Content-Disposition: ' . ($isDownload ? 'attachment' : 'inline') . '; filename="' . date('Y_m_d') . '-' . time() . '.pdf"');
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
	public function handleDownloadSingleProductLabelPdf(int $productsId, int $typeId, bool $isDownload = TRUE): void
	{
		$branchesId = $this->session->filters[$this->getName()]['custom__branches'] ?? 0;
		$adminUserId = $this->adminUser->id ?? 0;

		$label = $this->productLabelsRepo->findEntityBy([
			'products_id' => $productsId,
			'branches_id' => $branchesId,
			'admin_users_id' => $adminUserId,
			'type_id' => $typeId,
		]);

		if (!$label && ($product = $this->repository->findEntity($productsId))) {
			$productPrice = $product->getPrice($branchesId);
			$labelHeader = $this->productLabelHeadersRepo->findAll()->order('pos_order')->limit(1)->fetch();
			$row = $this->productLabelsRepo->insert([
				'product_label_headers_id' => $labelHeader ? $labelHeader->id : NULL,
				'products_id' => $product->id,
				'branches_id' => $branchesId,
				'admin_users_id' => $adminUserId,
				'count' => $isDownload ? 0 : 1,
				'datetime_printed' => NULL,
				'type_id' => $typeId,
				'sale_text' => '',
				'sale_percent' => $productPrice ? $productPrice->getActionPercents(TRUE) : 0,
				'is_more_species' => 0,
				'price_text' => \ProductLabelsEntity::PRICE_TYPE_CZK,
				'origin_countries_id' => $product->country_origin ?: NULL,
				'name' => $product->name,
				'note_1' => $product->label_note_1,
				'note_2' => $product->label_note_2,
				'note_3' => $product->label_note_3,
			]);
			$label = \ProductLabelsRepository::getEntity($row);
		}

		if ($label) {
			if ($isDownload && (int)$label->count <= 0) {
				$this->flashMessage($this->trans('admin.terms.noLabelsToPrint') ?? 'Vui lòng chọn số lượng tem lớn hơn 0 để tải.', 'warning');
				$this->redirect('this');
			}
			$this->handleDownloadPdf($label->id, $isDownload);
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
	public function handleDownloadProductAllLabelsPdf(int $productsId, bool $isDownload = TRUE, $overridePrice = NULL, $overridePriceAction = NULL, $overrideActionFrom = NULL, $overrideActionTo = NULL): void
	{
		$branchesId = (int)($this->session->filters[$this->getName()]['custom__branches'] ?? 0);
		$adminUserId = (int)($this->adminUser->id ?? 0);
		$product = $this->repository->findEntity($productsId);

		if (!$product) {
			$this->redirect('this');
		}

		$priceOverride = NULL;
		if ($overridePrice !== NULL || $overridePriceAction !== NULL || $overrideActionFrom !== NULL || $overrideActionTo !== NULL) {
			$parseDate = static function (?string $value): ?string {
				if (!$value) {
					return NULL;
				}
				$dt = \DateTime::createFromFormat('d.m.Y', $value);
				return $dt ? $dt->format('Y-m-d') : NULL;
			};
			$priceOverride = [
				'price' => $overridePrice !== NULL && $overridePrice !== '' ? (float)$overridePrice : NULL,
				'price_action' => $overridePriceAction !== NULL && $overridePriceAction !== '' ? (float)$overridePriceAction : NULL,
				'action_from' => $parseDate($overrideActionFrom),
				'action_to' => $parseDate($overrideActionTo),
			];
		}

		$labelIds = [];
		foreach ($this->global['productLabelTypes'] as $typeId => $term) {
			$label = $this->productLabelsRepo->findEntityBy([
				'products_id' => $productsId,
				'branches_id' => $branchesId,
				'admin_users_id' => $adminUserId,
				'type_id' => $typeId,
			]);

			if (!$label) {
				$productPrice = $product->getPrice($branchesId);
				$labelHeader = $this->productLabelHeadersRepo->findAll()->order('pos_order')->limit(1)->fetch();
				$row = $this->productLabelsRepo->insert([
					'product_label_headers_id' => $labelHeader ? $labelHeader->id : NULL,
					'products_id' => $product->id,
					'branches_id' => $branchesId,
					'admin_users_id' => $adminUserId,
					'count' => $isDownload ? 0 : 1,
					'datetime_printed' => NULL,
					'type_id' => $typeId,
					'sale_text' => '',
					'sale_percent' => $productPrice ? $productPrice->getActionPercents(TRUE) : 0,
					'is_more_species' => 0,
					'price_text' => \ProductLabelsEntity::PRICE_TYPE_CZK,
					'origin_countries_id' => $product->country_origin ?: NULL,
					'name' => $product->name,
					'note_1' => $product->label_note_1,
					'note_2' => $product->label_note_2,
					'note_3' => $product->label_note_3,
				]);
				$label = \ProductLabelsRepository::getEntity($row);
			}

			if ($label) {
				if ($isDownload) {
					if ((int)$label->count > 0) {
						$labelIds[] = $label->id;
					}
				} else {
					$labelIds[] = $label->id;
				}
			}
		}

		if (empty($labelIds)) {
			if ($isDownload) {
				$this->flashMessage($this->trans('admin.terms.noLabelsToPrint') ?? 'Sản phẩm này chưa được chọn số lượng tem để tải.', 'warning');
				$this->redirect('this');
			}
		}

		$file = $this->productLabelsService->generatePdf($branchesId, (bool)$isDownload, $labelIds, $priceOverride);
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
		$grouped = [
			\ProductLabelsEntity::TYPE_A4 => ['key' => 'a4', 'typeId' => 1, 'name' => 'A4', 'dims' => '210 × 297 mm', 'perPage' => 1, 'w' => '150px', 'h' => '212px', 'items' => [], 'count' => 0],
			\ProductLabelsEntity::TYPE_A5 => ['key' => 'a5', 'typeId' => 2, 'name' => 'A5', 'dims' => '210 × 148 mm', 'perPage' => 2, 'w' => '150px', 'h' => '106px', 'items' => [], 'count' => 0],
			\ProductLabelsEntity::TYPE_SIZE_1 => ['key' => 'v1', 'typeId' => 3, 'name' => 'Vel. 1', 'dims' => '150 × 44 mm', 'perPage' => 3, 'w' => '160px', 'h' => '48px', 'items' => [], 'count' => 0],
			\ProductLabelsEntity::TYPE_SIZE_2 => ['key' => 'v2', 'typeId' => 4, 'name' => 'Vel. 2', 'dims' => '133 × 52 mm', 'perPage' => 2, 'w' => '150px', 'h' => '58px', 'items' => [], 'count' => 0],
			\ProductLabelsEntity::TYPE_SIZE_3 => ['key' => 'v3', 'typeId' => 5, 'name' => 'Vel. 3', 'dims' => '182 × 56 mm', 'perPage' => 2, 'w' => '170px', 'h' => '56px', 'items' => [], 'count' => 0],
		];

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
	 * @return void
	 */
	public function handleClearAllPrintCounts()
	{
		$branchesId = (int)($this->session->filters[$this->getName()]['custom__branches'] ?? 0);
		$adminUserId = (int)($this->adminUser->id ?? 0);

		$this->productLabelsRepo->findAll()
			->where('branches_id', $branchesId)
			->where('admin_users_id', $adminUserId)
			->where('datetime_printed IS NULL')
			->update(['count' => 0]);

		if ($this->isAjax()) {
			$this->redrawControl('list');
			$this->redrawControl('printSummary');
			$this->redrawControl('headerPrintBtn');
			$this->redrawControl('printPreviewSnippet');
		} else {
			$this->redirect('this');
		}
	}


	/**
	 * @param int $labelId
	 * @return void
	 */
	public function handleIncrementQueue(int $labelId)
	{
		if ($label = $this->productLabelsRepo->findEntity($labelId)) {
			$label->count = (int)$label->count + 1;
			$label->datetime_printed = NULL;
			$this->productLabelsRepo->update($label);
		}

		if ($this->isAjax()) {
			$this->redrawControl('list');
			$this->redrawControl('printSummary');
			$this->redrawControl('headerPrintBtn');
			$this->redrawControl('printPreviewSnippet');
		} else {
			$this->redirect('this');
		}
	}


	/**
	 * @param int $labelId
	 * @return void
	 */
	public function handleDecrementQueue(int $labelId)
	{
		if ($label = $this->productLabelsRepo->findEntity($labelId)) {
			$label->count = max(0, (int)$label->count - 1);
			$label->datetime_printed = NULL;
			$this->productLabelsRepo->update($label);
		}

		if ($this->isAjax()) {
			$this->redrawControl('list');
			$this->redrawControl('printSummary');
			$this->redrawControl('headerPrintBtn');
			$this->redrawControl('printPreviewSnippet');
		} else {
			$this->redirect('this');
		}
	}


	/**
	 * @param int $labelId
	 * @return void
	 */
	public function handleRemoveFromQueue(int $labelId)
	{
		if ($label = $this->productLabelsRepo->findEntity($labelId)) {
			$label->count = 0;
			$this->productLabelsRepo->update($label);
		}

		if ($this->isAjax()) {
			$this->redrawControl('list');
			$this->redrawControl('printSummary');
			$this->redrawControl('headerPrintBtn');
			$this->redrawControl('printPreviewSnippet');
		} else {
			$this->redirect('this');
		}
	}


	/**
	 * @param int $typeId
	 * @param bool $isDownload TRUE = download attachment, FALSE = preview inline in browser
	 * @return void
	 */
	public function handleDownloadPdfGroup(int $typeId, bool $isDownload = TRUE)
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

		$file = $this->productLabelsService->generatePdf($branchesId, (bool)$isDownload, array_values($labelIds));
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
