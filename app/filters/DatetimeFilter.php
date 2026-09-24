<?php


class DatetimeFilter
{
	/**
	 * @param DateTime|null $datetime
	 * @return string
	 */
	public function process(?\DateTime $datetime, bool $showTime = TRUE): string
	{
		if (!$datetime) {
			return '';
		}

		$date = $datetime->format('d.m.Y');

		if ($date === date('d.m.Y')) {
			$date = 'Dnes';
		}
		elseif ($date === date('d.m.Y', strtotime('-1 day'))) {
			$date = 'Včera';
		}
		elseif ($date === date('d.m.Y', strtotime('+1 day'))) {
			$date = 'Zítra';
		}

		return $date.($showTime ? ' '.$datetime->format('H:i') : '');
	}
}