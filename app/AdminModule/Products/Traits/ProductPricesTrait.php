<?php

namespace App\AdminModule\Traits;

use App\AdminModule\Presenters\BulkActionsControl;
use App\AdminModule\Presenters\ProductActionsControl;
use BaseEntity;
use Nette\Application\AbortException;
use Nette\Application\UI\Form;
use Nette\Application\UI\Multiplier;
use Nette\Database\Table\Selection;
use Nette\Utils\Strings;

trait ProductPricesTrait
{
	/**
	 * @return Multiplier
	 */
	protected function createComponentProductActions(): Multiplier
	{
		return new Multiplier(function($itemId) {
			$control = new  ProductActionsControl($this->repository);
			$control->setItemId($itemId);
			$control->setBranchesPairs($this->branchesPairs);
			$control->setBranchesId($this->session->filters[$this->getName()]['custom__branches']);

			return $control;
		});
	}


	/**
	 * @return Form
	 */
	protected function createComponentPricesForm(): Form
	{
		$form = new Form();
		$form->setTranslator($this->translator);

		$form->addSubmit('submit', 'admin.formButtons.save');
		$form->addSubmit('submit_next_page', 'admin.formButtons.saveAndGoToNextPage');

		$form->onSuccess[] = [$this, 'pricesFormSubmitted'];

		return $form;
	}


	/**
	 * @param Form $form
	 * @throws AbortException
	 */
	public function pricesFormSubmitted(Form $form)
	{
		$values = $form->getHttpData();

		foreach ($values as $key => $val) {
			if (Strings::startsWith($key, 'price__')) {
				[$nothing, $productId] = explode('__', $key);

				$isAction = FALSE;
				if ($val && $values['price_action__'.$productId] && $val > $values['price_action__'.$productId] && $values['action_from__'.$productId] && date('Y-m-d', strtotime($values['action_from__'.$productId])) <= date('Y-m-d') && (!$values['action_to__'.$productId] || date('Y-m-d', strtotime($values['action_to__'.$productId])) >= date('Y-m-d'))) {
					$isAction = TRUE;
				}

				$set = [
					'branches_id' => $this->session->filters[$this->getName()]['custom__branches'],
					'currencies_id' => $this->currency->id,
					'products_id' => $productId,
					'price' => $val,
					'price_action' => $values['price_action__'.$productId] !== '' ? $values['price_action__'.$productId] : NULL,
					'is_action' => $isAction,
					'action_from' => $values['action_from__'.$productId] ? date('Y-m-d', strtotime($values['action_from__'.$productId])) : NULL,
					'action_to' => $values['action_to__'.$productId] ? date('Y-m-d', strtotime($values['action_to__'.$productId])) : NULL,
				];

				// Log price change for the main branch (before DB write so origin price is read correctly)
				$logPrice = $isAction ? (float)$values['price_action__'.$productId] : (float)$val;
				if ($set['price']) {
					$this->service->logProductPrice((int)$productId, (int)$set['branches_id'], (int)$set['currencies_id'], $logPrice, NULL, $isAction ? $set['action_from'] : NULL, $isAction ? $set['action_to'] : NULL);
				}

				$productsPriceEntity = $this->productPricesRepository->findEntityBy([
					'products_id' => $productId,
					'branches_id' => $set['branches_id'],
					'currencies_id' => $set['currencies_id'],
				]);
				if ($productsPriceEntity instanceof \ProductPricesEntity) {
					if ($set['price']) {
						$productsPriceEntity->setValues($set);
						$this->productPricesRepository->update($productsPriceEntity);
					}
					else {
						$this->productPricesRepository->delete($productsPriceEntity->id);
					}
				}
				else {
					if ($set['price']) {
						$this->productPricesRepository->insert($set);
					}
				}

				// Log & propagate base price to other branches
				if ($val) {
					foreach ($this->branches as $branch) {
						if ($branch->id == $set['branches_id']) continue;
						$product = $this->repository->findEntity((int)$productId);
						$branchPrice = $product ? $product->getPrice($branch->id) : null;
						if ($branchPrice && isset($branchPrice->is_valid_action) && $branchPrice->is_valid_action) continue;
						$this->service->logProductPrice((int)$productId, (int)$branch->id, (int)$set['currencies_id'], (float)$val);
					}
				}
				$this->productPricesRepository->findAll()->where([
					'branches_id != ' => $this->session->filters[$this->getName()]['custom__branches'],
					'currencies_id' => $this->currency->id,
					'products_id' => $productId,
				])->update([
					'price' => $val
				]);
			}
		}

		if ($form['submit_next_page']->isSubmittedBy()) {
			$this->getPaginatorControl()->setPage($this->getPaginatorControl()->getPage() + 1);
		}

		$this->redirect('this');
	}


	/**
	 * @param $item
	 * @param $itemId
	 * @param $value
	 * @param $branchId
	 * @param $currencyId
	 * @return true
	 */
	public function handleUpdatePrices($item, $itemId, $value, $branchId, $currencyId)
	{
		[$column, $idProduct] = explode("__", $item);

		if ($column == "action_from" || $column == "action_to") {
			$parsedValueDate = \DateTime::createFromFormat('d.m.Y', $value);

			if ($parsedValueDate) {
				$value = $parsedValueDate->format('Y-m-d');
			}
			else {
				$value = NULL;
			}
		}
		if (($column == 'price' || $column == 'price_action') && $value === '') {
			$value = NULL;
		}
		$itemEntity = $this->productPricesRepository->findEntity($itemId);
		if (!is_null($itemEntity) && $itemEntity) {

			$itemEntity->setValue($column, $value);
			$itemEntity->setValue('is_action', FALSE);
			if ($itemEntity->action_from && $itemEntity->price_action && $itemEntity->price_action > 0 && $itemEntity->price && $itemEntity->price > $itemEntity->price_action && date('Y-m-d', strtotime($itemEntity->action_from)) <= date('Y-m-d') && (!$itemEntity->action_to || date('Y-m-d', strtotime($itemEntity->action_to)) >= date('Y-m-d'))) {
				$itemEntity->setValue('is_action', TRUE);
			}

			// Log price change before DB write (origin price is read from current DB state)
			$logPrice = $itemEntity->is_action ? (float)$itemEntity->price_action : (float)$itemEntity->price;
			if ($logPrice && $itemEntity->price) {
				$this->service->logProductPrice((int)$idProduct, (int)$itemEntity->branches_id, (int)$this->currency->id, $logPrice, NULL, $itemEntity->is_action ? $itemEntity->action_from?->format('Y-m-d') : NULL, $itemEntity->is_action ? $itemEntity->action_to?->format('Y-m-d') : NULL);
			}

			$this->productPricesRepository->update($itemEntity);
		}
		else {
			$newValue = [
				"branches_id" => $branchId,
				"products_id" => $idProduct,
				"currencies_id" => $this->currency->id,
				$column => $value
			];

			$newValue['is_action'] = FALSE;
			if ($column == 'action_from' && $value && $value <= date('Y-m-d')) {
				$newValue['is_action'] = TRUE;
			}

			$this->productPricesRepository->insert($newValue);
		}

		if ($column == 'price') {
			foreach ($this->branches as $branch) {
				// Log propagated price change (skip branches with active actions)
				$product = $this->repository->findEntity((int)$idProduct);
				$branchPrice = $product ? $product->getPrice($branch->id) : null;
				if (!($branchPrice && isset($branchPrice->is_valid_action) && $branchPrice->is_valid_action)) {
					$this->service->logProductPrice((int)$idProduct, (int)$branch->id, (int)$this->currency->id, (float)$value);
				}

				if (!$this->productPricesRepository->findAll()->where(["branches_id" => $branch->id,
				                                               "products_id" => $idProduct,
				                                               "currencies_id" => $this->currency->id])->count()) {
					$newValue = [
						"branches_id" => $branch->id,
						"products_id" => $idProduct,
						"currencies_id" => $this->currency->id,
						$column => $value
					];

					$this->productPricesRepository->insert($newValue);
				}
				else {
					$this->productPricesRepository->findAll()->where(["branches_id" => $branch->id,
					                                              "products_id" => $idProduct,
					                                              "currencies_id" => $this->currency->id])->update(['price' => $value]);
				}
			}
		}

		$this['productLabels-'.$idProduct]->redrawAll();

		return TRUE;
	}

	public function handleUpdatePricesAll($itemId, $productsId, $price = null, $price_action = null, $action_from = null, $action_to = null, $branchId = null, $currencyId = null, $price_unit = null)
	{
		// the saving itself is shared with Tisk z mobilu (ProductPriceSaver)
		$this->productPriceSaver->save(
			(int)$productsId,
			(int)($branchId ?: ($this->session->filters[$this->getName()]['custom__branches'] ?? 0)),
			array_map(static fn($branch) => (int)$branch->id, $this->branches),
			$itemId ? (int)$itemId : NULL,
			$price, $price_action, $action_from, $action_to, $price_unit,
		);

		$this['productLabels-'.(int)$productsId]->redrawAll();

		return TRUE;
	}


	/**
	 * @return Form
	 */
	protected function createComponentMultiPricesForm(): Form
	{
		$form = new Form();
		$form->setTranslator($this->translator);

		foreach ($this->branchesPairs as $branchesId => $branchesName) {
			$form->addCheckbox('branch__'.$branchesId, $branchesName)->setDefaultValue(0);
		}

		$form->addSelect('is_action', 'admin.terms.action', $this->global['bool'])->setPrompt('-');
		$form->addText('price', 'admin.terms.price')
			->setHtmlAttribute('type', 'number')
			->setHtmlAttribute('step', '0.01')
			->addCondition(Form::FILLED, TRUE)
			->addRule(Form::Float);
		$form->addText('price_action', 'admin.terms.actionPrice')
			->setHtmlAttribute('type', 'number')
			->setHtmlAttribute('step', '0.01')
			->addCondition(Form::FILLED, TRUE)
			->addRule(Form::Float);

		$form->addText('action_from', 'admin.terms.actionFrom');
		$form->addText('action_to', 'admin.terms.actionFrom');

		$form->addSubmit('submit', 'admin.buttons.save');

		$form->onSuccess[] = [$this, 'multiPricesFormSubmitted'];

		return $form;
	}


	/**
	 * @param Form $form
	 * @return void
	 */
	public function multiPricesFormSubmitted(Form $form)
	{
		$values = $form->getValues(TRUE);

		$branchesIds = [];
		foreach ($values as $key => $val) {
			if (strpos($key, 'branch__') !== FALSE && $val) {
				[$prefix, $id] = explode('__', $key);
				$branchesIds[] = $id;
			}
		}

		$this->selection = $this->repository->findAll();
		$this->applyFilters();

		$price = strlen($values['price']) ? $values['price'] : NULL;
		$priceAction = strlen($values['price_action']) ? $values['price_action'] : NULL;
		$actionFrom = strlen($values['action_from']) ? date('Y-m-d', strtotime($values['action_from'])) : NULL;
		$actionTo = strlen($values['action_to']) ? date('Y-m-d', strtotime($values['action_to'])) : NULL;

		if (count($branchesIds) && (!is_null($price) || !is_null($priceAction) || !is_null($actionFrom) || !is_null($actionTo))) {
			foreach ($this->repository->getEntities($this->selection) as $product) {
				foreach ($branchesIds as $branchesId) {
					$set = [
						'branches_id' => $branchesId,
						'currencies_id' => $this->currency->id,
						'products_id' => $product->id,
					];

					if (!is_null($price)) {
						$set['price'] = $price;
					}

					if (!is_null($priceAction)) {
						$set['price_action'] = $priceAction;
					}

					if (!is_null($actionFrom)) {
						$set['action_from'] = $actionFrom;
					}

					if (!is_null($actionTo)) {
						$set['action_to'] = $actionTo;
					}

					$productsPriceEntity = $this->productPricesRepository->findEntityBy([
						'products_id' => $product->id,
						'branches_id' => $branchesId,
						'currencies_id' => $set['currencies_id'],
					]);
					if ($productsPriceEntity instanceof \ProductPricesEntity) {
						$set['is_action'] = FALSE;
						$productsPriceEntity->setValues($set);

						if ($productsPriceEntity->price && $productsPriceEntity->price_action && $productsPriceEntity->price > $productsPriceEntity->price_action && $productsPriceEntity->action_from && date('Y-m-d', strtotime($productsPriceEntity->action_from)) <= date('Y-m-d') && (!$productsPriceEntity->action_to || date('Y-m-d', strtotime($productsPriceEntity->action_to)) >= date('Y-m-d'))) {
							$set['is_action'] = TRUE;
						}

						// Log price change before DB write
						$logPrice = $set['is_action'] ? (float)$productsPriceEntity->price_action : (float)$productsPriceEntity->price;
						if ($logPrice) {
							$this->service->logProductPrice((int)$product->id, (int)$branchesId, (int)$this->currency->id, $logPrice, NULL, $set['is_action'] ? ($set['action_from'] ?? NULL) : NULL, $set['is_action'] ? ($set['action_to'] ?? NULL) : NULL);
						}

						$productsPriceEntity->setValues($set);
						$this->productPricesRepository->update($productsPriceEntity);
					}
					else {
						$set['is_action'] = FALSE;
						if (!is_null($price) && !is_null($priceAction) && $price > $priceAction && !is_null($actionFrom) && $actionFrom <= date('Y-m-d') && (is_null($actionTo) || $actionTo >= date('Y-m-d'))) {
							$set['is_action'] = TRUE;
						}

						// Log price for new record
						$logPrice = $set['is_action'] ? (float)($set['price_action'] ?? 0) : (float)($set['price'] ?? 0);
						if ($logPrice) {
							$this->service->logProductPrice((int)$product->id, (int)$branchesId, (int)$this->currency->id, $logPrice, NULL, $set['is_action'] ? ($set['action_from'] ?? NULL) : NULL, $set['is_action'] ? ($set['action_to'] ?? NULL) : NULL);
						}

						$this->productPricesRepository->insert($set);
					}
				}

				if (!is_null($price)) {
					foreach (array_diff_key($this->branchesPairs, array_flip($branchesIds)) as $branchesDiffId => $branchesDiffName) {
						// Log propagated price (skip active actions)
						$branchPrice = $product->getPrice($branchesDiffId);
						if (!($branchPrice && isset($branchPrice->is_valid_action) && $branchPrice->is_valid_action)) {
							$this->service->logProductPrice((int)$product->id, (int)$branchesDiffId, (int)$this->currency->id, (float)$price);
						}

						$productsPriceEntity = $this->productPricesRepository->findEntityBy([
							'products_id' => $product->id,
							'branches_id' => $branchesDiffId,
							'currencies_id' => $this->currency->id,
						]);

						if ($productsPriceEntity instanceof \ProductPricesEntity) {
							$productsPriceEntity->setValues(['price' => $price]);
							$this->productPricesRepository->update($productsPriceEntity);
						}
						else {
							$this->productPricesRepository->insert([
								'products_id' => $product->id,
								'branches_id' => $branchesDiffId,
								'currencies_id' => $this->currency->id,
								'price' => $price
							]);
						}
					}
				}
			}
		}

		$this->redirect('this');
	}


	/**
	 * @return void
	 */
	public function handleMultiPrices()
	{
		$this['multiPricesForm']->setDefaults([], TRUE);
		if (($branchesId = $this->session->filters[$this->getName()]['custom__branches']) && isset($this['multiPricesForm']['branch__'.$branchesId])) {
			$this['multiPricesForm']->setDefaults(['branch__'.$branchesId => 1]);
		}

		$this->redrawControl('multiPricesForm');
	}


	/**
	 * @return Form
	 */
	protected function createComponentMultiSaleForm(): Form
	{
		$form = new Form();
		$form->setTranslator($this->translator);
		$form->addMultiSelect('branches_ids', 'admin.terms.branches', $this->branchesPairs)
			->setRequired()
			->setDefaultValue($this->session->multiSaleBranchesIds ?? $this->session->filters[$this->getName()]['custom__branches']);

		$form->addText('action_from', 'admin.terms.actionFrom')
			->setRequired()
			->setDefaultValue($this->session->multiSaleActionFrom ?? '');
		$form->addText('action_to', 'admin.terms.actionTo')
			->setRequired()
			->setDefaultValue($this->session->multiSaleActionTo ?? '');

		$form->addSubmit('submit', 'admin.buttons.save');
		$form->onSuccess[] = [$this, 'multiSaleFormSubmitted'];

		return $form;
	}


	/**
	 * @param Form $form
	 * @return void
	 * @throws AbortException
	 */
	public function multiSaleFormSubmitted(Form $form)
	{
		$values = $form->getValues();
		$data = $form->getHttpData();

		$actionFrom = strlen($values->action_from) ? date('Y-m-d', strtotime($values->action_from)) : NULL;
		$actionTo = strlen($values->action_to) ? date('Y-m-d', strtotime($values->action_to)) : NULL;

		$branches = $values->branches_ids && is_array($values->branches_ids) ? \BranchesRepository::getEntities($this->branchesRepo->findAll()->where('id', $values->branches_ids)) : [];

		if (count($branches) && $actionFrom && $actionTo) {
			if (isset($this->session->multiSaleProductsIds) && is_array($this->session->multiSaleProductsIds) && count($this->session->multiSaleProductsIds)) {
				foreach (\ProductsRepository::getEntities($this->repository->findAll()
					->where('id', $this->session->multiSaleProductsIds)
					->order('FIELD(id, '.implode(',', $this->session->multiSaleProductsIds).')')) as $product) {
					$price = NULL;

					foreach ($branches as $branch) {
						$set = [
							'branches_id' => $branch->id,
							'currencies_id' => $this->currency->id,
							'products_id' => $product->id,
							'action_from' => $actionFrom,
							'action_to' => $actionTo,
						];

						$price = isset($data['price__'.$product->id]) && strlen($data['price__'.$product->id]) ? floatval(str_replace(',', '.', $data['price__'.$product->id])) : NULL;
						$priceAction = isset($data['price_action__'.$product->id]) && strlen($data['price_action__'.$product->id]) ? floatval(str_replace(',', '.', $data['price_action__'.$product->id])) : NULL;

						if (!is_null($price)) {
							$set['price'] = $price;
						}

						if (!is_null($priceAction)) {
							$set['price_action'] = $priceAction;
						}

						$productsPriceEntity = $this->productPricesRepository->findEntityBy([
							'products_id' => $product->id,
							'branches_id' => $branch->id,
							'currencies_id' => $this->currency->id,
						]);

						if ($productsPriceEntity instanceof \ProductPricesEntity) {
							$set['is_action'] = FALSE;
							$productsPriceEntity->setValues($set);

							if ($productsPriceEntity->price && $productsPriceEntity->price_action && $productsPriceEntity->price > $productsPriceEntity->price_action && $productsPriceEntity->action_from && date('Y-m-d', strtotime($productsPriceEntity->action_from)) <= date('Y-m-d') && (!$productsPriceEntity->action_to || date('Y-m-d', strtotime($productsPriceEntity->action_to)) >= date('Y-m-d'))) {
								$set['is_action'] = TRUE;
							}

							// Log price change before DB write
							$logPrice = $set['is_action'] ? (float)$productsPriceEntity->price_action : (float)$productsPriceEntity->price;
							if ($logPrice) {
								$this->service->logProductPrice((int)$product->id, (int)$branch->id, (int)$this->currency->id, $logPrice, NULL, $set['is_action'] ? $set['action_from'] : NULL, $set['is_action'] ? $set['action_to'] : NULL);
							}

							$productsPriceEntity->setValues($set);
							$this->productPricesRepository->update($productsPriceEntity);
						}
						else {
							$set['is_action'] = FALSE;
							if (!is_null($price) && !is_null($priceAction) && $price > $priceAction && !is_null($actionFrom) && $actionFrom <= date('Y-m-d') && (is_null($actionTo) || $actionTo >= date('Y-m-d'))) {
								$set['is_action'] = TRUE;
							}

							// Log price for new record
							$logPrice = $set['is_action'] ? (float)($set['price_action'] ?? 0) : (float)($set['price'] ?? 0);
							if ($logPrice) {
								$this->service->logProductPrice((int)$product->id, (int)$branch->id, (int)$this->currency->id, $logPrice, NULL, $set['is_action'] ? $set['action_from'] : NULL, $set['is_action'] ? $set['action_to'] : NULL);
							}

							$this->productPricesRepository->insert($set);
						}
					}

					if (!is_null($price)) {
						foreach (array_diff_key($this->branchesPairs, array_flip(array_keys($branches))) as $branchesDiffId => $branchesDiffName) {
							// Log propagated price (skip active actions)
							$branchPrice = $product->getPrice($branchesDiffId);
							if (!($branchPrice && isset($branchPrice->is_valid_action) && $branchPrice->is_valid_action)) {
								$this->service->logProductPrice((int)$product->id, (int)$branchesDiffId, (int)$this->currency->id, (float)$price);
							}

							$productsPriceEntity = $this->productPricesRepository->findEntityBy([
								'products_id' => $product->id,
								'branches_id' => $branchesDiffId,
								'currencies_id' => $this->currency->id,
							]);

							if ($productsPriceEntity instanceof \ProductPricesEntity) {
								$productsPriceEntity->setValues(['price' => $price]);
								$this->productPricesRepository->update($productsPriceEntity);
							}
							else {
								$this->productPricesRepository->insert([
									'products_id' => $product->id,
									'branches_id' => $branchesDiffId,
									'currencies_id' => $this->currency->id,
									'price' => $price
								]);
							}
						}
					}
				}
			}
		}

		$this->redirect('this');
	}


	/**
	 * @param string $col
	 * @param string $value
	 * @return void
	 */
	public function handleSetMultiSaleDate(string $col, string $value = NULL)
	{
		if ($col == 'action_from') {
			$this->session->multiSaleActionFrom = $value;
		}

		if ($col == 'action_to') {
			$this->session->multiSaleActionTo = $value;
		}

		$this->redrawControl('multiSaleForm');
	}


	/**
	 * @param $branchesIds
	 * @return void
	 */
	public function handleSetMultiSaleBranches($branchesIds = NULL)
	{
		$this->session->multiSaleBranchesIds = $branchesIds && $branchesIds !== 'null' ? explode(',', $branchesIds) : NULL;

		$this->redrawControl('multiSaleForm');
	}


	/**
	 * @param int|NULL $productsId
	 * @param $value
	 * @return void
	 */
	public function handleSetMultiSalePrice(int $productsId = NULL, $value = NULL)
	{
		if ($productsId) {
			$this->session->multiSaleProductPrices[$productsId] = $value && floatval($value) ? floatval($value) : 0;
		}

		$this->redrawControl('multiSaleForm');
	}



	/**
	 * @param int|NULL $productsId
	 * @param $value
	 * @return void
	 */
	public function handleSetMultiSaleActionPrice(int $productsId = NULL, $value = NULL)
	{
		if ($productsId) {
			$this->session->multiSaleProductActionPrices[$productsId] = $value && floatval($value) ? floatval($value) : 0;
		}

		$this->redrawControl('multiSaleForm');
	}


	/**
	 * @return void
	 */
	public function handlePrepareMultiSale()
	{
		$this->session->multiSaleProductsIds = [];
		$this->session->multiSaleProductPrices = [];
		$this->session->multiSaleProductActionPrices = [];
		$this->session->multiSaleBranchesIds = NULL;
		$this->session->multiSaleActionFrom = NULL;
		$this->session->multiSaleActionTo = NULL;
		$this->redrawControl('multiSaleForm');
	}


	/**
	 * @param string|NULL $ean
	 * @return void
	 */
	public function handleAddMultiSaleProduct(string $ean = NULL)
	{
		$product = NULL;
		if ($ean && $product = $this->repository->findAll()
				->where('code_ean = ? OR id IN (SELECT products_id FROM product_codes WHERE value = ?)', $ean, $ean)
				->limit(1)
				->fetch()) {
			if (!is_array($this->session->multiSaleProductsIds) || !in_array($product->id, $this->session->multiSaleProductsIds)) {
				$this->session->multiSaleProductsIds[] = $product->id;
			}
		}

		if (!$product) {
			if ($ean) {
				$this->newProductModal = TRUE;
				$this->template->newProductModalEan = $ean;
				$this->template->newProductModalIsMultiSale = 1;
				$this->redrawControl('javascripts');
			}
			$this->flashMessage($this->trans('admin.terms.multiSaleProductNotFound'), 'danger');
		}

		$this->redrawControl('multiSaleForm');
		$this->redrawControl('flashMessages');
	}


	/**
	 * @return void
	 */
	public function handleRemoveMultiSaleProduct(int $id = NULL)
	{
		if ($id && $this->session->multiSaleProductsIds && is_array($this->session->multiSaleProductsIds) && in_array($id, $this->session->multiSaleProductsIds)) {
			unset($this->session->multiSaleProductsIds[array_search($id, $this->session->multiSaleProductsIds)]);
		}

		$this->redrawControl('multiSaleForm');
	}


	/**
	 * @return array
	 */
	public function getMultiSaleProducts(): array
	{
		$return = [];

		if (isset($this->session->multiSaleProductsIds) && is_array($this->session->multiSaleProductsIds) && count($this->session->multiSaleProductsIds)) {
			$return = \ProductsRepository::getEntities($this->repository->findAll()
				->where('id', $this->session->multiSaleProductsIds)
				->order('FIELD(id, '.implode(',', $this->session->multiSaleProductsIds).')'));
		}

		return $return;
	}


	/**
	 * @return float|null
	 */
	public function getMultiSaleProductPrice(int $productsId): ?float
	{
		return ($this->session->multiSaleProductPrices[$productsId] ?? NULL);
	}



	/**
	 * @return float|null
	 */
	public function getMultiSaleProductActionPrice(int $productsId): ?float
	{
		return ($this->session->multiSaleProductActionPrices[$productsId] ?? NULL);
	}


	/**
	 * "Historie cen" modal: one product ($productsId), the ticked products ($ids "1,2,3") or every
	 * product of the branch ($all = 1); the list first, the CSV export from its button (custom.js picks
	 * ids: a ticked row shows every ticked product). With many products only the newest 500 rows are listed.
	 * @param int|null $branchesId Explicit branch id (optional); if null, current filter branch is used
	 */
	public function handleShowPricesLog(int $productsId = 0, ?int $branchesId = NULL, string $ids = '', int $all = 0): void
	{
		$this->loadPricesLog($productsId, $branchesId, $ids, $all);
		$this->redrawControl('modalWrapper');
		$this->redrawControl('pricesLog');
	}


	/**
	 * Delete a single price log entry and refresh the modal content (same products as before)
	 * @param int $logId product_prices_log.id
	 */
	public function handleDeletePriceLog(int $logId, int $productsId = 0, string $ids = '', int $all = 0): void
	{
		$row = $this->database->table('product_prices_log')->get($logId);
		if ($row) {
			$productsId = $productsId ?: (!$ids && !$all ? (int)$row->products_id : 0);
			$branchesId = (int)$row->branches_id;
			$this->database->table('product_prices_log')->where('id', $logId)->delete();
			$this->loadPricesLog($productsId, $branchesId, $ids, $all);
		}

		$this->redrawControl('modalWrapper');
		$this->redrawControl('pricesLog');
	}


	private function loadPricesLog(int $productsId, ?int $branchesId, string $ids, int $all): void
	{
		$branchId = (int)($branchesId ?? ($this->session->filters[$this->getName()]['custom__branches'] ?? 0));
		$currencyId = (int)$this->globalContainer->getDefaultCurrency()->id;
		$productIds = $all ? NULL : array_values(array_unique(array_filter(array_map('intval', $ids !== '' ? explode(',', $ids) : [$productsId]))));
		$selection = $this->productPricesLogRepo->findAll()
			->where('branches_id', $branchId)
			->where('currencies_id', $currencyId)
			->order('datetime DESC, id DESC');
		if (!$all) {
			$selection->where('products_id', $productIds ?: [0]);
		}
		$multi = $all || count($productIds ?? []) > 1;
		$total = $multi ? (clone $selection)->count('*') : NULL;
		// many products: grouped by product (name A–Z), newest change first in each, as the CSV
		if ($multi) {
			$orderIds = $this->database->query('SELECT l.id FROM product_prices_log l'
				. ' LEFT JOIN products_mld pm ON pm.products_id = l.products_id AND pm.languages_id = ?'
				. ' WHERE l.branches_id = ? AND l.currencies_id = ?' . ($all ? '' : ' AND l.products_id IN (?)')
				. ' ORDER BY pm.name, l.products_id, l.datetime DESC, l.id DESC LIMIT 500',
				...array_merge(['cs', $branchId, $currencyId], $all ? [] : [$productIds ?: [0]]))->fetchPairs(NULL, 'id');
			$byId = [];
			foreach (\ProductPricesLogRepository::getEntities($this->productPricesLogRepo->findAll()->where('id', $orderIds ?: [0])) as $entity) {
				$byId[(int)$entity->id] = $entity;
			}
			$changes = array_values(array_filter(array_map(static fn($id) => $byId[(int)$id] ?? NULL, $orderIds)));
		}
		else {
			$changes = \ProductPricesLogRepository::getEntities($selection);
		}

		$names = [];
		if ($multi && $changes) {
			$shown = array_values(array_unique(array_map(static fn($c) => (int)$c->products_id, $changes)));
			foreach ($this->database->query('SELECT p.id, p.code_ean, pm.name FROM products p LEFT JOIN products_mld pm ON pm.products_id = p.id AND pm.languages_id = ? WHERE p.id IN (?)', 'cs', $shown) as $r) {
				$names[(int)$r->id] = ['name' => (string)$r->name, 'ean' => (string)$r->code_ean];
			}
		}

		$this->template->priceChanges = $changes;
		$this->template->priceLogProductsId = !$multi ? (int)($productIds[0] ?? 0) : 0;
		$this->template->priceLogIds = !$all && $multi ? implode(',', $productIds) : '';
		$this->template->priceLogAll = $all ? 1 : 0;
		$this->template->priceLogMulti = $multi;
		$this->template->priceLogTotal = $total;
		$this->template->priceLogCount = $all ? NULL : count($productIds ?? []);
		$this->template->priceLogNames = $names;
		$this->template->priceLogBranchesId = $branchId;
		$this->template->priceLogBranchName = (string)($this->database->table('branches_mld')->where('branches_id', $branchId)->where('languages_id', 'cs')->fetchField('name') ?: '');
		$this->template->priceLogProductName = !$multi && $productIds ? (string)($this->database->table('products_mld')->where('products_id', $productIds[0])->where('languages_id', 'cs')->fetchField('name') ?: '') : '';
	}


	/**
	 * Export price log entries to CSV (XLS-compatible) – one product, from the "Historie cen" modal
	 */
	public function handleExportPricesLog(int $productsId, int $branchesId): void
	{
		$product = $this->repository->find($productsId);
		$this->sendPricesLogCsv([$productsId], $branchesId, 'historie-cen-' . ($product ? $product->name : $productsId));
	}


	/**
	 * Price history of the products ticked in the list ($ids "1,2,3") or of every product ($all = 1),
	 * for the branch of the list filter (one of the admin's branches) – Produkty print bar / head.
	 */
	public function handleExportPricesLogMany(string $ids = '', int $all = 0): void
	{
		$branchId = (int)($this->session->filters[$this->getName()]['custom__branches'] ?? 0);
		if (!$branchId || !isset($this->branchesPairs[$branchId])) {
			$this->error('Branch not allowed', 403);
		}
		$productIds = $all ? NULL : array_values(array_unique(array_filter(array_map('intval', explode(',', $ids)))));
		if ($productIds === []) {
			$this->flashMessage($this->trans('admin.pv.noneChecked'), 'warning');
			$this->redirect('this');
		}
		$branch = $this->database->table('branches_mld')->where('branches_id', $branchId)->where('languages_id', 'cs')->fetch();
		$this->sendPricesLogCsv($productIds, $branchId, 'historie-cen-' . ($all ? 'vse' : count($productIds) . '-produktu') . '-' . ($branch ? $branch->name : $branchId) . '-' . date('Y-m-d'));
	}


	/**
	 * CSV (";", UTF-8 BOM, Czech decimals) of product_prices_log for the products (NULL = all) of a branch:
	 * product, EAN, branch, then the columns of the "Historie cen" modal. Newest first per product.
	 * @param int[]|null $productIds
	 */
	private function sendPricesLogCsv(?array $productIds, int $branchId, string $name): void
	{
		$currencyId = (int)$this->globalContainer->getDefaultCurrency()->id;
		$branchName = (string)($this->database->table('branches_mld')->where('branches_id', $branchId)->where('languages_id', 'cs')->fetchField('name') ?: $branchId);
		$sql = 'SELECT l.*, p.code_ean, pm.name FROM product_prices_log l'
			. ' JOIN products p ON p.id = l.products_id'
			. ' LEFT JOIN products_mld pm ON pm.products_id = l.products_id AND pm.languages_id = ?'
			. ' WHERE l.branches_id = ? AND l.currencies_id = ?'
			. ($productIds !== NULL ? ' AND l.products_id IN (?)' : '')
			. ' ORDER BY pm.name, l.products_id, l.datetime DESC, l.id DESC';
		$params = ['cs', $branchId, $currencyId];
		if ($productIds !== NULL) {
			$params[] = $productIds;
		}
		$rows = $this->database->query($sql, ...$params);

		$price = static fn($v): string => $v !== NULL && (float)$v > 0 ? number_format((float)$v, 2, ',', '') : '';
		header('Content-Type: text/csv; charset=utf-8');
		header('Content-Disposition: attachment; filename="' . Strings::webalize($name) . '.csv";');
		$f = fopen('php://output', 'w');
		fprintf($f, chr(0xEF) . chr(0xBB) . chr(0xBF));
		fputcsv($f, ['Produkt', 'EAN', 'Pobočka', 'Datum', 'Původní cena', 'Nová cena', 'Sleva %', 'Referenční cena', 'Akce od', 'Akce do'], ';');
		foreach ($rows as $row) {
			$ref = (float)($row->price_ref ?? 0);
			fputcsv($f, [
				(string)($row->name ?? ''),
				(string)($row->code_ean ?? ''),
				$branchName,
				$row->datetime ? $row->datetime->format('d.m.Y H:i:s') : '',
				$price($row->price_origin),
				number_format((float)$row->price_new, 2, ',', ''),
				$ref > 0 ? (string)round(100 - (float)$row->price_new / $ref * 100) : '',
				$price($row->price_ref),
				$row->action_from ? $row->action_from->format('d.m.Y') : '',
				$row->action_to ? $row->action_to->format('d.m.Y') : '',
			], ';');
		}
		fclose($f);
		exit;
	}
}
