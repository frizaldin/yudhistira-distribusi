-- Tambah kolom creator_name dan known_name di delivery_orders (setelah note)

ALTER TABLE `delivery_orders`
  ADD `creator_name` VARCHAR(255) NOT NULL DEFAULT '' AFTER `note`,
  ADD `known_name` VARCHAR(255) NOT NULL DEFAULT '' AFTER `creator_name`;
