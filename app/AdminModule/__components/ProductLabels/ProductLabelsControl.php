<?php

namespace App\AdminModule\Presenters;

/**
 * One product row of "Admin - Produkty v2": the Cena / Akce cells (renderPrice),
 * the "Štítky k tisku" chips (renderChips) and the "Možnosti tisku a úprav" panel
 * (renderPanel). Each part is its own snippet, redrawn together after a change.
 */
class ProductLabelsControl extends \Nette\Application\UI\Control
{
	const STATUS_RUNNING = 'running';
	const STATUS_PLANNED = 'planned';
	const STATUS_ENDED = 'ended';
	const STATUS_NONE = 'none';

	/** Std template => its Akce twin, used while an action is running ("+ k vybraným"). */
	const AKCE_TWINS = [
		\ProductLabelsEntity::TYPE_A4 => \ProductLabelsEntity::TYPE_A4_AKCE,
		\ProductLabelsEntity::TYPE_A5 => \ProductLabelsEntity::TYPE_A5_AKCE,
		\ProductLabelsEntity::TYPE_SIZE_2 => \ProductLabelsEntity::TYPE_SIZE_2_AKCE,
		\ProductLabelsEntity::TYPE_SIZE_3 => \ProductLabelsEntity::TYPE_SIZE_3_AKCE,
	];

	/** @var null|int */
	protected $itemId = NULL;

	/** @var null|int */
	protected $branchesId = NULL;

	/** @var \ProductsEntity|null|false */
	private $item = FALSE;

	/** @var \ProductLabelsEntity|null|false the product's shared texts (type_id 0), FALSE = not loaded */
	private $shared = FALSE;

	/** @var array<int, string>|null */
	private static $headerPairs = NULL;

	/** @var array<int, \ProductLabelsEntity>|null see getLabels() */
	private $labels = NULL;


	public function __construct(protected \ProductsRepository $productsRepo, protected \ProductLabelsRepository $productLabelsRepo, protected \ProductLabelHeadersRepository $productLabelHeadersRepo)
	{
	}


	/**
	 * All three parts. Only the ajax snippet refresh calls this (Nette re-renders an
	 * invalidated child through render()); the page places the parts one by one.
	 */
	public function render(): void
	{
		$this->renderPart('all');
	}


	public function renderPrice(): void
	{
		$this->renderPart('price');
	}


	public function renderChips(): void
	{
		$this->renderPart('chips');
	}


	public function renderPanel(): void
	{
		$this->renderPart('panel');
	}


	private function renderPart(string $part): void
	{
		$this->template->setFile(__DIR__.'/ProductLabelsControl.latte');
		$this->template->part = $part;
		$this->template->render();
	}


	public function getItem(): ?\ProductsEntity
	{
		if ($this->item === FALSE) {
			$this->item = $this->getItemId() ? ($this->productsRepo->findEntity($this->getItemId()) ?: NULL) : NULL;
		}

		return $this->item;
	}


	public function getItemId(): ?int
	{
		return $this->itemId;
	}


	public function setItemId(?int $itemId): self
	{
		$this->itemId = $itemId;

		return $this;
	}


	public function getBranchesId(): ?int
	{
		return $this->branchesId;
	}


	public function setBranchesId(?int $branchesId): self
	{
		$this->branchesId = $branchesId;

		return $this;
	}


	/**
	 * "Probíhá" / "Naplánováno" / "Skončila" / "Bez akce" of the product's action price.
	 */
	public static function getActionStatus(?\ProductPricesEntity $price): string
	{
		if (!$price || !(float)$price->price_action || (float)$price->price_action >= (float)$price->price || !$price->action_from) {
			return self::STATUS_NONE;
		}
		$today = date('Y-m-d');
		if ($price->action_to && $price->action_to->format('Y-m-d') < $today) {
			return self::STATUS_ENDED;
		}
		if ($price->action_from->format('Y-m-d') > $today) {
			return self::STATUS_PLANNED;
		}

		return self::STATUS_RUNNING;
	}


	/** @return array<int, string> header id => name */
	public function getHeaderPairs(): array
	{
		if (self::$headerPairs === NULL) {
			self::$headerPairs = $this->productLabelHeadersRepo->findAll()->order('pos_order')->fetchPairs('id', 'name');
		}

		return self::$headerPairs;
	}


	/** Header a label of this type starts with (Akce templates "Akce", others the first one). */
	public function getDefaultHeaderId(int $typeId): ?int
	{
		$pairs = $this->getHeaderPairs();
		if (\ProductLabelsEntity::isAkceType($typeId) && isset($pairs[\ProductLabelsEntity::HEADER_TYPE_ACTION])) {
			return \ProductLabelsEntity::HEADER_TYPE_ACTION;
		}

		return array_key_first($pairs);
	}


	/**
	 * The admin's labels of the product per type, read from the table: the product's
	 * related('product_labels') can be cached by the list before a form in the same
	 * request (✎ editor) changed them. @return array<int, \ProductLabelsEntity>
	 */
	public function getLabels(): array
	{
		if ($this->labels === NULL) {
			$this->labels = [];
			$item = $this->getItem();
			if ($item && $this->getBranchesId()) {
				foreach ($this->productLabelsRepo->findAll()->where([
					'products_id' => $item->id,
					'branches_id' => $this->getBranchesId(),
					'admin_users_id' => $GLOBALS['adminUser']->id ?? 0,
				]) as $row) {
					$this->labels[(int)$row->type_id] = \ProductLabelsRepository::getEntity($row);
				}
			}
		}

		return $this->labels;
	}


	/** Labels queued for printing (count) per type. @return array<int, int> */
	public function getCounts(): array
	{
		$counts = [];
		foreach ($this->getLabels() as $typeId => $label) {
			if ($typeId > 0 && $label->isToPrint()) {
				$counts[$typeId] = (int)$label->count;
			}
		}

		return $counts;
	}


	/** TRUE when the label differs from what a fresh label of the product would print. */
	public function isEdited(?\ProductLabelsEntity $label): bool
	{
		$item = $this->getItem();
		// texts are shared by the product's templates: one without a label of its
		// own prints the shared (type_id 0) texts
		if (!$label && $item && $this->getBranchesId()) {
			if ($this->shared === FALSE) {
				$this->shared = $this->productLabelsRepo->findShared((int)$item->id, (int)$this->getBranchesId(), (int)($GLOBALS['adminUser']->id ?? 0));
			}
			$label = $this->shared;
		}
		if (!$label || !$item) {
			return FALSE;
		}

		return ($label->name && $label->name !== $item->name)
			|| (string)$label->sale_text !== ''
			|| (float)$label->sale_percent > 0
			|| trim((string)($label->getRow()->ingredients ?? '')) !== ''
			|| ($label->getRow()->price_unit ?? NULL)
			|| $label->hide_validity || $label->hide_ingredients || $label->is_more_species
			|| (string)$label->note_1 !== (string)$item->label_note_1
			|| (string)$label->note_2 !== (string)$item->label_note_2
			|| (string)$label->note_3 !== (string)$item->label_note_3;
	}


	private function findLabel(int $typeId): ?\ProductLabelsEntity
	{
		$item = $this->getItem();
		if (!$item || !$this->getBranchesId()) {
			return NULL;
		}
		$label = $this->productLabelsRepo->findEntityBy([
			'products_id' => $item->id,
			'branches_id' => $this->getBranchesId(),
			'admin_users_id' => $GLOBALS['adminUser']->id ?? 0,
			'type_id' => $typeId,
		]);

		return $label instanceof \ProductLabelsEntity ? $label : NULL;
	}


	/**
	 * Adds $delta labels of $typeId to the print queue (a printed label starts again from 0).
	 */
	public function changeCount(int $typeId, int $delta): void
	{
		$item = $this->getItem();
		if (!$item || !$this->getBranchesId() || !isset(\ProductLabelsEntity::TYPES_ARR[$typeId])
			|| ($delta > 0 && \ProductLabelsEntity::isLockedType($typeId))) {
			return;
		}

		if ($label = $this->findLabel($typeId)) {
			$current = $label->datetime_printed ? 0 : (int)$label->count;
			$label->count = max(0, $current + $delta);
			$label->datetime_printed = NULL;
			$this->productLabelsRepo->update($label);
		}
		elseif ($delta > 0) {
			$this->productLabelsRepo->insertForProduct($item, $this->getBranchesId(), (int)($GLOBALS['adminUser']->id ?? 0), $typeId, $delta);
		}
	}


	/** "Možnosti tisku" panel, loaded when the row is first opened (the list renders it empty). */
	public function handleLoadPanel(): void
	{
		$this->redrawControl('panel');
	}


	public function handleIncrement(int $typeId): void
	{
		$this->changeCount($typeId, 1);
		$this->redrawAll();
	}


	public function handleDecrement(int $typeId): void
	{
		$this->changeCount($typeId, -1);
		$this->redrawAll();
	}


	public function handleSaveLabelCount(int $typeId, int $count = 0): void
	{
		$label = $this->findLabel($typeId);
		$current = $label && $label->isToPrint() ? (int)$label->count : 0;
		$this->changeCount($typeId, max(0, $count) - $current);
		$this->redrawAll();
	}


	/** Hlavička chosen in a chip or card: applies to every template of the product. */
	public function handleSetHeader(int $typeId, int $headerId): void
	{
		$item = $this->getItem();
		if ($item && $this->getBranchesId() && isset($this->getHeaderPairs()[$headerId]) && isset(\ProductLabelsEntity::TYPES_ARR[$typeId])) {
			$this->productLabelsRepo->setHeaderForProduct($item, $this->getBranchesId(), (int)($GLOBALS['adminUser']->id ?? 0), $headerId);
		}
		$this->redrawAll();
	}


	public function redrawAll(): void
	{
		$this->item = FALSE;
		$this->shared = FALSE;
		$this->labels = NULL;
		$this->redrawControl('price');
		$this->redrawControl('chips');
		$this->redrawControl('panel');
		if ($presenter = $this->getPresenter()) {
			$presenter->redrawControl('printSummary');
			$presenter->redrawControl('printPreviewSnippet');
		}
	}
}
