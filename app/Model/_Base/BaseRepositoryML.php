<?php

declare(strict_types=1);

use Nette\Database\Table\Selection;
use Nette\Database\IRow;

abstract class BaseRepositoryML extends BaseRepository
{
	/** @var bool  */
	protected $isML = TRUE;

	/**
	 * @return Selection
	 */
	protected function getTable(): Selection
	{
		return $this->db->table($this->tableName.'_mld')
			->select('*, '.$this->tableName.'.*');
	}


	/**
	 * @return Selection
	 */
	public function findAll(): Selection
	{
		return $this->getTable()
			->where($this->tableName.'_mld.languages_id = ?', $GLOBALS['lang']);
	}


	/**
	 * @param array $values
	 * @return bool|int|\Nette\Database\Table\ActiveRow
	 */
	public function simpleInsert(array $values): mixed
	{
		// base table
		$set = array_intersect_key($values, array_flip($this->getTableColumnNames($this->tableName)));
		$result = $this->db->table($this->tableName)->insert($set);

		// mld table
		$set = array_intersect_key($values, array_flip($this->getTableColumnNames($this->tableName.'_mld')));
		$set[$this->tableName.'_id'] = $result->id;
		$sets = [];
		foreach ($GLOBALS['languages'] as $language) {
			$set['languages_id'] = $language->id;
			$sets[] = $set;
		}
		$this->db->table($this->tableName.'_mld')->insert($sets);

		return $result;
	}


	/**
	 * @param array $values
	 * @return IRow
	 */
	public function insert(array $values): IRow
	{
		if ($this->isSortable()) {
			$values['pos_order'] = $this->getMaxOrder() + 1;
		}

		$result = $this->simpleInsert($values);

		if ($this->isSortable()) {
			$this->recountOrder();
		}

		return $this->find($result->id);
	}


	/**
	 * @param BaseEntity $entity
	 * @return bool
	 */
	public function update(BaseEntity $entity): bool
	{
		if ($entity->id) {
			$values = $entity->getValues();

			// base table
			$set = array_intersect_key($values, array_flip($this->getTableColumnNames($this->tableName)));
			if (count($set)) {
				$this->db->table($this->tableName)
					->where('id', $entity->id)
					->update($set);
			}

			// mld table
			$set = array_intersect_key($values, array_flip($this->getTableColumnNames($this->tableName.'_mld')));
			if (count($set)) {
				$this->db->table($this->tableName.'_mld')
					->where($this->tableName.'_id', $entity->id)
					->where('languages_id', $GLOBALS['lang'])
					->update($set);
			}
		}

		return TRUE;
	}
}
