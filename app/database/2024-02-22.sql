ALTER TABLE `product_labels` ADD `count` INT(1) NOT NULL AFTER `datetime_printed`;
ALTER TABLE `product_labels` ADD `name` VARCHAR(255) NOT NULL AFTER `count`;