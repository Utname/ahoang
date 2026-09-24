<?php


class BranchesEntity extends BaseEntity
{

    public function getUrl(): string
    {
        return $this->id.'-'.$this->slug;
    }


	/**
	 * @param int|NULL $categoriesId
	 * @return array
	 */
    public function getProducts(int $categoriesId = NULL)
    {
        $productsEntities = [];
        $branch = $this->getRow();
        $productsPrices = $branch->related('product_prices')->where('is_action = 1')
            ->where('action_from <= NOW()')
            ->where('action_to >= NOW()');

        foreach ($productsPrices as $productsPrice) {

            $row = $productsPrice->ref('products', 'products_id');


            $product = ProductsRepository::getEntity($row, $row->related('products_mld'));

	        $productCategories = [];
			if ($categoriesId) {
				$productCategories = $row->related('products_categories_rel', 'products_id')->fetchPairs('categories_id', 'categories_id');
			}

            if($product->is_active && (!$categoriesId || isset($productCategories[$categoriesId]))) {
                $product->price = ProductPricesRepository::getEntity($productsPrice);

                $productsEntities[] = $product;
            }

        }

        return $productsEntities;
    }

    public function getPrice(ProductsEntity $product = NULL, CurrenciesEntity $currency = NULL)
    {
        foreach ($this->getRow()->related('product_prices') as $row) {

            if($row->products_id == $product->id){
                return ProductPricesRepository::getEntity($row);
            }
        }

        return NULL;
    }


	/**
	 * @return string
	 */
	public function getGoogleIframeSrc(): string
	{
		if (strpos($this->google_maps_link, 'iframe') === FALSE) {
			return '';
		}

		return preg_replace('/<iframe(.*?)src="(.*?)"(.*?)>(.*?)<\/iframe>/', '$2', $this->google_maps_link);
	}
}
