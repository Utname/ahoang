<?php

namespace App\FrontModule\Components;

use Nette\ComponentModel\IComponent;

class BaseControl extends \Nette\Application\UI\Control
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