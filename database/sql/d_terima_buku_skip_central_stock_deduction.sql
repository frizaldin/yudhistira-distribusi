-- Kolom opsi per baris NTB: jika 1, baris ini tidak membuat pengurangan stock pusat
-- (tidak ada baris di central_stock_deductions dengan source_type = ntb untuk id detail tersebut).

ALTER TABLE `d_terima_buku`
ADD COLUMN `skip_central_stock_deduction` TINYINT(1) NOT NULL DEFAULT 0
COMMENT '1 = tidak mengurangi stock pusat (tanpa central_stock_deductions)'
AFTER `branch_sender`;
