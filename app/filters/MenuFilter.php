<?php


class MenuFilter
{
	/**
	 * @param string $moduleName
	 * @return string
	 */
	public function process(string $moduleName): string
	{
		$pars = explode('/', $moduleName);

		return trim(end($pars));
	}
}