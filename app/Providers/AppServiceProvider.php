<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Pastikan direktori storage yang dipakai Laravel ada (mencegah FileNotFoundException compiled view di server)
        $dirs = [
            storage_path('framework/temp'),
            storage_path('framework/views'),
            storage_path('framework/sessions'),
            storage_path('framework/cache/data'),
        ];
        foreach ($dirs as $dir) {
            if (!is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }
        }

        // Pakai direktori temp aplikasi agar tidak trigger notice "tempnam(): file created in system's temporary directory"
        $tempDir = storage_path('framework/temp');
        if (is_dir($tempDir) && is_writable($tempDir)) {
            @ini_set('upload_tmp_dir', $tempDir);
        }

        // Suppress hanya notice/warning tempnam system temp (dari PHP/vendor), bukan error lain
        set_error_handler(function ($severity, $message, $file, $line) {
            $isTempnamNotice = (strpos($message, 'tempnam():') !== false && strpos($message, "system's temporary directory") !== false);
            if ($isTempnamNotice && in_array($severity, [E_NOTICE, E_WARNING], true)) {
                return true; // suppress
            }
            return false; // pass to default handler
        }, E_NOTICE | E_WARNING);
    }
}
