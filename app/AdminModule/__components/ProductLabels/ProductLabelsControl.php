<?php

namespace App\AdminModule\Presenters;

class ProductLabelsControl extends \Nette\Application\UI\Control
{
	/** @var null|int */
	protected $itemId = NULL;

	/** @var null|int */
	protected $branchesId = NULL;


	/**
	 * @param \ProductsRepository $productsRepo
	 * @param \ProductLabelsRepository $productLabelsRepo
	 * @param \ProductLabelHeadersRepository $productLabelHeadersRepo
	 */
	public function __construct(protected \ProductsRepository $productsRepo, protected \ProductLabelsRepository $productLabelsRepo, protected \ProductLabelHeadersRepository $productLabelHeadersRepo)
	{
	}


	/**
	 * @return void
	 */
	public function render()
	{
		$this->template->setFile(__DIR__.'/ProductLabelsControl.latte');
		$this->template->render();
	}


	/**
	 * @return \ProductsEntity|null
	 */
	public function getItem(): ?\ProductsEntity
	{
		/** @var \ProductsEntity $item */
		if ($this->getItemId() && $item = $this->productsRepo->findEntity($this->getItemId())) {
			return $item;
		}

		return NULL;
	}


	/**
	 * @return int|null
	 */
	public function getItemId(): ?int
	{
		return $this->itemId;
	}


	/**
	 * @param int|null $itemId
	 * @return $this
	 */
	public function setItemId(?int $itemId): self
	{
		$this->itemId = $itemId;

		return $this;
	}


	/**
	 * @return int|null
	 */
	public function getBranchesId(): ?int
	{
		return $this->branchesId;
	}


	/**
	 * @param int|null $branchesId
	 * @return $this
	 */
	public function setBranchesId(?int $branchesId): self
	{
		$this->branchesId = $branchesId;

		return $this;
	}


	/**
	 * @param $typeId
	 * @param $idItem
	 * @return void
	 */
	public function handleIncrement($typeId, $idItem = NULL)
	{
		$branchesId = $this->getBranchesId();
		$adminUserId = $GLOBALS['adminUser']->id ?? 0;
		$product = $this->getItem();

		if ($idItem && $item = $this->productLabelsRepo->findEntity($idItem)) {
			$item->count = (int)$item->count + 1;
			$item->datetime_printed = NULL;
			$this->productLabelsRepo->update($item);
		}
		elseif ($typeId && $branchesId && $product) {
			$existing = $this->productLabelsRepo->findEntityBy([
				'products_id' => $product->id,
				'branches_id' => $branchesId,
				'admin_users_id' => $adminUserId,
				'type_id' => $typeId,
			]);

			if ($existing) {
				$existing->count = (int)$existing->count + 1;
				$existing->datetime_printed = NULL;
				$this->productLabelsRepo->update($existing);
			}
			else {
				$this->productLabelsRepo->insertForProduct($product, $branchesId, $adminUserId, (int)$typeId, 1);
			}
		}

		$this->redrawControl('list');
		if ($presenter = $this->getPresenter()) {
			$presenter->redrawControl('printSummary');
			$presenter->redrawControl('headerPrintBtn');
			$presenter->redrawControl('printPreviewSnippet');
		}
	}


	/**
	 * @param $typeId
	 * @param $idItem
	 * @return void
	 */
	public function handleDecrement($typeId, $idItem = NULL)
	{
		$branchesId = $this->getBranchesId();
		$adminUserId = $GLOBALS['adminUser']->id ?? 0;
		$product = $this->getItem();

		if ($idItem && $item = $this->productLabelsRepo->findEntity($idItem)) {
			$item->count = max(0, (int)$item->count - 1);
			$item->datetime_printed = NULL;
			$this->productLabelsRepo->update($item);
		}
		elseif ($typeId && $branchesId && $product) {
			$existing = $this->productLabelsRepo->findEntityBy([
				'products_id' => $product->id,
				'branches_id' => $branchesId,
				'admin_users_id' => $adminUserId,
				'type_id' => $typeId,
			]);

			if ($existing) {
				$existing->count = max(0, (int)$existing->count - 1);
				$existing->datetime_printed = NULL;
				$this->productLabelsRepo->update($existing);
			}
		}

		$this->redrawControl('list');
		if ($presenter = $this->getPresenter()) {
			$presenter->redrawControl('printSummary');
			$presenter->redrawControl('headerPrintBtn');
			$presenter->redrawControl('printPreviewSnippet');
		}
	}


	/**
	 * @param $typeId
	 * @param $idItem
	 * @param int $count
	 * @return void
	 */
	public function handleSaveLabelCount($typeId, $idItem = NULL, int $count = 0)
	{
		if ($idItem && $item = $this->productLabelsRepo->findEntity($idItem)) {
			$item->count = max(0, $count);
			$item->datetime_printed = NULL;
			$this->productLabelsRepo->update($item);
		}
		elseif ($typeId && $this->getBranchesId() && ($product = $this->getItem()) && $product->getPrice($this->getBranchesId())
			&& $this->productLabelHeadersRepo->findAll()->count('*')) {
			$this->productLabelsRepo->insertForProduct($product, $this->getBranchesId(), (int)$GLOBALS['adminUser']->id, (int)$typeId, $count);
		}

		$this->redrawControl('list');
		if ($presenter = $this->getPresenter()) {
			$presenter->redrawControl('printSummary');
			$presenter->redrawControl('headerPrintBtn');
			$presenter->redrawControl('printPreviewSnippet');
		}
	}
}