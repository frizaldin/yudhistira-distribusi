SET NAMES utf8mb4;

-- Daftarkan fitur baru 'Kartu Gudang Besar' jika belum ada
INSERT INTO `features` (`title`, `code`, `type`, `created_at`, `updated_at`)
SELECT 'Kartu Gudang Besar', 'kartu_gudang_besar', 'menu', NOW(), NOW()
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `features` WHERE `code` = 'kartu_gudang_besar');

-- Ambil ID dari fitur yang baru diinsert (atau yang sudah ada)
SET @fid_kgb = (SELECT `id` FROM `features` WHERE `code` = 'kartu_gudang_besar' LIMIT 1);

-- Tambahkan fitur ini ke authority_id = 5 (Distribusi) dan 1 (Superadmin)
UPDATE `authorities`
SET `code` = JSON_ARRAY_APPEND(`code`, '$', CAST(@fid_kgb AS CHAR))
WHERE `id` IN (1, 5)
  AND JSON_VALID(`code`)
  AND @fid_kgb IS NOT NULL
  AND NOT JSON_CONTAINS(`code`, JSON_QUOTE(CAST(@fid_kgb AS CHAR)), '$');
