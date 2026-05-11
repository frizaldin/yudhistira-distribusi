-- Menu Laporan Distribusi: feature + otoritas
-- Jalankan di MySQL 8+ setelah backup.
-- Menu ini hanya untuk authority_id = 5 (Distribusi) dan Superadmin (id = 1).

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------------
-- 1) Feature menu
-- ---------------------------------------------------------------------------
INSERT INTO `features` (`title`, `code`, `type`, `created_at`, `updated_at`)
SELECT 'Laporan Distribusi', 'laporan-distribusi', 'menu', NOW(), NOW()
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `features` WHERE `code` = 'laporan-distribusi');

-- ---------------------------------------------------------------------------
-- 2) Tambahkan ke authorities (tanpa duplikat)
-- ---------------------------------------------------------------------------
SET @ldist_fid = (SELECT `id` FROM `features` WHERE `code` = 'laporan-distribusi' LIMIT 1);

-- Superadmin (id = 1)
UPDATE `authorities`
SET `code` = JSON_ARRAY_APPEND(`code`, '$', CAST(@ldist_fid AS CHAR))
WHERE `id` = 1
  AND JSON_VALID(`code`)
  AND @ldist_fid IS NOT NULL
  AND NOT JSON_CONTAINS(`code`, JSON_QUOTE(CAST(@ldist_fid AS CHAR)), '$');

-- Distribusi (id = 5)
UPDATE `authorities`
SET `code` = JSON_ARRAY_APPEND(`code`, '$', CAST(@ldist_fid AS CHAR))
WHERE `id` = 5
  AND JSON_VALID(`code`)
  AND @ldist_fid IS NOT NULL
  AND NOT JSON_CONTAINS(`code`, JSON_QUOTE(CAST(@ldist_fid AS CHAR)), '$');
