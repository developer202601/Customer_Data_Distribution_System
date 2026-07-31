<?php

namespace App\Providers;

use App\Models\Configurations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use PDOException;

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
        $this->ensureSessionStoreAvailable();

        View::composer('*', function ($view) {
            $threshold = Configurations::where('config_name', 'outstanding_threshold')->value('value');
            $view->with('outstandingThreshold', $threshold !== null ? (int) $threshold : 0);
        });
    }

    private function ensureSessionStoreAvailable(): void
    {
        if (config('session.driver') !== 'database') {
            return;
        }

        $connection = config('session.connection') ?? config('database.default');

        try {
            DB::connection($connection)->getPdo();
        } catch (PDOException $exception) {
            if ((int) $exception->getCode() !== 2002) {
                throw $exception;
            }

            Log::warning('Session database connection failed, falling back to file driver.', [
                'connection' => $connection,
                'error' => $exception->getMessage(),
            ]);

            config(['session.driver' => 'file']);
        }
    }
}
