<?php


class BlogEntity extends BaseEntity
{
	/** @var array */
	protected $categories;

	/**
	 * @return string
	 */
	public function getUrl(): string
	{
		$category = $this->getCategory();
		return (!is_null($category) ? ($category->getUrl().'/') : '').$this->slug;
	}

	/**
	 * @return array
	 */
	public function getCategoryIds(): array
	{
		return $this->getRow()
			->related('blog_blog_categories_rel', 'blog_id')
			->fetchPairs(NULL, 'blog_categories_id');
	}


	/**
	 * @return array
	 */
	public function getCategories(): array
	{
		if (is_null($this->categories)) {
			$this->categories = [];
			$related = $this->getRow()->related('blog_blog_categories_rel');
			foreach ($related as $rel) {
				$row = $rel->ref('blog_categories');
				$entity = BlogCategoriesRepository::getEntity($row, $row->related('blog_categories_mld'));
				$this->categories[$entity->pos_order] = $entity;
			}
			ksort($this->categories);
		}
		return $this->categories;
	}


	/**
	 * @return BlogCategoriesEntity|null
	 */
	public function getCategory(): ?BlogCategoriesEntity
	{
		$categories = $this->getCategories();
		$category = NULL;
		if(!empty($categories)){
			$category = reset($categories);
		}
		return $category;
	}
}
