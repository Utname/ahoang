-- Migration: Add Pečivo (150x41mm) print template preset
INSERT INTO `print_templates` (`name`, `product_label_type_id`, `width_mm`, `height_mm`, `variant`, `html_source`, `is_active`, `is_default`)
SELECT 'Fikko Cena – tem dải', 8, 150.00, 41.00, 'always', '', 1, 1
WHERE NOT EXISTS (
    SELECT 1 FROM `print_templates` WHERE `product_label_type_id` = 8 AND `variant` = 'always'
);
