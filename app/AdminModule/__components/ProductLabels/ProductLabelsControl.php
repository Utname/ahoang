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
	 * @param int $count
	 * @return void
	 */
	public function handleSaveLabelCount($typeId, $idItem = NULL, int $count = 0)
	{
		if ($idItem && $item = $this->productLabelsRepo->findEntity($idItem)) {
			$item->count = $count;
			$item->datetime_printed = NULL;
			$this->productLabelsRepo->update($item);
		}
		elseif ($typeId && $this->getBranchesId() && ($product = $this->getItem()) && ($productPrice = $product->getPrice($this->getBranchesId())) && ($labelHeader = $this->productLabelHeadersRepo->findAll()
				->order('pos_order')
				->limit(1)
				->fetch())) {
			$values = [
				'product_label_headers_id' => $labelHeader->id,
				'products_id' => $product->id,
				'branches_id' => $this->getBranchesId(),
				'admin_users_id' => $GLOBALS['adminUser']->id,
				'count' => $count,
				'datetime_printed' => NULL,
				'type_id' => $typeId,
				'sale_text' => '',
				'sale_percent' => $productPrice->getActionPercents(TRUE),
				'is_more_species' => 0,
				'price_text' => \ProductLabelsEntity::PRICE_TYPE_CZK,
				'origin_countries_id' => $product->country_origin ?: NULL,
				'name' => $product->name,
				'note_1' => $product->label_note_1,
				'note_2' => $product->label_note_2,
				'note_3' => $product->label_note_3,
			];

			$this->productLabelsRepo->insert($values);
		}

		$this->redrawControl('list');
	}
}