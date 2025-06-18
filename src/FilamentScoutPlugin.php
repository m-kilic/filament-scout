<?php

namespace Kainiklas\FilamentScout;

use Exception;
use Filament\Contracts\Plugin;
use Filament\Panel;
use Kainiklas\FilamentScout\Providers\CustomScoutGlobalSearchProvider;
use Kainiklas\FilamentScout\Providers\MeilisearchGlobalSearchProvider;
use Kainiklas\FilamentScout\Traits\ConfigurableSearchExclusions;
use Kainiklas\FilamentScout\Traits\ConfigurePlugin;

class FilamentScoutPlugin implements Plugin
{
    use ConfigurableSearchExclusions;
    use ConfigurePlugin;

    public function getId(): string
    {
        return 'kainiklas-filament-scout-plugin';
    }

    public function register(Panel $panel): void
    {
        //
    }

    /**
     * @throws Exception
     */
    public function boot(Panel $panel): void
    {
        // Global Search Provider
        if ($this->getUseMeiliSearch()) {
            $panel->globalSearch(MeilisearchGlobalSearchProvider::class);
        } else {
            $panel->globalSearch(CustomScoutGlobalSearchProvider::class);
        }
    }

    public static function make(): static
    {
        return app(static::class);
    }

    public static function get(): static
    {
        /** @var static $plugin */
        $plugin = filament(app(static::class)->getId());

        return $plugin;
    }
}
