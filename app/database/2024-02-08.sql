ALTER TABLE `branches` ADD `google_profile_url` VARCHAR(255) NOT NULL AFTER `lng`;

ALTER TABLE `branches` ADD `image` VARCHAR(32) NOT NULL AFTER `google_profile_url`;

ALTER TABLE `branches_mld` ADD `sale_text` TEXT NOT NULL AFTER `slug`;

ALTER TABLE `branches` ADD `images` TEXT NOT NULL AFTER `image`;