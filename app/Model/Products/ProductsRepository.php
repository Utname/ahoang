<?php


class ProductsRepository extends BaseRepositoryML
{
	protected $tableName = 'products';


	/**
	 * @return array
	 */
	public function getAllTags(): array
	{
		$allTags = [];
		foreach($this->findAll()->where('tags != ?', '')->fetchPairs(NULL, 'tags') as $tags){
			foreach(explode(' ', $tags) as $tag){
				$allTags[$tag] = $tag;
			}
		}
		return $allTags;
	}
}
