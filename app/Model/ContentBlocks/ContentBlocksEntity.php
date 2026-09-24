<?php


class ContentBlocksEntity extends BaseEntity
{

	/**
	 * @param string $column
	 * @param $value
	 */
	public function setValue(string $column, $value)
	{
		parent::setValue($column, $value);

		$data = json_decode($this->data, TRUE);
		$data[$column] = $value;

		$this->data = json_encode($data, JSON_UNESCAPED_UNICODE);
	}


	/**
	 * @param string $parameter
	 * @return mixed
	 */
	public function getValue(string $parameter): mixed
	{
		$data = json_decode($this->data, TRUE);
		$defaultReturnValue = NULL;
		$parameterDefinition = \ContentBlocksDefinition::BLOCK_TYPES[$this->types_id][\ContentBlocksDefinition::PARAMETERS][$parameter] ?? NULL;
		if(is_array($parameterDefinition) && in_array($parameterDefinition[\ContentBlocksDefinition::TYPE], [\ContentBlocksDefinition::PARAMETER_TYPE_MULTIPLICATOR, \ContentBlocksDefinition::PARAMETER_TYPE_MULTISELECT])){
			$defaultReturnValue = [];
		}

		return $data[$parameter] ?? $defaultReturnValue;
	}




	/**
	 * @param string $parameter
	 * @return array|null
	 */
	public function getParameterDefinition(string $parameter): ?array
	{
		return \ContentBlocksDefinition::BLOCK_TYPES[$this->types_id][\ContentBlocksDefinition::PARAMETERS][$parameter] ?? NULL;
	}


	/**
	 * @param string $parameterKey
	 * @return array
	 */
	public function getContentImages(string $parameterKey): array
	{
		$images = [];
		if ($dataImages = $this->getValue($parameterKey)) {
			foreach (explode(';', $dataImages) as $jsonFile) {
				$file = json_decode($jsonFile);

				if ($file->file && ImagesService::imageExists($file->file)) {
					$images[] = $file;
				}
			}
		}

		return $images;
	}


	/**
	 * @param string $parameterKey
	 * @return array
	 */
	public function getContentFiles(string $parameterKey): array
	{
		$files = [];
		if ($dataFiles = $this->getValue($parameterKey)) {
			foreach (explode(';', $dataFiles) as $jsonFile) {
				$file = json_decode($jsonFile);

				if ($file->file && FilesService::fileExists($file->file)) {
					$file->extension = pathinfo($file->file, PATHINFO_EXTENSION);
					$file->formattedFileSize = FilesService::getFormattedFileSize($file->file);
					$files[] = $file;
				}
			}
		}

		return $files;
	}
}
