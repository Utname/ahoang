<?php


class ContentsEntity extends BaseEntity
{
	const TYPE_PARAMETERS_WRAPPER = 1;
	const TYPE_SECTIONS_WRAPPER = 2;

	/**
	 * @return array
	 */
	public function getParameters(): array
	{
		$items = [];
		$related = $this->getRow()->related('content_parameters')->order('pos_order');
		foreach ($related as $rel) {
			$entity = ContentParametersRepository::getEntity($rel, $rel->related('content_parameters_mld'));

			$items[$entity->id] = $entity;
		}

		return $items;
	}

	/**
	 * @return array
	 */
	public function getSections(): array
	{
		$entities = [];
		$related = $this->getRow()->related('content_sections')->order('pos_order');
		foreach ($related as $rel) {
			$entities[] = ContentSectionsRepository::getEntity($rel);
		}

		return $entities;
	}
}
