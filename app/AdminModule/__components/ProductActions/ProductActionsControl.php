<?php

namespace App\AdminModule\Presenters;

class ProductActionsControl extends \Nette\Application\UI\Control
{
	/** @var null|int */
	protected $itemId = NULL;

	/** @var null|int */
	protected $branchesId = NULL;

	/** @var array */
	protected $branchesPairs = [];


	/**
	 * @param \ProductsRepository $productsRepo
	 */
	public function __construct(protected \ProductsRepository $productsRepo)
	{
	}


	/**
	 * @return void
	 */
	public function render()
	{
		$this->template->setFile(__DIR__.'/ProductActionsControl.latte');
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
	 * @return array
	 */
	public function getBranchesPairs(): array
	{
		return $this->branchesPairs;
	}


	/**
	 * @param array $branchesPairs
	 * @return $this
	 */
	public function setBranchesPairs(array $branchesPairs): self
	{
		$this->branchesPairs = $branchesPairs;

		return $this;
	}
}