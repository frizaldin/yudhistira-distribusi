UPDATE `features` SET `title` = 'Worksheet' WHERE `features`.`id` = 12;

UPDATE `features` SET `title` = 'Worksheet Area' WHERE `features`.`id` = 13;

INSERT INTO `features` (`id`, `title`, `code`, `type`, `created_at`, `updated_at`) VALUES (NULL, 'NPPB', 'preparation_notes', 'menu', '2026-02-02 01:37:17', '2026-02-02 01:37:17');

UPDATE `authorities` SET `code` = '["1","2","3","4","5","6","7","8","9","10","11","12","13","14","18"]' WHERE `authorities`.`id` = 4;

ALTER TABLE `nppb_centrals` ADD `created_by` BIGINT NULL AFTER `updated_at`;

UPDATE `authorities` SET `code` = '["1","2","3","4","5","6","7","8","9","10","11","12","13","14","15","16","17","18"]' WHERE `authorities`.`id` = 1;

ALTER TABLE `nppb_centrals` ADD `stack` VARCHAR(255) NULL AFTER `id`;

ALTER TABLE `nppb_centrals` ADD `document_id` BIGINT NULL AFTER `id`;

CREATE TABLE `nppb_documents` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `number` VARCHAR(20) NOT NULL,
  `note` TEXT NOT NULL,
  `sender_code` VARCHAR(255) NOT NULL,
  `recipient_code` VARCHAR(255) NOT NULL,
  `send_date` DATE NOT NULL,
  `total_type_books` BIGINT NOT NULL,
  `total_exemplar` BIGINT NOT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  `created_by` BIGINT UNSIGNED NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_0900_ai_ci;

-- Jika tabel sudah ada dengan kolom recipient (TEXT), jalankan untuk migrasi:
-- ALTER TABLE `nppb_documents` ADD `sender_code` VARCHAR(255) NULL AFTER `note`, ADD `recipient_code` VARCHAR(255) NULL AFTER `sender_code`;
-- UPDATE `nppb_documents` SET `sender_code` = '', `recipient_code` = '' WHERE `sender_code` IS NULL;
-- ALTER TABLE `nppb_documents` MODIFY `sender_code` VARCHAR(255) NOT NULL, MODIFY `recipient_code` VARCHAR(255) NOT NULL;
-- ALTER TABLE `nppb_documents` DROP COLUMN `recipient`;
