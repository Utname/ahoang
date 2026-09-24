<?php


trait BlogServiceCategoriesTrait
{
	/**
	 * @param BlogEntity $blog
	 * @return void
	 */
	public function resetCategories(BlogEntity $blog): void
	{
		$this->database->table('blog_blog_categories_rel')->where('blog_id', $blog->id)->delete();
	}


	/**
	 * @param BlogEntity $blog
	 * @param BlogCategoriesEntity $category
	 * @return void
	 */
	public function addCategory(BlogEntity $blog, BlogCategoriesEntity $category): void
	{
		$set = [
			'blog_id' => $blog->id,
			'blog_categories_id' => $category->id
		];

		//if duplicate, do nothing
		try{
			$this->database->transaction(function() use ($set) {
				$this->database->table('blog_blog_categories_rel')->insert($set);
			});
		}
		catch(Exception $e){}
	}


	/**
	 * @param BlogEntity $blog
	 * @param BlogCategoriesEntity $category
	 * @return void
	 */
	public function removeCategory(BlogEntity $blog, BlogCategoriesEntity $category): void
	{
		$this->database->table('blog_blog_categories_rel')->where('blog_id', $blog->id)->where('blog_categories_id', $category->id)->delete();
	}
}
