-- Menu Mutasi: tabel data + feature + otoritas
-- Jalankan di MySQL 8+ setelah backup.
-- Tidak menggantikan migration Laravel; ini skrip manual.

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------------
-- 1) Tabel mutasi (penambahan eksemplar ke stock pusat per kode buku)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `stock_mutations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `book_code` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `koli` int unsigned NOT NULL DEFAULT 0,
  `isi_koli` int unsigned NOT NULL DEFAULT 0,
  `eceran` int unsigned NOT NULL DEFAULT 0,
  `total_eksemplar` bigint unsigned NOT NULL DEFAULT 0,
  `nama_pt_produksi` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tanggal_penerimaan` date DEFAULT NULL,
  `nama_penerima` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nomor_surat_jalan` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nomor_jo` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `keterangan` text COLLATE utf8mb4_unicode_ci,
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `stock_mutations_book_code_index` (`book_code`),
  CONSTRAINT `stock_mutations_book_code_foreign` FOREIGN KEY (`book_code`) REFERENCES `books` (`book_code`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- 2) Feature menu (kode = key di sidebar: mutasi)
-- ---------------------------------------------------------------------------
INSERT INTO `features` (`title`, `code`, `type`, `created_at`, `updated_at`)
SELECT 'Mutasi', 'mutasi', 'menu', NOW(), NOW()
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `features` WHERE `code` = 'mutasi');

-- ---------------------------------------------------------------------------
-- 3) Tambahkan id feature ke JSON `authorities.code` (tanpa duplikat)
--    Sesuaikan daftar `id` jika role di server Anda berbeda.
-- ---------------------------------------------------------------------------
SET @mutasi_fid = (SELECT `id` FROM `features` WHERE `code` = 'mutasi' LIMIT 1);

UPDATE `authorities`
SET `code` = JSON_ARRAY_APPEND(`code`, '$', CAST(@mutasi_fid AS CHAR))
WHERE `id` IN (1, 3, 4)
  AND JSON_VALID(`code`)
  AND @mutasi_fid IS NOT NULL
  AND NOT JSON_CONTAINS(`code`, JSON_QUOTE(CAST(@mutasi_fid AS CHAR)), '$');

-- Jika ada authority lain yang harus melihat menu Mutasi, duplikat UPDATE dengan id yang sesuai,
-- atau jalankan sekali per baris:
-- UPDATE authorities SET code = JSON_ARRAY_APPEND(code, '$', CAST(@mutasi_fid AS CHAR))
-- WHERE id = ? AND JSON_VALID(code) AND NOT JSON_CONTAINS(code, JSON_QUOTE(CAST(@mutasi_fid AS CHAR)), '$');
