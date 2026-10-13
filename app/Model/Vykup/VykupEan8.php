<?php

declare(strict_types=1);

/**
 * EAN-8 for the Výkup lahví vouchers: check digit + SVG bars.
 * 0000081 → 00000819 (Vrácená láhev), 0000080 → 00000802 (Vrácená přepravka).
 * The same codes are set up in GoKasa, so a scan books the item; the count is typed
 * by the cashier ("24 ×").
 */
final class VykupEan8
{
	private const L = ['0001101', '0011001', '0010011', '0111101', '0100011', '0110001', '0101111', '0111011', '0110111', '0001011'];
	private const R = ['1110010', '1100110', '1101100', '1000010', '1011100', '1001110', '1010000', '1000100', '1001000', '1110100'];


	public static function checkDigit(string $digits7): int
	{
		if (!preg_match('/^\d{7}$/', $digits7)) {
			throw new \InvalidArgumentException('EAN-8 needs 7 digits');
		}
		$sum = 0;
		for ($i = 0; $i < 7; $i++) {
			$sum += (int)$digits7[$i] * ($i % 2 ? 1 : 3);
		}

		return (10 - $sum % 10) % 10;
	}


	public static function isValid(string $ean): bool
	{
		return (bool)preg_match('/^\d{8}$/', $ean) && self::checkDigit(substr($ean, 0, 7)) === (int)$ean[7];
	}


	/**
	 * SVG bars: 1 module = 0.375mm (3 dots on a 203 dpi thermal printer), 10 quiet
	 * modules each side, 16mm high.
	 */
	public static function svg(string $ean, float $moduleMm = 0.375, float $heightMm = 16): string
	{
		if (!self::isValid($ean)) {
			throw new \InvalidArgumentException("Invalid EAN-8: $ean");
		}
		$bits = '101';
		for ($i = 0; $i < 4; $i++) {
			$bits .= self::L[(int)$ean[$i]];
		}
		$bits .= '01010';
		for ($i = 4; $i < 8; $i++) {
			$bits .= self::R[(int)$ean[$i]];
		}
		$bits .= '101';

		$width = strlen($bits) + 20;
		$rects = '';
		for ($i = 0, $n = strlen($bits); $i < $n; ) {
			$j = $i;
			while ($j < $n && $bits[$j] === $bits[$i]) {
				$j++;
			}
			if ($bits[$i] === '1') {
				$rects .= sprintf('<rect x="%d" y="0" width="%d" height="40"/>', 10 + $i, $j - $i);
			}
			$i = $j;
		}

		return sprintf(
			'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %d 40" preserveAspectRatio="none" shape-rendering="crispEdges" style="width:%.3fmm;height:%.1fmm;display:block">%s</svg>',
			$width, $width * $moduleMm, $heightMm, $rects,
		);
	}
}
