<?php


class PriceFilter
{
    /**
     * @param float|null $price
     * @param int $decimals
     * @return string
     */
    public function process(?float $price, int $decimals = 2): string
    {
        /** @var GlobalContainer $globalContainer */
        if (!$globalContainer = $GLOBALS['container']) {
            return number_format(floatval($price), $decimals, ',', ' ');
        }

        return CurrenciesService::formatPrice($price, $globalContainer->getCurrency(), $decimals);
    }
}
