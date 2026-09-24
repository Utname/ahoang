<?php


class BlacklistEmailsService
{
    use \Nette\SmartObject;


    /**
     * BlacklistEmailsService constructor.
     * @param BlacklistEmailsRepository $blacklistEmailsRepo
     */
    public function __construct(protected BlacklistEmailsRepository $blacklistEmailsRepo)
    {
    }


	/**
	 * @param array $emails
	 * @return int
	 */
    public function isBlacklisted(array $emails): int
    {
	   return $this->blacklistEmailsRepo->findAll()->where("email IN (?)", $emails)->count();
    }


	/**
	 * @return array
	 */
    public function getBlacklistedEmails(): array
    {
	   return $this->blacklistEmailsRepo->findAll()->fetchPairs('email', 'email');
    }


	/**
	 * @param string $email
	 * @return bool
	 */
    public function addEmail(string $email)
    {
    	$blacklistEmail = $this->blacklistEmailsRepo->findEntityBy(['email' => $email]);
	    if (!$blacklistEmail instanceof BlacklistEmailsEntity)
	    {
		    $this->blacklistEmailsRepo->insert(['email' => $email]);
		    return TRUE;
	    }
	    return FALSE;
    }


	/**
	 * @param string $email
	 * @return bool
	 */
    public function removeEmail(string $email): bool
    {
        $blacklistEmail = $this->blacklistEmailsRepo->findEntityBy(['email' => $email]);

        if ($blacklistEmail instanceof BlacklistEmailsEntity) {
            $this->blacklistEmailsRepo->delete($blacklistEmail->id);
            return TRUE;
        }
        return FALSE;
    }
}
