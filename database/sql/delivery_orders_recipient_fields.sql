-- ============================================================================
-- Tambah field baru ke tabel delivery_orders:
--   recipient_name  : Nama penerima
--   recipient_phone : No telepon penerima
--   recipient_address : Alamat penerima
-- ============================================================================

SET NAMES utf8mb4;

ALTER TABLE `delivery_orders`
    ADD COLUMN `recipient_name`    varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `known_name`,
    ADD COLUMN `recipient_phone`   varchar(50)  COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `recipient_name`,
    ADD COLUMN `recipient_address` text         COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `recipient_phone`;
-- ============================================================================
-- Selesai. Kolom baru:
--   recipient_name    varchar(255) nullable
--   recipient_phone   varchar(50)  nullable
--   recipient_address text         nullable
-- ============================================================================
