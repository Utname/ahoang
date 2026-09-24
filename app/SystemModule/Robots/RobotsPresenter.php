<?php

namespace App\SystemModule\Presenters;

use App\SystemModule\Url;
use Nette\Application\UI\Presenter;
use Nette\Database\Table\ActiveRow;

class RobotsPresenter extends BasePresenter
{

	/**
	 * @return void
	 * @throws \Exception
	 */
	public function renderDefault()
	{
		$this->getHttpResponse()->setContentType('text/txt');
	}
}
