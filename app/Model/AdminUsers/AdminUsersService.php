<?php

use Nette\Localization\Translator;
use \Nette\Utils\Random;

class AdminUsersService
{
	use \Nette\SmartObject;

	/**
	 * @param AdminUsersRepository $adminUsersRepo
	 * @param AdminUsersAuthenticator $adminUsersAuthenticator
	 * @param MailingService $mailingService
	 * @param Translator $translator
	 */
	public function __construct(protected AdminUsersRepository $adminUsersRepo, protected AdminUsersAuthenticator $adminUsersAuthenticator, protected MailingService $mailingService, protected Translator $translator)
	{
	}


	/**
	 * @param AdminUsersEntity $adminUsersEntity
	 * @return bool
	 * @throws Exception
	 */
	public function resetPassword(AdminUsersEntity $adminUsersEntity): bool
	{
		$password = Random::generate(6).'-'.Random::generate(6).'-'.Random::generate(6);

		if ($this->setPassword($adminUsersEntity, $password)) {
			$vars = [
				'url' => $GLOBALS['container']->getUrlScript()->getHostUrl().'/admin',
				'email' => $adminUsersEntity->email,
				'password' => $password
			];

			$sender = 'robot@'.preg_replace('/^(.*)\.loc$/', '$1.cz', $GLOBALS['container']->getUrlScript()->getHost());
			$sender = str_replace('@localhost', '@localhost.mail', $sender);

			return $this->mailingService->sendLatteTemplate('adminUserResetPassword.latte', $vars, $this->translator->translate('admin.emails.resetPassword.subject'), [$adminUsersEntity->email], $sender);
		}

		return FALSE;
	}


	/**
	 * @param AdminUsersEntity $adminUsersEntity
	 * @param string $password
	 * @return bool
	 */
	public function setPassword(AdminUsersEntity $adminUsersEntity, string $password): bool
	{
		$adminUsersEntity->setValues([
			'password' => $this->adminUsersAuthenticator->calculateHash($password)
		]);

		return $this->adminUsersRepo->update($adminUsersEntity);
	}
}
