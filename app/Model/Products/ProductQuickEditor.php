<?php

use Nette\Database\Explorer;
use Nette\Http\FileUpload;

/**
 * Product detail of the phone app ("Admin – tisk štítků mobil", Mobil – detail produktu): reads and
 * saves the product card – categories, name, code, active, photo, content, origin, the price of the
 * branch (ProductPriceSaver, so the 30-day log is written), label notes, ingredients with the
 * allergens, thawed, nutrition values, e-shop description and SEO. Texts are the Czech ones.
 * Allergens have no column: they are the closing "Obsahuje alergeny: …." sentence of the ingredients
 * (as the design writes them), so the labels print them with Složení.
 */
class ProductQuickEditor
{
	/** the 14 EU allergens (Regulation 1169/2011, annex II), as the design lists them */
	public const ALLERGENS = ['Lepek', 'Korýši', 'Vejce', 'Ryby', 'Arašídy', 'Sója', 'Mléko', 'Skořápkové plody',
		'Celer', 'Hořčice', 'Sezam', 'Oxid siřičitý', 'Vlčí bob', 'Měkkýši'];

	private const ALLERGEN_SENTENCE = '#\s*Obsahuje alergeny:\s*([^.]*)\.?\s*$#u';
	private const LANG = 'cs';


	public function __construct(
		private Explorer $db,
		private PrintListsService $printLists,
		private ProductPriceSaver $priceSaver,
		private ProductsRepository $products,
	) {
	}


	/** What the detail screen edits. */
	public function detail(int $productsId, int $branchesId): ?array
	{
		$product = $this->products->findEntity($productsId);
		$row = $this->db->table('products')->get($productsId);
		if (!$product || !$row) {
			return NULL;
		}
		$mld = $this->db->table('products_mld')->where('products_id', $productsId)->where('languages_id', self::LANG)->fetch();
		[$ingredients, $allergens] = self::splitAllergens((string)$row->ingredients);
		$price = $product->getPrice($branchesId);
		$values = $this->db->table('product_nutrition_values')->where('products_id', $productsId)->fetchPairs('nutrition_values_id', 'value');
		$nutrition = [];
		foreach ($this->db->table('nutrition_values')->order('pos_order') as $nv) {
			$nutrition[] = ['id' => (int)$nv->id, 'name' => $nv->name, 'unit' => $nv->unit, 'value' => (string)($values[$nv->id] ?? '')];
		}

		return [
			'id' => $productsId,
			'name' => (string)($mld->name ?? $product->name),
			'code' => (string)($row->code_ean ?: $row->code),
			'active' => (bool)($mld->is_active ?? 1),
			'categories' => array_map('intval', array_values($this->db->table('products_categories_rel')->where('products_id', $productsId)->fetchPairs(NULL, 'categories_id'))),
			'weight' => $row->weight !== NULL && (float)$row->weight > 0 ? rtrim(rtrim(number_format((float)$row->weight, 3, '.', ''), '0'), '.') : '',
			'unitsId' => (int)$row->units_id,
			'country' => (int)$row->country_origin,
			'priceUnit' => $product->getPriceUnit(),
			'base' => $price && $price->price !== NULL ? (float)$price->price : NULL,
			'action' => $price && (float)$price->price_action > 0 ? (float)$price->price_action : NULL,
			'from' => $price?->action_from?->format('Y-m-d'),
			'to' => $price?->action_to?->format('Y-m-d'),
			'notes' => [(string)$row->label_note_1, (string)$row->label_note_2, (string)$row->label_note_3],
			'ingredients' => $ingredients,
			'allergens' => $allergens,
			'thawed' => (bool)$row->rozmrazeno,
			'nutrition' => $nutrition,
			'nutritionRef' => $row->nutrition_reference_amount !== NULL ? rtrim(rtrim((string)$row->nutrition_reference_amount, '0'), '.') . ' ' . $row->nutrition_reference_unit : '100 g',
			'perex' => (string)($mld->perex ?? ''),
			'metaTitle' => (string)($mld->meta_title ?? ''),
			'metaDesc' => (string)($mld->meta_desc ?? ''),
			'slug' => (string)($mld->slug ?? ''),
		];
	}


	/**
	 * @param array<string, mixed> $d form values of the detail screen
	 * @param list<int> $priceBranchIds branches ProductPriceSaver may touch (the admin's)
	 * @throws \InvalidArgumentException with a translation key (admin.mob.new.err.*)
	 */
	public function save(int $productsId, array $d, int $branchesId, array $priceBranchIds, ?FileUpload $photo = NULL): void
	{
		$row = $this->db->table('products')->get($productsId);
		if (!$row) {
			throw new \InvalidArgumentException('admin.mob.new.err.code');
		}
		$text = static fn(string $k, int $max = 255): string => mb_substr(trim(preg_replace('#[ \t]+#u', ' ', (string)($d[$k] ?? ''))), 0, $max);
		$number = static function (string $k) use ($d): ?float {
			$v = str_replace([',', ' '], ['.', ''], trim((string)($d[$k] ?? '')));
			return $v === '' || !is_numeric($v) ? NULL : (float)$v;
		};

		$name = $text('name', 250);
		if ($name === '') {
			throw new \InvalidArgumentException('admin.mob.new.err.name');
		}
		$code = $text('code', 32);
		if ($code === '' || !preg_match('#^[\w.\-/]+$#u', $code)) {
			throw new \InvalidArgumentException('admin.mob.new.err.code');
		}
		$other = $this->printLists->findProductId($code);
		if ($other && $other !== $productsId) {
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
		$unitsId = array_key_exists((int)($d['units_id'] ?? 0), ProductsEntity::UNITS) ? (int)($d['units_id'] ?? 0) : (int)$row->units_id;
		$weight = $number('weight');
		$categories = array_values(array_unique(array_filter(array_map('intval', (array)($d['categories'] ?? [])))));
		$categories = $categories ? array_map('intval', $this->db->table('categories')->where('id', $categories)->where('pos_level > 0')->fetchPairs(NULL, 'id')) : [];
		$allergens = array_values(array_intersect(self::ALLERGENS, (array)($d['allergens'] ?? [])));
		$ingredients = self::joinAllergens(trim((string)($d['ingredients'] ?? '')), $allergens);

		$images = $row->images;
		if ($photo && $photo->isOk()) {
			if (!$photo->isImage()) {
				throw new \InvalidArgumentException('admin.mob.new.err.photo');
			}
			// the new photo becomes the main one, the others stay
			$file = ImagesService::saveImageUpload($photo);
			if ($file) {
				$images = json_encode(['file' => $file]) . ($images ? ';' . $images : '');
			}
		}

		$this->db->beginTransaction();
		try {
			$isEan = preg_match('#^\d{8,14}$#', $code) === 1;
			$row->update([
				'code_ean' => $isEan ? $code : NULL,
				'code' => $isEan ? NULL : $code,
				'label_note_1' => $text('label_note_1'),
				'label_note_2' => $text('label_note_2'),
				'label_note_3' => $text('label_note_3'),
				'ingredients' => $ingredients,
				'units_id' => $unitsId,
				'weight' => $weight && $weight > 0 ? $weight : NULL,
				'rozmrazeno' => !empty($d['rozmrazeno']) ? 1 : 0,
				'country_origin' => $countryId ? (string)$countryId : NULL,
				'price_unit' => $priceUnit,
				'images' => $images,
			]);
			$this->db->table('products_mld')->where('products_id', $productsId)->where('languages_id', self::LANG)->update([
				'name' => $name,
				'perex' => trim((string)($d['perex'] ?? '')) ?: NULL,
				'meta_title' => $text('meta_title') ?: NULL,
				'meta_desc' => trim((string)($d['meta_desc'] ?? '')) ?: NULL,
			]);
			// "Aktivní" is the product on the website: every language
			$this->db->table('products_mld')->where('products_id', $productsId)->update(['is_active' => !empty($d['active']) ? 1 : 0]);

			$this->db->table('products_categories_rel')->where('products_id', $productsId)->where('categories_id NOT', $categories ?: [0])->delete();
			$have = $this->db->table('products_categories_rel')->where('products_id', $productsId)->fetchPairs(NULL, 'categories_id');
			foreach (array_diff($categories, array_map('intval', $have)) as $categoryId) {
				$this->db->table('products_categories_rel')->insert(['products_id' => $productsId, 'categories_id' => $categoryId]);
			}

			$nutrition = (array)($d['nutrition'] ?? []);
			foreach ($this->db->table('nutrition_values')->fetchPairs('id', 'id') as $nvId) {
				$value = mb_substr(trim((string)($nutrition[$nvId] ?? '')), 0, 64);
				$where = ['products_id' => $productsId, 'nutrition_values_id' => $nvId];
				if ($value === '') {
					$this->db->table('product_nutrition_values')->where($where)->delete();
				}
				elseif (!$this->db->table('product_nutrition_values')->where($where)->update(['value' => $value])
					&& !$this->db->table('product_nutrition_values')->where($where + ['value' => $value])->count('*')) {
					$this->db->table('product_nutrition_values')->insert($where + ['value' => $value]);
				}
			}
			$this->db->commit();
		}
		catch (\Throwable $e) {
			$this->db->rollBack();
			throw $e;
		}

		// the price of this branch, as "Cena a akce" saves it – only when it changed (every save
		// writes a price log row, the base of the 30-day rule)
		$priceRow = $this->products->findEntity($productsId)?->getPrice($branchesId);
		$from = $this->dmy($d['action_from'] ?? NULL);
		$to = $this->dmy($d['action_to'] ?? NULL);
		$same = static fn($a, $b): bool => $a === NULL ? $b === NULL : ($b !== NULL && abs((float)$a - (float)$b) < 0.005);
		if ($priceRow
			&& $same($price, $priceRow->price !== NULL ? (float)$priceRow->price : NULL)
			&& $same($priceAction, (float)$priceRow->price_action > 0 ? (float)$priceRow->price_action : NULL)
			&& $from === $priceRow->action_from?->format('d.m.Y') && $to === $priceRow->action_to?->format('d.m.Y')) {
			return;
		}
		if (!$priceRow && $price === NULL) {
			return;
		}
		$this->priceSaver->save($productsId, $branchesId, $priceBranchIds, $priceRow?->id ? (int)$priceRow->id : NULL,
			$price !== NULL ? (string)$price : NULL, $priceAction !== NULL ? (string)$priceAction : NULL, $from, $to, $priceUnit);
	}


	/** @return array{0: string, 1: list<string>} ingredients without the allergen sentence, the allergens */
	public static function splitAllergens(string $ingredients): array
	{
		if (!preg_match(self::ALLERGEN_SENTENCE, $ingredients, $m)) {
			return [$ingredients, []];
		}
		$named = array_map(static fn($a) => mb_strtolower(trim($a)), explode(',', $m[1]));
		$found = array_values(array_filter(self::ALLERGENS, static fn($a) => in_array(mb_strtolower($a), $named, TRUE)));

		return [trim(preg_replace(self::ALLERGEN_SENTENCE, '', $ingredients)), $found];
	}


	/** @param list<string> $allergens */
	public static function joinAllergens(string $ingredients, array $allergens): string
	{
		[$ingredients] = self::splitAllergens($ingredients);
		if (!$allergens) {
			return $ingredients;
		}

		return trim($ingredients . ' Obsahuje alergeny: ' . implode(', ', array_map('mb_strtolower', $allergens)) . '.');
	}


	/** Y-m-d (date input) → d.m.Y as ProductPriceSaver takes it */
	private function dmy($iso): ?string
	{
		return preg_match('#^(\d{4})-(\d{2})-(\d{2})$#', (string)$iso, $m) ? "$m[3].$m[2].$m[1]" : NULL;
	}
}
