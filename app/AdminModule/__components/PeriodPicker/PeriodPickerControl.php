<?php

namespace App\AdminModule\Presenters;

use Nette\Application\AbortException;
use Nette\Application\UI\Form;
use Nette\Http\SessionSection;
use Nette\Localization\Translator;
use Nette\Utils\Callback;
use Nette\Utils\Strings;

class PeriodPickerControl extends BaseControl
{
	const TYPE_YEAR = 1;
	const TYPE_HALFYEAR = 2;
	const TYPE_QUARTER = 3;
	const TYPE_MONTH = 4;
	const TYPE_WEEK = 5;
	const TYPE_DATE = 6;
	const TYPE_CUSTOM_PERIOD = 7;

	/** @var callable */
	protected $onPeriodChange;

	/** @var int[] */
	protected $allowedTypes = [
		self::TYPE_YEAR => self::TYPE_YEAR,
		self::TYPE_HALFYEAR => self::TYPE_HALFYEAR,
		self::TYPE_QUARTER => self::TYPE_QUARTER,
		self::TYPE_MONTH => self::TYPE_MONTH,
		self::TYPE_WEEK => self::TYPE_WEEK,
		self::TYPE_DATE => self::TYPE_DATE,
		self::TYPE_CUSTOM_PERIOD => self::TYPE_CUSTOM_PERIOD
	];

	/** @var int|null */
	protected $yearFrom = NULL;

	/** @var int|null */
	protected $yearTo = NULL;

	/** @var null|string */
	protected $dateFrom = NULL;

	/** @var null|string */
	protected $dateTo = NULL;

	/** @var null|int */
	protected $type = NULL;


	/**
	 * @param SessionSection $session
	 * @param Translator $translator
	 * @param string|null $dateFrom
	 * @param string|null $dateTo
	 * @param int|null $type
	 */
	public function __construct(
		protected SessionSection $session,
		protected Translator $translator,
		?string $dateFrom,
		?string $dateTo,
		?int $type)
	{

		$this->setYearFrom(date('Y') - 20);
		$this->setYearTo(date('Y') + 1);

		$this->setDateFrom($dateFrom);
		$this->setDateTo($dateTo);
		$this->setType($type);
	}


	/**
	 * Filter form.
	 * @return Form
	 */
	protected function createComponentForm(): Form
	{
		$form = new Form();

		$defaultDateFrom = $this->getDateFrom();
		$defaultDateTo = $this->getDateTo();

		//===========YEAR
		$years = array_combine(range($this->getYearTo(), $this->getYearFrom()), range($this->getYearTo(), $this->getYearFrom()));
		$form->addSelect('input_1_'.self::TYPE_YEAR, $this->translator->translate('admin.globals.periodPickerAllowdTypes.'.self::TYPE_YEAR), $years)
			->setDefaultValue($defaultDateFrom ? date('Y', strtotime($defaultDateFrom)) : date('Y'));

		//===========YEAR AND HALFS
		$halfYears = [1 => 'I.', 2 => 'II.'];
		$form->addSelect('input_1_'.self::TYPE_HALFYEAR, $this->translator->translate('admin.globals.periodPickerAllowdTypes.'.self::TYPE_YEAR), $years)
			->setDefaultValue($defaultDateFrom ? date('Y', strtotime($defaultDateFrom)) : date('Y'));

		$defaultHalfYear = $defaultDateFrom ? date('n', strtotime($defaultDateFrom)) : date('n');
		if ($defaultHalfYear) {
			$defaultHalfYear = ceil($defaultHalfYear / 6);
		}

		$form->addSelect('input_2_'.self::TYPE_HALFYEAR, $this->translator->translate('admin.globals.periodPickerAllowdTypes.'.self::TYPE_HALFYEAR), $halfYears)
			->setDefaultValue($defaultHalfYear ? $defaultHalfYear : NULL);

		//===========YEAR AND QUARTERS
		$quaters = [1 => 'I.', 2 => 'II.', 3 => 'III.', 4 => 'IV.'];
		$form->addSelect('input_1_'.self::TYPE_QUARTER, $this->translator->translate('admin.globals.periodPickerAllowdTypes.'.self::TYPE_YEAR), $years)
			->setDefaultValue($defaultDateFrom ? date('Y', strtotime($defaultDateFrom)) : date('Y'));

		$defaultQuaterYear = $defaultDateFrom ? date('n', strtotime($defaultDateFrom)) : date('n');
		if ($defaultQuaterYear) {
			$defaultQuaterYear = ceil($defaultQuaterYear / 3);
		}

		$form->addSelect('input_2_'.self::TYPE_QUARTER, $this->translator->translate('admin.globals.periodPickerAllowdTypes.'.self::TYPE_QUARTER), $quaters)
			->setDefaultValue($defaultQuaterYear ? $defaultQuaterYear : NULL);

		//===========YEAR AND MONTHS
		$months = [];
		for($i = 1; $i <= 12; $i++){
			$months[$i] = $this->translator->translate('admin.monthNames.'.$i);
		}
		$form->addSelect('input_1_'.self::TYPE_MONTH, $this->translator->translate('admin.globals.periodPickerAllowdTypes.'.self::TYPE_YEAR), $years)
			->setDefaultValue($defaultDateFrom ? date('Y', strtotime($defaultDateFrom)) : date('Y'));
		$form->addSelect('input_2_'.self::TYPE_MONTH, $this->translator->translate('admin.globals.periodPickerAllowdTypes.'.self::TYPE_MONTH), $months)
			->setDefaultValue($defaultDateFrom ? date('n', strtotime($defaultDateFrom)) : date('n'));

		//===========YEAR AND WEEKS
		$weeks = array_combine(range(1, 53), range(1, 53));
		$form->addSelect('input_1_'.self::TYPE_WEEK, $this->translator->translate('admin.globals.periodPickerAllowdTypes.'.self::TYPE_YEAR), $years)
			->setDefaultValue($defaultDateFrom ? date('Y', strtotime($defaultDateFrom)) : date('Y'));
		$form->addSelect('input_2_'.self::TYPE_WEEK, $this->translator->translate('admin.globals.periodPickerAllowdTypes.'.self::TYPE_WEEK), $weeks)
			->setDefaultValue($defaultDateFrom ? ltrim(date('W', strtotime($defaultDateFrom)), 0) : ltrim(date('W'), 0));

		//===========DATE
		$form->addText('input_1_'.self::TYPE_DATE, $this->translator->translate('admin.globals.periodPickerAllowdTypes.'.self::TYPE_DATE))
			->setDefaultValue($defaultDateFrom ? date('d.m.Y', strtotime($defaultDateFrom)) : date('d.m.Y'));

		//===========CUSTOM PERIOD
		$form->addText('input_1_'.self::TYPE_CUSTOM_PERIOD, $this->translator->translate('admin.terms.from'))
			->setDefaultValue($defaultDateFrom ? date('d.m.Y', strtotime($defaultDateFrom)) : NULL);
		$form->addText('input_2_'.self::TYPE_CUSTOM_PERIOD, $this->translator->translate('admin.terms.to'))
			->setDefaultValue($defaultDateTo ? date('d.m.Y', strtotime($defaultDateTo)) : NULL);

		//===========TYPE
		$allowedTypes = $this->getAllowedTypes();
		$form->addHidden('selected_type')
			->setDefaultValue($this->getType() ?: reset($allowedTypes));

		$form->addSubmit('submit', 'filter');
		$form->onSuccess[] = [$this, 'formSubmitted'];

		return $form;
	}


	/**
	 * @param Form $form
	 * @throws AbortException
	 */
	public function formSubmitted(Form $form)
	{
		$values = $form->getValues();

		$dateFrom = NULL;
		$dateTo = NULL;
		$type = $values['selected_type'];

		if ($type == self::TYPE_YEAR) {
			$startMonth = 1;
			$endMonth = 12;

			$dateFrom = date('Y-m-01', strtotime($values['input_1_'.$type].'-'.$startMonth));
			$dateTo = date('Y-m-t', strtotime($values['input_1_'.$type].'-'.$endMonth));
		}
		if ($type == self::TYPE_HALFYEAR) {
			$startMonth = (($values['input_2_'.$type] * 6) - 6) + 1;
			$endMonth = $values['input_2_'.$type] * 6;

			$dateFrom = date('Y-m-01', strtotime($values['input_1_'.$type].'-'.$startMonth));
			$dateTo = date('Y-m-t', strtotime($values['input_1_'.$type].'-'.$endMonth));
		}
		if ($type == self::TYPE_QUARTER) {
			$startMonth = (($values['input_2_'.$type] * 3) - 3) + 1;
			$endMonth = $values['input_2_'.$type] * 3;

			$dateFrom = date('Y-m-01', strtotime($values['input_1_'.$type].'-'.$startMonth));
			$dateTo = date('Y-m-t', strtotime($values['input_1_'.$type].'-'.$endMonth));
		}
		if ($type == self::TYPE_MONTH) {
			$startMonth = $values['input_2_'.$type];
			$endMonth = $values['input_2_'.$type];

			$dateFrom = date('Y-m-01', strtotime($values['input_1_'.$type].'-'.$startMonth));
			$dateTo = date('Y-m-t', strtotime($values['input_1_'.$type].'-'.$endMonth));
		}
		if ($type == self::TYPE_WEEK) {
			$dateFrom = date('Y-m-j', strtotime($values['input_1_'.$type].'W'.sprintf('%02d', $values['input_2_'.$type])));
			$dateTo = date('Y-m-j', strtotime($values['input_1_'.$type].'W'.sprintf('%02d', $values['input_2_'.$type]).'7'));
		}
		if ($type == self::TYPE_DATE) {
			$dateFrom = date('Y-m-d', strtotime($values['input_1_'.$type]));
			$dateTo = date('Y-m-d', strtotime($values['input_1_'.$type]));
		}
		if ($type == self::TYPE_CUSTOM_PERIOD) {
			$dateFrom = $values['input_1_'.$type] ? date('Y-m-d', strtotime($values['input_1_'.$type])) : NULL;
			$dateTo = $values['input_2_'.$type] ? date('Y-m-d', strtotime($values['input_2_'.$type])): NULL;
		}

		$this->setPeriod($dateFrom, $dateTo, $type);
	}


	/**
	 * @return bool
	 */
	public function isPeriodSet(): bool
	{
		return $this->getType() && (($this->getDateFrom() && $this->getDateTo()) || ($this->getType() == self::TYPE_CUSTOM_PERIOD && ($this->getDateFrom() || $this->getDateTo())));
	}


	/**
	 * @throws AbortException
	 */
	public function handleNext()
	{
		[$dateFrom, $dateTo] = $this->getDatesModified($this->getDateFrom(), $this->getDateTo(), '+');
		$this->setPeriod($dateFrom, $dateTo, $this->getType());
	}


	/**
	 * @throws AbortException
	 */
	public function handlePrev()
	{
		[$dateFrom, $dateTo] = $this->getDatesModified($this->getDateFrom(), $this->getDateTo(), '-');
		$this->setPeriod($dateFrom, $dateTo, $this->getType());
	}


	/**
	 * @throws AbortException
	 */
	public function handleReset()
	{
		$this->setPeriod(NULL, NULL, NULL);
	}


	/**
	 * @return bool
	 */
	public function isHandleNextValid(): bool
	{
		if($this->getType() == self::TYPE_CUSTOM_PERIOD){
			return FALSE;
		}
		[$yearFrom, $yearTo] = $this->getDatesModified($this->getDateFrom(), $this->getDateTo(), '+', 'Y');

		return ($yearFrom != NULL && $yearFrom <= $this->getYearTo());
	}


	/**
	 * @return bool
	 */
	public function isHandlePrevValid(): bool
	{
		if($this->getType() == self::TYPE_CUSTOM_PERIOD){
			return FALSE;
		}
		[$yearFrom, $yearTo] = $this->getDatesModified($this->getDateFrom(), $this->getDateTo(), '-', 'Y');

		return ($yearFrom != NULL && $yearFrom >= $this->getYearFrom());
	}


	/**
	 * @param string|null $dateFrom
	 * @param string|null $dateTo
	 * @param string $operator
	 * @param string $format
	 * @return null[]|string[]
	 */
	public function getDatesModified(?string $dateFrom, ?string $dateTo, string $operator, string $format = 'Y-m-d')
	{
		if ($this->getType() == self::TYPE_YEAR) {
			$dateFrom = date($format, strtotime('first day of '.$operator.' 1 year', strtotime($dateFrom)));
			$dateTo = date($format, strtotime('last day of '.$operator.' 1 year', strtotime($dateTo)));
		}
		if ($this->getType() == self::TYPE_HALFYEAR) {
			$dateFrom = date($format, strtotime('first day of '.$operator.' 6 months', strtotime($dateFrom)));
			$dateTo = date($format, strtotime('last day of '.$operator.' 6 months', strtotime($dateTo)));
		}
		if ($this->getType() == self::TYPE_QUARTER) {
			$dateFrom = date($format, strtotime('first day of '.$operator.' 3 months', strtotime($dateFrom)));
			$dateTo = date($format, strtotime('last day of '.$operator.' 3 months', strtotime($dateTo)));
		}
		if ($this->getType() == self::TYPE_MONTH) {
			$dateFrom = date($format, strtotime('first day of '.$operator.' 1 month', strtotime($dateFrom)));
			$dateTo = date($format, strtotime('last day of '.$operator.' 1 month', strtotime($dateTo)));
		}
		if ($this->getType() == self::TYPE_WEEK) {
			$dateFrom = date($format, strtotime($operator.'1 week', strtotime($dateFrom)));
			$dateTo = date($format, strtotime($operator.'1 week', strtotime($dateTo)));
		}
		if ($this->getType() == self::TYPE_DATE) {
			$dateFrom = date($format, strtotime($operator.' 1 day', strtotime($dateFrom)));
			$dateTo = date($format, strtotime($operator.' 1 day', strtotime($dateTo)));
		}
		if ($this->getType() == self::TYPE_CUSTOM_PERIOD) {
			$dateFrom = $dateFrom ? date($format, strtotime($operator.' 1 day', strtotime($dateFrom))) : NULL;
			$dateTo = $dateTo ? date($format, strtotime($operator.' 1 day', strtotime($dateTo))) : NULL;
		}
		return [$dateFrom, $dateTo];
	}


	/**
	 * @param string|null $dateFrom
	 * @param string|null $dateTo
	 * @param int|null $type
	 * @throws AbortException
	 */
	public function setPeriod(?string $dateFrom, ?string $dateTo, ?int $type)
	{
		if ($callback = $this->onPeriodChange) {
			$callback($dateFrom, $dateTo, $type);
		}
		else {
			$this->getPresenter()->redirect('this');
		}
	}


	/**
	 * @return array
	 */
	public function getAllowedTypesPrepared(): array
	{
		$preparedAllowdTypes = [];
		foreach ($this->getAllowedTypes() as $typesId) {
			$preparedAllowdTypes[$typesId] = (object)[
				'id' => $typesId,
				'name' => $this->translator->translate('admin.globals.periodPickerAllowdTypes.'.$typesId),
			];
		}

		return $preparedAllowdTypes;
	}


	/**
	 * @return string
	 */
	public function getInputValueFormatted(): string
	{
		$value = '';
		if ($this->isPeriodSet()) {
			if ($this->getType() == self::TYPE_YEAR) {
				$value = date('Y', strtotime($this->getDateFrom()));
			}
			if ($this->getType() == self::TYPE_HALFYEAR) {
				$month = date('n', strtotime($this->getDateFrom()));
				$yearPart = round($month / 6) + 1;
				$value = $yearPart.'/2 '.date('Y', strtotime($this->getDateFrom()));
			}
			if ($this->getType() == self::TYPE_QUARTER) {
				$month = date('n', strtotime($this->getDateFrom()));
				$yearPart = round($month / 3) + 1;
				$value = $yearPart.'/4 '.date('Y', strtotime($this->getDateFrom()));
			}
			if ($this->getType() == self::TYPE_MONTH) {
				$month = date('n', strtotime($this->getDateFrom()));
				$monthName = $this->translator->translate('admin.monthNames.'.$month);
				$value = $monthName.' '.date('Y', strtotime($this->getDateFrom()));
			}
			if ($this->getType() == self::TYPE_WEEK) {
				$week = date('W', strtotime($this->getDateFrom()));
				$value = $week.'. '.$this->translator->translate('admin.terms.week').' '.date('Y', strtotime($this->getDateFrom()));
			}
			if ($this->getType() == self::TYPE_DATE) {
				$value = date('d.m.Y', strtotime($this->getDateFrom()));
			}
			if ($this->getType() == self::TYPE_CUSTOM_PERIOD) {
				$parts = [];
				if($this->getDateFrom()){
					$parts[] = Strings::lower($this->translator->translate('admin.terms.from')).' '.date('d.m.Y', strtotime($this->getDateFrom()));
				}
				if($this->getDateTo()){
					$parts[] = Strings::lower($this->translator->translate('admin.terms.to')).' '.date('d.m.Y', strtotime($this->getDateTo()));
				}
				$value = implode(' ', $parts);
			}
		}

		return $value;
	}


	/**
	 * @return string
	 */
	public function getInputValuePeriodFormatted(): string
	{
		$value = '';
		if ($this->isPeriodSet())
		{
			$parts = [];
			if($this->getDateFrom()){
				$parts[] = Strings::lower($this->translator->translate('admin.terms.from')).' '.date('d.m.Y', strtotime($this->getDateFrom()));
			}
			if($this->getDateTo()){
				$parts[] = Strings::lower($this->translator->translate('admin.terms.to')).' '.date('d.m.Y', strtotime($this->getDateTo()));
			}
			$value = implode(' ', $parts);
		}
		return $value;
	}

	/**
	 * @param $params
	 * @return void
	 */
	public function renderInput($params = [])
	{
		parent::render($params);

		$this->template->setFile(__DIR__.'/PeriodPickerControlInput.latte');
		$this->template->render();
	}

	/**
	 * @return int[]
	 */
	public function getAllowedTypes(): array
	{
		return $this->allowedTypes;
	}


	/**
	 * @param int[] $allowedTypes
	 */
	public function setAllowedTypes(array $allowedTypes): void
	{
		$this->allowedTypes = $allowedTypes;
	}


	/**
	 * @return callable
	 */
	public function getOnPeriodChange(): callable
	{
		return $this->onPeriodChange;
	}


	/**
	 * @param callable $onPeriodChange
	 */
	public function setOnPeriodChange(callable $onPeriodChange): void
	{
		$this->onPeriodChange = $onPeriodChange;
	}


	/**
	 * @return string|null
	 */
	public function getDateFrom(): ?string
	{
		return $this->dateFrom;
	}


	/**
	 * @param string|null $dateFrom
	 */
	public function setDateFrom(?string $dateFrom): void
	{
		$this->dateFrom = $dateFrom;
	}


	/**
	 * @return string|null
	 */
	public function getDateTo(): ?string
	{
		return $this->dateTo;
	}


	/**
	 * @param string|null $dateTo
	 */
	public function setDateTo(?string $dateTo): void
	{
		$this->dateTo = $dateTo;
	}


	/**
	 * @return int|null
	 */
	public function getType(): ?int
	{
		return $this->type;
	}


	/**
	 * @param int|null $type
	 */
	public function setType(?int $type): void
	{
		$this->type = $type;
	}


	/**
	 * @return int|null
	 */
	public function getYearFrom(): ?int
	{
		return $this->yearFrom;
	}


	/**
	 * @param int|null $yearFrom
	 */
	public function setYearFrom(?int $yearFrom): void
	{
		$this->yearFrom = $yearFrom;
	}


	/**
	 * @return int|null
	 */
	public function getYearTo(): ?int
	{
		return $this->yearTo;
	}


	/**
	 * @param int|null $yearTo
	 */
	public function setYearTo(?int $yearTo): void
	{
		$this->yearTo = $yearTo;
	}
}
