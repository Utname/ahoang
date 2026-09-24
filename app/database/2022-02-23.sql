ALTER TABLE `product_labels` DROP `header_type_id`;
ALTER TABLE `product_labels` ADD `product_label_headers_id` INT(11) NOT NULL AFTER `origin_countries_id`;
UPDATE `product_labels` SET `product_label_headers_id` = 1 WHERE 1;

CREATE TABLE `product_label_headers` (
	`id` int(11) NOT NULL,
	`pos_order` float NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Struktura tabulky `product_label_headers_mld`
--

CREATE TABLE `product_label_headers_mld` (
	`product_label_headers_id` int(11) NOT NULL,
	`languages_id` varchar(2) COLLATE utf8_czech_ci NOT NULL,
	`name` varchar(255) COLLATE utf8_czech_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;

--
-- Indexy pro exportované tabulky
--

--
-- Indexy pro tabulku `product_label_headers`
--
ALTER TABLE `product_label_headers`
	ADD PRIMARY KEY (`id`);

--
-- Indexy pro tabulku `product_label_headers_mld`
--
ALTER TABLE `product_label_headers_mld`
	ADD PRIMARY KEY (`product_label_headers_id`,`languages_id`),
  ADD KEY `languages_id` (`languages_id`);

--
-- AUTO_INCREMENT pro tabulky
--

--
-- AUTO_INCREMENT pro tabulku `product_label_headers`
--
ALTER TABLE `product_label_headers`
	MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Omezení pro exportované tabulky
--

--
-- Omezení pro tabulku `product_label_headers_mld`
--
ALTER TABLE `product_label_headers_mld`
	ADD CONSTRAINT `product_label_headers_mld_ibfk_1` FOREIGN KEY (`product_label_headers_id`) REFERENCES `product_label_headers` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `product_label_headers_mld_ibfk_2` FOREIGN KEY (`languages_id`) REFERENCES `languages` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `product_labels` ADD FOREIGN KEY (`product_label_headers_id`) REFERENCES `product_label_headers`(`id`) ON DELETE CASCADE ON UPDATE CASCADE;