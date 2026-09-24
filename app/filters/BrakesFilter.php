<?php

use Latte\Runtime\Html;

class BrakesFilter
{
	/**
	 * @param string|int $decimals
	 * @return string
	 */
	public function process(string $string): string
	{
		return new Html(str_replace('  ', '<br />', htmlspecialchars((string) $string, ENT_NOQUOTES | ENT_SUBSTITUTE, 'UTF-8'), \Latte\Runtime\Filters::$xhtml));
	}
}