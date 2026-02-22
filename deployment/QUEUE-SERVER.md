# Menjalankan Queue Worker di Server (Otomatis)

Agar halaman **Rekapitulasi** (/recap) tidak 500, job rekap harus diproses oleh worker. Ada dua cara tanpa harus menjalankan `php artisan queue:work` manual.

---

## Opsi 1: Cron + Scheduler (disarankan, tanpa akses root)

Cukup **satu baris cron** di server. Laravel akan menjalankan queue worker setiap menit lewat scheduler.

### Langkah

1. Buka crontab (biasanya tidak butuh root):
   ```bash
   crontab -e
   ```

2. Tambahkan baris ini (ganti `/var/www/yudhistira-distribusi` dengan path project kamu):
   ```cron
   * * * * * cd /var/www/yudhistira-distribusi && php artisan schedule:run >> /dev/null 2>&1
   ```

3. Simpan. Setiap menit cron akan menjalankan `schedule:run`, dan scheduler akan memproses queue (job rekap) maksimal ~55 detik lalu berhenti.

**Keuntungan:** Tidak perlu Supervisor, tidak perlu proses yang jalan terus. Cukup akses crontab (biasanya user biasa bisa).

---

## Opsi 2: Supervisor (jika ada akses root/sudo)

Worker jalan terus, otomatis restart kalau crash.

1. Install Supervisor (Debian/Ubuntu):
   ```bash
   sudo apt install supervisor
   ```

2. Buat config (ganti user, path, dan jumlah worker jika perlu):
   ```bash
   sudo nano /etc/supervisor/conf.d/laravel-worker.conf
   ```
   Isi:
   ```ini
   [program:laravel-worker]
   process_name=%(program_name)s_%(process_num)02d
   command=php /var/www/yudhistira-distribusi/artisan queue:work database --sleep=3 --tries=3 --max-time=3600
   autostart=true
   autorestart=true
   user=www-data
   numprocs=1
   redirect_stderr=true
   stdout_logfile=/var/www/yudhistira-distribusi/storage/logs/worker.log
   stopwaitsecs=3600
   ```

3. Jalankan:
   ```bash
   sudo supervisorctl reread
   sudo supervisorctl update
   sudo supervisorctl start laravel-worker:*
   ```

4. Cek status:
   ```bash
   sudo supervisorctl status
   ```

---

## Cek queue

- Lihat job yang antri: tabel `jobs` di database.
- Setelah cron/supervisor jalan, buka `/recap` → halaman "sedang diproses" → refresh ~15 detik → data rekap muncul (dari cache).
