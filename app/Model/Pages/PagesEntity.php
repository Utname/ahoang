<?php


class PagesEntity extends BaseEntity
{
	/** @var PageTypesEntity */
	protected $pageType;


	/**
	 * @return PageTypesEntity|null
	 */
	public function getType(): ?PageTypesEntity
	{
		if (!$this->pageType && $this->page_types_id && $row = $this->getRow()->ref('page_types')) {
			$this->pageType = PageTypesRepository::getEntity($row);
		}

		return $this->pageType;
	}
}