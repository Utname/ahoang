CREATE TABLE `print_templates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `name` varchar(255) NOT NULL,
  `width_mm` decimal(6,2) NOT NULL,
  `height_mm` decimal(6,2) NOT NULL,
  `variant` enum('always','discount','no_discount') NOT NULL DEFAULT 'always',
  `html_source` longtext NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Register the new admin module + permissions (required, or the module is invisible/access-denied for everyone)
INSERT INTO `admin_modules` (`id`, `name`) VALUES ('PrintTemplates', 'Katalog / Šablony tisku');
INSERT INTO `admin_permissions` (`id`, `name`, `admin_modules_id`) VALUES
  ('PrintTemplates_list', 'list', 'PrintTemplates'),
  ('PrintTemplates_edit', 'edit', 'PrintTemplates'),
  ('PrintTemplates_create', 'create', 'PrintTemplates'),
  ('PrintTemplates_delete', 'delete', 'PrintTemplates');
-- Grant to role 1 (Administrátor) — adjust/add more admin_roles_id rows as needed for other roles.
INSERT INTO `admin_roles_admin_permissions_rel` (`admin_roles_id`, `admin_permissions_id`) VALUES
  (1, 'PrintTemplates_list'),
  (1, 'PrintTemplates_edit'),
  (1, 'PrintTemplates_create'),
  (1, 'PrintTemplates_delete');
