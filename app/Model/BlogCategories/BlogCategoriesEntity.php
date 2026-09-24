<?php


class BlogCategoriesEntity extends BaseEntity
{
	/**
	 * @return string
	 */
	public function getUrl(): string
	{
		return $this->slug;
	}
}
