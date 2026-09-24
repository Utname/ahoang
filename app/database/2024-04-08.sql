CREATE TABLE `product_codes` (
	`id` int(11) NOT NULL,
	`products_id` int(11) NOT NULL,
	`value` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

ALTER TABLE `product_codes`
	ADD PRIMARY KEY (`id`),
  ADD KEY `products_id` (`products_id`);

ALTER TABLE `product_codes`
	MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `product_codes`
	ADD CONSTRAINT `product_codes_ibfk_1` FOREIGN KEY (`products_id`) REFERENCES `products` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `products` ADD `default_units_id` TINYINT(1) NULL DEFAULT NULL AFTER `images_supsup`, ADD `default_units_weight` FLOAT NULL DEFAULT NULL AFTER `default_units_id`;

ALTER TABLE `products` ADD `label_note_1` VARCHAR(255) NOT NULL AFTER `default_units_weight`, ADD `label_note_2` VARCHAR(255) NOT NULL AFTER `label_note_1`, ADD `label_note_3` VARCHAR(255) NOT NULL AFTER `label_note_2`;

CREATE TABLE `product_label_action_types` (
	`id` int(11) NOT NULL,
	`name` varchar(255) NOT NULL,
	`value` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO `product_label_action_types` (`id`, `name`, `value`) VALUES
	(1, 'Akce platí do', 'Akce platí do %DATE_TO%'),
	(2, 'Akce platí do nebo vyprodání zásob', 'Akce platí do %DATE_TO% nebo vyprodání zásob');

ALTER TABLE `product_label_action_types`
	ADD PRIMARY KEY (`id`);

ALTER TABLE `product_label_action_types`
	MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;