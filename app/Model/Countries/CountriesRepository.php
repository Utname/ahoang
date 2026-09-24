<?php


class CountriesRepository extends BaseRepositoryML
{
	protected $tableName = 'countries';


	/**
	 * @return CountriesEntity|null
	 */
	public function findDefault(): ?CountriesEntity
	{
		if ($row = $this->findAllActive('pos_order')->limit(1)->fetch()) {
			return $this->getEntity($row);
		}

		return NULL;
	}
}