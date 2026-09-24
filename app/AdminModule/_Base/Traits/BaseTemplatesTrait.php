<?php

namespace App\AdminModule\Traits;

use Nette\Application\LinkGenerator;
use Nette\Utils\Html;
use Nette\Utils\Strings;

trait BaseTemplatesTrait
{
	/** @var LinkGenerator @inject */
	public $linkGenerator;

	/**
	 * @return array|string[]
	 */
	public function formatLayoutTemplateFiles(): array
	{
		$layout = $this->layout ?: 'layout';

		return [
			APP_DIR."/AdminModule/@$layout.latte"
		];
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
	 * @return void
	 */
	public function beforeRender()
	{
		parent::beforeRender();
		$this->template->addFunction('renderMenu', function() {
			$menuGenerator = new \MenuGenerator($this->linkGenerator);
			$menuGenerator->setNameSpace('Admin'); //for link generator
			$menuGenerator->setMenuStructure($this->container->getParameters()['menuStructure']);
			$menuGenerator->setIconClassPrefix('menu-icon');
			$menuGenerator->setTranslator($this->translator);
			$menuGenerator->setAdminPermissionsService($this->aps);
			return $menuGenerator->generate()->toHtml();
		});
	}

}
