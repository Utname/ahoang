<?php

namespace App\AdminModule\Presenters;

use BaseRepository;
use Nette\Application\AbortException;
use Nette\Database\Table\Selection;
use Nette\Http\SessionSection;
use Nette\Localization\Translator;
class SortingControl extends \Nette\Application\UI\Control
{
	/** @var callable */
	protected $onSortingChange;

	/** @var string */
	protected $sessionKey = '';

	/** @var array  */
	protected $sortingOptions = [];


	/**
	 * SortingControl constructor.
	 * @param SessionSection $session
	 * @param Translator $translator
	 * @param BaseRepository $repository
	 * @param string $basicSorting
	 */
	public function __construct(protected SessionSection $session, protected Translator $translator, protected BaseRepository $repository, protected string $basicSorting)
	{

	}


	/**
	 * @param string $sortingOption
	 */
	public function render(string $sortingOption)
	{
		$activeSortingOptionExploded = explode('__', $this->session->sorting[$this->getSessionKey()]);
		$this->template->isActive = ($activeSortingOptionExploded[0] == $sortingOption);
		$this->template->sortingOption = $sortingOption;

		$this->template->setFile(__DIR__.'/SortingControl.latte');
		$this->template->render();
	}


	/**
	 * @param Selection $selection
	 * @return Selection
	 */
	public function applySorting(Selection $selection): Selection
	{
		if ($this->repository->isSortable()) {
			if(count($this->repository->getOrderRestrictions())){
				$selection->order(implode(', ', $this->repository->getOrderRestrictions()).', '.$this->repository->getTableName().'.pos_order');
			}
			else{
				$selection->order($this->repository->getTableName().'.pos_order');
			}
		}
		elseif (!empty($this->getSortingOptions())) {
			if(!isset($this->session->sorting[$this->getSessionKey()])){
				$sortingOption = array_key_first($this->getSortingOptions());
				$this->session->sorting[$this->getSessionKey()] = $sortingOption;
			}

			$sortingOption = $this->session->sorting[$this->getSessionKey()];
			if(isset($this->getSortingOptions()[$sortingOption])){
				$selection->order($this->getSortingOptions()[$sortingOption]);
			}
		}
		elseif ($this->basicSorting) {
			$selection->order($this->basicSorting);
		}

		return $selection;
	}


	/**
	 * @param string $sortBy
	 * @throws AbortException
	 */
	public function handleSetSortingOption(string $sortingOption)
	{
		if($this->session->sorting[$this->getSessionKey()] == $sortingOption && isset($this->getSortingOptions()[$sortingOption.'__desc'])){
			$sortingOption .= '__desc';
		}
		$this->session->sorting[$this->getSessionKey()] = $sortingOption;

		if($callback = $this->getOnSortingChange()) {
			$callback($sortingOption);
		}

		$this->getPresenter()->redirect('this');
	}


	/**
	 * @return callable
	 */
	public function getOnSortingChange(): callable
	{
		return $this->onSortingChange;
	}


	/**
	 * @param callable $onSortingChange
	 * @return $this
	 */
	public function setOnSortingChange(callable $onSortingChange): SortingControl
	{
		$this->onSortingChange = $onSortingChange;

		return $this;
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
	 * @return $this
	 */
	public function setSessionKey(string $sessionKey): SortingControl
	{
		$this->sessionKey = $sessionKey;

		return $this;
	}


	/**
	 * @param array $sortingOptions
	 * @return $this
	 */
	public function setSortingOptions(array $sortingOptions): SortingControl
	{
		$this->sortingOptions = $sortingOptions;

		return $this;
	}


	/**
	 * @return array
	 */
	public function getSortingOptions(): array
	{
		return $this->sortingOptions;
	}

}
