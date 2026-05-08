-- =============================================================================
-- MySQL (CRM): tabel hasil sinkron 3 staging baru dari PostgreSQL
-- 1. Gudang Isolasi (m_pindah_gudang, d_pindah_gudang)
-- 2. Nota Promosi (m_kirim_promosi, d_kirim_promosi)
-- 3. Nota Penghapusan (m_hapus_barang, d_hapus_barang)
-- =============================================================================

-- 1. Gudang Isolasi
CREATE TABLE IF NOT EXISTS `m_pindah_gudang` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `move_code` VARCHAR(100) NOT NULL,
    `branch_code` VARCHAR(100) NULL,
    `info` TEXT NULL,
    `mova_date` DATE NULL,
    `officer` VARCHAR(100) NULL,
    `printed` INT NULL DEFAULT 0,
    `status` INT NULL DEFAULT 0,
    `user_id` VARCHAR(100) NULL,
    `whouse_head` VARCHAR(100) NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `m_pindah_gudang_move_code_unique` (`move_code`),
    KEY `m_pindah_gudang_branch_code_index` (`branch_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `d_pindah_gudang` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `move_code` VARCHAR(100) NOT NULL,
    `branch_code` VARCHAR(100) NULL,
    `book_code` VARCHAR(100) NOT NULL,
    `exemplar` DECIMAL(20, 0) NOT NULL DEFAULT 0,
    `koli` DECIMAL(20, 0) NOT NULL DEFAULT 0,
    `total_exemplar` DECIMAL(20, 0) NOT NULL DEFAULT 0,
    `volume` DECIMAL(20, 0) NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    UNIQUE KEY `d_pindah_gudang_move_book_unique` (`move_code`, `book_code`),
    KEY `d_pindah_gudang_book_code_index` (`book_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Nota Promosi
CREATE TABLE IF NOT EXISTS `m_kirim_promosi` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `nota_kirim_promo` VARCHAR(100) NOT NULL,
    `approve_by` VARCHAR(100) NULL,
    `branch_sender` VARCHAR(100) NULL,
    `deliver_by` VARCHAR(100) NULL,
    `info` TEXT NULL,
    `printed` INT NULL DEFAULT 0,
    `sales_code` VARCHAR(100) NULL,
    `send_date` DATE NULL,
    `status` INT NULL DEFAULT 0,
    `user_id` VARCHAR(100) NULL,
    `whouse_head` VARCHAR(100) NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `m_kirim_promosi_nota_unique` (`nota_kirim_promo`),
    KEY `m_kirim_promosi_branch_sender_index` (`branch_sender`),
    KEY `m_kirim_promosi_sales_code_index` (`sales_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `d_kirim_promosi` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `nota_kirim_promo` VARCHAR(100) NOT NULL,
    `book_code` VARCHAR(100) NOT NULL,
    `book_price` VARCHAR(100) NULL,
    `branch_sender` VARCHAR(100) NULL,
    `exemplar` DECIMAL(20, 0) NOT NULL DEFAULT 0,
    `koli` DECIMAL(20, 0) NOT NULL DEFAULT 0,
    `total_exemplar` DECIMAL(20, 0) NOT NULL DEFAULT 0,
    `volume` DECIMAL(20, 0) NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    UNIQUE KEY `d_kirim_promosi_nota_book_unique` (`nota_kirim_promo`, `book_code`),
    KEY `d_kirim_promosi_book_code_index` (`book_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Nota Penghapusan
CREATE TABLE IF NOT EXISTS `m_hapus_barang` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `erase_code` VARCHAR(100) NOT NULL,
    `branch_code` VARCHAR(100) NULL,
    `edit_date` DATE NULL,
    `empl_code` VARCHAR(100) NULL,
    `info` TEXT NULL,
    `printed` INT NULL DEFAULT 0,
    `status` INT NULL DEFAULT 0,
    `trans_date` DATE NULL,
    `user_id` VARCHAR(100) NULL,
    `whouse_head` VARCHAR(100) NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `m_hapus_barang_erase_code_unique` (`erase_code`),
    KEY `m_hapus_barang_branch_code_index` (`branch_code`),
    KEY `m_hapus_barang_empl_code_index` (`empl_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `d_hapus_barang` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `erase_code` VARCHAR(100) NOT NULL,
    `book_code` VARCHAR(100) NOT NULL,
    `book_price` VARCHAR(100) NULL,
    `branch_code` VARCHAR(100) NULL,
    `exemplar` DECIMAL(20, 0) NOT NULL DEFAULT 0,
    `koli` DECIMAL(20, 0) NOT NULL DEFAULT 0,
    `total_exemplar` DECIMAL(20, 0) NOT NULL DEFAULT 0,
    `volume` DECIMAL(20, 0) NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    UNIQUE KEY `d_hapus_barang_erase_book_unique` (`erase_code`, `book_code`),
    KEY `d_hapus_barang_book_code_index` (`book_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
