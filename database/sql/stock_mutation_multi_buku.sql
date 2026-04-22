-- ============================================================================
-- Stock Mutation Multi-Buku (maks 25 buku per mutasi)
-- Jalankan di MySQL 8+ setelah backup.
-- Tidak menggantikan migration Laravel; ini skrip manual.
-- ============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------------------------------------------------------
-- 1) Buat tabel stock_mutation_items (detail buku per mutasi)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `stock_mutation_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `mutation_id` bigint unsigned NOT NULL,
  `book_code` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `koli` int unsigned NOT NULL DEFAULT 0,
  `isi_koli` int unsigned NOT NULL DEFAULT 0,
  `eceran` int unsigned NOT NULL DEFAULT 0,
  `total_eksemplar` bigint unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `smi_mutation_id_index` (`mutation_id`),
  KEY `smi_book_code_index` (`book_code`),
  CONSTRAINT `smi_mutation_id_foreign` FOREIGN KEY (`mutation_id`) REFERENCES `stock_mutations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `smi_book_code_foreign` FOREIGN KEY (`book_code`) REFERENCES `books` (`book_code`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 2) Migrasi data lama: pindahkan kolom buku dari stock_mutations ke items
--    (hanya bila kolom lama masih ada)
-- ----------------------------------------------------------------------------
INSERT INTO `stock_mutation_items`
  (`mutation_id`, `book_code`, `koli`, `isi_koli`, `eceran`, `total_eksemplar`, `created_at`, `updated_at`)
SELECT
  `id`,
  `book_code`,
  COALESCE(`koli`, 0),
  COALESCE(`isi_koli`, 0),
  COALESCE(`eceran`, 0),
  COALESCE(`total_eksemplar`, 0),
  `created_at`,
  `updated_at`
FROM `stock_mutations`
WHERE `book_code` IS NOT NULL
  -- Hindari duplikat jika script dijalankan lebih dari sekali
  AND `id` NOT IN (SELECT DISTINCT `mutation_id` FROM `stock_mutation_items`);

-- ----------------------------------------------------------------------------
-- 3) Hapus foreign key lama (book_code di stock_mutations) jika ada
-- ----------------------------------------------------------------------------
-- MySQL tidak mendukung DROP FOREIGN KEY IF EXISTS secara langsung,
-- gunakan stored procedure sementara untuk mengecek.
DROP PROCEDURE IF EXISTS drop_fk_if_exists;
DELIMITER //
CREATE PROCEDURE drop_fk_if_exists()
BEGIN
  IF EXISTS (
    SELECT 1 FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
      AND TABLE_NAME = 'stock_mutations'
      AND CONSTRAINT_NAME = 'stock_mutations_book_code_foreign'
      AND CONSTRAINT_TYPE = 'FOREIGN KEY'
  ) THEN
    ALTER TABLE `stock_mutations` DROP FOREIGN KEY `stock_mutations_book_code_foreign`;
  END IF;
END //
DELIMITER ;
CALL drop_fk_if_exists();
DROP PROCEDURE IF EXISTS drop_fk_if_exists;

-- ----------------------------------------------------------------------------
-- 4) Hapus kolom buku dari stock_mutations (jadikan murni header)
--    Jalankan setiap ALTER secara kondisional via stored procedure
-- ----------------------------------------------------------------------------
DROP PROCEDURE IF EXISTS alter_stock_mutations_header;
DELIMITER //
CREATE PROCEDURE alter_stock_mutations_header()
BEGIN
  -- Hapus kolom book_code
  IF EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'stock_mutations' AND COLUMN_NAME = 'book_code'
  ) THEN
    ALTER TABLE `stock_mutations` DROP COLUMN `book_code`;
  END IF;

  -- Hapus index book_code jika masih ada
  IF EXISTS (
    SELECT 1 FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'stock_mutations' AND INDEX_NAME = 'stock_mutations_book_code_index'
  ) THEN
    ALTER TABLE `stock_mutations` DROP INDEX `stock_mutations_book_code_index`;
  END IF;

  -- Hapus kolom koli
  IF EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'stock_mutations' AND COLUMN_NAME = 'koli'
  ) THEN
    ALTER TABLE `stock_mutations` DROP COLUMN `koli`;
  END IF;

  -- Hapus kolom isi_koli
  IF EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'stock_mutations' AND COLUMN_NAME = 'isi_koli'
  ) THEN
    ALTER TABLE `stock_mutations` DROP COLUMN `isi_koli`;
  END IF;

  -- Hapus kolom eceran
  IF EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'stock_mutations' AND COLUMN_NAME = 'eceran'
  ) THEN
    ALTER TABLE `stock_mutations` DROP COLUMN `eceran`;
  END IF;

  -- Hapus kolom total_eksemplar
  IF EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'stock_mutations' AND COLUMN_NAME = 'total_eksemplar'
  ) THEN
    ALTER TABLE `stock_mutations` DROP COLUMN `total_eksemplar`;
  END IF;
END //
DELIMITER ;
CALL alter_stock_mutations_header();
DROP PROCEDURE IF EXISTS alter_stock_mutations_header;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================================
-- Selesai. Struktur akhir:
--   stock_mutations  : id, nama_pt_produksi, tanggal_penerimaan, nama_penerima,
--                      nomor_surat_jalan, nomor_jo, keterangan, created_by,
--                      created_at, updated_at
--   stock_mutation_items : id, mutation_id FK, book_code FK, koli, isi_koli,
--                          eceran, total_eksemplar, created_at, updated_at
-- ============================================================================
