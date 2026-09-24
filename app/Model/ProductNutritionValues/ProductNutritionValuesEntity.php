<?php


class ProductNutritionValuesEntity extends BaseEntity
{
	/** @var NutritionValuesEntity */
	protected $nutritionValue;


	public function getNutritionValue(): ?NutritionValuesEntity
	{
		if (!$this->nutritionValue && $this->nutrition_values_id && $row = $this->getRow()->ref('nutrition_values')) {
			$this->nutritionValue = NutritionValuesRepository::getEntity($row);
		}

		return $this->nutritionValue;
	}
}
