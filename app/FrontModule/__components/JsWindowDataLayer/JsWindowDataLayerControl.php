<?php

namespace App\FrontModule\Components;

use Nette\Http\SessionSection;

class JsWindowDataLayerControl extends BaseControl
{
	/** @var array  */
	protected $jsWindowDataLayerPushes = [];


	/**
	 * @param \GlobalContainer $globalContainer
	 * @param SessionSection $session
	 */
	public function __construct(protected \GlobalContainer $globalContainer, protected SessionSection $session)
	{
		if ($this->session->jsWindowDataLayerPushes ?? NULL) {
			$this->jsWindowDataLayerPushes = $this->session->jsWindowDataLayerPushes;
			$this->session->jsWindowDataLayerPushes = [];
		}
	}




	/**
	 * @param array $data
	 * @param bool $addToSession
	 * @return $this
	 */
	protected function push(array $data, bool $addToSession = FALSE): self
	{
		if ($addToSession) {
			$this->session->jsWindowDataLayerPushes[] = $data;
		}
		else {
			$this->jsWindowDataLayerPushes[] = $data;
		}

		return $this;
	}


	/**
	 * @return $this
	 */
	public function redraw(): self
	{
		$this->redrawControl('pushes');
		return $this;
	}


	/**
	 * @return array
	 */
	public function getJsWindowDataLayerPushes(): array
	{
		return $this->jsWindowDataLayerPushes;
	}

}
