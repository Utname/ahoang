<?php


class CurrenciesService
{
	use \Nette\SmartObject;

	/**
	 * CurrenciesService constructor.
	 * @param CurrenciesRepository $currenciesRepo
	 * @param \Nette\Database\Explorer $database
	 */
	public function __construct(protected CurrenciesRepository $currenciesRepo, protected \Nette\Database\Explorer $database)
	{
	}


	/**
	 * @param CurrenciesEntity $currency
	 * @return bool
	 */
	public function setAsDefault(CurrenciesEntity $currency): bool
	{
		$this->database->transaction(function() use ($currency) {
			$currency->is_default = TRUE;

			if ($defaultCurrency = $this->currenciesRepo->findDefault()) {
				$defaultCurrency->is_default = FALSE;
			}

			// change rates
			if ($defaultCurrency && $currency->exchange_rate && $defaultCurrency->exchange_rate) {
				$ratio = $defaultCurrency->exchange_rate / $currency->exchange_rate;

				$currency->exchange_rate = 1;
				$defaultCurrency->exchange_rate *= $ratio;

				// other currencies
				$otherCurrencies = $this->currenciesRepo->getEntities($this->currenciesRepo->findAll()
					->where('id != ?', $currency->id)
					->where('id != ?', $defaultCurrency->id));

				foreach ($otherCurrencies as $otherCurrency) {
					$otherCurrency->exchange_rate *= $ratio;
					$this->currenciesRepo->update($otherCurrency);
				}
			}

			$this->currenciesRepo->update($currency);

			if ($defaultCurrency) {
				$this->currenciesRepo->update($defaultCurrency);
			}
		});

		return TRUE;
	}


	/**
	 * @param float $price
	 * @param CurrenciesEntity|null $currency
	 * @param int|null $forcedDecimals
	 * @return string
	 */
	public static function formatPrice(?float $price, CurrenciesEntity $currency = NULL, int $forcedDecimals = NULL): string
	{
		if (!$currency && isset($GLOBALS['container'])) {
			$currency = $GLOBALS['container']->getCurrency();
		}

		if (!$currency) {
			return number_format($price, 2, self::getDecimalsSeparator(), self::getDecimalsSeparator());
		}

		$decimals = $forcedDecimals ?? $currency->decimals;

		// rounding
		$price *= pow(10, $decimals);

		if ($currency->rounding === CurrenciesEntity::ROUNDING_UP) {
			$price = ceil($price);
		}
		elseif ($currency->rounding === CurrenciesEntity::ROUNDING_DOWN) {
			$price = floor($price);
		}
		else {
			$price = round($price);
		}

		$price /= pow(10, $decimals);

		// formatting
		$formatted = number_format($price, $decimals, self::getDecimalsSeparator($currency), self::getThousandsSeparator($currency));

		if ($currency->symbol && $currency->symbol_position === CurrenciesEntity::SYMBOL_POSITION_AFTER) {
			$formatted = $formatted.' '.$currency->symbol;
		}
		elseif ($currency->symbol && $currency->symbol_position === CurrenciesEntity::SYMBOL_POSITION_BEFORE) {
			$formatted = $currency->symbol.' '.$formatted;
		}

		return $formatted;
	}


	/**
	 * @param CurrenciesEntity|null $currency
	 * @return string
	 */
	public static function getDecimalsSeparator(CurrenciesEntity $currency = NULL): string
	{
		if (!$currency && isset($GLOBALS['container'])) {
			$currency = $GLOBALS['container']->getCurrency();
		}

		if (!$currency) {
			return ',';
		}

		$separators = [
			CurrenciesEntity::DECIMALS_SEPARATOR_COMMA => ',',
			CurrenciesEntity::DECIMALS_SEPARATOR_POINT => '.'
		];

		return $separators[$currency->decimals_separator] ?? '?';
	}


	/**
	 * @param CurrenciesEntity|null $currency
	 * @return string
	 */
	public static function getThousandsSeparator(CurrenciesEntity $currency = NULL): string
	{
		if (!$currency && isset($GLOBALS['container'])) {
			$currency = $GLOBALS['container']->getCurrency();
		}

		if (!$currency) {
			return ' ';
		}

		$separators = [
			CurrenciesEntity::THOUSANDS_SEPARATOR_SPACE => ' ',
			CurrenciesEntity::THOUSANDS_SEPARATOR_COMMA => ',',
			CurrenciesEntity::THOUSANDS_SEPARATOR_POINT => '.',
			CurrenciesEntity::THOUSANDS_SEPARATOR_NONE => ''
		];

		return $separators[$currency->thousands_separator] ?? ' ';
	}


	/**
	 * @return int
	 */
	public function updateExchangeRatesFromCnb(): int
	{
		$historySets = [];
		$updatedCount = 0;
		$lines = @file("http://www.cnb.cz/cs/financni_trhy/devizovy_trh/kurzy_devizoveho_trhu/denni_kurz.txt");
		if(is_array($lines))
		{
			array_shift($lines);//remove date
			array_shift($lines);//remove column names

			if($curremciesSelection = $this->currenciesRepo->findAll()->where('exchange_rate_source', \CurrenciesEntity::EXCHANGE_RATE_SOURCE_CNB)){
				$currencyEntitiesByIsoCodes = $this->currenciesRepo->getEntities($curremciesSelection, 'iso_code_3');

				foreach($lines as $line)
				{
					[$country, $name, $amount, $isoCode3, $rate] = explode('|', $line);

					/** @var CurrenciesEntity|null $currenciesEntity */
					$currenciesEntity = $currencyEntitiesByIsoCodes[$isoCode3] ?? NULL;
					if(!is_null($currenciesEntity)){
						$rate = str_replace(',', '.', $rate);
						$exchangeRateValue = $rate / $amount;
						$currenciesEntity->setValue('exchange_rate', $exchangeRateValue);
						$this->currenciesRepo->update($currenciesEntity);
						$historySets[] = [
							'date' => date('d.m.Y'),
							'currencies_id' => $currenciesEntity->id,
							'exchange_rate' => $exchangeRateValue,
						];
						$updatedCount++;
					}
				}
			}
		}
		if(!empty($historySets)){
			$this->database->table('exchange_rates_history')->insert($historySets);
		}
		return $updatedCount;
	}


	/**
	 * @return int
	 */
	public function logExchangeRates(): int
	{
		$sets = [];

		foreach($this->currenciesRepo->findAll()->where('is_default = 0') as $currencyRow){
			$sets[] = [
				'date' => date('Y-m-d'),
				'currencies_id' => $currencyRow->id,
				'exchange_rate' => $currencyRow->exchange_rate,
			];
		}

		if(!empty($sets)){
			$this->database->table('exchange_rates_history')->insert($sets);
		}
		return count($sets);
	}
}
