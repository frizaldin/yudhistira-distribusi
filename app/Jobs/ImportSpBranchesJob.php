<?php

namespace App\Jobs;

use App\Imports\SpBranchesImport;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\File;
use Maatwebsite\Excel\Facades\Excel;

class ImportSpBranchesJob implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    protected $filePath;
    protected $disk;

    /**
     * Create a new job instance.
     */
    public function __construct(string $filePath, string $disk = 'local')
    {
        $this->filePath = $filePath;
        $this->disk = $disk;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            // Pastikan file masih ada
            if (!file_exists($this->filePath)) {
                Log::error('ImportSpBranchesJob Error: File not found', [
                    'file_path' => $this->filePath
                ]);
                return;
            }

            Log::info('ImportSpBranchesJob: Starting import', [
                'file_path' => $this->filePath,
                'file_size' => filesize($this->filePath)
            ]);

            // Gunakan File object untuk absolute path
            // Ini akan membuat Maatwebsite Excel membaca file langsung tanpa masalah disk
            $file = new File($this->filePath);
            
            Log::info('ImportSpBranchesJob: Using File object for import', [
                'file_path' => $this->filePath,
                'file_exists' => file_exists($this->filePath)
            ]);
            
            // Import dengan chunking per 100 data
            // Menggunakan File object akan menghindari masalah path dengan disk 'local'
            Excel::import(new SpBranchesImport, $file);

            Log::info('ImportSpBranchesJob: Import completed successfully', [
                'file_path' => $this->filePath
            ]);
        } catch (\Exception $e) {
            Log::error('ImportSpBranchesJob Error: ' . $e->getMessage(), [
                'file_path' => $this->filePath,
                'trace' => $e->getTraceAsString()
            ]);
            throw $e; // Re-throw agar job bisa di-retry jika perlu
        }
    }
}
