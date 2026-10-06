<?php


class PrintLabelsEntity extends BaseEntity
{
	const TYPE_PRICE = 1;
	const TYPE_DESCRIPTION = 2;
	const TYPE_PRICE_MANUAL = 3;
	const TYPE_FRUIT = 4;


	/**
	 * @return stdClass|null
	 */
	public function getData(): ?stdClass
	{
		if (!$this->data) {
			return NULL;
		}

		$data = json_decode($this->data);

		if (json_last_error() !== JSON_ERROR_NONE) {
			return NULL;
		}

		return $data;
	}
}
