-- nppb_centrals: tambah kolom stack dan created_by
-- stack = penanda simpan berbarengan, format: WS + 5 digit urutan + 2 digit user id + DDMMYYYY (contoh: WS000010124022026)
-- created_by = id user yang menyimpan

ALTER TABLE `nppb_centrals`
  ADD COLUMN `stack` VARCHAR(50) NULL DEFAULT NULL AFTER `volume`,
  ADD COLUMN `created_by` BIGINT UNSIGNED NULL DEFAULT NULL AFTER `stack`;

-- Optional: index untuk filter by stack
-- CREATE INDEX `nppb_centrals_stack_index` ON `nppb_centrals` (`stack`);
