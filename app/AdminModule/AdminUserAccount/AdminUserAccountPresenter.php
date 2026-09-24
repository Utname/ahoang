<?php

namespace App\AdminModule\Presenters;

use Nette\Application\UI\Form;

class AdminUserAccountPresenter extends BasePresenter
{
	/** @var \AdminUsersRepository @inject */
	public $adminUsersRepo;

	/** @var \AdminUsersService @inject */
	public $adminUsersService;

	/** @var \AdminUsersAuthenticator @inject */
	public $adminUsersAuthenticator;


	/**
	 * @return Form
	 */
	protected function createComponentEditAccountForm(): Form
	{
		$form = new Form();
		$form->setTranslator($this->translator);

		$form->addText('name', 'admin.account.name')->setRequired(TRUE);
		$form->addEmail('email', 'admin.account.email')->setRequired(TRUE);

		$form->addSubmit('submit', 'admin.account.button');

		$form->onSuccess[] = [$this, 'processEditAccountForm'];

		$form->setDefaults($this->adminUser->getValues());

		return $form;
	}


	/**
	 * @param Form $form
	 */
	public function processEditAccountForm(Form $form): void
	{
		$this->adminUser->setValues($form->getValues(TRUE));
		$this->adminUsersRepo->update($this->adminUser);

		$this->flashMessage($this->trans('admin.account.success'), 'success');
		$this->redirect('this');
	}


	/**
	 * @return Form
	 */
	protected function createComponentEditPasswordForm(): Form
	{
		$form = new Form();
		$form->setTranslator($this->translator);

		$form->addPassword('old_password', 'admin.changePassword.currentPassword')->setRequired(TRUE);
		$form->addPassword('new_password', 'admin.changePassword.newPassword')->setRequired(TRUE);

		$form->addPassword('new_password_verify', 'admin.changePassword.confirmPassword')
			->setRequired(TRUE)
			->addRule(Form::EQUAL, 'admin.changePassword.verificationFailed', $form['new_password']);

		$form->addSubmit('submit', 'admin.changePassword.button');

		$form->onSuccess[] = [$this, 'processEditPasswordForm'];

		return $form;
	}


	/**
	 * @param Form $form
	 */
	public function processEditPasswordForm(Form $form): void
	{
		$values = $form->getValues();

		if (!$this->adminUsersAuthenticator->checkPassword($this->adminUser, $values->old_password)) {
			$this->flashMessage($this->trans('admin.changePassword.error'), 'danger');
			$this->redirect('this');
		}

		if (!$this->adminUsersService->setPassword($this->adminUser, $values->new_password)) {
			$this->flashMessage($this->trans('admin.changePassword.error'), 'danger');
			$this->redirect('this');
		}

		$this->flashMessage($this->trans('admin.changePassword.success'), 'success');
		$this->redirect('this');
	}
}