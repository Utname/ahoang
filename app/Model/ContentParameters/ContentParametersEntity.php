<?php


class ContentParametersEntity extends BaseEntity
{
	const TYPE_TEXT = 1;
	const TYPE_WSW = 2;
	const TYPE_IMG = 3;
	const TYPE_DATE = 4;
	const TYPE_COLOR = 5;
	const TYPE_ICON = 6;
	const TYPE_BOOL = 7;


	/**
	 * @return mixed
	 */
	public function getDataValue(): mixed
	{
		return $this->data ?: '';
	}
}
