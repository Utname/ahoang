<?php

use \Nette\Security\AuthenticationException;
use \Nette\Security\Passwords;
use \Nette\Security\User;

class AdminUsersAuthenticator implements \Nette\Security\Authenticator
{
	/** @var User */
	protected $user;


	/**
	 * AdminUsersAuthenticator constructor.
	 * @param AdminUsersRepository $adminUsersRepo
	 */
	public function __construct(protected AdminUsersRepository $adminUsersRepo, protected Passwords $passwords)
	{
	}


	/**
	 * @param User $user
	 */
	public function setUser(User $user)
	{
		$this->user = $user;
		$this->user->setAuthenticator($this)->getStorage()->setNamespace('adminModule');
		$this->user->setAuthenticator($this)->getStorage()->setExpiration('+7 days');
	}


	/**
	 * @param string $email
	 * @param string $password
	 */
	public function login(string $email, string $password)
	{
		$this->user->login($email, $password);
	}


	/**
	 *
	 */
	public function logout()
	{
		$this->user->logout();
	}


	/**
	 * @return AdminUsersEntity|null
	 */
	public function getLogged(): ?AdminUsersEntity
	{
		if (!$this->user->isLoggedIn()) {
			return NULL;
		}

		if (!$adminUsersRow = $this->adminUsersRepo->find($this->user->getIdentity()->getId())) {
			return NULL;
		}

		return $this->adminUsersRepo->getEntity($adminUsersRow);
	}


	/**
	 * @param AdminUsersEntity $adminUsersEntity
	 * @return \Nette\Security\SimpleIdentity
	 */
	public function getIdentity(AdminUsersEntity $adminUsersEntity): \Nette\Security\SimpleIdentity
	{
		$values = $adminUsersEntity->getValues();

		unset($values['password'], $values['password_salt']);

		return new \Nette\Security\SimpleIdentity($adminUsersEntity->id, [$adminUsersEntity->admin_roles_id], $values);
	}


	/**
	 * @param string $user
	 * @param string $password
	 * @return \Nette\Security\IIdentity
	 * @throws AuthenticationException
	 */
	public function authenticate(string $user, string $password): \Nette\Security\IIdentity
	{
		if (!$adminUsersRow = $this->adminUsersRepo->findOneByEmail($user)) {
			throw new AuthenticationException("Login failed", self::INVALID_CREDENTIAL);
		}

		$adminUsersEntity = $this->adminUsersRepo->getEntity($adminUsersRow);

		if (!$this->checkPassword($adminUsersEntity, $password)) {
			throw new AuthenticationException("Login failed", self::INVALID_CREDENTIAL);
		}
		else {
			return $this->getIdentity($adminUsersEntity);
		}
	}


	/**
	 * @param string $password
	 * @return string
	 */
	public function calculateHash(string $password): string
	{
		return $this->passwords->hash($password);
	}


	/**
	 * @param AdminUsersEntity $adminUsersEntity
	 * @param string $password
	 * @return bool
	 */
	public function checkPassword(AdminUsersEntity $adminUsersEntity, string $password): bool
	{
		return $this->passwords->verify($password, $adminUsersEntity->password);
	}
}