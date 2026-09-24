<?php

namespace App\AdminModule\Presenters;

use Nette\Application\AbortException;
use Nette\Application\UI\Control;
use Nette\Application\UI\Form;
use Nette\Http\FileUpload;
use Nette\Localization\Translator;
use Nette\Utils\Strings;

class ImportExportControl extends Control
{
	/** @var string  */
	protected $csvDelimiter = ';';

	/** @var string[]  */
	protected $csvDefinition = [];

	/** @var array  */
	protected $csvFileNameParts = [];

	/** @var callable */
	protected $onProcessImportItems;

	/** @var callable */
	protected $onProcessImportItem;

	/** @var callable */
	protected $onProcessExportItem;

	/** @var callable */
	protected $onProcessExportItems;


	/**
	 * @param Translator $translator
	 * @param \BaseRepository $repository
	 * @param string $orderBy
	 * @param FiltersControl $filtersControl
	 */
	public function __construct(protected Translator $translator, protected \BaseRepository $repository, protected string $orderBy, protected FiltersControl $filtersControl){}


	/**
	 * @param string $column
	 * @param string $name
	 * @param bool $required
	 * @return $this
	 */
	public function addCsvColumn(string $column, string $name, bool $required = FALSE): self
	{
		$this->csvDefinition[] = [
			'column' => $column,
			'required' => $required,
			'name' => $name,
		];

		return $this;
	}



	/**
	 *
	 */
	public function renderForm()
	{
		$this->template->setFile(__DIR__.'/ImportExportForm.latte');
		$this->template->render();
	}


	/**
	 *
	 */
	public function renderButton()
	{
		$this->template->setFile(__DIR__.'/ImportExportButton.latte');
		$this->template->render();
	}

	/**
	 * @return Form
	 */
	protected function createComponentImportForm(): Form
	{
		$form = new Form();
		$form->setTranslator($this->translator);

		$form->addUpload('file', 'admin.terms.csvFile')->setRequired(TRUE);

		$form->addSubmit('submit', 'admin.buttons.import');

		$form->onSuccess[] = [$this, 'importFormSubmitted'];

		return $form;
	}


	/**
	 * @param Form $form
	 * @return void
	 * @throws AbortException
	 */
	public function importFormSubmitted(Form $form)
	{
		$values = $form->getValues(TRUE);

		/** @var FileUpload $file */
		$file = $values['file'];

		/** @var BasePresenter $presenter */
		$presenter = $this->getPresenter();

		if ($file->isOk() && Strings::lower(pathinfo($file->getUntrustedName(), PATHINFO_EXTENSION)) == 'csv') {
			$fileContent = $file->getContents();
			$lines = explode(PHP_EOL, $fileContent);
			//header
			unset($lines[0]);

			$items = [];
			foreach ($lines as $line) {
				$preparedItem = $this->prepareItemFromLine($line);
				if(!is_null($preparedItem)){
					$items[] = $preparedItem;
				}
			}

			$precessedItems = 0;
			foreach ($items as $item) {
				if ($this->onProcessImportItem && is_callable($this->onProcessImportItem)) {
					$callback = $this->onProcessImportItem;
					if($callback($item)){
						$precessedItems++;
					}
				}
				else{
					throw new \Exception('Callback onProcessImportItem is required. Data could not be processed.');
				}
			}

			if ($this->onProcessImportItems && is_callable($this->onProcessImportItems)) {
				$callback = $this->onProcessImportItems;
				$callback();
			}


			if($precessedItems > 0){
				$presenter->flashMessage(str_replace('%d', $precessedItems, $this->translator->translate('admin.terms.importSuccess')), 'success');
			}
			else {
				$presenter->flashMessage($this->translator->translate('admin.formMessages.mimeType'), 'danger');
			}
		}
		else {
			$presenter->flashMessage($this->translator->translate('admin.formMessages.mimeType'), 'danger');
		}
		$presenter->redirect('this');
	}


	/**
	 * @return void
	 */
	public function handleExportToXls()
	{
		$data = [
			$this->getCsvHeader()
		];

		foreach ($this->getListItems() as $item) {
			if ($this->onProcessExportItem && is_callable($this->onProcessExportItem)) {
				$callback = $this->onProcessExportItem;
				$data[] = $callback($item);
			}
			else{
				throw new \Exception('Callback onProcessExportItem is required and has to return CSV line.');
			}
		}

		if ($this->onProcessExportItems && is_callable($this->onProcessExportItems)) {
			$callback = $this->onProcessExportItems;
			$callback();
		}

		$this->sendCsv($data);
	}




	/**
	 * @param string $line
	 * @return array|null
	 */
	public function prepareItemFromLine(string $line): ?array
	{
		$values = str_getcsv($line, $this->getCsvDelimiter());
		$preparedArray = [];
		foreach($this->csvDefinition as $definitionKey => $definitionSettings){
			$value = isset($values[$definitionKey]) ? $values[$definitionKey] : NULL;
			if($definitionSettings['required'] && is_null($value)){
				return NULL;
			}
			$preparedArray[$definitionSettings['column']] = $value;
		}
		return $preparedArray;
	}

	/**
	 * @return array
	 */
	protected function getCsvHeader(): array
	{
		$header = [];
		foreach($this->csvDefinition as $columnSettings){
			$header[] = $columnSettings['name'].($columnSettings['required'] ? (' ('.$this->translator->translate('admin.terms.required').')') : '');
		}
		return $header;
	}


	/**
	 * @return \BaseEntity[]
	 */
	public function getListItems(): array
	{
		$items = $this->repository->findAll();
		$this->filtersControl->applyFilters($items);
		$items->order($this->orderBy);

		if($items){
			return $this->repository->getEntities($items);
		}
		else{
			return [];
		}
	}


	/**
	 * @param array $lines
	 * @return void
	 */
	public function sendCsv(array $lines): void
	{
		if(empty($this->getCsvFileNameParts())){
			/** @var BasePresenter $presenter */
			$presenter = $this->getPresenter();
			$this->setCsvFileNameParts([$presenter->getModuleName()]);
		}

		header('Content-Type: application/csv');
		header('Content-Disposition: attachment; filename="'.Strings::webalize(implode(' ', $this->getCsvFileNameParts())).'.csv";');
		$f = fopen('php://output', 'w');
		foreach ($lines as $line) {
			fputcsv($f, $line, $this->getCsvDelimiter());
		}
		exit;
	}


	/**
	 * @return array
	 */
	public function getCsvFileNameParts(): array
	{
		return $this->csvFileNameParts;
	}


	/**
	 * @param array $csvFileNameParts
	 */
	public function setCsvFileNameParts(array $csvFileNameParts): void
	{
		$this->csvFileNameParts = $csvFileNameParts;
	}


	/**
	 * @return string
	 */
	public function getCsvDelimiter(): string
	{
		return $this->csvDelimiter;
	}


	/**
	 * @param string $csvDelimiter
	 * @return $this
	 */
	public function setCsvDelimiter(string $csvDelimiter): self
	{
		$this->csvDelimiter = $csvDelimiter;

		return $this;
	}

	/**
	 * @param callable $onProcessImportItems
	 * @return $this
	 */
	public function setOnProcessImportItems(callable $onProcessImportItems): self
	{
		$this->onProcessImportItems = $onProcessImportItems;
		return $this;
	}


	/**
	 * @param callable $onProcessImportItem
	 * @return $this
	 */
	public function setOnProcessImportItem(callable $onProcessImportItem): self
	{
		$this->onProcessImportItem = $onProcessImportItem;
		return $this;
	}


	/**
	 * @param callable $onProcessExportItem
	 * @return $this
	 */
	public function setOnProcessExportItem(callable $onProcessExportItem): self
	{
		$this->onProcessExportItem = $onProcessExportItem;
		return $this;
	}


	/**
	 * @param callable $onProcessExportItems
	 * @return $this
	 */
	public function setOnProcessExportItems(callable $onProcessExportItems): self
	{
		$this->onProcessExportItems = $onProcessExportItems;
		return $this;
	}


	/**
	 * @return array
	 */
	public function getCsvDefinitionColumns(): array
	{
		$columns = [];
		foreach($this->csvDefinition as $definitionSettings){
			$columns[] = $definitionSettings['column'];
		}
		return $columns;
	}

}
