<?php

namespace App\AdminModule\Traits;


use App\AdminModule\Presenters\ImageCropControl;
use App\AdminModule\Presenters\ImagesControl;
use BaseEntity;
use Nette\Application\UI\Form;
use Nette\Utils\Random;
use Nette\Utils\UnknownImageFileException;

trait BaseImagesTrait
{
	/** @var string */
	protected $imagesComponentTempDir = '';

	/** @var \ImagesService @inject */
	public $imagesService;

	/** @var \FilesDataService @inject */
	public $filesDataService;


	/**
	 * @return void
	 */
	public function startupBaseImageTrait()
	{
		$this->preserveOriginalImageNames();
		$this->imagesComponentTempDir = Random::generate(8);
	}

	/**
	 * @return ImageCropControl
	 */
	protected function createComponentImageCrop(): ImageCropControl
	{
		$control = new ImageCropControl($this->translator, $this->imagesService, $this->globalContainer);
		return $control;
	}


	/**
	 * @return ImagesControl
	 */
	protected function createComponentImages(): ImagesControl
	{
		$control = new ImagesControl($this->imagesService, $this->filesDataService);
		$control->setAction($this->getAction());
		$control->setTempDir($this->imagesComponentTempDir);

		if ($this->entity) {
			$control->setImages($this->entity->getImages());
		}

		return $control;
	}


	/**
	 * @param BaseEntity $entity
	 * @param Form $form
	 * @param \BaseRepository $repository
	 * @return void
	 * @throws UnknownImageFileException
	 */
	protected function processImages(BaseEntity $entity, Form $form, \BaseRepository $repository)
	{

		// update exists images
		$params = $form->getHttpData();

		if (isset($params['images'])) {
			$entity->images = $params['images'];
			$repository->update($entity);
		}

		// new images
		$values = $form->getValues(TRUE);

		if ($repository->isWithImages() && isset($values['temp_images_directory']) && $values['temp_images_directory']) {
			$repository->addImages($entity, $values['temp_images_directory']);
		}
	}

	/**
	 * @return void
	 */
	public function preserveOriginalImageNames(): void
	{
		if(isset($_SESSION['preserveImageUploadNames']) && !empty($_SESSION['preserveImageUploadNames'])){
			foreach($_SESSION['preserveImageUploadNames'] as $newFileName => $originalFileName){
				$this->filesDataService->setImageTitle($newFileName, pathinfo($originalFileName, PATHINFO_FILENAME));
				unset($_SESSION['preserveImageUploadNames'][$newFileName]);
			}
		}
		unset($_SESSION['preserveImageUploadNames']);
	}
}
