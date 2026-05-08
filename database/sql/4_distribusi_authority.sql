SET NAMES utf8mb4;

-- 1. Pastikan Otoritas Distribusi (authority_id = 5) ada di tabel authorities
INSERT INTO `authorities` (`id`, `title`, `code`, `created_at`, `updated_at`)
SELECT 5, 'Distribusi', '[]', NOW(), NOW()
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `authorities` WHERE `id` = 5);

UPDATE `authorities`
SET `code` = JSON_ARRAY_APPEND(`code`, '$', '1')
WHERE `id` = 5 AND NOT JSON_CONTAINS(`code`, '"1"', '$') AND JSON_VALID(`code`);

UPDATE `authorities`
SET `code` = JSON_ARRAY_APPEND(`code`, '$', '3')
WHERE `id` = 5 AND NOT JSON_CONTAINS(`code`, '"3"', '$') AND JSON_VALID(`code`);

UPDATE `authorities`
SET `code` = JSON_ARRAY_APPEND(`code`, '$', '5')
WHERE `id` = 5 AND NOT JSON_CONTAINS(`code`, '"5"', '$') AND JSON_VALID(`code`);

UPDATE `authorities`
SET `code` = JSON_ARRAY_APPEND(`code`, '$', '14')
WHERE `id` = 5 AND NOT JSON_CONTAINS(`code`, '"14"', '$') AND JSON_VALID(`code`);

UPDATE `authorities`
SET `code` = JSON_ARRAY_APPEND(`code`, '$', '19')
WHERE `id` = 5 AND NOT JSON_CONTAINS(`code`, '"19"', '$') AND JSON_VALID(`code`);

UPDATE `authorities`
SET `code` = JSON_ARRAY_APPEND(`code`, '$', '21')
WHERE `id` = 5 AND NOT JSON_CONTAINS(`code`, '"21"', '$') AND JSON_VALID(`code`);

UPDATE `authorities`
SET `code` = JSON_ARRAY_APPEND(`code`, '$', '23')
WHERE `id` = 5 AND NOT JSON_CONTAINS(`code`, '"23"', '$') AND JSON_VALID(`code`);

UPDATE `authorities`
SET `code` = JSON_ARRAY_APPEND(`code`, '$', '24')
WHERE `id` = 5 AND NOT JSON_CONTAINS(`code`, '"24"', '$') AND JSON_VALID(`code`);
