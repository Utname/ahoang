<?php

declare(strict_types=1);

use \Nette\Database\Table\ActiveRow;
use \Nette\Database\Table\Selection;

abstract class BaseTreeRepositoryML extends BaseRepositoryML
{
	/**
	 * @param null $order
	 * @return array
	 */
	public function findAllEntities($order = NULL): array
	{
		$selection = $this->findAll()->where('pos_level')->order('pos_left');

		return self::getEntities($selection);
	}


	/**
	 * @param BaseEntity $entity
	 * @return Selection
	 */
	public function getChildren(BaseEntity $entity): Selection
	{
		return $this->findAll()
			->where('id')
			->where('pos_level > ?', 0)
			->where('pos_left > ?', $entity->pos_left)
			->where('pos_right < ?', $entity->pos_right)
			->order('pos_left');
	}


	/**
	 * @param BaseEntity $entity
	 * @param int|NULL $level
	 * @return ActiveRow|null
	 */
	public function getChild(BaseEntity $entity, int $level = NULL): ?ActiveRow
	{
		$child = $this->getChildren($entity)->where('pos_level', $level ? $level : $entity->pos_level + 1)->limit(1)->fetch();

		return $child ?: NULL;
	}


	/**
	 * @param BaseEntity $entity
	 * @return Selection
	 */
	public function getParents(BaseEntity $entity): Selection
	{
		return $this->findAll()
			->where('id')
			->where('pos_level > ?', 0)
			->where('pos_left < ?', $entity->pos_left)
			->where('pos_right > ?', $entity->pos_right)
			->order('pos_left');
	}


	/**
	 * @param BaseEntity $entity
	 * @param int|null $level
	 * @return ActiveRow|null
	 */
	public function getParent(BaseEntity $entity, int $level = NULL): ?ActiveRow
	{
		return $this->getParents($entity)->where('pos_level', $level ? $level : $entity->pos_level - 1)->limit(1)->fetch();
	}


	/**
	 * @param BaseEntity $entity
	 * @return string
	 */
	public function getUrl(BaseEntity $entity): string
	{
		$parts = [];
		foreach ($this->getParents($entity) as $parent) {
			$parts[] = $parent->slug;
		}
		$parts[] = $entity->slug;

		return ($GLOBALS['container']->isMultilang() ? ('/'.$entity->languages_id) : '').'/'.implode('/', $parts);
	}


	/**
	 * @param BaseEntity $entity
	 * @param string $glue
	 * @return string
	 */
	public function getFullName(BaseEntity $entity, string $glue = ', '): string
	{
		$parts = [];
		foreach ($this->getParents($entity) as $parent) {
			$parts[] = $parent->name;
		}
		$parts[] = $entity->name;

		return implode($glue, $parts);
	}


	/**
	 * @param BaseEntity $entity
	 * @param bool $changeOtherLanguages
	 */
	public function updateFullUrl(BaseEntity $entity, bool $changeOtherLanguages): void
	{
		$this->db->transaction(function() use ($entity, $changeOtherLanguages) {
			$columnNames = $this->getTableColumnNames($this->tableName.'_mld');

			$rows = $this->db->table($this->tableName.'_mld')
				->select('*, '.$this->tableName.'.*')
				->where($this->tableName.'_id', $entity->id);

			if (!$changeOtherLanguages) {
				$rows->where('languages_id', $GLOBALS['lang']);
			}

			foreach ($rows as $row) {
				$item = $this->getEntity($row);

				$set = [];

				if (in_array('full_url', $columnNames)) {
					$set['full_url'] = $this->getUrl($item);
				}

				if (in_array('full_name', $columnNames)) {
					$set['full_name'] = $this->getFullName($item, ' / ');
				}

				$this->db->table($this->tableName.'_mld')
					->where($this->tableName.'_id', $entity->id)
					->where('languages_id', $row->languages_id)
					->update($set);
			}
		});
	}


	/**
	 * @param BaseEntity $entity
	 * @return bool
	 */
	public function update(BaseEntity $entity): bool
	{
		if (!parent::update($entity)) {
			return FALSE;
		}

		if ((isset($entity->slug) && $entity->slug != $entity->getRowML()->slug) || (isset($entity->link) && $entity->link != $entity->getRowML()->link)) {
			$this->updateFullUrl($entity, FALSE);

			foreach ($this->getChildren($entity) as $child) {
				$this->updateFullUrl($this->getEntity($child), FALSE);
			}
		}

		return TRUE;
	}


	/**
	 * @param BaseEntity $entity
	 * @param array $values
	 * @return ActiveRow|null
	 */
	public function insertChild(BaseEntity $entity, array $values): ?ActiveRow
	{
		$item = $this->db->transaction(function() use ($entity, $values) {
			$this->db->query("
					UPDATE ".$this->tableName."
					SET pos_left = pos_left + 2
					WHERE pos_left > ?
				", $entity->pos_right);

			$this->db->query("
					UPDATE ".$this->tableName."
					SET pos_right = pos_right + 2
					WHERE pos_right >= ?
				", $entity->pos_right);

			$values['pos_left'] = $entity->pos_right;
			$values['pos_right'] = $entity->pos_right + 1;
			$values['pos_level'] = $entity->pos_level + 1;

			return $this->insert($values);
		});

		if ($item) {
			$this->updateFullUrl($this->getEntity($item), TRUE);
		}

		return $item;
	}


	/**
	 * @param $id
	 * @return bool
	 */
	public function delete($id): bool
	{
		if (!$item = $this->find($id)) {
			return FALSE;
		}

		$this->db->transaction(function() use ($item) {
			$levelDiff = $item->pos_right - $item->pos_left + 1;

			$this->db->table($this->tableName)
				->where('pos_left >= ?', $item->pos_left)
				->where('pos_right <= ?', $item->pos_right)
				->delete();

			$this->db->query("
			            UPDATE ".$this->tableName."
			            SET pos_left = pos_left - ?
			            WHERE pos_left > ?
			        ", $levelDiff, $item->pos_right);

			$this->db->query("
						UPDATE ".$this->tableName."
						SET pos_right = pos_right - ?
						WHERE pos_right > ?
					", $levelDiff, $item->pos_right);
		});

		return TRUE;
	}


	/**
	 * @param BaseEntity $entity
	 * @param BaseEntity $relEntity
	 */
	public function moveBefore(BaseEntity $entity, BaseEntity $relEntity): void
	{
		$this->move($entity, $relEntity, 'before');
	}


	/**
	 * @param BaseEntity $entity
	 * @param BaseEntity $relEntity
	 */
	public function moveTo(BaseEntity $entity, BaseEntity $relEntity): void
	{
		$this->move($entity, $relEntity, 'to');
	}


	/**
	 * @param BaseEntity $entity
	 * @param BaseEntity $relEntity
	 * @param string $relType
	 */
	protected function move(BaseEntity $entity, BaseEntity $relEntity, string $relType): void
	{
		$levelDiff = $entity->pos_right - $entity->pos_left + 1;

		if ($relType == 'before') {
			$left = $relEntity->pos_left;
			$level = $relEntity->pos_level;
		}
		else {
			$left = $relEntity->pos_right;
			$level = $relEntity->pos_level + 1;
		}

		if ($left > $entity->pos_left) {
			$left -= $levelDiff;
		}

		if ($left != $entity->pos_left) {
			$min_left = min($left, $entity->pos_left);
			$max_right = max($left + $levelDiff - 1, $entity->pos_right);
			$offset = $left - $entity->pos_left;

			if ($left > $entity->pos_left) {
				$levelDiff = -$levelDiff;
			}

			$this->db->query("
					UPDATE ".$this->tableName."
					SET
						pos_level = pos_level + IF(@podstrom := pos_left >= ? AND pos_right <= ?, ?, 0),
						pos_left = pos_left + IF(@podstrom, ?, IF(pos_left >= ?, ?, 0)),
						pos_right = pos_right + IF(@podstrom, ?, IF(pos_right <= ?, ?, 0))
					WHERE pos_right >= ? AND pos_left <= ?
	      		", $entity->pos_left, $entity->pos_right, ($level - $entity->pos_level), $offset, $min_left, $levelDiff, $offset, $max_right, $levelDiff, $min_left, $max_right);
		}

		$item = $this->getEntity($this->find($entity->id));

		$this->updateFullUrl($item, TRUE);

		foreach ($this->getChildren($item) as $child) {
			$this->updateFullUrl($this->getEntity($child), TRUE);
		}
	}


	/**
	 * @return ActiveRow
	 */
	public function getRoot(): ActiveRow
	{
		if (!$root = $this->findOneBy(['pos_level' => 0])) {
			$root = $this->find($this->makeRoot());
		}

		return $root;
	}


	/**
	 * @return int
	 */
	public function makeRoot(): int
	{
		return $this->insert($set = [
			'id' => 1,
			'name' => '-root-',
			'pos_left' => 0,
			'pos_right' => 1,
			'pos_level' => 0
		])->id;
	}
}
