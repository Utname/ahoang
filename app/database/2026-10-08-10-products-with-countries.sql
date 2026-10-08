-- Migration: Replace 10 products with 10 products having full origin countries and flags
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- Countries & Translations
INSERT INTO `countries` (`id`, `created_at`, `is_active`, `iso_code_2`, `iso_code_3`, `pos_order`, `image`) VALUES
(1, '2023-12-07 16:33:20', 1, 'CZ', 'CZE', 3, '0e7552c8cb6fa204.png'),
(2, '2024-02-02 00:44:57', 1, '', '', 6, '15f9e6cc777b89da.png'),
(3, '2024-02-02 00:44:57', 1, '', '', 50, 'ceab986130261c46.png'),
(12, '2024-02-02 00:44:57', 1, '', '', 41, 'a87329cec5318557.png'),
(13, '2024-02-02 00:44:57', 1, '', '', 40, '0f1cdd0c73aea5e6.png'),
(18, '2024-02-02 00:44:57', 1, '', '', 2, '87d2dbe368ccb57b.png'),
(21, '2024-02-02 00:44:57', 1, '', '', 35, '03e4bb8dd03764a7.jpg'),
(23, '2024-02-02 00:44:57', 1, '', '', 33, 'c8bbe295064981f9.png'),
(30, '2024-02-02 00:44:58', 1, '', '', 26, '6aa00c1f58d4e178.png'),
(35, '2024-02-02 00:44:58', 1, '', '', 21, '2ceaa80c7045a441.jpg')
ON DUPLICATE KEY UPDATE `image` = VALUES(`image`), `is_active` = VALUES(`is_active`);

INSERT INTO `countries_mld` (`countries_id`, `languages_id`, `name`) VALUES
(1, 'cs', 'Česká Republika'),
(1, 'de', 'Česká Republika'),
(2, 'cs', 'Polsko'),
(2, 'de', 'Polsko'),
(3, 'cs', 'Itálie'),
(3, 'de', 'Itálie'),
(12, 'cs', 'Dánsko'),
(12, 'de', 'Dánsko'),
(13, 'cs', 'Vietnam'),
(13, 'de', 'Vietnam'),
(18, 'cs', 'Německo'),
(18, 'de', 'Německo'),
(21, 'cs', 'Taiwan'),
(21, 'de', 'Taiwan (provincie Čínské republiky)'),
(23, 'cs', 'Belgie'),
(23, 'de', 'Belgie'),
(30, 'cs', 'Ukrajina'),
(30, 'de', 'Ukrajina'),
(35, 'cs', 'Estonsko'),
(35, 'de', 'Estonsko')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- Clear any other products on remote system
DELETE FROM `product_prices_log` WHERE `products_id` NOT IN (1, 14, 99, 112, 840, 21625, 30963, 3750, 53086, 2);
DELETE FROM `product_prices` WHERE `products_id` NOT IN (1, 14, 99, 112, 840, 21625, 30963, 3750, 53086, 2);
DELETE FROM `product_nutrition_values` WHERE `products_id` NOT IN (1, 14, 99, 112, 840, 21625, 30963, 3750, 53086, 2);
DELETE FROM `product_codes` WHERE `products_id` NOT IN (1, 14, 99, 112, 840, 21625, 30963, 3750, 53086, 2);
DELETE FROM `product_labels` WHERE `products_id` NOT IN (1, 14, 99, 112, 840, 21625, 30963, 3750, 53086, 2);
DELETE FROM `products_mld` WHERE `products_id` NOT IN (1, 14, 99, 112, 840, 21625, 30963, 3750, 53086, 2);
DELETE FROM `products` WHERE `id` NOT IN (1, 14, 99, 112, 840, 21625, 30963, 3750, 53086, 2);

-- Products & Details
INSERT INTO `products` (`id`, `created_at`, `code`, `code_ean`, `types_id`, `images`, `files`, `meta_image`, `internal_note`, `tags`, `set_pieces`, `priority`, `country_origin`, `units_id`, `weight`, `images_supsup`, `default_units_id`, `default_units_weight`, `label_note_1`, `label_note_2`, `label_note_3`, `ingredients`, `consumption_types_id`, `nutrition_reference_amount`, `nutrition_reference_unit`) VALUES
(1, '2023-12-07 17:48:30', NULL, '5711953117985', 1, '{"file":"73964a6e6ddc1310.jpg"}', NULL, NULL, NULL, '', 1, NULL, '12', 2, 250, NULL, NULL, 0, '', '', '', 'NÁPOJ STARBUCKS FRAPPUCCINO CARMEL 250 ml\nSložení: Mléko s obsahem tuku 2 % (75 %),káva Arabica Starbucks (voda,kávový extrakt) (19,3 %), cukr (5,5 %),přírodní aromata (obsahuje mléko),regulátor kyselosti (uhličitany draselné)\nSkladování: Skladujte tại teplotě +2 °C až +25 °C. Po otevření skladujte při teplotě do +8 °C a spotřebujte do tří dnů.\nAlergeny: Laktóza.', 1, NULL, NULL),
(2, '2023-12-07 17:49:52', NULL, '', 1, '{"file":"fd8ad9a88a98cda2.png"}', NULL, NULL, NULL, '', 1, NULL, '30', 2, 800, '_b.', NULL, NULL, '', '', '', '', NULL, NULL, NULL),
(14, '2024-01-16 18:52:08', NULL, '30', 1, '{"file":"52794787a0b58339.png"}', NULL, NULL, NULL, '', 1, NULL, '18', 4, 35, NULL, NULL, 0, '', '', '', 'Složení: Mléčná čokoláda 28,5 % (kakaové máslo, cukr, plnotučné sušené mléko (15,5 %), kakaová hmota, emulgátor: lecitiny (sójové); vanilin), Pasterované mléko (14,5 %), Cukr, Karamel 11 % (slazené kondenzované mléko (50 %), cukr, lískové oříšky, palmový olej, aroma), Lískové oříšky (10,5 %), Palmový olej, Odtučněné sušené mléko (5,5 %), Pšeničná mouka, Máselný tuk, Rýžová mouka, Maniokový škrob, Emulgátory (mono- a diglyceridy mastných kyselin, lecitiny (sójové)), Jedlá sůl, Vanilin, Zahušťovadlo (guma tara), Kypřící látka (E 500ii)\nAlergeny Obsahuje: Lískové ořechy, Mléko, Sója, Pšenice\n', NULL, NULL, NULL),
(99, '2024-01-16 18:52:10', NULL, '1302', 1, '', NULL, NULL, NULL, '', 1, NULL, '3', 4, 100, NULL, NULL, 0, 'Cena za 100g', '', 'cena za 1kg = 159 Kč', '', NULL, 0, NULL),
(112, '2024-01-16 18:52:10', NULL, '1874', 1, '', NULL, NULL, NULL, '', 1, NULL, '2', 3, 1, NULL, NULL, 0, '', '', '', 'SLOŽENÍ: VEPŘOVÁ SLANINA (94 %); SŮL: STABILIZÁTORY: TRIFOSFORÁTY; GLUKÓZA; VEPŘOVÝ PROTEIN: ANTIOXIDANT: IZOKORBÁT SODNÝ; MALTODEXTRIN; KOUŘOVÁ PŘÍCHUŤ; KONZERVANT CA: DUSIČNAN SODNÝ. VÝROBEK MŮŽE OBSAHOVAT: SÓJU. OBILOVINY OBSAHUJÍCÍ LEPEK. MLÉKO A DERIVÁTY (VČETNĚ LAKTÓZY), CELER, HOŘČICE VÝŽIVOVÁ HODNOTA VE 100 g VÝROBKU: ENERGETICKÁ HODNOTA 1385 kJ/335 kcal: TUKY 31 g. VČETNĚ NAsycených mastných kyselin 12 9: SACHARIDY 1,0 g. VČETNĚ CUKRŮ 0,5 g; PROTEIN 13 g: SŮL 2,3 g', NULL, NULL, NULL),
(840, '2024-01-16 18:52:24', NULL, '20012274', 1, '{"file":"affd3f54ac68e784.jpg"}', NULL, NULL, NULL, '', 1, NULL, '23', 4, 250, NULL, NULL, NULL, '', '', '', '', NULL, NULL, NULL),
(3750, '2024-01-16 18:53:21', NULL, '8938505311388', 1, '', NULL, NULL, NULL, '', 1, NULL, '13', 2, 325, '_b.', NULL, 0, '', '', '', '', NULL, 0, NULL),
(21625, '2024-01-16 18:59:09', NULL, '4740098090601', 1, '{"file":"988d79f67ad0d681.jpg"}', NULL, NULL, NULL, '', 1, NULL, '35', 0, 0, NULL, NULL, 0, '', '', '', '', NULL, NULL, NULL),
(30963, '2024-01-16 19:02:29', NULL, '5201360614387', 1, NULL, NULL, NULL, NULL, '', 1, NULL, '1', 0, 0, NULL, NULL, NULL, '', '', '', '', NULL, NULL, NULL),
(53086, '2026-10-04 09:59:08', NULL, '69016689363184712928345731', 1, '', NULL, NULL, NULL, '', 1, NULL, '21', 0, 0, NULL, NULL, 0, '', '', '', '', NULL, 0, NULL)
ON DUPLICATE KEY UPDATE `country_origin` = VALUES(`country_origin`), `code_ean` = VALUES(`code_ean`);

INSERT INTO `products_mld` (`products_id`, `languages_id`, `name`, `slug`, `title`, `perex`, `text`, `meta_title`, `meta_desc`, `is_active`) VALUES
(1, 'cs', 'Starbucks Frappuccino Mocha 250 ml', 'starbucks-frappuccino-mocha-250-ml', NULL, '', '', '', '', 1),
(1, 'de', 'Starbucks Frappuccino Mocha 250 ml', 'starbucks-frappuccino-mocha-250-ml', NULL, '', '', '', '', 1),
(2, 'cs', 'Mléko 800ml', 'mleko-800ml', NULL, '<p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Nam ultricies dolor at velit maximus lacinia. Maecenas aliquam vehicula scelerisque. Morbi imperdiet, libero nec fringilla accumsan, dui mi feugiat nisl, quis viverra neque nisl vel augue. Fusce malesuada elit in leo porttitor lobortis. Nunc consectetur purus quis sem ultricies tempus.</p>\n', '<p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Nam ultricies dolor at velit maximus lacinia. Maecenas aliquam vehicula scelerisque. Morbi imperdiet, libero nec fringilla accumsan, dui mi feugiat nisl, quis viverra neque nisl vel augue. Fusce malesuada elit in leo porttitor lobortis. Nunc consectetur purus quis sem ultricies tempus.</p>\n', '', '', 1),
(2, 'de', 'Mléko 800ml', 'mleko-800ml', NULL, '<p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Nam ultricies dolor at velit maximus lacinia. Maecenas aliquam vehicula scelerisque. Morbi imperdiet, libero nec fringilla accumsan, dui mi feugiat nisl, quis viverra neque nisl vel augue. Fusce malesuada elit in leo porttitor lobortis. Nunc consectetur purus quis sem ultricies tempus.</p>\n', '<p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Nam ultricies dolor at velit maximus lacinia. Maecenas aliquam vehicula scelerisque. Morbi imperdiet, libero nec fringilla accumsan, dui mi feugiat nisl, quis viverra neque nisl vel augue. Fusce malesuada elit in leo porttitor lobortis. Nunc consectetur purus quis sem ultricies tempus.</p>\n', '', '', 1),
(14, 'cs', 'Kinder Maxi King 35g', 'kinder-maxi-king-35g', NULL, '', '', '', '', 1),
(14, 'de', 'Kinder Maxi King 35g', 'kinder-maxi-king-35g', NULL, '', '', '', '', 1),
(99, 'cs', 'Prosciutto Crudo', 'prosciutto-crudo', NULL, '', '', '', '', 1),
(99, 'de', 'Prosciutto Crudo', 'prosciutto-crudo', NULL, '', '', '', '', 1),
(112, 'cs', 'Uzená Slanina', 'uzena-slanina', NULL, '', '', '', '', 1),
(112, 'de', 'Lovecký salám patky', 'lovecky-salam-patky', NULL, NULL, NULL, NULL, NULL, 1),
(840, 'cs', 'J.d.Gross Belgian chocolate 250g', 'j-d-gross-belgian-chocolate-250g', NULL, NULL, NULL, NULL, NULL, 1),
(840, 'de', 'J.d.Gross Belgian chocolate 250g', 'j-d-gross-belgian-chocolate-250g', NULL, NULL, NULL, NULL, NULL, 1),
(3750, 'cs', 'Datafa nápoj aloe vera s příchutí maracuji 325ml', 'datafa-napoj-aloe-vera-s-prichuti-maracuji-325ml', NULL, '', '', '', '', 1),
(3750, 'de', 'Datafa nápoj aloe vera s příchutí maracuji 325ml', 'datafa-napoj-aloe-vera-s-prichuti-maracuji-325ml', NULL, NULL, NULL, NULL, NULL, 1),
(21625, 'cs', 'Le COQ Koktejl 330 ml', 'le-coq-koktejl-330-ml', NULL, '', '', '', '', 1),
(21625, 'de', 'Le Coq alkoholický koktejl 4,7% 330ml', 'le-coq-alkoholicky-koktejl-4-7-330ml', NULL, '', '', '', '', 1),
(30963, 'cs', '29.10.2023 7Days bake rolls bran pizza 80g', '29-10-2023-7days-bake-rolls-bran-pizza-80g', NULL, NULL, NULL, NULL, NULL, 1),
(30963, 'de', '29.10.2023 7Days bake rolls bran pizza 80g', '29-10-2023-7days-bake-rolls-bran-pizza-80g', NULL, NULL, NULL, NULL, NULL, 1),
(53086, 'cs', '–6 % QLove Boba Brown Sugar Mochi Standing Pouch 120g', '6-qlove-boba-brown-sugar-mochi-standing-pouch-120g', NULL, '<p>mô tả bac</p>\n', '<p>nội dung bac</p>\n', '', '', 1),
(53086, 'de', '–6 % QLove Boba Brown Sugar Mochi Standing Pouch 120g', '6-qlove-boba-brown-sugar-mochi-standing-pouch-120g', NULL, '<p>mô tả bac</p>\n', '<p>nội dung bac</p>\n', '', '', 1)
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `is_active` = VALUES(`is_active`);

INSERT INTO `product_prices` (`id`, `created_at`, `products_id`, `branches_id`, `currencies_id`, `price`, `price_action`, `is_action`, `action_from`, `action_to`) VALUES
(630932, '2026-01-20 19:03:30', 1, 1, 1, 99.9, 29.9, 1, '2026-04-02 00:00:00', '2026-04-02 00:00:00'),
(630934, '2026-01-20 19:03:30', 1, 2, 1, 200, 29.9, 0, '2026-03-27 00:00:00', '2026-03-27 00:00:00'),
(630935, '2026-01-20 19:03:30', 1, 15, 1, 200, NULL, 0, NULL, NULL),
(630936, '2026-01-20 19:03:30', 1, 8, 1, 200, 39.9, 0, '2026-01-10 00:00:00', '2026-01-31 00:00:00'),
(630937, '2026-01-20 19:03:30', 1, 10, 1, 200, 39.9, 0, '2026-01-10 00:00:00', '2026-01-31 00:00:00'),
(630938, '2026-01-20 19:03:30', 1, 7, 1, 200, 36.9, 0, '2024-11-21 00:00:00', '2025-01-31 00:00:00'),
(630939, '2026-01-20 19:03:30', 1, 13, 1, 200, NULL, 0, NULL, NULL),
(630940, '2026-01-20 19:03:30', 1, 9, 1, 200, 41.9, 0, '2024-11-21 00:00:00', '2026-01-20 00:00:00'),
(630941, '2026-01-20 19:03:30', 1, 14, 1, 200, 39.9, 0, '2026-01-06 00:00:00', '2026-02-28 00:00:00'),
(68718, '2024-01-18 22:12:39', 2, 1, 1, 16, 0, 0, '2024-02-01 00:00:00', '2024-04-15 00:00:00'),
(68719, '2024-01-18 22:12:39', 2, 2, 1, 19, NULL, 0, NULL, NULL),
(651312, '2026-02-09 15:17:06', 14, 1, 1, 24.9, 18.9, 0, '2025-10-31 00:00:00', '2025-11-09 00:00:00'),
(651314, '2026-02-09 15:17:06', 14, 2, 1, 24.9, NULL, 0, NULL, NULL),
(651315, '2026-02-09 15:17:06', 14, 15, 1, 24.9, NULL, 0, NULL, NULL),
(651316, '2026-02-09 15:17:06', 14, 8, 1, 24.9, 18.9, 1, '2026-04-29 00:00:00', '2026-04-29 00:00:00'),
(651317, '2026-02-09 15:17:06', 14, 10, 1, 24.9, NULL, 0, NULL, NULL),
(651318, '2026-02-09 15:17:06', 14, 7, 1, 24.9, 19.9, 0, '2026-03-27 00:00:00', '2026-03-27 00:00:00'),
(651319, '2026-02-09 15:17:06', 14, 13, 1, 24.9, 18.9, 0, '2025-12-10 00:00:00', '2025-12-10 00:00:00'),
(651320, '2026-02-09 15:17:06', 14, 9, 1, 24.9, NULL, 0, NULL, NULL),
(651321, '2026-02-09 15:17:06', 14, 14, 1, 24.9, 19.9, 1, '2026-08-11 00:00:00', '2026-08-11 00:00:00'),
(880994, '2026-08-05 22:32:28', 99, 1, 1, 39.9, 18.9, 0, '2026-05-25 00:00:00', '2026-05-25 00:00:00'),
(880995, '2026-08-05 22:32:28', 99, 2, 1, 39.9, 15.9, 1, '2026-08-05 00:00:00', '2026-08-05 00:00:00'),
(880996, '2026-08-05 22:32:28', 99, 15, 1, 39.9, 15.9, 1, '2026-08-05 00:00:00', '2026-08-05 00:00:00'),
(880997, '2026-08-05 22:32:28', 99, 8, 1, 39.9, 18.9, 0, '2026-05-27 00:00:00', '2026-05-27 00:00:00'),
(880998, '2026-08-05 22:32:28', 99, 10, 1, 39.9, 18.9, 0, '2026-02-13 00:00:00', '2026-02-13 00:00:00'),
(880999, '2026-08-05 22:32:28', 99, 7, 1, 39.9, 12.9, 0, '2025-11-16 00:00:00', '2025-11-16 00:00:00'),
(881000, '2026-08-05 22:32:28', 99, 13, 1, 39.9, 12.9, 0, '2025-11-18 00:00:00', '2025-11-18 00:00:00'),
(881001, '2026-08-05 22:32:28', 99, 9, 1, 39.9, 15.9, 0, '2024-11-02 00:00:00', '2026-05-02 00:00:00'),
(881002, '2026-08-05 22:32:28', 99, 14, 1, 39.9, 18.9, 0, '2026-05-25 00:00:00', '2026-05-25 00:00:00'),
(312408, '2025-04-03 22:31:09', 112, 1, 1, 149.9, 59.9, 0, '2025-04-03 00:00:00', '2025-04-03 00:00:00'),
(312410, '2025-04-03 22:31:09', 112, 2, 1, 149.9, NULL, 0, NULL, NULL),
(312411, '2025-04-03 22:31:09', 112, 7, 1, 149.9, NULL, 0, NULL, NULL),
(312412, '2025-04-03 22:31:09', 112, 8, 1, 149.9, 59.9, 0, '2025-04-03 00:00:00', '2025-04-03 00:00:00'),
(312413, '2025-04-03 22:31:09', 112, 10, 1, 149.9, 59.9, 0, '2025-04-03 00:00:00', '2025-04-03 00:00:00'),
(312414, '2025-04-03 22:31:09', 112, 9, 1, 149.9, NULL, 0, NULL, NULL),
(944, '2024-01-16 17:52:24', 840, 1, 1, 69.9, NULL, 0, '2024-02-01 00:00:00', '2024-04-15 00:00:00'),
(885850, '2026-08-08 19:19:12', 3750, 1, 1, 29.9, NULL, 0, '2024-02-01 00:00:00', '2024-04-15 00:00:00'),
(885851, '2026-08-08 19:19:12', 3750, 2, 1, 29.9, NULL, 0, NULL, NULL),
(885852, '2026-08-08 19:19:12', 3750, 15, 1, 29.9, NULL, 0, NULL, NULL),
(885853, '2026-08-08 19:19:12', 3750, 8, 1, 29.9, NULL, 0, NULL, NULL),
(885854, '2026-08-08 19:19:12', 3750, 10, 1, 29.9, NULL, 0, NULL, NULL),
(885855, '2026-08-08 19:19:12', 3750, 7, 1, 29.9, 4.9, 0, '2026-07-24 00:00:00', '2026-07-24 00:00:00'),
(885856, '2026-08-08 19:19:12', 3750, 13, 1, 29.9, 4.9, 1, '2026-07-23 00:00:00', '2026-08-08 00:00:00'),
(885857, '2026-08-08 19:19:12', 3750, 9, 1, 29.9, NULL, 0, NULL, NULL),
(885858, '2026-08-08 19:19:12', 3750, 14, 1, 29.9, 5.9, 0, '2026-04-19 00:00:00', '2026-04-19 00:00:00'),
(139280, '2024-07-30 14:18:06', 21625, 1, 1, 44.9, 29.9, 0, '2024-07-30 00:00:00', '2024-09-30 00:00:00'),
(139282, '2024-07-30 14:18:06', 21625, 2, 1, 44.9, NULL, 0, NULL, NULL),
(139283, '2024-07-30 14:18:06', 21625, 7, 1, 44.9, NULL, 0, NULL, NULL),
(139284, '2024-07-30 14:18:06', 21625, 8, 1, 44.9, 38.9, 0, '2024-07-20 00:00:00', '2024-07-20 00:00:00'),
(139285, '2024-07-30 14:18:06', 21625, 10, 1, 44.9, NULL, 0, NULL, NULL),
(139286, '2024-07-30 14:18:06', 21625, 9, 1, 44.9, 39.9, 0, '2024-06-25 00:00:00', '2024-07-31 00:00:00'),
(31067, '2024-01-16 18:02:29', 30963, 1, 1, 19, 15, 1, '2026-10-01 00:00:00', '2026-10-15 00:00:00'),
(892296, '2026-10-04 09:59:08', 53086, 1, 1, 100, NULL, 0, NULL, NULL),
(892297, '2026-10-04 09:59:08', 53086, 2, 1, 100, NULL, 0, NULL, NULL),
(892298, '2026-10-04 09:59:08', 53086, 15, 1, 100, 49.9, 0, '2026-06-02 00:00:00', '2026-06-02 00:00:00'),
(892299, '2026-10-04 09:59:08', 53086, 8, 1, 100, NULL, 0, NULL, NULL),
(892300, '2026-10-04 09:59:08', 53086, 10, 1, 100, NULL, 0, NULL, NULL),
(892301, '2026-10-04 09:59:08', 53086, 7, 1, 100, NULL, 0, NULL, NULL),
(892302, '2026-10-04 09:59:08', 53086, 13, 1, 100, NULL, 0, NULL, NULL),
(892303, '2026-10-04 09:59:08', 53086, 9, 1, 100, NULL, 0, NULL, NULL),
(892304, '2026-10-04 09:59:08', 53086, 14, 1, 100, 49.9, 0, '2026-07-01 00:00:00', '2026-07-01 00:00:00')
ON DUPLICATE KEY UPDATE `price` = VALUES(`price`), `price_action` = VALUES(`price_action`);

INSERT INTO `product_prices_log` (`id`, `products_id`, `branches_id`, `currencies_id`, `datetime`, `price_origin`, `price_new`, `price_ref`, `action_from`, `action_to`) VALUES
(6347, 1, 2, 1, '2026-03-30 14:45:45', 29.9, 99.9, 29.9, NULL, NULL),
(6348, 1, 8, 1, '2026-03-30 14:45:45', 39.9, 99.9, 39.9, NULL, NULL),
(6349, 1, 10, 1, '2026-03-30 14:45:45', 39.9, 99.9, 39.9, NULL, NULL),
(6350, 1, 9, 1, '2026-03-30 14:45:45', 41.9, 99.9, 41.9, NULL, NULL),
(6351, 1, 14, 1, '2026-03-30 14:45:45', 39.9, 99.9, 39.9, NULL, NULL),
(7977, 14, 7, 1, '2026-03-30 14:45:54', 19.9, 24.9, 19.9, NULL, NULL),
(26685, 14, 8, 1, '2026-04-29 16:27:40', 24.9, 18.9, 24.9, NULL, NULL),
(71346, 14, 14, 1, '2026-08-11 15:34:36', 24.9, 19.9, 24.9, NULL, NULL),
(8317, 99, 10, 1, '2026-03-30 14:45:56', 18.9, 39.9, 18.9, NULL, NULL),
(8318, 99, 14, 1, '2026-03-30 14:45:56', 18.9, 39.9, 18.9, NULL, NULL),
(26297, 99, 9, 1, '2026-04-29 12:57:20', 39.9, 15.9, 39.9, NULL, NULL),
(27532, 99, 9, 1, '2026-05-02 11:32:33', 39.9, 15.9, 39.9, NULL, NULL),
(36213, 99, 1, 1, '2026-05-25 15:13:00', 39.9, 18.9, 39.9, NULL, NULL),
(36267, 99, 14, 1, '2026-05-25 16:36:12', 39.9, 18.9, 39.9, NULL, NULL),
(37212, 99, 8, 1, '2026-05-27 11:17:45', 39.9, 18.9, 39.9, NULL, NULL),
(44535, 99, 2, 1, '2026-06-11 11:36:10', 39.9, 18.9, 39.9, NULL, NULL),
(56069, 99, 2, 1, '2026-07-06 15:02:13', 39.9, 15.9, 39.9, NULL, NULL),
(63427, 99, 15, 1, '2026-07-22 14:38:39', 39.9, 15.9, 39.9, NULL, NULL),
(63617, 99, 2, 1, '2026-07-22 17:01:47', 39.9, 15.9, 39.9, NULL, NULL),
(69148, 99, 15, 1, '2026-08-05 17:10:13', 39.9, 15.9, 39.9, NULL, NULL),
(69177, 99, 2, 1, '2026-08-05 17:32:33', 39.9, 15.9, 39.9, NULL, NULL),
(21106, 3750, 14, 1, '2026-04-19 10:52:04', 29.9, 5.9, 29.9, NULL, NULL),
(64429, 3750, 7, 1, '2026-07-24 11:25:47', 29.9, 4.9, 29.9, NULL, NULL),
(72470, 30963, 1, 1, '2026-10-08 06:10:45', 19, 15, 19, '2026-10-01 00:00:00', '2026-10-15 00:00:00'),
(72471, 30963, 2, 1, '2026-10-08 06:10:45', 0, 19, 0, NULL, NULL),
(72472, 30963, 15, 1, '2026-10-08 06:10:45', 0, 19, 0, NULL, NULL),
(72473, 30963, 8, 1, '2026-10-08 06:10:45', 0, 19, 0, NULL, NULL),
(72474, 30963, 10, 1, '2026-10-08 06:10:45', 0, 19, 0, NULL, NULL),
(72475, 30963, 7, 1, '2026-10-08 06:10:45', 0, 19, 0, NULL, NULL),
(72476, 30963, 13, 1, '2026-10-08 06:10:45', 0, 19, 0, NULL, NULL),
(72477, 30963, 9, 1, '2026-10-08 06:10:45', 0, 19, 0, NULL, NULL),
(72478, 30963, 14, 1, '2026-10-08 06:10:45', 0, 19, 0, NULL, NULL),
(72442, 53086, 1, 1, '2026-10-04 04:59:08', 0, 100, 0, NULL, NULL),
(72443, 53086, 2, 1, '2026-10-04 04:59:08', 0, 100, 0, NULL, NULL),
(72444, 53086, 15, 1, '2026-10-04 04:59:08', 0, 100, 0, NULL, NULL),
(72445, 53086, 8, 1, '2026-10-04 04:59:08', 0, 100, 0, NULL, NULL),
(72446, 53086, 10, 1, '2026-10-04 04:59:08', 0, 100, 0, NULL, NULL),
(72447, 53086, 7, 1, '2026-10-04 04:59:08', 0, 100, 0, NULL, NULL),
(72448, 53086, 13, 1, '2026-10-04 04:59:08', 0, 100, 0, NULL, NULL),
(72449, 53086, 9, 1, '2026-10-04 04:59:08', 0, 100, 0, NULL, NULL),
(72450, 53086, 14, 1, '2026-10-04 04:59:08', 0, 100, 0, NULL, NULL);

SET FOREIGN_KEY_CHECKS = 1;
