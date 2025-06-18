<?php

namespace Kainiklas\FilamentScout\Providers;

use Exception;
use Filament\Facades\Filament;
use Filament\GlobalSearch\Contracts\GlobalSearchProvider;
use Filament\GlobalSearch\GlobalSearchResult;
use Filament\GlobalSearch\GlobalSearchResults;
use Illuminate\Database\Eloquent\Model;

class CustomScoutGlobalSearchProvider implements GlobalSearchProvider
{
    public function getResults(string $query): ?GlobalSearchResults
    {
        $builder = GlobalSearchResults::make();

        // Get the plugin instance to access trait methods
        $plugin = \Kainiklas\FilamentScout\FilamentScoutPlugin::get();

        // Get resources to exclude based on the search query using the plugin
        $excludedResources = $plugin::getExcludedResourcesForQuery($query);

        foreach (Filament::getResources() as $resource) {
            if (! $resource::canGloballySearch()) {
                continue;
            }

            // Skip this resource if it should be excluded
            if (in_array($resource, $excludedResources) ||
                in_array(get_class($resource), $excludedResources) ||
                $plugin::shouldExcludeResource($query, $resource) ||
                $plugin::shouldExcludeResource($query, get_class($resource))) {
                continue;
            }

            if (! method_exists($resource::getModel(), 'search')) {
                throw new Exception('The model is not searchable. Please add the Laravel Scout trait Searchable to the model.');
            }

            $search = $resource::getModel()::search($query);

            $resourceResults = $search
                ->get()
                ->map(function (Model $record) use ($resource): ?GlobalSearchResult {
                    $url = $resource::getGlobalSearchResultUrl($record);

                    if (blank($url)) {
                        return null;
                    }

                    return new GlobalSearchResult(
                        title: $resource::getGlobalSearchResultTitle($record),
                        url: $url,
                        details: $resource::getGlobalSearchResultDetails($record),
                        actions: $resource::getGlobalSearchResultActions($record),
                    );
                })
                ->filter();

            if (! $resourceResults->count()) {
                continue;
            }

            $builder->category($resource::getPluralModelLabel(), $resourceResults);
        }

        return $builder;
    }
}
