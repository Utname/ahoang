<?php

namespace App\FrontModule\Traits;

use Nette\Application\AbortException;
use Nette\Application\BadRequestException;
use Nette\Forms\Controls\CsrfProtection;
use Nette\Forms\Controls\SelectBox;
use Nette\Forms\Controls\SubmitButton;
use Nette\Forms\Controls\UploadControl;
use Nette\Forms\Form;
use Nette\Forms\Validator;

trait BaseLocalizationTrait
{
	/** @var \LanguagesRepository @inject */
	public $languagesRepo;

	/** @var \LanguagesService @inject */
	public $languagesService;

	/** @var \LanguagesEntity[] */
	protected $languages;

	/** @var \LanguagesEntity[] */
	protected $menuLanguages;

	/** @var \LanguagesEntity */
	protected $language;


	/**
	 * @return void
	 * @throws BadRequestException
	 */
	protected function processLanguagesLocalization(): void{
		$this->loadLanguages();
		$this->detectLanguage();
	}


	/**
	 * @return void
	 */
	protected function processOtherLocalization(): void
	{
		$this->setDefaultFormMessages();

		$this->globalContainer
			->setLanguage($this->language);

	}


	/**
	 *
	 */
	protected function loadLanguages(): void
	{
		$this->languages = $this->languagesRepo->getEntities($this->languagesRepo->findAll()
			->where('is_active', TRUE)
			->order('pos_order'));

		foreach ($this->languages as $language) {
			$language->page = $language->getRow()->related('pages_mld')->where('pages_id', $this->page->id)->fetch();
		}

		$GLOBALS['languages'] = $this->languages;
	}


	/**
	 * @return \LanguagesEntity
	 * @throws BadRequestException
	 */
	protected function detectLanguage(): \LanguagesEntity
	{
		if(!$this->language instanceof \LanguagesEntity){
			if (!isset($this->languages[$this->getParameter('lang')])) {
				throw new BadRequestException('Page not found.');
			}
			$this->language = $this->languages[$this->getParameter('lang')];
		}

		$this->template->language = $GLOBALS['language'] = $this->language;

		$GLOBALS['lang'] = $this->language->id;

		return $this->language;
	}

	/**
	 * @return array
	 */
	public function getMenuLanguages(): array
	{
		if (is_null($this->menuLanguages)) {
			$this->menuLanguages = [];

			if(!empty($this->languages)){
				/** @var \LanguagesEntity $language */
				foreach($this->languages as  $language){
					if(!$language->is_in_menu){
						continue;
					}
					$this->menuLanguages[$language->id] = $language;
				}
			}
		}

		return $this->menuLanguages;
	}


	/**
	 *
	 */
	protected function setDefaultFormMessages(): void
	{
		Validator::$messages = [
			CsrfProtection::PROTECTION => $this->getContent('default_form_message','protection'),
			Form::EQUAL => $this->getContent('default_form_message','equal'),
			Form::NOT_EQUAL => $this->getContent('default_form_message','not_equal'),
			Form::FILLED => $this->getContent('default_form_message','filled'),
			Form::BLANK => $this->getContent('default_form_message','blank'),
			Form::MIN_LENGTH => $this->getContent('default_form_message','min_length'),
			Form::MAX_LENGTH => $this->getContent('default_form_message','max_length'),
			Form::LENGTH => $this->getContent('default_form_message','length'),
			Form::EMAIL => $this->getContent('default_form_message','email'),
			Form::URL => $this->getContent('default_form_message','url'),
			Form::INTEGER => $this->getContent('default_form_message','integer'),
			Form::FLOAT => $this->getContent('default_form_message','float'),
			Form::MIN => $this->getContent('default_form_message','min'),
			Form::MAX => $this->getContent('default_form_message','max'),
			Form::RANGE => $this->getContent('default_form_message','range'),
			Form::MAX_FILE_SIZE => $this->getContent('default_form_message','max_file_size'),
			Form::MAX_POST_SIZE => $this->getContent('default_form_message','max_post_size'),
			Form::MIME_TYPE => $this->getContent('default_form_message','mime_type'),
			Form::IMAGE => $this->getContent('default_form_message','image'),
			SelectBox::VALID => $this->getContent('default_form_message','valid'),
			UploadControl::VALID => $this->getContent('default_form_message','valid_file')
		];
	}
}
