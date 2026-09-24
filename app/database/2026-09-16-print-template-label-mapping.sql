ALTER TABLE `print_templates`
    ADD `product_label_type_id` TINYINT(1) NULL DEFAULT NULL AFTER `name`;

CREATE INDEX `print_templates_label_type_variant_idx`
    ON `print_templates` (`product_label_type_id`, `variant`, `is_active`, `is_default`);
