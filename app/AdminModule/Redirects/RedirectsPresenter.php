<?php

namespace App\AdminModule\Presenters;

use Nette\Application\UI\Form;

class RedirectsPresenter extends BaseModulePresenter
{
	/** @var \RedirectsRepository @inject */
	public $repository;

	/** @var \RedirectsService @inject */
	public $service;

	/** @var array */
	protected $moduleBulkActions = [
		BulkActionsControl::ACTION_DELETE,
	];

	/** @var string */
	protected $orderBy = 'input_url';


	/**
	 *
	 */
	public function actionDefault()
	{
		$this['filters-form']->addText('input_url', '', 50, 255);
		$this['filters-form']->addText('output_url', '', 50, 255);

		parent::actionDefault();
	}


	/**
	 * @param Form $form
	 * @return Form
	 */
	protected function setFormFields(Form $form): Form
	{
		$form->addText('input_url', 'admin.terms.input_url')->setRequired(TRUE);
		$form->addText('output_url', 'admin.terms.destination_url');

		return parent::setFormFields($form);
	}


	public function handleCheckUrls()
	{
		$url = 'https://docs.google.com/feeds/download/spreadsheets/Export?key=1mjPB4KdBYVrtqBZQKQM3db_Y37nKJDyoH6lRJjCbfTg&exportFormat=tsv&gid=331868429';

		$data = file_get_contents($url);
		$rows = explode("\n", $data);

		foreach ($rows as $c => $row) {
			$values = str_getcsv($row, "\t");
			$url = $values[0];
			//echo $url.'<br />';
			echo $this->getResponseCode($url).' - '.$url.'<br />';
		}

		exit;
	}


	protected function getResponseCode($url)
	{
		$ch = curl_init($url);

		curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);
		curl_setopt($ch,CURLOPT_TIMEOUT,10);

		$output = curl_exec($ch);

		$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

		curl_close($ch);

		return $httpCode;
	}
}