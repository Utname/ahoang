<?php

namespace App\AdminModule\Traits;

use App\AdminModule\Presenters\QuickCreator;
use Nette\Application\UI\Form;

trait PagesQuickCreatorsTrait
{
	/**
	 * @return QuickCreator
	 */
	public function createComponentPageTypesQuickCreator(): QuickCreator
	{

		$control = new QuickCreator($this->aps, $this->translator, $this->pageTypesRepo);

		$control->setRequiredPermissions(['PageTypes_create']);

		$control->setModalTitle($this->trans('admin.terms.pageType').' - '.$this->trans('admin.actions.new'));

		$control->getForm()->addText('name', $this->translator->translate('admin.terms.name'))
			->setRequired(TRUE);

		$control->getForm()->addText('presenter', $this->translator->translate('admin.terms.presenter'))
			->setRequired(TRUE);

		$control->getForm()->addText('action', $this->translator->translate('admin.terms.action'));

		$control->setOnCreateCallback(function(\BaseEntity $entity = NULL) use ($control) {
			$this->payload->fieldItems['editForm']['page_types_id'] = [$entity->id => $entity->name];
			$this->redrawControl('pageTypesInput');
		});

		return $control;
	}
}
