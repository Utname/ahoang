<?php

use Cron\CronExpression;

class CronsService
{
	use \Nette\SmartObject;

	/**
	 * @param CronsRepository $cronsRepo
	 */
	public function __construct(protected CronsRepository $cronsRepo)
	{

	}

	/**
	 * @param CronsEntity $cronsEntity
	 * @return CronsEntity
	 * @throws Exception
	 */
	public function updateNextRunAt(CronsEntity $cronsEntity): CronsEntity
	{
		$nextRunAt = NULL;

		$expression = $cronsEntity->getExpression();
		if(CronExpression::isValidExpression($expression)){
			$cron = new CronExpression($expression);
			$nextRunAt = $cron->getNextRunDate();
		}


		$cronsEntity->next_run_at = $nextRunAt;
		$this->cronsRepo->update($cronsEntity);

		return $cronsEntity;
	}

	/**
	 * @param CronsEntity $cronsEntity
	 * @return CronsEntity
	 * @throws Exception
	 */
	public function updateLastRunAt(CronsEntity $cronsEntity): CronsEntity
	{
		$cronsEntity->last_run_at = date('Y-m-d H:i:s');
		$this->cronsRepo->update($cronsEntity);
		return $cronsEntity;
	}
}
