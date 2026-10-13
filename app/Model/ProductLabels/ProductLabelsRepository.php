<?php


class ProductLabelsRepository extends BaseRepository
{
	protected $tableName = 'product_labels';

	/**
	 * Label texts shared by all templates of a product (✎ Upravit edits them once for
	 * every template). Count and header stay per template. The shared values are kept
	 * on the product's type_id 0 row, so a template added later starts with them.
	 */
	const SHARED_FIELDS = [
		'name', 'sale_text', 'sale_percent', 'is_more_species', 'origin_countries_id',
		'hide_validity', 'hide_ingredients', 'thawed', 'ingredients', 'price_unit', 'price_text',
		'note_1', 'note_2', 'note_3', 'default_units_id', 'default_units_weight',
	];


	/** The product's shared label texts (type_id 0 row), if it was edited. */
	public function findShared(int $productsId, int $branchesId, int $adminUserId): ?ProductLabelsEntity
	{
		$label = $this->findEntityBy([
			'products_id' => $productsId,
			'branches_id' => $branchesId,
			'admin_users_id' => $adminUserId,
			'type_id' => 0,
		]);

		return $label instanceof ProductLabelsEntity ? $label : NULL;
	}


	/**
	 * Saves the shared label texts to the type_id 0 row and to every template label of
	 * the product (counts and headers are not touched).
	 *
	 * @param array<string, mixed> $values
	 */
	public function saveShared(int $productsId, int $branchesId, int $adminUserId, array $values, ?int $headerId = NULL): void
	{
		$shared = array_intersect_key($values, array_flip(self::SHARED_FIELDS));
		foreach (['name', 'sale_text', 'price_text', 'note_1', 'note_2', 'note_3'] as $notNull) {
			if (array_key_exists($notNull, $shared)) {
				$shared[$notNull] = (string)($shared[$notNull] ?? '');
			}
		}
		foreach (['sale_percent', 'is_more_species', 'hide_validity', 'hide_ingredients', 'thawed'] as $int) {
			if (array_key_exists($int, $shared)) {
				$shared[$int] = (int)$shared[$int];
			}
		}

		$where = ['products_id' => $productsId, 'branches_id' => $branchesId, 'admin_users_id' => $adminUserId];
		$this->findAll()->where($where)->update($shared);

		if (!$this->findShared($productsId, $branchesId, $adminUserId)) {
			$headerId = $headerId ?: $this->db->table('product_label_headers')->order('pos_order')->limit(1)->fetch()?->id;
			$this->insert($where + $shared + [
				'type_id' => 0,
				'count' => 0,
				'datetime_printed' => NULL,
				'product_label_headers_id' => $headerId,
			]);
		}
	}


	/**
	 * New print-queue label for a product, prefilled from the product's shared label
	 * texts when it has them, otherwise from the product (name, notes, origin).
	 */
	/**
	 * Hlavička is shared by every template of the product, like the texts: a template
	 * without a label yet gets one (count 0) so it shows and prints the chosen header.
	 */
	public function setHeaderForProduct(ProductsEntity $product, int $branchesId, int $adminUserId, int $headerId): void
	{
		$where = ['products_id' => $product->id, 'branches_id' => $branchesId, 'admin_users_id' => $adminUserId];
		$existing = $this->findAll()->where($where)->fetchPairs('type_id', 'id');
		foreach (ProductLabelsEntity::TYPES_ARR as $typeId => $def) {
			if (!isset($existing[$typeId]) && !ProductLabelsEntity::isLockedType($typeId)) {
				$this->insertForProduct($product, $branchesId, $adminUserId, $typeId, 0);
			}
		}
		$this->findAll()->where($where)->update(['product_label_headers_id' => $headerId]);
	}


	public function insertForProduct(ProductsEntity $product, int $branchesId, int $adminUserId, int $typeId, int $count): ProductLabelsEntity
	{
		// "Admin - Produkty v2": Akce templates start with the "Akce" header, the
		// others with the first one ("Fikko Cena").
		$labelHeader = (ProductLabelsEntity::isAkceType($typeId)
				? $this->db->table('product_label_headers')->get(ProductLabelsEntity::HEADER_TYPE_ACTION)
				: NULL)
			?: $this->db->table('product_label_headers')->order('pos_order')->limit(1)->fetch();

		// shared texts of the product (read through the row: the newer columns are not
		// always in an entity's copied values)
		$shared = [];
		if ($typeId > 0 && ($sharedLabel = $this->findShared((int)$product->id, $branchesId, $adminUserId))) {
			$shared = array_intersect_key($sharedLabel->getRow()->toArray(), array_flip(self::SHARED_FIELDS));
			$shared['ingredients'] = $sharedLabel->getRow()->ingredients;
			$shared['price_unit'] = $sharedLabel->getRow()->price_unit;
		}

		$row = $this->insert($shared + [
			'product_label_headers_id' => $labelHeader ? $labelHeader->id : NULL,
			'products_id' => $product->id,
			'branches_id' => $branchesId,
			'admin_users_id' => $adminUserId,
			'count' => max(0, $count),
			'datetime_printed' => NULL,
			'type_id' => $typeId,
			'sale_text' => '',
			// 0 = worked out at print time from the 30-day low; ✎ Upravit sets an explicit %
			'sale_percent' => 0,
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
