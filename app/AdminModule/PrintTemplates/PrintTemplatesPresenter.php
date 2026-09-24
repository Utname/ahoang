<?php

namespace App\AdminModule\Presenters;

use Nette\Application\UI\Form;
use Nette\Http\FileUpload;

class PrintTemplatesPresenter extends BaseModulePresenter
{
	/** @var \PrintTemplatesRepository @inject */
	public $repository;

	/** @var \PrintTemplatesService @inject */
	public $service;

	/** @var \ProductsRepository @inject */
	public $productsRepo;

	/** @var \BranchesRepository @inject */
	public $branchesRepo;

	/** @var array */
	protected $branchesPairs = [];


	/**
	 * @throws \ReflectionException
	 */
	protected function startup()
	{
		parent::startup();

		$this->branchesPairs = $this->branchesRepo->findAll()->order('pos_order')->fetchPairs('id', 'name');
	}


	/**
	 * @param $id
	 */
	public function actionEdit($id)
	{
		parent::actionEdit($id);

		$this->template->currentId = $id;
		$this->template->branchesPairs = $this->branchesPairs;
	}


	/**
	 *
	 */
	public function actionDefault()
	{
		$this['filters-form']->addText('name', 'admin.terms.name');

		$variantsPairs = [];
		foreach (\PrintTemplatesEntity::VARIANTS_ARR as $id => $term) {
			$variantsPairs[$id] = $this->trans($term);
		}
		$this->template->printTemplateVariantsPairs = $variantsPairs;

		parent::actionDefault();
	}


	/**
	 * @param Form $form
	 * @return Form
	 */
	protected function setFormFields(Form $form): Form
	{
		$form->addText('name', 'admin.terms.name')->setRequired(TRUE);
		$form->addSelect('product_label_type_id', 'admin.terms.printTemplateLabelType', $this->global['productLabelTypes'])
			->setTranslator(NULL)
			->setRequired(TRUE);
		$form->addText('width_mm', 'admin.terms.printTemplateWidthMm')->setRequired(TRUE)->addRule(Form::FLOAT);
		$form->addText('height_mm', 'admin.terms.printTemplateHeightMm')->setRequired(TRUE)->addRule(Form::FLOAT);

		$variantsPairs = [];
		foreach (\PrintTemplatesEntity::VARIANTS_ARR as $id => $term) {
			$variantsPairs[$id] = $this->trans($term);
		}
		$form->addSelect('variant', 'admin.terms.printTemplateVariant', $variantsPairs)->setTranslator(NULL);

		$form->addSelect('is_active', $this->trans('admin.terms.isActive'), $this->global['bool'])->setTranslator(NULL);
		$form->addSelect('is_default', $this->trans('admin.terms.printTemplateIsDefault'), $this->global['bool'])->setTranslator(NULL);

		if ($this->getAction() === 'new') {
			$form->addUpload('import_html_file', 'admin.terms.printTemplateImportFile')->setRequired(FALSE);
		}

		$form->addTextArea('html_source', 'admin.terms.printTemplateHtmlSource')->setRequired($this->getAction() !== 'new');

		return parent::setFormFields($form);
	}


	/**
	 * @param array $values
	 * @return \BaseEntity
	 */
	protected function processCreateForm(array $values): \BaseEntity
	{
		if (isset($values['import_html_file']) && $values['import_html_file'] instanceof FileUpload && $values['import_html_file']->isOk()) {
			$values['html_source'] = $values['import_html_file']->getContents();
		}
		unset($values['import_html_file']);

		return parent::processCreateForm($values);
	}


	/**
	 * Set this template as the default one for its (name, variant) group — unsetting any other default in that group.
	 * @param int $id
	 */
	public function handleSetAsDefault(int $id): void
	{
		$entity = $this->repository->findEntity($id);
		if ($entity) {
			$this->repository->findAll()
				->where('product_label_type_id', $entity->product_label_type_id)
				->where('variant', $entity->variant)
				->update(['is_default' => FALSE]);

			$entity->is_default = TRUE;
			$this->repository->update($entity);

			$this->flashMessage($this->trans('admin.messages.successSave'), 'success');
		}

		$this->redirect('this');
	}


	/** Install the reviewed A4 discount design as an editable mapped template. */
	public function handleInstallA4Preset(): void
	{
		$this->checkPermission('create');

		$template = $this->service->installPreset(\PrintTemplatesService::PRESET_A4_DISCOUNT);
		$this->flashMessage($this->trans('admin.messages.successSave'), 'success');
		$this->redirect('edit', $template->id);
	}


	/** Install the reviewed A5 design as an editable mapped template. */
	public function handleInstallA5Preset(): void
	{
		$this->checkPermission('create');

		$template = $this->service->installPreset(\PrintTemplatesService::PRESET_A5_DISCOUNT);
		$this->flashMessage($this->trans('admin.messages.successSave'), 'success');
		$this->redirect('edit', $template->id);
	}


	/** Install the reviewed Vel.1 design as an editable mapped template. */
	public function handleInstallVel1Preset(): void
	{
		$this->checkPermission('create');

		$template = $this->service->installPreset(\PrintTemplatesService::PRESET_VEL1);
		$this->flashMessage($this->trans('admin.messages.successSave'), 'success');
		$this->redirect('edit', $template->id);
	}


	/** Install the reviewed Vel.3 design as an editable mapped template. */
	public function handleInstallVel3Preset(): void
	{
		$this->checkPermission('create');

		$template = $this->service->installPreset(\PrintTemplatesService::PRESET_VEL3);
		$this->flashMessage($this->trans('admin.messages.successSave'), 'success');
		$this->redirect('edit', $template->id);
	}


	/**
	 * Render the template with a real product's data — used as the live preview <iframe> src on the edit page.
	 * @param int $id
	 * @param int|null $productId
	 * @param int|null $branchId
	 */
	public function handlePreviewHtml(int $id, ?int $productId = NULL, ?int $branchId = NULL): void
	{
		$template = $this->repository->findEntity($id);
		if (!$template) {
			echo 'Template not found.';
			$this->terminate();
		}

		if ($productId) {
			$product = $this->productsRepo->findEntity($productId);
		}
		else {
			$firstRow = $this->productsRepo->findAll()->limit(1)->fetch();
			$product = $firstRow ? \ProductsRepository::getEntity($firstRow) : NULL;
		}
		$branchId = $branchId ?: (int)array_key_first($this->branchesPairs);

		if (!$product) {
			echo 'Product not found.';
			$this->terminate();
		}

		try {
			echo $this->service->renderHtml($template, $product, $branchId);
		}
		catch (\Throwable $e) {
			echo '<pre style="color:#c0392b;white-space:pre-wrap">' . htmlspecialchars($e->getMessage()) . '</pre>';
		}

		$this->terminate();
	}


	/**
	 * Download/preview the actual PDF for one template + one product (manual per-product print, not tied to the old A4/A5/Vel.1-3 grid).
	 * Leaving $productId empty (e.g. the "View" link on the list page) previews with the first available product.
	 * @param int $id
	 * @param int|null $productId
	 * @param int|null $branchId
	 * @param bool $download
	 */
	public function handleDownloadPdf(int $id, ?int $productId = NULL, ?int $branchId = NULL, bool $download = FALSE): void
	{
		$template = $this->repository->findEntity($id);

		if ($productId) {
			$product = $this->productsRepo->findEntity($productId);
		}
		else {
			$firstRow = $this->productsRepo->findAll()->limit(1)->fetch();
			$product = $firstRow ? \ProductsRepository::getEntity($firstRow) : NULL;
		}
		$branchId = $branchId ?: (int)array_key_first($this->branchesPairs);

		if (!$template || !$product) {
			$this->error('Template or product not found.');
		}

		$pdf = $this->service->renderPdf($template, $product, $branchId);

		header('Content-Type: application/pdf');
		header('Content-Disposition: ' . ($download ? 'attachment' : 'inline') . '; filename="' . \Nette\Utils\Strings::webalize($template->name) . '.pdf"');
		echo $pdf;
		exit;
	}
}
