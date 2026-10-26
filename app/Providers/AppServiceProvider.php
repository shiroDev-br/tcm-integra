<?php

namespace App\Providers;

use App\Enums\Role;
use App\Models\User;
use App\Services\FormatadorDeData;
use Carbon\CarbonImmutable;
use Database\Seeders\SuperAdminSeeder;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

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
        $this->configureDefaults();
        $this->registerRateLimiters();
        $this->registerGates();
        $this->registerBladeDirectives();
        $this->seedSuperAdmin();
    }

    /**
     * Date directives: @data($valor) prints dd/mm/aaaa, @dataHora($valor) prints dd/mm/aaaa hh:mm
     * and @periodo($inicio, $fim) prints "dd/mm/aaaa a dd/mm/aaaa".
     */
    protected function registerBladeDirectives(): void
    {
        Blade::directive('data', fn (string $expressao): string => '<?php echo e(\\'.FormatadorDeData::class."::data({$expressao})); ?>");
        Blade::directive('dataHora', fn (string $expressao): string => '<?php echo e(\\'.FormatadorDeData::class."::dataHora({$expressao})); ?>");
        Blade::directive('periodo', fn (string $expressao): string => '<?php echo e(\\'.FormatadorDeData::class."::periodo({$expressao})); ?>");
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );

        JsonResource::withoutWrapping();
    }

    /**
     * Rate limiters keyed by IP, matching the original slowapi limits.
     */
    protected function registerRateLimiters(): void
    {
        $resposta = fn (Request $request, array $headers) => response()->json(
            ['detail' => 'Too many requests.'],
            429,
            $headers
        );

        $limiter = function (string $config) use ($resposta) {
            return function (Request $request) use ($config, $resposta) {
                return Limit::perMinute((int) config($config))
                    ->by($request->ip())
                    ->response($resposta);
            };
        };

        RateLimiter::for('tcm-login', $limiter('tcm.rate_limits.login'));
        RateLimiter::for('tcm-search', $limiter('tcm.rate_limits.search'));
        RateLimiter::for('tcm-terms', $limiter('tcm.rate_limits.terms'));
        RateLimiter::for('tcm-read', $limiter('tcm.rate_limits.read'));
    }

    /**
     * Only the web superadmin may manage the panel accounts (usuarios).
     */
    protected function registerGates(): void
    {
        Gate::define(
            'manage-panel-accounts',
            fn (User $user): bool => $user->role === Role::SuperAdmin,
        );
    }

    /**
     * Seeds the superadmin once on boot (guarded: table must exist, cache marker
     * avoids a query on every request).
     */
    protected function seedSuperAdmin(): void
    {
        if (! Schema::hasTable('usuarios')) {
            return;
        }

        if (Schema::hasTable('cache') && ! cache()->add('tcm:superadmin:seeded', true, now()->addDay())) {
            return;
        }

        app(SuperAdminSeeder::class)->run();
    }
}
