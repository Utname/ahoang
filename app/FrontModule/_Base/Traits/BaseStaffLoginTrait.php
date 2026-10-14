<?php

namespace App\FrontModule\Traits;

/**
 * Staff (admin user) login shown in the front header: the admin session ("adminModule" storage)
 * is read here, signing in happens on /admin/sign/in?back=…, signing out right on the page.
 */
trait BaseStaffLoginTrait
{
	/** @var \AdminUsersAuthenticator @inject */
	public $staffAuthenticator;


	protected function processStaffLogin(): void
	{
		$this->staffAuthenticator->setUser($this->getUser());
		$staff = $this->staffAuthenticator->getLogged();
		$this->template->staffUser = $staff;
		$this->template->staffLoginUrl = '/admin/sign/in?back=' . rawurlencode($this->getHttpRequest()->getUrl()->getPath());
	}


	public function handleStaffSignOut(): void
	{
		$this->staffAuthenticator->setUser($this->getUser());
		$this->staffAuthenticator->logout();
		$this->redirectUrl($this->getHttpRequest()->getUrl()->getPath());
	}
}
