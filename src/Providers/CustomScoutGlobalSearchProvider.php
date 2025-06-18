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

        // Get resources to exclude based on the search query using the plugin instance
        $excludedResources = $plugin->getExcludedResourcesForQuery($query);

        foreach (Filament::getResources() as $resource) {
            if (! $resource::canGloballySearch()) {
                continue;
            }

            // Skip this resource if it should be excluded
            // $resource is already a string (class name) in Filament's getResources()
            if (in_array($resource, $excludedResources) ||
                $plugin->shouldExcludeResource($query, $resource)) {
                continue;
            }

            if (! method_exists($resource::getModel(), 'search')) {
                throw new Exception('The model is not searchable. Please add the Laravel Scout trait Searchable to the model.');
            }

            $search = $resource::getModel()::search($query);

            // Apply search limit from plugin configuration or environment
            $searchLimit = $plugin->getSearchLimit();

            $resourceResults = $search
                ->take($searchLimit)  // Limit the number of results from Scout
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
