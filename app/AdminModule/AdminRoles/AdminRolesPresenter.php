<?php

namespace App\AdminModule\Presenters;

use Nette\Application\UI\Form;

class AdminRolesPresenter extends BaseModulePresenter
{
	/** @var \AdminRolesRepository @inject */
	public $repository;

	/** @var \AdminRolesService @inject */
	public $service;

	/** @var \AdminModulesService @inject */
	public $adminModulesService;

	/** @var array */
	protected $modules = [];


	/**
	 * @throws \ReflectionException
	 */
	public function startup()
	{
		parent::startup();

		$this->modules = \AdminModulesService::loadModules($this->translator);
		$this->adminModulesService->updateModulesTable($this->modules);
		$this->aps->updatePermissionsTable($this->modules);

		if (count($availableModules = $this->container->getParameters()['availableModules'])) {
			foreach ($this->modules as $moduleId => $moduleinfo) {
				if (!in_array($moduleId, $availableModules)) {
					unset($this->modules[$moduleId]);
				}
			}
		}

		asort($this->modules);

		$this->template->modules = $this->modules;
	}


	/**
	 *
	 */
	public function actionDefault()
	{
		$this['filters-form']->addText('name', 'admin.terms.name', 50, 255);

		parent::actionDefault();
	}


	/**
	 * @param Form $form
	 * @return Form
	 */
	protected function setFormFields(Form $form): Form
	{
		$form->addText('name', 'admin.terms.name', 50, 255)->setRequired(TRUE);

		$selected = $this->entity ? $this->entity->getPermissionsIds() : [];
		$form->addHidden('rel_table__admin_roles_admin_permissions_rel__admin_roles__admin_permissions');

		foreach ($this->modules as $moduleId => $moduleInfo) {
			foreach ($moduleInfo['actions'] as $actionId) {
				$form->addCheckbox('rel_values__admin_roles_admin_permissions_rel__'.$moduleId.'_'.$actionId, $this->translator->translate('admin.permissions.'.$actionId))
					->setDisabled(!$this->aps->has('edit'))
					->setDefaultValue(in_array($moduleId.'_'.$actionId, $selected))
					->setTranslator(NULL);
			}
		}

		return parent::setFormFields($form);
	}
}
