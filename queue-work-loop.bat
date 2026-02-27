@echo off
REM Jalankan queue worker; kalau mati (setelah 1 job atau error) jalan lagi.
echo Queue worker (restart otomatis). Tekan Ctrl+C untuk berhenti.
:loop
php artisan queue:work --memory=512 --sleep=3
echo Worker berhenti. Restart dalam 2 detik...
timeout /t 2 /nobreak >nul
goto loop
