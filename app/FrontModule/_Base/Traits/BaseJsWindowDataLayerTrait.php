<?php

namespace App\FrontModule\Traits;

use App\FrontModule\Components\BreadcrumbsControl;
use App\FrontModule\Components\JsWindowDataLayerControl;

trait BaseJsWindowDataLayerTrait
{
	/** @var JsWindowDataLayerControl */
	protected $controlJsWindowDataLayer;


	/**
	 *
	 */
	protected function processJsWindowDataLayer(): void
	{
		$this->controlJsWindowDataLayer = $this->getComponent('jsWindowDataLayer');
	}


	/**
	 * @return JsWindowDataLayerControl
	 */
	protected function createComponentJsWindowDataLayer(): JsWindowDataLayerControl
	{
		return new JsWindowDataLayerControl($this->globalContainer, $this->sessionSection);
	}
}
