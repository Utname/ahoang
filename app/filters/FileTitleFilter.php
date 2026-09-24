<?php


class FileTitleFilter
{
	/**
	 * @param GlobalContainer $globalContainer
	 */
	public function __construct(protected GlobalContainer $globalContainer)
	{
	}


	/**
	 * @param string $filename
	 * @return string
	 */
	public function process(string $filename): string
	{
		$fileData = $this->globalContainer->getFileData($filename);
		if(!is_null($fileData)){
			return $fileData->title;
		}
		return '';
	}
}
