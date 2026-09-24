<?php

namespace App\AdminModule\Traits;

use App\AdminModule\Presenters\ImportExportControl;

trait ProductPricesImportExportTrait
{
	/**
	 * @return ImportExportControl
	 */
	public function createComponentImportExport(): ImportExportControl
	{
		$control = new ImportExportControl($this->translator, $this->repository, $this->orderBy, $this['filters']);

		$control->setCsvFileNameParts([
			$this->moduleName,
			$this->priceGroup->name,
			$this->currency->iso_code_3
		]);

		$control
			->addCsvColumn('id', $this->trans('admin.terms.id'), TRUE)
			->addCsvColumn('name', $this->trans('admin.terms.name'), FALSE)
			->addCsvColumn('code', $this->trans('admin.terms.code'), FALSE)
			->addCsvColumn('code_ean', $this->trans('admin.terms.ean'), FALSE)
			->addCsvColumn('price', $this->trans('admin.terms.price'), FALSE)
			->addCsvColumn('is_action', $this->trans('admin.terms.action'), FALSE)
			->addCsvColumn('price_action', $this->trans('admin.terms.actionPrice'), FALSE)
			->addCsvColumn('action_from', $this->trans('admin.terms.actionFrom'), FALSE)
			->addCsvColumn('action_to', $this->trans('admin.terms.actionTo'), FALSE);


		$control->setOnProcessExportItem(function(\BaseEntity $item) use ($control) {
			$product = $item->getProduct();
			$price = $item->getPrice($this->priceGroup, $this->currency);

			$line = [];
			foreach($control->getCsvDefinitionColumns() as $column){
				$value = '';
				switch($column){
					case 'id': $value = $item->id;
						break;
					case 'name': $value = $product->name . ($item->getName() ? (', '.$item->getName()) : '');
						break;
					case 'code': $value = $product->hasVariants() ? $item->code : $product->code;
						break;
					case 'code_ean': $value = $product->code_ean;
						break;
					case 'price': $value = $price && $price->price ? $price->price : '';
						break;
					case 'is_action': $value = $price && $price->is_action ? 1 : 0;
						break;
					case 'price_action': $value = $price && $price->price_action ? $price->price_action : '';
						break;
					case 'action_from': $value = $price && $price->action_from ? $price->action_from->format('d.m.Y') : '';
						break;
					case 'action_to': $value = $price && $price->action_to ? $price->action_to->format('d.m.Y') : '';
						break;
					default: isset($item->{$column}) ? $item->{$column} : '';
						break;
				}
				$line[] = $value;
			}
			return $line;
		});

		$control->setOnProcessImportItem(function(array $item) use ($control) {
			$set = [
				'price_groups_id' => $this->priceGroup->id,
				'currencies_id' => $this->currency->id,
			];

			foreach($control->getCsvDefinitionColumns() as $column){
				$value = $item[$column];
				switch($column){
					case 'id':
						if(!is_numeric($value)){
							return FALSE;
						}
						$set[$column] = $value;
						break;
					case 'price_action':
					case 'price':
						$set[$column] = $value ? $value : NULL;
						break;
					case 'is_action': $set[$column] = $value ? 1 : 0;
						break;
					case 'action_from':
					case 'action_to': $set[$column] = $value ? date('Y-m-d', strtotime($value)) : NULL;
						break;
					default:
						break;
				}
			}

			//insert / update
			$productsPriceEntity = $this->productPricesRepo->findEntityBy([
				'product_variants_id' => $set['id'],
				'price_groups_id' => $set['price_groups_id'],
				'currencies_id' => $set['currencies_id'],
			]);
			$success = FALSE;
			if($productsPriceEntity instanceof \ProductPricesEntity){
				if($set['price']){
					$productsPriceEntity->setValues($set);
					$this->productPricesRepo->update($productsPriceEntity);
					$success = TRUE;
				}
				else{
					$this->productPricesRepo->delete($productsPriceEntity->id);
					$success = TRUE;
				}
			}
			else{
				if($set['price'])
				{
					$this->productPricesRepo->insert($set);
					$success = TRUE;
				}
			}

			if($success){
				//recount prices
				$this->pricesService->recountProductPrices(onlyForPriceGroup: $this->priceGroup);

				//log prices history
				$this->pricesService->logPricesHistory(onlyForPriceGroup: $this->priceGroup);
			}

			return $success;
		});

		return $control;
	}
}
