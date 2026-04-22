-- ============================================================================
-- Tambah field koli, pack, terbilang ke tabel delivery_orders
-- ============================================================================

SET NAMES utf8mb4;

ALTER TABLE `delivery_orders`
    ADD COLUMN `koli`      int          UNSIGNED DEFAULT NULL AFTER `recipient_address`,
    ADD COLUMN `pack`      int          UNSIGNED DEFAULT NULL AFTER `koli`,
    ADD COLUMN `terbilang` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `pack`;

-- ============================================================================
-- Selesai. Kolom baru:
--   koli       int unsigned  nullable
--   pack       int unsigned  nullable
--   terbilang  varchar(500)  nullable  (misal: "Sepuluh Koli Sembilan Pack")
-- ============================================================================
