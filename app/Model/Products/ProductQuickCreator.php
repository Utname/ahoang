<?php

use Nette\Database\Explorer;
use Nette\Http\FileUpload;
use Nette\Utils\Strings;

/**
 * A product created from the phone app when a scanned code is not known yet, with the data of the
 * admin product card: code, name (all languages – the admin translates later), category, country,
 * package size and unit price basis, label notes, ingredients, thawed, consumption type, short
 * description, a photo, and the price / action for the branch (or all the admin's branches) through
 * ProductPriceSaver, so the price log for the 30-day rule is written too.
 */
class ProductQuickCreator
{
	public function __construct(
		private Explorer $db,
		private PrintListsService $printLists,
		private ProductPriceSaver $priceSaver,
	) {
	}


	/**
	 * @param array<string, mixed> $d form values of the phone app
	 * @param list<int> $branchIds branches that get the price (the first one is the current branch)
	 * @return int the new product id
	 * @throws \InvalidArgumentException with a translation key (admin.mob.new.err.*)
	 */
	public function create(array $d, array $branchIds, ?FileUpload $photo = NULL): int
	{
		$text = static fn(string $k, int $max = 255): string => mb_substr(trim(preg_replace('#[ \t]+#u', ' ', (string)($d[$k] ?? ''))), 0, $max);
		$number = static function (string $k) use ($d): ?float {
			$v = str_replace([',', ' '], ['.', ''], trim((string)($d[$k] ?? '')));
			return $v === '' || !is_numeric($v) ? NULL : (float)$v;
		};

		$code = $text('code', 32);
		$name = $text('name', 250);
		if ($name === '') {
			throw new \InvalidArgumentException('admin.mob.new.err.name');
		}
		if ($code === '' || !preg_match('#^[\w.\-/]+$#u', $code)) {
			throw new \InvalidArgumentException('admin.mob.new.err.code');
		}
		if ($this->printLists->findProductId($code)) {
			throw new \InvalidArgumentException('admin.mob.new.err.exists');
		}
		$price = $number('price');
		$priceAction = $number('price_action');
		if (($price !== NULL && ($price <= 0 || $price > 1000000)) || ($priceAction !== NULL && ($priceAction <= 0 || ($price !== NULL && $priceAction >= $price)))) {
			throw new \InvalidArgumentException('admin.mob.new.err.price');
		}
		$priceUnit = in_array($d['price_unit'] ?? NULL, ProductsEntity::PRICE_UNITS, TRUE) ? $d['price_unit'] : 'Kč';
		$countryId = (int)($d['country'] ?? 0);
		$countryId = $countryId && $this->db->table('countries')->get($countryId) ? $countryId : NULL;
		$categoryId = (int)($d['category'] ?? 0);
		$categoryId = $categoryId && $this->db->table('categories')->where('id', $categoryId)->where('pos_level > 0')->count('*') ? $categoryId : NULL;
		$unitsId = array_key_exists((int)($d['units_id'] ?? 0), ProductsEntity::UNITS) ? (int)($d['units_id'] ?? 0) : 0;
		$defaultUnitsId = ($d['default_units_id'] ?? '') !== '' && array_key_exists((int)$d['default_units_id'], ProductsEntity::UNITS) ? (int)$d['default_units_id'] : NULL;
		$consumption = (int)($d['consumption_types_id'] ?? 0);
		$consumption = $consumption && array_key_exists($consumption, ProductLabelsEntity::CONSUMPTION_TYPES) ? $consumption : NULL;
		$weight = $number('weight');
		$defaultWeight = $number('default_units_weight');

		$image = NULL;
		if ($photo && $photo->isOk()) {
			if (!$photo->isImage()) {
				throw new \InvalidArgumentException('admin.mob.new.err.photo');
			}
			$image = ImagesService::saveImageUpload($photo) ?: NULL;
		}

		$this->db->beginTransaction();
		try {
			$isEan = preg_match('#^\d{8,14}$#', $code) === 1;
			$product = $this->db->table('products')->insert([
				// digits = an EAN / UPC, anything else is the shop's own code
				'code_ean' => $isEan ? $code : NULL,
				'code' => $isEan ? NULL : $code,
				'tags' => '',
				'label_note_1' => $text('label_note_1'),
				'label_note_2' => $text('label_note_2'),
				'label_note_3' => $text('label_note_3'),
				'ingredients' => trim((string)($d['ingredients'] ?? '')),
				'units_id' => $unitsId,
				'weight' => $weight && $weight > 0 ? $weight : NULL,
				'default_units_id' => $defaultUnitsId,
				'default_units_weight' => $defaultUnitsId !== NULL && $defaultWeight && $defaultWeight > 0 ? $defaultWeight : NULL,
				'consumption_types_id' => $consumption,
				'rozmrazeno' => !empty($d['rozmrazeno']) ? 1 : 0,
				'country_origin' => $countryId ? (string)$countryId : NULL,
				'price_unit' => $priceUnit,
				'images' => $image ? json_encode(['file' => $image]) : NULL,
			]);
			$id = (int)$product->id;

			$slug = Strings::webalize($name) ?: 'produkt';
			if ($this->db->table('products_mld')->where('slug', $slug)->count('*')) {
				$slug .= '-' . $id;
			}
			$perex = trim((string)($d['perex'] ?? ''));
			$rows = [];
			foreach ($this->db->table('languages')->fetchPairs('id', 'id') as $languageId) {
				$rows[] = ['products_id' => $id, 'languages_id' => $languageId, 'name' => $name, 'slug' => $slug,
					'perex' => $perex !== '' ? $perex : NULL, 'is_active' => 1];
			}
			$this->db->table('products_mld')->insert($rows);
			if ($categoryId) {
				$this->db->table('products_categories_rel')->insert(['products_id' => $id, 'categories_id' => $categoryId]);
			}
			$this->db->commit();
		}
		catch (\Throwable $e) {
			$this->db->rollBack();
			throw $e;
		}

		if ($price !== NULL) {
			$from = $this->dmy($d['action_from'] ?? NULL);
			$to = $this->dmy($d['action_to'] ?? NULL);
			foreach (array_values(array_unique(array_map('intval', $branchIds))) as $branchId) {
				$this->priceSaver->save($id, $branchId, [$branchId], NULL, $price, $priceAction, $priceAction !== NULL ? $from : NULL, $priceAction !== NULL ? $to : NULL, $priceUnit);
			}
		}

		return $id;
	}


	/** Y-m-d (date input) → d.m.Y as ProductPriceSaver takes it */
	private function dmy($iso): ?string
	{
		return preg_match('#^(\d{4})-(\d{2})-(\d{2})$#', (string)$iso, $m) ? "$m[3].$m[2].$m[1]" : NULL;
	}
}
