-- Fix: Assign features ke authority_id = 5 (Distribusi) menggunakan code lookup.
-- Aman dijalankan berulang kali (idempoten, tidak duplikat).
-- Jalankan di MySQL 8+.

SET NAMES utf8mb4;

-- Pastikan authority Distribusi (id = 5) ada
INSERT INTO `authorities` (`id`, `title`, `code`, `created_at`, `updated_at`)
SELECT 5, 'Distribusi', '[]', NOW(), NOW()
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `authorities` WHERE `id` = 5);

-- Pastikan code valid JSON sebelum append
UPDATE `authorities` SET `code` = '[]' WHERE `id` = 5 AND NOT JSON_VALID(`code`);

-- ---------------------------------------------------------------------------
-- Helper macro: append feature id by code jika belum ada
-- ---------------------------------------------------------------------------

-- dashboard
SET @fid = (SELECT `id` FROM `features` WHERE `code` = 'dashboard' LIMIT 1);
UPDATE `authorities` SET `code` = JSON_ARRAY_APPEND(`code`, '$', CAST(@fid AS CHAR))
WHERE `id` = 5 AND @fid IS NOT NULL AND JSON_VALID(`code`)
  AND NOT JSON_CONTAINS(`code`, JSON_QUOTE(CAST(@fid AS CHAR)), '$');

-- nppb-central
SET @fid = (SELECT `id` FROM `features` WHERE `code` = 'nppb-central' LIMIT 1);
UPDATE `authorities` SET `code` = JSON_ARRAY_APPEND(`code`, '$', CAST(@fid AS CHAR))
WHERE `id` = 5 AND @fid IS NOT NULL AND JSON_VALID(`code`)
  AND NOT JSON_CONTAINS(`code`, JSON_QUOTE(CAST(@fid AS CHAR)), '$');

-- preparation_notes
SET @fid = (SELECT `id` FROM `features` WHERE `code` = 'preparation_notes' LIMIT 1);
UPDATE `authorities` SET `code` = JSON_ARRAY_APPEND(`code`, '$', CAST(@fid AS CHAR))
WHERE `id` = 5 AND @fid IS NOT NULL AND JSON_VALID(`code`)
  AND NOT JSON_CONTAINS(`code`, JSON_QUOTE(CAST(@fid AS CHAR)), '$');

-- nkb
SET @fid = (SELECT `id` FROM `features` WHERE `code` = 'nkb' LIMIT 1);
UPDATE `authorities` SET `code` = JSON_ARRAY_APPEND(`code`, '$', CAST(@fid AS CHAR))
WHERE `id` = 5 AND @fid IS NOT NULL AND JSON_VALID(`code`)
  AND NOT JSON_CONTAINS(`code`, JSON_QUOTE(CAST(@fid AS CHAR)), '$');

-- nkb_penyesuaian
SET @fid = (SELECT `id` FROM `features` WHERE `code` = 'nkb_penyesuaian' LIMIT 1);
UPDATE `authorities` SET `code` = JSON_ARRAY_APPEND(`code`, '$', CAST(@fid AS CHAR))
WHERE `id` = 5 AND @fid IS NOT NULL AND JSON_VALID(`code`)
  AND NOT JSON_CONTAINS(`code`, JSON_QUOTE(CAST(@fid AS CHAR)), '$');

-- ntb
SET @fid = (SELECT `id` FROM `features` WHERE `code` = 'ntb' LIMIT 1);
UPDATE `authorities` SET `code` = JSON_ARRAY_APPEND(`code`, '$', CAST(@fid AS CHAR))
WHERE `id` = 5 AND @fid IS NOT NULL AND JSON_VALID(`code`)
  AND NOT JSON_CONTAINS(`code`, JSON_QUOTE(CAST(@fid AS CHAR)), '$');

-- ntb_retur
SET @fid = (SELECT `id` FROM `features` WHERE `code` = 'ntb_retur' LIMIT 1);
UPDATE `authorities` SET `code` = JSON_ARRAY_APPEND(`code`, '$', CAST(@fid AS CHAR))
WHERE `id` = 5 AND @fid IS NOT NULL AND JSON_VALID(`code`)
  AND NOT JSON_CONTAINS(`code`, JSON_QUOTE(CAST(@fid AS CHAR)), '$');

-- kartu_gudang_besar
SET @fid = (SELECT `id` FROM `features` WHERE `code` = 'kartu_gudang_besar' LIMIT 1);
UPDATE `authorities` SET `code` = JSON_ARRAY_APPEND(`code`, '$', CAST(@fid AS CHAR))
WHERE `id` = 5 AND @fid IS NOT NULL AND JSON_VALID(`code`)
  AND NOT JSON_CONTAINS(`code`, JSON_QUOTE(CAST(@fid AS CHAR)), '$');

-- delivery_orders (Surat Jalan)
SET @fid = (SELECT `id` FROM `features` WHERE `code` = 'delivery_orders' LIMIT 1);
UPDATE `authorities` SET `code` = JSON_ARRAY_APPEND(`code`, '$', CAST(@fid AS CHAR))
WHERE `id` = 5 AND @fid IS NOT NULL AND JSON_VALID(`code`)
  AND NOT JSON_CONTAINS(`code`, JSON_QUOTE(CAST(@fid AS CHAR)), '$');

-- laporan-distribusi (pastikan feature sudah ada dulu)
INSERT INTO `features` (`title`, `code`, `type`, `created_at`, `updated_at`)
SELECT 'Laporan Distribusi', 'laporan-distribusi', 'menu', NOW(), NOW()
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `features` WHERE `code` = 'laporan-distribusi');

SET @fid = (SELECT `id` FROM `features` WHERE `code` = 'laporan-distribusi' LIMIT 1);
UPDATE `authorities` SET `code` = JSON_ARRAY_APPEND(`code`, '$', CAST(@fid AS CHAR))
WHERE `id` = 5 AND @fid IS NOT NULL AND JSON_VALID(`code`)
  AND NOT JSON_CONTAINS(`code`, JSON_QUOTE(CAST(@fid AS CHAR)), '$');
-- Juga tambahkan laporan-distribusi ke Superadmin (id = 1)
UPDATE `authorities` SET `code` = JSON_ARRAY_APPEND(`code`, '$', CAST(@fid AS CHAR))
WHERE `id` = 1 AND @fid IS NOT NULL AND JSON_VALID(`code`)
  AND NOT JSON_CONTAINS(`code`, JSON_QUOTE(CAST(@fid AS CHAR)), '$');

-- user-distribusi (pastikan feature sudah ada dulu)
INSERT INTO `features` (`title`, `code`, `type`, `created_at`, `updated_at`)
SELECT 'User Distribusi', 'user-distribusi', 'menu', NOW(), NOW()
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `features` WHERE `code` = 'user-distribusi');

SET @fid = (SELECT `id` FROM `features` WHERE `code` = 'user-distribusi' LIMIT 1);
-- user-distribusi hanya untuk Superadmin (id = 1)
UPDATE `authorities` SET `code` = JSON_ARRAY_APPEND(`code`, '$', CAST(@fid AS CHAR))
WHERE `id` = 1 AND @fid IS NOT NULL AND JSON_VALID(`code`)
  AND NOT JSON_CONTAINS(`code`, JSON_QUOTE(CAST(@fid AS CHAR)), '$');

-- ---------------------------------------------------------------------------
-- Verifikasi: jalankan query ini untuk melihat hasilnya
-- SELECT id, title, code FROM authorities WHERE id = 5;
-- SELECT id, title, code FROM features WHERE code IN (
--   'dashboard','nppb-central','preparation_notes','nkb','nkb_penyesuaian',
--   'ntb','ntb_retur','kartu_gudang_besar','delivery_orders','laporan-distribusi'
-- );
-- ---------------------------------------------------------------------------
