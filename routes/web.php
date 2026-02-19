<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\BranchController;
use App\Http\Controllers\CentralStockController;
use App\Http\Controllers\SpBranchController;
use App\Http\Controllers\RekapController;
use App\Http\Controllers\RangkumanController;
use App\Http\Controllers\SpVStockController;
use App\Http\Controllers\SpVTargetController;
use App\Http\Controllers\StagingController;
use App\Http\Controllers\TargetController;
use App\Http\Controllers\PeriodeController;
use App\Http\Controllers\NppbCentralController;
use App\Http\Controllers\NppbWarehouseController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ApiController;
use App\Models\Staging\Master\Book;
use App\Models\Staging\Master\Periode;
use App\Models\Staging\Master\SpBranch;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/login');
})->name('login');

Route::controller(AuthController::class)->group(function () {
    Route::get('/login', 'index');
    Route::post('_login', 'login')->middleware('throttle:5,1');
    Route::get('/logout', 'logout')->name('logout');
});

Route::get('cek-staging', function () {
    try {
        DB::connection('pgsql')->getPdo();
        $tables = DB::connection('pgsql')->select("SELECT tablename FROM pg_tables WHERE schemaname='public'");
        $tableNames = array_map(function ($table) {
            return $table->tablename;
        }, $tables);
        return [
            'connection' => 'success',
            'message' => 'Koneksi ke database PGSQL berhasil.',
        ];
    } catch (\Exception $e) {
        return [
            'connection' => 'error',
            'message' => 'Koneksi ke database PGSQL gagal: ' . $e->getMessage(),
        ];
    }
});


Route::middleware('auth')->group(function () {
    // API Routes for Select2 AJAX
    Route::controller(ApiController::class)->group(function () {
        Route::get('/api/branches', 'getBranches')->name('api.branches');
        Route::get('/api/branches-by-warehouse', 'getBranchesByWarehouse')->name('api.branches-by-warehouse');
        Route::get('/api/warehouse-codes', 'getWarehouseCodes')->name('api.warehouse-codes');
        Route::get('/api/products', 'getProducts')->name('api.products');
        Route::get('/api/areas', 'getAreas')->name('api.areas');
        Route::get('/api/nppb-products', 'getNppbProducts')->name('api.nppb-products');
        Route::get('/api/nppb-products-by-warehouse', 'getNppbProductsByWarehouse')->name('api.nppb-products-by-warehouse');
        Route::post('/api/nppb-products/save', 'saveNppbProducts')->name('api.nppb-products.save');
    });

    Route::controller(DashboardController::class)->group(function () {
        Route::get('/dashboard', 'index')->name('dashboard');
        Route::post('/dashboard/set-date-range', 'setDateRange')->name('dashboard.set-date-range');
    });
    
    // Branch detail route
    Route::get('/branch/{branchCode}', [DashboardController::class, 'branchDetail'])->name('dashboard.branch-detail');

    Route::controller(ProductController::class)->group(function () {
        Route::get('/product', 'index')->name('product.index');
        Route::get('/product/{book_code}/detail', 'showDetail')->name('product.detail');
        Route::post('/product/import', 'import')->name('product.import');
        Route::post('/product/import-category-serial', 'importCategorySerial')->name('product.import-category-serial');
        Route::post('/product/synchronize', 'synchronize')->name('product.synchronize');
        Route::post('/product/clear-and-sync', 'clearAndSync')->name('product.clear-and-sync');
        Route::get('/product/sync-progress', 'getProgress')->name('product.sync-progress');
    });

    Route::controller(BranchController::class)->group(function () {
        Route::get('/branch', 'index')->name('branch.index');
        Route::post('/branch/import', 'import')->name('branch.import');
        Route::post('/branch/synchronize', 'synchronize')->name('branch.synchronize');
        Route::post('/branch/clear-and-sync', 'clearAndSync')->name('branch.clear-and-sync');
        Route::get('/branch/sync-progress', 'getProgress')->name('branch.sync-progress');
    });

    Route::controller(CentralStockController::class)->group(function () {
        Route::get('/central-stock', 'index')->name('central-stock.index');
        Route::post('/central-stock/import', 'import')->name('central-stock.import');
        Route::post('/central-stock/synchronize', 'synchronize')->name('central-stock.synchronize');
        Route::post('/central-stock/clear-and-sync', 'clearAndSync')->name('central-stock.clear-and-sync');
        Route::get('/central-stock/sync-progress', 'getProgress')->name('central-stock.sync-progress');
    });

    Route::controller(SpBranchController::class)->group(function () {
        Route::get('/pesanan', 'index')->name('pesanan.index');
        Route::post('/pesanan/import', 'import')->name('pesanan.import');
        Route::post('/pesanan/synchronize', 'synchronize')->name('pesanan.synchronize');
        Route::post('/pesanan/clear-and-sync', 'clearAndSync')->name('pesanan.clear-and-sync');
    });

    Route::controller(RekapController::class)->group(function () {
        Route::get('/recap', 'index')->name('recap.index');
    });

    Route::controller(RangkumanController::class)->group(function () {
        Route::get('/rangkuman', 'index')->name('rangkuman.index');
    });

    Route::controller(SpVStockController::class)->group(function () {
        Route::get('/sp_v_stock', 'index')->name('sp-v-stock');
    });

    Route::controller(SpVTargetController::class)->group(function () {
        Route::get('/sp_v_target', 'index')->name('sp-v-target');
    });

    Route::controller(StagingController::class)->group(function () {
        Route::get('/staging', 'index')->name('staging.index');
        Route::get('/staging/counts', 'getStagingCounts')->name('staging.counts');
        Route::post('/staging/synchronize-all', 'synchronizeAll')->name('staging.synchronize-all');
        Route::post('/staging/synchronize', 'synchronize')->name('staging.synchronize');
        Route::get('/staging/progress', 'getProgress')->name('staging.progress');
        Route::post('/staging/cutoff-data', 'storeCutoffData')->name('staging.cutoff-data.store');
        Route::put('/staging/cutoff-data/{id}', 'updateCutoffData')->name('staging.cutoff-data.update');
        Route::delete('/staging/cutoff-data/{id}', 'destroyCutoffData')->name('staging.cutoff-data.destroy');
        Route::post('/staging/cutoff-data/{id}/toggle', 'toggleCutoffData')->name('staging.cutoff-data.toggle');
    });

    Route::controller(TargetController::class)->group(function () {
        Route::get('/target', 'index')->name('target.index');
        Route::post('/target/import', 'import')->name('target.import');
        Route::post('/target/synchronize', 'synchronize')->name('target.synchronize');
        Route::post('/target/clear-and-sync', 'clearAndSync')->name('target.clear-and-sync');
        Route::delete('/target/{id}', 'destroy')->name('target.destroy');
    });

    Route::controller(PeriodeController::class)->group(function () {
        Route::get('/period', 'index')->name('period.index');
        Route::post('/period/synchronize', 'synchronize')->name('period.synchronize');
        Route::post('/period/clear-and-sync', 'clearAndSync')->name('period.clear-and-sync');
    });

    Route::controller(NppbWarehouseController::class)->group(function () {
        Route::get('/nppb-warehouse', 'index')->name('nppb-warehouse.index');
    });

    Route::controller(NppbCentralController::class)->group(function () {
        Route::get('/nppb-central', 'index')->name('nppb-central.index');
        Route::get('/nppb-central/create', 'create')->name('nppb-central.create');
        Route::post('/nppb-central', 'store')->name('nppb-central.store');
        Route::get('/nppb-central/{id}/edit', 'edit')->name('nppb-central.edit');
        Route::put('/nppb-central/{id}', 'update')->name('nppb-central.update');
        Route::delete('/nppb-central/{id}', 'destroy')->name('nppb-central.destroy');
    });

    Route::controller(UserController::class)->group(function () {
        // User Pusat routes
        Route::get('/user-pusat', 'indexPusat')->name('user-pusat.index');
        Route::get('/user-pusat/create', 'createPusat')->name('user-pusat.create');
        Route::post('/user-pusat', 'storePusat')->name('user-pusat.store');
        Route::get('/user-pusat/{id}/edit', 'editPusat')->name('user-pusat.edit');
        Route::put('/user-pusat/{id}', 'updatePusat')->name('user-pusat.update');
        Route::delete('/user-pusat/{id}', 'destroyPusat')->name('user-pusat.destroy');

        // User Cabang routes
        Route::get('/user-cabang', 'indexCabang')->name('user-cabang.index');
        Route::get('/user-cabang/create', 'createCabang')->name('user-cabang.create');
        Route::post('/user-cabang', 'storeCabang')->name('user-cabang.store');
        Route::get('/user-cabang/{id}/edit', 'editCabang')->name('user-cabang.edit');
        Route::put('/user-cabang/{id}', 'updateCabang')->name('user-cabang.update');
        Route::delete('/user-cabang/{id}', 'destroyCabang')->name('user-cabang.destroy');

        // User ADP routes (authority_id = 3)
        Route::get('/user-adp', 'indexAdp')->name('user-adp.index');
        Route::get('/user-adp/create', 'createAdp')->name('user-adp.create');
        Route::post('/user-adp', 'storeAdp')->name('user-adp.store');
        Route::get('/user-adp/{id}/edit', 'editAdp')->name('user-adp.edit');
        Route::put('/user-adp/{id}', 'updateAdp')->name('user-adp.update');
        Route::delete('/user-adp/{id}', 'destroyAdp')->name('user-adp.destroy');
    });
});
