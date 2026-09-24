<?php

namespace App\AdminModule\Presenters;

use Nette\Application\UI\Form;
use Nette\Utils\Strings;

class CountriesPresenter extends BaseModulePresenter
{
	/** @var \CountriesRepository @inject */
	public $repository;

	/** @var \CountriesService @inject */
	public $service;


	/**
	 *
	 */
	public function actionDefault()
	{
		$this['filters-form']->addText('name', 'admin.terms.name', 50, 255);
		$this['filters-form']->addText('iso_code_2', 'admin.terms.isoCode2', 50, 255);
		$this['filters-form']->addText('iso_code_3', 'admin.terms.isoCode3', 50, 255);

		parent::actionDefault();
	}


	/**
	 * @param Form $form
	 * @return Form
	 */
	protected function setFormFields(Form $form): Form
	{
		$form->addText('name', 'admin.terms.name', 50, 255)->setRequired(TRUE);
		$form->addText('iso_code_2', 'admin.terms.isoCode2', 50, 255);
		$form->addText('iso_code_3', 'admin.terms.isoCode3', 50, 255);
		$form->addSelect('is_active', $this->trans('admin.terms.isActive'), $this->global['bool'])->setTranslator(NULL);
        $form->addUpload('image', 'admin.terms.image');

		return parent::setFormFields($form);
	}

    public function actionImportCountries()
    {
        $countriesPairs = [];
        $countries = $this->repository->findAll();
        $countriesOldAll = $this->database->table("parameterValues")->where("parameter_id = ? AND import !=''", 45)->fetchAll();

        $imageService = new \ImagesService($this->filesDataService, $this->globalContainer);

        foreach($countries as $country){
            $countriesPairs[Strings::webalize($country->name)] = $country->id;
        }

        foreach($countriesOldAll as $country){
            $countryName = Strings::webalize($country['import']);

            if(!isset($countriesPairs[$countryName])) {
                $fileLang = NULL;

                if ($country['ext']) {
                    $url = "https://supsup.cz/{$country['ext']}";
                    $imgUrl = explode('/', $country['ext']);
                    if (!is_dir(FS_TEMP . "/flags/")) {
                        mkdir(FS_TEMP . "/flags/", 0777, true);
                    }

                    if ($file = file_get_contents($url)) {
                        file_put_contents(FS_TEMP . "/flags/" . end($imgUrl), $file);

                        $imageToSave = new \SplFileInfo(FS_TEMP . "/flags/" . end($imgUrl));

                        $file = $imageService::saveImage($imageToSave);
                        $fileLang = $file->file;
                    }
                }

                $this->repository->insert([
                    "name" => $country['import'],
                    "image" => $fileLang
                ]);
            }
        }

        exit;
    }

}