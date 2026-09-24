<?php


class NewLineFilter
{
	/**
	 * @param string|null $text
	 * @return string
	 */
	public function process(?string $text): string
	{
		if (!$text) {
			return '';
		}

		$text = str_replace('||', '<br>', $text);
		$text = str_replace('  ', '<br>', $text);

		return $text;
	}
}