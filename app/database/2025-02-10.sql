ALTER TABLE `mailing_queue` ADD `email_templates_id` INT(11) NULL DEFAULT NULL AFTER `created_at`;
UPDATE `mailing_queue` SET email_templates_id = 8 WHERE `subject` LIKE 'Nová zpráva';
ALTER TABLE `mailing_queue` ADD `is_reply` TINYINT(1) NOT NULL DEFAULT '0' AFTER `email_templates_id`;