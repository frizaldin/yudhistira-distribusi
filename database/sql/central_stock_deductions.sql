-- Tabel pengurangan stock pusat karena NKB / Delivery Order (eksemplar yang sudah keluar).
-- Digunakan agar di nppb-central dan nppb-warehouse, stock pusat yang tampil = central_stocks - deduction per book_code.

CREATE TABLE `central_stock_deductions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `book_code` VARCHAR(100) NOT NULL,
  `quantity` DECIMAL(20, 0) NOT NULL DEFAULT 0 COMMENT 'Eksemplar yang dikurangi',
  `source_type` VARCHAR(50) NOT NULL COMMENT 'nkb, delivery_order',
  `source_id` VARCHAR(100) NOT NULL COMMENT 'Nomor NKB atau ID Delivery Order',
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `central_stock_deductions_book_code_source_type_index` (`book_code`, `source_type`),
  CONSTRAINT `central_stock_deductions_book_code_foreign` FOREIGN KEY (`book_code`) REFERENCES `books` (`book_code`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
