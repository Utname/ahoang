<?php

/**
 * EAN-13 barcode bar generation, ported from the design reference files
 * (design_handoff_fikko/*.dc.html, Component.modules()/renderVals()).
 */
class EanBarcodeHelper
{
	private const L = ['0001101', '0011001', '0010011', '0111101', '0100011', '0110001', '0101111', '0111011', '0110111', '0001011'];
	private const G = ['0100111', '0110011', '0011011', '0100001', '0011101', '0111001', '0000101', '0010001', '0001001', '0010111'];
	private const R = ['1110010', '1100110', '1101100', '1000010', '1011100', '1001110', '1010000', '1000100', '1001000', '1110100'];
	private const PARITY = ['LLLLLL', 'LLGLGG', 'LLGGLG', 'LLGGGL', 'LGLLGG', 'LGGLLG', 'LGGGLL', 'LGLGLG', 'LGLGGL', 'LGGLGL'];


	/**
	 * @param string $ean
	 * @return string
	 */
	public static function getDigits(string $ean): string
	{
		$digits = preg_replace('/\D/', '', $ean) ?? '';
		return str_pad(substr($digits, 0, 13), 13, '0');
	}


	/**
	 * @param string $ean
	 * @return string
	 */
	public static function getBits(string $ean): string
	{
		$d = array_map('intval', str_split(self::getDigits($ean)));
		$parity = self::PARITY[$d[0]];

		$s = '101';
		for ($i = 1; $i <= 6; $i++) {
			$s .= ($parity[$i - 1] === 'L' ? self::L : self::G)[$d[$i]];
		}
		$s .= '01010';
		for ($i = 7; $i <= 12; $i++) {
			$s .= self::R[$d[$i]];
		}

		return $s . '101';
	}


	/**
	 * @param string $ean
	 * @param float $unitMm width of one barcode module, in mm
	 * @return array<int, array{color: string, width: string}>
	 */
	public static function getBars(string $ean, float $unitMm = 0.38): array
	{
		$bits = self::getBits($ean);
		$len = strlen($bits);
		$bars = [];

		$i = 0;
		while ($i < $len) {
			$n = 1;
			while ($i + $n < $len && $bits[$i + $n] === $bits[$i]) {
				$n++;
			}
			$bars[] = [
				'color' => $bits[$i] === '1' ? '#111' : '#fff',
				'width' => round($n * $unitMm, 2) . 'mm',
			];
			$i += $n;
		}

		return $bars;
	}


	/**
	 * Formats or generates a 13-digit scale barcode EAN-13 from PLU and price (if PLU/short code provided)
	 * or standardizes an existing EAN-13.
	 *
	 * @param string $codeOrPlu
	 * @param float $price
	 * @return string
	 */
	public static function formatFruitEan(string $codeOrPlu, float $price): string
	{
		$digits = preg_replace('/\D/', '', $codeOrPlu) ?? '';
		if (strlen($digits) === 13) {
			return $digits;
		}
		$plu = str_pad(substr($digits ?: '10001', -5), 5, '0', STR_PAD_LEFT);
		$priceHalere = str_pad(substr((string)round($price * 100), -5), 5, '0', STR_PAD_LEFT);
		$body = '28' . $plu . $priceHalere;
		$d = array_map('intval', str_split($body));
		$sum = 0;
		foreach ($d as $i => $v) {
			$sum += $v * ($i % 2 ? 3 : 1);
		}
		$check = (10 - ($sum % 10)) % 10;
		return $body . $check;
	}
}
