<?php

namespace App\Jobs;

use App\Models\Employee;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class SynchronizeEmployeesJob implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    public function __construct()
    {
        //
    }

    public function handle(): void
    {
        $cacheKey = 'sync_employees_progress';

        try {
            Log::info('SynchronizeEmployeesJob: Starting synchronization from PostgreSQL');

            $totalRecords = DB::connection('pgsql')->table('m_employee')->count();

            Cache::put($cacheKey, [
                'status'     => 'running',
                'total'      => $totalRecords,
                'processed'  => 0,
                'created'    => 0,
                'updated'    => 0,
                'errors'     => 0,
                'percentage' => 0,
            ], now()->addHours(2));

            $chunkSize     = 500;
            $created       = 0;
            $updated       = 0;
            $errors        = [];
            $offset        = 0;
            $totalProcessed = 0;

            while (true) {
                $records = DB::connection('pgsql')
                    ->table('m_employee')
                    ->orderBy('empl_code')
                    ->offset($offset)
                    ->limit($chunkSize)
                    ->get();

                if ($records->isEmpty()) {
                    break;
                }

                foreach ($records as $row) {
                    try {
                        $data = (array) $row;

                        $employeeData = [
                            'empl_code'      => $data['empl_code']      ?? null,
                            'empl_name'      => $data['empl_name']      ?? null,
                            'address'        => $data['address']        ?? null,
                            'city'           => $data['city']           ?? null,
                            'sex_id'         => $data['sex_id']         ?? null,
                            'stat_id'        => $data['stat_id']        ?? null,
                            'religion_id'    => $data['religion_id']    ?? null,
                            'zip_code'       => $data['zip_code']       ?? null,
                            'marital_id'     => $data['marital_id']     ?? null,
                            'grade_id'       => $data['grade_id']       ?? null,
                            'dept_id'        => $data['dept_id']        ?? null,
                            'salary'         => $data['salary']         ?? null,
                            'join_date'      => $data['join_date']      ?? null,
                            'resign_date'    => $data['resign_date']    ?? null,
                            'branch_code'    => $data['branch_code']    ?? null,
                            'birth_date'     => $data['birth_date']     ?? null,
                            'photo'          => $data['photo']          ?? null,
                            'phone_no'       => $data['phone_no']       ?? null,
                            'edu_background' => $data['edu_background'] ?? null,
                            'active'         => $data['active']         ?? 0,
                            'ktp'            => $data['ktp']            ?? null,
                            'spv_code'       => $data['spv_code']       ?? null,
                        ];

                        $existing = Employee::where('empl_code', $employeeData['empl_code'])->first();

                        if ($existing) {
                            $existing->update($employeeData);
                            $updated++;
                        } else {
                            Employee::create($employeeData);
                            $created++;
                        }

                        $totalProcessed++;
                    } catch (\Exception $e) {
                        $emplCode = $data['empl_code'] ?? 'unknown';
                        $errors[] = "Error pada empl_code {$emplCode}: " . $e->getMessage();
                        Log::error("SynchronizeEmployeesJob Error untuk empl_code {$emplCode}: " . $e->getMessage());
                    }
                }

                $offset += $chunkSize;

                $percentage = $totalRecords > 0 ? round(($totalProcessed / $totalRecords) * 100, 2) : 0;
                Cache::put($cacheKey, [
                    'status'     => 'running',
                    'total'      => $totalRecords,
                    'processed'  => $totalProcessed,
                    'created'    => $created,
                    'updated'    => $updated,
                    'errors'     => count($errors),
                    'percentage' => $percentage,
                ], now()->addHours(2));

                if ($totalProcessed % 1000 == 0) {
                    Log::info("SynchronizeEmployeesJob: Processed {$totalProcessed}/{$totalRecords} ({$percentage}%)");
                }
            }

            Cache::put($cacheKey, [
                'status'       => 'completed',
                'total'        => $totalRecords,
                'processed'    => $totalProcessed,
                'created'      => $created,
                'updated'      => $updated,
                'errors'       => count($errors),
                'percentage'   => 100,
                'completed_at' => now()->toDateTimeString(),
            ], now()->addHours(2));

            Cache::put($cacheKey . '_last_sync', now()->toDateTimeString(), now()->addDays(30));

            Log::info('SynchronizeEmployeesJob: Completed', [
                'created'      => $created,
                'updated'      => $updated,
                'errors_count' => count($errors),
            ]);

            if (count($errors) > 0) {
                Log::warning('SynchronizeEmployeesJob errors: ', $errors);
            }
        } catch (\Exception $e) {
            $current = Cache::get($cacheKey, []);
            Cache::put($cacheKey, [
                'status'        => 'failed',
                'total'         => $current['total']     ?? 0,
                'processed'     => $current['processed'] ?? 0,
                'created'       => $current['created']   ?? 0,
                'updated'       => $current['updated']   ?? 0,
                'errors'        => $current['errors']    ?? 0,
                'percentage'    => $current['percentage'] ?? 0,
                'error_message' => $e->getMessage(),
            ], now()->addHours(2));

            Log::error('SynchronizeEmployeesJob Error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }
}
