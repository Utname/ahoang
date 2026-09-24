<?php

use \Nette\Utils\Random;
use \Nette\Utils\Strings;

class FilesService
{
	use \Nette\SmartObject;

	/**
	 * @param string $filename
	 * @return bool
	 */
	public static function fileExists(string $filename): bool
	{
		$dir = self::getFileDir($filename);

		return file_exists(FS_DIR.'/files/'.$dir.'/'.$filename) && is_file(FS_DIR.'/files/'.$dir.'/'.$filename);
	}


	/**
	 * @param string $tempDirectory
	 * @param SplFileInfo $fileInfo
	 * @return stdClass|null
	 */
	public static function saveFile(string $tempDirectory, SplFileInfo $fileInfo): ?stdClass
	{
		$filename = Strings::substring(md5(microtime().Random::generate(12)), 0, 16).'.'.$fileInfo->getExtension();

		$file = new stdClass();
		$file->file = $filename;

		if(copy($tempDirectory.'/'.$fileInfo->getFilename(), FS_DIR.'/files/'.self::getFileDir($filename).'/'.$filename)){
			if(isset($_SESSION['preserveDropzoneUploadNames'][$fileInfo->getFilename()])){
				//dropzone
				if(!isset($_SESSION['preserveFileUploadNames'])){
					$_SESSION['preserveFileUploadNames'] = [];
				}
				$_SESSION['preserveFileUploadNames'][$filename] = $_SESSION['preserveDropzoneUploadNames'][$fileInfo->getFilename()];
				unset($_SESSION['preserveDropzoneUploadNames'][$fileInfo->getFilename()]);
			}
			else{
				//classic file upload
				$_SESSION['preserveFileUploadNames'][$filename] = $fileInfo->getFilename();
			}

			return $file;
		}
		else{
			return NULL;
		}
	}


	/**
	 * @param string $fileName
	 * @return string|null
	 */
	public static function copyFile(string $fileName): ?string
	{
		$copyFilePath = FS_DIR.'/files/'.self::getFileDir($fileName).'/'.$fileName;
		if(!file_exists($copyFilePath)){
			return NULL;
		}

		$extension = Strings::lower(pathinfo($fileName, PATHINFO_EXTENSION));

		$newFileName = Strings::substring(md5(microtime().Random::generate(12)), 0, 16).'.'.$extension;

		copy($copyFilePath, FS_DIR.'/files/'.self::getFileDir($newFileName).'/'.$newFileName);

		return $newFileName;
	}


	/**
	 * @param \Nette\Http\FileUpload $file
	 * @return string|null
	 */
	public static function saveFileUpload(\Nette\Http\FileUpload $file)
	{
		if (!$file->isOk()) {
			return NULL;
		}

		$extension = Strings::lower(pathinfo($file->getName(), PATHINFO_EXTENSION));
		$filename = Strings::substring(md5(microtime().Random::generate(12)), 0, 16).'.'.$extension;
		$file->move(FS_DIR.'/files/'.self::getFileDir($filename).'/'.$filename);


		$_SESSION['preserveFileUploadNames'][$filename] = $file->getName();

		return $filename;
	}


	/**
	 * @param string $filename
	 * @return string
	 */
	public static function getFileDir(string $filename): string
	{
		$fileDir = Strings::substring($filename, 0, 2).'/'.Strings::substring($filename, 2, 2);

		self::createDirIfNotExists(FS_DIR.'/files', $fileDir);

		return $fileDir;
	}


	/**
	 * @param string $mainDir
	 * @param string $dir
	 * @return void
	 */
	public static function createDirIfNotExists(string $mainDir, string $dir)
	{
		$parts = explode(DIRECTORY_SEPARATOR, $dir);

		$dir = $mainDir;

		foreach ($parts as $part) {
			$dir .= DIRECTORY_SEPARATOR.$part;

			if (!file_exists($dir)) {
				@mkdir($dir, 0755, TRUE);
				@chmod($dir, 0755);
			}
		}
	}


	/**
	 * @param string $filename
	 * @return int
	 */
	public static function getFileSize(string $filename): int
	{
		$dir = self::getFileDir($filename);
		$filePath = FS_DIR.'/files/'.$dir.'/'.$filename;

		return file_exists($filePath) && is_file($filePath) ? filesize($filePath) : 0;
	}


	/**
	 * @param string $filename
	 * @return string
	 */
	public static function getFormattedFileSize(string $filename): string
	{
		if (!$fileSizeInBytes = self::getFileSize($filename)) {
			return '';
		}

		if ($fileSizeInBytes > pow(1024, 3)) {
			return round($fileSizeInBytes / pow(1024, 3), 2).' GB';
		}
		elseif ($fileSizeInBytes > pow(1024, 2)) {
			return round($fileSizeInBytes / pow(1024, 2), 2).' MB';
		}
		elseif ($fileSizeInBytes > 1024) {
			return round($fileSizeInBytes / 1024, 2).' KB';
		}

		return $fileSizeInBytes.' B';
	}
}
