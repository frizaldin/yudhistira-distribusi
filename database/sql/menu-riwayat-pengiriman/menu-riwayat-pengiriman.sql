-- Menu: Riwayat NKB / NPPB / Surat Jalan (feature code = riwayat_pengiriman, sama dengan key sidebar)
-- Jalankan manual di MySQL 8+ setelah backup.

SET NAMES utf8mb4;

INSERT INTO `features` (`title`, `code`, `type`, `created_at`, `updated_at`)
SELECT 'Riwayat NKB / NPPB / SJ', 'riwayat_pengiriman', 'menu', NOW(), NOW()
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `features` WHERE `code` = 'riwayat_pengiriman');

SET @fid = (SELECT `id` FROM `features` WHERE `code` = 'riwayat_pengiriman' LIMIT 1);

UPDATE `authorities`
SET `code` = JSON_ARRAY_APPEND(`code`, '$', CAST(@fid AS CHAR))
WHERE `id` IN (1, 3, 4)
  AND JSON_VALID(`code`)
  AND @fid IS NOT NULL
  AND NOT JSON_CONTAINS(`code`, JSON_QUOTE(CAST(@fid AS CHAR)), '$');

-- Tambahkan authority id lain sesuai kebutuhan (duplikat UPDATE dengan id berbeda).
