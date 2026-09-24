<?php

namespace App\AdminModule\Presenters;

use Nette\Application\UI\Form;
use Nette\Http\FileUpload;
use Nette\Localization\Translator;
use Nette\Utils\Finder;
use Nette\Utils\Random;
use Nette\Utils\Strings;

class SettingsControl extends \Nette\Application\UI\Control
{
	/** @var object|null */
	protected $settings;

	/** @var array|null */
	protected $emailTemplatesPairs;

	/** @var \BranchesRepository @inject */
	public $branchesRepository;


	/**
	 * @param Translator $translator
	 * @param \AdminPermissionsService $aps
	 * @param \SettingsRepository $repository
	 * @param \UnitsRepository $unitsRepo
	 * @param \ContentParametersRepository $contentParametersRepo
	 * @param \EmailTemplatesRepository $emailTemplatesRepo
	 * @param \BranchesRepository $branchesRepo
	 * @param \CacheService $cacheService
	 */
	public function __construct(protected Translator $translator, protected \AdminPermissionsService $aps, protected \SettingsRepository $repository, protected \UnitsRepository $unitsRepo, protected \ContentParametersRepository $contentParametersRepo, protected \EmailTemplatesRepository $emailTemplatesRepo, protected \BranchesRepository $branchesRepo, protected \CacheService $cacheService,)
	{

	}


	/**
	 * @return void
	 */
	protected function loadVariables(): void
	{
		if (is_null($this->settings)) {
			$this->settings = (object)$this->repository->findAll()->fetchPairs('id', 'value');
		}

		if (is_null($this->emailTemplatesPairs)) {
			$this->emailTemplatesPairs = $this->emailTemplatesRepo->findAll()->order('name')->fetchPairs('id', 'name');
		}
	}


	/**
	 * @param string $action
	 * @return void
	 */
	public function render(string $action)
	{
		$this->template->settings = $this->settings;
		$this->template->action = $action;
		$this->template->aps = $this->aps;
		$this->template->setFile(__DIR__.'/'.Strings::firstLower(str_replace('_', '', ucwords($action, '_'))).'.latte');
		$this->template->render();
	}


	/**
	 * @return Form
	 */
	protected function createComponentGeneralForm(): Form
	{
		$form = $this->createBlankForm();

		$form->addText('website_name', 'admin.terms.webName', 50, 255)->setRequired(TRUE);

		$form->addUpload('logo', 'admin.terms.logo');
		$form->addUpload('logo_footer', 'admin.terms.logo_footer');

		$form->addUpload('favicon_zip', 'admin.terms.zipFavicon');
		$form->addTextArea('favicon', 'admin.terms.codeFavicon');

		$this->setFormPermissions($form, ['Settings_general']);
		$this->setFormDefaults($form, $this->settings);

		return $form;
	}


	/**
	 * @return Form
	 */
	protected function createComponentSeoForm(): Form
	{
		$form = $this->createBlankForm();

		//meta
		$form->addText('meta_title', 'admin.terms.metaTitle');
		$form->addTextArea('meta_desc', 'admin.terms.metaDesc');
		$form->addUpload('meta_image', 'admin.terms.image');

		//robots
		$form->addTextArea('robots_txt', 'admin.terms.robotsTxt');

		$this->setFormPermissions($form, ['Settings_seo']);
		$this->setFormDefaults($form, $this->settings);

		return $form;
	}


	/**
	 * @return Form
	 */
	protected function createComponentGoogleForm(): Form
	{
		$form = $this->createBlankForm();

		$form->addText('google_maps_api_key', 'admin.terms.googleMapsApiKey', 50, 255);

		$form->addText('google_recaptcha_site_key', 'admin.terms.googleRecaptchaSiteKey', 50, 255);
		$form->addText('google_recaptcha_secret_key', 'admin.terms.googleRecaptchaSecretKey', 50, 255);

		$form->addText('google_recaptcha_invisible_site_key', 'admin.terms.googleRecaptchaSiteKey', 50, 255);
		$form->addText('google_recaptcha_invisible_secret_key', 'admin.terms.googleRecaptchaSecretKey', 50, 255);

		$this->setFormPermissions($form, ['Settings_google']);
		$this->setFormDefaults($form, $this->settings);

		return $form;
	}


	/**
	 * @return Form
	 */
	protected function createComponentBarcodeForm(): Form
	{
		$form = $this->createBlankForm();

		$form->addText('barcode_prefixes', 'admin.terms.prefixes', 50, 255);
		$form->addText('barcode_plu_length', 'admin.terms.pluLength', 50, 255)
			->addCondition(Form::FILLED, TRUE)
			->addRule(Form::INTEGER);
		$form->addText('barcode_checksum_1_length', 'admin.terms.checkSum1Length', 50, 255)->addCondition(Form::FILLED, TRUE)
			->addRule(Form::INTEGER);
		$form->addText('barcode_weight_length', 'admin.terms.weightLength', 50, 255)->addCondition(Form::FILLED, TRUE)
			->addRule(Form::INTEGER);
		$form->addText('barcode_checksum_2_length', 'admin.terms.checkSum2Length', 50, 255)->addCondition(Form::FILLED, TRUE)
			->addRule(Form::INTEGER);

		$this->setFormPermissions($form, ['Settings_google']);
		$this->setFormDefaults($form, $this->settings);

		return $form;
	}


	/**
	 * @return Form
	 */
	protected function createComponentEcomailForm(): Form
	{
		$form = $this->createBlankForm();

		$form->addText('ecomail_api_key', 'admin.terms.ecomailApiKey', 50, 255);
		$form->addText('ecomail_list_id', 'admin.terms.ecomailListId', 50, 255);

		$this->setFormPermissions($form, ['Settings_ecomail']);
		$this->setFormDefaults($form, $this->settings);

		return $form;
	}


	/**
	 * @return Form
	 */
	protected function createComponentCustomCodesForm(): Form
	{
		$form = $this->createBlankForm();

		$form->addTextArea('custom_code_header', 'admin.terms.custom_code_header', NULL, 20);
		$form->addTextArea('custom_code_footer', 'admin.terms.custom_code_footer', NULL, 20);

		$this->setFormPermissions($form, ['Settings_custom_codes']);
		$this->setFormDefaults($form, $this->settings);

		return $form;
	}


	/**
	 * @return Form
	 */
	protected function createComponentWatermarkForm(): Form
	{
		$form = $this->createBlankForm();

		$form->addSelect('watermark', 'admin.terms.show_watermark', $GLOBALS['global']['bool']);
		$form->addText('watermark_pos_x', 'admin.terms.pos_x_percent')
			->setHtmlType('number')
			->setHtmlAttribute('min', '1')
			->setHtmlAttribute('max', '100');
		$form->addText('watermark_pos_y', 'admin.terms.pos_y_percent')
			->setHtmlType('number')
			->setHtmlAttribute('min', '1')
			->setHtmlAttribute('max', '100');
		$form->addText('watermark_ratio', 'admin.terms.ratio_percent')
			->setHtmlType('number')
			->setHtmlAttribute('min', '1')
			->setHtmlAttribute('max', '100');
		$form->addText('watermark_transparency', 'admin.terms.transparency_percent')
			->setHtmlType('number')
			->setHtmlAttribute('min', '0')
			->setHtmlAttribute('max', '100');
		$form->addUpload('watermark_image', 'admin.terms.image');

		$this->setFormPermissions($form, ['Settings_watermark']);
		$this->setFormDefaults($form, $this->settings);

		return $form;
	}


	protected function createComponentProductsForm(): Form
	{
		$branches = $this->branchesRepo->findAllActive()->fetchPairs("id", "name");
		$form = $this->createBlankForm();

		$form->addSelect('default_branch', 'admin.terms.defaultBranch', $branches)->setRequired(TRUE);

		$this->setFormPermissions($form, ['products']);
		$this->setFormDefaults($form, $this->settings);

		return $form;
	}


	/**
	 * @param Form $form
	 * @throws \Nette\Application\AbortException
	 */
	public function settingsFormSubmitted(Form $form)
	{
		$values = $form->getHttpData(TRUE);

		$removeThumbnails = FALSE;

		foreach ($values as $id => $value) {
			if (Strings::startsWith($id, '_')) {
				continue;
			}
			if (!in_array($id, ['logo_size']) && (Strings::startsWith($id, 'logo') || Strings::startsWith($id, 'file') || Strings::startsWith($id, 'image') || $value instanceof FileUpload)) {
				if (!$value || !$value->isOk()) {
					continue;
				}

				if ($id == 'favicon_zip') {
					$this->processFaviconFileUpload($value);
					continue;
				}
				else {
					$extension = Strings::lower(pathinfo($value->getName(), PATHINFO_EXTENSION));

					if (!in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'svg'])) {
						continue;
					}

					$filename = Strings::substring(md5(microtime().Random::generate(12)), 0, 16).'.'.$extension;

					$value->move(FS_DIR.'/images/'.\ImagesService::getImageDir($filename).'/'.$filename);

					$value = $filename;
				}
			}

			if ($this->repository->find($id)) {
				$rowsUpdated = $this->repository->findAll()->where('id', $id)->update(['value' => $value]);

				if ($rowsUpdated > 0) {
					if (Strings::startsWith($id, 'watermark')) {
						$removeThumbnails = TRUE;
					}
				}
			}
			else {
				$this->repository->insert([
					'id' => $id,
					'value' => $value
				]);

				if (Strings::startsWith($id, 'watermark')) {
					$removeThumbnails = TRUE;
				}
			}
		}

		// IMAGE FAVICON
		if (isset($values['favicon']) && isset($values['favicon_zip']) && !$values['favicon_zip']->error && pathinfo($values['favicon_zip']->getUntrustedName(), PATHINFO_EXTENSION) != "zip") {
			$favicons = explode("\n", $values['favicon']);
			$newFavicon = [];

			foreach ($favicons as $favicon) {
				if (strpos($favicon, "data-type=\"custom-icon-image\"") === FALSE) {
					$newFavicon[] = $favicon;
				}
			}

			$newFavicon[] = '<link rel="icon" type="image/png" data-type="custom-icon-image" href="/'.$values['favicon_zip']->name.'">';
			$this->repository->findAll()->where('id', 'favicon')->update(['value' => implode("\n", $newFavicon)]);
		}

		// THUMBNAILS REMOVE
		if ($removeThumbnails) {
			\ImagesService::removeThumbnails();
		}

		/** @var BasePresenter $presenter */
		$presenter = $this->getPresenter();

		$this->cacheService->deleteByTags($this->repository->cleanCacheTags);

		$presenter->flashMessage($this->trans('admin.messages.successSave'), 'success');
		$presenter->redirect('this');
	}


	/**
	 * @param FileUpload $value
	 */
	public function processFaviconFileUpload(FileUpload $value)
	{
		/** @var SplFileInfo $file */
		foreach (Finder::findFiles('*.*')->from(WWW_DIR.'/files/favicon') as $file) {
			unlink($file->getPath().'/'.$file->getFilename());
		}
		if (!file_exists(WWW_DIR.'/files/favicon')) {
			mkdir(WWW_DIR.'/files/favicon');
		}
		if (!file_exists(WWW_DIR.'/files/favicon/temp')) {
			mkdir(WWW_DIR.'/files/favicon/temp');
		}
		$extension = pathinfo($value->getUntrustedName(), PATHINFO_EXTENSION);
		$fileName = Strings::webalize(pathinfo($value->getUntrustedName(), PATHINFO_FILENAME));
		if ($extension == 'zip') {
			$value->move(WWW_DIR.'/files/favicon/temp/'.$fileName.'.'.$extension);
			$zip = new \ZipArchive();
			$zip->open(WWW_DIR.'/files/favicon/temp/'.$fileName.'.'.$extension);
			$zip->extractTo(WWW_DIR.'/files/favicon');
		}
		elseif (in_array($extension, ['ico', 'png', 'jpg', 'jpeg'])) {
			$value->move(WWW_DIR.'/files/favicon/'.$fileName.'.'.$extension);
		}
		else {
			/** @var BasePresenter $presenter */
			$presenter = $this->getPresenter();

			$presenter->flashMessage('admin.messages.zipFaviconError', 'danger');
		}
	}


	/**
	 * @param \Nette\Forms\Form $form
	 * @param mixed $defaults
	 * @return void
	 */
	public function setFormDefaults(\Nette\Forms\Form $form, mixed $defaults)
	{
		foreach ($defaults as $key => $val) {
			//try catch for deleted item ids
			try {
				$form->setDefaults([$key => $val]);
			}
			catch (\Exception $e) {
			}
		}
	}


	/**
	 * @return Form
	 */
	protected function createBlankForm(): Form
	{
		$this->loadVariables();

		$form = new Form();
		$form->setTranslator($this->translator);

		$form->addSubmit('submit', 'save');
		$form->onSuccess[] = [$this, 'settingsFormSubmitted'];
		$form->onError[] = [$this, 'settingsFormSubmitted']; //some templates do not have all inputs rendered

		return $form;
	}


	/**
	 * @param Form $form
	 * @param array $permissions
	 * @return void
	 */
	protected function setFormPermissions(Form $form, array $permissions): void
	{
		if (!$this->aps->hasAny($permissions)) {
			foreach ($form->getComponents() as $component) {
				$component->setDisabled();
			}
		}
	}


	/**
	 * @param string $param
	 * @throws \Nette\Application\AbortException
	 */
	public function handleDeleteValue(string $param)
	{
		$this->repository->findAll()->where('id', $param)->update(['value' => '']);

		/** @var BasePresenter $presenter */
		$presenter = $this->getPresenter();

		$presenter->flashMessage($this->trans('admin.messages.successDelete'), 'success');
		$presenter->redirect('this');
	}


	/**
	 * @throws \Nette\Application\AbortException
	 */
	public function handleRemoveFavicon()
	{
		/** @var SplFileInfo $file */
		foreach (Finder::findFiles('*.*')->from(WWW_DIR.'/files/favicon') as $file) {
			unlink($file->getPath().'/'.$file->getFilename());
		}
		\Helpers::recursiveDelete(WWW_DIR.'/files/favicon', FALSE);

		/** @var BasePresenter $presenter */
		$presenter = $this->getPresenter();

		$presenter->flashMessage($this->trans('admin.messages.successDelete'), 'success');
		$presenter->redirect('this');
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


	/**
	 * @param string $moduleName
	 * @return bool
	 */
	public function isModuleCurrent(string $moduleName): bool
	{
		return $this->getPresenter()?->getName() == 'Admin:'.$moduleName;
	}


	/**
	 * @param string $thisPartsName
	 * @param array $showOnlyParts
	 * @return bool
	 */
	public function isThisPartVisible(string $thisPartsName, array $showOnlyParts): bool
	{
		return in_array($thisPartsName, $showOnlyParts) || empty($showOnlyParts);
	}
}
