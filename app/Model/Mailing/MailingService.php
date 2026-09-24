<?php

use Latte\Engine;
use Nette\DI\Container;
use Nette\Localization\Translator;
use Nette\Mail\Message;
use Nette\Mail\SendmailMailer;
use Nette\Mail\SmtpMailer;
use Nette\SmartObject;
use Nette\Utils\Validators;

class MailingService
{
	use SmartObject;

	/** @var string */
	protected $templatesDir = APP_DIR.'/AdminModule/__email';

	/** @var array */
	protected $parameters;


	/**
	 * @param EmailTemplatesRepository $emailTemplatesRepo
	 * @param MailingQueueRepository $mailingQueueRepository
	 * @param SettingsService $settingsService
	 * @param Container $container
	 * @param BlacklistEmailsService $blacklistEmailsService
	 * @param Translator $translator
	 */
	public function __construct(protected EmailTemplatesRepository $emailTemplatesRepo, protected MailingQueueRepository $mailingQueueRepository, protected SettingsService $settingsService, protected Container $container, protected BlacklistEmailsService $blacklistEmailsService, protected Translator $translator)
	{
		$this->parameters = $container->getParameters();
	}


	/**
	 * @param EmailTemplatesEntity $emailTemplatesEntity
	 * @param $vars
	 * @param $recipients
	 * @param $sender
	 * @param array $attachments
	 * @param bool $sendImmediately
	 * @return bool
	 * @throws Exception
	 */
	public function sendTemplate(EmailTemplatesEntity $emailTemplatesEntity, $vars = [], $recipients = [], $sender = '', array $attachments = [], bool $sendImmediately = TRUE): bool
	{
		$subject = $emailTemplatesEntity->getSubject($vars);
		$body = $emailTemplatesEntity->getHtmlBody($vars, $this->settingsService->getValues());

		foreach ($emailTemplatesEntity->getRecipients() as $recipient) {
			$recipients[] = $recipient;
		}

		foreach ($emailTemplatesEntity->getFiles() as $file) {
			$attachments[] = FS_DIR.'/files/'.FilesService::getFileDir($file->file).'/'.$file->file;
		}

		if (!$sender) {
			$sender = $emailTemplatesEntity->email_from;
		}

		return $this->send($subject, $body, $recipients, $sender, $attachments, $sendImmediately, $emailTemplatesEntity);
	}


	/**
	 * @param string $template
	 * @param array $vars
	 * @param string $subject
	 * @param array $recipients
	 * @param string $sender
	 * @param array $attachments
	 * @param bool $sendImmediately
	 * @return bool
	 * @throws Exception
	 */
	public function sendLatteTemplate(string $template, array $vars, string $subject, array $recipients, string $sender, array $attachments = [], bool $sendImmediately = TRUE): bool
	{
		$latte = new Engine;
		$latte->addFilter('translate', function($string){
			return $this->translator->translate($string);
		});
		$body = $latte->renderToString($this->templatesDir.'/'.$template, $vars);

		return $this->send($subject, $body, $recipients, $sender, $attachments, $sendImmediately);
	}


	/**
	 * @param string $subject
	 * @param string $body
	 * @param array $recipients
	 * @param string $sender
	 * @param array $attachments
	 * @param bool $sendImmediately
	 * @param EmailTemplatesEntity|NULL $emailTemplate
	 * @return bool
	 * @throws Exception
	 */
	public function send(string $subject, string $body, array $recipients, string $sender, array $attachments = [], bool $sendImmediately = TRUE, EmailTemplatesEntity $emailTemplate = NULL): bool
	{
	    if($this->blacklistEmailsService->isBlacklisted($recipients)){
            throw new Exception('Blacklist');
        }

	    if (!$subject) {
			throw new Exception('No email subject');
		}

		if (!$body) {
			throw new Exception('No email body');
		}

		if (!Validators::isEmail($sender)) {
			throw new Exception('No valid email sender');
		}


		$recipients = $this->getValidRecipients($recipients);

		if (!count($recipients)) {
			throw new Exception('No valid email recipients');
		}

		$attachments = $this->getValidAttachments($attachments);

		$sent = FALSE;


		foreach ($recipients as $recipient) {

			$errorMessage = NULL;
			$sent = FALSE;

			if($sendImmediately){
				try {
					$this->mail($subject, $body, $recipient, $sender, $attachments);

					$sent = TRUE;
				}
				catch (Exception $e) {
					$sent = FALSE;
					$errorMessage = $e->getMessage();
				}
			}

			$this->mailingQueueRepository->insert([
				'email_templates_id' => $emailTemplate ? $emailTemplate->id : NULL,
				'subject' => $subject,
				'email_from' => $sender,
				'email_to' => $recipient,
				'body' => $body,
				'attachments' => implode('#', $attachments),
				'sent_at' => $sent ? date('Y-m-d H:i:s') : NULL,
				'error_message' => $errorMessage
			]);
		}

		return $sent;
	}


	/**
	 * @param string $subject
	 * @param string $body
	 * @param string $recipient
	 * @param string $sender
	 * @param array $attachments
	 * @return void
	 */
	public function mail(string $subject, string $body, string $recipient, string $sender, array $attachments = []): void
	{
		$mail = new Message;

		if (!isset($this->parameters['smtpMailer']['host'])) {
			$mailSender = new SendmailMailer();
		}
		else {
			$mailSender = new SmtpMailer([
				'host' => $this->parameters['smtpMailer']['host'],
				'username' => $this->parameters['smtpMailer']['username'],
				'password' => $this->parameters['smtpMailer']['password'],
				'secure' => $this->parameters['smtpMailer']['secured'],
			]);

			if (isset($this->parameters['smtpMailer']['campaignId'])) {
				$mail->setHeader('X-CampaignID', $this->parameters['smtpMailer']['campaignId']);
			}
		}

		$mail->setFrom($sender);
		$mail->setSubject($subject);
		$mail->setHtmlBody($body);

		/** @var GlobalContainer $globalContainer */
		$globalContainer = $GLOBALS['container'];
		$fileTitleFilter = new FileTitleFilter($globalContainer);

		foreach ($attachments as $attachment) {
			$file = pathinfo($attachment, PATHINFO_BASENAME);
			$ext = pathinfo($attachment, PATHINFO_EXTENSION);

			$title = $fileTitleFilter->process($file);

			if($title){
				$mail->addAttachment($title.'.'.$ext, file_get_contents($attachment));
			}
			else{
				$mail->addAttachment($attachment);
			}

		}

		$mail->addTo($recipient);


		$mailSender->send($mail);
	}


	/**
	 * @param int $quantity
	 * @return int
	 */
	public function sendQueuedMails(int $quantity = 10): int
	{
		$emailsSent = 0;
		$mailingQueue = $this->mailingQueueRepository->findAll()->where('sent_at IS NULL AND error_message IS NULL')->limit($quantity);
		foreach($mailingQueue as $mailingQueueRow){
			$errorMessage = NULL;
			$sent = FALSE;
			try {
				$attachments = $mailingQueueRow->attachments ? explode('#', $mailingQueueRow->attachments) : [];
				$this->mail($mailingQueueRow->subject, $mailingQueueRow->body, $mailingQueueRow->email_to, $mailingQueueRow->email_from, $attachments);
				$sent = TRUE;
				$emailsSent++;
			}
			catch (Exception $e) {
				$sent = FALSE;
				$errorMessage = $e->getMessage();
			}

			$mailingQueueRow->update([
				'error_message' => $errorMessage,
				'sent_at' => $sent ? date('Y-m-d H:i:s') : NULL
			]);
		}
		return $emailsSent;
	}


	/**
	 * @param array $attachments
	 * @return array
	 */
	protected function getValidAttachments(array $attachments): array
	{
		$validAttachments = [];
		foreach ($attachments as $attachment) {
			if (file_exists($attachment) && is_file($attachment)) {
				$validAttachments[] = $attachment;
			}
		}

		return $validAttachments;
	}


	/**
	 * @param array $recipients
	 * @return array
	 */
	protected function getValidRecipients(array $recipients): array
	{
		if (isset($GLOBALS['container']) && $GLOBALS['container']->isDevMode()) {
			$devEmail = $GLOBALS['container']->getDevModeEmail();
			return Validators::isEmail($devEmail) ? [$devEmail] : [];
		}

		if ($this->settingsService->getValue('mailing_test_mode')) {
			return Validators::isEmail($this->settingsService->getValue('mailing_test_mode_email')) ? [$this->settingsService->getValue('mailing_test_mode_email')] : [];
		}

		$validRecipients = [];
		foreach ($recipients as $recipient) {
			$recipient = trim($recipient);
			if (Validators::isEmail($recipient) && !in_array($recipient, $validRecipients)) {
				$validRecipients[] = $recipient;
			}
		}

		return $validRecipients;
	}


	/**
	 * @param $system
	 * @return EmailTemplatesEntity|null
	 */
	protected function getTemplate($system): ?EmailTemplatesEntity
	{
		if ($template = $this->emailTemplatesRepo->findOneBySystem($system)) {
			return $this->emailTemplatesRepo->getEntity($template);
		}

		return NULL;
	}
}
