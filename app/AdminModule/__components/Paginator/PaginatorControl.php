<?php

namespace App\AdminModule\Presenters;

use \Nette\Database\Table\Selection;
use \Nette\Http\SessionSection;
use \Nette\Utils\Paginator;

class PaginatorControl extends BaseControl
{
	/** @var Paginator */
	protected $paginator;

	/** @var SessionSection */
	protected $session;

	/** @var string */
	protected $sessionKey = '';

	/** @var callable */
	protected $onPageChange;

	/** @var bool */
	protected $allowedAjax = FALSE;

	/** @var int  */
	protected $defaultItemsPerPage = 20;

	/** @var int  */
	protected $pageSpread = 30;




	/** PAGINATOR */

	/**
	 * @param Selection $selection
	 * @return Selection
	 */
	public function applyPaging(Selection $selection): Selection
	{
		return $selection->limit($this->paginator->getLength(), $this->paginator->getOffset());
	}

	/**
	 * @return Paginator
	 */
	public function getPaginator(): Paginator
	{
		return $this->paginator;
	}


	/**
	 * @param Paginator $paginator
	 * @return PaginatorControl
	 */
	public function setPaginator(Paginator $paginator): PaginatorControl
	{
		$this->paginator = $paginator;
		$this->prepareSession();

		$this->paginator->setItemsPerPage($this->getItemsPerPage());
		$this->paginator->setPage($this->getPage());

		return $this;
	}



	/** ITEMS PER PAGE */

	/**
	 * @param int $itemsPerPage
	 * @return void
	 * @throws \Nette\Application\AbortException
	 */
	public function handleChangeItemsPerPage(int $itemsPerPage)
	{
		$this->setItemsPerPage($itemsPerPage);
		$this->redirect('this');
	}

	/**
	 * @param int $defaultItemsPerPage
	 */
	public function setDefaultItemsPerPage(int $defaultItemsPerPage): void
	{
		$this->defaultItemsPerPage = $defaultItemsPerPage;
	}

	/**
	 * @return int
	 */
	public function getDefaultItemsPerPage(): int
	{
		return $this->defaultItemsPerPage;
	}

	/**
	 * @return int
	 */
	public function getItemsPerPage(): int
	{
		return $this->session->paginator[$this->getSessionKey()]['itemsPerPage'] ?? $this->defaultItemsPerPage;
	}

	/**
	 * @param int $itemsPerPage
	 * @return void
	 */
	public function setItemsPerPage(int $itemsPerPage): void
	{
		$this->prepareSession();
		$this->session->paginator[$this->getSessionKey()]['itemsPerPage'] = max(1, (int)$itemsPerPage);
	}



	/** PAGE */

	/**
	 * @param callable $onPageChange
	 * @return PaginatorControl
	 */
	public function setOnPageChange(callable $onPageChange): PaginatorControl
	{
		$this->onPageChange = $onPageChange;

		return $this;
	}

	/**
	 * @param int $page
	 * @return void
	 * @throws \Nette\Application\AbortException
	 */
	public function handleChangePage(int $page)
	{
		$this->setPage($page);

		if ($this->onPageChange) {
			$callback = $this->onPageChange;
			$callback($this, $page);
		}
		else {
			$this->redirect('this');
		}
	}

	/**
	 * @return int
	 */
	public function getPage(): int
	{
		return $this->session->paginator[$this->getSessionKey()]['page'] ?? 1;
	}

	/**
	 * @param int $page
	 * @return void
	 */
	public function setPage(int $page): void
	{
		$this->prepareSession();
		$this->session->paginator[$this->getSessionKey()]['page'] = max(1, (int)$page);
	}



	/** AJAX */

	/**
	 * @return bool
	 */
	public function isAllowedAjax(): bool
	{
		return $this->allowedAjax;
	}

	/**
	 * @param bool $allowedAjax
	 * @return PaginatorControl
	 */
	public function setAllowedAjax(bool $allowedAjax): PaginatorControl
	{
		$this->allowedAjax = $allowedAjax;

		return $this;
	}



	/** SESSION */

	/**
	 * @param SessionSection $session
	 * @return PaginatorControl
	 */
	public function setSession(SessionSection $session): PaginatorControl
	{
		$this->session = $session;
		$this->prepareSession();

		return $this;
	}

	/**
	 * @return void
	 */
	public function prepareSession(): void
	{
		if (!isset($this->session->paginator)) {
			$this->session->paginator = [];
		}

		if (!isset($this->session->paginator[$this->getSessionKey()])) {
			$this->session->paginator[$this->getSessionKey()] = [];
		}
	}

	/**
	 * @return string
	 */
	public function getSessionKey(): string
	{
		return $this->sessionKey ?: 'general';
	}

	/**
	 * @param string $sessionKey
	 */
	public function setSessionKey(string $sessionKey)
	{
		$this->sessionKey = $sessionKey;
	}


	/**
	 * @return array
	 */
	public function getPages(): array
	{
		$spread = $this->getPageSpread();

		$pages = [];
		if($this->getPaginator()->page > 1){
			for($i = ((1 > $this->getPaginator()->page - $spread) ? 1 : $this->getPaginator()->page - $spread); $i < $this->getPaginator()->page; $i++){
				$pages[] = $i;
			}
		}

		$pages[] = $this->getPaginator()->page;
		if($this->getPaginator()->page < $this->getPaginator()->pageCount){
			for($i = ($this->getPaginator()->page + 1); $i <= (($this->getPaginator()->pageCount < $this->getPaginator()->page + $spread) ? $this->getPaginator()->pageCount : $this->getPaginator()->page + $spread); $i++){
				$pages[] = $i;
			}
		}

		return $pages;
	}


	/**
	 * @return int
	 */
	public function getPageSpread(): int
	{
		return $this->pageSpread;
	}
}
