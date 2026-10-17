<?php

namespace App\AdminModule\Presenters;

use Nette\Application\Responses\TextResponse;
use Nette\Application\UI\Form;

/**
 * Phone web app "Tisk štítků" (predani-tisk-stitku "Admin – tisk štítků mobil"): scan an EAN,
 * pick templates and counts, keep a queue on the phone (Fronta) and send it to the admin
 * (Tisk z mobilu). One page (templates/default.latte + www/js/admin/mobil.js) talking to the
 * JSON signals below. Permissions are those of Tisk z mobilu; sending needs PrintLists_edit.
 */
class MobilPresenter extends BaseModulePresenter
{
	/** @var \PrintListsService @inject */
	public $printLists;

	/** @var \ProductPriceSaver @inject */
	public $productPriceSaver;

	/** @var \ProductsRepository @inject */
	public $productsRepo;

	/** @var \BranchesRepository @inject */
	public $branchesRepo;

	/** @var \ProductLabelHeadersRepository @inject */
	public $headersRepo;

	/** @var \CountriesRepository @inject */
	public $countriesRepo;

	/** @var \VykupService @inject */
	public $vykup;

	/** @var \ImageFilter @inject */
	public $imageFilter;

	/** @var \ProductQuickCreator @inject */
	public $productQuickCreator;

	/** @var \CategoriesRepository @inject */
	public $categoriesRepo;

	/** @var \ProductQuickEditor @inject */
	public $productQuickEditor;

	/** texts of the app (admin.mob.*), translated for the admin's language */
	private const TEXT_KEYS = [
		'appTitle', 'overview', 'scan', 'queue', 'sent', 'queueTitle',
		'kpiAkce', 'kpiQueue', 'kpiWaiting', 'kpiVykup', 'quickActions', 'scanBtn',
		'productsBtn', 'camera', 'cameraStart', 'cameraAim', 'cameraOff', 'cameraHttps',
		'cameraStop', 'quickMode', 'quickHint', 'searchPh', 'recent', 'results',
		'noResults', 'regular', 'lowest30', 'akcePrice', 'ourPrice', 'noAkce',
		'editPrice', 'labels', 'noLabels', 'sheetHint', 'addToQueue', 'akceNoPrice',
		'origin', 'moreSpecies', 'nameEdited', 'nameNote', 'preview', 'previewAll',
		'sendInfo', 'sendCount', 'sending', 'sentOk', 'queueEmpty', 'jobsEmpty',
		'waiting', 'printed', 'again', 'againDone', 'undo', 'added',
		'notFound', 'priceSaved', 'priceNote', 'save', 'cancel', 'endAkce',
		'from', 'to', 'unit', 'menuAdmin', 'menuProducts', 'menuVykup',
		'branch', 'signOut', 'noRight', 'error', 'pcsPages', 'remove',
		'statusRunning', 'statusPlanned', 'statusEnded', 'statusNone', 'groupStd', 'groupSpec',
		'groupAkce', 'newTitle', 'newHint', 'newCode', 'newName', 'newPrice', 'newCreate', 'newCreated', 'newSaving',
		'newBasic', 'newCategory', 'newPhoto', 'newPhotoTake', 'newPack', 'newWeight', 'newUnitPrice', 'newPrices', 'newPriceAction',
		'newAllBranches', 'newLabel', 'newNote', 'newIngredients', 'newThawed', 'newConsumption', 'newPerex', 'newOptional',
		'products', 'filterAll', 'filterRunning', 'filterPlanned', 'filterToPrint', 'prodSummary', 'more', 'edit',
		'printQueue', 'discount', 'scanEan',
		'dtBack', 'dtUnsaved', 'dtSaved', 'dtCategories', 'dtChange', 'dtDone', 'dtNone', 'dtActive', 'dtCode', 'dtCodeBad',
		'dtPhoto', 'dtContent', 'dtPrice', 'dtLabel', 'dtAllergens', 'dtAllergensNote', 'dtNutrition', 'dtNutritionRef',
		'dtPerex', 'dtSeo', 'dtMetaTitle', 'dtMetaDesc', 'dtSlug', 'dtWarnPrice', 'dtWarnDates', 'dtLeave', 'dtSaving',
	];

	/** @var array<int, string> */
	protected $branchesPairs = [];

	/** @var int */
	protected $branchesId = 0;


	protected function startup()
	{
		parent::startup();
		// local debug mode: Tracy's bar would cover the app's bottom tabs
		\Tracy\Debugger::$showBar = FALSE;
		// the app is part of Tisk z mobilu (same module and permissions)
		$this->aps->setActualModuleId('PrintLists');
		$this->checkPermission('list');

		$selection = $this->branchesRepo->findAll()->order('pos_order');
		if (!$this->adminUser->is_all_branches_permission) {
			$ids = $this->adminUser->getBranchesIds();
			$selection->where('id', $ids ?: [0]);
		}
		$this->branchesPairs = $selection->fetchPairs('id', 'name');
		if (!$this->branchesPairs) {
			$this->redirect('Homepage:');
		}
		$section = $this->getSession('printLists');
		$branchesId = (int)($section->branch ?? 0) ?: (int)($this->adminUser->default_branches_id ?? 0);
		$this->branchesId = isset($this->branchesPairs[$branchesId]) ? $branchesId : (int)array_key_first($this->branchesPairs);
	}


	/** The base beforeRender() wants an edit form; this module has no repository. */
	protected function createComponentEditForm(): Form
	{
		$form = new Form();
		$form->setTranslator($this->translator);
		return $form;
	}


	public function handleBranch(int $id): void
	{
		if (isset($this->branchesPairs[$id])) {
			$this->getSession('printLists')->branch = $id;
		}
		$this->redirect('default');
	}


	public function renderDefault(): void
	{
		$this->setLayout(FALSE);
		$types = [];
		foreach (\PrintListsService::types() as $typeId) {
			$def = \ProductLabelsEntity::TYPES_ARR[$typeId];
			$types[] = [
				'id' => $typeId,
				'name' => $this->global['productLabelTypes'][$typeId] ?? (string)$typeId,
				'dims' => $def['dims'],
				'perPage' => (int)$def['perPage'],
				'akce' => \ProductLabelsEntity::isAkceType($typeId),
				'group' => $def['group'],
				'header' => $this->printLists->defaultHeaderId($typeId),
			];
		}
		$headers = [];
		foreach ($this->headersRepo->findAll()->order('pos_order')->fetchPairs('id', 'name') as $id => $name) {
			$headers[] = ['id' => (int)$id, 'name' => $name];
		}
		$countries = [];
		foreach ($this->countriesRepo->findAll()->order('pos_order')->fetchPairs('id', 'name') as $id => $name) {
			$countries[] = ['id' => (int)$id, 'name' => $name];
		}
		$this->template->config = [
			'branch' => ['id' => $this->branchesId, 'name' => $this->branchesPairs[$this->branchesId]],
			'branches' => array_map(fn($id, $name) => ['id' => (int)$id, 'name' => $name, 'url' => $this->link('branch!', ['id' => $id])], array_keys($this->branchesPairs), $this->branchesPairs),
			'user' => (string)($this->adminUser->name ?? ''),
			'canSend' => $this->hasPermission('edit'),
			// a scanned code that is not known can become a new product
			'canCreate' => $this->aps->has('Products_create'),
			// the product detail screen saves the product card
			'canEditProduct' => $this->aps->has('Products_edit'),
			'allergens' => \ProductQuickEditor::ALLERGENS,
			'types' => $types,
			'headers' => $headers,
			'countries' => $countries,
			'units' => \ProductsEntity::PRICE_UNITS,
			// new product form (scanned code not known yet)
			'categories' => array_map(fn($id, $name) => ['id' => (int)$id, 'name' => $name], array_keys($categories = $this->categoriesRepo->findAll()->where('pos_level > 0')->order('pos_left')->fetchPairs('id', 'name')), $categories),
			'sizeUnits' => array_map(fn($id, $name) => ['id' => $id, 'name' => $name, 'short' => \ProductsEntity::UNITS_SHORT[$id] ?? $name], array_keys(\ProductsEntity::UNITS), \ProductsEntity::UNITS),
			'consumptionTypes' => array_map(fn($id, $name) => ['id' => $id, 'name' => $name], array_keys($this->global['consumptionTypes'] ?? []), $this->global['consumptionTypes'] ?? []),
			'branchCount' => count($this->branchesPairs),
			'urls' => [
				'search' => $this->link('search!'),
				'byCode' => $this->link('byCode!'),
				'products' => $this->link('products!'),
				'list' => $this->link('list!'),
				'detail' => $this->link('detail!'),
				'saveProduct' => $this->link('saveProduct!'),
				// "✎ Upravit" on a product card: the admin product card (999999 = the product id)
				'productEdit' => $this->link('Products:edit', ['id' => 999999]),
				'savePrice' => $this->link('savePrice!'),
				'createProduct' => $this->link('createProduct!'),
				'send' => $this->link('send!'),
				'lists' => $this->link('lists!'),
				'stats' => $this->link('stats!'),
				'preview' => $this->link('preview!'),
				'admin' => $this->link('PrintLists:default'),
				'products_admin' => $this->link('Products:default'),
				'vykup' => $this->link('Vykup:default'),
				'signOut' => $this->link('signOut!'),
			],
			'storageKey' => 'fikko-mobil-' . (int)($this->adminUser->id ?? 0) . '-' . $this->branchesId,
			't' => array_combine(self::TEXT_KEYS, array_map(fn($k) => $this->trans('admin.mob.' . $k), self::TEXT_KEYS)),
		];
	}


	// ---------- JSON ----------

	/** Products by name or the start of the EAN. */
	public function handleSearch(string $term = ''): void
	{
		$out = [];
		foreach ($this->printLists->searchProducts($term, 20) as $row) {
			if ($info = $this->productInfo($row['id'])) {
				$out[] = $info;
			}
		}
		$this->sendJson($out);
	}


	/** A scanned code: the product with that EAN / code (or NULL). */
	public function handleByCode(string $code = ''): void
	{
		$id = $this->printLists->findProductId($code);
		$this->sendJson($id ? $this->productInfo($id) : NULL);
	}


	/** Products of the phone's recent / queue list, fresh prices. */
	public function handleProducts(string $ids = ''): void
	{
		$out = [];
		foreach (array_slice(array_unique(array_filter(array_map('intval', explode(',', $ids)))), 0, 60) as $id) {
			if ($info = $this->productInfo($id)) {
				$out[$id] = $info;
			}
		}
		$this->sendJson($out);
	}


	/** Produkty: the branch's priced products, 20 at a time – all / running action / planned action, by name or EAN. */
	public function handleList(string $filter = 'all', string $term = '', int $offset = 0): void
	{
		$today = \PrintListsService::now()->format('Y-m-d');
		$where = ['pp.branches_id = ?'];
		$params = [$this->branchesId];
		if ($filter === 'running') {
			$where[] = 'pp.price_action > 0 AND pp.price > pp.price_action AND pp.action_from IS NOT NULL AND pp.action_from <= ? AND (pp.action_to IS NULL OR pp.action_to >= ?)';
			array_push($params, $today, $today);
		}
		elseif ($filter === 'planned') {
			$where[] = 'pp.price_action > 0 AND pp.action_from > ?';
			$params[] = $today;
		}
		$term = trim($term);
		if (preg_match('/^\d{3,}$/', $term)) {
			$where[] = '(p.code_ean LIKE ? OR p.id IN (SELECT products_id FROM product_codes WHERE value LIKE ?))';
			array_push($params, $term . '%', $term . '%');
		}
		elseif ($term !== '') {
			foreach (array_filter(explode(' ', $term)) as $part) {
				$where[] = 'm.name LIKE ?';
				$params[] = '%' . $part . '%';
			}
		}
		$from = 'FROM product_prices pp JOIN products p ON p.id = pp.products_id JOIN products_mld m ON m.products_id = p.id AND m.languages_id = ? WHERE ' . implode(' AND ', $where);
		$total = (int)$this->database->query('SELECT COUNT(DISTINCT p.id) ' . $from, 'cs', ...$params)->fetchField();
		$offset = max(0, $offset);
		$ids = $this->database->query('SELECT p.id, MIN(m.name) name ' . $from . ' GROUP BY p.id ORDER BY name LIMIT 20 OFFSET ?', 'cs', ...[...$params, $offset])->fetchPairs(NULL, 'id');
		$items = [];
		foreach ($ids as $id) {
			if ($info = $this->productInfo((int)$id)) {
				$items[] = $info;
			}
		}
		$this->sendJson(['total' => $total, 'items' => $items, 'next' => $offset + 20 < $total ? $offset + 20 : NULL]);
	}


	/** Detail produktu: what the screen edits (the product card, the price of this branch). */
	public function handleDetail(int $productsId = 0): void
	{
		$this->sendJson($this->productQuickEditor->detail($productsId, $this->branchesId));
	}


	/** Detail produktu "Uložit" (multipart POST: the fields + an optional photo). */
	public function handleSaveProduct(int $productsId = 0): void
	{
		if (!$this->aps->has('Products_edit')) {
			$this->sendJson(['ok' => FALSE, 'error' => $this->trans('admin.mob.noRight')]);
		}
		$request = $this->getHttpRequest();
		try {
			$this->productQuickEditor->save($productsId, (array)$request->getPost(), $this->branchesId, array_map('intval', array_keys($this->branchesPairs)), $request->getFile('photo'));
		}
		catch (\InvalidArgumentException $e) {
			$this->sendJson(['ok' => FALSE, 'error' => $this->trans($e->getMessage())]);
		}
		$this->sendJson(['ok' => TRUE, 'product' => $this->productInfo($productsId), 'detail' => $this->productQuickEditor->detail($productsId, $this->branchesId)]);
	}


	/** "Cena a akce" from the phone: the product card, as in Produkty. */
	public function handleSavePrice(int $productsId = 0, $price = NULL, $price_action = NULL, $action_from = NULL, $action_to = NULL, $price_unit = NULL): void
	{
		if (!$this->hasPermission('edit')) {
			$this->sendJson(['ok' => FALSE]);
		}
		$priceRow = $this->productsRepo->findEntity($productsId)?->getPrice($this->branchesId);
		$this->productPriceSaver->save($productsId, $this->branchesId, array_map('intval', array_keys($this->branchesPairs)), $priceRow?->id ? (int)$priceRow->id : NULL, $price, $price_action, $action_from, $action_to, $price_unit);
		$this->sendJson(['ok' => TRUE, 'product' => $this->productInfo($productsId)]);
	}


	/** A new product for a scanned code that is not known (multipart POST: the form fields + photo). */
	public function handleCreateProduct(): void
	{
		if (!$this->aps->has('Products_create')) {
			$this->sendJson(['ok' => FALSE, 'error' => $this->trans('admin.mob.new.err.right')]);
		}
		$request = $this->getHttpRequest();
		$data = (array)$request->getPost();
		// the price for this branch, or for all branches of the admin (this one first)
		$branchIds = !empty($data['all_branches'])
			? array_merge([$this->branchesId], array_map('intval', array_keys($this->branchesPairs)))
			: [$this->branchesId];
		try {
			$id = $this->productQuickCreator->create($data, $branchIds, $request->getFile('photo'));
		}
		catch (\InvalidArgumentException $e) {
			$this->sendJson(['ok' => FALSE, 'error' => $this->trans($e->getMessage())]);
		}
		$this->sendJson(['ok' => TRUE, 'product' => $this->productInfo($id)]);
	}


	/** "Odeslat do adminu": the queue (JSON body {device, items}) becomes a waiting list. */
	public function handleSend(): void
	{
		if (!$this->hasPermission('edit')) {
			$this->sendJson(['ok' => FALSE, 'error' => $this->trans('admin.mob.noRight')]);
		}
		$body = json_decode((string)$this->getHttpRequest()->getRawBody(), TRUE) ?: [];
		try {
			$id = $this->printLists->create($this->branchesId, isset($this->adminUser->id) ? (int)$this->adminUser->id : NULL, (string)($body['device'] ?? 'Mobil'), 'mobile', $this->items($body['items'] ?? []));
			$list = $this->printLists->get($this->branchesId, $id);
			$this->sendJson(['ok' => TRUE, 'id' => $id, 'no' => $list->list_no]);
		}
		catch (\DomainException $e) {
			$this->sendJson(['ok' => FALSE, 'error' => $this->trans($e->getMessage())]);
		}
	}


	/** "Odesláno": this admin's lists of today in the branch. */
	public function handleLists(): void
	{
		$out = [];
		foreach ($this->printLists->lists($this->branchesId, 'all', \PrintListsService::now()->format('Y-m-d')) as $list) {
			if ((int)$list->admin_users_id !== (int)($this->adminUser->id ?? 0)) {
				continue;
			}
			$items = $this->printLists->items((int)$list->id);
			$stats = \PrintListsService::stats($items);
			$sizes = [];
			foreach ($stats['perType'] as $typeId => $count) {
				$sizes[] = ($this->global['productLabelTypes'][$typeId] ?? $typeId) . ' ×' . $count;
			}
			$out[] = [
				'id' => (int)$list->id,
				'no' => $list->list_no,
				'time' => $list->created_at->format('H:i'),
				'count' => $stats['count'],
				'pages' => $stats['pages'],
				'sizes' => implode(' · ', $sizes),
				'status' => $list->status,
				'items' => array_map(static fn($i) => [
					'products_id' => (int)$i->products_id,
					'type_id' => (int)$i->type_id,
					'qty' => (int)$i->qty,
					'header' => (int)$i->product_label_headers_id,
					'overrides' => \PrintListsService::overrides($i),
				], $items),
			];
		}
		$this->sendJson($out);
	}


	/** Přehled: actions running, lists waiting in the admin, bottle returns of today. */
	public function handleStats(): void
	{
		$today = \PrintListsService::now()->format('Y-m-d');
		$running = (int)$this->database->table('product_prices')
			->where('branches_id', $this->branchesId)
			->where('price_action > 0 AND price > price_action AND action_from IS NOT NULL AND action_from <= ? AND (action_to IS NULL OR action_to >= ?)', $today, $today)
			->count('DISTINCT products_id');
		$vykup = NULL;
		try {
			$vykup = (int)$this->vykup->period($this->branchesId, \VykupService::today(), \VykupService::today())['total']->kc;
		}
		catch (\Throwable $e) {
		}
		$this->sendJson(['akce' => $running, 'waiting' => $this->printLists->waitingCount($this->branchesId), 'vykup' => $vykup]);
	}


	/** Preview of the phone queue before sending: a PDF of the posted items (nothing saved). */
	public function handlePreview(): void
	{
		$items = json_decode((string)($this->getHttpRequest()->getPost('items') ?? '[]'), TRUE) ?: [];
		$pdf = $this->printLists->pdfFromItems($this->branchesId, $this->items($items));
		if (!$pdf) {
			$this->sendResponse(new TextResponse($this->trans('admin.pl.err.nothing')));
		}
		$this->getHttpResponse()->setContentType('application/pdf');
		$this->getHttpResponse()->setHeader('Content-Disposition', 'inline; filename="nahled-stitku.pdf"');
		$this->sendResponse(new TextResponse($pdf));
	}


	// ---------- helpers ----------

	/** Items from the phone, in the PrintListsService shape. */
	private function items(array $items): array
	{
		$out = [];
		foreach (array_slice($items, 0, 300) as $item) {
			if (!is_array($item)) {
				continue;
			}
			$out[] = [
				'products_id' => (int)($item['products_id'] ?? 0),
				'type_id' => (int)($item['type_id'] ?? 0),
				'qty' => (int)($item['qty'] ?? 1),
				'product_label_headers_id' => isset($item['header']) ? (int)$item['header'] : NULL,
				'overrides' => is_array($item['overrides'] ?? NULL) ? $item['overrides'] : [],
			];
		}

		return $out;
	}


	/** What the phone shows about a product: prices, action, unit, origin, photo. */
	private function productInfo(int $productsId): ?array
	{
		/** @var ?\ProductsEntity $product */
		$product = $this->productsRepo->findEntity($productsId);
		if (!$product) {
			return NULL;
		}
		$price = $product->getPrice($this->branchesId);
		$status = ProductLabelsControl::getActionStatus($price);
		$info = \PrintPreviewInfo::of($product, $this->branchesId, \ProductLabelsEntity::TYPE_SIZE_2);
		$action = $price && (float)$price->price_action > 0 ? (float)$price->price_action : NULL;
		$ref30 = $action !== NULL ? ($product->getRefPrice($this->branchesId) ?: ($price->price !== NULL ? (float)$price->price : NULL)) : NULL;
		$country = $product->getCountry();
		$image = NULL;
		try {
			$file = $product->getImage()?->file ?? NULL;
			$image = $file ? $this->imageFilter->process($file, 'adminGallery') : NULL;
		}
		catch (\Throwable $e) {
		}

		return [
			'id' => (int)$product->id,
			'name' => (string)$product->name,
			'ean' => $info['ean'],
			'weight' => $info['weight'],
			'origin' => $country?->name,
			'originId' => (int)($product->country_origin ?? 0),
			'image' => $image,
			'base' => $price && $price->price !== NULL ? (float)$price->price : NULL,
			'action' => $action,
			'ref30' => $ref30,
			'pct' => $action !== NULL && $ref30 ? (int)floor(($ref30 - $action) / $ref30 * 100) : 0,
			'status' => $status,
			'from' => $price?->action_from?->format('d.m.Y'),
			'to' => $price?->action_to?->format('d.m.Y'),
			'dates' => $price && $price->action_from ? $price->action_from->format('d.m.') . ' – ' . ($price->action_to ? $price->action_to->format('d.m.') : '…') : '',
			'unit' => $product->getPriceUnit(),
			'unitText' => $info['unitText'],
			'ingredients' => trim((string)($product->ingredients ?? '')) !== '',
			'thawed' => $product->isRozmrazeno(),
		];
	}
}
