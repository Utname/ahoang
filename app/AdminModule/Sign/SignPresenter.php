<?php

namespace App\AdminModule\Presenters;

use Nette\Application\UI\Form;
use Nette\Security\AuthenticationException;

class SignPresenter extends BasePresenter
{
	/** @var \AdminUsersRepository @inject */
	public $adminUsersRepo;

	/** @var \AdminUsersService @inject */
	public $adminUsersService;


	/**
	 * @throws \Nette\Application\AbortException
	 */
	public function actionIn(?string $back = NULL): void
	{
		// "Přihlásit" on the website: back to that page afterwards (a path of this site only)
		if ($back !== NULL && preg_match('#^/(?![/\\\\])[^\s]*$#', $back)) {
			$this->session->requestedUrlBeforeLogin = $back;
		}
		if ($this->getAdminUser()) {
			if ($back !== NULL && isset($this->session->requestedUrlBeforeLogin) && $this->session->requestedUrlBeforeLogin === $back) {
				$this->redirectUrl($back);
			}
			$this->redirect('Homepage:');
		}
	}


	/**
	 * @throws \Nette\Application\AbortException
	 */
	public function actionOut(): void
	{
		$this->adminUsersAuthenticator->logout();

		$this->flashMessage($this->trans('admin.logout.success'), 'success');
		$this->redirect('in');
	}


	/**
	 * @return Form
	 */
	protected function createComponentSignInForm(): Form
	{
		$form = new Form();
		$form->setTranslator($this->translator);

		$form->addEmail('email', 'admin.login.email')->setDefaultValue($this->getParameter('email'))->setRequired(TRUE);
		$form->addPassword('password', 'admin.login.password')->setRequired(TRUE);

		$form->addSubmit('submit', 'admin.login.button');

		$form->onSuccess[] = [$this, 'processSignInForm'];

		return $form;
	}


	/**
	 * @param Form $form
	 * @throws \Nette\Application\AbortException
	 */
	public function processSignInForm(Form $form): void
	{
		$values = $form->getValues();

		try {
			$this->user->login($values->email, $values->password);

			$this->flashMessage($this->trans('admin.login.success'), 'success');
			if(isset($this->session->requestedUrlBeforeLogin) && !is_null($this->session->requestedUrlBeforeLogin) && $this->session->requestedUrlBeforeLogin){
				$url = $this->session->requestedUrlBeforeLogin;
				$this->session->requestedUrlBeforeLogin = NULL; // used once
				$this->redirectUrl($url);
			}
			else{
				$this->redirect('Homepage:');
			}
		}
		catch (AuthenticationException $e) {
			$this->flashMessage($this->trans('admin.login.error'), 'danger');
			$this->redirect('this', ['email' => $values->email]);
		}
	}


	/**
	 * Reset password form.
	 * @return Form
	 */
	protected function createComponentForgotPasswordForm()
	{
		$form = new Form();
		$form->setTranslator($this->translator);

		$form->addEmail('email', 'admin.forgottenPassword.email')
			->setDefaultValue($this->getParameter('email'))
			->setRequired(TRUE);

		$form->addSubmit('submit', 'admin.forgottenPassword.button');

		$form->onSuccess[] = [$this, 'processForgotPasswordForm'];

		return $form;
	}


	/**
	 * @param Form $form
	 */
	public function processForgotPasswordForm(Form $form)
	{
		$values = $form->getValues();

		/** @var \AdminUsersEntity $user */
		if (!$user = $this->adminUsersRepo->findEntityByEmail($values->email)) {
			$this->flashMessage($this->trans('admin.forgottenPassword.error'), 'danger');
			$this->redirect('this', ['email' => $values->email]);
		}

		if (!$this->adminUsersService->resetPassword($user)) {
			$this->flashMessage($this->trans('admin.forgottenPassword.error'), 'danger');
			$this->redirect('this', ['email' => $values->email]);
		}

		$this->flashMessage($this->trans('admin.forgottenPassword.success'), 'success');
		$this->redirect('in', ['email' => $values->email]);
	}
}
