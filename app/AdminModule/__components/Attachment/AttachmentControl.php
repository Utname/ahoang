<?php

namespace App\AdminModule\Presenters;


use BaseEntity;
use BaseRepository;
use GlobalContainer;
use FilesDataService;
use ImagesService;
use Nette\Localization\Translator;

class AttachmentControl extends BaseControl
{
	/** @var string|null */
	protected $action;

	/**
	 * @param Translator $translator
	 * @param ImagesService $imagesService
	 * @param FilesDataService $filesDataService
	 * @param BaseRepository $repository
	 */
	public function __construct(
		protected Translator $translator,
		protected ImagesService $imagesService,
		protected FilesDataService $filesDataService,
		protected BaseRepository $repository
	)
	{
	}


	/**
	 * @param null $item
	 * @param string|null $field
	 * @param string|null $repository
	 */
	public function renderFile($item = NULL, string $field = NULL, string $repository = NULL)
	{
		$this->template->item = $item;
		$this->template->field = $field;
		$this->template->repository = $repository;

		$this->template->setFile(__DIR__.'/templates/_file.latte');
		$this->template->render();
	}


	/**
	 * @param null $item
	 * @param string|null $field
	 * @param string|null $imageType
	 * @param string|null $repository
	 */
	public function renderImage($item = NULL, string $field = NULL, string $imageType = NULL, string $repository = NULL)
	{
		$this->template->item = $item;
		$this->template->field = $field;
		$this->template->imageType = $imageType;
		$this->template->repository = $repository;

		$this->template->setFile(__DIR__.'/templates/_image.latte');
		$this->template->render();
	}


	/**
	 * @param int $itemId
	 * @param string $field
	 * @param string $value
	 * @param string|null $repository
	 * @throws \Nette\Application\AbortException
	 */
	public function handleSetValue(int $itemId, string $field, string $value = '', string $repository = NULL)
	{
		/** @var BaseModulePresenter $presenter */
		$presenter = $this->getPresenter();

		if(is_null($repository)){
			/** @var BaseRepository $repository */
			$repository = $this->repository;
		}
		else{
			/** @var BaseRepository $repository */
			$repository = $presenter->container->getByType($repository);
		}

		/** @var BaseEntity $entity */
		if ($entity = $repository->findEntity($itemId)) {
			$entity->setValue($field, $value);
			$repository->update($entity);

			/** @var BasePresenter $presenter */
			$presenter = $this->getPresenter();
			$presenter->cleanRelatedModuleCache();
		}

		$presenter->redrawControl('flashMessages');
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

	/**
	 * @param string $filename
	 * @param string $title
	 */
	public function handleSetFileTitle(string $filename, string $title)
	{
		$this->filesDataService->setFileTitle($filename, $title);
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
