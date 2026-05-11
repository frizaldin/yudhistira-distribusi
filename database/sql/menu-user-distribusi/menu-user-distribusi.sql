-- Menu User Distribusi: feature + otoritas
-- Jalankan di MySQL 8+ setelah backup.
-- Tidak menggantikan migration Laravel; ini skrip manual.

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------------
-- 1) Feature menu (kode = key di sidebar: user-distribusi)
--    Mengikuti pola user-pusat, user-cabang, user-adp
-- ---------------------------------------------------------------------------
INSERT INTO `features` (`title`, `code`, `type`, `created_at`, `updated_at`)
SELECT 'User Distribusi', 'user-distribusi', 'menu', NOW(), NOW()
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `features` WHERE `code` = 'user-distribusi');

-- ---------------------------------------------------------------------------
-- 2) Tambahkan id feature ke JSON `authorities.code` (tanpa duplikat)
--    Menu ini ditambahkan ke Superadmin (id=1) agar dapat menambah user
--    dengan authority_id = 5 (Distribusi).
--    Tambahkan authority id lain di bawah jika diperlukan.
-- ---------------------------------------------------------------------------
SET @udist_fid = (SELECT `id` FROM `features` WHERE `code` = 'user-distribusi' LIMIT 1);

-- Superadmin (id = 1)
UPDATE `authorities`
SET `code` = JSON_ARRAY_APPEND(`code`, '$', CAST(@udist_fid AS CHAR))
WHERE `id` = 1
  AND JSON_VALID(`code`)
  AND @udist_fid IS NOT NULL
  AND NOT JSON_CONTAINS(`code`, JSON_QUOTE(CAST(@udist_fid AS CHAR)), '$');

-- Jika ada authority lain yang harus melihat menu User Distribusi, duplikat UPDATE di bawah:
-- UPDATE `authorities`
-- SET `code` = JSON_ARRAY_APPEND(`code`, '$', CAST(@udist_fid AS CHAR))
-- WHERE `id` = ?
--   AND JSON_VALID(`code`)
--   AND @udist_fid IS NOT NULL
--   AND NOT JSON_CONTAINS(`code`, JSON_QUOTE(CAST(@udist_fid AS CHAR)), '$');
