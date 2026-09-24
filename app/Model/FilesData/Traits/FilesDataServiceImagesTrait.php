<?php


trait FilesDataServiceImagesTrait
{
	/**
	 * @param string $filename
	 * @param int $rotation
	 * @return FilesDataEntity
	 */
	public function setImageRotation(string $filename, int $rotation): FilesDataEntity
	{
		$values = [
			'file' => $filename,
			'rotate_degrees' => $rotation > 0 ? $rotation : NULL
		];

		return $this->setData($filename, FilesDataEntity::TYPE_IMAGE, $values);
	}

	/**
	 * @param string $filename
	 * @return FilesDataEntity
	 */
	public function rotateImage(string $filename): FilesDataEntity
	{
		$rotation = 0;
		/** @var FilesDataEntity|null $filesDataEntity */
		if($filesDataEntity = $this->filesDataRepository->findEntityBy(['file' => $filename])){
			$rotation = $filesDataEntity->rotate_degrees;
		}
		$rotation += 90;
		if($rotation >= 360){
			$rotation = 0;
		}

		return $this->setImageRotation($filename, $rotation);
	}

	/**
	 * @param string $filename
	 * @param int $x
	 * @param int $y
	 * @param int $width
	 * @param int $height
	 * @return FilesDataEntity
	 */
	public function setImageCrop(string $filename, int $x, int $y, int $width, int $height): FilesDataEntity
	{
		$values = [
			'file' => $filename,
			'crop_x' => $x,
			'crop_y' => $y,
			'crop_width' => $width,
			'crop_height' => $height,
		];

		return $this->setData($filename, FilesDataEntity::TYPE_IMAGE, $values);
	}


	/**
	 * @param string $filename
	 * @return FilesDataEntity
	 */
	public function removeImageCrop(string $filename): FilesDataEntity
	{
		$values = [
			'file' => $filename,
			'crop_x' => NULL,
			'crop_y' => NULL,
			'crop_width' => NULL,
			'crop_height' => NULL,
		];

		return $this->setData($filename, FilesDataEntity::TYPE_IMAGE, $values);
	}


	/**
	 * @param string $filename
	 * @param string $title
	 * @return FilesDataEntity
	 */
	public function setImageTitle(string $filename, string $title): FilesDataEntity
	{
		$values = [
			'file' => $filename,
			'title' => $title
		];

		return $this->setData($filename, FilesDataEntity::TYPE_IMAGE, $values);
	}
}
