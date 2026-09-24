<?php


class LazyLoadingFilter
{
	/**
	 * @param float $number
	 * @param int $decimals
	 * @return string
	 */
	public function process(?string $string): string
	{
		if (!$string) {
			return '';
		}

		$string = preg_replace('~<img[^>]*\K(?=src)~i', 'data-', $string);
		$string = str_replace('<img ', '<img class="lazyload"', $string);

		return $string;
	}
}