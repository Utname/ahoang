<?php

namespace App\AdminModule\Presenters;

use Nette\Application\UI\Form;
use Nette\Http\FileUpload;
use Nette\Utils\Finder;
use Nette\Utils\Random;
use Nette\Utils\Strings;

class SettingsPresenter extends BaseModulePresenter
{
	/** @var \SettingsRepository @inject */
	public $repository;

	/** @var array */
	protected $moduleActions = [
		'list',
		'general',
		'barcode',
		'google',
		'custom_codes',
        'products'
	];

	/** @var SettingsControl @inject */
	public $settingsControl;


	public function createComponentSettings()
	{
		return $this->settingsControl;
	}

}
