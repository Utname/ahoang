<?php


class LinksEntity extends BaseEntity
{
	/**
	 * @param string|null $type
	 * @return string
	 */
	public function getIcon(string $type = NULL): string
	{
		return $type ? str_replace(['fas', 'fab', 'far'], $type, $this->icon) : $this->icon;
	}
}
