<?php

use \Nette\Database\Context;
use \Nette\Http\SessionSection;

class LanguagesService
{
	use \Nette\SmartObject;

	/**
	 * @param LanguagesRepository $languagesRepo
	 * @param ContentsService $contentsService
	 * @param \Nette\Database\Explorer $database
	 */
	public function __construct(protected LanguagesRepository $languagesRepo, protected ContentsService $contentsService, protected \Nette\Database\Explorer $database)
	{
	}


	/**
	 * @param SessionSection $session
	 * @return LanguagesEntity|null
	 */
	public function detectLanguage(SessionSection $session): ?LanguagesEntity
	{
		/** @var LanguagesEntity $language */
		if ($session->languages_id && $language = $this->languagesRepo->findEntity($session->languages_id)) {
			return $language;
		}

		if ($language = $this->languagesRepo->findDefault()) {
			$session->languages_id = $language->id;

			return $language;
		}

		return NULL;
	}


	/**
	 * @return array
	 */
	public function getLanguages(): array
	{
		return $this->languagesRepo->findAllEntities('pos_order');
	}


	/**
	 * @param LanguagesEntity $language
	 * @return bool
	 */
	public function setAsDefault(LanguagesEntity $language): bool
	{
		$this->database->transaction(function() use ($language) {
			if ($defaultLanguage = $this->languagesRepo->findDefault()) {
				$defaultLanguage->is_default = FALSE;
				$this->languagesRepo->update($defaultLanguage);
			}

			$language->is_default = TRUE;
			$language->is_active = TRUE;
			$this->languagesRepo->update($language);
		});

		return TRUE;
	}


	/**
	 * @param LanguagesEntity|null $language
	 * @param string $id
	 * @return bool
	 */
	public function checkDuplicity(?LanguagesEntity $language, string $id): bool
	{
		return  (!$language || mb_strtolower($language->id) != mb_strtolower($id)) && $this->languagesRepo->find($id) ? FALSE : TRUE;
	}


	/**
	 * @param LanguagesEntity $language
	 * @param LanguagesEntity|null $createFromLanguage
	 * @return bool
	 */
	public function createMultiLangTableRows(LanguagesEntity $language, ?LanguagesEntity $createFromLanguage = NULL): bool
	{
		if(!$createFromLanguage instanceof LanguagesEntity){
			$createFromLanguage =  $this->languagesRepo->findDefault();
		}
		if(!$createFromLanguage instanceof LanguagesEntity){
			return FALSE;
		}

		$structure = $this->database->getStructure();

		foreach ($this->getMultilangTables() as $table) {
			$columns = $this->getColumnNames($table);

			unset($columns[array_search('languages_id', $columns)]);


			//unset languages_id if not primary and table contains column id
			$index = array_search('id', $columns);
			if(!is_bool($index)){
				$columnsWithParams = $structure->getColumns($table);
				foreach($columnsWithParams as $column){
					if($column['name'] == 'languages_id' && !$column['primary']){
						unset($columns[$index]);
					}
				}
			}
			$containsContent = FALSE;
			//unset contents from mld tables
			if(!\Nette\Utils\Strings::contains($table, 'content')){
				$index = array_search('contents_id', $columns);
				if(!is_bool($index)){
					$containsContent = TRUE;
					unset($columns[$index]);
				}
			}

			$this->database->queryArgs("
				INSERT INTO {$table} (".implode(', ', $columns).", languages_id)
				SELECT ".implode(', ', $columns).", ? FROM {$table} WHERE languages_id = ?
			", [$language->id, $createFromLanguage->id]);


			//copy content from default lang
			if(!\Nette\Utils\Strings::contains($table, 'content') && $containsContent){
				$columnsWithParams = $structure->getColumns($table);
				foreach($columnsWithParams as $column){
					if($column['name'] != 'languages_id' && $column['primary']){
						$createFromLanguageItemIdsWithContentIds = $this->database->table($table)->select('*')->where('languages_id', $createFromLanguage->id)->where('contents_id IS NOT NULL')->fetchPairs($column['name'], 'contents_id');
						foreach($createFromLanguageItemIdsWithContentIds as $itemId => $contentsId){
							$copiedContentsEntity = $this->contentsService->copyContent($contentsId);
							$this->database->table($table)->where('languages_id', $language->id)->where($column['name'], $itemId)->update([
								'contents_id' => $copiedContentsEntity->id
							]);
						}
					}
				}
			}
		}

		return TRUE;
	}


	/**
	 * @return array
	 */
	protected function getMultiLangTables(): array
	{
		$structure = $this->database->getStructure();

		$tables = [];
		foreach ($structure->getTables() as $table) {
			if (!in_array('languages_id', $this->getColumnNames($table['name']))) {
				continue;
			}

			$tables[] = $table['name'];
		}

		return $tables;
	}


	/**
	 * @param string $tableName
	 * @return array
	 */
	protected function getColumnNames(string $tableName): array
	{
		$structure = $this->database->getStructure();

		$columns = [];
		foreach ($structure->getColumns($tableName) as $column) {
			$columns[] = $column['name'];
		}

		return $columns;
	}
}
