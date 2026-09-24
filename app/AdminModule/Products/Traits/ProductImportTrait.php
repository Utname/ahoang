<?php

namespace App\AdminModule\Traits;


use App\AdminModule\Presenters\BulkActionsControl;
use BaseEntity;
use Nette\Application\UI\Form;
use Nette\Database\Table\Selection;
use Nette\Http\FileUpload;
use Nette\Utils\Strings;

trait ProductImportTrait
{
	/**
	 * @return Form
	 */
	protected function createComponentImportForm(): Form
	{
		$form = new Form();
		$form->setTranslator($this->translator);

		$form->addSelect('branches_id', 'admin.terms.branches', $this->branchesPairs);

		$form->addUpload('file', 'admin.terms.file')->setRequired(TRUE);

		$form->addSubmit('submit', 'admin.terms.save');

		$form->onSuccess[] = [$this, 'processImportForm'];

		return $form;
	}


	/**
	 * @param Form $form
	 * @return void
	 */
	public function processImportForm(Form $form): void
	{
		$currenciesID = 1;

		$convertUnits = [
			'pcs' => \ProductsEntity::UNITS_TYPE_PIECE,
			'g' => \ProductsEntity::UNITS_TYPE_GRAMS,
			'l' => \ProductsEntity::UNITS_TYPE_LITERS,
			'ml' => \ProductsEntity::UNITS_TYPE_MILLILITERS,
			'pkg' => \ProductsEntity::UNITS_TYPE_PACKAGE,
			'cm' => \ProductsEntity::UNITS_TYPE_CENTIMETER,
		];

		$values = $form->getValues();

		/** @var FileUpload $file */
		$file = $values['file'];
		if ($file->isOk() && Strings::lower(pathinfo($file->getUntrustedName(), PATHINFO_EXTENSION)) == 'csv') {
			$file->move(FS_TEMP.'/prod_import.csv');
			$fp = fopen(FS_TEMP.'/prod_import.csv', 'r');
			$lines = array();
			$counter = 0;
			while ($row = fgetcsv($fp, null, "\t")) {
				$counter++;

				$lines[] = $row;
			}
			$precessedItems = 0;

			if(!empty($lines)){

				$newItems = [];

				$productsEanKey = $this->repository->findAll()->fetchPairs("code_ean", "id");
				$productsPrices = $this->productPricesRepository->findAll()->where("branches_id = ?", $values['branches_id'])->fetchPairs("products_id", "price");

				foreach($lines as $key=>$line){
					$newItem = [];

					if($key == 0) {
						continue;
					}

					$newItem['code_ean']    =  $line[0];
					$newItem['name']        =  trim($line[1]);
					$newItem['price']       =  $line[2];
					//$newItem['group']       =  $line[3];
					$newItem['weight']      =  $line[4];
					$newItem['units_id']    =  isset($convertUnits[$line[5]]) ? $convertUnits[$line[5]] : 0;
					$newItem['slug']        = Strings::webalize($newItem['name']);

					$newItems[] = $newItem;
				}
				try {
					foreach ($newItems as $item) {

						if (isset($productsEanKey[$item['code_ean']])) {
							$product = $this->repository->find($productsEanKey[$item['code_ean']]);

							if($product->name != $item['name'] || $product->weight != $item['weight'] || $product->units_id != $item['units_id']){
								$product = $this->repository->getEntity($product);

								$product->name = $item['name'];
								$product->weight = $item['weight'];
								$product->slug = $item['slug'];
								$product->units_id = $item['units_id'];

								$this->repository->update($product);
							}


							if(!isset($productsPrices[$product->id]) || $productsPrices[$product->id] != $item['price']){
								// Log price change before DB write (origin price already fetched into $productsPrices)
								$this->service->logProductPrice($product->id, (int)$values['branches_id'], $currenciesID, (float)$item['price'], isset($productsPrices[$product->id]) ? (float)$productsPrices[$product->id] : 0.0);

								$productPrice = $this->productPricesRepository->findAll()->where("products_id = ? AND branches_id = ?", $product->id, $values['branches_id']);

								if ($productPrice->count()) {
									$productPrice->update(['price' => $item['price']]);
								} else {
									$productPrice->insert(['products_id' => $product->id, 'branches_id' => $values['branches_id'], 'currencies_id' => $currenciesID, 'price' => $item['price']]);
								}
							}

						} else {

							$setProduct = [
								'code_ean' => $item['code_ean'],
								'name' => $item['name'],
								'weight' => $item['weight'],
								'slug' => $item['slug'],
								'units_id' => $item['units_id']
							];

							$newProduct = $this->repository->insert($setProduct);

							$setPrices = [
								'products_id' => $newProduct->id,
								'branches_id' => $values['branches_id'],
								'currencies_id' => $currenciesID,
								'price' => $item['price']
							];

							$this->productPricesRepository->insert($setPrices);

						}

					}
				}catch(Exception $e){

				}

			}

			if($precessedItems > 0){
				$this->flashMessage(str_replace('%d', $precessedItems, $this->translator->translate('admin.terms.importSuccess')), 'success');
			}
			else {
				$this->flashMessage($this->translator->translate('admin.formMessages.mimeType'), 'danger');
			}
		}
		else {
			$this->flashMessage($this->translator->translate('admin.formMessages.mimeType'), 'danger');
		}
		$this->redirect('this');

	}
}
