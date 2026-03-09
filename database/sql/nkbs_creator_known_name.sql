-- Tambah kolom creator_name dan known_name di nkbs (setelah note)

ALTER TABLE `nkbs`
  ADD `creator_name` VARCHAR(255) NOT NULL DEFAULT '' AFTER `note`,
  ADD `known_name` VARCHAR(255) NOT NULL DEFAULT '' AFTER `creator_name`;
