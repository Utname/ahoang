<?php

namespace App\AdminModule\Traits;

/**
 * "Admin - Detail produktu" (design_handoff_fikko) as a large modal on Produkty: the product card on
 * one screen with the prices of all the admin's branches. Data and saving: \ProductQuickEditor (shared
 * with the phone app's detail). The modal is built by www/js/admin/product-detail.js from the JSON below;
 * the old 4-tab edit page stays for the rest (parameters, more photos, codes…).
 */
trait ProductDetailTrait
{
	/** @var \ProductQuickEditor @inject */
	public $productQuickEditor;

	/** @var \ImageFilter @inject */
	public $pdImageFilter;

	/** @var \CountriesRepository @inject */
	public $pdCountriesRepo;


	/** Texts, lists and links of the modal (JSON in templates/default.latte). */
	public function productDetailConfig(): array
	{
		$texts = [];
		foreach (['title', 'back', 'unsaved', 'saved', 'saving', 'print', 'save', 'advanced', 'categories', 'photo', 'name', 'ean',
			'eanBad', 'active', 'yes', 'no', 'content', 'contentNote', 'origin', 'unitPreview', 'unitPreviewEmpty', 'onLabel', 'note',
			'ingredients', 'allergens', 'allergensNote', 'nutrition', 'nutritionNote', 'filled', 'empty', 'perex', 'perexPh', 'seo',
			'auto', 'slug', 'metaTitle', 'metaDesc', 'prices', 'priceUnit', 'allPrice', 'allPriceNote', 'apply', 'bulk', 'bulkPrice',
			'setAkce', 'endAkce', 'branch', 'price', 'akcePrice', 'akceRange', 'clear', 'akceBad', 'running', 'planned', 'ended',
			'pricesNote', 'toastAll', 'toastBulk', 'toastPick', 'newTitle', 'leave', 'error', 'createdToast', 'savedToast'] as $key) {
			$texts[$key] = $this->translator->translate('admin.pd.' . $key);
		}

		return [
			'urls' => [
				'data' => $this->link('detailData!'),
				'save' => $this->link('detailSave!'),
				'refresh' => $this->link('detailRefresh!'),
				'edit' => $this->link('edit', ['id' => 999999]),
			],
			'canEdit' => $this->aps->has('edit'),
			'canCreate' => $this->aps->has('create'),
			'today' => (new \DateTime('now', new \DateTimeZone('Europe/Prague')))->format('Y-m-d'),
			'categories' => array_map(fn($id, $name) => ['id' => (int)$id, 'name' => (string)$name], array_keys($c = $this->categoriesRepo->findAll()->where('pos_level > 0')->order('pos_left')->fetchPairs('id', 'name')), $c),
			'countries' => array_map(fn($id, $name) => ['id' => (int)$id, 'name' => (string)$name], array_keys($cs = $this->pdCountriesRepo->findAll()->order('pos_order')->fetchPairs('id', 'name')), $cs),
			'sizeUnits' => array_map(fn($id) => ['id' => $id, 'short' => \ProductsEntity::UNITS_SHORT[$id] ?? \ProductsEntity::UNITS[$id]], array_keys(\ProductsEntity::UNITS)),
			'priceUnits' => \ProductsEntity::PRICE_UNITS,
			'allergens' => \ProductQuickEditor::ALLERGENS,
			't' => $texts,
		];
	}


	/** The card of a product, or an empty one (productsId 0; a scanned unknown code fills the code). */
	public function handleDetailData(int $productsId = 0, string $code = ''): void
	{
		$data = $productsId
			? $this->productQuickEditor->detail($productsId, $this->pdBranchId(), $this->branchesPairs)
			: $this->productQuickEditor->blank($this->branchesPairs, preg_replace('#[^\w.\-/]#u', '', $code));
		if ($data) {
			// the branch of the list filter: the unit price preview uses its price
			$data['branchId'] = $this->pdBranchId();
			$data['image'] = NULL;
			$product = $productsId ? $this->repository->findEntity($productsId) : NULL;
			try {
				$file = $product?->getImage()?->file;
				$data['image'] = $file ? $this->pdImageFilter->process($file, 'adminGallery') : NULL;
			}
			catch (\Throwable $e) {
			}
		}
		$this->sendJson($data);
	}


	/** "✓ Uložit" (multipart POST: the fields, prices[branchId][…], an optional photo). */
	public function handleDetailSave(int $productsId = 0): void
	{
		if (!$this->aps->has($productsId ? 'edit' : 'create')) {
			$this->sendJson(['ok' => FALSE, 'error' => $this->translator->translate('admin.mob.noRight')]);
		}
		$request = $this->getHttpRequest();
		$data = (array)$request->getPost();
		// only the admin's branches
		$data['prices'] = array_intersect_key((array)($data['prices'] ?? []), $this->branchesPairs);
		$branchIds = array_map('intval', array_keys($this->branchesPairs));
		try {
			if ($productsId) {
				$this->productQuickEditor->save($productsId, $data, $this->pdBranchId(), $branchIds, $request->getFile('photo'), FALSE);
			}
			else {
				$productsId = $this->productQuickEditor->create($data, $this->pdBranchId(), $branchIds, $request->getFile('photo'), FALSE);
			}
		}
		catch (\InvalidArgumentException $e) {
			$this->sendJson(['ok' => FALSE, 'error' => $this->translator->translate($e->getMessage())]);
		}
		$this->cleanRelatedModuleCache();
		$this->sendJson(['ok' => TRUE, 'id' => $productsId]);
	}


	/** After saving: the list again (filters kept). */
	public function handleDetailRefresh(): void
	{
		$this->redrawControl('productList');
		$this->redrawControl('productCount');
	}


	private function pdBranchId(): int
	{
		$branchId = (int)($this->session->filters[$this->getName()]['custom__branches'] ?? 0);

		return isset($this->branchesPairs[$branchId]) ? $branchId : (int)array_key_first($this->branchesPairs);
	}
}
