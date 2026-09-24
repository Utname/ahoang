<?php


class FileSizeFilter
{
	public function process(?float $fileSize): string
	{
		$units = [
			'GB' => pow(1024, 3),
			'MB' => pow(1024, 2),
			'KB' => 1024
		];

		foreach ($units as $unit => $value) {
			if ($fileSize > $value / 10) {
				return number_format($fileSize / $value, 2, '.', ' ').' '.$unit;
			}
		}

		return number_format($fileSize, 0, '.', ' ').' B';
	}
}