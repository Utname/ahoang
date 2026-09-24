CREATE TABLE `product_prices_log` (
	`id` int(11) NOT NULL,
	`products_id` int(11) NOT NULL,
	`branches_id` int(11) NOT NULL,
	`currencies_id` int(11) NOT NULL,
	`datetime` datetime NOT NULL,
	`price_origin` float NOT NULL,
	`price_new` float NOT NULL,
	`price_ref` float NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

ALTER TABLE `product_prices_log`
	ADD PRIMARY KEY (`id`),
  ADD KEY `products_id` (`products_id`),
  ADD KEY `branches_id` (`branches_id`),
  ADD KEY `currencies_id` (`currencies_id`);

ALTER TABLE `product_prices_log`
	MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `product_prices_log`
	ADD CONSTRAINT `product_prices_log_ibfk_1` FOREIGN KEY (`products_id`) REFERENCES `products` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `product_prices_log_ibfk_2` FOREIGN KEY (`branches_id`) REFERENCES `branches` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `product_prices_log_ibfk_3` FOREIGN KEY (`currencies_id`) REFERENCES `currencies` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;