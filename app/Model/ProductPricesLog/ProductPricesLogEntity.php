<?php


class ProductPricesLogEntity extends BaseEntity
{
	/**
	 * Returns discount percent based on price_ref and price_new.
	 * Avoids division by zero and negative values.
	 * @return int
	 */
	public function getDiscountPercent(): int
	{
		$priceRef = (float)($this->price_ref ?? 0);
		$priceNew = (float)($this->price_new ?? 0);

		if ($priceRef <= 0) {
			return 0;
		}

		$percent = 100 - ($priceNew / $priceRef * 100);
		$percent = round($percent);
		if ($percent < 0) {
			$percent = 0;
		}
		return (int)$percent;
	}
}