<?php

use Nette\Http\FileUpload;
use Nette\Http\SessionSection;
use \Nette\Utils\Image;
use \Nette\Utils\Random;
use \Nette\Utils\Strings;

class ImagesService
{
	use \Nette\SmartObject;

	const FLAG_FIT = 'f';
	const FLAG_EXACT = 'e';
	const FLAG_FIT_WHITE_BG = 'w';
	const FLAG_FIT_BLACK_BG = 'b';

	const SOURCE_IMAGE_MAX_WIDTH = 1920;
	const SOURCE_IMAGE_MAX_HEIGHT = 1920;
	const SOURCE_IMAGE_MAX_QUALITY = 80;


	/**
	 * @param FilesDataService $filesDataService
	 * @param GlobalContainer $globalContainer
	 */
	public function __construct(protected FilesDataService $filesDataService, protected GlobalContainer $globalContainer)
	{
	}


	/**
	 * @param string $filename
	 * @return bool
	 */
	public static function imageExists(string $filename): bool
	{
		$dir = self::getImageDir($filename);

		return file_exists(FS_DIR.'/images/'.$dir.'/'.$filename) && is_file(FS_DIR.'/images/'.$dir.'/'.$filename);
	}


	/**
	 * @param string $filename
	 * @return FilesDataEntity
	 */
	public function rotateImage(string $filename): FilesDataEntity
	{
		$filesDataEntity = $this->filesDataService->rotateImage($filename);

		self::removeThumbnails($filename);

		return $filesDataEntity;
	}


	/**
	 * @param string $filename
	 * @return FilesDataEntity
	 */
	public function removeImageCrop(string $filename): FilesDataEntity
	{
		$filesDataEntity = $this->filesDataService->removeImageCrop($filename);

		self::removeThumbnails($filename);

		return $filesDataEntity;
	}


	/**
	 * @param string $filename
	 * @param int $x
	 * @param int $y
	 * @param int $width
	 * @param int $height
	 * @return FilesDataEntity
	 */
	public function cropImage(string $filename, int $x, int $y, int $width, int $height): FilesDataEntity
	{
		$filesDataEntity = $this->filesDataService->setImageCrop($filename, $x, $y, $width, $height);

		self::removeThumbnails($filename);

		return $filesDataEntity;
	}


	/**
	 * @param string|null $forFilename
	 */
	public static function removeThumbnails(string $forFilename = NULL)
	{
		if(is_null($forFilename)){
			Helpers::recursiveDelete(WWW_DIR.'/media', FALSE);
		}
		else{
			$thumbnailDir = self::getImageDir($forFilename);
			array_map('unlink', glob(WWW_DIR."/media/".$thumbnailDir."/*.*"));
		}
	}



	/**
	 * @param SplFileInfo $imageFile
	 * @return stdClass|null
	 * @throws \Nette\Utils\ImageException
	 * @throws \Nette\Utils\UnknownImageFileException
	 */
	public static function saveImage(SplFileInfo $imageFile): ?stdClass
	{
		$extension = \Nette\Utils\Strings::lower($imageFile->getExtension());
		$filename = Strings::substring(md5(microtime().Random::generate(12)), 0, 16).'.'.$extension;


		$file = new stdClass();
		$file->file = $filename;


        if(in_array($extension, ['jpg', 'jpeg', 'png', 'gif'])) {
            $image = Image::fromFile($imageFile->getRealPath());
            if ($image->getWidth() > self::SOURCE_IMAGE_MAX_WIDTH || $image->getHeight() > self::SOURCE_IMAGE_MAX_HEIGHT) {
                $image->resize(self::SOURCE_IMAGE_MAX_WIDTH, self::SOURCE_IMAGE_MAX_HEIGHT, Image::SHRINK_ONLY | Image::FIT);
            }

            if(in_array($extension, ['jpg', 'jpeg'])) {
                $exif = exif_read_data($imageFile->getRealPath());
                if (is_array($exif) && ($exif['Orientation'] ?? 0) == 3) {
                    $image->rotate(180, 0);
                }
            }

            $image->save(FS_DIR . '/images/' . self::getImageDir($filename) . '/' . $filename, self::SOURCE_IMAGE_MAX_QUALITY);
        }
        elseif(in_array($extension, ['svg'])){
            copy($imageFile->getRealPath(), FS_DIR . '/images/' . self::getImageDir($filename) . '/' . $filename);
        }
        else{
            return NULL;
        }

		if(isset($_SESSION['preserveDropzoneUploadNames'][$imageFile->getFilename()])){
			//dropzone
			if(!isset($_SESSION['preserveImageUploadNames'])){
				$_SESSION['preserveImageUploadNames'] = [];
			}
			$_SESSION['preserveImageUploadNames'][$filename] = $_SESSION['preserveDropzoneUploadNames'][$imageFile->getFilename()];
			unset($_SESSION['preserveDropzoneUploadNames'][$imageFile->getFilename()]);
		}
		else{
			//classic file upload
			$_SESSION['preserveImageUploadNames'][$filename] = $imageFile->getFilename();
		}

		return $file;
	}


	/**
	 * @param string $fileName
	 * @return string|null
	 */
	public static function copyImage(string $fileName): ?string
	{
		$copyImagePath = FS_DIR.'/images/'.self::getImageDir($fileName).'/'.$fileName;
		if(!file_exists($copyImagePath)){
			return NULL;
		}

		$extension = Strings::lower(pathinfo($fileName, PATHINFO_EXTENSION));

		if (!in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'svg'])) {
			return NULL;
		}

		$newFileName = Strings::substring(md5(microtime().Random::generate(12)), 0, 16).'.'.$extension;

		copy($copyImagePath, FS_DIR.'/images/'.self::getImageDir($newFileName).'/'.$newFileName);

		return $newFileName;
	}


	/**
	 * @param FileUpload $imageFile
	 * @return false|string
	 * @throws \Nette\Utils\ImageException
	 * @throws \Nette\Utils\UnknownImageFileException
	 */
	public static function saveImageUpload(FileUpload $imageFile)
	{
		if (!$imageFile->isOk()) {
			return FALSE;
		}

		$extension = Strings::lower(pathinfo($imageFile->getName(), PATHINFO_EXTENSION));

		$filename = Strings::substring(md5(microtime().Random::generate(12)), 0, 16).'.'.$extension;

		if(in_array($extension, ['jpg', 'jpeg', 'png', 'gif'])){
			$image = Image::fromFile($imageFile->getTemporaryFile());
			if($image->getWidth() > self::SOURCE_IMAGE_MAX_WIDTH || $image->getHeight() > self::SOURCE_IMAGE_MAX_HEIGHT){
				$image->resize(self::SOURCE_IMAGE_MAX_WIDTH, self::SOURCE_IMAGE_MAX_HEIGHT, Image::SHRINK_ONLY | Image::FIT);
			}

            if(in_array($extension, ['jpg', 'jpeg'])) {
                $exif = exif_read_data($imageFile->getTemporaryFile());
                if (is_array($exif) && ($exif['Orientation'] ?? 0) == 3) {
                    $image->rotate(180, 0);
                }
            }
            $image->save(FS_DIR.'/images/'.ImagesService::getImageDir($filename).'/'.$filename, ImagesService::SOURCE_IMAGE_MAX_QUALITY);
            $_SESSION['preserveImageUploadNames'][$filename] = $imageFile->getName();

		}
		elseif(in_array($extension, ['svg'])){
			$imageFile->move(FS_DIR.'/images/'.ImagesService::getImageDir($filename).'/'.$filename);

			$_SESSION['preserveImageUploadNames'][$filename] = $imageFile->getName();
		}
		else{
			return FALSE;
		}

		return $filename;
	}

	/**
	 * @param string $filename
	 * @return string
	 */
	public static function getImageDir(string $filename): string
	{
		$imageDir = Strings::substring($filename, 0, 2).'/'.Strings::substring($filename, 2, 2);

		FilesService::createDirIfNotExists(FS_DIR.'/images', $imageDir);

		return $imageDir;
	}


	/**
	 * @param string $filename
	 * @param int $width
	 * @param int $height
	 * @param string $flag
	 * @param int|NULL $quality
	 * @param string|NULL $watermark
	 * @param bool $useWebpFormat
	 * @return string
	 */
	public static function getThumbnailName(string $filename, int $width, int $height, string $flag, int $quality = NULL, string $watermark = NULL, bool $useWebpFormat = TRUE): string
	{
		$pathInfo = pathinfo($filename);

		return $pathInfo['filename'].'-'.$width.'-'.$height.'-'.$flag.($quality ? '-'.$quality : '').($watermark ? '-'.$watermark : '').($useWebpFormat ? '.webp' : '.'.$pathInfo['extension']);
	}


	/**
	 * @param array $imagesTypes
	 * @param string $filename
	 * @return bool
	 */
	public static function imageTypeExists(array $imagesTypes, string $filename): bool
	{
		$pathInfo = pathinfo($filename);
		@[$hash, $width, $height, $flag, $quality, $watermark] = explode('-', $pathInfo['filename']);

		foreach ($imagesTypes as $imagesType) {
			@[$itWidth, $itHeight, $itFlag, $itQuality, $itWatermark] = explode('-', $imagesType);
			if ($itWidth == $width && $itHeight == $height && $itFlag == $flag && $itQuality == $quality && $itWatermark == $watermark) {
				return TRUE;
			}
		}

		return FALSE;
	}


	/**
	 * @param string $filename
	 * @return string|null
	 * @throws \Nette\Utils\ImageException
	 * @throws \Nette\Utils\UnknownImageFileException
	 */
	public function saveThumbnail(string $filename): ?string
	{
		$pathInfo = pathinfo($filename);
		@[$hash, $width, $height, $flag, $quality, $watermark] = explode('-', $pathInfo['filename']);

		$originalFilename = $hash.'.'.$pathInfo['extension'];
		$originalFilePath = NULL;

		if ($hash === 'universal') { //universal image
			$originalFilePath = FS_DIR.'/'.$originalFilename;
		}
		else {
			if(strtolower($pathInfo['extension']) == 'svg'){ //svg image
				return WWW_DIR.'/files/images/'.self::getImageDir($originalFilename).'/'.$originalFilename;
			}
			elseif (strtolower($pathInfo['extension']) === 'webp') { //webp image
				$extension = NULL;
				foreach (['png', 'jpg', 'jpeg', 'webp'] as $ext) { //allowed original extensions for webp conversion
					$originalFilename = $hash.'.'.$ext;
					$originalFilePath = FS_DIR.'/images/'.self::getImageDir($originalFilename).'/'.$originalFilename;

					if (file_exists($originalFilePath)) {
						$extension = $ext;
						break;
					}
				}

				if (!$extension) {
					$originalFilename = $hash.'.'.$pathInfo['extension'];
					$originalFilePath = FS_DIR.'/images/'.self::getImageDir($originalFilename).'/'.$originalFilename;
				}
			}
		}

		if (is_null($originalFilePath) || !file_exists($originalFilePath)) {
			return NULL;
		}





		$hasAlphaBlending = $pathInfo['extension'] == 'png';

		$targetDir = self::getImageDir($originalFilename);

		FilesService::createDirIfNotExists(WWW_DIR.'/media', $targetDir);

		$image = Image::fromFile($originalFilePath);


		$imageData = $this->globalContainer->getImageData($originalFilename);
		if(!is_null($imageData) && ($imageData->crop_x != 0 || $imageData->crop_y != 0 || $imageData->crop_width != 0 || $imageData->crop_height != 0)){
			$image->crop($imageData->crop_x, $imageData->crop_y, $imageData->crop_width, $imageData->crop_height);
		}
		if(!is_null($imageData) && $imageData->rotate_degrees > 0){
			$image->rotate(-($imageData->rotate_degrees), 255);
		}


		if ($flag == self::FLAG_EXACT) {
			$image->resize($width, $height, Image::EXACT);
		}
		else {
			$image->resize($width, $height, Image::FIT | Image::SHRINK_ONLY);
		}

		if ($flag == self::FLAG_FIT_WHITE_BG || $flag == self::FLAG_FIT_BLACK_BG) {
			$color = $flag == self::FLAG_FIT_WHITE_BG ? 255 : 0;
			$bg = Image::rgb($color, $color, $color, $hasAlphaBlending ? 127 : 0);
			$bgImage = Image::fromBlank($width, $height, $bg);


			$bgImage->place($image, '50%', '50%', 100);
			$image = $bgImage;
		}

		/** @var object $watermarkInfo */
		$watermarkInfo = $this->globalContainer->getWatermarkInfo();
		if($watermarkInfo->is_active && $watermark){
			$watermarkImage = @Image::fromFile(WWW_DIR.'/files/images/'.self::getImageDir($watermarkInfo->image).'/'.$watermarkInfo->image);
			if($watermarkImage instanceof Image){
				$ratioPrepared = ($watermarkInfo->ratio / 100);
				$watermarkImage->resize(intval($image->getWidth() * $ratioPrepared), intval($image->getHeight() * $ratioPrepared), Image::FIT);
				$image->place($watermarkImage, $watermarkInfo->pos_x.'%', $watermarkInfo->pos_y.'%', $watermarkInfo->transparency);
			}
		}

		if ($hasAlphaBlending) {
			$image->alphaBlending(FALSE);
			$image->saveAlpha(TRUE);
		}

		$image->save(WWW_DIR.'/media/'.$targetDir.'/'.$filename, $quality ? $quality : 90);

		return WWW_DIR.'/media/'.$targetDir.'/'.$filename;
	}
}
