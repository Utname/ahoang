ALTER TABLE `branches` ADD `image_detail` VARCHAR(32) NOT NULL AFTER `images`;

ALTER TABLE `branches_mld` ADD `detail_text` TEXT NOT NULL AFTER `sale_text`;

ALTER TABLE `branches` ADD `fb_url` VARCHAR(255) NOT NULL AFTER `image_detail`;