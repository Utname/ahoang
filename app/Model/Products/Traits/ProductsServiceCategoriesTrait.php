<?php


trait ProductsServiceCategoriesTrait
{
	/**
	 * @param array $productIds
	 * @return void
	 */
	public function resetCategories(array $productIds): void
	{
		$this->database->table('products_categories_rel')->where('products_id', $productIds)->delete();
	}


	/**
	 * @param ProductsEntity $product
	 * @param CategoriesEntity $category
	 */
	public function addCategory(ProductsEntity $product, CategoriesEntity $category): void
	{
		$this->addCategories([$product->id], [$category->id]);
	}


	/**
	 * @param array $productIds
	 * @param array $categoryIds
	 * @return void
	 */
	public function addCategories(array $productIds, array $categoryIds): void
	{
		$sets = [];
		foreach($productIds as $productId){
			foreach($categoryIds as $categoriesId){
				$sets[] = [
					'products_id' => $productId,
					'categories_id' => $categoriesId,
				];
			}
		}
		$this->database->transaction(function() use ($productIds, $categoryIds, $sets) {
			$this->database->table('products_categories_rel')->where('products_id', $productIds)->where('categories_id', $categoryIds)->delete();
			foreach(array_chunk($sets, 5000) as $chunkSet){
				$this->database->table('products_categories_rel')->insert($chunkSet);
			}
		});
	}


	/**
	 * @param ProductsEntity $product
	 * @param CategoriesEntity $category
	 */
	public function removeCategory(ProductsEntity $product, CategoriesEntity $category): void
	{
		$this->removeCategories([$product->id], [$category->id]);
	}


	/**
	 * @param array $productIds
	 * @param array $categoyIds
	 * @return void
	 */
	public function removeCategories(array $productIds, array $categoyIds): void
	{
		$this->database->table('products_categories_rel')->where('products_id', $productIds)->where('categories_id', $categoyIds)->delete();
	}
}
