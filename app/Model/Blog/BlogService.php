<?php


class BlogService
{
	use BlogServiceCategoriesTrait;
	use \Nette\SmartObject;

	/**
	 * @param \Nette\Database\Explorer $database
	 */
	public function __construct(
		protected \Nette\Database\Explorer $database,
	)
	{
	}
}
