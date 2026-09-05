<?php

declare(strict_types=1);

namespace Muni\Candados\Tests\Fixtures\App\Providers;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Muni\Candados\Tests\Fixtures\App\Http\Middleware\IgnorarCookieDeRecordar;

/**
 * Un panel de Filament como el de cualquier sistema del ecosistema, con el
 * middleware que ignora la cookie de recordar PRIMERO en su lista: el panel no
 * pasa por el grupo «web», así que lo que se quita allí no se quita acá.
 */
class PanelDePruebaProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->middleware($this->middlewareDelPanel())
            ->authMiddleware([
                Authenticate::class,
            ]);
    }

    /**
     * @return list<class-string>
     */
    protected function middlewareDelPanel(): array
    {
        return [
            IgnorarCookieDeRecordar::class,
            ...$this->middlewareHeredadoDeFilament(),
        ];
    }

    /**
     * La lista que trae `filament:install`.
     *
     * @return list<class-string>
     */
    protected function middlewareHeredadoDeFilament(): array
    {
        return [
            EncryptCookies::class,
            AddQueuedCookiesToResponse::class,
            StartSession::class,
            AuthenticateSession::class,
            ShareErrorsFromSession::class,
            VerifyCsrfToken::class,
            SubstituteBindings::class,
            DisableBladeIconComponents::class,
            DispatchServingFilamentEvent::class,
        ];
    }
}
