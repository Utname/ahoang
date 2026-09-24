<?php


trait FilesDataServiceFilesTrait
{
	/**
	 * @param string $filename
	 * @param string $title
	 * @return FilesDataEntity
	 */
	public function setFileTitle(string $filename, string $title): FilesDataEntity
	{
		$values = [
			'file' => $filename,
			'title' => $title
		];

		return $this->setData($filename, FilesDataEntity::TYPE_FILE, $values);
	}
}
