<?php


class Helpers
{
	/**
	 * @param DateTime|null $dateTimeFrom
	 * @param DateTime|null $dateTimeTo
	 * @return string
	 */
	public static function formatPeriod(?DateTime $dateTimeFrom, ?DateTime $dateTimeTo): string
	{
		$parts = [];
		if ($dateTimeFrom) {
			$parts[] = $dateTimeFrom->format('d.m.Y');
		}
		if ($dateTimeTo) {
			$parts[] = $dateTimeTo->format('d.m.Y');
		}

		return implode(' - ', $parts);
	}


	/**
	 * @param string $fileName
	 * @return string
	 */
	public static function getExtension(string $fileName): string
	{
		return pathinfo($fileName, PATHINFO_EXTENSION);
	}


	/**
	 * @param string $fileName
	 * @return string
	 */
	public static function getFormattedFileSize(string $fileName): string
	{
		return FilesService::getFormattedFileSize($fileName);
	}


	/**
	 * @param string $string
	 * @return bool
	 */
	public static function isPhoneNumber(string $string): bool
	{
		return preg_match('/^((\+|00){1}[0-9]{3})?([- ]?[1-9][0-9]{2}[- ]?[0-9]{3}[- ]?[0-9]{3,4})?$/', $string);
	}


	/**
	 * @param string $string
	 * @return bool
	 */
	public static function isEmailAddress(string $string): bool
	{
		return \Nette\Utils\Validators::isEmail($string);
	}


	/**
	 * @param string $path
	 * @param bool $includingParent
	 * @return bool|void
	 */
	public static function recursiveDelete(string $path, bool $includingParent = TRUE) {
		if($includingParent){
			if (is_file($path)) {
				return @unlink($path);
			}
			elseif (is_dir($path)) {
				$scan = glob(rtrim($path,'/').'/*');
				foreach($scan as $index=>$thisPath) {
					self::recursiveDelete($thisPath);
				}
				return @rmdir($path);
			}
		}
		else{
			$scan = glob(rtrim($path,'/').'/*');
			foreach($scan as $index=>$thisPath) {
				self::recursiveDelete($thisPath, TRUE);
			}
		}
	}

}
