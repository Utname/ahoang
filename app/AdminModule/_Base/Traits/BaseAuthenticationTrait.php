<?php

namespace App\AdminModule\Traits;

trait BaseAuthenticationTrait
{
	/** @var \AdminUsersAuthenticator @inject */
	public $adminUsersAuthenticator;

	/** @var \AdminPermissionsService @inject */
	public $aps;

	/** @var \AdminUsersEntity */
	protected $adminUser;


	/**
	 *
	 */
	protected function processAdminUserDetection(): void
	{
		$this->adminUser = $this->getAdminUser();

		if (!$this->adminUser && $this->name != 'Admin:Sign') {
			$this->session->requestedUrlBeforeLogin = $_SERVER['REQUEST_URI'];
			$this->redirect('Sign:in');
		}

		if ($this->adminUser) {
			$this->aps->setAdminUser($this->adminUser);
		}

		$GLOBALS['adminUser'] = $this->template->adminUser = $this->adminUser;
		$this->template->aps = $this->aps;
	}


	/**
	 * @return \AdminUsersEntity|null
	 */
	protected function getAdminUser(): ?\AdminUsersEntity
	{
		$this->adminUsersAuthenticator->setUser($this->user);

		return $this->adminUsersAuthenticator->getLogged();
	}


	/**
	 *
	 */
	public function handleSignOut(): void
	{
		$this->adminUsersAuthenticator->logout();

		$this->flashMessage($this->trans('admin.logout.success'), 'success');
		$this->redirect('Sign:in');
	}
}