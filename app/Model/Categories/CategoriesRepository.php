<?php

use Nette\Database\IRow;
use Nette\Database\Table\ActiveRow;

class CategoriesRepository extends BaseTreeRepositoryML
{
	protected $tableName = 'categories';


	/**
	 * @return array
	 */
	public function getAllTags(): array
	{
		$allTags = [];
		foreach($this->findAll()
			->where('tags != ?', '')
			->fetchPairs(NULL, 'tags') as $tags)
		{
			foreach(explode(' ', $tags) as $tag)
			{
				$allTags[$tag] = $tag;
			}
		}

		return $allTags;
	}


	/**
	 * @param array $values
	 * @param bool $indexForSearch
	 * @return IRow
	 */
	public function insert(array $values, bool $indexForSearch = FALSE): IRow
	{
		$result = parent::insert($values);

		if($indexForSearch)
		{
			$this->index([$result->id]);
		}

		return $result;
	}


	/**
	 * @param BaseEntity $entity
	 * @param bool $indexForSearch
	 * @return bool
	 */
	public function update(BaseEntity $entity, bool $indexForSearch = FALSE): bool
	{
		$result = parent::update($entity);

		if($indexForSearch)
		{
			$this->index([$entity->id]);
		}

		return $result;
	}


	/**
	 * @param int $itemId
	 * @param LanguagesEntity $languagesEntity
	 * @param bool $isBigIndex
	 * @return array
	 */
	public function getIndexParts(int $itemId, LanguagesEntity $languagesEntity, bool $isBigIndex = FALSE): array
	{
		$indexParts = parent::getIndexParts($itemId, $languagesEntity);

		if($itemRow = $this->db->table($this->getTableName().'_mld')
			->select('*, '.$this->getTableName().'.*')
			->where($this->getTableName().'_mld.languages_id = ?', $languagesEntity->id)
			->where('id', $itemId)
			->limit(1)
			->fetch())
		{
			$indexParts[] = $itemRow->name;
			$indexParts[] = $itemRow->tags;
			$indexParts[] = $itemRow->title;
			$indexParts[] = $itemRow->text;
		}

		return $indexParts;
	}
}
