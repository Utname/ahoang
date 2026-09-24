ALTER TABLE `products` ADD `images_supsup` VARCHAR(255) NULL;

UPDATE products p
    JOIN products_images_supsup pis ON p.code_ean = pis.ean
    SET p.images_supsup = CONCAT(pis.image_name, '_b.', pis.image_type)
WHERE p.code_ean IS NOT NULL AND p.images_supsup IS NULL limit 5000;