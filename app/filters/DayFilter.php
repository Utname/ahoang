<?php


class DayFilter
{
    /**
     * @param string $date
     * @return string
     * @throws Exception
     */
    public function process(string $date): string
    {
        $days = [
	        'Neděle',
	        'Pondělí',
	        'Úterý',
	        'Středa',
	        'Čtvrtek',
	        'Pátek',
	        'Sobota',
        ];
        return $days[(int)date('w', strtotime($date))];
    }
}
