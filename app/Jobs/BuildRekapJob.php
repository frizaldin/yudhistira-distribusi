<?php

namespace App\Jobs;

use App\Http\Controllers\RekapController;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

class BuildRekapJob implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    public int $userId;
    public string $year;
    public string $filterBookCode;
    public int $role;
    public ?string $userBranchCode;
    public ?array $filteredBranchCodes;
    public string $callbackfolder;

    public int $timeout = 180;

    public function __construct(
        int $userId,
        string $year,
        string $filterBookCode,
        int $role,
        ?string $userBranchCode,
        ?array $filteredBranchCodes,
        string $callbackfolder = 'superadmin'
    ) {
        $this->userId = $userId;
        $this->year = $year;
        $this->filterBookCode = $filterBookCode;
        $this->role = $role;
        $this->userBranchCode = $userBranchCode;
        $this->filteredBranchCodes = $filteredBranchCodes;
        $this->callbackfolder = $callbackfolder;
    }

    public function handle(): void
    {
        $controller = app(RekapController::class);
        $result = $controller->buildRecapDataForCache(
            $this->year,
            $this->filterBookCode,
            $this->role,
            $this->userBranchCode,
            $this->filteredBranchCodes,
            $this->callbackfolder
        );

        $key = self::cacheKey(
            $this->userId,
            $this->year,
            $this->filterBookCode,
            $this->role,
            $this->userBranchCode,
            $this->filteredBranchCodes
        );
        Cache::put($key, $result, now()->addMinutes(15));
    }

    public static function cacheKey(int $userId, string $year, string $filterBookCode, int $role, ?string $userBranchCode, ?array $filteredBranchCodes): string
    {
        $part = $userId . '_' . $year . '_' . $filterBookCode . '_' . $role . '_' . ($userBranchCode ?? '') . '_' . md5(json_encode($filteredBranchCodes ?? []));
        return 'recap_' . $part;
    }
}
