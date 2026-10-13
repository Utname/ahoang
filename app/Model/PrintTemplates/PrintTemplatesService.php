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
	public const PRESET_VEL2 = 'vel2';
	public const PRESET_VEL3 = 'vel3';
	public const PRESET_FRUIT = 'fruit';
	public const PRESET_VEL2B_AKCE = 'vel2b-akce';
	public const PRESET_VEL2_AKCE = 'vel2-akce';
	public const PRESET_PECIVO = 'pecivo';


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
			ProductLabelsEntity::TYPE_A5 => ['width_mm' => 210, 'height_mm' => 148.5],
			ProductLabelsEntity::TYPE_SIZE_1 => ['width_mm' => 164, 'height_mm' => 41],
			ProductLabelsEntity::TYPE_SIZE_2 => ['width_mm' => 133, 'height_mm' => 41],
			ProductLabelsEntity::TYPE_SIZE_3 => ['width_mm' => 182, 'height_mm' => 56],
			ProductLabelsEntity::TYPE_SIZE_2B => ['width_mm' => 133, 'height_mm' => 52],
			ProductLabelsEntity::TYPE_SIZE_2_AKCE => ['width_mm' => 133, 'height_mm' => 38],
			ProductLabelsEntity::TYPE_PECIVO => ['width_mm' => 150, 'height_mm' => 41],
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
			self::PRESET_VEL2 => [
				'name' => 'Fikko Cena Vel.2',
				'labelTypeId' => ProductLabelsEntity::TYPE_SIZE_2,
				'widthMm' => 133,
				'heightMm' => 41,
				'sourceFile' => 'vel2.latte',
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
			self::PRESET_VEL2B_AKCE => [
				'name' => 'Fikko Cena Vel. 2B Akce',
				'labelTypeId' => ProductLabelsEntity::TYPE_SIZE_2B,
				'widthMm' => 133,
				'heightMm' => 52,
				'sourceFile' => 'vel2b-akce.latte',
			],
			self::PRESET_VEL2_AKCE => [
				'name' => 'Fikko Cena Vel. 2 Akce',
				'labelTypeId' => ProductLabelsEntity::TYPE_SIZE_2_AKCE,
				'widthMm' => 133,
				'heightMm' => 38,
				'sourceFile' => 'vel2-akce.latte',
			],
			self::PRESET_PECIVO => [
				'name' => 'Fikko Cena – tem dải',
				'labelTypeId' => ProductLabelsEntity::TYPE_PECIVO,
				'widthMm' => 150,
				'heightMm' => 41,
				'sourceFile' => 'pecivo.latte',
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
	 *        Unsaved values typed in the product list (dates Y-m-d); applied to a clone, never saved.
	 * @return string rendered HTML, ready for renderPdf() or direct display
	 */
	public function renderHtml(PrintTemplatesEntity $template, ProductsEntity $product, int $branchesId, ?ProductLabelsEntity $label = NULL, ?array $priceOverride = NULL): string
	{
		// Match the specific "fikko-a4" preset markup, not just its physical size — an
		// older 210 × 297 mm template (a different design, pre-dating this preset) must
		// not have its content mangled by the A4-only barcode-stripping surgery below.
		$isA4 = str_contains($template->html_source, 'fikko-a4') || str_contains($template->html_source, 'A4 Akce') || (float)$template->width_mm === 210.0;
		$isFruit = str_contains($template->html_source, 'fikko-fruit');
		$isSize2BAkce = str_contains($template->html_source, 'fikko-vel2b-akce') || ((float)$template->width_mm === 133.0 && (float)$template->height_mm === 52.0);
		$isPecivo = str_contains($template->html_source, 'fikko-pecivo') || ((float)$template->width_mm === 150.0 && (float)$template->height_mm === 41.0) || (int)($template->product_label_type_id ?? 0) === ProductLabelsEntity::TYPE_PECIVO;
		$isSize2 = (float)$template->width_mm === 133.0;
		$isVel1 = (float)$template->width_mm === 164.0 || ((float)$template->width_mm === 150.0 && !$isPecivo);
		$isSize2B = $this->isSize2Template($template);
		$isA5 = $this->isA5Template($template);
		$price = $product->getPriceWithLog($branchesId);
		$rawPrice = $product->getPrice($branchesId);
		if ($price && $rawPrice && (float)$price->price <= (float)$price->price_action && (float)$rawPrice->price > (float)$price->price_action) {
			$price = clone $price;
			$price->price = $rawPrice->price;
		}
		if ($priceOverride && $price) {
			$price = $this->applyPriceOverride($price, $priceOverride);
		}
		$ean = trim($product->getDefaultCode() ?: '');
		// Old rule (types outside TYPES_ARR): a queued label is a snapshot, its
		// sale_percent keeps the action price on it after the action date ends.
		$hasPriceAction = $price && (float)$price->price_action > 0 && (float)$price->price > (float)$price->price_action;
		// With typed-but-unsaved prices ($priceOverride) the queued label's stored
		// sale_percent is stale, so the discount is worked out from the new prices.
		$usesStoredLabelDiscount = !$priceOverride && $label && (float)$label->sale_percent > 0 && $price && (float)$price->price_action > 0;
		$isDiscounted = ($price && !empty($price->is_valid_action)) || $usesStoredLabelDiscount;
		// The chosen template decides the layout ("Admin - Produkty v2"): an Akce
		// template prints -% and the 30-day low whenever there is an action price,
		// a std template ("Naše cena, bez %") never does and compares only during
		// the action dates; the special ones apply the 30-day rule automatically, also
		// only during the action dates. Types outside TYPES_ARR keep the old rule.
		$labelTypeId = (int)($label?->type_id ?: ($template->product_label_type_id ?? 0));
		$typeGroup = ProductLabelsEntity::TYPES_ARR[$labelTypeId]['group'] ?? NULL;
		if ($typeGroup === ProductLabelsEntity::GROUP_AKCE) {
			$isDiscounted = $hasPriceAction;
		}
		elseif ($typeGroup !== NULL) {
			$usesStoredLabelDiscount = FALSE;
			$isDiscounted = $price && !empty($price->is_valid_action);
			$hasPriceAction = $isDiscounted;
		}
		$finalPrice = ($isDiscounted || $hasPriceAction) ? (float)$price->price_action : (float)($price->price ?? 0);
		$basePrice = ($isDiscounted || $hasPriceAction) ? (float)($price->price ?? 0) : 0.0;
		// 30-day rule (CACH-LAM-CODE.md §3/§5): the "Nejnižší cena za 30 dní" value and
		// the % come from the 30-day low (getRefPrice(), product_prices_log). Without a
		// valid low the label falls back to the "Běžná cena" comparison; an Akce template
		// then shows the regular price as the low.
		$refPrice30 = $isDiscounted ? $product->getRefPrice($branchesId) : NULL;
		$hasValidRef30 = $refPrice30 !== NULL && $refPrice30 > $finalPrice;
		$isAkce30 = match ($typeGroup) {
			ProductLabelsEntity::GROUP_AKCE => $isDiscounted,
			ProductLabelsEntity::GROUP_STD => FALSE,
			default => $usesStoredLabelDiscount || ($isDiscounted && $hasValidRef30),
		};
		$lowestPrice30 = $hasValidRef30 ? $refPrice30 : $basePrice;
		// "Běžná cena" on a comparison label is the regular price saved (or typed) in the row.
		// During an action getPriceWithLog() swaps in the price-log reference, the shop's own
		// earlier price, which belongs only in "Nejnižší cena za 30 dní" (ZAKON-30-DNI).
		if (!$isAkce30 && $basePrice > 0) {
			$regularPrice = (float)(($priceOverride['price'] ?? NULL) ?: ($rawPrice->price ?? 0));
			if ($regularPrice > 0) {
				$basePrice = $regularPrice;
			}
		}
		$salePercent = match (TRUE) {
			!$priceOverride && $label && (float)$label->sale_percent > 0 => (float)$label->sale_percent,
			$hasValidRef30 => floor((($refPrice30 - $finalPrice) / $refPrice30) * 100),
			$isAkce30 && $basePrice > 0 => floor((($basePrice - $finalPrice) / $basePrice) * 100),
			default => 0.0,
		};
		$hasSaleRibbon = $isAkce30 && $salePercent > 0;
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
		// action dates only on a label that prints the action; a custom text always
		$validityText = $hideValidity ? NULL : ($label?->sale_text ?: (($isDiscounted || $isAkce30) ? $this->formatValidity($price) : NULL));
		// the newer columns are read through the row: Nette's column auto-detection
		// leaves them out of an entity's copied values
		$ingredients = trim((string)($label?->getRow()->ingredients ?? '')) ?: trim((string)($product->ingredients ?? ''));
		$isThawed = $product->isRozmrazeno() || !empty($label?->thawed);
		// "Cena za" (products.price_unit, per label override): plain "Kč" keeps
		// each template's own "Kč / 1 ks" text.
		$priceUnit = ($label?->getRow()->price_unit ?? NULL) ?: $product->getPriceUnit();
		// Header chosen per label; the narrow templates print it word by word in their
		// brand column ("Fikko / Cena"), at most three lines.
		$headerText = $label?->getHeader()?->name ?: 'Fikko Cena';
		$headerWords = preg_split('/\s+/u', trim(rtrim($headerText, ' !'))) ?: [];
		$headerWords = $headerWords && $headerWords[0] !== '' ? $headerWords : ['Fikko', 'Cena'];
		if (count($headerWords) > 3) {
			$headerWords = [$headerWords[0], $headerWords[1], implode(' ', array_slice($headerWords, 2))];
		}
		$priceUnitText = match ($priceUnit) {
			'Kč/ks' => $currencySymbol . ' / 1 ks',
			'Kč/balení' => $currencySymbol . ' / 1 balení',
			'Kč/kg' => $currencySymbol . ' / 1 kg',
			'Kč/100 g' => $currencySymbol . ' / 100 g',
			'Kč/l' => $currencySymbol . ' / 1 l',
			default => NULL,
		};

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
			'ingredients' => $ingredients,
			'isThawed' => $isThawed,
			'headerText' => $headerText,
			'headerWords' => $headerWords,
			'headerWordMax' => max(array_map('mb_strlen', $headerWords)),
			'priceUnitText' => $priceUnitText,
			'labelName' => $labelName,
			'labelNameLength' => function_exists('mb_strlen') ? mb_strlen($labelName) : strlen($labelName),
			'price' => $price,
			'basePrice' => $basePrice,
			'basePriceText' => number_format($basePrice, 2, ',', ' '),
			'lowestPrice30Text' => number_format($lowestPrice30, 2, ',', ' '),
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
			'isAkce30' => $isAkce30,
			'currencySymbol' => $currencySymbol,
			'unit' => $unit,
			'unitText' => $unit ? sprintf('%s / %s %s', number_format($unit->price, 2, ',', ' '), $unit->unitWeight, $unit->unitShort) : NULL,
			'country' => $country,
			'validityText' => $validityText,
			// a text from the editor ("Akce platí do…" / Text na štítku) is a whole sentence
			// "Akce platí do 06.10.2026 nebo do vyprodání zásob" takes the end date only (design A4/A5 Akce)
			// "Platnost 10. 10. – 15. 10." (A4 comparison) always from the action dates, never the chosen text
			'validityRange' => !$hideValidity && $isDiscounted ? ($this->formatValidity($price) ?: NULL) : NULL,
			'validityUntil' => !$hideValidity && ($isDiscounted || $isAkce30) && $price?->action_to ? $price->action_to->format('d.m.Y') : NULL,
			'validityCustom' => !$hideValidity && trim((string)($label?->sale_text ?? '')) !== '',
			'ean' => $ean,
			'eanText' => $ean !== '' ? EanBarcodeHelper::getDigits($ean) : '',
			'bars' => match (TRUE) {
				$ean === '' => [],
				$isA4 => EanBarcodeHelper::getBars($ean, 0.38),
				str_contains($template->html_source, 'fikko-vel3') => EanBarcodeHelper::getBars($ean, 0.33),
				$isSize2BAkce => EanBarcodeHelper::getBars($ean, 0.33),
				$isPecivo => EanBarcodeHelper::getBars($ean, 0.24),
				$isSize2 => EanBarcodeHelper::getBars($ean, 0.28),
				$isVel1 => EanBarcodeHelper::getBars($ean, 0.28),
				$isA5 => EanBarcodeHelper::getBars($ean, 0.42),
				$isFruit => EanBarcodeHelper::getBars($ean, 0.20),
				default => [],
			},
			'template' => $template,
			'logoDataUri' => $this->imageAsDataUri(WWW_DIR . '/images/fikko-logo-color.png', 'image/png'),
			'brushDataUri' => $this->imageAsDataUri(WWW_DIR . '/images/brush-20.png', 'image/png'),
			// Dompdf renders the near-black brush-20.png at half height (or white) in large
			// boxes; this navy-tinted copy renders correctly. Use it via <img>.
			'ribbonBadgeBrushDataUri' => $this->imageAsDataUri(WWW_DIR . '/images/brush-20-ribbon-badge.png', 'image/png'),
			// Same artwork flattened onto white (no alpha): Dompdf's CSS background-image
			// path drops 8-bit RGBA PNGs, so use this one for background-image.
			'ribbonBadgeBrushFlatDataUri' => $this->imageAsDataUri(WWW_DIR . '/images/brush-20-ribbon-badge-flat.png', 'image/png'),
			// Dompdf does not render inline <svg> markup at all (confirmed: even a bare filled
			// <rect> inside <svg> paints nothing), so the save-badge's piggy-bank mascot from the
			// original designs is pre-rendered to PNG and embedded as an image instead, the same
			// way the logo/brush already are.
			// The origin flag uses the country's own uploaded image (countries.image) via the same
			// getImageDir() lookup the old TCPDF renderer used, not a static placeholder — so it
			// actually matches the product's real origin country instead of always showing Czech.
			'flagIconDataUri' => $this->countryFlagImages($country)['round'],
			// the rectangular flags (Vel. 2 Akce / Složení, Pečivo)
			'flagRectDataUri' => $this->countryFlagImages($country)['rect'],
			'snowflakeIconDataUri' => $this->imageAsDataUri(WWW_DIR . '/images/snowflake-icon.png', 'image/png'),
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
	public function applyPriceOverride(ProductPricesEntity $price, array $priceOverride): ProductPricesEntity
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
			$css = '.ean-barcode{position:absolute;left:2.5mm;bottom:6.8mm;width:30mm;height:9mm;overflow:hidden}';
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
		if (!$price) {
			return '';
		}

		$from = $price->action_from?->format('j. n.');
		$to = $price->action_to?->format('j. n.');

		if (!$from && !$to) {
			return '';
		}

		if ($from && $to && $from === $to) {
			return $from;
		}

		return trim(implode(' – ', array_filter([$from, $to])));
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
	/**
	 * The country images are square icons (128x128 or 16x16) with the flag in the
	 * middle and transparent bands above and below it; stretched into a label's
	 * circle or rectangle those bands showed as white gaps. Trimmed to the flag:
	 * 'rect' for the rectangular flags, 'round' centre-cropped to a square so it
	 * fills a circle. Falls back to the original file when GD cannot read it.
	 *
	 * @return array{round: string, rect: string}
	 */
	private function countryFlagImages(?CountriesEntity $country): array
	{
		static $cache = [];
		$image = (string)($country?->image ?? '');
		if ($image === '') {
			return ['round' => '', 'rect' => ''];
		}
		if (isset($cache[$image])) {
			return $cache[$image];
		}

		$path = FS_DIR . '/images/' . ImagesService::getImageDir($image) . '/' . $image;
		$mime = match (strtolower((string)pathinfo($image, PATHINFO_EXTENSION))) {
			'jpg', 'jpeg' => 'image/jpeg',
			'gif' => 'image/gif',
			'webp' => 'image/webp',
			default => 'image/png',
		};
		$original = $this->imageAsDataUri($path, $mime);
		$source = is_file($path) && function_exists('imagecreatefromstring') ? @imagecreatefromstring((string)file_get_contents($path)) : FALSE;
		if (!$source) {
			return $cache[$image] = ['round' => $original, 'rect' => $original];
		}

		// bounding box of the opaque pixels
		$w = imagesx($source);
		$h = imagesy($source);
		$x1 = $w; $y1 = $h; $x2 = -1; $y2 = -1;
		for ($y = 0; $y < $h; $y++) {
			for ($x = 0; $x < $w; $x++) {
				$alpha = imagecolorsforindex($source, imagecolorat($source, $x, $y))['alpha'];
				if ($alpha < 20) {
					$x1 = min($x1, $x); $x2 = max($x2, $x);
					$y1 = min($y1, $y); $y2 = max($y2, $y);
				}
			}
		}
		if ($x2 < 0) {
			return $cache[$image] = ['round' => $original, 'rect' => $original];
		}
		// half-transparent anti-aliased edge rows left a light line along the circle
		if ($x2 - $x1 > 40 && $y2 - $y1 > 40) {
			$x1++; $y1++; $x2--; $y2--;
		}
		$fw = $x2 - $x1 + 1;
		$fh = $y2 - $y1 + 1;
		$side = min($fw, $fh);

		return $cache[$image] = [
			'rect' => $this->pngDataUri($source, $x1, $y1, $fw, $fh, (int)round(120 * $fw / $fh), 120),
			'round' => $this->pngDataUri($source, $x1 + intdiv($fw - $side, 2), $y1 + intdiv($fh - $side, 2), $side, $side, 120, 120),
		];
	}


	/** A region of a GD image, resampled to $outW x $outH, as a PNG data URI. */
	private function pngDataUri(\GdImage $source, int $x, int $y, int $w, int $h, int $outW, int $outH): string
	{
		$out = imagecreatetruecolor($outW, $outH);
		imagealphablending($out, FALSE);
		imagesavealpha($out, TRUE);
		imagefill($out, 0, 0, imagecolorallocatealpha($out, 255, 255, 255, 127));
		// a tiny icon (16 px) is scaled pixel by pixel: smoothing blurred its stripes
		if ($w < 40 || $h < 40) {
			imagecopyresized($out, $source, 0, 0, $x, $y, $outW, $outH, $w, $h);
		}
		else {
			imagecopyresampled($out, $source, 0, 0, $x, $y, $outW, $outH, $w, $h);
		}
		ob_start();
		imagepng($out);

		return 'data:image/png;base64,' . base64_encode((string)ob_get_clean());
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
		$layout = $this->sheetLayout($template);
		if ($layout === 'a5') {
			return $this->renderDompdf($this->renderA5Page($html), 210, 297)->output();
		}
		if (is_array($layout)) {
			return $this->renderDompdf($this->renderStackedSheet([$html], $layout['w'], $layout['h'], 8.0, 8.0, $layout['gap'], $layout['cut']), 210, 297)->output();
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
		$queueKey = NULL;
		$queueJobs = [];

		// All labels share one Dompdf document, and the templates reuse class names
		// (.name, .brand, .save …) with different positions: each template's CSS is
		// scoped to its own wrapper so a later template cannot restyle an earlier one.
		$render = fn(array $j): string => $this->scopeTemplateHtml(
			$this->renderHtml($j['template'], $j['label']->getProduct(), $branchesId, $j['label'], $j['priceOverride'] ?? NULL),
			'pt-t'.(int)$j['template']->id,
		);

		$flushQueue = function () use (&$pages, &$queueKey, &$queueJobs, $render) {
			if ($queueJobs) {
				$layout = $this->sheetLayout($queueJobs[0]['template']);
				if ($layout === 'a5') {
					foreach (array_chunk($queueJobs, 2) as $pair) {
						$pages[] = $this->renderA5Page($render($pair[0]), isset($pair[1]) ? $render($pair[1]) : NULL);
					}
				}
				elseif (is_array($layout)) {
					foreach (array_chunk($queueJobs, $layout['perPage']) as $chunk) {
						$pages[] = $this->renderStackedSheet(array_map($render, $chunk), $layout['w'], $layout['h'], 8.0, 8.0, $layout['gap'], $layout['cut']);
					}
				}
				else {
					foreach ($queueJobs as $j) {
						$pages[] = $render($j);
					}
				}
			}
			$queueJobs = [];
			$queueKey = NULL;
		};

		foreach ($jobs as $job) {
			if (!$job['label']->getProduct()) {
				continue;
			}

			// Labels of the same size share a sheet, whichever template they use.
			$layout = $this->sheetLayout($job['template']);
			$key = is_array($layout) ? $layout['w'] . 'x' . $layout['h'] : ($layout ?? 'page');
			if ($queueKey !== NULL && $queueKey !== $key) {
				$flushQueue();
			}

			$queueKey = $key;
			$queueJobs[] = $job;
		}

		$flushQueue();

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


	/**
	 * Rendered label HTML as its (scoped) style blocks followed by the label markup in
	 * a <div class="$scope"> wrapper, ready for renderStackedSheet() / renderA5Page().
	 */
	private function scopeTemplateHtml(string $html, string $scope): string
	{
		preg_match_all('#<style\b[^>]*>(.*?)</style>#is', $html, $styles);
		$body = preg_match('#<body\b[^>]*>(.*)</body>#is', $html, $match)
			? $match[1]
			: preg_replace('#<style\b[^>]*>.*?</style>#is', '', $html);
		$body = preg_replace('#<style\b[^>]*>.*?</style>#is', '', (string)$body);

		$css = '';
		foreach ($styles[1] as $style) {
			$css .= '<style>'.$this->scopeCss($style, $scope).'</style>';
		}

		return $css.'<div class="'.$scope.'">'.$body.'</div>';
	}


	/**
	 * Prefixes every selector with ".$scope ". The templates' CSS has flat rules only;
	 * @page / @font-face blocks stay as they are, html/body rules apply to the wrapper.
	 */
	private function scopeCss(string $css, string $scope): string
	{
		$css = preg_replace('#/\*.*?\*/#s', '', $css) ?? $css;

		return preg_replace_callback('#([^{}]+)\{([^{}]*)\}#', function (array $rule) use ($scope): string {
			$prelude = trim($rule[1]);
			if ($prelude === '' || $prelude[0] === '@') {
				return $rule[0];
			}
			$selectors = array_map(static function (string $selector) use ($scope): string {
				$selector = trim($selector);
				if (preg_match('#^(html|body)\b(.*)$#i', $selector, $m)) {
					return '.'.$scope.$m[2];
				}

				return '.'.$scope.' '.$selector;
			}, explode(',', $prelude));

			return implode(', ', $selectors).' {'.$rule[2].'}';
		}, $css) ?? $css;
	}


	/**
	 * Where a template sits on the A4 sheet: 'a5' (two-up), stacked strips
	 * ['w', 'h', 'gap', 'perPage'] in mm, or NULL for a page of its own (A4, fruit).
	 *
	 * @return 'a5'|array{w: float, h: float, gap: float, perPage: int}|null
	 */
	private function sheetLayout(PrintTemplatesEntity $template): string|array|null
	{
		if ($this->isA5Template($template)) {
			return 'a5';
		}

		$w = (float)$template->width_mm;
		$h = (float)$template->height_mm;
		if ($w >= 200 || $w < 100 || $h <= 0) {
			return NULL;
		}

		$gap = match (TRUE) {
			$h >= 56 => 6.0,
			$h <= 38 || ($w === 133.0 && $h <= 41) => 3.0,
			default => 4.0,
		};
		// 8mm top margin, at least 5mm left at the bottom
		$perPage = max(1, (int)floor((297 - 8 - 5 + $gap) / ($h + $gap)));

		// labels with their own dashed frame are cut along it: no extra line between them;
		// frameless ones (Vel. 3) get the dashed cut line of their design
		$cut = !str_contains((string)$template->html_source, 'dashed');

		return ['w' => $w, 'h' => $h, 'gap' => $gap, 'perPage' => $perPage, 'cut' => $cut];
	}


	private function isSize2BTemplate(PrintTemplatesEntity $template): bool
	{
		return (float)$template->width_mm === 133.0 && ((float)$template->height_mm === 52.0 || str_contains($template->html_source, 'fikko-vel2b') || (int)($template->product_label_type_id ?? 0) === ProductLabelsEntity::TYPE_SIZE_2B);
	}


	private function isSize2Template(PrintTemplatesEntity $template): bool
	{
		return (float)$template->width_mm === 133.0 && !$this->isSize2BTemplate($template);
	}


	private function isA5Template(PrintTemplatesEntity $template): bool
	{
		return ((float)$template->width_mm === 200.0 && (float)$template->height_mm === 138.5)
			|| ((float)$template->width_mm === 210.0 && (float)$template->height_mm === 148.5)
			|| in_array((int)($template->product_label_type_id ?? 0), [ProductLabelsEntity::TYPE_A5, ProductLabelsEntity::TYPE_A5_AKCE], TRUE);
	}


	/**
	 * A5 is a two-up A4 stock: up to two 210 × 148.5 mm labels stacked on one A4 portrait sheet,
	 * separated by a thin dashed cut line with breathing room.
	 */
	private function renderA5Page(string $html1, ?string $html2 = NULL): string
	{
		preg_match_all('#<style\b[^>]*>.*?</style>#is', $html1, $styleMatches1);
		preg_match('#<body\b[^>]*>(.*)</body>#is', $html1, $bodyMatch1);
		$labelHtml1 = $bodyMatch1[1] ?? (preg_replace('#<style\b[^>]*>.*?</style>#is', '', $html1) ?? $html1);
		$styles1 = implode('', $styleMatches1[0] ?? []);

		$secondHtml = '';
		$styles2 = '';
		if ($html2 !== NULL) {
			preg_match_all('#<style\b[^>]*>.*?</style>#is', $html2, $styleMatches2);
			preg_match('#<body\b[^>]*>(.*)</body>#is', $html2, $bodyMatch2);
			$labelHtml2 = $bodyMatch2[1] ?? (preg_replace('#<style\b[^>]*>.*?</style>#is', '', $html2) ?? $html2);
			$styles2 = implode('', $styleMatches2[0] ?? []);
			$secondHtml = '<div class="a5-divider"><span class="a5-divider-line"></span></div>'
				. '<div class="a5-label second">' . $labelHtml2 . '</div>';
		}

		return '<style>'
			. '@page{margin:0}body{margin:0!important;padding:0!important;width:210mm!important}'
			. '.a5-sheet{position:relative;width:210mm;height:297mm;overflow:hidden}'
			. '.a5-label{position:absolute;left:0;width:210mm;height:148.5mm;overflow:visible}'
			. '.a5-label.first{top:0}.a5-label.second{top:148.5mm}'
			. '.a5-divider{position:absolute;top:148.5mm;left:0;width:210mm;z-index:5}'
			. '.a5-divider-line{display:block;width:100%;border-top:0.3mm dashed #555}'
			. '</style>' . $styles1 . $styles2
			. '<div class="a5-sheet">'
			. '<div class="a5-label first">' . $labelHtml1 . '</div>'
			. $secondHtml
			. '</div>';
	}


	/**
	 * Universal stacked multi-label sheet renderer for A4 page.
	 *
	 * @param list<string> $labelHtmls Array of rendered HTML for each label on this sheet
	 * @param float $widthMm Label width in mm
	 * @param float $heightMm Label height in mm
	 * @param float $leftMm Left offset in mm
	 * @param float $topStartMm Top offset for 1st item in mm
	 * @param float $gapMm Gap between consecutive items in mm
	 * @param bool $cutLines dashed cut line between items (labels without a frame of their own)
	 * @return string Complete HTML page string
	 */
	private function renderStackedSheet(
		array $labelHtmls,
		float $widthMm,
		float $heightMm,
		float $leftMm = 8.0,
		float $topStartMm = 8.0,
		float $gapMm = 4.0,
		bool $cutLines = TRUE
	): string {
		$allStyles = [];
		$labelItemsHtml = '';
		$step = $heightMm + $gapMm;

		foreach ($labelHtmls as $index => $html) {
			preg_match_all('#<style\b[^>]*>.*?</style>#is', $html, $styleMatches);
			preg_match('#<body\b[^>]*>(.*)</body>#is', $html, $bodyMatch);
			$labelBody = $bodyMatch[1] ?? (preg_replace('#<style\b[^>]*>.*?</style>#is', '', $html) ?? $html);
			foreach ($styleMatches[0] ?? [] as $st) {
				$allStyles[$st] = TRUE;
			}

			$currentTop = $topStartMm + ($index * $step);

			if ($index > 0 && $cutLines) {
				$cutTop = $currentTop - ($gapMm / 2);
				$labelItemsHtml .= sprintf(
					'<div class="stacked-divider" style="top:%.2fmm;left:%.2fmm;width:%.2fmm;"><span class="stacked-divider-line"></span></div>',
					$cutTop,
					$leftMm,
					$widthMm
				);
			}

			$labelItemsHtml .= sprintf(
				'<div class="stacked-label" style="top:%.2fmm;left:%.2fmm;width:%.2fmm;height:%.2fmm;">%s</div>',
				$currentTop,
				$leftMm,
				$widthMm,
				$heightMm,
				$labelBody
			);
		}

		return '<style>'
			. '@page{margin:0}body{margin:0!important;padding:0!important;width:210mm!important}'
			. '.stacked-sheet{position:relative;width:210mm;height:297mm;overflow:hidden}'
			. '.stacked-label{position:absolute;overflow:visible}'
			. '.stacked-divider{position:absolute;z-index:5}'
			. '.stacked-divider-line{display:block;width:100%;border-top:0.3mm dashed #777}'
			. '</style>'
			. implode('', array_keys($allStyles))
			. '<div class="stacked-sheet">'
			. $labelItemsHtml
			. '</div>';
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
