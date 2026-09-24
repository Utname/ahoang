<?php


class ProductLabelActionTypesEntity extends BaseEntity
{
	/**
	 * @param ProductPricesEntity|NULL $productPrice
	 * @return string
	 */
	public function getFormattedValue(ProductPricesEntity $productPrice = NULL): string
	{
		$dateTo = '';
		if ($productPrice && $productPrice->action_to) {
			$dateTo = date('d.m.Y', strtotime($productPrice->action_to));
		}

		$value = str_replace('%DATE_TO%', $dateTo, $this->value);

		return $value;
	}
}
