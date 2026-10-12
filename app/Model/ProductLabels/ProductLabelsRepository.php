<?php


class ProductLabelsRepository extends BaseRepository
{
	protected $tableName = 'product_labels';


	/**
	 * New print-queue label for a product, prefilled from the product (name, notes,
	 * origin, first label header) and its current sale percent.
	 */
	public function insertForProduct(ProductsEntity $product, int $branchesId, int $adminUserId, int $typeId, int $count): ProductLabelsEntity
	{
		$productPrice = $product->getPrice($branchesId);
		$labelHeader = $this->db->table('product_label_headers')->order('pos_order')->limit(1)->fetch();

		$row = $this->insert([
			'product_label_headers_id' => $labelHeader ? $labelHeader->id : NULL,
			'products_id' => $product->id,
			'branches_id' => $branchesId,
			'admin_users_id' => $adminUserId,
			'count' => max(0, $count),
			'datetime_printed' => NULL,
			'type_id' => $typeId,
			'sale_text' => '',
			'sale_percent' => $productPrice ? $productPrice->getActionPercents(TRUE) : 0,
			'is_more_species' => 0,
			'price_text' => ProductLabelsEntity::PRICE_TYPE_CZK,
			'origin_countries_id' => $product->country_origin ?: NULL,
			'name' => $product->name,
			'note_1' => $product->label_note_1,
			'note_2' => $product->label_note_2,
			'note_3' => $product->label_note_3,
		]);

		return static::getEntity($row);
	}
}
