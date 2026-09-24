ALTER TABLE `products`
	ADD `nutrition_reference_amount` DECIMAL(10,2) NULL DEFAULT NULL AFTER `consumption_types_id`,
	ADD `nutrition_reference_unit` VARCHAR(16) NULL DEFAULT NULL AFTER `nutrition_reference_amount`;
