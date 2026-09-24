<?php

namespace App\AdminModule\Traits;

use Nette\Forms\Controls\CsrfProtection;
use Nette\Forms\Controls\SelectBox;
use Nette\Forms\Controls\UploadControl;
use Nette\Forms\Form;
use Nette\Forms\Validator;
use Nette\Localization\Translator;
use Contributte\Translation\LocalesResolvers\Session;

trait BaseLocalizationTrait
{
	/** @var Session @inject */
	public $translatorSessionResolver;


	/** @var Translator @inject */
	public $translator;

	/** @var \LanguagesService @inject */
	public $languagesService;

	/** @var \LanguagesEntity[] */
	protected $languages;

	/** @var \LanguagesEntity */
	protected $language;

	/** @var array */
	protected $locales = [];

	/** @var string */
	protected $locale;



	/**
	 *
	 */
	protected function processLocalization(): void
	{
		$this->loadLanguages();
		$this->detectLanguage();
		$this->loadLocales();
		$this->detectLocale();
		$this->setDefaultFormMessages();
	}



	/**
	 * @return void
	 */
	protected function detectLocale(): void
	{
		$this->translator->setLocale($this->getSession('Contributte\Translation\LocalesResolvers\Session')->locale ?? 'cs_CZ');
		$this->template->locale = $this->locale = $this->translator->getLocale();
	}

	/**
	 * @return void
	 */
	protected function loadLocales(): void
	{
		$this->template->locales = $this->locales = $this->translator->getAvailableLocales();
	}



	/**
	 * @return void
	 */
	protected function loadLanguages(): void
	{
		$this->template->languages = $GLOBALS['languages'] = $this->languages = $this->languagesService->getLanguages();
	}


	/**
	 * @return void
	 */
	protected function detectLanguage(): void
	{
		$this->template->language = $GLOBALS['language'] = $this->language = $this->languagesService->detectLanguage($this->session);

		$GLOBALS['lang'] = $this->language->id;
	}


	/**
	 * @param $languagesId
	 * @return void
	 * @throws \Nette\Application\AbortException
	 */
	public function handleSwitchLanguage($languagesId)
	{
		if ($languagesId && isset($this->languages[$languagesId])) {
			$this->session->languages_id = $languagesId;
		}
		$this->redirect('this');
	}


	/**
	 * @param $localeId
	 * @return void
	 * @throws \Nette\Application\AbortException
	 */
	public function handleSwitchLocale($localeId)
	{
		if (in_array($localeId, $this->locales)) {
			$this->translatorSessionResolver->setLocale($localeId);
		}
		$this->redirect('this');
	}



	/**
	 *
	 */
	protected function setDefaultFormMessages(): void
	{
		Validator::$messages = [
			CsrfProtection::PROTECTION => $this->trans('admin.formMessages.protection'),
			Form::EQUAL => $this->trans('admin.formMessages.equal'),
			Form::NOT_EQUAL => $this->trans('admin.formMessages.notEqual'),
			Form::FILLED => $this->trans('admin.formMessages.filled'),
			Form::BLANK => $this->trans('admin.formMessages.blank'),
			Form::MIN_LENGTH => $this->trans('admin.formMessages.minLength'),
			Form::MAX_LENGTH => $this->trans('admin.formMessages.maxLength'),
			Form::LENGTH => $this->trans('admin.formMessages.length'),
			Form::EMAIL => $this->trans('admin.formMessages.email'),
			Form::URL => $this->trans('admin.formMessages.url'),
			Form::INTEGER => $this->trans('admin.formMessages.integer'),
			Form::FLOAT => $this->trans('admin.formMessages.float'),
			Form::MIN => $this->trans('admin.formMessages.min'),
			Form::MAX => $this->trans('admin.formMessages.max'),
			Form::RANGE => $this->trans('admin.formMessages.range'),
			Form::MAX_FILE_SIZE => $this->trans('admin.formMessages.maxFileSize'),
			Form::MAX_POST_SIZE => $this->trans('admin.formMessages.maxPostSize'),
			Form::MIME_TYPE => $this->trans('admin.formMessages.mimeType'),
			Form::IMAGE => $this->trans('admin.formMessages.image'),
			SelectBox::VALID => $this->trans('admin.formMessages.validOption'),
			UploadControl::VALID => $this->trans('admin.formMessages.uploadError')
		];
	}


	/**
	 * @param string $key
	 * @param mixed ...$parameters
	 * @return string
	 */
	public function trans(string $key, ...$parameters): string
	{
		return $this->translator->translate($key, ...$parameters);
	}
}
