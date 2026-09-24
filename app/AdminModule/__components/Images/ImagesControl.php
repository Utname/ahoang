<?php

namespace App\AdminModule\Presenters;

use FilesDataService;
use ImagesService;

class ImagesControl extends BaseControl
{
	/** @var string|null */
	protected $action;

	/** @var string|null */
	protected $tempDir;

	/** @var array */
	protected $images = [];

	/** @var array */
	protected $parameters = [];


	/**
	 * @param ImagesService $imagesService
	 * @param FilesDataService $filesDataService
	 */
	public function __construct(protected ImagesService $imagesService, protected FilesDataService $filesDataService)
	{
	}


	/**
	 * @return string|null
	 */
	public function getTempDir(): ?string
	{
		return $this->tempDir;
	}


	/**
	 * @param string $tempDir
	 * @return FilesControl
	 */
	public function setTempDir(string $tempDir): ImagesControl
	{
		$this->tempDir = $tempDir;

		return $this;
	}


	/**
	 * @return array
	 */
	public function getImages(): array
	{
		return $this->images;
	}


	/**
	 * @return array
	 */
	public function getJsonImages(): array
	{
		$jsonImages = [];

		foreach ($this->getImages() as $image) {
			$jsonImages[] = json_encode($image);
		}

		return $jsonImages;
	}


	/**
	 * @param array $images
	 * @return FilesControl
	 */
	public function setImages(array $images): ImagesControl
	{
		$this->images = $images;

		return $this;
	}



	/*
	 *
	 */
	public function handleRotateImage(string $filename)
	{
		$this->imagesService->rotateImage($filename);

		/** @var BaseModulePresenter $presenter */
		$presenter = $this->getPresenter();
		$presenter->redrawControl('flashMessages');
	}


	/**
	 * @param string $filename
	 * @param string $title
	 */
	public function handleSetImageTitle(string $filename, string $title)
	{
		$this->filesDataService->setImageTitle($filename, $title);
	}



	/*
	 *
	 */
	public function handleCropImage(string $filename)
	{
		/** @var BasePresenter $presenter */
		$presenter = $this->getPresenter();
		/** @var ImageCropControl $imageCropControl */
		$imageCropControl = $presenter['imageCrop'];

		$imageCropControl->openModal($filename);
	}


	/**
	 * @return string|null
	 */
	public function getAction(): ?string
	{
		return $this->action;
	}


	/**
	 * @param string|null $action
	 */
	public function setAction(?string $action): void
	{
		$this->action = $action;
	}


}
