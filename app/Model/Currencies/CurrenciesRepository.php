<?php


class CurrenciesRepository extends BaseRepositoryML
{
	protected $tableName = 'currencies';


	/**
	 * @param $id
	 * @return bool
	 */
	public function delete($id): bool
	{
		if (!$row = $this->find($id)) {
			return FALSE;
		}

		if ($row->is_default) {
			return FALSE;
		}

		if ($this->findAll()->count() === 1) {
			return FALSE;
		}

		return parent::delete($id);
	}
}
