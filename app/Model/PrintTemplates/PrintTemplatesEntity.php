<?php


class PrintTemplatesEntity extends BaseEntity
{
	const VARIANT_ALWAYS = 'always';
	const VARIANT_DISCOUNT = 'discount';
	const VARIANT_NO_DISCOUNT = 'no_discount';

	const VARIANTS_ARR = [
		self::VARIANT_ALWAYS => 'admin.terms.printTemplateVariantAlways',
		self::VARIANT_DISCOUNT => 'admin.terms.printTemplateVariantDiscount',
		self::VARIANT_NO_DISCOUNT => 'admin.terms.printTemplateVariantNoDiscount',
	];
}
