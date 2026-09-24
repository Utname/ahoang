<?php

namespace App\AdminModule\Presenters;

use Nette\Application\UI\Control;
use Nette\ComponentModel\IComponent;

class BaseControl extends Control
{
	/**
	 * @param IComponent $presenter
	 */
	protected function attached(IComponent $presenter): void
	{
		parent::attached($presenter);
	}


	/**
	 *
	 */
	public function render($params = [])
	{
		$reflection = new \ReflectionClass($this);

		$componentName = $reflection->getShortName();

		$this->template->setFile(__DIR__.'/'.str_replace('Control', '', $componentName).'/'.$componentName.'.latte');
		$this->template->render();
	}
}