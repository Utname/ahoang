<?php

namespace App\FrontModule\Presenters;

use App\FrontModule\Traits\BaseBreadcrumbsTrait;
use App\FrontModule\Traits\BaseContentTrait;
use App\FrontModule\Traits\BaseIndexBannersTrait;
use App\FrontModule\Traits\BaseJsWindowDataLayerTrait;
use App\FrontModule\Traits\BaseLinksTrait;
use App\FrontModule\Traits\BaseLocalizationTrait;
use App\FrontModule\Traits\BaseMetaInfoTrait;
use App\FrontModule\Traits\BaseNewsletterTrait;
use Nette\Application\BadRequestException;
use Nette\Application\UI\Presenter;
use Nette\Database\Explorer;
use Nette\DI\Container;
use Nette\Http\SessionSection;

class BasePresenter extends Presenter
{
	use BaseLocalizationTrait;
	use BaseMetaInfoTrait;
	use BaseContentTrait;
	use BaseBreadcrumbsTrait;
	use BaseJsWindowDataLayerTrait;
	use BaseNewsletterTrait;
	use BaseLinksTrait;
	use BaseIndexBannersTrait;

	/** @var string */
	public $slug;

	/** @var Explorer @inject */
	public $database;

	/** @var SessionSection */
	protected $sessionSection;

	/** @var \PagesEntity */
	public $page;

	/** @var Container @inject */
	public $container;

	/** @var \GlobalContainer @inject */
	public $globalContainer;

	/** @var \CacheService @inject */
	public $cacheService;

	/** @var array */
	protected $parameters;


	/**
	 *
	 */
	protected function startup()
	{
		parent::startup();

		$GLOBALS['container'] = $this->globalContainer;

		$this->sessionSection = $this->session->getSection('frontend');

		if (!$this->page instanceof \PagesEntity) {
			if (!$this->page = $this->getParameter('page')) {
				throw new BadRequestException('Page not found.');
			}
		}

		$GLOBALS['parameters'] = $this->parameters = $this->template->parameters = $this->container->getParameters();


		$this->processLanguagesLocalization();
		$this->processJsWindowDataLayer();
		$this->processOtherLocalization();
		$this->processContents();
		$this->processMetaTags();
		$this->processBreadcrumbs();
		$this->processNewsletter();
	}


	/**
	 *
	 */
	protected function beforeRender()
	{
		parent::beforeRender();

		$this->template->page = $this->page;
		$this->template->globalContainer = $this->globalContainer;
		$this->template->language = $this->language;
		$this->template->menuLanguages = $this->menuLanguages;

		$this->template->isErrorPage = FALSE;
	}


	/**
	 * @return array|string[]
	 */
	public function formatLayoutTemplateFiles(): array
	{
		$layout = $this->layout ?: 'layout';

		$dir = dirname(static::getReflection()->getFileName());
		$dir = is_dir("$dir/templates") ? $dir : dirname($dir);

		$list = [
			"$dir/../@$layout.latte"
		];

		return $list;
	}


	/**
	 * Formats view template file names.
	 */
	public function formatTemplateFiles(): array
	{
		$dir = dirname(static::getReflection()->getFileName());
		$dir = is_dir("$dir/templates") ? $dir : dirname($dir);

		return [
			"$dir/templates/$this->view.latte"
		];
	}


	/**
	 * @param string|null $msg
	 * @param string $type
	 * @param string|null $referer
	 * @throws \Nette\Application\AbortException
	 */
	public function redirectUrlWithFlashMessage(?string $msg, string $type, ?string $referer = NULL): void
	{
		$this->flashMessage($msg ?: '', $type);

		if (!$referer) {
			$referer = $this->globalContainer->getUrlScript()->getPath();
		}

		$referer .= (parse_url($referer, PHP_URL_QUERY) ? '&' : '?').'_fid=';
		$referer .= $this->getParameter(self::FLASH_KEY);

		$this->redirectUrl($referer);
	}


	/**
	 * @param string|null $msg
	 * @param string $type
	 * @param array|null $controls
	 */
	public function redrawControlsWithFlashMessage(?string $msg, string $type, array $controls = NULL): void
	{
		if (!$this->isAjax()) {
			$this->redirectUrlWithFlashMessage($msg, $type);
		}

		$this->flashMessage($msg ?: '', $type);

		if (!$controls) {
			$this->redrawControl();
		}
		else {
			foreach ($controls as $control) {
				$this->redrawControl($control);
			}

			$this->redrawControl('flashMessages');
		}
	}

	/**
	 * @return bool
	 */
	public function isRobot(): bool
	{
		return isset($_SERVER['HTTP_USER_AGENT']) && (
				str_contains($_SERVER['HTTP_USER_AGENT'], 'facebookexternalhit/') ||
				str_contains($_SERVER['HTTP_USER_AGENT'], 'Twitterbot') ||
				str_contains($_SERVER['HTTP_USER_AGENT'], 'Google') ||
				str_contains($_SERVER['HTTP_USER_AGENT'], 'Chrome-Lighthouse') ||
				str_contains($_SERVER['HTTP_USER_AGENT'], 'Google Page Speed Insights') ||
				preg_match('/bot|crawl|slurp|spider/i', $_SERVER['HTTP_USER_AGENT'])
			);
	}
}
