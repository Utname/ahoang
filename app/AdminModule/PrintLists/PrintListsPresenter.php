<?php

namespace App\AdminModule\Presenters;

use Nette\Application\Responses\TextResponse;
use Nette\Application\UI\Form;
use Nette\Database\Table\ActiveRow;

/**
 * Tisk z mobilu (predani-tisk-stitku "Admin – Tisk z mobilu"): label lists scanned on the
 * phone, checked and printed here. One branch at a time (the admin's branches, chosen in the
 * session). Row changes (count, header, ✎ Upravit) touch only the list; "Cena a akce" saves
 * the product card like the Produkty list (ProductPriceSaver). Rules in \PrintListsService.
 */
class PrintListsPresenter extends BaseModulePresenter
{
	/** @var \PrintListsService @inject */
	public $printLists;

	/** @var \ProductPriceSaver @inject */
	public $productPriceSaver;

	/** @var \BranchesRepository @inject */
	public $branchesRepo;

	/** @var \ProductsRepository @inject */
	public $productsRepo;

	/** @var \ProductLabelHeadersRepository @inject */
	public $headersRepo;

	/** @var \CountriesRepository @inject */
	public $countriesRepo;

	/** @var array<int, string> */
	protected $branchesPairs = [];

	/** @var int */
	protected $branchesId = 0;

	/** @var ?ActiveRow the list shown on the right */
	protected $list = NULL;

	/** @var ?ActiveRow item of the ✎ Upravit form */
	protected $editItem = NULL;


	protected function startup()
	{
		parent::startup();
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


	public function beforeRender()
	{
		parent::beforeRender();
		$this->template->plBranchesPairs = $this->branchesPairs;
		$this->template->plBranchesId = $this->branchesId;
		$this->template->plCanEdit = $this->hasPermission('edit');
	}


	public function handleBranch(int $id): void
	{
		if (isset($this->branchesPairs[$id])) {
			$this->getSession('printLists')->branch = $id;
			$this->getSession('printLists')->list = NULL;
		}
		$this->redirect('this', ['list' => NULL]);
	}


	// ---------- the page ----------

	public function actionDefault(?string $filter = NULL, ?string $date = NULL, ?int $list = NULL): void
	{
		$section = $this->getSession('printLists');
		if ($filter !== NULL) {
			$section->filter = in_array($filter, ['waiting', 'printed', 'all'], TRUE) ? $filter : 'waiting';
		}
		if ($date !== NULL) {
			$section->date = preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? $date : '';
		}
		if ($list !== NULL) {
			$section->list = $list;
		}
		$this->loadLists();
	}


	/** Lists of the filter and the selected one; again at render, after a signal changed them. */
	private function loadLists(): void
	{
		$section = $this->getSession('printLists');
		$filter = $section->filter ?? 'waiting';
		$date = $section->date ?? \PrintListsService::now()->format('Y-m-d');

		$lists = $this->printLists->lists($this->branchesId, $filter, $date ?: NULL);
		$selectedId = (int)($section->list ?? 0);
		$this->list = NULL;
		foreach ($lists as $row) {
			if ((int)$row->id === $selectedId) {
				$this->list = $row;
			}
		}
		$this->list ??= $lists[0] ?? NULL;

		$this->template->filter = $filter;
		$this->template->date = $date;
		$this->template->lists = $lists;
		$this->template->counts = $this->printLists->counts($this->branchesId, $date ?: NULL);
	}


	public function renderDefault(): void
	{
		$this->loadLists();
		$cards = [];
		foreach ($this->template->lists as $row) {
			$items = $this->printLists->items((int)$row->id);
			$cards[] = $this->listCard($row, $items);
		}
		$this->template->cards = $cards;
		$this->template->sel = $this->list ? $this->listCard($this->list, $this->printLists->items((int)$this->list->id)) : NULL;
		$this->template->groups = $this->list ? $this->groups($this->list) : [];
		$this->template->headersPairs = $this->headersRepo->findAll()->order('pos_order')->fetchPairs('id', 'name');
		$this->template->countriesPairs = $this->countriesRepo->findAll()->order('pos_order')->fetchPairs('id', 'name');
		$this->template->typeNames = $this->global['productLabelTypes'];
		$this->template->editItem = $this->editItem;
		$this->template->pp = $this->list ? $this->printPreview($this->list, $this->template->groups) : NULL;
		if ($this->list) {
			$this['addItemForm']->setDefaults(['list_id' => (int)$this->list->id]);
			if ($lastType = (int)($this->getSession('printLists')->lastType ?? 0)) {
				$this['addItemForm']['type_id']->setDefaultValue(in_array($lastType, \PrintListsService::types(), TRUE) ? $lastType : NULL);
			}
		}
	}


	/** Náhled tisku v3 of a list (shared __components/PrintPreview/printPreview.latte). */
	private function printPreview(ActiveRow $list, array $groups): array
	{
		$id = (int)$list->id;
		$ppGroups = [];
		$count = 0;
		$pages = 0;
		foreach ($groups as $g) {
			$cards = [];
			foreach ($g['rows'] as $r) {
				$cards[] = $r + [
					'headerUrl' => $this->link('header!', ['itemId' => $r['id'], 'headerId' => 999999]),
					'originUrl' => $this->link('origin!', ['itemId' => $r['id'], 'countryId' => 999999]),
					'decUrl' => $this->link('qty!', ['itemId' => $r['id'], 'delta' => -1]),
					'incUrl' => $this->link('qty!', ['itemId' => $r['id'], 'delta' => 1]),
					'removeUrl' => $this->link('remove!', ['itemId' => $r['id']]),
					'editUrl' => $this->link('edit!', ['itemId' => $r['id']]),
					'editTarget' => '#modal-plItem',
					'viewUrl' => $this->link('pdf', ['id' => $id, 'itemId' => $r['id'], 'one' => TRUE]),
					'pdfUrl' => $this->link('pdf', ['id' => $id, 'itemId' => $r['id'], 'download' => TRUE]),
				];
			}
			$count += $g['count'];
			$pages += $g['pages'];
			$ppGroups[] = [
				'dims' => $g['dims'] . ' mm',
				'viewUrl' => $this->link('pdf', ['id' => $id, 'typeId' => $g['typeId']]),
				'pdfUrl' => $this->link('pdf', ['id' => $id, 'typeId' => $g['typeId'], 'download' => TRUE]),
				'cards' => $cards,
			] + $g;
		}

		return [
			'totalCount' => $count,
			'totalPages' => $pages,
			'clearUrl' => NULL,
			'clearConfirm' => '',
			'viewAllUrl' => $this->link('pdf', ['id' => $id]),
			'downloadAllUrl' => $this->link('pdf', ['id' => $id, 'download' => TRUE]),
			'headersPairs' => $this->template->headersPairs,
			'countriesPairs' => $this->template->countriesPairs,
			'emptyHint' => $this->trans('admin.pl.noItems'),
			'groups' => $ppGroups,
		];
	}


	// ---------- rows ----------

	private function redrawList(): void
	{
		if (!$this->isAjax()) {
			$this->redirect('this');
		}
		$this->redrawControl('lists');
		$this->redrawControl('detail');
		$this->redrawControl('counts');
		$this->redrawControl('preview');
	}


	public function handleQty(int $itemId, int $delta): void
	{
		$this->checkPermission('edit');
		$this->tryAction(fn() => $this->printLists->changeQty($this->branchesId, $itemId, $delta > 0 ? 1 : -1));
		$this->redrawList();
	}


	public function handleRemove(int $itemId): void
	{
		$this->checkPermission('edit');
		$this->printLists->removeItem($this->branchesId, $itemId);
		$this->redrawList();
	}


	public function handleHeader(int $itemId, int $headerId): void
	{
		$this->checkPermission('edit');
		$this->printLists->setHeader($this->branchesId, $itemId, $headerId);
		$this->redrawList();
	}


	/** Původ chosen in the print preview card: an override of this print. */
	public function handleOrigin(int $itemId, int $countryId = 0): void
	{
		$this->checkPermission('edit');
		if ($item = $this->printLists->item($this->branchesId, $itemId)) {
			$overrides = \PrintListsService::overrides($item);
			$overrides['origin_countries_id'] = $countryId ?: NULL;
			$this->printLists->saveItem($this->branchesId, $itemId, (int)$item->qty, (int)$item->product_label_headers_id, $overrides);
		}
		$this->redrawList();
	}


	public function handlePrinted(int $listId, bool $printed = TRUE): void
	{
		$this->checkPermission('edit');
		$this->printLists->markPrinted($this->branchesId, $listId, $this->adminUserId(), $printed);
		$this->flashMessage($this->trans($printed ? 'admin.pl.markedPrinted' : 'admin.pl.markedWaiting'), 'success');
		$this->redirect('this');
	}


	public function handleDeleteList(int $listId): void
	{
		$this->checkPermission('edit');
		$this->printLists->delete($this->branchesId, $listId);
		$this->getSession('printLists')->list = NULL;
		$this->flashMessage($this->trans('admin.pl.deleted'), 'success');
		$this->redirect('this');
	}


	/** "Cena a akce" of a row: saves the product card (same request as the Produkty list). */
	public function handleUpdatePricesAll($itemId, $productsId, $price = NULL, $price_action = NULL, $action_from = NULL, $action_to = NULL, $branchId = NULL, $currencyId = NULL, $price_unit = NULL): void
	{
		$this->checkPermission('edit');
		$this->productPriceSaver->save(
			(int)$productsId,
			$this->branchesId,
			array_map('intval', array_keys($this->branchesPairs)),
			$itemId ? (int)$itemId : NULL,
			$price, $price_action, $action_from, $action_to, $price_unit,
		);
		$this->redrawList();
	}


	// ---------- lists made on the PC ----------

	/** "+ Nový seznam": an empty list of this PC, filled with the add row. */
	public function handleNewList(): void
	{
		$this->checkPermission('edit');
		$id = $this->printLists->create($this->branchesId, $this->adminUserId(), 'PC', 'pc', [], TRUE);
		$section = $this->getSession('printLists');
		$section->list = $id;
		$section->filter = 'waiting';
		$section->date = \PrintListsService::now()->format('Y-m-d');
		$this->flashMessage(sprintf($this->trans('admin.pl.created'), $this->printLists->get($this->branchesId, $id)->list_no), 'success');
		$this->redirect('this', ['list' => $id]);
	}


	/** Suggestions for the add row (EAN start or name): [{label, value, id}] for jQuery UI. */
	public function handleSearch(string $term = ''): void
	{
		$data = [];
		foreach ($this->printLists->searchProducts($term) as $product) {
			$data[] = ['label' => $product['name'] . ($product['ean'] ? ' · ' . $product['ean'] : ''), 'value' => $product['ean'] ?: $product['name'], 'id' => $product['id']];
		}
		$this->sendJson($data);
	}


	protected function createComponentAddItemForm(): Form
	{
		$form = new Form();
		$form->setTranslator($this->translator);
		$types = [];
		foreach (\PrintListsService::types() as $typeId) {
			$types[$typeId] = $this->global['productLabelTypes'][$typeId] ?? (string)$typeId;
		}
		$form->addHidden('list_id');
		$form->addHidden('products_id');
		$form->addText('product')->setRequired('admin.pl.err.product');
		$form->addSelect('type_id', NULL, $types)->setTranslator(NULL);
		$form->addInteger('qty')->setDefaultValue(1)->setRequired()->addRule(Form::RANGE, NULL, [1, \PrintListsService::MAX_QTY]);
		$form->addSelect('product_label_headers_id', NULL, ['' => $this->trans('admin.pl.headerAuto')] + $this->headersRepo->findAll()->order('pos_order')->fetchPairs('id', 'name'))->setTranslator(NULL);
		$form->addSubmit('add');
		$form->onSuccess[] = function (Form $form, array $values): void {
			$this->checkPermission('edit');
			$productsId = (int)$values['products_id'] ?: $this->printLists->findProductId((string)$values['product']);
			try {
				if (!$productsId) {
					throw new \DomainException('admin.pl.err.product');
				}
				$this->printLists->addItem($this->branchesId, (int)$values['list_id'], $productsId, (int)$values['type_id'], (int)$values['qty'], $values['product_label_headers_id'] ? (int)$values['product_label_headers_id'] : NULL);
				// the template and the count stay for the next scan
				$this->getSession('printLists')->lastType = (int)$values['type_id'];
				$this->flashMessage(sprintf($this->trans('admin.pl.added'), $values['product']), 'success');
			}
			catch (\DomainException $e) {
				$this->flashMessage($this->trans($e->getMessage()), 'danger');
			}
			if ($this->isAjax()) {
				$form->setValues(['product' => '', 'products_id' => ''], FALSE);
				$this->redrawList();
				$this->redrawControl('flashMessages');
				return;
			}
			$this->redirect('this');
		};

		return $form;
	}


	// ---------- ✎ Upravit (this print only) ----------

	public function handleEdit(int $itemId): void
	{
		$this->checkPermission('edit');
		$this->editItem = $this->printLists->item($this->branchesId, $itemId);
		if ($this->editItem) {
			$o = \PrintListsService::overrides($this->editItem);
			$product = $this->productsRepo->findEntity((int)$this->editItem->products_id);
			$price = $product?->getPrice($this->branchesId);
			$this['itemForm']->setDefaults([
				'item_id' => (int)$this->editItem->id,
				'product_label_headers_id' => (int)$this->editItem->product_label_headers_id,
				'qty' => (int)$this->editItem->qty,
				'name' => $o['name'] ?? (string)($product->name ?? ''),
				'price' => $o['price'] ?? ($price && $price->price !== NULL ? (float)$price->price : ''),
				'price_action' => $o['price_action'] ?? ($price && $price->price_action ? (float)$price->price_action : ''),
				'sale_percent' => $o['sale_percent'] ?? '',
				'price_unit' => $o['price_unit'] ?? '',
				'is_more_species' => (int)($o['is_more_species'] ?? 0),
				'origin_countries_id' => $o['origin_countries_id'] ?? ($product->country_origin ?? NULL),
				'hide_validity' => (bool)($o['hide_validity'] ?? 0),
				'hide_ingredients' => (bool)($o['hide_ingredients'] ?? 0),
				'thawed' => (bool)($o['thawed'] ?? ($product?->isRozmrazeno() ?? FALSE)),
				'sale_text' => $o['sale_text'] ?? '',
				'ingredients' => $o['ingredients'] ?? '',
				'note_1' => $o['note_1'] ?? (string)($product->label_note_1 ?? ''),
				'note_2' => $o['note_2'] ?? (string)($product->label_note_2 ?? ''),
				'note_3' => $o['note_3'] ?? (string)($product->label_note_3 ?? ''),
			]);
		}
		$this->redrawControl('itemForm');
	}


	public function handleReset(int $itemId): void
	{
		$this->checkPermission('edit');
		if ($item = $this->printLists->item($this->branchesId, $itemId)) {
			$this->printLists->saveItem($this->branchesId, $itemId, (int)$item->qty, (int)$item->product_label_headers_id, NULL);
			$this->flashMessage($this->trans('admin.pl.resetDone'), 'success');
		}
		$this->redrawList();
		$this->redrawControl('flashMessages');
	}


	protected function createComponentItemForm(): Form
	{
		$form = new Form();
		$form->setTranslator($this->translator);
		$form->addHidden('item_id');
		$form->addSelect('product_label_headers_id', NULL, $this->headersRepo->findAll()->order('pos_order')->fetchPairs('id', 'name'))->setTranslator(NULL);
		$form->addInteger('qty')->setRequired()->addRule(Form::RANGE, NULL, [1, \PrintListsService::MAX_QTY]);
		$form->addText('name')->setMaxLength(255);
		$form->addText('price');
		$form->addText('price_action');
		$form->addText('sale_percent');
		$form->addSelect('price_unit', NULL, ['' => 'admin.pv.priceUnitProduct'] + array_combine(\ProductsEntity::PRICE_UNITS, \ProductsEntity::PRICE_UNITS));
		$form->addSelect('is_more_species', NULL, $this->global['bool'])->setTranslator(NULL);
		$form->addSelect('origin_countries_id', NULL, $this->countriesRepo->findAll()->order('pos_order')->fetchPairs('id', 'name'))->setPrompt('–')->setTranslator(NULL);
		$form->addCheckbox('hide_validity');
		$form->addCheckbox('hide_ingredients');
		$form->addCheckbox('thawed');
		$form->addText('sale_text')->setMaxLength(255);
		$form->addTextArea('ingredients');
		$form->addText('note_1')->setMaxLength(255);
		$form->addText('note_2')->setMaxLength(255);
		$form->addText('note_3')->setMaxLength(255);
		$form->addSubmit('save');
		$form->onSuccess[] = function (Form $form, array $values): void {
			$this->checkPermission('edit');
			$item = $this->printLists->item($this->branchesId, (int)$values['item_id']);
			if ($item) {
				$product = $this->productsRepo->findEntity((int)$item->products_id);
				$price = $product?->getPrice($this->branchesId);
				$num = static fn($v) => $v === '' || $v === NULL ? NULL : (float)str_replace(',', '.', (string)$v);
				$overrides = [
					// only what differs from the product card is kept
					'name' => trim((string)$values['name']) !== (string)($product->name ?? '') ? trim((string)$values['name']) : NULL,
					'price' => $num($values['price']) !== NULL && $num($values['price']) !== ($price && $price->price !== NULL ? (float)$price->price : NULL) ? $num($values['price']) : NULL,
					'price_action' => $num($values['price_action']) !== NULL && $num($values['price_action']) !== ($price && $price->price_action ? (float)$price->price_action : NULL) ? $num($values['price_action']) : NULL,
					'sale_percent' => (int)$values['sale_percent'] > 0 ? (int)$values['sale_percent'] : NULL,
					'price_unit' => $values['price_unit'] ?: NULL,
					'is_more_species' => (int)$values['is_more_species'] ?: NULL,
					'origin_countries_id' => (int)$values['origin_countries_id'] && (int)$values['origin_countries_id'] !== (int)($product->country_origin ?? 0) ? (int)$values['origin_countries_id'] : NULL,
					'hide_validity' => $values['hide_validity'] ? 1 : NULL,
					'hide_ingredients' => $values['hide_ingredients'] ? 1 : NULL,
					'thawed' => $values['thawed'] && !$product?->isRozmrazeno() ? 1 : NULL,
					'sale_text' => trim((string)$values['sale_text']) ?: NULL,
					'ingredients' => trim((string)$values['ingredients']) ?: NULL,
					'note_1' => (string)$values['note_1'] !== (string)($product->label_note_1 ?? '') ? (string)$values['note_1'] : NULL,
					'note_2' => (string)$values['note_2'] !== (string)($product->label_note_2 ?? '') ? (string)$values['note_2'] : NULL,
					'note_3' => (string)$values['note_3'] !== (string)($product->label_note_3 ?? '') ? (string)$values['note_3'] : NULL,
				];
				$this->printLists->saveItem($this->branchesId, (int)$item->id, (int)$values['qty'], (int)$values['product_label_headers_id'], $overrides);
				$this->flashMessage($this->trans('admin.pv.saved'), 'success');
			}
			$this->redrawList();
			$this->redrawControl('flashMessages');
		};

		return $form;
	}


	// ---------- PDF / printing ----------

	/** PDF of the list, one template type of it or one item (inline or as a download). */
	public function actionPdf(int $id, ?int $typeId = NULL, ?int $itemId = NULL, bool $download = FALSE, bool $one = FALSE): void
	{
		$list = $this->printLists->get($this->branchesId, $id);
		$pdf = $list ? $this->printLists->pdf($this->branchesId, $id, $typeId, $itemId, $one) : NULL;
		if (!$pdf) {
			$this->flashMessage($this->trans('admin.pl.err.nothing'), 'danger');
			$this->redirect('default');
		}
		$name = 'stitky-' . $list->list_no . ($typeId ? '-' . $typeId : '') . '.pdf';
		$response = $this->getHttpResponse();
		$response->setContentType('application/pdf');
		$response->setHeader('Content-Disposition', ($download ? 'attachment' : 'inline') . '; filename="' . $name . '"');
		$this->sendResponse(new TextResponse($pdf));
	}


	/** "Tisknout": the PDF in a page that opens the print dialog; the list is marked printed. */
	public function actionPrint(int $id): void
	{
		$list = $this->printLists->get($this->branchesId, $id);
		if (!$list) {
			$this->redirect('default');
		}
		if ($this->hasPermission('edit')) {
			$this->printLists->markPrinted($this->branchesId, $id, $this->adminUserId());
		}
		$this->setLayout(FALSE);
		$this->template->list = $list;
		$this->template->pdfUrl = $this->link('pdf', ['id' => $id]);
		$this->template->backUrl = $this->link('default');
	}


	// ---------- view data ----------

	/** @param list<ActiveRow> $items */
	private function listCard(ActiveRow $list, array $items): array
	{
		$stats = \PrintListsService::stats($items);
		$sender = $list->admin_users_id ? $this->database->table('admin_users')->get((int)$list->admin_users_id) : NULL;
		$chips = [];
		foreach ($stats['perType'] as $typeId => $count) {
			$chips[] = ['label' => ($this->global['productLabelTypes'][$typeId] ?? $typeId) . ' × ' . $count, 'akce' => \ProductLabelsEntity::isAkceType($typeId)];
		}

		return [
			'id' => (int)$list->id,
			'no' => $list->list_no,
			'sender' => $sender ? (string)$sender->name : '–',
			'device' => (string)$list->device,
			'source' => (string)$list->source,
			'time' => $list->created_at->format('H:i'),
			'date' => $list->created_at->format('d.m.Y'),
			'status' => $list->status,
			'printedAt' => $list->printed_at?->format('d.m. H:i'),
			'chips' => $chips,
		] + $stats;
	}


	/** Items of a list per template type, with what the rows show. */
	private function groups(ActiveRow $list): array
	{
		$groups = [];
		foreach ($this->printLists->items((int)$list->id) as $item) {
			$typeId = (int)$item->type_id;
			$def = \ProductLabelsEntity::TYPES_ARR[$typeId];
			$groups[$typeId] ??= [
				'typeId' => $typeId,
				'name' => $this->global['productLabelTypes'][$typeId] ?? (string)$typeId,
				'dims' => $def['dims'],
				'isAkce' => \ProductLabelsEntity::isAkceType($typeId),
				'perPage' => (int)$def['perPage'],
				'count' => 0,
				'rows' => [],
			];
			$groups[$typeId]['count'] += (int)$item->qty;
			$groups[$typeId]['rows'][] = $this->rowInfo($item);
		}
		foreach ($groups as &$group) {
			$group['pages'] = (int)ceil($group['count'] / max(1, $group['perPage']));
		}

		return array_values($groups);
	}


	/** One item as the row / preview card shows it: prices of the card + this print's overrides. */
	private function rowInfo(ActiveRow $item): array
	{
		$o = \PrintListsService::overrides($item);
		$product = $this->productsRepo->findEntity((int)$item->products_id);

		return [
			'id' => (int)$item->id,
			'typeId' => (int)$item->type_id,
			'productsId' => (int)$item->products_id,
			'qty' => (int)$item->qty,
			'headerId' => (int)$item->product_label_headers_id,
			'originId' => (int)($o['origin_countries_id'] ?? ($product->country_origin ?? 0)),
			'edited' => (bool)$o,
		] + \PrintPreviewInfo::of($product, $this->branchesId, (int)$item->type_id, $o);
	}


	private function tryAction(callable $action): void
	{
		try {
			$action();
		}
		catch (\DomainException $e) {
			$this->flashMessage($this->trans($e->getMessage()), 'danger');
		}
	}


	private function adminUserId(): ?int
	{
		return isset($this->adminUser->id) ? (int)$this->adminUser->id : NULL;
	}
}
