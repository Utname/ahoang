<?php

namespace App\FrontModule\Presenters;

use \Nette\Application\BadRequestException;
use \Nette\Application\Responses\FileResponse;
use Nette\DI\Container;

class ImagePresenter extends \Nette\Application\UI\Presenter
{
	/** @var Container @inject */
	public $container;

	/** @var \ImagesService @inject */
	public $imagesService;


	/**
	 * @param string $filename
	 * @throws BadRequestException
	 */
	public function actionDefault(string $filename)
	{
		$imageTypes = $this->container->getParameters()['images'];

		if (!\ImagesService::imageTypeExists($imageTypes, $filename)) {
			throw new BadRequestException('Image not found');
		}

		if (!$imagePath = $this->imagesService->saveThumbnail($filename)) {
			throw new BadRequestException('Image not found');
		}

		$this->sendResponse(new FileResponse($imagePath));
	}
}
