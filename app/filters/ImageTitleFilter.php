<?php


class ImageTitleFilter
{
	/**
	 * @param GlobalContainer $globalContainer
	 */
	public function __construct(protected GlobalContainer $globalContainer)
	{
	}


	/**
	 * @param string|null $filename
	 * @return string
	 */
	public function process(?string $filename): string
	{

		$fileData = $filename ? $this->globalContainer->getImageData($filename) : NULL;
		if(!is_null($fileData)){
			return $fileData->title;
		}
		return '';
	}
}
