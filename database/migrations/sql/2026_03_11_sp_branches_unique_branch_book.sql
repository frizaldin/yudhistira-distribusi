-- ============================================================
-- sp_branches: hapus duplikat + unique (branch_code, book_code)
-- Satu baris per cabang+buku, sync pakai upsert.
-- ============================================================

-- 1. Hapus duplikat (simpan baris dengan id terkecil per branch_code + book_code)
DELETE t1 FROM sp_branches t1
INNER JOIN sp_branches t2
ON t1.branch_code = t2.branch_code AND t1.book_code = t2.book_code AND t1.id > t2.id;

-- 2. Tambah unique index
ALTER TABLE sp_branches
ADD UNIQUE KEY sp_branches_branch_book_unique (branch_code, book_code);


-- ---------- ROLLBACK (jika perlu buang unique lagi) ----------
-- ALTER TABLE sp_branches DROP INDEX sp_branches_branch_book_unique;
