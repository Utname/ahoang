ALTER TABLE `product_prices_log`
	ADD `action_from` DATE NULL DEFAULT NULL AFTER `price_ref`,
	ADD `action_to` DATE NULL DEFAULT NULL AFTER `action_from`;
