ALTER TABLE `product_labels` ADD `default_units_id` TINYINT(1) NULL DEFAULT NULL AFTER `name`, ADD `default_units_weight` FLOAT NULL DEFAULT NULL AFTER `default_units_id`;



ALTER TABLE `products` ADD `ingredients` TEXT NOT NULL AFTER `label_note_3`;