<?php


class LanguagesEntity extends BaseEntity
{
	/**
	 * @return bool
	 */
	public function canBeDeleted(): bool
	{
		return !$this->is_default;
	}
}