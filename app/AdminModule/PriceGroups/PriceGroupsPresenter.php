<?php

namespace App\AdminModule\Presenters;

use BaseEntity;
use CurrenciesRepository;
use Nette\Application\AbortException;
use Nette\Application\UI\Form;
use Nette\Utils\Strings;
use PriceGroupsEntity;
use PriceGroupsRepository;
use PriceGroupsService;
use ReflectionException;

class PriceGroupsPresenter extends BaseModulePresenter
{
	/** @var PriceGroupsRepository @inject */
	public $repository;

	/** @var PriceGroupsService @inject */
	public $service;

	/** @var array */
	protected $priceTypesPairs = [];

	/** @var array */
	protected $displayTypesPairs = [];

	/** @var CurrenciesRepository @inject */
	public $currenciesRepo;

	/** @var array */
	protected $currencies = [];

	/** @var \PricesService @inject */
	public $pricesService;


	/** @var array */
	protected $moduleBulkActions = [
		BulkActionsControl::ACTION_DELETE,
	];



	/**
	 * @throws ReflectionException
	 */
	protected function startup()
	{
		parent::startup();

		$this->template->priceTypesPairs = $this->priceTypesPairs = $this->global['price_types'];

		$this->template->displayTypesPairs = $this->displayTypesPairs = [
			PriceGroupsEntity::DISPLAY_TYPE_DEFAULT => $this->trans('admin.priceDisplayTypes.default'),
			PriceGroupsEntity::DISPLAY_TYPE_BOTH => $this->trans('admin.priceDisplayTypes.both')
		];

		$this->template->currencies = $this->currencies = $this->currenciesRepo->findAllEntities('pos_order');
	}


	/**
	 *
	 */
	public function actionDefault()
	{
		$this['filters-form']->addText('name', 'admin.terms.name');

		$this['filters-form']->addSelect('price_groups__default_price_types_id', 'admin.terms.defaultPriceType', $this->priceTypesPairs)
			->setPrompt('-')
			->setTranslator(NULL);

		$this['filters-form']->addSelect('price_groups__display_types_id', 'admin.terms.priceDisplayType', $this->displayTypesPairs)
			->setPrompt('-')
			->setTranslator(NULL);

		parent::actionDefault();
	}


	/**
	 * @param Form $form
	 * @return Form
	 */
	protected function setFormFields(Form $form): Form
	{
		$form->addText('name', 'admin.terms.name')->setRequired(TRUE);

		$form->addSelect('default_price_types_id', $this->trans('admin.terms.defaultPriceType'), $this->priceTypesPairs)
			->setTranslator(NULL);

		$form->addSelect('display_types_id', $this->trans('admin.terms.priceDisplayType'), $this->displayTypesPairs)
			->setTranslator(NULL);

		$form->addText('money_id', 'admin.terms.id');

		return parent::setFormFields($form);
	}


	/**
	 * @param BaseEntity $copyFromEntity
	 * @param BaseEntity $entity
	 * @param Form $form
	 */
	protected function afterEntityCopy(BaseEntity $copyFromEntity, BaseEntity $entity, Form $form)
	{
		parent::afterEntityCopy($copyFromEntity, $entity, $form);

		$this->service->copyPrices($copyFromEntity, $entity);
		//recount prices
		$this->pricesService->recountPrices(onlyForPriceGroup: $entity);
		//log prices history
		$this->pricesService->logPricesHistory(onlyForPriceGroup: $entity);
	}


	/**
	 * @param BaseEntity $entity
	 * @param Form $form
	 */
	protected function afterEntitySave(BaseEntity $entity, Form $form)
	{
		parent::afterEntitySave($entity, $form);

		$values = $form->getHttpData();

		//settings

		$this->service->resetSettings($entity);
		foreach($values as $key => $value){
			if(Strings::startsWith($key, 'auto_recount_by_default_currency__')){
				[$nothing, $currencyId] = explode('__', $key);
				$autoRecountExchangeRateCorrection = floatval($values['auto_recount_exchange_rate_correction__'.$currencyId] ?? 0);
				$this->service->setIsAutoRecount($entity, $this->currencies[$currencyId], $autoRecountExchangeRateCorrection);
			}
		}

		//recount prices
		$this->pricesService->recountPrices(onlyForPriceGroup: $entity);

		//log prices history
		$this->pricesService->logPricesHistory(onlyForPriceGroup: $entity);
	}


	/**
	 * @param string $itemId
	 * @return void
	 * @throws AbortException
	 */
	public function handleSetAsDefault(string $itemId)
	{
		/** @var PriceGroupsEntity $priceGroup */
		if (!$priceGroup = $this->repository->findEntity($itemId)) {
			$this->flashMessage($this->trans('admin.messages.errorDefault'), 'danger');
			$this->redirect('this');
		}

		if (!$this->service->setAsDefault($priceGroup)) {
			$this->flashMessage($this->trans('admin.messages.errorDefault'), 'danger');
			$this->redirect('this');
		}

		$this->flashMessage($this->trans('admin.messages.successDefault'), 'success');
		$this->redirect('this');
	}



	/**
	 * @return PricesBulkEditor
	 */
	public function createComponentPricesBulkEditor(): PricesBulkEditor
	{
		$control = new PricesBulkEditor($this->aps, $this->translator, $this->repository, $this->currenciesRepo, $this->pricesService, $this->database);

		$control->setDefaultPriceGroupIds($this->globalContainer->getDefaultPriceGroup() ? [$this->globalContainer->getDefaultPriceGroup()->id] : []);
		$control->setDefaultCurrencyIds($this->globalContainer->getDefaultCurrency() ? [$this->globalContainer->getDefaultCurrency()->id] : []);

		return $control;
	}
}
