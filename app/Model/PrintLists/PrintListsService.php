<?php

use Nette\Database\Explorer;
use Nette\Database\Table\ActiveRow;


/**
 * Tisk z mobilu (predani-tisk-stitku): label lists scanned on the phone and printed on a
 * PC. A list belongs to one branch; an item is product + template type (ProductLabelsEntity
 * TYPES_ARR) + count + header, and its overrides (JSON) change only that print – never the
 * product card nor the admin's own label queue. Prices come from the product (product_prices);
 * "Cena a akce" on the page saves the card through ProductPriceSaver.
 */
final class PrintListsService
{
	public const STATUS_WAITING = 'waiting';
	public const STATUS_PRINTED = 'printed';
	public const STATUS_DELETED = 'deleted';
	public const MAX_QTY = 999;
	public const TIMEZONE = 'Europe/Prague';

	/** override keys an item may carry: label texts + prices for this print only */
	public const OVERRIDE_KEYS = [
		'name', 'note_1', 'note_2', 'note_3', 'sale_text', 'sale_percent', 'is_more_species',
		'origin_countries_id', 'hide_validity', 'hide_ingredients', 'thawed', 'ingredients', 'price_unit',
		'default_units_id', 'default_units_weight', 'price', 'price_action',
	];

	/** @var array<int, ?ProductLabelHeadersEntity> */
	private array $headers = [];

	/** @var array<int, ?CountriesEntity> */
	private array $countries = [];


	public function __construct(
		private Explorer $db,
		private ProductsRepository $productsRepo,
		private ProductLabelHeadersRepository $headersRepo,
		private CountriesRepository $countriesRepo,
		private PrintTemplatesService $printTemplatesService,
	)
	{
	}


	public static function now(): DateTimeImmutable
	{
		return new DateTimeImmutable('now', new DateTimeZone(self::TIMEZONE));
	}


	/** Template types a list may hold, in print order (std, special, Akce last). @return list<int> */
	public static function types(): array
	{
		return array_values(array_filter(array_keys(ProductLabelsEntity::TYPES_ARR), static fn(int $t) => !ProductLabelsEntity::isLockedType($t)));
	}


	// ---------- lists ----------

	/**
	 * A list sent from the phone (or the PC).
	 * @param list<array{products_id: int, type_id: int, qty?: int, product_label_headers_id?: ?int, overrides?: array<string, mixed>}> $items
	 */
	public function create(int $branchesId, ?int $adminUserId, ?string $device, string $source, array $items, bool $allowEmpty = FALSE): int
	{
		$rows = [];
		foreach ($items as $item) {
			if ($row = $this->itemRow($item)) {
				$rows[] = $row;
			}
		}
		if (!$rows && !$allowEmpty) {
			throw new DomainException('admin.pl.err.empty');
		}

		$this->db->beginTransaction();
		try {
			// list numbers per branch: M-001, M-002 … (P- for lists made on the PC)
			// the branch's lists are locked while the next number is taken
			$seq = count($this->db->query('SELECT id FROM print_lists WHERE branches_id = ? FOR UPDATE', $branchesId)->fetchAll()) + 1;
			$list = $this->db->table('print_lists')->insert([
				'list_no' => sprintf('%s-%03d', $source === 'pc' ? 'P' : 'M', $seq),
				'branches_id' => $branchesId,
				'admin_users_id' => $adminUserId,
				'device' => $device !== NULL ? mb_substr(trim($device), 0, 40) : NULL,
				'source' => $source === 'pc' ? 'pc' : 'mobile',
				'status' => self::STATUS_WAITING,
				'created_at' => self::now()->format('Y-m-d H:i:s'),
			]);
			foreach ($rows as $row) {
				$this->db->table('print_list_items')->insert(['print_lists_id' => $list->id] + $row);
			}
			$this->db->commit();
		}
		catch (Throwable $e) {
			$this->db->rollBack();
			throw $e;
		}

		return (int)$list->id;
	}


	/**
	 * One more item in a list (PC "Přidat"); the same product + template + header without
	 * own edits only gets a higher count.
	 * @param array<string, mixed> $overrides
	 */
	public function addItem(int $branchesId, int $listId, int $productsId, int $typeId, int $qty, ?int $headerId = NULL, array $overrides = []): void
	{
		if (!$this->get($branchesId, $listId)) {
			throw new DomainException('admin.pl.err.notFound');
		}
		$row = $this->itemRow(['products_id' => $productsId, 'type_id' => $typeId, 'qty' => $qty, 'product_label_headers_id' => $headerId, 'overrides' => $overrides]);
		if (!$row) {
			throw new DomainException('admin.pl.err.product');
		}
		$same = $row['overrides'] === NULL ? $this->db->table('print_list_items')->where([
			'print_lists_id' => $listId,
			'products_id' => $row['products_id'],
			'type_id' => $row['type_id'],
			'product_label_headers_id' => $row['product_label_headers_id'],
			'overrides' => NULL,
		])->fetch() : NULL;
		if ($same) {
			$same->update(['qty' => min(self::MAX_QTY, (int)$same->qty + $row['qty'])]);
			return;
		}
		$this->db->table('print_list_items')->insert(['print_lists_id' => $listId] + $row);
	}


	/**
	 * The admin's label queue of a branch (Produkty) as a new list; the queue is emptied,
	 * the label texts (✎, shared per product) go with the items as overrides.
	 */
	public function createFromQueue(int $branchesId, int $adminUserId): ?int
	{
		$labels = $this->db->table('product_labels')
			->where(['branches_id' => $branchesId, 'admin_users_id' => $adminUserId])
			->where('datetime_printed IS NULL AND count > 0 AND type_id > 0')
			->order('type_id, id');
		$items = [];
		$ids = [];
		foreach ($labels as $label) {
			$overrides = [];
			foreach (self::OVERRIDE_KEYS as $key) {
				if (in_array($key, ['price', 'price_action'], TRUE) || !isset($label->$key)) {
					continue;
				}
				$value = $label->$key;
				if ($value !== '' && $value !== 0 && $value !== NULL && $value !== '0') {
					$overrides[$key] = is_float($value) ? $value : (is_numeric($value) && !is_string($value) ? (int)$value : $value);
				}
			}
			// a name equal to the product name is no edit
			$productName = $this->db->table('products_mld')->where(['products_id' => $label->products_id, 'languages_id' => 'cs'])->fetch()?->name;
			if (isset($overrides['name']) && $overrides['name'] === $productName) {
				unset($overrides['name']);
			}
			$items[] = [
				'products_id' => (int)$label->products_id,
				'type_id' => (int)$label->type_id,
				'qty' => (int)$label->count,
				'product_label_headers_id' => (int)$label->product_label_headers_id,
				'overrides' => $overrides,
			];
			$ids[] = (int)$label->id;
		}
		if (!$items) {
			return NULL;
		}
		$listId = $this->create($branchesId, $adminUserId, 'PC', 'pc', $items);
		$this->db->table('product_labels')->where('id', $ids)->update(['count' => 0]);

		return $listId;
	}


	/**
	 * A product by EAN / product code, or else by its exact name (scanner, typed EAN).
	 */
	public function findProductId(string $term): ?int
	{
		$term = trim($term);
		if ($term === '') {
			return NULL;
		}
		// UPC-A is stored both as 12 digits and as EAN-13 with a leading 0; scanners return either
		$codes = [$term];
		if (preg_match('/^\d{12}$/', $term)) {
			$codes[] = '0' . $term;
		} elseif (preg_match('/^0\d{12}$/', $term)) {
			$codes[] = substr($term, 1);
		}
		$id = $this->db->query('SELECT id FROM products WHERE code_ean IN (?) UNION SELECT products_id FROM product_codes WHERE value IN (?) LIMIT 1', $codes, $codes)->fetchField();
		if (!$id) {
			$id = $this->db->query('SELECT products_id FROM products_mld WHERE languages_id = ? AND name = ? LIMIT 1', 'cs', $term)->fetchField();
		}

		return $id ? (int)$id : NULL;
	}


	/**
	 * Suggestions for the add field: by the start of the EAN (digits) or by name.
	 * @return list<array{id: int, name: string, ean: string}>
	 */
	public function searchProducts(string $term, int $limit = 12): array
	{
		$term = trim($term);
		if (mb_strlen($term) < 2) {
			return [];
		}
		if (preg_match('/^\d{3,}$/', $term)) {
			$ids = $this->db->query('SELECT id FROM products WHERE code_ean LIKE ? UNION SELECT products_id FROM product_codes WHERE value LIKE ? LIMIT ?', $term . '%', $term . '%', $limit)->fetchPairs(NULL, 'id');
			$sql = 'm.products_id IN (?)';
			$params = [$ids ?: [0]];
		}
		else {
			$parts = array_values(array_filter(explode(' ', $term)));
			$sql = implode(' AND ', array_fill(0, count($parts), 'm.name LIKE ?'));
			$params = array_map(static fn($part) => '%' . $part . '%', $parts);
		}
		$rows = $this->db->query('SELECT m.products_id id, m.name, p.code_ean ean FROM products_mld m JOIN products p ON p.id = m.products_id WHERE m.languages_id = ? AND (' . $sql . ') ORDER BY m.name LIMIT ?', 'cs', ...[...$params, $limit])->fetchAll();

		return array_map(static fn($r) => ['id' => (int)$r->id, 'name' => (string)$r->name, 'ean' => (string)$r->ean], $rows);
	}


	/** A valid item row, or NULL (unknown product, a template a list cannot hold). */
	private function itemRow(array $item): ?array
	{
		$typeId = (int)($item['type_id'] ?? 0);
		$productsId = (int)($item['products_id'] ?? 0);
		if (!in_array($typeId, self::types(), TRUE) || !$this->db->table('products')->where('id', $productsId)->count('*')) {
			return NULL;
		}
		$headerId = isset($item['product_label_headers_id']) ? (int)$item['product_label_headers_id'] : NULL;

		return [
			'products_id' => $productsId,
			'type_id' => $typeId,
			'qty' => max(1, min(self::MAX_QTY, (int)($item['qty'] ?? 1))),
			'product_label_headers_id' => $headerId && $this->header($headerId) ? $headerId : $this->defaultHeaderId($typeId),
			'overrides' => $this->encodeOverrides($item['overrides'] ?? []),
		];
	}


	/**
	 * Lists of a branch for "Čeká / Vytištěno / Vše" and a day (Y-m-d, NULL = every day).
	 * @return list<ActiveRow>
	 */
	public function lists(int $branchesId, string $filter, ?string $date): array
	{
		$selection = $this->db->table('print_lists')
			->where('branches_id', $branchesId)
			->where('status != ?', self::STATUS_DELETED)
			->order('created_at DESC, id DESC');
		if ($date) {
			$selection->where('created_at >= ? AND created_at < ?', $date . ' 00:00:00', date('Y-m-d', strtotime($date . ' +1 day')) . ' 00:00:00');
		}
		if (in_array($filter, [self::STATUS_WAITING, self::STATUS_PRINTED], TRUE)) {
			$selection->where('status', $filter);
		}

		return array_values($selection->fetchAll());
	}


	/**
	 * Lists per filter, and their labels (sum of qty) under 'labels'.
	 * @return array{waiting: int, printed: int, all: int, labels: array{waiting: int, printed: int, all: int}}
	 */
	public function counts(int $branchesId, ?string $date): array
	{
		$counts = ['waiting' => 0, 'printed' => 0, 'all' => 0];
		$labels = $counts;
		$lists = $this->lists($branchesId, 'all', $date);
		$qty = $lists
			? $this->db->table('print_list_items')
				->where('print_lists_id', array_map(static fn(ActiveRow $list) => $list->id, $lists))
				->select('print_lists_id, SUM(qty) AS qty')
				->group('print_lists_id')
				->fetchPairs('print_lists_id', 'qty')
			: [];
		foreach ($lists as $list) {
			$n = (int)($qty[$list->id] ?? 0);
			$counts[$list->status] = ($counts[$list->status] ?? 0) + 1;
			$counts['all']++;
			$labels[$list->status] = ($labels[$list->status] ?? 0) + $n;
			$labels['all'] += $n;
		}

		return $counts + ['labels' => $labels];
	}


	/** First branch of an admin (all branches, or the ones given), as Tisk z mobilu picks it. */
	public function firstBranchId(?array $allowedIds): int
	{
		$selection = $this->db->table('branches')->order('pos_order');
		if ($allowedIds !== NULL) {
			$selection->where('id', $allowedIds ?: [0]);
		}

		return (int)($selection->limit(1)->fetch()->id ?? 0);
	}


	/** Badge of the menu item: waiting lists of the branch, any day. */
	public function waitingCount(int $branchesId): int
	{
		return $this->db->table('print_lists')->where(['branches_id' => $branchesId, 'status' => self::STATUS_WAITING])->count('*');
	}


	public function get(int $branchesId, int $listId): ?ActiveRow
	{
		return $this->db->table('print_lists')
			->where(['id' => $listId, 'branches_id' => $branchesId])
			->where('status != ?', self::STATUS_DELETED)
			->fetch() ?: NULL;
	}


	/** Items in print order (template type order, then as sent). @return list<ActiveRow> */
	public function items(int $listId): array
	{
		$order = array_flip(self::types());
		$items = array_values($this->db->table('print_list_items')->where('print_lists_id', $listId)->order('id')->fetchAll());
		usort($items, static fn($a, $b) => [$order[$a->type_id] ?? 99, $a->id] <=> [$order[$b->type_id] ?? 99, $b->id]);

		return $items;
	}


	/**
	 * Labels, products and A4 sheets of some items (every template on its own sheets).
	 * @param list<ActiveRow> $items
	 * @return array{count: int, products: int, pages: int, perType: array<int, int>}
	 */
	public static function stats(array $items): array
	{
		$perType = [];
		$products = [];
		foreach ($items as $item) {
			$perType[(int)$item->type_id] = ($perType[(int)$item->type_id] ?? 0) + (int)$item->qty;
			$products[(int)$item->products_id] = TRUE;
		}
		$pages = 0;
		foreach ($perType as $typeId => $count) {
			$pages += (int)ceil($count / max(1, (int)(ProductLabelsEntity::TYPES_ARR[$typeId]['perPage'] ?? 1)));
		}

		return ['count' => array_sum($perType), 'products' => count($products), 'pages' => $pages, 'perType' => $perType];
	}


	public function markPrinted(int $branchesId, int $listId, ?int $adminUserId, bool $printed = TRUE): void
	{
		$this->db->table('print_lists')->where(['id' => $listId, 'branches_id' => $branchesId])->update([
			'status' => $printed ? self::STATUS_PRINTED : self::STATUS_WAITING,
			'printed_at' => $printed ? self::now()->format('Y-m-d H:i:s') : NULL,
			'printed_by' => $printed ? $adminUserId : NULL,
		]);
	}


	/** Soft delete: the list stays for the record. */
	public function delete(int $branchesId, int $listId): void
	{
		$this->db->table('print_lists')->where(['id' => $listId, 'branches_id' => $branchesId])->update(['status' => self::STATUS_DELETED]);
	}


	// ---------- items ----------

	public function item(int $branchesId, int $itemId): ?ActiveRow
	{
		$item = $this->db->table('print_list_items')->get($itemId);
		if (!$item) {
			return NULL;
		}
		$list = $this->get($branchesId, (int)$item->print_lists_id);

		return $list ? $item : NULL;
	}


	/** −/+ on a row; at 0 the item leaves the list. */
	public function changeQty(int $branchesId, int $itemId, int $delta): void
	{
		if (!$item = $this->item($branchesId, $itemId)) {
			throw new DomainException('admin.pl.err.notFound');
		}
		$qty = (int)$item->qty + $delta;
		if ($qty <= 0) {
			$item->delete();
			return;
		}
		$item->update(['qty' => min(self::MAX_QTY, $qty)]);
	}


	public function removeItem(int $branchesId, int $itemId): void
	{
		$this->item($branchesId, $itemId)?->delete();
	}


	public function setHeader(int $branchesId, int $itemId, int $headerId): void
	{
		$item = $this->item($branchesId, $itemId);
		if ($item && $this->header($headerId)) {
			$item->update(['product_label_headers_id' => $headerId]);
		}
	}


	/**
	 * ✎ Upravit: count, header and the overrides of this print; NULL overrides = back to the
	 * product card ("Obnovit z produktu").
	 * @param array<string, mixed>|null $overrides
	 */
	public function saveItem(int $branchesId, int $itemId, int $qty, ?int $headerId, ?array $overrides): void
	{
		if (!$item = $this->item($branchesId, $itemId)) {
			throw new DomainException('admin.pl.err.notFound');
		}
		$item->update([
			'qty' => max(1, min(self::MAX_QTY, $qty)),
			'product_label_headers_id' => $headerId && $this->header($headerId) ? $headerId : $item->product_label_headers_id,
			'overrides' => $overrides === NULL ? NULL : $this->encodeOverrides($overrides),
		]);
	}


	/** @return array<string, mixed> */
	public static function overrides(ActiveRow $item): array
	{
		$data = $item->overrides ? json_decode((string)$item->overrides, TRUE) : NULL;

		return is_array($data) ? $data : [];
	}


	// ---------- printing ----------

	/**
	 * The label of an item: product card + overrides (see PrintListLabelEntity).
	 */
	public function label(int $branchesId, ActiveRow $item): ?PrintListLabelEntity
	{
		/** @var ?ProductsEntity $product */
		$product = $this->productsRepo->findEntity((int)$item->products_id);
		if (!$product) {
			return NULL;
		}
		$o = self::overrides($item);
		$countryId = isset($o['origin_countries_id']) && $o['origin_countries_id'] !== '' ? (int)$o['origin_countries_id'] : NULL;
		$data = [
			'id' => 0,
			'products_id' => (int)$product->id,
			'branches_id' => $branchesId,
			'admin_users_id' => NULL,
			'type_id' => (int)$item->type_id,
			'is_more_species' => (int)($o['is_more_species'] ?? 0),
			'thawed' => (int)($o['thawed'] ?? 0),
			'origin_countries_id' => $countryId,
			'product_label_headers_id' => (int)$item->product_label_headers_id,
			'sale_text' => (string)($o['sale_text'] ?? ''),
			'sale_percent' => (int)($o['sale_percent'] ?? 0),
			'price_text' => ProductLabelsEntity::PRICE_TYPE_CZK,
			'note_1' => (string)($o['note_1'] ?? ''),
			'note_2' => (string)($o['note_2'] ?? ''),
			'note_3' => (string)($o['note_3'] ?? ''),
			'hide_validity' => (int)($o['hide_validity'] ?? 0),
			'hide_ingredients' => (int)($o['hide_ingredients'] ?? 0),
			'datetime_printed' => NULL,
			'count' => (int)$item->qty,
			'name' => (string)($o['name'] ?? ''),
			'default_units_id' => isset($o['default_units_id']) && $o['default_units_id'] !== '' ? (int)$o['default_units_id'] : NULL,
			'default_units_weight' => isset($o['default_units_weight']) && $o['default_units_weight'] !== '' ? (float)$o['default_units_weight'] : NULL,
			'ingredients' => isset($o['ingredients']) && trim((string)$o['ingredients']) !== '' ? (string)$o['ingredients'] : NULL,
			'price_unit' => isset($o['price_unit']) && in_array($o['price_unit'], ProductsEntity::PRICE_UNITS, TRUE) ? $o['price_unit'] : NULL,
		];

		return PrintListLabelEntity::create($this->db, $data, $product, $this->header((int)$item->product_label_headers_id), $countryId ? $this->country($countryId) : NULL);
	}


	/**
	 * Prices typed in ✎ for this print only, in the priceOverride shape of renderHtml(). A
	 * typed sale price is meant to be printed, so it counts as a running action: the product's
	 * own action dates when they cover today, otherwise from today without an end.
	 */
	public static function priceOverride(ActiveRow $item, ?ProductsEntity $product = NULL, int $branchesId = 0): ?array
	{
		$o = self::overrides($item);
		$override = [];
		foreach (['price', 'price_action'] as $key) {
			if (isset($o[$key]) && $o[$key] !== '' && is_numeric(str_replace(',', '.', (string)$o[$key]))) {
				$override[$key] = (float)str_replace(',', '.', (string)$o[$key]);
			}
		}
		if (isset($override['price_action'])) {
			$today = self::now()->format('Y-m-d');
			$price = $product?->getPrice($branchesId);
			$from = $price?->action_from?->format('Y-m-d');
			$to = $price?->action_to?->format('Y-m-d');
			$override['action_from'] = $from && $from <= $today ? $from : $today;
			$override['action_to'] = $to && $to >= $today ? $to : NULL;
		}

		return $override ?: NULL;
	}


	/**
	 * PDF of a list (or one template type / one item of it, $oneCopy = one label for a quick
	 * look), the templates of the label queue, every template on its own sheets.
	 */
	public function pdf(int $branchesId, int $listId, ?int $typeId = NULL, ?int $itemId = NULL, bool $oneCopy = FALSE): ?string
	{
		$items = array_filter($this->items($listId), static fn(ActiveRow $item) => (!$typeId || (int)$item->type_id === $typeId) && (!$itemId || (int)$item->id === $itemId));

		return $this->renderItems($branchesId, $items, $oneCopy);
	}


	/**
	 * PDF of items not saved anywhere (the phone's queue before sending), same rules as a list.
	 * @param list<array{products_id: int, type_id: int, qty?: int, product_label_headers_id?: ?int, overrides?: array}> $items
	 */
	public function pdfFromItems(int $branchesId, array $items): ?string
	{
		$order = array_flip(self::types());
		$rows = [];
		foreach ($items as $i => $item) {
			if ($row = $this->itemRow($item)) {
				$rows[] = new ActiveRow(['id' => -1 - $i, 'print_lists_id' => 0] + $row, $this->db->table('print_list_items')->select('*')->where('id', -1));
			}
		}
		usort($rows, static fn($a, $b) => [$order[$a->type_id] ?? 99, -$a->id] <=> [$order[$b->type_id] ?? 99, -$b->id]);

		return $this->renderItems($branchesId, $rows);
	}


	/** @param iterable<ActiveRow> $items */
	private function renderItems(int $branchesId, iterable $items, bool $oneCopy = FALSE): ?string
	{
		$jobs = [];
		foreach ($items as $item) {
			$label = $this->label($branchesId, $item);
			$template = $label ? $this->printTemplatesService->findTemplateForProductLabel($label, $label->getProduct(), $branchesId) : NULL;
			if (!$template) {
				continue;
			}
			for ($i = 0; $i < ($oneCopy ? 1 : (int)$item->qty); $i++) {
				$jobs[] = ['template' => $template, 'label' => $label, 'priceOverride' => self::priceOverride($item, $label->getProduct(), $branchesId)];
			}
		}

		return $jobs ? $this->printTemplatesService->renderProductLabelsPdf($jobs, $branchesId)->output() : NULL;
	}


	// ---------- helpers ----------

	public function header(int $id): ?ProductLabelHeadersEntity
	{
		if (!array_key_exists($id, $this->headers)) {
			$header = $this->headersRepo->findEntity($id);
			$this->headers[$id] = $header instanceof ProductLabelHeadersEntity ? $header : NULL;
		}

		return $this->headers[$id];
	}


	public function country(int $id): ?CountriesEntity
	{
		if (!array_key_exists($id, $this->countries)) {
			$country = $this->countriesRepo->findEntity($id);
			$this->countries[$id] = $country instanceof CountriesEntity ? $country : NULL;
		}

		return $this->countries[$id];
	}


	/** Akce templates start with "Akce !!!", the others with the first header (as the queue). */
	public function defaultHeaderId(int $typeId): ?int
	{
		if (ProductLabelsEntity::isAkceType($typeId) && $this->header(ProductLabelsEntity::HEADER_TYPE_ACTION)) {
			return ProductLabelsEntity::HEADER_TYPE_ACTION;
		}
		$first = $this->db->table('product_label_headers')->order('pos_order')->limit(1)->fetch();

		return $first ? (int)$first->id : NULL;
	}


	/** @param array<string, mixed> $overrides */
	private function encodeOverrides(array $overrides): ?string
	{
		$clean = [];
		foreach (self::OVERRIDE_KEYS as $key) {
			if (array_key_exists($key, $overrides) && $overrides[$key] !== NULL && $overrides[$key] !== '') {
				$clean[$key] = is_string($overrides[$key]) ? trim($overrides[$key]) : $overrides[$key];
			}
		}

		return $clean ? json_encode($clean, JSON_UNESCAPED_UNICODE) : NULL;
	}
}
