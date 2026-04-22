-- =============================================================================
-- MySQL (CRM): tabel hasil sinkron Nota Terima dari PostgreSQL staging
-- Sinkron: PostgreSQL m_terima_buku / d_terima_buku → MySQL tabel di bawah
--
-- Jalankan manual di database MySQL CRM jika tabel belum ada (alternatif:
-- `php artisan migrate` memakai migration yang sama strukturnya).
-- =============================================================================

CREATE TABLE IF NOT EXISTS `m_terima_buku` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `nota_kirim_cab` VARCHAR(100) NULL,
    `receive_code` VARCHAR(100) NOT NULL,
    `branch_code` VARCHAR(100) NULL,
    `retur_date` DATE NULL,
    `send_date` DATE NULL,
    `info` VARCHAR(500) NULL,
    `branch_sender` VARCHAR(100) NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `m_terima_buku_receive_code_unique` (`receive_code`),
    KEY `m_terima_buku_nota_kirim_cab_index` (`nota_kirim_cab`),
    KEY `m_terima_buku_branch_code_index` (`branch_code`),
    KEY `m_terima_buku_branch_sender_index` (`branch_sender`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `d_terima_buku` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `nota_kirim_cab` VARCHAR(100) NOT NULL,
    `book_code` VARCHAR(100) NOT NULL,
    `book_price` VARCHAR(100) NULL,
    `koli` DECIMAL(20, 0) NOT NULL DEFAULT 0,
    `exemplar` DECIMAL(20, 0) NOT NULL DEFAULT 0,
    `total_exemplar` DECIMAL(20, 0) NOT NULL DEFAULT 0,
    `volume` DECIMAL(20, 0) NOT NULL DEFAULT 0,
    `branch_sender` VARCHAR(100) NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `d_terima_buku_nota_book_unique` (`nota_kirim_cab`, `book_code`),
    KEY `d_terima_buku_book_code_index` (`book_code`),
    KEY `d_terima_buku_branch_sender_index` (`branch_sender`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
