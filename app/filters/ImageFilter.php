<?php


class ImageFilter
{
	const RETURN_REMOTE_IMAGES_WHEN_NOT_FOUND = FALSE;

	/** @var array */
	protected $imagesTypes = [];


	/**
	 * ImageFilter constructor.
	 * @param array $imagesTypes
	 */
	public function __construct(array $imagesTypes)
	{
		$this->imagesTypes = $imagesTypes;
	}


	/**
	 * @param string|null $filename
	 * @param string $type
	 * @param bool $useWebpFormat
	 * @param $addHostUrl
	 * @return string
	 * @throws Exception
	 */
	public function process(?string $filename, string $type, bool $useWebpFormat = TRUE, $addHostUrl = FALSE): string
	{
		if (!isset($this->imagesTypes[$type])) {
			throw new Exception('Image type "'.$type.'" not exists in config.images.neon');
		}

		if(!$filename && file_exists(FS_DIR.'/universal.png')){
			$filename = 'universal.png';
			$useWebpFormat = FALSE;
		}

		$pathInfo = pathinfo($filename);
		if(!isset($pathInfo['extension'])){
			return '';
		}


		@[$width, $height, $flag, $quality, $watermark] = explode('-', $this->imagesTypes[$type]);

		if(strtolower($pathInfo['extension']) == 'svg'){
			$dir = '/files/images/'.ImagesService::getImageDir($filename).'/'.$filename;
		}
		else{
			$thumbnailDir = ImagesService::getImageDir($filename);
			$thumbnailName = ImagesService::getThumbnailName($filename, $width, $height, $flag, $quality, $watermark, $useWebpFormat);
			$dir = '/media/'.$thumbnailDir.'/'.$thumbnailName;
		}

		//browser cache workaround (when rotated, cropped)
		/** @var GlobalContainer $globalContainer */
		$globalContainer = $GLOBALS['container'];
		if(!is_null($imageData = $globalContainer->getImageData($filename)) &&
			$version = implode('', [$imageData->crop_x, $imageData->crop_y, $imageData->crop_width, $imageData->crop_height, $imageData->rotate_degrees]))
		{
			$dir .= '?v='.$version ;
		}

        if (self::RETURN_REMOTE_IMAGES_WHEN_NOT_FOUND && str_contains($globalContainer->getUrlScript()->getHost(), '.loc') && !ImagesService::imageExists($filename)) {
            return 'https://' . str_replace('.loc', '.cz', $globalContainer->getUrlScript()->getHost()) .  $dir;
        } else {
            return ($addHostUrl ? $globalContainer->getUrlScript()->getHostUrl() : '') . $dir;
        }
	}
}
