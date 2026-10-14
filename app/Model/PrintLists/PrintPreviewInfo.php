<?php

use App\AdminModule\Presenters\ProductLabelsControl;


/**
 * What a row / preview card shows about one label (Tisk z mobilu rows, Náhled tisku v3 cards):
 * the price as that template prints it, the unit, the unit price, the weight and small tags.
 * $o = overrides of this print (PrintListsService::OVERRIDE_KEYS) or the label's own texts.
 */
final class PrintPreviewInfo
{
	/** @param array<string, mixed> $o */
	public static function of(?ProductsEntity $product, int $branchesId, int $typeId, array $o = []): array
	{
		$isAkceType = ProductLabelsEntity::isAkceType($typeId);
		$price = $product?->getPrice($branchesId);
		$status = ProductLabelsControl::getActionStatus($price);
		$base = isset($o['price']) && $o['price'] !== '' ? (float)$o['price'] : ($price && $price->price !== NULL ? (float)$price->price : NULL);
		$action = isset($o['price_action']) && $o['price_action'] !== '' ? (float)$o['price_action'] : ($price && (float)$price->price_action > 0 ? (float)$price->price_action : NULL);
		$running = $status === ProductLabelsControl::STATUS_RUNNING || isset($o['price_action']);
		if ($isAkceType) {
			// an Akce template prints the action price whenever there is one (CLAUDE.md)
			$sale = $action ?? $base;
			$ref = $action !== NULL ? ($product?->getRefPrice($branchesId) ?: $base) : NULL;
			$refLabel = 'lowest30';
		}
		else {
			$sale = $running && $action !== NULL ? $action : $base;
			$ref = $running && $action !== NULL ? $base : NULL;
			$refLabel = 'regular';
		}
		$unitValue = $product && $sale !== NULL ? $product->getUnitValue($sale) : NULL;
		$weight = $product && (float)$product->weight > 0 && isset(ProductsEntity::UNITS_SHORT[(int)$product->units_id])
			? rtrim(rtrim(number_format((float)$product->weight, 2, ',', ''), '0'), ',') . ' ' . ProductsEntity::UNITS_SHORT[(int)$product->units_id]
			: '';
		$dates = $price && $price->action_from && $action !== NULL
			? $price->action_from->format('d.m.') . '–' . ($price->action_to ? $price->action_to->format('d.m.') : '…')
			: '';
		$tags = [];
		if ($dates && $isAkceType) {
			$tags[] = ['text' => 'Akce ' . $dates, 'kind' => 'akce'];
		}
		if (!empty($o['is_more_species'])) {
			$tags[] = ['text' => 'Více druhů', 'kind' => 'muted'];
		}
		if (trim((string)($o['ingredients'] ?? '')) !== '' || trim((string)($product->ingredients ?? '')) !== '') {
			$tags[] = ['text' => 'Složení ✓', 'kind' => 'ok'];
		}
		if (!empty($o['thawed']) || $product?->isRozmrazeno()) {
			$tags[] = ['text' => '❄ Rozmrazeno', 'kind' => 'info'];
		}

		return [
			'name' => trim((string)($o['name'] ?? '')) ?: (string)($product->name ?? '?'),
			'ean' => (string)($product?->getDefaultCode() ?? ''),
			'weight' => $weight,
			'isAkceType' => $isAkceType,
			'base' => $base,
			'sale' => $sale,
			'ref' => $ref,
			'refLabel' => $refLabel,
			'unit' => (string)(($o['price_unit'] ?? NULL) ?: ($product ? $product->getPriceUnit() : 'Kč')),
			'unitText' => $unitValue ? sprintf('cena za %s %s = %s Kč', $unitValue->unitWeight, $unitValue->unitShort, number_format($unitValue->price, 2, ',', ' ')) : '',
			'dates' => $dates,
			'price' => $price,
			'tags' => $tags,
		];
	}
}
