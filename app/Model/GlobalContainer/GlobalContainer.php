<?php

use Nette\Utils\Html;

class GlobalContainer
{
	use \Nette\SmartObject;

    /** @var PriceGroupsEntity */
    protected $priceGroup;

    /** @var PriceGroupsEntity */
    protected $defaultPriceGroup;

    /** @var CurrenciesEntity */
    protected $currency;

    /** @var CurrenciesEntity */
    protected $defaultCurrency;

	/** @var LanguagesEntity */
	protected $defaultLanguage;

	/** @var LanguagesEntity */
	protected $language;


	/** @var array|null */
	protected $filesDataBuffer;

	/** @var array */
	protected $parameters;


    /**
     * @param \Nette\DI\Container $container
     * @param PriceGroupsRepository $priceGroupsRepo
     * @param CurrenciesRepository $currenciesRepo
     * @param SettingsService $settingsService
     * @param UnitsRepository $unitsRepo
     * @param FilesDataRepository $filesDataRepository
     * @param LanguagesRepository $languagesRepository
     */
	public function __construct(
		protected \Nette\DI\Container $container,
        protected PriceGroupsRepository $priceGroupsRepo,
        protected CurrenciesRepository $currenciesRepo,
		protected SettingsService $settingsService,
        protected UnitsRepository $unitsRepo,
		protected FilesDataRepository $filesDataRepository,
		protected LanguagesRepository $languagesRepository,
	)
	{
		$this->parameters = $container->getParameters();
	}


	/**
	 * @param string|null $key
	 * @return mixed
	 */
	public function getParameters(string $key = NULL): mixed
	{
		if ($key === NULL) {
			return $this->parameters;
		}

		// Used to fall back to returning the *entire* parameters array when
		// $key wasn't set (instead of NULL) — harmless for callers that
		// always expect the key to exist, but silently broke isDevMode() on
		// any config that never mentions `devMode` (i.e. everything except
		// config/02-local.neon): (bool) of a non-empty array is always TRUE,
		// so dev-only behaviour (admin IP whitelist bypass, mail redirected
		// to devModeEmail, the CZ->VI translate widget) was actually active
		// in production too.
		return $this->parameters[$key] ?? NULL;
	}


	/**
	 * @param string $moduleName
	 * @return bool
	 */
	public function isModuleAvailable(string $moduleName): bool
	{
		$availableModules = $this->getParameters('availableModules');
		return in_array($moduleName, $availableModules) || isset($availableModules[$moduleName]);
	}


	/**
	 * @return bool
	 */
	public function isMultilang(): bool
	{
		return (bool)$this->getParameters('isMultilang');
	}


	/**
	 * Single switch for local-dev-only safety overrides (admin IP whitelist, outgoing mail redirect...).
	 * Set via `devMode` parameter, only present in config/02-local.neon, so it never applies to a real domain config.
	 * @return bool
	 */
	public function isDevMode(): bool
	{
		return (bool)$this->getParameters('devMode');
	}


	/**
	 * @return string|null
	 */
	public function getDevModeEmail(): ?string
	{
		return $this->getParameters('devModeEmail');
	}


	/**
	 * @return \Nette\Http\UrlScript
	 */
	public function getUrlScript(): \Nette\Http\UrlScript
	{
		//$GLOBALS['uri'] from routerFactory
		return $GLOBALS['uri'];
	}


	/**
	 * @return LanguagesEntity|null
	 */
	public function getDefaultLanguage(): ?LanguagesEntity
	{
		if(is_null($this->defaultLanguage)){
			$this->defaultLanguage = $this->languagesRepository->findDefault();
		}
		return $this->defaultLanguage;
	}

	/**
	 * @return LanguagesEntity
	 */
	public function getLanguage(): LanguagesEntity
	{
		return $this->language;
	}


	/**
	 * @param LanguagesEntity $language
	 * @return $this
	 */
	public function setLanguage(LanguagesEntity $language): GlobalContainer
	{
		$this->language = $language;

		return $this;
	}

    /**
     * @return PriceGroupsEntity|null
     */
    public function getPriceGroup(): ?PriceGroupsEntity
    {
        return $this->priceGroup ?: $this->getDefaultPriceGroup();
    }

    /**
     * @return PriceGroupsEntity|null
     */
    public function getDefaultPriceGroup(): ?PriceGroupsEntity
    {
        if (!$this->defaultPriceGroup) {
            $this->defaultPriceGroup = $this->priceGroupsRepo->findDefault();
        }

        return $this->defaultPriceGroup;
    }


    /**
     * @param PriceGroupsEntity|null $priceGroup
     * @return $this
     */
    public function setPriceGroup(?PriceGroupsEntity $priceGroup): GlobalContainer
    {
        $this->priceGroup = $priceGroup;

        return $this;
    }


	/**
	 * @return void
	 */
	public function loadFilesDataBuffer()
	{
		if (!isset($GLOBALS['lang'])) {
			$GLOBALS['lang'] = $this->getDefaultLanguage()->id;
		}
		if (is_null($this->filesDataBuffer)) {
			$this->filesDataBuffer = [];
			foreach ($this->filesDataRepository->findAll() as $filesDataRow) {
				$this->filesDataBuffer[$filesDataRow->types_id][$filesDataRow->file] = $filesDataRow->toArray();
			}
		}
	}

	/**
	 * @param string $filename
	 * @return stdClass|null
	 */
	public function getImageData(string $filename): ?stdClass
	{
		$this->loadFilesDataBuffer();

		return isset($this->filesDataBuffer[FilesDataEntity::TYPE_IMAGE][$filename]) ? (object)$this->filesDataBuffer[FilesDataEntity::TYPE_IMAGE][$filename] : NULL;
	}

	/**
	 * @param string $filename
	 * @return stdClass|null
	 */
	public function getFileData(string $filename): ?stdClass
	{
		$this->loadFilesDataBuffer();

		return isset($this->filesDataBuffer[FilesDataEntity::TYPE_FILE][$filename]) ? (object)$this->filesDataBuffer[FilesDataEntity::TYPE_FILE][$filename] : NULL;
	}

	/**
	 * @return string
	 */
	public function getWebsiteName(): string
	{
		return (string)($this->settingsService->getValue('website_name'));
	}

	/**
	 * @return object
	 * @throws Throwable
	 */
	public function getWatermarkInfo(): object
	{
		return (object)[
			'is_active' => $this->settingsService->getValue('watermark') && $this->settingsService->getValue('watermark_image'),
			'image' => $this->settingsService->getValue('watermark_image'),
			'pos_x' => intval($this->settingsService->getValue('watermark_pos_x') ?: '50'),
			'pos_y' => intval($this->settingsService->getValue('watermark_pos_y') ?: '50'),
			'transparency' => 100 - intval($this->settingsService->getValue('watermark_transparency')),
			'ratio' => intval($this->settingsService->getValue('watermark_ratio') ?: '30'),
			'is_png' => $this->settingsService->getValue('watermark_image') && \Nette\Utils\Strings::endsWith(\Nette\Utils\Strings::lower($this->settingsService->getValue('watermark_image')), 'png')
		];
	}


    /**
     * @return CurrenciesEntity|null
     */
    public function getCurrency(): ?CurrenciesEntity
    {
        return $this->currency ?: $this->getDefaultCurrency();
    }


    /**
     * @return CurrenciesEntity|null
     */
    public function getDefaultCurrency(): ?CurrenciesEntity
    {
        if (!$this->defaultCurrency) {
            $this->defaultCurrency = $this->currenciesRepo->findDefault();
        }

        return $this->defaultCurrency;
    }


    /**
     * @param CurrenciesEntity|null $currency
     * @return $this
     */
    public function setCurrency(?CurrenciesEntity $currency): GlobalContainer
    {
        $this->currency = $currency;

        return $this;
    }

}
