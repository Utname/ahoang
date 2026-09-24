<?php

namespace App\AdminModule\Presenters;

use BaseEntity;
use Nette\Application\UI\Form;
use Nette\Database\Table\ActiveRow;
use Nette\Http\FileUpload;
use Nette\Utils\Strings;

class LanguagesPresenter extends BaseModulePresenter
{
	/** @var \LanguagesRepository @inject */
	public $repository;

	/** @var \LanguagesService @inject */
	public $service;

	/** @var array */
	protected $moduleBulkActions = [
		BulkActionsControl::ACTION_SET_ACTIVE,
	];

	/** @var string */
	protected $orderBy = 'pos_order';

	/** @var \PagesRepository @inject */
	public $pagesRepo;

	/** @var array */
	protected $pairs;


	/**
	 *
	 */
	public function actionDefault()
	{
		$this['filters-form']->addText('id', 'admin.terms.id');
		$this['filters-form']->addText('name', 'admin.terms.name');

		parent::actionDefault();
	}


	/**
	 * @return void
	 * @throws \ReflectionException
	 */
	public function startup()
	{
		parent::startup();
		$this->template->pairs = $this->pairs = $this->repository->findAll()->fetchPairs('id', 'name');
	}


	/**
	 * @param Form $form
	 * @return Form
	 */
	protected function setFormFields(Form $form): Form
	{
		$form->addText('id', 'admin.terms.shortcut')->setRequired(TRUE)->addRule(Form::LENGTH, NULL, 2);
		$form->addText('name', 'admin.terms.name')->setRequired(TRUE);
		$form->addSelect('is_active', $this->trans('admin.terms.isActive'), $this->global['bool'])->setTranslator(NULL);
		$form->addSelect('is_in_menu', $this->trans('admin.terms.isInMenu'), $this->global['bool'])->setTranslator(NULL);

		return parent::setFormFields($form);
	}


	/**
	 * @param \BaseEntity|null $entity
	 * @param Form $form
	 */
	protected function beforeEntitySave(?\BaseEntity $entity, Form $form)
	{
		$values = $form->getValues();

		if (!$this->service->checkDuplicity($entity, $values->id)) {
			$this->flashMessage($this->trans('admin.messages.errorLanguageExists', $entity->id), 'danger');
			$this->redirect('default');
		}

		parent::beforeEntitySave($entity, $form);
	}


	/**
	 * @param \BaseEntity $entity
	 * @param Form $form
	 */
	protected function afterEntityCreate(\BaseEntity $entity, Form $form)
	{
		$this->service->createMultiLangTableRows($entity);

		$this->updatePagesFullUrl();

		parent::afterEntityCreate($entity, $form);
	}


	/**
	 * @param BaseEntity $copyFromEntity
	 * @param BaseEntity $entity
	 * @param Form $form
	 * @return void
	 */
	protected function afterEntityCopy(BaseEntity $copyFromEntity, BaseEntity $entity, Form $form)
	{
		$this->service->createMultiLangTableRows($entity, $copyFromEntity);

		$this->updatePagesFullUrl();

		parent::afterEntityCopy($copyFromEntity, $entity, $form);
	}


	/**
	 * @return void
	 */
	protected function updatePagesFullUrl(): void
	{
		// update pages full url
		if ($root = $this->pagesRepo->findEntityBy(['pos_level' => 0])) {
			foreach ($this->pagesRepo->getChildren($root) as $child) {
				$this->pagesRepo->updateFullUrl($this->pagesRepo->getEntity($child), TRUE);
			}
		}
	}

	/**
	 * @param BaseEntity $entity
	 * @param Form $form
	 */
	protected function afterEntityUpdate(BaseEntity $entity, Form $form)
	{
		if ($entity->is_default && !$entity->is_active) {
			$entity->is_active = TRUE;
			$this->repository->update($entity);
		}

		// update pages full url
		if ($entity->id != $this->originEntity->id) {
			if ($root = $this->pagesRepo->findEntityBy(['pos_level' => 0])) {
				foreach ($this->pagesRepo->getChildren($root) as $child) {
					$this->pagesRepo->updateFullUrl($this->pagesRepo->getEntity($child), TRUE);
				}
			}
		}

		parent::afterEntityUpdate($entity, $form);
	}


	/**
	 * @param string $itemId
	 * @return void
	 * @throws \Nette\Application\AbortException
	 */
	public function handleSetAsDefault(string $itemId)
	{
		/** @var \LanguagesEntity $language */
		if (!$language = $this->repository->findEntity($itemId)) {
			$this->flashMessage($this->trans('admin.messages.errorLanguageDefault'), 'danger');
			$this->redirect('this');
		}

		if (!$this->service->setAsDefault($language)) {
			$this->flashMessage($this->trans('admin.messages.errorLanguageDefault'), 'danger');
			$this->redirect('this');
		}

		$this->flashMessage($this->trans('admin.messages.successLanguageDefault'), 'success');
		$this->redirect('this');
	}


	/**
	 * @param $langId
	 * @return void
	 */
	public function handleExport($langId)
	{
		$mldTables = [];
		$structure = $this->database->getStructure();
		foreach($structure->getTables() as $table){
			if(Strings::endsWith($table['name'], '_mld')){
				$mldTables[] = $table['name'];
			}
		}

		$lines = [];

		if(!empty($mldTables)){
			foreach($mldTables as $tableName){
				$baseTableName = str_replace('_mld', '', $tableName);
				$data = $this->database->table($tableName)->select('*');
				if($data->count()){
					/** @var ActiveRow $row */
					foreach($data as $row){
						foreach($row->toArray() as $column => $value){
							if(Strings::endsWith($column, '_id') || Strings::contains($column, 'is_') || Strings::contains($column, 'url') || Strings::contains($column, 'slug')){
								continue;
							}
							$idColumn = $baseTableName.'_id';
							$identificator = implode('__', [$tableName, $row->$idColumn, $column]);
							$lines[] = [
								$identificator,
								$value
							];
						}
					}
				}
			}
		}
		header('Content-Type: application/csv');
		header('Content-Disposition: attachment; filename="export_'.$langId.'.csv";');
		$f = fopen('php://output', 'w');
		foreach ($lines as $line) {
			fputcsv($f, $line, ';');
		}
		exit;
	}

	protected function createComponentImportForm(): Form
	{
		$form = new Form();
		$form->setTranslator($this->translator);

		$form->addSelect('languages_id', 'admin.terms.language', $this->pairs);

		$form->addUpload('file', 'admin.terms.file')->setRequired(TRUE);

		$form->addSubmit('submit', 'admin.terms.save');

		$form->onSuccess[] = [$this, 'processImportForm'];

		return $form;
	}


	/**
	 * @param Form $form
	 */
	public function processImportForm(Form $form): void
	{
		$values = $form->getValues();

		/** @var FileUpload $file */
		$file = $values['file'];
		if ($file->isOk() && Strings::lower(pathinfo($file->getUntrustedName(), PATHINFO_EXTENSION)) == 'csv') {
			$file->move(FS_TEMP.'/lang_import.csv');
			$fp = fopen(FS_TEMP.'/lang_import.csv', 'r');
			$lines = array();
			while ($row = fgetcsv($fp, null, ";")) {
				$lines[] = $row;
			}

			$precessedItems = 0;

			if(!empty($lines)){

				$groups = [];
				foreach($lines as $line){
					$identificator = $line[0] ?? NULL;
					$value = $line[1] ?? NULL;
					if(is_null($identificator) || is_null($value)){
						continue;
					}
					[$tableName, $itemId, $column] = explode('__', $identificator);
					$groups[implode('__', [$tableName, $itemId])][$column] = $value;
				}

				if(!empty($groups)){
					foreach($groups as $identificator => $data){
						[$tableName, $itemId] = explode('__', $identificator);
						$itemIdColum = str_replace('_mld', '_id', $tableName);
						$this->database->table($tableName)->where('languages_id', $values['languages_id'])->where($itemIdColum, $itemId)->update($data);
						$precessedItems += count($data);
					}
				}
			}

			if($precessedItems > 0){
				$this->flashMessage(str_replace('%d', $precessedItems, $this->translator->translate('admin.terms.importSuccess')), 'success');
			}
			else {
				$this->flashMessage($this->translator->translate('admin.formMessages.mimeType'), 'danger');
			}
		}
		else {
			$this->flashMessage($this->translator->translate('admin.formMessages.mimeType'), 'danger');
		}
		$this->redirect('this');

	}
}
