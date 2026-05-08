SET NAMES utf8mb4;

-- 1. Gudang Isolasi
INSERT INTO `features` (`title`, `code`, `type`, `created_at`, `updated_at`)
SELECT 'Gudang Isolasi', 'gudang_isolasi', 'menu', NOW(), NOW()
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `features` WHERE `code` = 'gudang_isolasi');

SET @fid_gudang = (SELECT `id` FROM `features` WHERE `code` = 'gudang_isolasi' LIMIT 1);

UPDATE `authorities`
SET `code` = JSON_ARRAY_APPEND(`code`, '$', CAST(@fid_gudang AS CHAR))
WHERE `id` IN (1, 3, 4)
  AND JSON_VALID(`code`)
  AND @fid_gudang IS NOT NULL
  AND NOT JSON_CONTAINS(`code`, JSON_QUOTE(CAST(@fid_gudang AS CHAR)), '$');

-- 2. Nota Promosi
INSERT INTO `features` (`title`, `code`, `type`, `created_at`, `updated_at`)
SELECT 'Nota Promosi', 'nota_promosi', 'menu', NOW(), NOW()
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `features` WHERE `code` = 'nota_promosi');

SET @fid_promo = (SELECT `id` FROM `features` WHERE `code` = 'nota_promosi' LIMIT 1);

UPDATE `authorities`
SET `code` = JSON_ARRAY_APPEND(`code`, '$', CAST(@fid_promo AS CHAR))
WHERE `id` IN (1, 3, 4)
  AND JSON_VALID(`code`)
  AND @fid_promo IS NOT NULL
  AND NOT JSON_CONTAINS(`code`, JSON_QUOTE(CAST(@fid_promo AS CHAR)), '$');

-- 3. Nota Penghapusan
INSERT INTO `features` (`title`, `code`, `type`, `created_at`, `updated_at`)
SELECT 'Nota Penghapusan', 'nota_penghapusan', 'menu', NOW(), NOW()
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `features` WHERE `code` = 'nota_penghapusan');

SET @fid_hapus = (SELECT `id` FROM `features` WHERE `code` = 'nota_penghapusan' LIMIT 1);

UPDATE `authorities`
SET `code` = JSON_ARRAY_APPEND(`code`, '$', CAST(@fid_hapus AS CHAR))
WHERE `id` IN (1, 3, 4)
  AND JSON_VALID(`code`)
  AND @fid_hapus IS NOT NULL
  AND NOT JSON_CONTAINS(`code`, JSON_QUOTE(CAST(@fid_hapus AS CHAR)), '$');
