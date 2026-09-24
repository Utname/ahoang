<?php

namespace App\AdminModule\Traits;

use App\AdminModule\Presenters\QuickCreator;
use Nette\Application\UI\Multiplier;
use Nette\Database\Table\ActiveRow;
use Nette\Utils\Html;
use Nette\Utils\Strings;

trait ProductQuickCreatorsTrait
{
	/**
	 * @return Multiplier
	 */
	public function createComponentPropertyOptionQuickCreator(): Multiplier
	{
		return new Multiplier(function ($id) {

			[$classification, $formName, $propertiesId] = explode('__', $id);

			/** @var \PropertiesEntity $propertiesEntity */
			$propertiesEntity = $this->propertiesRepo->findEntity($propertiesId);

			$control = new QuickCreator($this->aps, $this->translator, $this->propertyOptionsRepo);

			$control->setRequiredPermissions(['Properties_edit']);

			$control->setModalTitle($propertiesEntity->name.' - '.$this->trans('admin.actions.new'));

			$control->getForm()->addText('name', $this->translator->translate('admin.terms.name'))
				->setRequired(TRUE);

			if($propertiesEntity->display_types_id === \PropertiesEntity::DISPLAY_TYPE_COLOR){
				$control->getForm()->addText('color', $this->translator->translate('admin.terms.color'))->setHtmlAttribute('class', 'form-control color-picker');
			}

			if($propertiesEntity->display_types_id === \PropertiesEntity::DISPLAY_TYPE_IMAGE){
				$control->getForm()->addUpload('image', $this->translator->translate('admin.terms.image'));
			}

			$control->setCustomCreateCallback(function(array $values) use ($control, $propertiesEntity) {
				$values['properties_id'] = $propertiesEntity->id;
				if ($entity = $this->propertyOptionsRepo->getEntity($this->propertyOptionsRepo->insert($values))) {
					return $entity;
				}
				return NULL;
			});

			$control->setOnCreateCallback(function(\BaseEntity $entity = NULL) use ($control, $propertiesEntity, $formName) {
				$this->payload->fieldItems[$formName]['template_parameters_'.$propertiesEntity->id] = [$entity->id => $entity->name];
				$this->payload->forms[$formName]['template_parameters_'.$propertiesEntity->id] = $entity->id;
				$this->sendPayload();
			});

			return $control;
		});
	}
	/**
	 * @return Multiplier
	 */
	public function createComponentConfiguratorPropertyOptionQuickCreator(): Multiplier
	{
		return new Multiplier(function ($id) {

			[$classification, $formName, $propertiesId] = explode('__', $id);

			/** @var \PropertiesEntity $propertiesEntity */
			$propertiesEntity = $this->propertiesRepo->findEntity($propertiesId);

			$control = new QuickCreator($this->aps, $this->translator, $this->propertyOptionsRepo);

			$control->setCustomButtonHtml(
				Html::el('a')
					->setAttribute('data-toggle', 'tooltip')
					->setAttribute('title', $this->trans('admin.terms.productsBtnCreateNewOption'))
					->setAttribute('class', 'text-gray')
					->addHtml(
						Html::el('i')
							->setAttribute('class', 'far fa-plus-circle')
					)
			);


			$control->setRequiredPermissions(['Properties_edit']);

			$control->setModalTitle($propertiesEntity->name.' - '.$this->trans('admin.actions.new'));

			$control->getForm()->addText('name', $this->translator->translate('admin.terms.name'))
				->setRequired(TRUE);

			if($propertiesEntity->display_types_id === \PropertiesEntity::DISPLAY_TYPE_COLOR){
				$control->getForm()->addText('color', $this->translator->translate('admin.terms.color'))->setHtmlAttribute('class', 'form-control color-picker');
			}

			if($propertiesEntity->display_types_id === \PropertiesEntity::DISPLAY_TYPE_IMAGE){
				$control->getForm()->addUpload('image', $this->translator->translate('admin.terms.image'));
			}

			$control->setCustomCreateCallback(function(array $values) use ($control, $propertiesEntity) {
				$values['properties_id'] = $propertiesEntity->id;
				if ($entity = $this->propertyOptionsRepo->getEntity($this->propertyOptionsRepo->insert($values))) {
					return $entity;
				}
				return NULL;
			});

			$control->setOnCreateCallback(function(\BaseEntity $entity = NULL) use ($control, $propertiesEntity, $formName) {
				$this->redrawControl('productConfiguratorParametersArea');
				$this->redrawControl('productConfiguratorParameters');
			});

			return $control;
		});
	}

	/**
	 * @return QuickCreator
	 */
	public function createComponentBrandsQuickCreator(): QuickCreator
	{
		$control = new QuickCreator($this->aps, $this->translator, $this->brandsRepo);

		$control->setRequiredPermissions(['Brands_create']);

		$control->setModalTitle($this->trans('admin.terms.brand').' - '.$this->trans('admin.actions.new'));

		$control->getForm()->addText('name', $this->translator->translate('admin.terms.name'))
			->setRequired(TRUE);

		$control->setOnCreateCallback(function(\BaseEntity $entity = NULL) use ($control) {
			$this->payload->fieldItems['editForm']['brands_id'] = [$entity->id => $entity->name];
			$this->payload->forms['editForm']['brands_id'] = $entity->id;
			$this->payload->forms['editForm']['product_series_id'] = NULL;
			$this->cacheService->deleteByTags($this->brandsRepo->cleanCacheTags);
			$this->redrawControl('brandsInput');
		});

		return $control;
	}

	/**
	 * @return QuickCreator
	 */
	public function createComponentProductSeriesQuickCreator(): QuickCreator
	{
		$control = new QuickCreator($this->aps, $this->translator, $this->productSeriesRepo);

		$control->setRequiredPermissions(['ProductSeries_create']);

		$control->setModalTitle($this->trans('admin.terms.productSerie').' - '.$this->trans('admin.actions.new'));

		$control->getForm()->addSelect('brands_id', $this->translator->translate('admin.terms.brand'), $this->brandsPairs)
			->setRequired(TRUE);

		$control->getForm()->addText('name', $this->translator->translate('admin.terms.name'))
			->setRequired(TRUE);

		$control->setOnCreateCallback(function(\BaseEntity $entity = NULL) use ($control) {
			$this->setProductSeriesInputItems($entity->brands_id);
			$this->payload->forms['editForm']['brands_id'] = $entity->brands_id;
			$this->payload->forms['editForm']['product_series_id'] = $entity->id;
			$this->redrawControl('productSeriesInput');
		});

		return $control;
	}

	/**
	 * @return QuickCreator
	 */
	public function createComponentSuppliersQuickCreator(): QuickCreator
	{
		$control = new QuickCreator($this->aps, $this->translator, $this->suppliersRepo);

		$control->setRequiredPermissions(['Suppliers_create']);

		$control->setModalTitle($this->trans('admin.terms.supplier').' - '.$this->trans('admin.actions.new'));

		$control->getForm()->addText('name', $this->translator->translate('admin.terms.name'))
			->setRequired(TRUE);

		$control->setOnCreateCallback(function(\BaseEntity $entity = NULL) use ($control) {
			$this->payload->fieldItems['editForm']['suppliers_id'] = [$entity->id => $entity->name];
			$this->payload->forms['editForm']['suppliers_id'] = $entity->id;
			$this->redrawControl('suppliersInput');
		});

		return $control;
	}

	/**
	 * @return QuickCreator
	 */
	public function createComponentWarrantiesQuickCreator(): QuickCreator
	{
		$control = new QuickCreator($this->aps, $this->translator, $this->warrantiesRepo);

		$control->setRequiredPermissions(['Warranties_create']);

		$control->setModalTitle($this->trans('admin.terms.warranty').' - '.$this->trans('admin.actions.new'));

		$control->getForm()->addText('name', $this->translator->translate('admin.terms.name'))
			->setRequired(TRUE);
		$control->getForm()->addText('months', $this->translator->translate('admin.terms.months'))
			->setRequired(TRUE);

		$control->setOnCreateCallback(function(\BaseEntity $entity = NULL) use ($control) {
			$this->payload->fieldItems['editForm']['warranties_id'] = [$entity->id => $entity->name];
			$this->payload->forms['editForm']['warranties_id'] = $entity->id;
			$this->redrawControl('warrantiesInput');
		});

		return $control;
	}

	/**
	 * @return QuickCreator
	 */
	public function createComponentCategoriesQuickCreator(): QuickCreator
	{
		$control = new QuickCreator($this->aps, $this->translator, $this->categoriesRepo);

		$control->setRequiredPermissions(['Categories_create']);

		$control->setModalTitle($this->trans('admin.terms.category').' - '.$this->trans('admin.actions.new'));
		$control->setButtonText($this->trans('admin.terms.productsBtnCreateNewCat'));

		$control->getForm()->addSelect('categories_id', $this->trans('admin.terms.parent'), $this->categoriesPairsMain)
			->setTranslator(NULL)->setPrompt('-');
		$control->getForm()->addText('name', $this->translator->translate('admin.terms.name'))
			->setHtmlAttribute('class', 'form-control form-control-bigger url-source')
			->setHtmlAttribute('data-target', '.category-url-target')
			->setRequired(TRUE);
		$control->getForm()->addText('slug', $this->translator->translate('admin.terms.slug'))
			->setHtmlAttribute('class', 'form-control webalize category-url-target')
			->setRequired(TRUE);

		$control->setCustomCreateCallback(function(array $values) use ($control) {

			$values['is_active'] = TRUE;

			if ($values['categories_id']) {
				$parentEntity = $this->categoriesRepo->getEntity($this->categoriesRepo->find($values['categories_id']));
			}
			else {
				$parentEntity = $this->categoriesRepo->getEntity($this->categoriesRepo->getRoot());
			}

			if ($parentEntity) {
				if ($entity = $this->categoriesRepo->getEntity($this->categoriesRepo->insertChild($parentEntity, $values))) {
					return $entity;
				}
			}
			return NULL;
		});

		$control->setOnCreateCallback(function(\BaseEntity $entity = NULL) use ($control){
			$this->redrawControl('productCategoriesList');
		});

		return $control;
	}

	/**
	 * @return QuickCreator
	 */
	public function createComponentUnitsQuickCreator(): QuickCreator
	{
		$control = new QuickCreator($this->aps, $this->translator, $this->unitsRepo);

		$control->setRequiredPermissions(['Units_create']);

		$control->setModalTitle($this->trans('admin.terms.unit').' - '.$this->trans('admin.actions.new'));

		$control->getForm()->addText('name', $this->translator->translate('admin.terms.name'))
			->setRequired(TRUE);
		$control->getForm()->addText('shortcut', $this->translator->translate('admin.terms.shortcut'))
			->setRequired(TRUE);

		$control->setOnCreateCallback(function(\BaseEntity $entity = NULL) use ($control) {
			//for variants modal
			$this->payload->fieldItems['productVariantsForm']['variant_units_id'] = [$entity->id => $entity->name];
			$this->payload->forms['productVariantsForm']['variant_units_id'] = $entity->id;

			//for edit form
			$this->payload->fieldItems['editForm']['variant_units_id'] = [$entity->id => $entity->name];
			$this->payload->forms['editForm']['variant_units_id'] = $entity->id;
			$this->redrawControl('variantUnitsInput');
		});

		return $control;
	}

	/**
	 * @return QuickCreator
	 */
	public function createComponentAvailabilitiesQuickCreator(): QuickCreator
	{
		$control = new QuickCreator($this->aps, $this->translator, $this->availabilitiesRepo);

		$control->setRequiredPermissions(['Availabilities_create']);

		$this->setAvailablitiesQuickCreatorOptions($control);

		$control->setOnCreateCallback(function(\BaseEntity $entity = NULL) use ($control) {
			//for variants modal
			$this->payload->fieldItems['productVariantsForm']['variant_availabilities_id'] = [$entity->id => $entity->name];
			$this->payload->forms['productVariantsForm']['variant_availabilities_id'] = $entity->id;

			//for edit form
			$this->payload->fieldItems['editForm']['variant_availabilities_id'] = [$entity->id => $entity->name];
			$this->payload->forms['editForm']['variant_availabilities_id'] = $entity->id;
			$this->redrawControl('variantAvailabilitiesInput');
		});

		return $control;
	}

	/**
	 * @return QuickCreator
	 */
	public function createComponentAvailabilitiesSoldQuickCreator(): QuickCreator
	{
		$control = new QuickCreator($this->aps, $this->translator, $this->availabilitiesRepo);

		$control->setRequiredPermissions(['Availabilities_create']);

		$this->setAvailablitiesQuickCreatorOptions($control);

		$control->setOnCreateCallback(function(\BaseEntity $entity = NULL) use ($control) {
			//for variants modal
			$this->payload->fieldItems['productVariantsForm']['variant_availabilities_sold_id'] = [$entity->id => $entity->name];
			$this->payload->forms['productVariantsForm']['variant_availabilities_sold_id'] = $entity->id;

			//for edit form
			$this->payload->fieldItems['editForm']['variant_availabilities_sold_id'] = [$entity->id => $entity->name];
			$this->payload->forms['editForm']['variant_availabilities_sold_id'] = $entity->id;
			$this->redrawControl('variantAvailabilitiesSoldInput');
		});

		return $control;
	}


	/**
	 * @param QuickCreator $control
	 * @return void
	 */
	public function setAvailablitiesQuickCreatorOptions(QuickCreator $control): void
	{
		$control->setModalTitle($this->trans('admin.terms.availability').' - '.$this->trans('admin.actions.new'));

		$control->getForm()->addText('name', $this->translator->translate('admin.terms.name'))
			->setRequired(TRUE);
		$control->getForm()->addTextArea('text', $this->translator->translate('admin.terms.description'))
			->setHtmlAttribute('class', 'form-control h-100');
		$control->getForm()->addText('delivery_in_hours', $this->translator->translate('admin.terms.deliveryInHours'));
	}
}
