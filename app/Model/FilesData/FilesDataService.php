<?php


class FilesDataService
{
	use \Nette\SmartObject;

	use FilesDataServiceImagesTrait;
	use FilesDataServiceFilesTrait;
	/**
	 * @param FilesDataRepository $filesDataRepository
	 */
	public function __construct(protected FilesDataRepository $filesDataRepository)
	{
	}


	/**
	 * @param string $filename
	 * @param int $typesId
	 * @param array $values
	 * @return FilesDataEntity
	 */
	public function setData(string $filename, int $typesId, array $values): FilesDataEntity
	{
		$values['types_id'] = $typesId;

		/** @var FilesDataEntity|null $filesDataEntity */
		if($filesDataEntity = $this->filesDataRepository->findEntityBy(['file' => $filename, 'types_id' => $typesId])){
			$filesDataEntity->setValues($values);
			$this->filesDataRepository->update($filesDataEntity);
		}
		else{
			$newRow = $this->filesDataRepository->insert($values);
			$filesDataEntity = \FilesDataRepository::getEntity($newRow);
		}

		return $filesDataEntity;
	}


	/**
	 * @param string $fromFileName
	 * @param string $toFileName
	 * @param int $typesId
	 * @return bool
	 */
	public function copyData(string $fromFileName, string $toFileName, int $typesId): bool
	{

		/** @var FilesDataEntity|null $filesDataEntity */
		if($filesDataEntity = $this->filesDataRepository->findEntityBy(['file' => $fromFileName, 'types_id' => $typesId])){
			$values = $filesDataEntity->getValues();
			unset($values['id']);
			$values['file'] = $toFileName;
			$this->filesDataRepository->insert($values);
			return TRUE;
		}

		return FALSE;
	}
}
