<?php


class UnitsService
{
	use \Nette\SmartObject;

	/**
	 * UnitsService constructor.
	 * @param UnitsRepository $unitsRepo
	 * @param \Nette\Database\Explorer $database
	 */
	public function __construct(protected UnitsRepository $unitsRepo, protected \Nette\Database\Explorer $database)
	{
	}


	/**
	 * @param UnitsEntity $unit
	 * @return bool
	 */
	public function setAsDefault(UnitsEntity $unit): bool
	{
		$this->database->transaction(function() use ($unit) {
			if ($defaultUnit = $this->unitsRepo->findDefault()) {
				$defaultUnit->is_default = FALSE;
				$this->unitsRepo->update($defaultUnit);
			}

			$unit->is_default = TRUE;
			$this->unitsRepo->update($unit);
		});

		return TRUE;
	}
}