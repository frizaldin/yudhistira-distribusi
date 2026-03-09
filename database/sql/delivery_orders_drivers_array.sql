-- Supir bisa 2: ganti kolom driver (string) jadi drivers (JSON array).

-- 1. Tambah kolom drivers (JSON)
ALTER TABLE `delivery_orders` ADD `drivers` JSON NULL AFTER `plate_number`;

-- 2. Pindahkan isi driver ke drivers[0] (satu elemen array)
UPDATE `delivery_orders` SET `drivers` = JSON_ARRAY(`driver`) WHERE `driver` IS NOT NULL AND `driver` != '';

-- 3. Hapus kolom driver
ALTER TABLE `delivery_orders` DROP COLUMN `driver`;
