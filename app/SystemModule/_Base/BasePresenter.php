<?php

namespace App\SystemModule\Presenters;

use App\AdminModule\Traits\BaseLocalizationTrait;
use App\AdminModule\Traits\BaseSettingsTrait;
use App\FrontModule\Components\MetaInfoControl;
use App\FrontModule\Traits\BaseMetaInfoTrait;
use Nette\Application\UI\Presenter;
use Nette\Database\Explorer;
use Nette\DI\Container;
use Nette\Http\SessionSection;

class BasePresenter extends Presenter
{
	use BaseSettingsTrait;
	use BaseLocalizationTrait;

	/** @var string */
	public $slug;

	/** @var Explorer @inject */
	public $database;

	/** @var \GlobalContainer @inject */
	public $globalContainer;

	/** @var SessionSection */
	public $session;

	/** @var Container @inject */
	public $container;

	/**
	 *
	 */
	protected function startup()
	{
		parent::startup();

		// session
		$this->session = $this->getSession('adminModule'); //adminModule for order print, dropzone, ...

		// parameters
		$GLOBALS['parameters'] = $this->template->parameters = $this->container->getParameters();

		// global container
		$GLOBALS['container'] = $this->template->globalContainer = $this->globalContainer;

		// localization
		$this->processLocalization();

		// settings
		$this->processSettings();

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

}
