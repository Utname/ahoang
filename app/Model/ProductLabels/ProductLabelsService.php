<?php

use Nette\Database\Explorer;
use \Nette\Localization\Translator;
use Dompdf\Dompdf;

class ProductLabelsService
{
	use \Nette\SmartObject;

	/**
	 * @param ProductLabelsRepository $productLabelsRepo
	 * @param Translator $translator
	 * @param PrintTemplatesService $printTemplatesService
	 */
	public function __construct(protected ProductLabelsRepository $productLabelsRepo, protected Translator $translator, protected PrintTemplatesService $printTemplatesService)
	{
	}


	/**
	 * @param int $branchesId
	 * @param bool $printed
	 * @param array $ids
	 * @param array<int, array{price?: ?float, price_action?: ?float, action_from?: ?string, action_to?: ?string}>|array{price?: ?float, price_action?: ?float, action_from?: ?string, action_to?: ?string}|null $priceOverride
	 *        Renders with these price/date values instead of the ones currently saved in
	 *        product_prices, without writing anything to the database — lets the admin
	 *        preview/print labels reflecting price or date fields they just edited inline
	 *        in the product list but haven't saved yet. Either a map keyed by product ID
	 *        (each product gets its own values), or one override applied to every label.
	 * @param int[] $newA4ProductIds Product IDs whose A4 label should be rendered with the new HTML template.
	 *        A4 labels of every other product are printed with the legacy TCPDF layout.
	 * @return string|TCPDF|Dompdf|NULL
	 */
	public function generatePdf(int $branchesId, bool $printed = TRUE, array $ids = [], ?array $priceOverride = NULL, array $newA4ProductIds = []): string|TCPDF|Dompdf|null
	{
		if (!isset($GLOBALS['adminUser']) || !$GLOBALS['adminUser']) {
			return NULL;
		}

		$items = $this->productLabelsRepo->findAll()
			->where('admin_users_id', $GLOBALS['adminUser']->id)
			->where('product_labels.branches_id', $branchesId)
			->order('type_id, id');
		if (count($ids)) {
			$items->where('id', $ids);
		}
		else {
			$items->where('products:product_prices.branches_id = ?', $branchesId);
			$items->where('datetime_printed IS NULL');
			$items->where('count > ?', 0);
			$items->where('type_id > ?', 0);
		}

		if (!$items->count()) {
			return NULL;
		}

		$items = ProductLabelsRepository::getEntities($items);
		$itemsBuffer = [];

		foreach ($items as $item) {
			switch ($item->type_id) {
				case ProductLabelsEntity::TYPE_A4:
					$itemsBuffer[$item->type_id]['pageParams'] = ['P', 'A4'];
					for ($i = 1; $i <= max(1, $item->count); $i++) {
						$itemsBuffer[$item->type_id]['items'][][0] = $item;
					}
					break;
				case ProductLabelsEntity::TYPE_A5:
					$itemsBuffer[$item->type_id]['pageParams'] = ['P', 'A4'];
					for ($i = 1; $i <= max(1, $item->count); $i++) {
						if (!isset($itemsBuffer[$item->type_id]['items']) || count($itemsBuffer[$item->type_id]['items'][array_key_last($itemsBuffer[$item->type_id]['items'])]) >= 2) {
							$itemsBuffer[$item->type_id]['items'][][] = $item;
						}
						else {
							$itemsBuffer[$item->type_id]['items'][array_key_last($itemsBuffer[$item->type_id]['items'])][] = $item;
						}
					}

					break;
				case ProductLabelsEntity::TYPE_SIZE_1:
					$itemsBuffer[$item->type_id]['pageParams'] = ['P', 'A4'];
					for ($i = 1; $i <= max(1, $item->count); $i++) {
						if (!isset($itemsBuffer[$item->type_id]['items']) || count($itemsBuffer[$item->type_id]['items'][array_key_last($itemsBuffer[$item->type_id]['items'])]) >= 7) {
							$itemsBuffer[$item->type_id]['items'][][] = $item;
						}
						else {
							$itemsBuffer[$item->type_id]['items'][array_key_last($itemsBuffer[$item->type_id]['items'])][] = $item;
						}
					}

					break;
				case ProductLabelsEntity::TYPE_SIZE_2:
				case ProductLabelsEntity::TYPE_SIZE_2_AKCE:
					$itemsBuffer[$item->type_id]['pageParams'] = ['P', 'A4'];
					for ($i = 1; $i <= max(1, $item->count); $i++) {
						if (!isset($itemsBuffer[$item->type_id]['items']) || count($itemsBuffer[$item->type_id]['items'][array_key_last($itemsBuffer[$item->type_id]['items'])]) >= 7) {
							$itemsBuffer[$item->type_id]['items'][][] = $item;
						}
						else {
							$itemsBuffer[$item->type_id]['items'][array_key_last($itemsBuffer[$item->type_id]['items'])][] = $item;
						}
					}
					break;
				case ProductLabelsEntity::TYPE_SIZE_3:
					$itemsBuffer[$item->type_id]['pageParams'] = ['P', 'A4'];
					for ($i = 1; $i <= max(1, $item->count); $i++) {
						if (!isset($itemsBuffer[$item->type_id]['items']) || count($itemsBuffer[$item->type_id]['items'][array_key_last($itemsBuffer[$item->type_id]['items'])]) >= 4) {
							$itemsBuffer[$item->type_id]['items'][][] = $item;
						}
						else {
							$itemsBuffer[$item->type_id]['items'][array_key_last($itemsBuffer[$item->type_id]['items'])][] = $item;
						}
					}
					break;
				case ProductLabelsEntity::TYPE_SIZE_2B:
					$itemsBuffer[$item->type_id]['pageParams'] = ['P', 'A4'];
					for ($i = 1; $i <= max(1, $item->count); $i++) {
						if (!isset($itemsBuffer[$item->type_id]['items']) || count($itemsBuffer[$item->type_id]['items'][array_key_last($itemsBuffer[$item->type_id]['items'])]) >= 5) {
							$itemsBuffer[$item->type_id]['items'][][] = $item;
						}
						else {
							$itemsBuffer[$item->type_id]['items'][array_key_last($itemsBuffer[$item->type_id]['items'])][] = $item;
						}
					}
					break;
				case ProductLabelsEntity::TYPE_PECIVO:
					$itemsBuffer[$item->type_id]['pageParams'] = ['P', 'A4'];
					for ($i = 1; $i <= max(1, $item->count); $i++) {
						if (!isset($itemsBuffer[$item->type_id]['items']) || count($itemsBuffer[$item->type_id]['items'][array_key_last($itemsBuffer[$item->type_id]['items'])]) >= 6) {
							$itemsBuffer[$item->type_id]['items'][][] = $item;
						}
						else {
							$itemsBuffer[$item->type_id]['items'][array_key_last($itemsBuffer[$item->type_id]['items'])][] = $item;
						}
					}
					break;
			}
		}

		if (!count($itemsBuffer)) {
			return NULL;
		}

		$printedIds = [];

		// Non-A4 label types (A5, Vel.1, Vel.3, etc.) use new HTML/Dompdf templates by default when available;
		// A4 labels only use the new template when specifically ticked for the product ($newA4ProductIds).
		$templateJobs = [];
		// TYPE_SIZE_2_AKCE is not offered in the admin form (TYPES_ARR) but labels
		// already queued with it must still reach its template, not TCPDF.
		$templateTypeIds = array_unique([...array_keys(ProductLabelsEntity::TYPES_ARR), ProductLabelsEntity::TYPE_SIZE_2_AKCE]);
		foreach ($templateTypeIds as $templateTypeId) {
			if (!isset($itemsBuffer[$templateTypeId]['items'])) {
				continue;
			}

			$legacyItems = [];
			foreach ($itemsBuffer[$templateTypeId]['items'] as $labelItems) {
				$allUseTemplate = TRUE;
				$pageJobs = [];
				foreach ($labelItems as $label) {
					$product = $label?->getProduct();
					$isA4 = ($templateTypeId === ProductLabelsEntity::TYPE_A4);
					$useTemplate = $label && $product && (
						(!$isA4) || in_array((int)$product->id, $newA4ProductIds, TRUE)
					);
					$template = $useTemplate
						? $this->printTemplatesService->findTemplateForProductLabel($label, $product, $branchesId)
						: NULL;

					if ($template) {
						$pageJobs[] = ['template' => $template, 'label' => $label, 'priceOverride' => $this->overrideFor($priceOverride, (int)$product->id)];
					}
					else {
						$allUseTemplate = FALSE;
					}
				}

				if ($allUseTemplate && count($pageJobs) === count($labelItems)) {
					foreach ($pageJobs as $job) {
						$templateJobs[] = $job;
					}
				}
				else {
					$legacyItems[] = $labelItems;
				}
			}

			if ($legacyItems) {
				$itemsBuffer[$templateTypeId]['items'] = $legacyItems;
			}
			else {
				unset($itemsBuffer[$templateTypeId]);
			}
		}

		$pdfFiles = [];
		foreach ($itemsBuffer as $labelTypeId => $itemBuffer) {
			$pdf = new TCPDF();
			$pdf->setPrintHeader(FALSE);
			$pdf->setPrintFooter(FALSE);
			$pdf->setHeaderMargin(0);
			$pdf->setFooterMargin(0);

			$fontname1 = TCPDF_FONTS::addTTFfont(WWW_DIR.'/pdf/sriracha.ttf', 'TrueTypeUnicode');
			$fontname2 = TCPDF_FONTS::addTTFfont(WWW_DIR.'/pdf/inter.ttf', 'TrueTypeUnicode');
			$fontname3 = TCPDF_FONTS::addTTFfont(WWW_DIR.'/pdf/srirachablack.ttf', 'TrueTypeUnicode');
			$pdf->SetFont($fontname1, '', 14, '', TRUE);
			$pdf->SetFont($fontname2, '', 14, '', TRUE);
			$pdf->SetFont($fontname3, '', 14, '', TRUE);

			$pdfFiles[$labelTypeId] = $pdf;
		}

		if ($templateJobs) {
			$pdfFiles['html-templates'] = $this->printTemplatesService->renderProductLabelsPdf($templateJobs, $branchesId);
			foreach ($templateJobs as $job) {
				$printedIds[] = $job['label']->id;
			}
		}

		foreach ($itemsBuffer as $labelTypeId => $itemBuffer) {
			if (!isset($pdfFiles[$labelTypeId])) {
				continue;
			}

			foreach ($itemBuffer['items'] as $labelTypeItems) {
				$pdfFiles[$labelTypeId]->AddPage(...$itemBuffer['pageParams']);

				switch ($labelTypeId) {
					case ProductLabelsEntity::TYPE_A4:
						$pdfY = 82;

						$product = $labelTypeItems[0]->getProduct();
						$productPrice = $this->priceFor($product, $branchesId, $priceOverride);
						$basePriceUnit = $product->getUnitValue($productPrice->price, $labelTypeItems[0]->price_text == ProductLabelsEntity::PRICE_TYPE_CZK_100g ? $labelTypeItems[0]->default_units_id : NULL, $labelTypeItems[0]->price_text == ProductLabelsEntity::PRICE_TYPE_CZK_100g ? $labelTypeItems[0]->default_units_weight : NULL);
						$actionPriceUnit = $product->getUnitValue($productPrice->price_action, $labelTypeItems[0]->price_text == ProductLabelsEntity::PRICE_TYPE_CZK_100g ? $labelTypeItems[0]->default_units_id : NULL, $labelTypeItems[0]->price_text == ProductLabelsEntity::PRICE_TYPE_CZK_100g ? $labelTypeItems[0]->default_units_weight : NULL);
						$basePricePars = explode('.', (string)number_format($labelTypeItems[0]->price_text == ProductLabelsEntity::PRICE_TYPE_CZK_100g && $basePriceUnit && $actionPriceUnit ? $basePriceUnit->price : ($productPrice->price ?: 0.0), 2, '.', ''));
						$actionPricePars = explode('.', (string)number_format($labelTypeItems[0]->price_text == ProductLabelsEntity::PRICE_TYPE_CZK_100g && $basePriceUnit && $actionPriceUnit ? $actionPriceUnit->price : ($productPrice->price_action ?: 0.0), 2, '.', ''));
						$priceAddX = $labelTypeItems[0]->price_text == ProductLabelsEntity::PRICE_TYPE_CZK_100g && $basePriceUnit && $actionPriceUnit ? 0 : 13;

						$pdfFiles[$labelTypeId]->Image(WWW_DIR.'/pdf/logo.jpg', 90, 5, 30, 30);
						$pdfFiles[$labelTypeId]->Rect(0, 40, 210, 35, 'F', '', [0, 0, 0]);
						$this->cell($pdfFiles[$labelTypeId], 0, 45, 60, 'white', 210, $labelTypeItems[0]->getHeader()?->name ?: '', 'C');

						$this->cell($pdfFiles[$labelTypeId], 0, $pdfY, 22, 'default', 210, $labelTypeItems[0]->name, 'C', 'inter');

						if ($labelTypeItems[0]->is_more_species) {
							$pdfY += 14;
							$this->cell($pdfFiles[$labelTypeId], 0, $pdfY, 18, 'default', 210, $this->translator->translate('admin.terms.moreSpecies'), 'C', 'sriracha');
						}

						if ($labelTypeItems[0]->note_1) {
							$pdfY += 14;
							$this->cell($pdfFiles[$labelTypeId], 0, $pdfY, 22, 'default', 210, $labelTypeItems[0]->note_1, 'C');
						}

						if ($labelTypeItems[0]->getCountry() || $labelTypeItems[0]->sale_text) {
							$pdfY += 14;
							if ($labelTypeItems[0]->getCountry()) {
								if ($countryImage = $labelTypeItems[0]->getCountry()->image) {
									$pdfFiles[$labelTypeId]->Image(FS_DIR.'/images/'.ImagesService::getImageDir($countryImage).'/'.$countryImage, 43, $pdfY + 0.5, 5);
								}
								$this->cell($pdfFiles[$labelTypeId], 5, $pdfY, 16, 'default', 140, $this->translator->translate('admin.terms.countryOrigin').': '.($countryImage ? '       ' : '').$labelTypeItems[0]->getCountry()->name, 'L', 'sriracha');
							}
							if ($labelTypeItems[0]->sale_text) {
								$this->cell($pdfFiles[$labelTypeId], 130, $pdfY + 1.5, 10, 'default', 75, $labelTypeItems[0]->sale_text, 'R', 'inter');
							}
						}

						$pdfY += 13;
						if ($product->code_ean) {
							$this->cell($pdfFiles[$labelTypeId], 5, $pdfY + 1, 12, 'default', 120, $this->translator->translate('admin.terms.ean').': '.$product->code_ean, 'L', 'inter');
						}
						if (floatval($basePricePars[0].'.'.$basePricePars[1])) {
							$this->cell($pdfFiles[$labelTypeId], 90, $pdfY, 16, 'default', 210, $this->translator->translate('admin.terms.basicPrice30days').':', 'L', 'inter');
						}

						$pdfY += 10;
						if ($labelTypeItems[0]->sale_percent) {
							$pdfFiles[$labelTypeId]->Image(WWW_DIR.'/pdf/brush_1.png', 0, $pdfY, 90, 35);
							$this->cell($pdfFiles[$labelTypeId], 0, $pdfY + 2, 80, 'white', 90, '-'.$labelTypeItems[0]->sale_percent.'%', 'C');
						}

						if (floatval($basePricePars[0].'.'.$basePricePars[1])) {
							$this->cell($pdfFiles[$labelTypeId], 92, $pdfY + 2, 85, 'default', 60, $basePricePars[0], 'R');
							$this->cell($pdfFiles[$labelTypeId], 150, $pdfY - 2, 45, 'default', 70, $basePricePars[1], 'L');

							$pdfFiles[$labelTypeId]->Line(95, $pdfY + 2, 170, $pdfY + 33, ['color' => [0, 0, 0], 'width' => 0.5]);
							$pdfFiles[$labelTypeId]->Line(95, $pdfY + 33, 170, $pdfY + 2, ['color' => [0, 0, 0], 'width' => 0.5]);
						}

						$pdfY += 30;
						$this->cell($pdfFiles[$labelTypeId], 60 + $priceAddX, $pdfY + 2, 180, 'default', 60, $actionPricePars[0], 'R');
						$this->cell($pdfFiles[$labelTypeId], 123 + $priceAddX, $pdfY + 8, 50, 'default', 70, $actionPricePars[1], 'L');
						$this->cell($pdfFiles[$labelTypeId], 123 + $priceAddX, $pdfY + 27, 35, 'default', 70, $productPrice->getCurrency()->symbol, 'L');
						if ($labelTypeItems[0]->price_text == ProductLabelsEntity::PRICE_TYPE_CZK_100g && $basePriceUnit && $actionPriceUnit) {
							$this->cell($pdfFiles[$labelTypeId], 139 + $priceAddX, $pdfY + 31, 22, 'default', 50, '/ '.$basePriceUnit->unitWeight.$basePriceUnit->unitShort, 'L');
						}

						$pdfY += 50;
						if ($labelTypeItems[0]->note_2) {
							$pdfY += 14;
							$this->cell($pdfFiles[$labelTypeId], 0, $pdfY, 22, 'default', 210, $labelTypeItems[0]->note_2, 'C');
						}

						if ($labelTypeItems[0]->note_3) {
							$pdfY += 12;
							$this->cell($pdfFiles[$labelTypeId], 0, $pdfY, 22, 'default', 210, $labelTypeItems[0]->note_3, 'C');
						}

						if ($labelTypeItems[0]->price_text != ProductLabelsEntity::PRICE_TYPE_CZK_100g && $actionPriceUnit) {
							$pdfY += 12;
							$this->cell($pdfFiles[$labelTypeId], 0, $pdfY, 18, 'default', 210, 'cena za '.$actionPriceUnit->unitWeight.$actionPriceUnit->unitShort.' = '.number_format($actionPriceUnit->price, 2, '.', '').' '.$productPrice->getCurrency()->symbol, 'C');
						}

						$printedIds[] = $labelTypeItems[0]->id;
						break;
					case ProductLabelsEntity::TYPE_A5:

						foreach ($labelTypeItems as $labelTypeItemIndex => $labelTypeItem) {
							$pdfY = 28 + ($labelTypeItemIndex * 140);
							$product = $labelTypeItem->getProduct();
							$productPrice = $this->priceFor($product, $branchesId, $priceOverride);
							$basePriceUnit = $product->getUnitValue($productPrice->price, $labelTypeItem->price_text == ProductLabelsEntity::PRICE_TYPE_CZK_100g ? $labelTypeItem->default_units_id : NULL, $labelTypeItem->price_text == ProductLabelsEntity::PRICE_TYPE_CZK_100g ? $labelTypeItem->default_units_weight : NULL);
							$actionPriceUnit = $product->getUnitValue($productPrice->price_action, $labelTypeItem->price_text == ProductLabelsEntity::PRICE_TYPE_CZK_100g ? $labelTypeItem->default_units_id : NULL, $labelTypeItem->price_text == ProductLabelsEntity::PRICE_TYPE_CZK_100g ? $labelTypeItem->default_units_weight : NULL);
							$basePricePars = explode('.', (string)number_format($labelTypeItem->price_text == ProductLabelsEntity::PRICE_TYPE_CZK_100g && $basePriceUnit && $actionPriceUnit ? $basePriceUnit->price : ($productPrice->price ?: 0.0), 2, '.', ''));
							$actionPricePars = explode('.', (string)number_format($labelTypeItem->price_text == ProductLabelsEntity::PRICE_TYPE_CZK_100g && $basePriceUnit && $actionPriceUnit ? $actionPriceUnit->price : ($productPrice->price_action ?: 0.0), 2, '.', ''));
							$priceAddX = $labelTypeItem->price_text == ProductLabelsEntity::PRICE_TYPE_CZK_100g && $basePriceUnit && $actionPriceUnit ? 0 : 11;

							$pdfFiles[$labelTypeId]->Rect(0, $pdfY - 28, 210, 26, 'F', '', [0, 0, 0]);
							$pdfFiles[$labelTypeId]->Image(WWW_DIR.'/pdf/logo.jpg', 1, $pdfY - 27, 24, 24);
							$pdfFiles[$labelTypeId]->Image(WWW_DIR.'/pdf/logo.jpg', 185, $pdfY - 27, 24, 24);
							$this->cell($pdfFiles[$labelTypeId], 0, $pdfY - 23, 45, 'white', 210, $labelTypeItem->getHeader()?->name ?: '', 'C');

							$this->cell($pdfFiles[$labelTypeId], 0, $pdfY - 2, 20, 'default', 210, $labelTypeItem->name, 'C', 'inter');

							if ($labelTypeItem->note_1) {
								$pdfY += 8;
								$this->cell($pdfFiles[$labelTypeId], 0, $pdfY - 0.5, 18, 'default', 210, $labelTypeItem->note_1, 'C');
							}

							if ($labelTypeItem->getCountry() || $labelTypeItem->note_2 || $labelTypeItem->is_more_species) {
								$pdfY += 10;
								if ($labelTypeItem->getCountry()) {
									if ($countryImage = $labelTypeItem->getCountry()->image) {
										$pdfFiles[$labelTypeId]->Image(FS_DIR.'/images/'.ImagesService::getImageDir($countryImage).'/'.$countryImage, 30, $pdfY - 0.5, 5);
									}
									$this->cell($pdfFiles[$labelTypeId], 5, $pdfY, 10, 'default', 85, $this->translator->translate('admin.terms.countryOrigin').': '.($countryImage ? '          ' : '').$labelTypeItem->getCountry()->name, 'L', 'sriracha');
								}
								if ($labelTypeItem->is_more_species) {
									$this->cell($pdfFiles[$labelTypeId], 90, $pdfY - 3, 14, 'default', 30, $this->translator->translate('admin.terms.moreSpecies'), 'C', 'sriracha');
								}
								if ($labelTypeItem->note_2) {
									$this->cell($pdfFiles[$labelTypeId], 130, $pdfY, 10, 'default', 75, $labelTypeItem->note_2, 'R');
								}
							}

							$pdfY += 4;
							if ($product->code_ean) {
								$this->cell($pdfFiles[$labelTypeId], 5, $pdfY + 3, 8, 'default', 120, $this->translator->translate('admin.terms.ean').': '.$product->code_ean, 'L', 'inter');
							}
							if (floatval($basePricePars[0].'.'.$basePricePars[1])) {
								$this->cell($pdfFiles[$labelTypeId], 110, $pdfY, 14, 'default', 210, $this->translator->translate('admin.terms.basicPrice30days').':', 'L', fontName: 'inter');
							}

							$pdfY += 8;
							if ($labelTypeItem->sale_percent) {
								$pdfFiles[$labelTypeId]->Image(WWW_DIR.'/pdf/brush_1.png', 0, $pdfY, 90, 22);
								$this->cell($pdfFiles[$labelTypeId], 0, $pdfY + 1, 55, 'white', 90, '-'.$labelTypeItem->sale_percent.'%', 'C');
							}

							if (floatval($basePricePars[0].'.'.$basePricePars[1])) {
								$this->cell($pdfFiles[$labelTypeId], 88, $pdfY, 60, 'default', 60, $basePricePars[0], 'R');
								$this->cell($pdfFiles[$labelTypeId], 148, $pdfY, 30, 'default', 70, $basePricePars[1], 'L');

								$pdfFiles[$labelTypeId]->Line(108, $pdfY + 2, 165, $pdfY + 19, ['color' => [0, 0, 0], 'width' => 0.5]);
								$pdfFiles[$labelTypeId]->Line(108, $pdfY + 19, 165, $pdfY + 2, ['color' => [0, 0, 0], 'width' => 0.5]);
							}

							$pdfY += 16;
							$this->cell($pdfFiles[$labelTypeId], 65 + $priceAddX, $pdfY, 130, 'default', 60, $actionPricePars[0], 'R');
							$this->cell($pdfFiles[$labelTypeId], 130 + $priceAddX, $pdfY + 4, 40, 'default', 70, $actionPricePars[1], 'L');
							$this->cell($pdfFiles[$labelTypeId], 130 + $priceAddX, $pdfY + 18, 30, 'default', 70, $productPrice->getCurrency()->symbol, 'L');
							if ($labelTypeItem->price_text == ProductLabelsEntity::PRICE_TYPE_CZK_100g && $basePriceUnit && $actionPriceUnit) {
								$this->cell($pdfFiles[$labelTypeId], 145 + $priceAddX, $pdfY + 20, 22, 'default', 50, '/ '.$basePriceUnit->unitWeight.$basePriceUnit->unitShort, 'L');
							}

							$pdfY += 44;
							if ($labelTypeItem->note_3) {
								$this->cell($pdfFiles[$labelTypeId], 0, $pdfY, 18, 'default', 210, $labelTypeItem->note_3, 'C');
							}

							if ($labelTypeItem->price_text != ProductLabelsEntity::PRICE_TYPE_CZK_100g && $actionPriceUnit) {
								$pdfY += 10;
								$this->cell($pdfFiles[$labelTypeId], 0, $pdfY, 18, 'default', 210, 'cena za '.$actionPriceUnit->unitWeight.$actionPriceUnit->unitShort.' = '.number_format($actionPriceUnit->price, 2, '.', '').' '.$productPrice->getCurrency()->symbol, 'C');
							}

							$printedIds[] = $labelTypeItem->id;
						}

						break;
					case ProductLabelsEntity::TYPE_SIZE_1:
						$pdfY = 1;
						foreach ($labelTypeItems as $labelTypeItem) {
							$product = $labelTypeItem->getProduct();
							$productPrice = $this->priceFor($product, $branchesId, $priceOverride);
							$basePriceUnit = $product->getUnitValue($productPrice->price, $labelTypeItem->price_text == ProductLabelsEntity::PRICE_TYPE_CZK_100g ? $labelTypeItem->default_units_id : NULL, $labelTypeItem->price_text == ProductLabelsEntity::PRICE_TYPE_CZK_100g ? $labelTypeItem->default_units_weight : NULL);
							$actionPriceUnit = $product->getUnitValue($productPrice->price_action, $labelTypeItem->price_text == ProductLabelsEntity::PRICE_TYPE_CZK_100g ? $labelTypeItem->default_units_id : NULL, $labelTypeItem->price_text == ProductLabelsEntity::PRICE_TYPE_CZK_100g ? $labelTypeItem->default_units_weight : NULL);
							$basePricePars = explode('.', (string)number_format($labelTypeItem->price_text == ProductLabelsEntity::PRICE_TYPE_CZK_100g && $basePriceUnit && $actionPriceUnit ? $basePriceUnit->price : ($productPrice->price ?: 0.0), 2, '.', ''));
							$actionPricePars = explode('.', (string)number_format($labelTypeItem->price_text == ProductLabelsEntity::PRICE_TYPE_CZK_100g && $basePriceUnit && $actionPriceUnit ? $actionPriceUnit->price : ($productPrice->price_action ?: 0.0), 2, '.', ''));
							$priceAddX = $labelTypeItem->price_text == ProductLabelsEntity::PRICE_TYPE_CZK_100g && $basePriceUnit && $actionPriceUnit ? 0 : 7;

							$pdfFiles[$labelTypeId]->Line(1, $pdfY, 209, $pdfY, ['color' => [0, 0, 0]]);
							$pdfFiles[$labelTypeId]->Line(1, $pdfY + 30, 60, $pdfY + 30, ['color' => [0, 0, 0]]);
							$pdfFiles[$labelTypeId]->Line(1, $pdfY + 39, 209, $pdfY + 39, ['color' => [0, 0, 0]]);

							$pdfFiles[$labelTypeId]->Line(1, $pdfY, 1, $pdfY + 39, ['color' => [0, 0, 0]]);
							$pdfFiles[$labelTypeId]->Line(60, $pdfY, 60, $pdfY + 39, ['color' => [0, 0, 0]]);
							$pdfFiles[$labelTypeId]->Line(209, $pdfY, 209, $pdfY + 39, ['color' => [0, 0, 0]]);

							$this->multiCell($pdfFiles[$labelTypeId], 5, $pdfY, 38, 'default', 50, 30, $labelTypeItem->getHeader()?->name ?: '', 'C', valign: 'M');
							if ($labelTypeItem->sale_text) {
								$this->multiCell($pdfFiles[$labelTypeId], 1, $pdfY + 30, 8, 'default', 60, 9, $labelTypeItem->sale_text, 'C', 'M', 'inter');
							}

							$this->multiCell($pdfFiles[$labelTypeId], 62, $pdfY - 1, 11, 'default', 150, 8, $labelTypeItem->name, 'L', 'M', 'inter');
							if ($labelTypeItem->is_more_species) {
								$this->multiCell($pdfFiles[$labelTypeId], 62, $pdfY + 5, 10, 'default', 60, 7, $this->translator->translate('admin.terms.moreSpecies'), 'L',  'M', 'sriracha');
							}

							if (floatval($basePricePars[0].'.'.$basePricePars[1])) {
								$this->multiCell($pdfFiles[$labelTypeId], 115, $pdfY + 5, 10, 'default', 60, 7, $this->translator->translate('admin.terms.basicPrice30days').':', 'L', valign: 'M', fontName: 'inter');
							}

							if ($labelTypeItem->sale_percent) {
								$pdfFiles[$labelTypeId]->Image(WWW_DIR.'/pdf/brush_2.png', 60, $pdfY + 12, 45, 18);
								$this->cell($pdfFiles[$labelTypeId], 60, $pdfY + 14, 40, 'white', 45, '-'.$labelTypeItem->sale_percent.'%', 'C');
							}

							if (floatval($basePricePars[0].'.'.$basePricePars[1])) {
								$this->cell($pdfFiles[$labelTypeId], 105, $pdfY + 15, 42, 'default', 32, $basePricePars[0], 'R');
								$this->cell($pdfFiles[$labelTypeId], 137, $pdfY + 13, 24, 'default', 10, $basePricePars[1], 'L');

								$pdfFiles[$labelTypeId]->Line(107, $pdfY + 12, 148, $pdfY + 30, ['color' => [0, 0, 0], 'width' => 0.5]);
								$pdfFiles[$labelTypeId]->Line(107, $pdfY + 30, 148, $pdfY + 12, ['color' => [0, 0, 0], 'width' => 0.5]);
							}

							$this->cell($pdfFiles[$labelTypeId], 140 + ($priceAddX * 2), $pdfY + 10, 70, 'default', 40, $actionPricePars[0], 'R');
							$this->cell($pdfFiles[$labelTypeId], 180 + ($priceAddX * 2), $pdfY + 11, 26, 'default', 30, $actionPricePars[1], 'L');
							$this->cell($pdfFiles[$labelTypeId], 180 + ($priceAddX * 2), $pdfY + 22, 18, 'default', 30, $productPrice->getCurrency()->symbol, 'L');
							if ($labelTypeItem->price_text == ProductLabelsEntity::PRICE_TYPE_CZK_100g && $basePriceUnit && $actionPriceUnit) {
								$this->cell($pdfFiles[$labelTypeId], 189 + ($priceAddX * 2), $pdfY + 19.5, 14, 'default', 50, '/ '.$basePriceUnit->unitWeight.$basePriceUnit->unitShort, 'L');
							}

							if ($labelTypeItem->getCountry()) {
								if ($countryImage = $labelTypeItem->getCountry()->image) {
									$pdfFiles[$labelTypeId]->Image(FS_DIR.'/images/'.ImagesService::getImageDir($countryImage).'/'.$countryImage, 82, $pdfY + 31, 4);
								}
								$this->cell($pdfFiles[$labelTypeId], 62, $pdfY + 30.5, 8, 'default', 120, $this->translator->translate('admin.terms.countryOrigin').': '.($countryImage ? '          ' : '').$labelTypeItem->getCountry()->name, 'L', 'sriracha');
							}

							if ($product->code_ean) {
								$this->cell($pdfFiles[$labelTypeId], 62, $pdfY + 35.5, 8, 'default', 120, $this->translator->translate('admin.terms.ean').': '.$product->code_ean, 'L', 'inter');
							}

							if ($labelTypeItem->note_2) {
								$this->cell($pdfFiles[$labelTypeId], 100, $pdfY + 35.5, 8, 'default', 70, $labelTypeItem->note_2, 'C');
							}

							if ($labelTypeItem->price_text != ProductLabelsEntity::PRICE_TYPE_CZK_100g && $actionPriceUnit) {
								$this->cell($pdfFiles[$labelTypeId], 172, $pdfY + 35.5, 8, 'default', 36, 'cena za '.$actionPriceUnit->unitWeight.$actionPriceUnit->unitShort.' = '.number_format($actionPriceUnit->price, 2, '.', '').' '.$productPrice->getCurrency()->symbol, 'R');
							}

							$printedIds[] = $labelTypeItem->id;
							$pdfY += 39;
						}

						break;
					case ProductLabelsEntity::TYPE_SIZE_2:
						$pdfY = 1;
						foreach ($labelTypeItems as $labelTypeItem) {
							$product = $labelTypeItem->getProduct();
							$productPrice = $this->priceFor($product, $branchesId, $priceOverride);
							$basePriceUnit = $product->getUnitValue($productPrice->price, $labelTypeItem->price_text == ProductLabelsEntity::PRICE_TYPE_CZK_100g ? $labelTypeItem->default_units_id : NULL, $labelTypeItem->price_text == ProductLabelsEntity::PRICE_TYPE_CZK_100g ? $labelTypeItem->default_units_weight : NULL);
							$actionPriceUnit = $product->getUnitValue($productPrice->price_action, $labelTypeItem->price_text == ProductLabelsEntity::PRICE_TYPE_CZK_100g ? $labelTypeItem->default_units_id : NULL, $labelTypeItem->price_text == ProductLabelsEntity::PRICE_TYPE_CZK_100g ? $labelTypeItem->default_units_weight : NULL);
							$basePricePars = explode('.', (string)number_format($labelTypeItem->price_text == ProductLabelsEntity::PRICE_TYPE_CZK_100g && $basePriceUnit && $actionPriceUnit ? $basePriceUnit->price : ($productPrice->price ?: 0.0), 2, '.', ''));
							$actionPricePars = explode('.', (string)number_format($labelTypeItem->price_text == ProductLabelsEntity::PRICE_TYPE_CZK_100g && $basePriceUnit && $actionPriceUnit ? $actionPriceUnit->price : ($productPrice->price_action ?: 0.0), 2, '.', ''));
							$priceAddX = $labelTypeItem->price_text == ProductLabelsEntity::PRICE_TYPE_CZK_100g && $basePriceUnit && $actionPriceUnit ? 0 : 5;

							$pdfFiles[$labelTypeId]->Line(1, $pdfY, 153, $pdfY, ['color' => [0, 0, 0]]);
							$pdfFiles[$labelTypeId]->Line(1, $pdfY + 30, 40, $pdfY + 30, ['color' => [0, 0, 0]]);
							$pdfFiles[$labelTypeId]->Line(1, $pdfY + 39, 153, $pdfY + 39, ['color' => [0, 0, 0]]);

							$pdfFiles[$labelTypeId]->Line(1, $pdfY, 1, $pdfY + 39, ['color' => [0, 0, 0]]);
							$pdfFiles[$labelTypeId]->Line(40, $pdfY, 40, $pdfY + 39, ['color' => [0, 0, 0]]);
							$pdfFiles[$labelTypeId]->Line(153, $pdfY, 153, $pdfY + 39, ['color' => [0, 0, 0]]);

							$this->multiCell($pdfFiles[$labelTypeId], 1, $pdfY, 26, 'default', 40, $labelTypeItem->sale_text ? 30 : 39, $labelTypeItem->getHeader()?->name ?: '', 'C', valign: 'M');
							if ($labelTypeItem->sale_text) {
								$this->multiCell($pdfFiles[$labelTypeId], 1, $pdfY + 30, 8, 'default', 40, 9, $labelTypeItem->sale_text, 'C', 'M', 'inter');
							}

							$this->multiCell($pdfFiles[$labelTypeId], 42, $pdfY - 1, 11, 'default', 106, 8, $labelTypeItem->name, 'L', 'M', 'inter');
							if ($labelTypeItem->is_more_species) {
								$this->multiCell($pdfFiles[$labelTypeId], 42, $pdfY + 5, 9, 'default', 30, 5, $this->translator->translate('admin.terms.moreSpecies'), 'L', valign: 'M');
							}
							if (floatval($basePricePars[0].'.'.$basePricePars[1])) {
								$this->multiCell($pdfFiles[$labelTypeId], 79, $pdfY + 4, 9, 'default', 45, 7, $this->translator->translate('admin.terms.basicPrice30days').':', 'L', valign: 'M', fontName: 'inter');
							}

							if ($labelTypeItem->sale_percent) {
								$pdfFiles[$labelTypeId]->Image(WWW_DIR.'/pdf/brush_2.png', 40, $pdfY + 11, 35, 19);
								$this->cell($pdfFiles[$labelTypeId], 40, $pdfY + 15, 34, 'white', 35, '-'.$labelTypeItem->sale_percent.'%', 'C');
							}

							if (floatval($basePricePars[0].'.'.$basePricePars[1])) {
								$this->cell($pdfFiles[$labelTypeId], 74, $pdfY + 15, 38, 'default', 26, $basePricePars[0], 'R');
								$this->cell($pdfFiles[$labelTypeId], 99, $pdfY + 13, 14, 'default', 17, $basePricePars[1], 'L');

								$pdfFiles[$labelTypeId]->Line(76, $pdfY + 12, 106, $pdfY + 30, ['color' => [0, 0, 0], 'width' => 0.4]);
								$pdfFiles[$labelTypeId]->Line(76, $pdfY + 30, 106, $pdfY + 12, ['color' => [0, 0, 0], 'width' => 0.4]);
							}

							$this->cell($pdfFiles[$labelTypeId], 106 + ($priceAddX * 2), $pdfY + 11, 60, 'default', 26, $actionPricePars[0], 'R');
							$this->cell($pdfFiles[$labelTypeId], 132 + ($priceAddX * 2), $pdfY + 13, 18, 'default', 26, $actionPricePars[1], 'L');
							$this->cell($pdfFiles[$labelTypeId], 132 + ($priceAddX * 2), $pdfY + 22, 12, 'default', 26, $productPrice->getCurrency()->symbol, 'L');
							if ($labelTypeItem->price_text == ProductLabelsEntity::PRICE_TYPE_CZK_100g && $basePriceUnit && $actionPriceUnit) {
								$this->cell($pdf, 138, $pdfY + 18.7, 10, 'default', 50, '/ '.$basePriceUnit->unitWeight.$basePriceUnit->unitShort, 'L');
							}

							if ($labelTypeItem->getCountry()) {
								if ($countryImage = $labelTypeItem->getCountry()->image) {
									$pdfFiles[$labelTypeId]->Image(FS_DIR.'/images/'.ImagesService::getImageDir($countryImage).'/'.$countryImage, 62, $pdfY + 31, 4);
								}
								$this->cell($pdfFiles[$labelTypeId], 42, $pdfY + 31, 8, 'default', 120, $this->translator->translate('admin.terms.countryOrigin').': '.($countryImage ? '          ' : '').$labelTypeItem->getCountry()->name, 'L', 'sriracha');
							}

							if ($product->code_ean) {
								$this->cell($pdfFiles[$labelTypeId], 42, $pdfY + 35.5, 8, 'default', 120, $this->translator->translate('admin.terms.ean').': '.$product->code_ean, 'L', 'inter');
							}

							if ($labelTypeItem->price_text != ProductLabelsEntity::PRICE_TYPE_CZK_100g && $actionPriceUnit) {
								$this->cell($pdfFiles[$labelTypeId], 113, $pdfY + 35.5, 8, 'default', 40, 'cena za '.$actionPriceUnit->unitWeight.$actionPriceUnit->unitShort.' = '.number_format($actionPriceUnit->price, 2, '.', '').' '.$productPrice->getCurrency()->symbol, 'R');
							}

							$printedIds[] = $labelTypeItem->id;
							$pdfY += 39;
						}
						break;
					case ProductLabelsEntity::TYPE_SIZE_3:
						$pdfY = 1;
						foreach ($labelTypeItems as $labelTypeItem) {
							$pdfYItem = $pdfY;

							$product = $labelTypeItem->getProduct();
							$productPrice = $this->priceFor($product, $branchesId, $priceOverride);
							$basePriceUnit = $product->getUnitValue($productPrice->price, $labelTypeItem->price_text == ProductLabelsEntity::PRICE_TYPE_CZK_100g ? $labelTypeItem->default_units_id : NULL, $labelTypeItem->price_text == ProductLabelsEntity::PRICE_TYPE_CZK_100g ? $labelTypeItem->default_units_weight : NULL);
							$actionPriceUnit = $product->getUnitValue($productPrice->price_action, $labelTypeItem->price_text == ProductLabelsEntity::PRICE_TYPE_CZK_100g ? $labelTypeItem->default_units_id : NULL, $labelTypeItem->price_text == ProductLabelsEntity::PRICE_TYPE_CZK_100g ? $labelTypeItem->default_units_weight : NULL);
							$basePricePars = explode('.', (string)number_format($labelTypeItem->price_text == ProductLabelsEntity::PRICE_TYPE_CZK_100g && $basePriceUnit && $actionPriceUnit ? $basePriceUnit->price : ($productPrice->price ?: 0.0), 2, '.', ''));
							$actionPricePars = explode('.', (string)number_format($labelTypeItem->price_text == ProductLabelsEntity::PRICE_TYPE_CZK_100g && $basePriceUnit && $actionPriceUnit ? $actionPriceUnit->price : ($productPrice->price_action ?: 0.0), 2, '.', ''));
							$priceAddX = $labelTypeItem->price_text == ProductLabelsEntity::PRICE_TYPE_CZK_100g && $basePriceUnit && $actionPriceUnit ? 0 : 5;

							$pdfFiles[$labelTypeId]->Rect(1, $pdfYItem, 208, 14, 'F', '', [0, 0, 0]);
							$pdfFiles[$labelTypeId]->Image(WWW_DIR.'/pdf/logo.jpg', 2, $pdfYItem + 1, 12, 12);
							$pdfFiles[$labelTypeId]->Image(WWW_DIR.'/pdf/logo.jpg', 196, $pdfYItem + 1, 12, 12);
							$this->cell($pdfFiles[$labelTypeId], 1, $pdfYItem + 0.5, 40, 'white', 208, $labelTypeItem->getHeader()?->name ?: '', 'C');

							$this->cell($pdfFiles[$labelTypeId], 1, $pdfYItem + 14, 16, 'default', 208, $labelTypeItem->name, 'C', 'inter');

							$pdfYItem += 21.5;
							if ($labelTypeItem->note_1) {
								$this->cell($pdfFiles[$labelTypeId], 1, $pdfYItem, 12, 'default', 208, $labelTypeItem->note_1, 'C');
								$pdfYItem += 6;
							}

							if ($labelTypeItem->getCountry() || $labelTypeItem->note_3 || $labelTypeItem->is_more_species) {
								if ($labelTypeItem->getCountry()) {
									if ($countryImage = $labelTypeItem->getCountry()->image) {
										$pdfFiles[$labelTypeId]->Image(FS_DIR.'/images/'.ImagesService::getImageDir($countryImage).'/'.$countryImage, 21, $pdfYItem, 4);
									}
									$this->cell($pdfFiles[$labelTypeId], 1, $pdfYItem, 8, 'default', 103, $this->translator->translate('admin.terms.countryOrigin').': '.($countryImage ? '          ' : '').$labelTypeItem->getCountry()->name, 'L', 'sriracha');
								}
								if ($labelTypeItem->is_more_species) {
									$this->cell($pdfFiles[$labelTypeId], 90, $pdfYItem - 1, 10, 'default', 30, $this->translator->translate('admin.terms.moreSpecies'), 'C', 'sriracha');
								}
								if ($labelTypeItem->note_3) {
									$this->cell($pdfFiles[$labelTypeId], 104, $pdfYItem - 1, 8, 'default', 104, $labelTypeItem->note_3, 'R');
								}
								$pdfYItem += 3;
							}

							if (floatval($basePricePars[0].'.'.$basePricePars[1])) {
								$this->cell($pdfFiles[$labelTypeId], 75, $pdfYItem + 2, 12, 'default', 74, $this->translator->translate('admin.terms.basicPrice30days').':', 'L', fontName: 'inter');
							}

							if ($labelTypeItem->sale_text) {
								$this->cell($pdfFiles[$labelTypeId], 120, $pdfYItem, 10, 'default', 90, $labelTypeItem->sale_text, 'R');
							}

							if ($labelTypeItem->sale_percent) {
								$pdfFiles[$labelTypeId]->Image(WWW_DIR.'/pdf/brush_2.png', 1, $pdfYItem + 11, 45, 18);
								$this->cell($pdfFiles[$labelTypeId], 1, $pdfYItem + 11.5, 44, 'white', 45, '-'.$labelTypeItem->sale_percent.'%', 'C');
							}

							if (floatval($basePricePars[0].'.'.$basePricePars[1])) {
								$this->cell($pdfFiles[$labelTypeId], 83, $pdfYItem + 10, 54, 'default', 26, $basePricePars[0], 'R');
								$this->cell($pdfFiles[$labelTypeId], 109, $pdfYItem + 10, 20, 'default', 17, $basePricePars[1], 'L');

								$pdfFiles[$labelTypeId]->Line(75, $pdfYItem + 10, 120, $pdfYItem + 28, ['color' => [0, 0, 0], 'width' => 0.5]);
								$pdfFiles[$labelTypeId]->Line(75, $pdfYItem + 28, 120, $pdfYItem + 10, ['color' => [0, 0, 0], 'width' => 0.5]);
							}

							$this->cell($pdfFiles[$labelTypeId], 150 + ($priceAddX * 2), $pdfYItem + 0, 95, 'default', 26, $actionPricePars[0], 'R');
							$this->cell($pdfFiles[$labelTypeId], 176 + ($priceAddX * 2), $pdfYItem + 6, 35, 'default', 26, $actionPricePars[1], 'L');
							$this->cell($pdfFiles[$labelTypeId], 176 + ($priceAddX * 2), $pdfYItem + 20, 22, 'default', 26, $productPrice->getCurrency()->symbol, 'L');
							if ($labelTypeItem->price_text == ProductLabelsEntity::PRICE_TYPE_CZK_100g && $basePriceUnit && $actionPriceUnit) {
								$this->cell($pdfFiles[$labelTypeId], 187, $pdfYItem + 21.7, 16, 'default', 50, '/ '.$basePriceUnit->unitWeight.$basePriceUnit->unitShort, 'L');
							}

							if ($product->code_ean) {
								$this->cell($pdfFiles[$labelTypeId], 1, $pdfYItem + 31.5, 10, 'default', 145.5, $this->translator->translate('admin.terms.ean').': '.$product->code_ean, 'L', 'inter');
							}

							if ($labelTypeItem->note_2) {
								$this->cell($pdfFiles[$labelTypeId], 62, $pdfYItem + 31.5, 10, 'default', 80, $labelTypeItem->note_2, 'C');
							}

							if ($labelTypeItem->price_text != ProductLabelsEntity::PRICE_TYPE_CZK_100g && $actionPriceUnit) {
								$this->cell($pdfFiles[$labelTypeId], 180, $pdfYItem + 31.5, 10, 'default', 28, 'cena za '.$actionPriceUnit->unitWeight.$actionPriceUnit->unitShort.' = '.number_format($actionPriceUnit->price, 2, '.', '').' '.$productPrice->getCurrency()->symbol, 'R');
							}

							$printedIds[] = $labelTypeItem->id;
							$pdfY += 66;
						}
						break;
				}
			}
		}

		if (count($printedIds) && $printed) {
			$this->productLabelsRepo->findAll()->where('id', $printedIds)->update([
				'datetime_printed' => date('Y-m-d H:i:s'),
				'count' => 0
			]);

			foreach (ProductLabelsRepository::getEntities($this->productLabelsRepo->findAll()
				->where('products_id IN (SELECT products_id FROM product_labels WHERE id IN (?))', $printedIds)
				->where('type_id', 0)) as $defaultType) {
				if (!$this->productLabelsRepo->findAll()
					->where('type_id > ?', 0)
					->where('products_id', $defaultType->products_id)
					->where('datetime_printed IS NULL AND count > ?', 0)
					->count()) {
					$defaultType->setValues([
						'datetime_printed' => date('Y-m-d H:i:s'),
						'count' => 0
					]);
					$this->productLabelsRepo->update($defaultType);
				}
			}
		}

		if (!$printed && count($pdfFiles)) {
			return \Nette\Utils\Arrays::first($pdfFiles);
		}

		if (count($pdfFiles) === 1) {
			return \Nette\Utils\Arrays::first($pdfFiles);
		}

		try {
			$zip = new ZipArchive();
			FilesService::createDirIfNotExists(FS_DIR.'/temp', 'pdf');
			$zipFile = ProductLabelsEntity::ZIP_FILE_PATH.'/'.ProductLabelsEntity::ZIP_FILE_NAME;

			if (file_exists($zipFile)) {
				unlink($zipFile);
			}
			if ($zip->open($zipFile, ZipArchive::CREATE) !== TRUE) {
				return NULL;
			}
			foreach ($pdfFiles as $labelTypeId => $pdfFile) {
				$filePath = ProductLabelsEntity::ZIP_FILE_PATH.'/'.$labelTypeId.'.pdf';
				if ($pdfFile instanceof Dompdf) {
					file_put_contents($filePath, $pdfFile->output());
				}
				else {
					$pdfFile->Output($filePath, 'F');
				}
				$fileName = is_numeric($labelTypeId)
					? ($GLOBALS['global']['productLabelTypes'][$labelTypeId] ?? $labelTypeId)
					: 'A4-template';
				$zip->addFile($filePath, $fileName.'.pdf');
			}
			$zip->close();
		}
		catch (Exception $exception) {
			return NULL;
		}

		return $zipFile;
	}


	/**
	 * The unsaved price/date values for one product: $priceOverride is either a map
	 * keyed by product ID or a single override meant for every label.
	 */
	private function overrideFor(?array $priceOverride, int $productId): ?array
	{
		if (!$priceOverride) {
			return NULL;
		}
		foreach (['price', 'price_action', 'action_from', 'action_to'] as $field) {
			if (array_key_exists($field, $priceOverride)) {
				return $priceOverride;
			}
		}

		return $priceOverride[$productId] ?? NULL;
	}


	/**
	 * Product price as the legacy TCPDF layouts read it, with any unsaved values applied.
	 */
	private function priceFor(ProductsEntity $product, int $branchesId, ?array $priceOverride): ?ProductPricesEntity
	{
		$price = $product->getPriceWithLog($branchesId);
		$override = $this->overrideFor($priceOverride, (int)$product->id);

		return $price && $override ? $this->printTemplatesService->applyPriceOverride($price, $override) : $price;
	}


	/**
	 * @param TCPDF $pdf
	 * @param $x
	 * @param $y
	 * @param $fontSize
	 * @param $textColor
	 * @param $width
	 * @param $content
	 * @param $align
	 * @param $fontName
	 * @return void
	 */
	protected function cell(TCPDF $pdf, $x, $y, $fontSize, $textColor, $width, $content, $align = 'L', $fontName = 'srirachablack'): void
	{
		$this->setTextColor($pdf, $textColor);

		$pdf->SetXY($x, $y);
		$pdf->SetFont($fontName, '', $fontSize);
		$pdf->cell($width, '', $content, '', '', $align);
	}


	/**
	 * @param TCPDF $pdf
	 * @param $x
	 * @param $y
	 * @param $fontSize
	 * @param $textColor
	 * @param $width
	 * @param $height
	 * @param $content
	 * @param $align
	 * @param $valign
	 * @param $fontName
	 * @return void
	 */
	protected function multiCell(TCPDF $pdf, $x, $y, $fontSize, $textColor, $width, $height, $content, $align = 'L', $valign = 'T', $fontName = 'srirachablack')
	{
		$this->setTextColor($pdf, $textColor);

		$pdf->SetFont($fontName, '', $fontSize);
		$pdf->setCellHeightRatio(1);
		$pdf->MultiCell($width, $height, $content, FALSE, $align, x: $x, y: $y, maxh: $height, valign: $valign);
	}


	/**
	 * @param TCPDF $pdf
	 * @param $colorName
	 * @return void
	 */
	protected function setTextColor(TCPDF $pdf, $colorName)
	{
		$colors = [
			'default' => [0, 0, 0],
			'white' => [255, 255, 255],
		];

		if (isset($colors[$colorName]) && $color = $colors[$colorName]) {
			$pdf->SetTextColor($color[0], $color[1], $color[2]);
		}
	}
}
