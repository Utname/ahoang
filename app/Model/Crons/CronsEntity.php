<?php

use Cron\CronExpression;

class CronsEntity extends BaseEntity
{
	const PART_TYPE_EVERYTIME = 1;
	const PART_TYPE_FROM_VALUES = 2;
	const PART_TYPE_RANGE = 3;
	const PART_TYPE_CUSTOM_DEFINITION = 4;


	const CRON_PART_DEFINITIONS = [
		'min' => [
			'title' => 'admin.terms.cron_part_min',
			'defaultType' => self::PART_TYPE_FROM_VALUES,
			'defaultValue' => '0',
			'rangeFrom' => 0,
			'rangeTo' => 59,
			'customOptions' => [
				'*/5' => 'admin.terms.cron_part_min_repeat_after_5',
				'*/10' => 'admin.terms.cron_part_min_repeat_after_10',
			],
			'padding' => 2,
			'localizationArray' => NULL
		],
		'hour' => [
			'title' => 'admin.terms.cron_part_hour',
			'defaultType' => self::PART_TYPE_FROM_VALUES,
			'defaultValue' => '12',
			'rangeFrom' => 0,
			'rangeTo' => 23,
			'customOptions' => [
				'*/2' => 'admin.terms.cron_part_hour_repeat_after_2',
				'*/3' => 'admin.terms.cron_part_hour_repeat_after_3',
			],
			'padding' => 2,
			'localizationArray' => NULL
		],
		'day_of_month' => [
			'title' => 'admin.terms.cron_part_day_of_month',
			'defaultType' => self::PART_TYPE_EVERYTIME,
			'defaultValue' => NULL,
			'rangeFrom' => 1,
			'rangeTo' => 31,
			'customOptions' => [],
			'padding' => 2,
			'localizationArray' => NULL
		],
		'month' => [
			'title' => 'admin.terms.cron_part_month',
			'defaultType' => self::PART_TYPE_EVERYTIME,
			'defaultValue' => NULL,
			'rangeFrom' => 1,
			'rangeTo' => 12,
			'customOptions' => [],
			'padding' => NULL,
			'localizationArray' => 'admin.monthNames.'
		],
		'day_of_week' => [
			'title' => 'admin.terms.cron_part_day_of_week',
			'defaultType' => self::PART_TYPE_EVERYTIME,
			'defaultValue' => NULL,
			'rangeFrom' => 1,
			'rangeTo' => 7,
			'customOptions' => [],
			'padding' => NULL,
			'localizationArray' => 'admin.weekDays.'
		],
	];


	/**
	 * @param int $limit
	 * @return array
	 */
	public function getPlannedRunnings(int $limit = 5): array
	{
		if($this->isValidExpression()){
			$cron = new CronExpression($this->getExpression());
			return $cron->getMultipleRunDates($limit);
		}
		return [];
	}


	/**
	 * @param $formatInvalid
	 * @return string
	 */
	public function getExpression($formatInvalid = FALSE): string
	{
		$parts = [];
		foreach(self::CRON_PART_DEFINITIONS as $expressionPartName => $expressionPartSettings){
			$parts[] = $this->getExpressionPart($expressionPartName, $formatInvalid);
		}
		return implode(' ', $parts);
	}


	/**
	 * @param string $partName
	 * @param $formatInvalid
	 * @return string
	 */
	public function getExpressionPart(string $partName, $formatInvalid = FALSE): string
	{
		$value = '?';
		//? is for invalid
		switch($this->{'part_'.$partName.'_type'}){
			case self::PART_TYPE_EVERYTIME:{
				$value = '*';
				break;
			}
			case self::PART_TYPE_FROM_VALUES:{
				$value = $this->{'part_'.$partName.'_value'} != '' ? $this->{'part_'.$partName.'_value'} : '?';
				break;
	        }
			case self::PART_TYPE_RANGE:{
				if($this->{'part_'.$partName.'_range_from'} != '' && $this->{'part_'.$partName.'_range_to'} != ''){
					$value = $this->{'part_'.$partName.'_range_from'}.'-'.$this->{'part_'.$partName.'_range_to'};
					break;
				}
				$value = '?';
				break;
	        }
			case self::PART_TYPE_CUSTOM_DEFINITION:{
				$value = $this->{'part_'.$partName.'_custom_value'} != '' ? $this->{'part_'.$partName.'_custom_value'} : '?';
				break;
	        }
		}

		if($formatInvalid && $value == '?'){
			return '<strong class="text-danger">'.$value.'</strong>';
		}

		return $value;
	}


	/**
	 * @return bool
	 */
	public function isValidExpression(): bool
	{
		$expression = $this->getExpression();
		return CronExpression::isValidExpression($expression);
	}
}
