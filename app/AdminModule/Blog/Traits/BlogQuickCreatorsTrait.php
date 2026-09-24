<?php

namespace App\AdminModule\Traits;

use App\AdminModule\Presenters\QuickCreator;
use Nette\Application\UI\Form;

trait BlogQuickCreatorsTrait
{
	/**
	 * @return QuickCreator
	 */
	public function createComponentCategoriesQuickCreator(): QuickCreator
	{

		$control = new QuickCreator($this->aps, $this->translator, $this->blogCategoriesRepo);

		$control->setRequiredPermissions(['BlogCategories_create']);

		$control->setModalTitle($this->trans('admin.terms.category').' - '.$this->trans('admin.actions.new'));
		$control->setButtonText($this->trans('admin.terms.blogBtnCreateNewCat'));

		$control->setDefaultValues([
			'is_active' => TRUE
		]);

		$control->getForm()->addText('name', $this->translator->translate('admin.terms.name'))
			->setHtmlAttribute('class', 'form-control form-control-bigger url-source')
			->setHtmlAttribute('data-target', '.category-url-target')
			->setRequired(TRUE);
		$control->getForm()->addText('slug', $this->translator->translate('admin.terms.slug'))
			->setHtmlAttribute('class', 'form-control webalize category-url-target')
			->setRequired(TRUE);

		$control->setOnCreateCallback(function(\BaseEntity $entity = NULL) use ($control) {
			$this->template->blogCategoryPairs = $this->blogCategoriesRepo->findAll()->order('pos_order')->fetchPairs('id', 'name');
			$this->redrawControl('categoriesList');
		});

		return $control;
	}
}
