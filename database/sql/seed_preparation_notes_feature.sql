-- Preparation Notes: tambah menu feature (jalankan sekali)
-- Untuk penambahan ke DB selanjutnya, buat file SQL baru di database/sql/

INSERT INTO `features` (`title`, `code`, `type`, `created_at`, `updated_at`)
SELECT 'Preparation Notes', 'preparation_notes', 'menu', NOW(), NOW()
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `features` WHERE `code` = 'preparation_notes' LIMIT 1);
