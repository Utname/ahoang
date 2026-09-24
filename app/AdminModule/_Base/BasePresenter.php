<?php

namespace App\AdminModule\Presenters;

use App\AdminModule\Traits\BaseAuthenticationTrait;
use App\AdminModule\Traits\BaseCacheTrait;
use App\AdminModule\Traits\BaseLocalizationTrait;
use App\AdminModule\Traits\BaseSettingsTrait;
use App\AdminModule\Traits\BaseTemplatesTrait;
use Nette\Application\UI\Presenter;
use Nette\Database\Explorer;
use Nette\DI\Container;
use Nette\Http\SessionSection;

class BasePresenter extends Presenter
{
	use BaseTemplatesTrait;
	use BaseAuthenticationTrait;
	use BaseLocalizationTrait;
	use BaseSettingsTrait;
	use BaseCacheTrait;

	/** @var Explorer @inject */
	public $database;

	/** @var \GlobalContainer @inject */
	public $globalContainer;

	/** @var SessionSection */
	public $session;


	/** @var Container @inject */
	public $container;

    /** @var \CurrenciesRepository @inject */
    public $currenciesRepo;

    /** @var \CurrenciesEntity */
    protected $defaultCurrency;


	/**
	 *
	 */
	protected function startup()
	{
		parent::startup();

		// session
		$this->session = $this->getSession('adminModule');

		// parameters
		$GLOBALS['parameters'] = $this->template->parameters = $this->container->getParameters();

		// global container
		$GLOBALS['container'] = $this->template->globalContainer = $this->globalContainer;

		// check authentication or redirect to login
		$this->processAdminUserDetection();

		// localization
		$this->processLocalization();

		// settings
		$this->processSettings();

        $GLOBALS['defaultCurrency'] = $this->template->defaultCurrency = $this->defaultCurrency = $this->currenciesRepo->findDefault();

		$this->startupBaseCacheTrait();
	}
}
