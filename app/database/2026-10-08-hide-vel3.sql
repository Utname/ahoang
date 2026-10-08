-- Migration: Deactivate Vel. 3 template
UPDATE `print_templates` SET `is_active` = 0, `is_default` = 0 WHERE `product_label_type_id` = 5 AND `name` = 'Fikko Cena Vel.3';
