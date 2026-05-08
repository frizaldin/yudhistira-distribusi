
SET NAMES utf8mb4;

INSERT INTO `features` (`title`, `code`, `type`, `created_at`, `updated_at`)
SELECT 'NKB Penyesuaian', 'nkb_penyesuaian', 'menu', NOW(), NOW()
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `features` WHERE `code` = 'nkb_penyesuaian');

SET @fid = (SELECT `id` FROM `features` WHERE `code` = 'nkb_penyesuaian' LIMIT 1);

UPDATE `authorities`
SET `code` = JSON_ARRAY_APPEND(`code`, '$', CAST(@fid AS CHAR))
WHERE `id` IN (1, 3, 4)
  AND JSON_VALID(`code`)
  AND @fid IS NOT NULL
  AND NOT JSON_CONTAINS(`code`, JSON_QUOTE(CAST(@fid AS CHAR)), '$');