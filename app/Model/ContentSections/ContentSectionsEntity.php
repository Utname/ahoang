<?php

class ContentSectionsEntity extends BaseEntity
{
	/**
	 * @return array
	 */
	public function getBlocks(): array
	{
		$entities = [];
		$related = $this->getRow()->related('content_blocks')->order('pos_order');
		foreach ($related as $rel) {
			$entities[] = ContentBlocksRepository::getEntity($rel);
		}

		return $entities;
	}


	/**
	 * @param string $parameter
	 * @return string
	 */
	public function getValue(string $parameter): string
	{
		$data = json_decode($this->data, TRUE);

		return isset($data[$parameter]) ? $data[$parameter] : '';
	}
}
