<?php


class MonthFilter
{
    /**
     * @param string $date
     * @return string
     * @throws Exception
     */
    public function process(string $date): string
    {
        $months = [1 => 'Leden', 'Únor', 'Březen', 'Duben', 'Květen', 'Červen', 'Červenec', 'Srpen', 'Září', 'Říjen', 'Listipad', 'Prosinec'];
        return $months[(int)date('m', strtotime($date))];
    }
}
