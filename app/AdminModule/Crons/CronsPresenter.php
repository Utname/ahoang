<?php

namespace App\AdminModule\Presenters;

use BaseEntity;
use Cron\CronExpression;
use Nette\Application\UI\Form;
use Nette\Utils\Random;
use Nette\Utils\Strings;
use Nette\Utils\UnknownImageFileException;

class CronsPresenter extends BaseModulePresenter
{
	/** @var \CronsRepository @inject */
	public $repository;

	/** @var \CronsService @inject */
	public $service;

	/** @var \PagesRepository @inject */
	public $pagesRepo;

	/** @var array  */
	protected $types = [];


	/**
	 * @return void
	 * @throws \ReflectionException
	 */
	public function startup()
	{
		parent::startup();
		$this->types = [
			\CronsEntity::PART_TYPE_EVERYTIME => $this->trans('admin.cronTypes.'.\CronsEntity::PART_TYPE_EVERYTIME),
			\CronsEntity::PART_TYPE_FROM_VALUES => $this->trans('admin.cronTypes.'.\CronsEntity::PART_TYPE_FROM_VALUES),
			\CronsEntity::PART_TYPE_RANGE => $this->trans('admin.cronTypes.'.\CronsEntity::PART_TYPE_RANGE),
			\CronsEntity::PART_TYPE_CUSTOM_DEFINITION => $this->trans('admin.cronTypes.'.\CronsEntity::PART_TYPE_CUSTOM_DEFINITION),
		];
	}

	/**
	 *
	 */
	public function actionDefault()
	{
		$this['filters-form']->addText('name', 'admin.terms.name');

		parent::actionDefault();
	}


	/**
	 * @param Form $form
	 * @return Form
	 */
	protected function setFormFields(Form $form): Form
	{
		$form->addText('name', 'admin.terms.name')->setRequired(TRUE);
		$form->addSelect('is_active', $this->trans('admin.terms.isActive'), $this->global['bool'])->setTranslator(NULL);
		$form->addTextArea('note', 'admin.terms.note');
		$form->addText('command', 'admin.terms.command')->setRequired(TRUE);

		foreach(\CronsEntity::CRON_PART_DEFINITIONS as $expressionPartName => $expressionPartSettings){
			$this->addCronPartInputs($form, $expressionPartName, $expressionPartSettings);
		}

		return parent::setFormFields($form);
	}


	/**
	 * @param Form $form
	 * @param string $expressionPartName
	 * @param array $expressionPartSettings
	 * @return void
	 */
	public function addCronPartInputs(Form $form, string $expressionPartName, array $expressionPartSettings): void
	{
		//type select
		$form->addSelect('part_'.$expressionPartName.'_type', 'admin.terms.repeatType', $this->types)->setDefaultValue($expressionPartSettings['defaultType']);

		//value
		$options = [];
		for($i = $expressionPartSettings['rangeFrom']; $i <= $expressionPartSettings['rangeTo']; $i++){
			if($expressionPartSettings['padding']){
				$options[$i] = sprintf('%0'.$expressionPartSettings['padding'].'d', $i);
			}
			elseif($expressionPartSettings['localizationArray']){
				$options[$i] = $this->trans($expressionPartSettings['localizationArray'].$i);
			}
			else{
				$options[$i] = $i;
			}
		}

		$optionsWithCustomOptions = [];
		foreach($expressionPartSettings['customOptions'] as $optionKey => $optionVal){
			$optionsWithCustomOptions[$optionKey] = $optionVal;
		}
		foreach($options as $optionKey => $optionVal){
			$optionsWithCustomOptions[$optionKey] = $optionVal;
		}

		$form->addMultiSelect('part_'.$expressionPartName.'_value', 'admin.cronTypes.'.\CronsEntity::PART_TYPE_FROM_VALUES, $optionsWithCustomOptions)
			->setDefaultValue($expressionPartSettings['defaultValue']);

		//range
		$form->addSelect('part_'.$expressionPartName.'_range_from', 'admin.terms.from', $options);
		$form->addSelect('part_'.$expressionPartName.'_range_to', 'admin.terms.to', $options);

		//custom value
		$form->addText('part_'.$expressionPartName.'_custom_value', 'admin.terms.value');
	}

	/**
	 * @return array
	 */
	public function getDefaultFormValues(): array
	{
		$values = parent::getDefaultFormValues();
		foreach($values as $key => $val){
			if(Strings::startsWith($key, 'part_')){
				if(Strings::endsWith($key, '_value') && !Strings::endsWith($key, '_custom_value') && $val){
					$values[$key] = explode(',', $val);
				}
				if(!$values[$key]){
					unset($values[$key]);
				}
			}
		}

		return $values;
	}


	/**
	 * @param Form $form
	 * @param string $action
	 * @return array
	 * @throws UnknownImageFileException
	 */
	public static function getEditFormValues(Form $form, string $action): array
	{
		$values = parent::getEditFormValues($form, $action);
		foreach($values as $key => $value){
			if(Strings::startsWith($key, 'part_')){
				if(is_array($value)){
					$values[$key] = implode(',', $value);
				}
				else{
					$values[$key] = $value;
				}
			}
		}
		return $values;
	}


	/**
	 * @param BaseEntity $entity
	 * @param Form $form
	 * @return void
	 * @throws \Exception
	 */
	protected function afterEntitySave(BaseEntity $entity, Form $form)
	{
		parent::afterEntitySave($entity, $form);

		/** @var \CronsEntity $cronsEntity */
		$cronsEntity = $entity;

		if (!$cronsEntity->hash) {
			$cronsEntity->hash = Random::generate(24);
			$this->repository->update($cronsEntity);
		}

		$this->service->updateNextRunAt($cronsEntity);
	}


}
