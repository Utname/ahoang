<?php

namespace App\AdminModule\Presenters;

use App\AdminModule\Traits\PagesQuickCreatorsTrait;
use Nette\Application\UI\Form;

class PagesPresenter extends BaseTreeModulePresenter
{
	use PagesQuickCreatorsTrait;

	/** @var \PagesRepository @inject */
	public $repository;

	/** @var \PagesService @inject */
	public $service;

	/** @var \PageTypesRepository @inject */
	public $pageTypesRepo;

	/** @var SettingsControl @inject */
	public $settingsControl;

	/** @var array */
	protected $pageTypesPairs = [];


	/**
	 *
	 */
	protected function startup()
	{
		parent::startup();

		$this->pageTypesPairs = $this->pageTypesRepo->findAll()->order('pos_order')->fetchPairs('id', 'name');

		$contentPageTypeIds = $this->pageTypesRepo->findAll()->order('pos_order')->where('presenter LIKE ? ', '%Content%')->fetchPairs('id', 'id');
		$this->template->contentPageTypeIds = (count($contentPageTypeIds) > 0) ? implode('|', $contentPageTypeIds) : '';
	}


	/**
	 *
	 */
	public function actionDefault()
	{
		$this['filters-form']->addText('name', 'admin.terms.name');

		$this['filters-form']->addSelect('pages__page_types_id', 'admin.terms.pageType', $this->pageTypesPairs)->setPrompt('-');

		parent::actionDefault();
	}


	/**
	 * @param Form $form
	 * @return Form
	 */
	protected function setFormFields(Form $form): Form
	{
		$form->addText('name', 'admin.terms.name')->setRequired(TRUE);
		$form->addText('slug', 'admin.terms.slug')->setRequired(TRUE);
		$form->addText('title', 'admin.terms.title');
		$form->addText('breadcrumb', 'admin.terms.breadcrumbName');
		$form->addText('link', 'admin.terms.externalLink');
		$form->addTextArea('text', 'admin.terms.text');
		$form->addSelect('is_active', $this->trans('admin.terms.isActive'), $this->global['bool'])->setTranslator(NULL);

		$form->addSelect('is_in_menu', $this->trans('admin.terms.isInMenu'), $this->global['bool'])
			->setTranslator(NULL)
			->setDefaultValue(0);

		$form->addSelect('is_in_footer', $this->trans('admin.terms.isInFooter'), $this->global['bool'])
			->setTranslator(NULL)
			->setDefaultValue(0);

		$form->addSelect('page_types_id', $this->trans('admin.terms.pageType'), $this->pageTypesPairs)
			->setTranslator(NULL)
			->setPrompt('-');

		$form->addSelect('open_to_blank', $this->trans('admin.terms.openToBlank'), $this->global['bool'])
			->setTranslator(NULL)
			->setDefaultValue(0);

		$form->addText('system', 'admin.terms.system');

		return parent::setFormFields($form);
	}


	/**
	 * @return SettingsControl
	 */
	public function createComponentSettings(): SettingsControl
	{
		return $this->settingsControl;
	}


	/**
	 * @param \BaseEntity $entity
	 * @param Form $form
	 * @return void
	 */
	protected function afterEntitySave(\BaseEntity $entity, Form $form)
	{
		parent::afterEntitySave($entity, $form);

		if($entity->system == 'conditions'){
			$this->flashMessage($this->trans('admin.messages.conditionsReminder'), 'alert');
		}

	}
}
