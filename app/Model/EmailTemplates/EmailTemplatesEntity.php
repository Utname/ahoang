<?php

use Latte\Engine;
use \Nette\Utils\Strings;
use \Nette\Utils\Validators;

class EmailTemplatesEntity extends BaseEntity
{
	const CONTACT_FORM_ID = 8;

	/**
	 * @param array $vars
	 * @return mixed
	 */
	public function getSubject(array $vars)
	{
		$subject = $this->subject;
		foreach ($vars as $key => $val) {
			$subject = str_replace('{*'.Strings::upper($key).'*}', $val, $subject);
		}

		return $subject;
	}


	/**
	 * @param array $vars
	 * @param array|null $settings
	 * @return string
	 */
	public function getHtmlBody(array $vars, ?array $settings)
	{
		return $this->addToEnvelope($this->setVars($vars), $settings);
	}


	/**
	 * @param array $vars
	 * @return string|string[]
	 */
	public function setVars(array $vars)
	{
		$html = $this->text;
		foreach ($vars as $key => $val) {
			$html = str_replace('{*'.Strings::upper($key).'*}', $val, $html);
		}

		return $html;
	}


	/**
	 * @param $html
	 * @param array|null $settings
	 * @return string
	 */
	public function addToEnvelope($html, ?array $settings)
	{
		$latte = new Engine;

		$imageFilter = new ImageFilter($this->globalContainer->getParameters('images'));

		$body = $latte->renderToString(APP_DIR.'/Model/EmailTemplates/_envelope.latte', [
			'text' => $html,
			'settings' => is_array($settings) ? $settings : [],
			//'logo' => is_array($settings) && $settings['logo'] ? $imageFilter->process($settings['logo'], 'original', TRUE, TRUE) : NULL,
			'emailFrom' => $this->email_from,
			'hostUrl' => $this->globalContainer->getUrlScript()->getHostUrl(),
			'colors' => ['base-primary' => 'auto', 'base-dark' => 'auto'],
		]);

		return $body;
	}


	/**
	 * @return array
	 */
	public function getRecipients()
	{
		$recipients = [];

		foreach (explode(',', $this->email_to) as $email) {
			$email = trim($email);
			if (Validators::isEmail($email)) {
				$recipients[] = $email;
			}
		}

		return $recipients;
	}
}
