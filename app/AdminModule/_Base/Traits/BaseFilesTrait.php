<?php

namespace App\AdminModule\Traits;

use BaseEntity;
use Nette\Application\AbortException;
use Nette\Application\Responses\FileResponse;
use Nette\Application\UI\Form;
use Nette\Utils\Random;

trait BaseFilesTrait
{
	/** @var string */
	protected $filesComponentTempDir = '';


	/**
	 * @return void
	 */
	public function startupBaseFilesTrait()
	{
		$this->preserveOriginalFileNames();
		$this->filesComponentTempDir = Random::generate(8);
	}


	/**
	 * @param string $file
	 * @param string $downloadName
	 * @return void
	 * @throws AbortException
	 * @throws \Nette\Application\BadRequestException
	 */
	public function handleDownloadFile(string $file): void
	{
		if ($file && \FilesService::fileExists($file)) {

			$fileTitleFilter = new \FileTitleFilter($this->globalContainer);
			$downloadName = $fileTitleFilter->process($file);
			if ($downloadName && strpos($downloadName, '.') === FALSE) {
				$downloadName .= '.'.pathinfo($file, PATHINFO_EXTENSION);
			}
			elseif (!$downloadName) {
				$downloadName = $file;
			}

			$this->presenter->sendResponse(new FileResponse(FS_DIR.'/files/'.\FilesService::getFileDir($file).'/'.$file, $downloadName, NULL, TRUE));
		}

		$this->redirect('this');
	}


	/**
	 * @param BaseEntity $entity
	 * @param Form $form
	 * @param \BaseRepository $repository
	 * @return void
	 */
	protected function processFiles(BaseEntity $entity, Form $form, \BaseRepository $repository)
	{
		// update exists images
		$params = $form->getHttpData();

		if (isset($params['files'])) {
			$entity->files = $params['files'];
			$repository->update($entity);
		}

		// new files
		$values = $form->getValues(TRUE);

		if ($repository->isWithFiles() && isset($values['temp_files_directory']) && $values['temp_files_directory']) {
			$repository->addFiles($entity, $values['temp_files_directory']);
		}
	}

	/**
	 * @return void
	 */
	public function preserveOriginalFileNames(): void
	{
		if(isset($_SESSION['preserveFileUploadNames']) && !empty($_SESSION['preserveFileUploadNames'])){
			foreach($_SESSION['preserveFileUploadNames'] as $newFileName => $originalFileName){
				$this->filesDataService->setFileTitle($newFileName, pathinfo($originalFileName, PATHINFO_FILENAME));
				unset($_SESSION['preserveFileUploadNames'][$newFileName]);
			}
		}
		unset($_SESSION['preserveFileUploadNames']);
	}
}
