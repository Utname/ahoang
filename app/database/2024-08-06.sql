ALTER TABLE `product_labels` ADD `admin_users_id` INT(11) NULL DEFAULT NULL AFTER `branches_id`;
ALTER TABLE `product_labels` ADD FOREIGN KEY (`admin_users_id`) REFERENCES `admin_users`(`id`) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE `fikkomarket_cz`.`product_labels` DROP INDEX `products_id`, ADD UNIQUE `products_id` (`products_id`, `branches_id`, `type_id`, `admin_users_id`) USING BTREE;

ALTER TABLE `branches` ADD `google_maps_link` TEXT NOT NULL AFTER `fb_url`;