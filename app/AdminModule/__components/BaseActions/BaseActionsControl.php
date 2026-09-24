<?php

namespace App\AdminModule\Presenters;

class BaseActionsControl extends \Nette\Application\UI\Control
{
	/** @var bool */
	protected $allowedSorting = TRUE;


	/**
	 * BaseActionsControl constructor.
	 * @param \AdminPermissionsService $adminPermissionsService
	 * @param \BaseRepository $repository
	 */
	public function __construct(protected \AdminPermissionsService $adminPermissionsService, protected \BaseRepository $repository)
	{
	}


	/**
	 * @param $item
	 * @param $iterator
	 */
	public function render($item, $iterator)
	{
		$this->template->aps = $this->adminPermissionsService;
		$this->template->repository = $this->repository;
		$this->template->item = $item;
		$this->template->iterator = $iterator;

		$this->template->setFile(__DIR__.'/BaseActionsControl.latte');
		$this->template->render();
	}


	/**
	 * @return bool
	 */
	public function isAllowedSorting(): bool
	{
		return $this->allowedSorting;
	}


	/**
	 * @param bool $allowedSorting
	 * @return BaseActionsControl
	 */
	public function setAllowedSorting(bool $allowedSorting): BaseActionsControl
	{
		$this->allowedSorting = $allowedSorting;

		return $this;
	}
}