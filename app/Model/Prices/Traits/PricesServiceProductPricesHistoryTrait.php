<?php


trait PricesServiceProductPricesHistoryTrait
{
	/**
	 * @param PriceGroupsEntity|NULL $onlyForPriceGroup
	 * @param CurrenciesEntity|NULL $onlyForCurrency
	 * @param ProductVariantsEntity|NULL $onlyForVariant
	 * @return void
	 */
	public function logProductPricesHistory(PriceGroupsEntity $onlyForPriceGroup = NULL, CurrenciesEntity $onlyForCurrency = NULL, ProductVariantsEntity $onlyForVariant = NULL): void
	{
		$columns = [];
		foreach($this->database->getStructure()->getColumns($this->productPricesRepo->getTableName()) as $column){
			$columnName = $column['name'];

			if(in_array($columnName, ['id', 'created_at'])){
				continue;
			}
			$columns[] = $columnName;
		}

		$whereParts = [];
		$concatParts = [];
		foreach($columns as $column){
			$concatParts[] = 'COALESCE('.$column.', -1)';
		}
		$whereParts[] = '
			(CONCAT('.implode(', ', $concatParts).') != IFNULL(
				(
					SELECT CONCAT('.implode(', ', $concatParts).') FROM '.$this->productPricesHistoryRepo->getTableName().'
					WHERE '.$this->productPricesHistoryRepo->getTableName().'.product_variants_id = '.$this->productPricesRepo->getTableName().'.product_variants_id AND
					 '.$this->productPricesHistoryRepo->getTableName().'.price_groups_id = '.$this->productPricesRepo->getTableName().'.price_groups_id AND
					 '.$this->productPricesHistoryRepo->getTableName().'.currencies_id = '.$this->productPricesRepo->getTableName().'.currencies_id
					ORDER BY created_at DESC
					LIMIT 1
				)
			, -1))';

		if($onlyForPriceGroup instanceof PriceGroupsEntity){
			$whereParts[] = 'price_groups_id = '.$onlyForPriceGroup->id;
		}
		if($onlyForCurrency instanceof CurrenciesEntity){
			$whereParts[] = 'currencies_id = '.$onlyForCurrency->id;
		}
		if($onlyForVariant instanceof ProductVariantsEntity){
			$whereParts[] = 'product_variants_id = '.$onlyForVariant->id;
		}

		$this->database->query('
			INSERT INTO '.$this->productPricesHistoryRepo->getTableName().' ('.implode(', ', $columns).') 
			SELECT '.implode(', ', $columns).' FROM '.$this->productPricesRepo->getTableName().' '.(!empty($whereParts) ? (' WHERE '.implode(' AND ', $whereParts)) : '').';
		');
	}
}
