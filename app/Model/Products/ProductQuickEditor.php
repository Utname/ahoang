<?php

use Nette\Database\Explorer;
use Nette\Http\FileUpload;
use Nette\Utils\Strings;

/**
 * The product card as the new designs edit it on one screen: the phone app's "Mobil – detail produktu"
 * and the admin's "Admin - Detail produktu" modal on Produkty. Reads and saves categories, name, code,
 * active, photo, content, origin, prices (ProductPriceSaver, so the 30-day log is written), label notes,
 * Složení with the allergens, thawed, nutrition values, e-shop description and SEO. Texts are the Czech ones.
 * Allergens have no column: like the design's buildSlozeni(), Složení (`ingredients`) ends with the line
 * "Obsahuje alergeny: …." and, when nutrition values are filled, "Výživové údaje na 100 g: …." – the
 * labels print Složení, so they print both.
 */
class ProductQuickEditor
{
	/** the 14 EU allergens (Regulation 1169/2011, annex II), as the design lists them */
	public const ALLERGENS = ['Lepek', 'Korýši', 'Vejce', 'Ryby', 'Arašídy', 'Sója', 'Mléko', 'Skořápkové plody',
		'Celer', 'Hořčice', 'Sezam', 'Oxid siřičitý', 'Vlčí bob', 'Měkkýši'];

	private const ALLERGEN_LINE = '#\s*Obsahuje alergeny:\s*([^\n]*)#u';
	private const NUTRITION_LINE = '#\s*Výživové údaje na [^:\n]*:[^\n]*#u';
	private const LANG = 'cs';


	public function __construct(
		private Explorer $db,
		private PrintListsService $printLists,
		private ProductPriceSaver $priceSaver,
		private ProductsRepository $products,
	) {
	}


	/**
	 * What the detail screens edit.
	 * @param array<int, string> $branchesPairs the admin's branches – their price rows ("Ceny na pobočkách")
	 */
	public function detail(int $productsId, int $branchesId, array $branchesPairs = []): ?array
	{
		$product = $this->products->findEntity($productsId);
		$row = $this->db->table('products')->get($productsId);
		if (!$product || !$row) {
			return NULL;
		}
		$mld = $this->db->table('products_mld')->where('products_id', $productsId)->where('languages_id', self::LANG)->fetch();
		[$ingredients, $allergens] = self::splitComposition((string)$row->ingredients);
		$price = $product->getPrice($branchesId);
		$values = $this->db->table('product_nutrition_values')->where('products_id', $productsId)->fetchPairs('nutrition_values_id', 'value');

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
			'branches' => $this->branchPrices($productsId, $branchesPairs),
			'notes' => [(string)$row->label_note_1, (string)$row->label_note_2, (string)$row->label_note_3],
			'ingredients' => $ingredients,
			'ingredientsRaw' => (string)$row->ingredients,
			'allergens' => $allergens,
			'thawed' => (bool)$row->rozmrazeno,
			'nutrition' => $this->nutritionList($values),
			'nutritionRef' => $this->nutritionRef($row),
			'perex' => (string)($mld->perex ?? ''),
			'metaTitle' => (string)($mld->meta_title ?? ''),
			'metaDesc' => (string)($mld->meta_desc ?? ''),
			'slug' => (string)($mld->slug ?? ''),
		];
	}


	/** An empty card for "Nový produkt" (a scanned unknown code fills the code). */
	public function blank(array $branchesPairs, string $code = ''): array
	{
		return [
			'id' => 0, 'name' => '', 'code' => $code, 'active' => TRUE, 'categories' => [], 'weight' => '', 'unitsId' => ProductsEntity::UNITS_TYPE_PIECE,
			'country' => 0, 'priceUnit' => 'Kč', 'base' => NULL, 'action' => NULL, 'from' => NULL, 'to' => NULL,
			'branches' => $this->branchPrices(0, $branchesPairs), 'notes' => ['', '', ''], 'ingredients' => '', 'ingredientsRaw' => '',
			'allergens' => [], 'thawed' => FALSE, 'nutrition' => $this->nutritionList([]), 'nutritionRef' => '100 g',
			'perex' => '', 'metaTitle' => '', 'metaDesc' => '', 'slug' => '',
		];
	}


	/**
	 * A new product from the admin modal; the code may be empty there (not every product has an EAN).
	 * @throws \InvalidArgumentException with a translation key (admin.mob.new.err.*)
	 */
	public function create(array $d, int $branchesId, array $priceBranchIds, ?FileUpload $photo = NULL, bool $requireCode = TRUE): int
	{
		$v = $this->validate($d, NULL, $requireCode);
		// the rest is checked by save(); a bad photo must not leave a half-made product
		if ($photo && $photo->isOk() && !$photo->isImage()) {
			throw new \InvalidArgumentException('admin.mob.new.err.photo');
		}
		$this->db->beginTransaction();
		try {
			$id = (int)$this->db->table('products')->insert(['tags' => '', 'label_note_1' => '', 'label_note_2' => '', 'label_note_3' => '', 'ingredients' => '',
				'units_id' => $v['unitsId'], 'price_unit' => $v['priceUnit']])->id;
			$slug = Strings::webalize($v['name']) ?: 'produkt';
			if ($this->db->table('products_mld')->where('slug', $slug)->count('*')) {
				$slug .= '-' . $id;
			}
			$rows = [];
			foreach ($this->db->table('languages')->fetchPairs('id', 'id') as $languageId) {
				$rows[] = ['products_id' => $id, 'languages_id' => $languageId, 'name' => $v['name'], 'slug' => $slug, 'is_active' => 1];
			}
			$this->db->table('products_mld')->insert($rows);
			$this->db->commit();
		}
		catch (\Throwable $e) {
			$this->db->rollBack();
			throw $e;
		}
		$this->save($id, $d, $branchesId, $priceBranchIds, $photo, $requireCode);

		return $id;
	}


	/**
	 * Prices: `prices[branchId][price|price_action|action_from|action_to]` (admin modal, one row per branch)
	 * or `price` / `price_action` / `action_from` / `action_to` for $branchesId (phone app).
	 * @param array<string, mixed> $d form values of the detail screen (dates Y-m-d)
	 * @param list<int> $priceBranchIds the admin's branches
	 * @throws \InvalidArgumentException with a translation key (admin.mob.new.err.*)
	 */
	public function save(int $productsId, array $d, int $branchesId, array $priceBranchIds, ?FileUpload $photo = NULL, bool $requireCode = TRUE): void
	{
		$row = $this->db->table('products')->get($productsId);
		if (!$row) {
			throw new \InvalidArgumentException('admin.mob.new.err.code');
		}
		$v = $this->validate($d, $productsId, $requireCode);
		$text = static fn(string $k, int $max = 255): string => mb_substr(trim(preg_replace('#[ \t]+#u', ' ', (string)($d[$k] ?? ''))), 0, $max);

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
		$nutrition = (array)($d['nutrition'] ?? []);
		$ingredients = self::joinComposition(trim((string)($d['ingredients'] ?? '')), $v['allergens'], $this->nutritionLine($nutrition, $this->nutritionRef($row)));

		$this->db->beginTransaction();
		try {
			$isEan = preg_match('#^\d{8,14}$#', $v['code']) === 1;
			$row->update([
				'code_ean' => $isEan ? $v['code'] : NULL,
				'code' => $isEan || $v['code'] === '' ? NULL : $v['code'],
				'label_note_1' => $text('label_note_1'),
				'label_note_2' => $text('label_note_2'),
				'label_note_3' => $text('label_note_3'),
				'ingredients' => $ingredients,
				'units_id' => $v['unitsId'],
				'weight' => $v['weight'] && $v['weight'] > 0 ? $v['weight'] : NULL,
				'rozmrazeno' => !empty($d['rozmrazeno']) ? 1 : 0,
				'country_origin' => $v['countryId'] ? (string)$v['countryId'] : NULL,
				'price_unit' => $v['priceUnit'],
				'images' => $images,
			]);
			$this->db->table('products_mld')->where('products_id', $productsId)->where('languages_id', self::LANG)->update([
				'name' => $v['name'],
				'perex' => trim((string)($d['perex'] ?? '')) ?: NULL,
				'meta_title' => $text('meta_title') ?: NULL,
				'meta_desc' => trim((string)($d['meta_desc'] ?? '')) ?: NULL,
			]);
			// "Aktivní" is the product on the website: every language
			$this->db->table('products_mld')->where('products_id', $productsId)->update(['is_active' => !empty($d['active']) ? 1 : 0]);

			$this->db->table('products_categories_rel')->where('products_id', $productsId)->where('categories_id NOT', $v['categories'] ?: [0])->delete();
			$have = array_map('intval', $this->db->table('products_categories_rel')->where('products_id', $productsId)->fetchPairs(NULL, 'categories_id'));
			foreach (array_diff($v['categories'], $have) as $categoryId) {
				$this->db->table('products_categories_rel')->insert(['products_id' => $productsId, 'categories_id' => $categoryId]);
			}

			foreach ($this->db->table('nutrition_values')->fetchPairs('id', 'id') as $nvId) {
				$value = mb_substr(trim((string)($nutrition[$nvId] ?? '')), 0, 64);
				$where = ['products_id' => $productsId, 'nutrition_values_id' => $nvId];
				if ($value === '') {
					$this->db->table('product_nutrition_values')->where($where)->delete();
				}
				elseif (!$this->db->table('product_nutrition_values')->where($where)->count('*')) {
					$this->db->table('product_nutrition_values')->insert($where + ['value' => $value]);
				}
				else {
					$this->db->table('product_nutrition_values')->where($where)->update(['value' => $value]);
				}
			}
			$this->db->commit();
		}
		catch (\Throwable $e) {
			$this->db->rollBack();
			throw $e;
		}

		if (isset($d['prices']) && is_array($d['prices'])) {
			// one row per branch: each saved alone (no copying of the regular price to the others)
			foreach ($v['prices'] as $branchId => $p) {
				$this->savePrice($productsId, $branchId, [$branchId], $p['price'], $p['action'], $p['from'], $p['to'], $v['priceUnit']);
			}
		}
		else {
			$this->savePrice($productsId, $branchesId, $priceBranchIds, $v['price'], $v['action'], $this->dmy($d['action_from'] ?? NULL), $this->dmy($d['action_to'] ?? NULL), $v['priceUnit']);
		}
	}


	/** @return array{0: string, 1: list<string>} Složení without the allergen / nutrition lines, the allergens */
	public static function splitComposition(string $ingredients): array
	{
		$found = [];
		if (preg_match(self::ALLERGEN_LINE, $ingredients, $m)) {
			$named = array_map(static fn($a) => mb_strtolower(trim($a, " \t.")), explode(',', $m[1]));
			$found = array_values(array_filter(self::ALLERGENS, static fn($a) => in_array(mb_strtolower($a), $named, TRUE)));
		}
		$text = preg_replace([self::ALLERGEN_LINE, self::NUTRITION_LINE], '', $ingredients);

		return [rtrim((string)$text), $found];
	}


	/** Složení + "Obsahuje alergeny: …." + the nutrition line, each on its own line (buildSlozeni()). */
	public static function joinComposition(string $ingredients, array $allergens, string $nutritionLine = ''): string
	{
		[$text] = self::splitComposition($ingredients);
		$allergens = array_values(array_intersect(self::ALLERGENS, $allergens));
		$lines = [$text];
		if ($allergens) {
			$lines[] = 'Obsahuje alergeny: ' . implode(', ', array_map('mb_strtolower', $allergens)) . '.';
		}
		$lines[] = $nutritionLine;

		return trim(implode("\n", array_filter($lines, static fn($l) => trim($l) !== '')));
	}


	/** "Výživové údaje na 100 g: energie 1000 kJ / 240 kcal, tuk 10 g, …." (empty when nothing is filled) */
	private function nutritionLine(array $values, string $ref): string
	{
		$energy = [];
		$parts = [];
		foreach ($this->db->table('nutrition_values')->order('pos_order') as $nv) {
			$value = trim((string)($values[$nv->id] ?? ''));
			if ($value === '') {
				continue;
			}
			if (in_array(mb_strtolower($nv->unit), ['kj', 'kcal'], TRUE)) {
				$energy[] = $value . ' ' . $nv->unit;
			}
			else {
				$parts[] = mb_strtolower(rtrim($nv->name, ':')) . ' ' . $value . ' ' . $nv->unit;
			}
		}
		if ($energy) {
			array_unshift($parts, 'energie ' . implode(' / ', $energy));
		}

		return $parts ? 'Výživové údaje na ' . $ref . ': ' . implode(', ', $parts) . '.' : '';
	}


	/** Checked and normalised form values (the same rules for every screen). */
	private function validate(array $d, ?int $productsId, bool $requireCode): array
	{
		$text = static fn(string $k, int $max = 255): string => mb_substr(trim(preg_replace('#[ \t]+#u', ' ', (string)($d[$k] ?? ''))), 0, $max);
		$name = $text('name', 250);
		if ($name === '') {
			throw new \InvalidArgumentException('admin.mob.new.err.name');
		}
		$code = $text('code', 32);
		if (($requireCode || $code !== '') && ($code === '' || !preg_match('#^[\w.\-/]+$#u', $code))) {
			throw new \InvalidArgumentException('admin.mob.new.err.code');
		}
		$other = $code !== '' ? $this->printLists->findProductId($code) : NULL;
		if ($other && $other !== $productsId) {
			throw new \InvalidArgumentException('admin.mob.new.err.exists');
		}
		$price = self::number($d['price'] ?? NULL);
		$action = self::number($d['price_action'] ?? NULL);
		self::checkPrice($price, $action);
		$prices = [];
		foreach ((array)($d['prices'] ?? []) as $branchId => $p) {
			$p = (array)$p;
			$prices[(int)$branchId] = ['price' => self::number($p['price'] ?? NULL), 'action' => self::number($p['price_action'] ?? NULL),
				'from' => $this->dmy($p['action_from'] ?? NULL), 'to' => $this->dmy($p['action_to'] ?? NULL)];
			self::checkPrice($prices[(int)$branchId]['price'], $prices[(int)$branchId]['action']);
		}
		$countryId = (int)($d['country'] ?? 0);
		$categories = array_values(array_unique(array_filter(array_map('intval', (array)($d['categories'] ?? [])))));

		return [
			'name' => $name,
			'code' => $code,
			'price' => $price,
			'action' => $action,
			'prices' => $prices,
			'priceUnit' => in_array($d['price_unit'] ?? NULL, ProductsEntity::PRICE_UNITS, TRUE) ? $d['price_unit'] : 'Kč',
			'countryId' => $countryId && $this->db->table('countries')->get($countryId) ? $countryId : NULL,
			'unitsId' => array_key_exists((int)($d['units_id'] ?? 0), ProductsEntity::UNITS) ? (int)$d['units_id'] : ProductsEntity::UNITS_TYPE_PIECE,
			'weight' => self::number($d['weight'] ?? NULL),
			'categories' => $categories ? array_map('intval', $this->db->table('categories')->where('id', $categories)->where('pos_level > 0')->fetchPairs(NULL, 'id')) : [],
			'allergens' => array_values(array_intersect(self::ALLERGENS, (array)($d['allergens'] ?? []))),
		];
	}


	/** ProductPriceSaver writes a price-log row every time (the 30-day rule): only when something changed. */
	private function savePrice(int $productsId, int $branchId, array $branchIds, ?float $price, ?float $action, ?string $from, ?string $to, string $priceUnit): void
	{
		$product = $this->products->findEntity($productsId);
		$priceRow = $product?->getPrice($branchId);
		$same = static fn($a, $b): bool => $a === NULL ? $b === NULL : ($b !== NULL && abs((float)$a - (float)$b) < 0.005);
		if ($priceRow
			&& $same($price, $priceRow->price !== NULL ? (float)$priceRow->price : NULL)
			&& $same($action, (float)$priceRow->price_action > 0 ? (float)$priceRow->price_action : NULL)
			&& $from === $priceRow->action_from?->format('d.m.Y') && $to === $priceRow->action_to?->format('d.m.Y')) {
			return;
		}
		if (!$priceRow && $price === NULL) {
			return;
		}
		$this->priceSaver->save($productsId, $branchId, $branchIds, $priceRow?->id ? (int)$priceRow->id : NULL,
			$price !== NULL ? (string)$price : NULL, $action !== NULL ? (string)$action : NULL, $from, $to, $priceUnit);
	}


	/** @param array<int, string> $branchesPairs */
	private function branchPrices(int $productsId, array $branchesPairs): array
	{
		$rows = $productsId ? $this->db->table('product_prices')->where('products_id', $productsId)->where('branches_id', array_keys($branchesPairs) ?: [0])->fetchAssoc('branches_id') : [];
		$out = [];
		foreach ($branchesPairs as $id => $name) {
			$r = $rows[$id] ?? NULL;
			$out[] = ['id' => (int)$id, 'name' => (string)$name,
				'price' => $r && $r['price'] !== NULL ? (float)$r['price'] : NULL,
				'action' => $r && (float)$r['price_action'] > 0 ? (float)$r['price_action'] : NULL,
				'from' => $r && $r['action_from'] ? $r['action_from']->format('Y-m-d') : NULL,
				'to' => $r && $r['action_to'] ? $r['action_to']->format('Y-m-d') : NULL];
		}

		return $out;
	}


	private function nutritionList(array $values): array
	{
		$out = [];
		foreach ($this->db->table('nutrition_values')->order('pos_order') as $nv) {
			$out[] = ['id' => (int)$nv->id, 'name' => $nv->name, 'unit' => $nv->unit, 'value' => (string)($values[$nv->id] ?? '')];
		}

		return $out;
	}


	private function nutritionRef($row): string
	{
		return $row && $row->nutrition_reference_amount !== NULL && $row->nutrition_reference_unit
			? rtrim(rtrim((string)$row->nutrition_reference_amount, '0'), '.') . ' ' . $row->nutrition_reference_unit
			: '100 g';
	}


	private static function number($v): ?float
	{
		$v = str_replace([',', ' '], ['.', ''], trim((string)$v));

		return $v === '' || !is_numeric($v) ? NULL : (float)$v;
	}


	private static function checkPrice(?float $price, ?float $action): void
	{
		// an action needs the regular price (it is lower than it)
		if (($price !== NULL && ($price <= 0 || $price > 1000000)) || ($action !== NULL && ($price === NULL || $action <= 0 || $action >= $price))) {
			throw new \InvalidArgumentException('admin.mob.new.err.price');
		}
	}


	/** Y-m-d (date input) → d.m.Y as ProductPriceSaver takes it */
	private function dmy($iso): ?string
	{
		return preg_match('#^(\d{4})-(\d{2})-(\d{2})$#', (string)$iso, $m) ? "$m[3].$m[2].$m[1]" : NULL;
	}
}
