<?php

namespace App\FrontModule\Components;

use Nette\Application\AbortException;
use Nette\Database\Table\Selection;
use Nette\Http\SessionSection;
use Nette\Utils\Callback;
use Nette\Utils\Paginator;

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
	protected $isAjax = FALSE;

	/** @var array */
	protected $urlParameters = [];



	/**
	 * @return Paginator
	 */
	public function getPaginator(): Paginator
	{
		return $this->paginator;
	}


	/**
	 * @param Paginator $paginator
	 * @return $this
	 */
	public function setPaginator(Paginator $paginator): PaginatorControl
	{
		$this->paginator = $paginator;
		$this->setPageFromSession();

		return $this;
	}


	/**
	 * @param SessionSection $session
	 * @return PaginatorControl
	 */
	public function setSession(SessionSection $session): PaginatorControl
	{
		$this->session = $session;
		$this->setPageFromSession();

		return $this;
	}


	/**
	 *
	 */
	public function setPageFromSession()
	{
		if ($this->paginator && $this->session && isset($this->session->paginator[$this->getSessionKey()]) && $page = $this->session->paginator[$this->getSessionKey()]) {
			$this->paginator->setPage($page);
		}
	}


	/**
	 * @param int $side
	 */
	public function setPage(int $side)
	{
		$this->session->paginator[$this->getSessionKey()] = max(1, (int)$side);
		$this->setPageFromSession();
	}


	/**
	 * @return string
	 */
	public function getSessionKey(): string
	{
		return $this->sessionKey ?: '';
	}


	/**
	 * @param string $sessionKey
	 */
	public function setSessionKey(string $sessionKey)
	{
		$this->sessionKey = $sessionKey;
	}


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
	 * @param array $urlPatrameters
	 * @return void
	 * @throws AbortException
	 */
	public function handleChangePage(int $page, array $urlPatrameters = [])
	{
		$this->session->paginator[$this->getSessionKey()] = max(1, (int)$page);

		if ($callback = $this->onPageChange) {
			$callback($page, $urlPatrameters);
		}
		else {
			$this->redirect('this');
		}
	}


	/**
	 * @param Selection $selection
	 * @return Selection
	 */
	public function applyPaging(Selection $selection): Selection
	{
		return $selection->limit($this->paginator->getLength(), $this->paginator->getOffset());
	}


	/**
	 * @return array
	 */
	public function getPages(): array
	{
		$pages = [];
		;
		if($this->getPaginator()->page > 1)
		{
			for($i = ((1 > $this->getPaginator()->page - 3) ? 1 : $this->getPaginator()->page - 3); $i < $this->getPaginator()->page; $i++)
			{
				$pages[] = $i;
			}
		}

		$pages[] = $this->getPaginator()->page;

		if($this->getPaginator()->page < $this->getPaginator()->pageCount)
		{
			for($i = ($this->getPaginator()->page + 1); $i <= (($this->getPaginator()->pageCount < $this->getPaginator()->page + 3) ? $this->getPaginator()->pageCount : $this->getPaginator()->page + 3); $i++)
			{

				$pages[] = $i;
			}
		}

		return $pages;

	}


	/**
	 * @return bool
	 */
	public function isAjax(): bool
	{
		return $this->isAjax;
	}


	/**
	 * @param bool $isAjax
	 */
	public function setIsAjax(bool $isAjax): void
	{
		$this->isAjax = $isAjax;
	}


	/**
	 * @param array $urlParameters
	 */
	public function setUrlParameters(array $urlParameters): void
	{
		$this->urlParameters = $urlParameters;
	}


	/**
	 * @return array
	 */
	public function getUrlParameters(): array
	{
		return $this->urlParameters;
	}






}
