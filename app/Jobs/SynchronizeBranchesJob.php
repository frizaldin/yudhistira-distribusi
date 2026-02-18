<?php

namespace App\Jobs;

use App\Models\Branch;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class SynchronizeBranchesJob implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    public function __construct()
    {
        //
    }

    public function handle(): void
    {
        $cacheKey = 'sync_branches_progress';

        try {
            Log::info('SynchronizeBranchesJob: Starting synchronization from PostgreSQL');

            $totalRecords = DB::connection('pgsql')->table('m_cabang')->count();

            Cache::put($cacheKey, [
                'status' => 'running',
                'total' => $totalRecords,
                'processed' => 0,
                'created' => 0,
                'updated' => 0,
                'errors' => 0,
                'percentage' => 0
            ], now()->addHours(2));

            $chunkSize = 500;
            $created = 0;
            $updated = 0;
            $errors = [];

            $offset = 0;
            $totalProcessed = 0;

            while (true) {
                $branchRecords = DB::connection('pgsql')
                    ->table('m_cabang')
                    ->orderBy('branch_code')
                    ->offset($offset)
                    ->limit($chunkSize)
                    ->get();

                if ($branchRecords->isEmpty()) {
                    break;
                }

                foreach ($branchRecords as $branchData) {
                    try {
                        $branch = (array) $branchData;

                        $removeQuotes = function ($value) {
                            if (empty($value)) return $value;
                            $value = trim($value, " '\"\`");
                            $value = preg_replace('/^[\'"`]+|[\'"`]+$/u', '', $value);
                            return trim($value);
                        };

                        $branchCode = isset($branch['branch_code']) ? $removeQuotes($branch['branch_code']) : null;
                        $branchNameRaw = isset($branch['branch_name']) ? $branch['branch_name'] : '';

                        $existingBranch = Branch::where('branch_code', $branchCode)->first();

                        $branchName = !empty(trim($branchNameRaw)) ? $removeQuotes($branchNameRaw) : 'Undefined';

                        $branchDataArray = [
                            'branch_code' => $branchCode,
                            'branch_name' => $branchName,
                            'address' => isset($branch['address']) ? $removeQuotes($branch['address']) : null,
                            'phone_no' => isset($branch['phone_no']) ? $removeQuotes($branch['phone_no']) : null,
                            'contact_person' => isset($branch['contact_person']) ? $removeQuotes($branch['contact_person']) : null,
                            'fax_no' => isset($branch['fax_no']) ? $removeQuotes($branch['fax_no']) : null,
                            'warehouse_head' => isset($branch['warehouse_head']) ? $removeQuotes($branch['warehouse_head']) : null,
                            'city' => isset($branch['city']) ? $removeQuotes($branch['city']) : null,
                            'email_address' => isset($branch['email_address']) ? $removeQuotes($branch['email_address']) : null,
                            'area_code' => isset($branch['area_code']) ? $removeQuotes($branch['area_code']) : null,
                            'active' => $branch['active'] ?? 1,
                            'ans_code' => isset($branch['ans_code']) ? $removeQuotes($branch['ans_code']) : null,
                            'branch_head' => isset($branch['branch_head']) ? $removeQuotes($branch['branch_head']) : null,
                            'region' => isset($branch['region']) ? $removeQuotes($branch['region']) : null,
                            'warehouse_code' => isset($branch['warehouse_code']) ? $removeQuotes($branch['warehouse_code']) : null,
                            'warehouse_code2' => isset($branch['warehouse_code2']) ? $removeQuotes($branch['warehouse_code2']) : null,
                            'tanggal_aktif' => isset($branch['tanggal_aktif']) && !empty($branch['tanggal_aktif']) ? $branch['tanggal_aktif'] : null,
                        ];

                        if ($existingBranch) {
                            $existingBranch->update($branchDataArray);
                            $updated++;
                        } else {
                            Branch::create($branchDataArray);
                            $created++;
                        }
                        $totalProcessed++;
                    } catch (\Exception $e) {
                        $branchCodeError = $branch['branch_code'] ?? 'unknown';
                        $errors[] = "Error pada branch_code {$branchCodeError}: " . $e->getMessage();
                        Log::error("Sync Error untuk branch_code {$branchCodeError}: " . $e->getMessage());
                    }
                }

                $offset += $chunkSize;

                $percentage = $totalRecords > 0 ? round(($totalProcessed / $totalRecords) * 100, 2) : 0;
                Cache::put($cacheKey, [
                    'status' => 'running',
                    'total' => $totalRecords,
                    'processed' => $totalProcessed,
                    'created' => $created,
                    'updated' => $updated,
                    'errors' => count($errors),
                    'percentage' => $percentage
                ], now()->addHours(2));

                if ($totalProcessed % 1000 == 0) {
                    Log::info("SynchronizeBranchesJob: Processed {$totalProcessed}/{$totalRecords} records ({$percentage}%)");
                }
            }

            Cache::put($cacheKey, [
                'status' => 'completed',
                'total' => $totalRecords,
                'processed' => $totalProcessed,
                'created' => $created,
                'updated' => $updated,
                'errors' => count($errors),
                'percentage' => 100,
                'completed_at' => now()->toDateTimeString()
            ], now()->addHours(2));
            
            // Save last sync timestamp
            Cache::put($cacheKey . '_last_sync', now()->toDateTimeString(), now()->addDays(30));

            Log::info('SynchronizeBranchesJob: Synchronization completed', [
                'created' => $created,
                'updated' => $updated,
                'errors_count' => count($errors)
            ]);

            if (count($errors) > 0) {
                Log::warning('SynchronizeBranchesJob errors: ', $errors);
            }
        } catch (\Exception $e) {
            $currentProgress = Cache::get($cacheKey, []);
            Cache::put($cacheKey, [
                'status' => 'failed',
                'total' => $currentProgress['total'] ?? 0,
                'processed' => $currentProgress['processed'] ?? 0,
                'created' => $currentProgress['created'] ?? 0,
                'updated' => $currentProgress['updated'] ?? 0,
                'errors' => $currentProgress['errors'] ?? 0,
                'percentage' => $currentProgress['percentage'] ?? 0,
                'error_message' => $e->getMessage()
            ], now()->addHours(2));

            Log::error('SynchronizeBranchesJob Error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }
}
