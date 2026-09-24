<?php

use Nette\Utils\Strings;

class ContentsRepository extends BaseRepositoryML
{
	protected $tableName = 'contents';
	public $cleanCacheTags = [\CacheTags::CONTENTS];

	/**
	 * @return array
	 */
	public function getTablesWithContents(): array
	{
		$names = [];

		$structure = $this->db->getStructure();
		foreach($structure->getTables() as $table){
			//remove "s"
			$thisTableName = substr($this->getTableName(), 0, -1);
			//ignore self and related tables
			if(Strings::contains($table['name'], $thisTableName)){
				continue;
			}

			$columns = $structure->getColumns($table['name']);
			if(is_array($columns) && !empty($columns)){
				foreach($columns as $column){
					if($column['name'] == 'contents_id'){
						$names[$table['name']] = $table['name'];
					}
				}
			}
		}
		return $names;
	}


	/**
	 * @param array $tables
	 * @param string $prefix
	 * @return array
	 */
	public function getModuleNamePairsFromTables(array $tables, string $prefix = ''): array
	{
		$names = [];

		foreach($tables as $table){
			$name = $table;
			$name = str_replace('_mld', '', $name); //remove mld
			$name = str_replace('_', '', ucwords($name, '_')); //camelize
			$name = Strings::firstLower($name); //firstLower

			$names[$table] = $prefix.$name;
		}
		return $names;
	}
}
