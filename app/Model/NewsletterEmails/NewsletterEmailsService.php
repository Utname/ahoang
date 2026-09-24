<?php

use Nette\Utils\Random;

class NewsletterEmailsService
{
	use \Nette\SmartObject;

	/** @var \Ecomail\EcomailConnector */
	protected $ecomailConnector;


	/**
	 * @param EmailTemplatesRepository $emailTemplatesRepo
	 * @param MailingService $mailingService
	 * @param PagesRepository $pagesRepo
	 * @param NewsletterEmailsRepository $newsletterEmailsRepo
	 * @param SettingsService $settingsService
	 * @param \Nette\Database\Explorer $database
	 */
	public function __construct(
		protected EmailTemplatesRepository $emailTemplatesRepo,
		protected MailingService $mailingService,
		protected PagesRepository $pagesRepo,
		protected NewsletterEmailsRepository $newsletterEmailsRepo,
		protected SettingsService $settingsService,
		protected \Nette\Database\Explorer $database,
	)
	{
		$this->ecomailConnector = new \Ecomail\EcomailConnector($this->settingsService->getValue('ecomail_api_key'),$this->settingsService->getValue('ecomail_list_id'));
	}


	/**
	 * @param string $email
	 * @param string $languageId
	 * @return int
	 * @throws Exception
	 */
	public function subscribe(string $email, string $languageId): int
	{
		if ($this->newsletterEmailsRepo->findEntityBy(['email' => $email]) instanceof NewsletterEmailsEntity) {
			return NewsletterEmailsEntity::VALIDATION_STATE_ERROR_EXISTS;
		}

		$set = [
			'email' => $email,
			'hash' => Random::generate(32),
			'lang_id' => $languageId
		];

		if (!$row = $this->newsletterEmailsRepo->insert($set)) {
			return NewsletterEmailsEntity::VALIDATION_STATE_ERROR_COULD_NOT_INSERT;
		}

		/** @var \NewsletterEmailsEntity $newsletterEmail */
		$newsletterEmail = $this->newsletterEmailsRepo->findEntity($row->id);

		if (!$this->sendConfirmationRequest($newsletterEmail)) {
			return NewsletterEmailsEntity::VALIDATION_STATE_ERROR_COULD_NOT_SEND_CONFRIMATION_MAIL;
		}

		return NewsletterEmailsEntity::VALIDATION_STATE_SUCCESS;
	}


	/**
	 * @param string|NULL $byEmail
	 * @param string|NULL $byHash
	 * @return bool
	 */
	public function unsubscribe(string $byEmail = NULL, string $byHash = NULL): bool
	{
		$findBy = [];
		if(!is_null($byEmail)){
			$findBy['email'] = $byEmail;
		}
		if(!is_null($byHash)){
			$findBy['hash'] = $byHash;
		}
		if(empty($findBy)){
			return FALSE;
		}

		$newsletterEmail = $this->newsletterEmailsRepo->findEntityBy($findBy);

		if (!$newsletterEmail instanceof \NewsletterEmailsEntity) {
			return FALSE;
		}

		//ecomail
		if($this->ecomailConnector->isActive()){
			$this->ecomailConnector->unsubscribe($newsletterEmail->email);
		}

		$this->newsletterEmailsRepo->delete($newsletterEmail->id);

		return TRUE;
	}


	/**
	 * @param string|NULL $byEmail
	 * @param string|NULL $byHash
	 * @return NewsletterEmailsEntity|null
	 */
	public function confirmSubscription(string $byEmail = NULL, string $byHash = NULL): ?NewsletterEmailsEntity
	{
		$findBy = [];
		if(!is_null($byEmail)){
			$findBy['email'] = $byEmail;
		}
		if(!is_null($byHash)){
			$findBy['hash'] = $byHash;
		}
		if(empty($findBy)){
			return NULL;
		}

		$newsletterEmail = $this->newsletterEmailsRepo->findEntityBy($findBy);

		if (!$newsletterEmail instanceof \NewsletterEmailsEntity) {
			return NULL;
		}

		$newsletterEmail->confirmed_at = date('Y-m-d H:i:s');
		$this->newsletterEmailsRepo->update($newsletterEmail);

		//ecomail
		if($this->ecomailConnector->isActive()){
			$this->ecomailConnector->subscribe($newsletterEmail->email, $newsletterEmail->hash);
		}

		return $newsletterEmail;
	}


	/**
	 * @param NewsletterEmailsEntity $newsletterEmail
	 * @return bool
	 */
	public function sendConfirmationRequest(NewsletterEmailsEntity $newsletterEmail): bool
	{
		$langId = $GLOBALS['lang'];

		if ($newsletterEmail->lang_id) {
			$GLOBALS['lang'] = $newsletterEmail->lang_id;
		}

		/** @var PagesEntity $page */
		$page = $this->pagesRepo->findEntityBySystem('home');

		/** @var \EmailTemplatesEntity $emailTemplate */
		$emailTemplate = $this->emailTemplatesRepo->findEntityBySystem('newsletter_confirmation_request');

		$GLOBALS['lang'] = $langId;

		if (!$emailTemplate) {
			return FALSE;
		}

		if (!$newsletterEmail->hash) {
			$newsletterEmail->hash = \Nette\Utils\Random::generate(32);
			$this->newsletterEmailsRepo->update($newsletterEmail);
		}

		$vars = [
			'email' => $newsletterEmail->email,
			'url' => $GLOBALS['container']->getUrlScript()->getHostUrl().$page?->full_url.'?ncr='.$newsletterEmail->hash
		];


		$state = FALSE;
		try{
			$state = $this->mailingService->sendTemplate($emailTemplate, $vars, [$newsletterEmail->email]);
		}
		catch(Exception $e){
			\Tracy\Debugger::log($e);
		}
		return $state;
	}


	/**
	 * @return int
	 */
	public function deleteInvalidSubscriptions(): int
	{
		return $this->database->table($this->newsletterEmailsRepo->getTableName())->where('confirmed_at IS NULL AND created_at < ?', date('Y-m-d h:i:s', strtotime('- 3 days')))->delete();
	}
}
