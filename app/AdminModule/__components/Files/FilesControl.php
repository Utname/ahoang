<?php

namespace App\AdminModule\Presenters;

use FilesDataService;

class FilesControl extends BaseControl
{
	/** @var string|null */
	protected $action;

	/** @var string */
	protected $tempDir;

	/** @var array */
	protected $files = [];


	/**
	 * @param FilesDataService $filesDataService
	 */
	public function __construct(protected FilesDataService $filesDataService)
	{
	}


	/**
	 * @return string
	 */
	public function getTempDir(): string
	{
		return $this->tempDir;
	}


	/**
	 * @param string $tempDir
	 * @return FilesControl
	 */
	public function setTempDir(string $tempDir): FilesControl
	{
		$this->tempDir = $tempDir;

		return $this;
	}


	/**
	 * @return array
	 */
	public function getFiles(): array
	{
		return $this->files;
	}


	/**
	 * @param array $images
	 * @return FilesControl
	 */
	public function setFiles(array $files): FilesControl
	{
		$this->files = $files;

		return $this;
	}


	/**
	 * @return array
	 */
	public function getJsonFiles(): array
	{
		$jsonFiles = [];

		foreach ($this->getFiles() as $file) {
			$jsonFiles[] = json_encode($file);
		}

		return $jsonFiles;
	}

	/**
	 * @param string $filename
	 * @param string $title
	 */
	public function handleSetFileTitle(string $filename, string $title)
	{
		$this->filesDataService->setFileTitle($filename, $title);
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
