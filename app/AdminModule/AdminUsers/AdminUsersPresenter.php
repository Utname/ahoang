<?php

namespace App\AdminModule\Presenters;

use Nette\Application\UI\Form;

class AdminUsersPresenter extends BaseModulePresenter
{
	/** @var \AdminUsersRepository @inject */
	public $repository;

	/** @var \AdminUsersService @inject */
	public $service;

	/** @var string */
	protected $orderBy = 'name';

	/** @var string[]  */
	protected $moduleSortingOptions = [
		'name' => 'name',
		'name__desc' => 'name DESC',
		'email' => 'email',
		'email__desc' => 'email DESC',
	];

	/** @var array */
	protected $adminRolesPairs = [];

	/** @var \AdminRolesRepository @inject */
	public $adminRolesRepo;

	/** @var \BranchesRepository @inject */
	public $branchesRepo;

	/** @var array */
	protected $branchesPairs = [];


	/**
	 *
	 */
	protected function startup()
	{
		parent::startup();

		$this->adminRolesPairs = $this->adminRolesRepo->findAll()->order('name')->fetchPairs('id', 'name');
		$this->template->branchesPairs = $this->branchesPairs = $this->branchesRepo->findAll()->order('pos_order')->fetchPairs('id', 'name');
	}


	/**
	 *
	 */
	public function actionDefault()
	{
		$this['filters-form']->addText('name', 'admin.terms.fullName');
		$this['filters-form']->addText('email', 'admin.terms.email');
		$this['filters-form']->addSelect('admin_roles_id', 'admin.terms.role', $this->adminRolesPairs)->setPrompt('-');

		parent::actionDefault();
	}


	/**
	 * @param Form $form
	 * @return Form
	 */
	protected function setFormFields(Form $form): Form
	{
		$form->addText('name', 'admin.terms.fullName')->setRequired(TRUE);
		$form->addEmail('email', 'admin.terms.email')->setRequired(TRUE);
		$form->addSelect('admin_roles_id', 'admin.terms.role', $this->adminRolesPairs)->setPrompt('-');
		$form->addPassword('new_password', 'admin.terms.newPassword');

		$form->addSelect('is_all_branches_permission', $this->trans('admin.terms.allBranchesPermission'), $this->global['bool'])
			->setDefaultValue(1);

		$form->addSelect('default_branches_id', 'admin.terms.defaultBranch', $this->branchesPairs)->setPrompt('-');

		$selected = $this->entity ? $this->entity->getBranchesIds() : [];
		$form->addHidden('rel_table__admin_users_branches_rel__admin_users__branches');

		foreach ($this->branchesPairs as $relKey => $relVal) {
			$form->addCheckbox('rel_values__admin_users_branches_rel__'.$relKey, $relVal)
				->setDisabled(!$this->aps->has('edit'))
				->setDefaultValue(in_array($relKey, $selected))
				->setTranslator(NULL);
		}

		return parent::setFormFields($form);
	}

	/**
	 * @param \BaseEntity $entity
	 * @param Form $form
	 */
	protected function afterEntitySave(\BaseEntity $entity, Form $form)
	{
		$values = $form->getValues();

		if ($values->new_password) {
			$this->service->setPassword($entity, $values->new_password);
		}

		parent::afterEntitySave($entity, $form);
	}


	/**
	 * @param int $itemId
	 * @return void
	 * @throws \Nette\Application\AbortException
	 */
	public function handleResetPassword(int $itemId)
	{
		if (in_array('edit', $this->moduleActions) && !$this->aps->has('edit')) {
			$this->redirect('AccessDeny:');
		}

		/** @var \AdminUsersEntity $user */
		if (!$user = $this->repository->findEntity($itemId)) {
			$this->flashMessage($this->trans('admin.messages.error'), 'danger');
			$this->redirect('this');
		}

		if (!$this->service->resetPassword($user)) {
			$this->flashMessage($this->trans('admin.messages.error'), 'danger');
			$this->redirect('this');
		}

		$this->flashMessage($this->trans('admin.messages.success'), 'success');
		$this->redirect('this');
	}
}
