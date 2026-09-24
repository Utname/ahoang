<?php

namespace App\AdminModule\Presenters;

use BaseEntity;
use CurrenciesEntity;
use CurrenciesRepository;
use CurrenciesService;
use Nette\Application\UI\Form;
use ReflectionException;

class CurrenciesPresenter extends BaseModulePresenter
{
	/** @var CurrenciesRepository @inject */
	public $repository;

	/** @var CurrenciesService @inject */
	public $service;

	/** @var \ProductPricesService @inject */
	public $productPricesService;

	/** @var \PricesService @inject */
	public $pricesService;

	/** @var array */
	protected $decimalsPairs = [
		0 => 0,
		1 => 1,
		2 => 2
	];

	/** @var array */
	protected $roundingPairs = [];

	/** @var array */
	protected $symbolPositionPairs = [];

	/** @var array */
	protected $decimalsSeparatorPairs = [];

	/** @var array */
	protected $thousandsSeparatorPairs = [];

	/** @var array */
	protected $exchangeRateSourcesPairs = [];


	/**
	 * @throws ReflectionException
	 */
	protected function startup()
	{
		parent::startup();

		$this->roundingPairs = [
			CurrenciesEntity::ROUNDING_MATH => $this->trans('admin.rounding.mathematical'),
			CurrenciesEntity::ROUNDING_UP => $this->trans('admin.rounding.up'),
			CurrenciesEntity::ROUNDING_DOWN => $this->trans('admin.rounding.down')
		];

		$this->symbolPositionPairs = [
			CurrenciesEntity::SYMBOL_POSITION_AFTER => $this->trans('admin.currencySymbolPositions.after'),
			CurrenciesEntity::SYMBOL_POSITION_BEFORE => $this->trans('admin.currencySymbolPositions.before')
		];

		$this->decimalsSeparatorPairs = [
			CurrenciesEntity::DECIMALS_SEPARATOR_COMMA => $this->trans('admin.decimalsSeparators.comma'),
			CurrenciesEntity::DECIMALS_SEPARATOR_POINT => $this->trans('admin.decimalsSeparators.point')
		];

		$this->thousandsSeparatorPairs = [
			CurrenciesEntity::THOUSANDS_SEPARATOR_SPACE => $this->trans('admin.thousandsSeparators.space'),
			CurrenciesEntity::THOUSANDS_SEPARATOR_COMMA => $this->trans('admin.thousandsSeparators.comma'),
			CurrenciesEntity::THOUSANDS_SEPARATOR_POINT => $this->trans('admin.thousandsSeparators.point'),
			CurrenciesEntity::THOUSANDS_SEPARATOR_NONE => $this->trans('admin.thousandsSeparators.none'),
		];

		$this->exchangeRateSourcesPairs = [
			CurrenciesEntity::EXCHANGE_RATE_SOURCE_FIX => $this->trans('admin.exchangeRateSources.fix'),
			CurrenciesEntity::EXCHANGE_RATE_SOURCE_CNB => $this->trans('admin.exchangeRateSources.cnb')
		];
	}


	/**
	 *
	 */
	public function actionDefault()
	{
		$this['filters-form']->addText('name', 'admin.terms.name', 50, 255);
		$this['filters-form']->addText('iso_code_3', 'admin.terms.isoCode3', 50, 255);

		parent::actionDefault();
	}


	/**
	 * @param Form $form
	 * @return Form
	 */
	protected function setFormFields(Form $form): Form
	{
		$form->addText('name', 'admin.terms.name')->setRequired(TRUE);
		$form->addText('iso_code_3', 'admin.terms.isoCode3')->setRequired(TRUE);
		$form->addSelect('is_active', $this->trans('admin.terms.isActive'), $this->global['bool'])->setTranslator(NULL);
		$form->addSelect('decimals', $this->trans('admin.terms.decimals'), $this->decimalsPairs)->setTranslator(NULL);
		$form->addSelect('rounding', $this->trans('admin.terms.rounding'), $this->roundingPairs)->setTranslator(NULL);
		$form->addText('symbol', 'admin.terms.symbol');
		$form->addSelect('symbol_position', $this->trans('admin.terms.symbolPosition'), $this->symbolPositionPairs)->setTranslator(NULL);
		$form->addSelect('decimals_separator', $this->trans('admin.terms.decimalsSeparator'), $this->decimalsSeparatorPairs)->setTranslator(NULL);
		$form->addSelect('thousands_separator', $this->trans('admin.terms.thousandsSeparator'), $this->thousandsSeparatorPairs)->setTranslator(NULL);
		$form->addText('exchange_rate', 'admin.terms.exchangeRate');

		return parent::setFormFields($form);
	}

	/**
	 * @param BaseEntity $entity
	 * @param Form $form
	 */
	protected function afterEntitySave(BaseEntity $entity, Form $form): void
	{
		parent::afterEntitySave($entity, $form);

		$this->pricesService->recountPrices(onlyForCurrency: $entity);
	}



	/**
	 * @param string $itemId
	 */
	public function handleSetAsDefault(string $itemId)
	{
		/** @var CurrenciesEntity $currency */
		if (!$currency = $this->repository->findEntity($itemId)) {
			$this->flashMessage($this->trans('admin.messages.errorCurrencyDefault'), 'danger');
			$this->redirect('this');
		}

		if (!$this->service->setAsDefault($currency)) {
			$this->flashMessage($this->trans('admin.messages.errorCurrencyDefault'), 'danger');
			$this->redirect('this');
		}

		$this->flashMessage($this->trans('admin.messages.successCurrencyDefault'), 'success');
		$this->redirect('this');
	}
}
