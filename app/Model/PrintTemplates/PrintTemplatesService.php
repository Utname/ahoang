<?php

use Latte\Engine;
use Latte\Loaders\StringLoader;
use Dompdf\Dompdf;
use Dompdf\Options;

class PrintTemplatesService
{
	use \Nette\SmartObject;

	/** mm -> pt conversion factor (72pt / 25.4mm) */
	private const MM_TO_PT = 72 / 25.4;
	public const PRESET_A4_DISCOUNT = 'a4-discount';
	public const PRESET_A5_DISCOUNT = 'a5-discount';
	public const PRESET_VEL1 = 'vel1';
	public const PRESET_VEL3 = 'vel3';
	public const PRESET_FRUIT = 'fruit';


	public function __construct(
		protected PrintTemplatesRepository $repository,
	)
	{
	}


	/**
	 * Pick the right template variant (with/without discount) for a product currently being printed.
	 * Templates are grouped by `name` — each group may have up to 3 rows (discount / no_discount / always).
	 *
	 * @param string $templateName
	 * @param ProductsEntity $product
	 * @param int $branchesId
	 * @return PrintTemplatesEntity|null
	 */
	public function findTemplateForProduct(string $templateName, ProductsEntity $product, int $branchesId): ?PrintTemplatesEntity
	{
		$price = $product->getPriceWithLog($branchesId);
		$hasDiscount = $price && !empty($price->is_valid_action);

		$wantedVariant = $hasDiscount ? PrintTemplatesEntity::VARIANT_DISCOUNT : PrintTemplatesEntity::VARIANT_NO_DISCOUNT;

		$template = $this->repository->findEntityBy([
			'name' => $templateName,
			'is_active' => TRUE,
			'is_default' => TRUE,
			'variant' => $wantedVariant,
		]);

		if (!$template) {
			$template = $this->repository->findEntityBy([
				'name' => $templateName,
				'is_active' => TRUE,
				'is_default' => TRUE,
				'variant' => PrintTemplatesEntity::VARIANT_ALWAYS,
			]);
		}

		return $template instanceof PrintTemplatesEntity ? $template : NULL;
	}


	/**
	 * Resolve the template from the physical label type, rather than an editable
	 * display name. This is the bridge between the existing print queue and the
	 * new HTML template system.
	 */
	public function findTemplateForProductLabel(ProductLabelsEntity $label, ProductsEntity $product, int $branchesId): ?PrintTemplatesEntity
	{
		$price = $product->getPriceWithLog($branchesId);
		$wantedVariant = $price && !empty($price->is_valid_action)
			? PrintTemplatesEntity::VARIANT_DISCOUNT
			: PrintTemplatesEntity::VARIANT_NO_DISCOUNT;
		$hasExplicitMapping = in_array('product_label_type_id', $this->repository->getColumnNames(), TRUE);

		// Use the explicit label-type mapping after the migration. For an older
		// database, A4 can still be mapped safely by its unambiguous physical size,
		// allowing an already-created 210 × 297 mm template to go live immediately.
		$criteria = $hasExplicitMapping
			? ['product_label_type_id' => $label->type_id]
			: $this->getLegacySizeCriteria($label->type_id);

		if (!$criteria) {
			return NULL;
		}

		$template = $this->repository->findEntityBy($criteria + [
			'is_active' => TRUE,
			'is_default' => TRUE,
			'variant' => $wantedVariant,
		]);

		if (!$template) {
			$template = $this->repository->findEntityBy($criteria + [
				'is_active' => TRUE,
				'is_default' => TRUE,
				'variant' => PrintTemplatesEntity::VARIANT_ALWAYS,
			]);
		}

		return $template instanceof PrintTemplatesEntity ? $template : NULL;
	}


	/** @return array<string, int>|null */
	private function getLegacySizeCriteria(int $labelTypeId): ?array
	{
		return match ($labelTypeId) {
			ProductLabelsEntity::TYPE_A4 => ['width_mm' => 210, 'height_mm' => 297],
			// The reviewed "Velikost 2B" design is stored as one 133 × 52 mm
			// label. It is placed twice on an A4 sheet by renderSize2BPage().
			ProductLabelsEntity::TYPE_SIZE_2 => ['width_mm' => 133, 'height_mm' => 52],
			ProductLabelsEntity::TYPE_A5 => ['width_mm' => 200, 'height_mm' => 138.5],
			default => NULL,
		};
	}


	/**
	 * Install the reviewed A4 design as an editable admin template. The source is
	 * deliberately stored in the database after installation, so it remains
	 * editable in the existing UI.
	 */
	public function installPreset(string $preset): PrintTemplatesEntity
	{
		$config = match ($preset) {
			self::PRESET_A4_DISCOUNT => [
				'name' => 'Fikko Cena A4',
				'labelTypeId' => ProductLabelsEntity::TYPE_A4,
				'widthMm' => 210,
				'heightMm' => 297,
				'sourceFile' => 'a4-discount.latte',
			],
			self::PRESET_A5_DISCOUNT => [
				'name' => 'Fikko Cena A5',
				'labelTypeId' => ProductLabelsEntity::TYPE_A5,
				'widthMm' => 200,
				'heightMm' => 138.5,
				'sourceFile' => 'a5-discount.latte',
			],
			self::PRESET_VEL1 => [
				'name' => 'Fikko Cena Vel.1',
				'labelTypeId' => ProductLabelsEntity::TYPE_SIZE_1,
				'widthMm' => 150,
				'heightMm' => 44,
				'sourceFile' => 'vel1.latte',
			],
			self::PRESET_VEL3 => [
				'name' => 'Fikko Cena Vel.3',
				'labelTypeId' => ProductLabelsEntity::TYPE_SIZE_3,
				'widthMm' => 182,
				'heightMm' => 56,
				'sourceFile' => 'vel3.latte',
			],
			self::PRESET_FRUIT => [
				'name' => 'Fikko Cena Ovoce',
				'labelTypeId' => ProductLabelsEntity::TYPE_SIZE_3,
				'widthMm' => 50,
				'heightMm' => 35,
				'sourceFile' => 'fruit.latte',
			],
			default => throw new \InvalidArgumentException('Unknown print template preset.'),
		};

		// Every preset's own .latte source branches internally on $hasSaleRibbon /
		// $isDiscounted to render the discount and no-discount layouts from the
		// same row, so the row itself always applies — it must be filed as
		// VARIANT_ALWAYS, not VARIANT_DISCOUNT (which would make it invisible to
		// findTemplateForProductLabel() for any non-discounted product).
		if ($existing = $this->repository->findEntityBy([
			'product_label_type_id' => $config['labelTypeId'],
			'variant' => PrintTemplatesEntity::VARIANT_ALWAYS,
			'name' => $config['name'],
		])) {
			return $existing;
		}

		$sourcePath = __DIR__.'/templates/'.$config['sourceFile'];
		$source = @file_get_contents($sourcePath);
		if ($source === FALSE) {
			throw new \RuntimeException('Print template source is missing: '.$config['sourceFile']);
		}

		$this->repository->findAll()
			->where('product_label_type_id', $config['labelTypeId'])
			->where('variant', PrintTemplatesEntity::VARIANT_ALWAYS)
			->update(['is_default' => FALSE]);

		$row = $this->repository->insert([
			'name' => $config['name'],
			'product_label_type_id' => $config['labelTypeId'],
			'width_mm' => $config['widthMm'],
			'height_mm' => $config['heightMm'],
			'variant' => PrintTemplatesEntity::VARIANT_ALWAYS,
			'html_source' => $source,
			'is_active' => TRUE,
			'is_default' => TRUE,
		]);

		return PrintTemplatesRepository::getEntity($row);
	}


	/**
	 * @param PrintTemplatesEntity $template
	 * @param ProductsEntity $product
	 * @param int $branchesId
	 * @param array{price?: ?float, price_action?: ?float, action_from?: ?string, action_to?: ?string}|null $priceOverride
	 *        Renders as if product_prices held these values instead of whatever is
	 *        actually saved — used for "preview with the price/date fields I just typed
	 *        but haven't saved yet" (see ProductLabelsControl::handleSaveLabelCount() and
	 *        ProductLabelsTrait::handleDownloadPdf()). Nothing is written to the database;
	 *        a clone of the real price row is mutated in memory only. action_from/action_to
	 *        are 'Y-m-d' strings (already normalized by the caller) or NULL to clear.
	 * @return string rendered HTML, ready for renderPdf() or direct display
	 */
	public function renderHtml(PrintTemplatesEntity $template, ProductsEntity $product, int $branchesId, ?ProductLabelsEntity $label = NULL, ?array $priceOverride = NULL): string
	{
		// Match the specific "fikko-a4" preset markup, not just its physical size — an
		// older 210 × 297 mm template (a different design, pre-dating this preset) must
		// not have its content mangled by the A4-only barcode-stripping surgery below.
		$isA4 = str_contains($template->html_source, 'fikko-a4') || str_contains($template->html_source, 'A4 Akce') || (float)$template->width_mm === 210.0;
		$isFruit = str_contains($template->html_source, 'fikko-fruit');
		$isSize2 = (float)$template->width_mm === 133.0;
		$isSize2B = $this->isSize2Template($template);
		$isA5 = $this->isA5Template($template);
		$price = $product->getPriceWithLog($branchesId);
		if ($priceOverride && $price) {
			$price = $this->applyPriceOverride($price, $priceOverride);
		}
		$ean = trim($product->getDefaultCode() ?: '');
		// A queued label is a snapshot for printing. Its sale_percent means the
		// action price must remain on the label even after the action date ends,
		// which is how the previous TCPDF renderer behaved.
		$usesStoredLabelDiscount = $label && (float)$label->sale_percent > 0 && $price && (float)$price->price_action > 0;
		$isDiscounted = ($price && !empty($price->is_valid_action)) || $usesStoredLabelDiscount;
		$finalPrice = $isDiscounted ? (float)$price->price_action : (float)($price->price ?? 0);
		$basePrice = (float)($price->price ?? 0);
		// A queued label carries an explicit "-X %" ribbon (admin-entered, same field the
		// previous TCPDF renderer read); without a label (e.g. direct product preview),
		// fall back to the computed discount ratio so the ribbon still has a real value.
		$salePercent = $label && (float)$label->sale_percent > 0
			? (float)$label->sale_percent
			: ($isDiscounted && $basePrice > 0 ? floor((($basePrice - $finalPrice) / $basePrice) * 100) : 0.0);
		$hasSaleRibbon = $isDiscounted && $salePercent > 0;
		$finalPriceMajor = number_format(floor($finalPrice), 0, ',', ' ');
		$priceDigits = strlen(str_replace([' ', ','], '', $finalPriceMajor));
		$labelName = $label?->name ?: $product->name;
		$country = $label?->getCountry() ?: $product->getCountry();
		$unit = $price ? $product->getUnitValue(
			$finalPrice,
			$label?->default_units_id ?: NULL,
			$label?->default_units_weight ?: NULL,
		) : NULL;
		$currencySymbol = $price?->getCurrency()?->symbol ?: 'Kč';
		$hideValidity = (bool)($label?->hide_validity ?? 0);
		$hideIngredients = (bool)($label?->hide_ingredients ?? 0);
		$validityText = $hideValidity ? NULL : ($label?->sale_text ?: $this->formatValidity($price));

		$note1 = $label?->note_1 ?: $product->label_note_1;
		$note2 = $label?->note_2 ?: $product->label_note_2;
		$note3 = $label?->note_3 ?: $product->label_note_3;

		$saving = round(max(0, $basePrice - $finalPrice), 2);
		$latte = new Engine();
		$latte->setLoader(new StringLoader());

		$html = $latte->renderToString($template->html_source, [
			'product' => $product,
			'label' => $label,
			'note1' => $note1,
			'note2' => $note2,
			'note3' => $note3,
			'hideValidity' => $hideValidity,
			'hideIngredients' => $hideIngredients,
			'labelName' => $labelName,
			'labelNameLength' => function_exists('mb_strlen') ? mb_strlen($labelName) : strlen($labelName),
			'price' => $price,
			'basePrice' => $basePrice,
			'basePriceText' => number_format($basePrice, 2, ',', ' '),
			'finalPrice' => $finalPrice,
			'finalPriceMajor' => $finalPriceMajor,
			'finalPriceMinor' => ','.substr(number_format($finalPrice, 2, '.', ''), -2),
			'priceDigits' => $priceDigits,
			'salePercent' => $salePercent,
			'hasSaleRibbon' => $hasSaleRibbon,
			'saving' => $saving,
			'savingText' => (floor($saving) == $saving)
				? number_format($saving, 0, ',', ' ')
				: number_format($saving, 2, ',', ' '),
			'isDiscounted' => $isDiscounted,
			'currencySymbol' => $currencySymbol,
			'unit' => $unit,
			'unitText' => $unit ? sprintf('%s / %s %s', number_format($unit->price, 2, ',', ' '), $unit->unitWeight, $unit->unitShort) : NULL,
			'country' => $country,
			'validityText' => $validityText,
			'ean' => $ean,
			'eanText' => $ean !== '' ? EanBarcodeHelper::getDigits($ean) : '',
			'bars' => match (TRUE) {
				$ean === '' => [],
				$isA4 => EanBarcodeHelper::getBars($ean, 0.38),
				$isSize2 => EanBarcodeHelper::getBars($ean, 0.28),
				$isA5 => EanBarcodeHelper::getBars($ean, 0.42),
				$isFruit => EanBarcodeHelper::getBars($ean, 0.20),
				default => [],
			},
			'template' => $template,
			'logoDataUri' => $this->imageAsDataUri(WWW_DIR . '/images/fikko-logo-mono.png', 'image/png'),
			'brushDataUri' => $this->imageAsDataUri(WWW_DIR . '/images/brush-20.png', 'image/png'),
			// Dompdf mis-renders brush-20.png at roughly half its declared height when
			// the box is large enough (confirmed on the A5 ribbon "-X %" badge, 58.5×24.2mm) —
			// isolated to Dompdf's handling of large near-black/near-gray PNGs specifically;
			// the exact same artwork retinted with a faint navy cast (barely perceptible at
			// print size) renders at the correct height. Same shape/alpha as brush-20.png,
			// used only where the badge is large enough to trip this. Rendered via <img> (a5-discount.latte),
			// which is unaffected by the background-image bug below — keep this one with its alpha
			// channel intact.
			'ribbonBadgeBrushDataUri' => $this->imageAsDataUri(WWW_DIR . '/images/brush-20-ribbon-badge.png', 'image/png'),
			// Same navy artwork as ribbonBadgeBrushDataUri above, pre-flattened onto an opaque white
			// background (no alpha channel at all — PNG color type 2, not 6) for use as a CSS
			// background-image (Vel.3's "Ušetříte" badge). Dompdf's pure-GD background-image
			// compositor (Cpdf::addImagePngAlpha) has a confirmed bug with 8-bit RGBA source PNGs —
			// its own source carries a "FIXME: doesn't work well with 8bit PNGs" comment — where
			// $eight_bit = ($byte & 4) !== 4 evaluates false for color type 6, sending it down a
			// broken non-gamma-corrected extraction branch that silently produces an invisible result
			// (background-color still paints, the image on top of it never does) even though every
			// intermediate GD buffer measured correct. A plain <img> tag with the SAME alpha PNG
			// renders fine (different call path, see ribbonBadgeBrushDataUri above), but CSS
			// background-image does not. Since Vel.3's badge always sits on the label's plain white
			// background, flattening away the alpha channel ahead of time sidesteps the Dompdf bug
			// entirely instead of working around it at render time. Kept as a separate variable
			// (not reusing ribbonBadgeBrushDataUri) so this doesn't change a5-discount.latte's
			// already-working <img>-based ribbon badge, which sits on artwork this flattening was
			// never verified against.
			'ribbonBadgeBrushFlatDataUri' => $this->imageAsDataUri(WWW_DIR . '/images/brush-20-ribbon-badge-flat.png', 'image/png'),
			// Dompdf does not render inline <svg> markup at all (confirmed: even a bare filled
			// <rect> inside <svg> paints nothing), so the save-badge's piggy-bank mascot from the
			// original designs is pre-rendered to PNG and embedded as an image instead, the same
			// way the logo/brush already are.
			// The origin flag uses the country's own uploaded image (countries.image) via the same
			// getImageDir() lookup the old TCPDF renderer used, not a static placeholder — so it
			// actually matches the product's real origin country instead of always showing Czech.
			'flagIconDataUri' => $this->countryFlagDataUri($country),
			'pigIconDataUri' => $this->imageAsDataUri(WWW_DIR . '/images/pig-icon.png', 'image/png'),
			// A5's save-badge uses a flat piggy-bank silhouette (Material Symbols "savings"
			// glyph), a different icon from A4's mascot — also rasterized, same reason as above.
			'pigFaceIconDataUri' => $this->imageAsDataUri(WWW_DIR . '/images/pig-face-icon.png', 'image/png'),
			'strikeSlashDataUri' => $this->imageAsDataUri(WWW_DIR . '/images/strike-slash.png', 'image/png'),
		]);

		$isVel1 = str_contains($template->html_source, 'fikko-vel1');
		$isVel3 = str_contains($template->html_source, 'fikko-vel3');

		if ($ean !== '' && ($isVel1 || $isVel3 || $isSize2B)) {
			$html = $this->injectEanBarcode($html, $ean, FALSE, $isVel1, $isVel3, $isSize2B);
		}

		if ($isA5) {
			$html = '<style>.fikko-a5 .product-meta{border-bottom:none!important}</style>' . $html;
		}

		return $html;
	}



	/**
	 * Clones $price and overwrites it with the given override values, recomputing
	 * is_valid_action with the exact same rule ProductsEntity::getPrice() uses
	 * (price > price_action, action_from <= today <= action_to). Never touches the
	 * database — the caller already has its own live $price row loaded; this only
	 * changes what renderHtml() sees for this one render.
	 *
	 * @param array{price?: ?float, price_action?: ?float, action_from?: ?string, action_to?: ?string} $priceOverride
	 */
	private function applyPriceOverride(ProductPricesEntity $price, array $priceOverride): ProductPricesEntity
	{
		$price = clone $price;

		if (array_key_exists('price', $priceOverride)) {
			$price->price = $priceOverride['price'];
		}
		if (array_key_exists('price_action', $priceOverride)) {
			$price->price_action = $priceOverride['price_action'];
		}
		if (array_key_exists('action_from', $priceOverride)) {
			$price->action_from = $priceOverride['action_from'] ? new \DateTime($priceOverride['action_from']) : NULL;
		}
		if (array_key_exists('action_to', $priceOverride)) {
			$price->action_to = $priceOverride['action_to'] ? new \DateTime($priceOverride['action_to']) : NULL;
		}

		$today = date('Y-m-d');
		$price->is_valid_action = (bool)(
			$price->action_from && $price->price_action && $price->price && $price->price > $price->price_action
			&& $price->action_from->format('Y-m-d') <= $today
			&& (!$price->action_to || $price->action_to->format('Y-m-d') >= $today)
		);

		return $price;
	}


	/**
	 * Adds EAN-13 barcode bars directly above the human-readable EAN of the A4,
	 * Vel.1 and Vel.3 labels. Bars are floated boxes (Dompdf's flexbox fails with
	 * ~60 adjacent elements, see a5-discount.latte). Values in the markup come only
	 * from EanBarcodeHelper, never from user input.
	 */
	private function injectEanBarcode(string $html, string $ean, bool $isA4, bool $isVel1, bool $isVel3, bool $isSize2B = FALSE): string
	{
		// Heights must be absolute: Dompdf resolves `height:100%` of a floated bar against
		// the page, which made the white bars blank out the whole A4 label.
		if ($isA4) {
			$unit = 0.38;
			$barHeight = 12;
			$css = '.ean-barcode{position:absolute;left:0;bottom:4.6mm;width:40mm;height:12mm;overflow:hidden}'
				.'.fikko-a4 .ean{top:277mm!important;bottom:auto!important;height:3.6mm!important;background:none!important}';
			$pattern = '#<div class="ean"#';
		}
		elseif ($isVel1) {
			$unit = 0.28;
			$barHeight = 8;
			// The logo column is crowded: lift the "Fikko / Cena" wordmark to make room.
			$css = '.ean-barcode{position:absolute;left:2.7mm;top:28.5mm;width:27mm;height:8mm;overflow:hidden}'
				.'.fikko-vel1 .brand-fikko{top:8.6mm!important}.fikko-vel1 .brand-cena{top:16.5mm!important}'
				.'.fikko-vel1 .ean-text{left:0!important;right:auto!important;top:37.6mm!important;width:32mm!important;text-align:center!important;font-size:2.4mm!important}';
			$pattern = '#<div class="ean-text"#';
		}
		elseif ($isVel3) {
			$unit = 0.30;
			$barHeight = 9;
			$css = '.ean-barcode{position:absolute;left:0;bottom:7.8mm;width:30mm;height:9mm;overflow:hidden}';
			$pattern = '#<div class="ean-text"#';
		}
		elseif ($isSize2B) {
			$unit = 0.30;
			$barHeight = 5;
			$css = '.size2b-barcode{position:absolute;left:2mm;bottom:4.2mm;width:32mm;height:5mm;overflow:hidden}'
				.'.size2b-ean{left:2mm!important;bottom:1.2mm!important;top:auto!important}';
			$pattern = '#<div class="size2b-ean"#';
		}
		else {
			return $html;
		}

		$bars = '';
		foreach (EanBarcodeHelper::getBars($ean, $unit) as $bar) {
			$bars .= '<div style="float:left;height:'.$barHeight.'mm;background:'.$bar['color'].';width:'.$bar['width'].'"></div>';
		}
		$barcode = '<div class="'.($isSize2B ? 'size2b-barcode' : 'ean-barcode').'">'.$bars.'</div>';

		$injected = preg_replace($pattern, $barcode.'$0', $html, 1, $count);
		if (!$count || $injected === NULL) {
			return $html;
		}

		return '<style>'.$css.'</style>'.$injected;
	}


	/**
	 * The original A4 design has no barcode class: it is an inline-styled
	 * 8.5 mm high div directly before the div containing "EAN ...". Remove
	 * only that previous sibling, preserving the human-readable EAN value.
	 */
	private function stripInlineA4Barcode(string $html): string
	{
		if (!str_contains($html, 'height:8.5mm') || !str_contains($html, 'EAN')) {
			return $html;
		}

		$previousUseInternalErrors = libxml_use_internal_errors(TRUE);
		try {
			$document = new \DOMDocument('1.0', 'UTF-8');
			$document->loadHTML(
				'<div id="print-template-root">' . $html . '</div>',
				LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD,
			);
			$root = $document->getElementById('print-template-root');
			if (!$root) {
				return $html;
			}

			$xpath = new \DOMXPath($document);
			/** @var \DOMElement $eanNode */
			foreach ($xpath->query('//div[starts-with(normalize-space(string(.)), "EAN ")]') ?: [] as $eanNode) {
				if ($eanNode->childElementCount > 0) {
					continue;
				}

				$previous = $eanNode->previousSibling;
				while ($previous && $previous->nodeType !== XML_ELEMENT_NODE) {
					$previous = $previous->previousSibling;
				}
				if (!$previous instanceof \DOMElement) {
					continue;
				}

				$style = strtolower(str_replace([' ', "\n", "\r", "\t"], '', $previous->getAttribute('style')));
				$isKnownBarcodeBox = str_contains($style, 'height:8.5mm')
					&& (str_contains($style, 'overflow:hidden') || str_contains($style, 'line-height:1px'));
				if ($isKnownBarcodeBox) {
					$previous->parentNode?->removeChild($previous);
				}
			}

			$cleanHtml = '';
			foreach ($root->childNodes as $node) {
				$cleanHtml .= $document->saveHTML($node);
			}

			return $cleanHtml;
		}
		catch (\Throwable) {
			return $html;
		}
		finally {
			libxml_clear_errors();
			libxml_use_internal_errors($previousUseInternalErrors);
		}
	}


	/** @param ProductPricesEntity|null $price */
	private function formatValidity(?ProductPricesEntity $price): string
	{
		if (!$price || empty($price->is_valid_action)) {
			return '';
		}

		$from = $price->action_from?->format('j. n.');
		$to = $price->action_to?->format('j. n.');

		return trim(implode(' - ', array_filter([$from, $to])));
	}


	/**
	 * @param string $path
	 * @param string $mime
	 * @return string
	 */
	private function imageAsDataUri(string $path, string $mime): string
	{
		if (!is_file($path)) {
			return '';
		}

		return 'data:' . $mime . ';base64,' . base64_encode((string)file_get_contents($path));
	}


	/**
	 * Resolves a country's own uploaded flag (countries.image), the same file the old TCPDF
	 * renderer reads via ImagesService::getImageDir() — real per-country artwork, not a
	 * one-size-fits-all placeholder.
	 */
	private function countryFlagDataUri(?CountriesEntity $country): string
	{
		$image = $country?->image;

		if (!$image) {
			return '';
		}

		$mime = match (strtolower((string)pathinfo($image, PATHINFO_EXTENSION))) {
			'jpg', 'jpeg' => 'image/jpeg',
			'gif' => 'image/gif',
			'webp' => 'image/webp',
			default => 'image/png',
		};

		return $this->imageAsDataUri(FS_DIR . '/images/' . ImagesService::getImageDir($image) . '/' . $image, $mime);
	}


	/**
	 * @param PrintTemplatesEntity $template
	 * @param ProductsEntity $product
	 * @param int $branchesId
	 * @param ProductLabelsEntity|null $label
	 * @param array{price?: ?float, price_action?: ?float, action_from?: ?string, action_to?: ?string}|null $priceOverride
	 * @return string binary PDF content
	 */
	public function renderPdf(PrintTemplatesEntity $template, ProductsEntity $product, int $branchesId, ?ProductLabelsEntity $label = NULL, ?array $priceOverride = NULL): string
	{
		$html = $this->renderHtml($template, $product, $branchesId, $label, $priceOverride);
		if ($this->isSize2Template($template)) {
			return $this->renderDompdf($this->renderSize2Page($html, $template), 210, 297)->output();
		}
		if ($this->isA5Template($template)) {
			return $this->renderDompdf($this->renderA5Page($html), 210, 297)->output();
		}
		if ($this->isVel1Template($template)) {
			return $this->renderDompdf($this->renderVel1Page($html), 210, 297)->output();
		}
		if ($this->isVel3Template($template)) {
			return $this->renderDompdf($this->renderVel3Page($html), 210, 297)->output();
		}

		$dompdf = $this->renderDompdf(
			$html,
			(float)$template->width_mm,
			(float)$template->height_mm,
		);

		return $dompdf->output();
	}


	/**
	 * @param array<int, array{template: PrintTemplatesEntity, label: ProductLabelsEntity, priceOverride?: ?array}> $jobs
	 */
	public function renderProductLabelsPdf(array $jobs, int $branchesId): Dompdf
	{
		if (!$jobs) {
			throw new \InvalidArgumentException('At least one print job is required.');
		}

		$pages = [];
		foreach ($jobs as $job) {
			$product = $job['label']->getProduct();
			if (!$product) {
				continue;
			}
			$html = $this->renderHtml($job['template'], $product, $branchesId, $job['label'], $job['priceOverride'] ?? NULL);
			$pages[] = match (TRUE) {
				$this->isSize2Template($job['template']) => $this->renderSize2Page($html, $job['template']),
				$this->isA5Template($job['template']) => $this->renderA5Page($html),
				$this->isVel1Template($job['template']) => $this->renderVel1Page($html),
				$this->isVel3Template($job['template']) => $this->renderVel3Page($html),
				default => $html,
			};
		}

		if (!$pages) {
			throw new \RuntimeException('No printable products were found.');
		}

		$html = '<!doctype html><html><head><meta charset="utf-8"><style>'
			.'@page { margin: 0; } body { margin: 0; padding: 0; } '
			.'.print-template-page { page-break-after: always; } '
			.'.print-template-page:last-child { page-break-after: auto; }'
			.'</style></head><body><div class="print-template-page">'
			.implode('</div><div class="print-template-page">', $pages)
			.'</div></body></html>';

		return $this->renderDompdf($html, 210, 297);
	}


	private function isSize2Template(PrintTemplatesEntity $template): bool
	{
		return (float)$template->width_mm === 133.0;
	}


	private function isA5Template(PrintTemplatesEntity $template): bool
	{
		return (float)$template->width_mm === 200.0 && (float)$template->height_mm === 138.5;
	}


	/**
	 * Velikost 2 / 2B is a two-up A4 stock. Its stored source is one label; this
	 * method reuses that exact label twice, at the coordinates from the design.
	 */
	private function renderSize2Page(string $html, PrintTemplatesEntity $template): string
	{
		preg_match_all('#<style\b[^>]*>.*?</style>#is', $html, $styleMatches);
		preg_match('#<body\b[^>]*>(.*)</body>#is', $html, $bodyMatch);
		$labelHtml = $bodyMatch[1] ?? (preg_replace('#<style\b[^>]*>.*?</style>#is', '', $html) ?? $html);
		$styles = implode('', $styleMatches[0] ?? []);
		$height = (float)$template->height_mm > 0 ? (float)$template->height_mm : 41.0;
		$secondTop = 8.0 + $height;

		return '<style>'
			.'@page{margin:0}body{margin:0!important;padding:0!important;width:210mm!important}'
			.'.size2-sheet{position:relative;width:210mm;height:297mm;overflow:hidden}'
			.'.size2-label{position:absolute;left:8mm;width:133mm;height:'.$height.'mm;overflow:visible}'
			.'.size2-label.first{top:8mm}.size2-label.second{top:'.$secondTop.'mm}'
			.'.size2-label.second .size2b-label-root,.size2-label.second .fikko-vel2{border-top:0}'
			.'.size2-divider{position:absolute;top:'.$secondTop.'mm;left:8mm;width:133mm;border-top:.4mm dashed #111;z-index:5}'
			.'.size2-label table.wrap{height:'.$height.'mm!important}'
			.'</style>'.$styles
			.'<div class="size2-sheet"><div class="size2-label first">'.$labelHtml.'</div><div class="size2-divider"></div>'
			.'<div class="size2-label second">'.$labelHtml.'</div></div>';
	}


	/**
	 * A5 is a two-up A4 stock, same as Velikost 2B: the original design places two
	 * identical 210 × 148.5 mm labels stacked on one A4 portrait sheet, separated
	 * by a thin dashed line — the stored source is only one label.
	 */
	private function renderA5Page(string $html): string
	{
		preg_match_all('#<style\b[^>]*>.*?</style>#is', $html, $styleMatches);
		preg_match('#<body\b[^>]*>(.*)</body>#is', $html, $bodyMatch);
		$labelHtml = $bodyMatch[1] ?? (preg_replace('#<style\b[^>]*>.*?</style>#is', '', $html) ?? $html);
		$styles = implode('', $styleMatches[0] ?? []);

		return '<style>'
			.'@page{margin:0}body{margin:0!important;padding:0!important;width:210mm!important}'
			.'.a5-sheet{position:relative;width:210mm;height:297mm;overflow:hidden}'
			.'.a5-label{position:absolute;left:0;width:210mm;height:148.5mm;overflow:visible}'
			.'.a5-label.first{top:0}.a5-label.second{top:148.5mm}'
			.'.a5-divider{position:absolute;top:147mm;left:8mm;width:194mm;height:3mm;line-height:3mm;z-index:5;color:#777;font-size:2.6mm;white-space:nowrap}'
			.'.a5-divider-icon{display:inline-block;vertical-align:middle;font-family:"DejaVu Sans",sans-serif;font-size:4.5mm;line-height:1;margin-right:2mm;color:#555}'
			.'.a5-divider-line{display:inline-block;vertical-align:middle;width:186mm;border-top:0.3mm dashed #555}'
			.'</style>'.$styles
			.'<div class="a5-sheet"><div class="a5-label first">'.$labelHtml.'</div>'
			.'<div class="a5-divider"><span class="a5-divider-icon">&#9986;</span><span class="a5-divider-line"></span></div>'
			.'<div class="a5-label second">'.$labelHtml.'</div></div>';
	}


	private function isVel1Template(PrintTemplatesEntity $template): bool
	{
		return (float)$template->width_mm === 150.0 && (float)$template->height_mm === 44.0;
	}


	private function isVel3Template(PrintTemplatesEntity $template): bool
	{
		return (float)$template->width_mm === 182.0 && (float)$template->height_mm === 56.0;
	}


	/**
	 * Vel.1 is a three-up A4 stock: the original design stacks three identical
	 * 150 × 44 mm strips, left-aligned inside a 6 mm page margin, with no gap
	 * between them (their own dashed borders double up at the shared edge,
	 * matching the design mockup exactly).
	 */
	private function renderVel1Page(string $html): string
	{
		preg_match_all('#<style\b[^>]*>.*?</style>#is', $html, $styleMatches);
		preg_match('#<body\b[^>]*>(.*)</body>#is', $html, $bodyMatch);
		$labelHtml = $bodyMatch[1] ?? (preg_replace('#<style\b[^>]*>.*?</style>#is', '', $html) ?? $html);
		$styles = implode('', $styleMatches[0] ?? []);

		return '<style>'
			.'@page{margin:0}body{margin:0!important;padding:0!important;width:210mm!important}'
			.'.vel1-sheet{position:relative;width:210mm;height:297mm;overflow:hidden}'
			.'.vel1-label{position:absolute;left:6mm;width:150mm;height:44mm;overflow:visible}'
			.'.vel1-label.n1{top:6mm}.vel1-label.n2{top:50mm}.vel1-label.n3{top:94mm}'
			// Each label carries its own full dashed border; stacked with no gap, the
			// bottom border of one and the top border of the next would double up into
			// two visible lines at the seam. Only the first label keeps its top border.
			.'.vel1-label.n2 .fikko-vel1,.vel1-label.n3 .fikko-vel1{border-top:0}'
			.'</style>'.$styles
			.'<div class="vel1-sheet">'
			.'<div class="vel1-label n1">'.$labelHtml.'</div>'
			.'<div class="vel1-label n2">'.$labelHtml.'</div>'
			.'<div class="vel1-label n3">'.$labelHtml.'</div>'
			.'</div>';
	}


	/**
	 * Vel.3 is a two-up A4 stock: two identical 182 × 56 mm strips inside an
	 * 8 mm page margin, with a 2 mm gap between them, exactly as stacked in
	 * the original design mockup.
	 */
	private function renderVel3Page(string $html): string
	{
		preg_match_all('#<style\b[^>]*>.*?</style>#is', $html, $styleMatches);
		preg_match('#<body\b[^>]*>(.*)</body>#is', $html, $bodyMatch);
		$labelHtml = $bodyMatch[1] ?? (preg_replace('#<style\b[^>]*>.*?</style>#is', '', $html) ?? $html);
		$styles = implode('', $styleMatches[0] ?? []);

		return '<style>'
			.'@page{margin:0}body{margin:0!important;padding:0!important;width:210mm!important}'
			.'.vel3-sheet{position:relative;width:210mm;height:297mm;overflow:hidden}'
			.'.vel3-label{position:absolute;left:8mm;width:182mm;height:56mm;overflow:visible}'
			.'.vel3-label.n1{top:8mm}.vel3-label.n2{top:72mm}'
			.'.vel3-divider{position:absolute;top:68mm;left:8mm;width:182mm;border-top:.3mm dashed #999;z-index:5}'
			.'</style>'.$styles
			.'<div class="vel3-sheet">'
			.'<div class="vel3-label n1">'.$labelHtml.'</div>'
			.'<div class="vel3-divider"></div>'
			.'<div class="vel3-label n2">'.$labelHtml.'</div>'
			.'</div>';
	}


	private function renderDompdf(string $html, float $widthMm, float $heightMm): Dompdf
	{
		$dompdf = $this->createDompdf();
		$dompdf->loadHtml($this->ensureFullHtmlDocument($html));
		$dompdf->setPaper([0, 0, $widthMm * self::MM_TO_PT, $heightMm * self::MM_TO_PT]);
		$dompdf->render();

		return $dompdf;
	}


	/**
	 * Dompdf silently renders a blank page — no exception, no warning — when fed a bare
	 * fragment (e.g. a template that starts directly with <style>, with no <html>/<body>).
	 * Browsers tolerate that for the HTML preview; Dompdf's parser does not. Wrap any
	 * fragment in a minimal document before it ever reaches Dompdf.
	 */
	private function ensureFullHtmlDocument(string $html): string
	{
		if (stripos($html, '<html') !== FALSE) {
			return $html;
		}

		return '<!DOCTYPE html><html><head><meta charset="utf-8"></head><body>'.$html.'</body></html>';
	}


	private function createDompdf(): Dompdf
	{

		$projectDir = dirname(__DIR__, 3);

		$options = new Options();
		$options->set('isRemoteEnabled', TRUE);
		$options->set('defaultFont', 'DejaVu Sans');
		// Dompdf's font SUBSETTING step (not registration, not the font file itself —
		// isolated by testing with isFontSubsettingEnabled toggled) silently corrupts the
		// glyph-to-outline mapping for Nunito-BlackItalic.ttf/Nunito-RegularItalic.ttf,
		// rendering every character as an empty "tofu" box (.notdef) at every font size,
		// while the identical text renders correctly the instant subsetting is disabled.
		// These fonts are static instances cut from a variable font via a build tool
		// ("FontKit" per its name-table signature); their cmap uses format 4 like any other
		// TrueType font, so the exact incompatibility wasn't pinned down further — disabling
		// subsetting is the confirmed, low-risk fix (these label PDFs are 1-3 pages, so the
		// larger embedded-font size is immaterial).
		$options->set('isFontSubsettingEnabled', FALSE);
		// Dompdf only allows local file:// reads under its own chroot (dompdf's package dir by
		// default), which excludes our project's www/pdf/ font assets — widen it to also permit
		// the project directory, otherwise registerFont() below fails silently ("Permission denied").
		$options->setChroot([$options->getChroot()[0], $projectDir]);

		$dompdf = new Dompdf($options);

		// Dompdf's bundled "Helvetica"/"Arial" are non-embedded core PDF fonts (WinAnsi-only) and
		// silently drop Czech characters like Ě/Ř. Point those family names at DejaVu Sans instead
		// (already bundled with dompdf, full Unicode), so any imported template using this common
		// font stack renders Czech text correctly.
		$fontMetrics = $dompdf->getFontMetrics();
		$dejaVuDir = dirname(__DIR__, 3) . '/vendor/dompdf/dompdf/lib/fonts/';
		foreach (['Helvetica Neue', 'Helvetica', 'Arial'] as $family) {
			$fontMetrics->registerFont(['family' => $family, 'weight' => 'normal', 'style' => 'normal'], $dejaVuDir . 'DejaVuSans.ttf');
			$fontMetrics->registerFont(['family' => $family, 'weight' => 'bold', 'style' => 'normal'], $dejaVuDir . 'DejaVuSans-Bold.ttf');
			$fontMetrics->registerFont(['family' => $family, 'weight' => 'normal', 'style' => 'italic'], $dejaVuDir . 'DejaVuSans-Oblique.ttf');
			$fontMetrics->registerFont(['family' => $family, 'weight' => 'bold', 'style' => 'italic'], $dejaVuDir . 'DejaVuSans-BoldOblique.ttf');
		}

		// All 5 approved design references use "Nunito, sans-serif" exclusively at weight 900 + italic
		// for prices/logo/badges. Google Fonts only ships Nunito as a variable font (unsupported by
		// Dompdf's font parser), so a static Black Italic cut is bundled here instead — without it,
		// every price/logo/badge silently falls back to the default font, losing the brand look.
		$nunitoBlackItalic = dirname(__DIR__, 3) . '/www/pdf/Nunito-BlackItalic.ttf';
		$fontMetrics->registerFont(['family' => 'Nunito', 'weight' => 900, 'style' => 'italic'], $nunitoBlackItalic);
		$fontMetrics->registerFont(['family' => 'Nunito', 'weight' => 'bold', 'style' => 'italic'], $nunitoBlackItalic);

		// A couple of decorative labels (e.g. "Více druhů") use Nunito at weight 400 — without a
		// registration for that weight, Dompdf silently falls back to a core WinAnsi font that's
		// missing rarer Czech letters like "ů" (renders as "?"), same class of bug as the Helvetica fix above.
		$nunitoRegularItalic = dirname(__DIR__, 3) . '/www/pdf/Nunito-RegularItalic.ttf';
		$fontMetrics->registerFont(['family' => 'Nunito', 'weight' => 'normal', 'style' => 'italic'], $nunitoRegularItalic);

		return $dompdf;
	}
}
