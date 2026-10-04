<?php

namespace App\AdminModule\Traits;


use App\AdminModule\Presenters\BulkActionsControl;
use BaseEntity;
use Nette\Application\AbortException;
use Nette\Application\UI\Form;
use Nette\Database\Table\Selection;
use Nette\Utils\Strings;

trait ProductFormTrait
{

	/**
	 * @return Form
	 */
	protected function createComponentProductsForm(): Form
	{
		$form = new Form();
		$form->setTranslator($this->translator);

		$form->addHidden('is_multi_sale', 0);

		$form->addText('code_ean', 'admin.terms.ean');
		$form->addText('name', 'admin.terms.name')->setRequired(TRUE);
		$form->addText('slug', 'admin.terms.slug')->setRequired(TRUE);
		$form->addSelect('country_origin', 'admin.terms.countryOrigin', $this->countriesPairs)->setTranslator(NULL);
		$form->addSelect('is_active', $this->trans('admin.terms.isActive'), $this->global['bool'])->setTranslator(NULL);
		$form->addTextArea('perex', 'admin.terms.perex');
		$form->addTextArea('text', 'admin.terms.text');

		$form->addSelect('units_id', NULL, \ProductsEntity::UNITS)->setTranslator(NULL);
		$form->addText('weight', 'admin.terms.weight');
		$form->addSelect('default_units_id', NULL, \ProductsEntity::UNITS)->setTranslator(NULL)->setPrompt('-');
		$form->addText('default_units_weight', 'admin.terms.defaultPriceWeight')
			->setNullable()
			->addCondition(Form::FILLED, TRUE)
			->addRule(Form::FLOAT);

		$form->addText('label_note_1', 'admin.terms.note1');
		$form->addText('label_note_2', 'admin.terms.note2');
		$form->addText('label_note_3', 'admin.terms.note3');

		$i = 0;
		foreach ($this->branches as $branch) {
			foreach ($this->currencies as $currency) {
				$i++;
				$form->addText('branch_prices_'.$branch->id.'_'.$currency->id.'_price');
				$form->addText('branch_prices_'.$branch->id.'_'.$currency->id.'_price_action');
				$form->addCheckbox('branch_prices_'.$branch->id.'_'.$currency->id.'_is_action');
				$form->addText('branch_prices_'.$branch->id.'_'.$currency->id.'_action_from');
				$form->addText('branch_prices_'.$branch->id.'_'.$currency->id.'_action_to');

				if ($i > 1) {
					$form['branch_prices_'.$branch->id.'_'.$currency->id.'_price']->setHtmlAttribute('readonly', 'readonly');
				}
			}
		}

		$form->addSubmit('submit', 'admin.buttons.save');
		$form->addSubmit('submit_and_edit', 'admin.buttons.saveAndEdit');

		$form->onSuccess[] = [$this, 'productsFormSubmitted'];

		return $form;
	}


	/**
	 * @param Form $form
	 * @return void
	 * @throws AbortException
	 * @throws \Nette\Utils\UnknownImageFileException
	 */
	public function productsFormSubmitted(Form $form)
	{
		$values = $this->getEditFormValues($form, $this->getAction());
		$entity = $this->processCreateForm($values);

		$data = $form->getHttpData();

		$this->service->updatePrices($entity, $form);

		// categories
		$this->service->resetCategories([$entity->id]);

		if (isset($data['categories'])) {
			foreach ($data['categories'] as $id => $val) {
				/** @var \CategoriesEntity $category */
				if (!isset($val) || !$category = $this->categoriesRepo->findEntity($id)) {
					continue;
				}

				$this->service->addCategory($entity, $category);
			}
		}

		$this->session->filters[$this->getName()]['custom__is_active'] = NULL;
		$this->session->filters[$this->getName()]['custom__is_acttion'] = NULL;
		$this->session->filters[$this->getName()]['custom__name'] = $entity->code_ean ?: $entity->name;

		if ($this->session->filters && isset($this->session->filters[$this->getName()]) && is_array($this->session->filters[$this->getName()])) {
			$this->filters = $this->session->filters[$this->getName()];
		}

		$this->flashMessage($this->trans('admin.messages.successSave'), 'success');

		if (isset($values['is_multi_sale']) && $values['is_multi_sale']) {
			$this->session->multiSaleProductsIds[] = $entity->id;
		}

		if ($form['submit_and_edit']->isSubmittedBy()) {
			$this->template->editProductWindowId = $entity->id;
		}

		$this->redrawControl();
	}


	/**
	 * @return void
	 */
	public function handleProduct($ean = NULL, $isMultiSale = 0)
	{
		$defaults = ['is_multi_sale' => $isMultiSale];
		if ($ean && is_numeric($ean)) {
			$defaults['code_ean'] = $ean;
		}

		$this['productsForm']->setDefaults($defaults, TRUE);
		$this->redrawControl('productsForm');
	}
}
