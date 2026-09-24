<?php

namespace App\AdminModule\Presenters;

use GlobalContainer;
use ImagesService;
use Nette\Localization\Translator;

class ImageCropControl extends BaseControl
{
	/** @var string */
	protected $image;


	/**
	 * ImageCropControl constructor.
	 */
	public function __construct(protected Translator $translator, protected ImagesService $imagesService, protected GlobalContainer $globalContainer)
	{
	}


	/**
	 * @param string $filename
	 */
	public function openModal(string $filename)
	{
		$this->template->imagePath = '/files/images/'.ImagesService::getImageDir($filename).'/'.$filename;
		$this->template->imageData = $this->globalContainer->getImageData($filename);
		$this->template->image = $filename;

		$this->redrawControl('imageCrop');
	}


	/**
	 * @param string $image
	 * @param int $x
	 * @param int $y
	 * @param int $width
	 * @param int $height
	 */
	public function handleProcessImageCrop(string $filename, int $x, int $y, int $width, int $height)
	{
		$this->imagesService->cropImage($filename, $x, $y, $width, $height);
	}


	/**
	 * @param string $filename
	 */
	public function handleRemoveImageCropData(string $filename)
	{
		$this->imagesService->removeImageCrop($filename);
	}
}
