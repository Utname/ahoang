<?php


class FloatFilter
{
	/**
	 * @param float $number
	 * @param int $decimals
	 * @return string
	 */
	public function process(?float $number, int $decimals = 0): string
	{
		return number_format(floatval($number), $decimals, ',', ' ');
	}
}