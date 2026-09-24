<?php

namespace App\AdminModule\Presenters;

use Nette\Application\AbortException;
use \Nette\Application\UI\Form;
use \Nette\Database\Table\Selection;
use \Nette\Http\SessionSection;
use Nette\Localization\Translator;
use Nette\Utils\Callback;
use \Nette\Utils\Strings;

class FiltersControl extends BaseControl
{
	/** @var callable */
	protected $onFilterChange;

	/** @var callable */
	protected $customFilters;

	/** @var string */
	protected $sessionKey = '';


	/**
	 * @param SessionSection $session
	 * @param Translator $translator
	 */
	public function __construct(protected SessionSection $session, protected Translator $translator)
	{
	}


	/**
	 * @param callable $onFilterChange$this->session = $presenter->session;
	 * @return FiltersControl
	 */
	public function setOnFilterChange(callable $onFilterChange): FiltersControl
	{
		$this->onFilterChange = $onFilterChange;

		return $this;
	}


	/**
	 * @param callable $customFilters
	 * @return FiltersControl
	 */
	public function setCustomFilters(callable $customFilters): FiltersControl
	{
		$this->customFilters = $customFilters;

		return $this;
	}


	/**
	 * @return string
	 */
	public function getSessionKey(): string
	{
		return $this->sessionKey ?: $this->getPresenter()->getName();
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
	public function getFilters(): array
	{
		return $this->session->filters[$this->getSessionKey()] ?? [];
	}


	/**
	 * @param string $filter
	 * @param mixed $value
	 * @return void
	 */
	public function setFilterValue(string $filter, mixed $value): void
	{
		$this->session->filters[$this->getSessionKey()][$filter] = $value;
	}


	/**
	 * @param Selection $selection
	 * @return Selection
	 */
	public function applyFilters(Selection $selection): Selection
	{
		$custom = [];

		$regular = [];
		foreach ($this->getFilters() as $key => $val) {
			if(Strings::startsWith($key, 'custom__')) {
				$custom[mb_substr($key, 8)] = $val;
			}
			elseif (!isset($this['form-'.$key]) || $val === NULL) {
				continue;
			}
			else{
				$regular[$key] = $val;
			}
		}

		foreach ($regular as $key => $val) {
			if (!isset($this['form-'.$key]) || $val === NULL || Strings::startsWith($key, 'custom__')) {
				continue;
			}
			$key = str_replace('___', ':', $key);
			$key = str_replace('__', '.', $key);
			if (Strings::startsWith($key, 'is_') || strpos($key, '.is_') || Strings::startsWith($key, 'has_') || strpos($key, '.has_') || strpos($key, '_is_') || Strings::endsWith($key, '_id')) {
				$selection->where($key, $val);
			}
			elseif ((Strings::startsWith($key, 'date_') || strpos($key, '.date_') || Strings::endsWith($key, '.date') || Strings::endsWith($key, '.created_at') || in_array($key, ['date', 'created_at'])) && $val) {
				if(Strings::contains($key, 'created_at')){
					$selection->where('DATE('.$key.')', date('Y-m-d', strtotime($val)));
				}
				else{
					$selection->where($key, date('Y-m-d', strtotime($val)));
				}
			}
			elseif ($val) {
				foreach (explode(' ', $val) as $part) {
					$selection->where($key.' LIKE ?', "%{$part}%");
				}
			}
		}

		if($callback = $this->customFilters) {
			$callback($selection, $custom);
		}

		return $selection;
	}


	/**
	 * Filter form.
	 * @return Form
	 */
	protected function createComponentForm(): Form
	{
		$form = new Form();
		$form->setTranslator($this->translator);
		$form->addSubmit('submit', 'filtrate');
		$form->onSuccess[] = [$this, 'formSubmitted'];

		return $form;
	}


	/**
	 * @param Form $form
	 * @throws AbortException
	 */
	public function formSubmitted(Form $form)
	{
		foreach($form->getValues(TRUE) as $key => $val){
			$this->setFilterValue($key, $val);
		}

		if ($callback = $this->onFilterChange) {
			$callback($this, $form);
		}
		else {
			$this->redirect('this');
		}
	}


	/**
	 * @throws AbortException
	 */
	public function resetFilters()
	{
		$this->session->filters[$this->getSessionKey()] = [];
		$this->redirect('this');
	}
}
