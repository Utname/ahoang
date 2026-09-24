<?php

namespace App\FrontModule\Traits;


use App\FrontModule\Presenters\BasePresenter;
use ContentBlocksEntity;
use EmailTemplatesEntity;
use Nette\Application\AbortException;
use Nette\Application\UI\Form;
use Nette\Utils\Html;

trait ContentBlocksControlFormsTrait
{
	/**
	 * @return Form
	 */
	public function createComponentForm(): Form
	{
		$form = new Form();
		$form->setAction($GLOBALS['container']->getUrlScript());

		/** @var BasePresenter $presenter */
		$presenter = $this->getPresenter();

		//form settings
		$form->addText('email_templates_id');
		$form->addText('content_blocks_id');

		//antispam protection
		$form->addHidden('real_name');
		$form->addHidden('system');

		//fields
		$form->addText('name', $presenter->getContent('contact_form', 'name'))
			->setRequired(TRUE);

		$form->addText('email', $presenter->getContent('contact_form', 'email'))
			->setRequired(TRUE)
			->addRule(Form::EMAIL);

		$form->addText('phone', $presenter->getContent('contact_form', 'phone'))
			->setRequired(TRUE);

		$form->addTextArea('text', $presenter->getContent('contact_form', 'message'), 64, 4)
			->setRequired(TRUE);

		$label = Html::el('');
		$label->addText($presenter->getContent('contact_form', 'agree_1').' ');
		$label->addHtml(Html::el('a')
			->href($presenter->getPage('privacy')->full_url)
			->setText($presenter->getContent('contact_form', 'agree_2')));
		$form->addCheckbox('agree', $label)
			->setRequired(TRUE)
			->setHtmlAttribute('class', 'agree-checkbox');

		$form->addSubmit('send', $presenter->getContent('contact_form', 'send'));

		$form->onSuccess[] = [$this, 'formSubmitted'];

		return $form;
	}


	/**
	 * @param Form $form
	 * @throws AbortException
	 * @throws Exception
	 * @throws Exception
	 */
	public function formSubmitted(Form $form)
	{
		$this->processSpamProtection($form);

		/** @var array $values */
		$values = $form->getValues(TRUE);

		/** @var BasePresenter $presenter */
		$presenter = $this->getPresenter();

		/** @var EmailTemplatesEntity $emailTemplatesEntity */
		if ($emailTemplatesEntity = $this->getEmailTemplatesRepo()->findEntity($values['email_templates_id'])) {

			$vars = [
				'name' => $values['name'],
				'email' => $values['email'],
				'text' => $values['text'],
				'phone' => $values['phone'],
			];
			$this->getMailingService()
				->sendTemplate($emailTemplatesEntity, $vars, [$values['email']]);
			$presenter->redirectUrlWithFlashMessage($presenter->getContent('contact_form', 'message_success'), 'success', $presenter->page->full_url);
		}

		$presenter->redirectUrlWithFlashMessage($presenter->getContent('contact_form', 'message_failed'), 'error', $presenter->page->full_url);
	}


	/**
	 * @param Form $form
	 * @return void
	 * @throws AbortException
	 */
	protected function processSpamProtection(Form $form): void
	{
		/** @var array $values */
		$values = $form->getValues(TRUE);

		/** @var BasePresenter $presenter */
		$presenter = $this->getPresenter();

		/** @var ContentBlocksEntity|null $contentBlocksEntity */
		$contentBlocksEntity = $this->getContentBlocksRepo()->findEntity($values['content_blocks_id']);
		if(!$contentBlocksEntity instanceof ContentBlocksEntity) {
			$presenter->redirectUrlWithFlashMessage($presenter->getContent('contact_form', 'message_failed'), 'error', $presenter->page->full_url);
			exit;
		}

		$spamProtection = $contentBlocksEntity->getValue('spam_protection') ?: 'basic';

		//basic antispam protection
		if($spamProtection == 'basic'){
			$numbers = explode('-', $values['system']);
			$randomNumber1 = $numbers[0] ?? 0;
			$randomNumber2 = $numbers[1] ?? 0;
			$number3 = $numbers[2] ?? -1;
			if($values['real_name'] != '' || (int)$randomNumber1 + (int)$randomNumber2 != $number3){
				/** @var BasePresenter $presenter */
				$presenter = $this->getPresenter();
				$presenter->redirectUrlWithFlashMessage($presenter->getContent('default_form_message', 'message_antispam'), 'error', $presenter->page->full_url);
			}
		}

		//recaptcha v2 & recaptcha v2 invisible
		$recaptchaSettingsKey = 'google_recaptcha'.($spamProtection == 'invisible_recaptcha' ? '_invisible' : '');
		$isRecaptcha = in_array($spamProtection, ['recaptcha', 'invisible_recaptcha']) && $presenter->getSettings($recaptchaSettingsKey.'_secret_key') && $presenter->getSettings($recaptchaSettingsKey.'_site_key');
		if($isRecaptcha){
			$data = $form->getHttpData();
			$verify = curl_init();
			curl_setopt($verify, CURLOPT_URL, "https://www.google.com/recaptcha/api/siteverify");
			curl_setopt($verify, CURLOPT_POST, true);
			curl_setopt($verify, CURLOPT_POSTFIELDS, http_build_query([
				'secret' => $presenter->getSettings($recaptchaSettingsKey.'_secret_key'),
				'response' => $data['g-recaptcha-response'] ?? ''
			]));
			curl_setopt($verify, CURLOPT_SSL_VERIFYPEER, false);
			curl_setopt($verify, CURLOPT_RETURNTRANSFER, true);
			$rawResponse = curl_exec($verify);
			$response = json_decode($rawResponse);
			if (!isset($response->success) || !$response->success) {
				$presenter->redirectUrlWithFlashMessage($presenter->getContent('default_form_message', 'message_antispam_recaptcha'), 'error', $presenter->page->full_url);
				exit;
			}
		}
	}
}
