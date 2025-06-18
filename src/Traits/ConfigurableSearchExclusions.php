<?php

namespace Kainiklas\FilamentScout\Traits;

trait ConfigurableSearchExclusions
{
    protected static array $globalExcludeRules = [];

    /**
     * Configure search exclusions - override this method in your panel provider
     *
     * @return array Array of exclusion rules in format: ['word' => ['ResourceClass1', 'ResourceClass2']]
     */
    public function configureSearchExclusions(): array
    {
        return [];
    }

    /**
     * Set search exclusion rules
     *
     * @param  array  $rules  Array of exclusion rules
     */
    public function searchExcludeRules(array $rules): static
    {
        static::$globalExcludeRules = array_merge_recursive(static::$globalExcludeRules, $rules);

        return $this;
    }

    /**
     * Add single exclusion rule
     *
     * @param  string  $word  The word that triggers exclusion
     * @param  array  $resources  Array of resource class names to exclude
     */
    public function searchExcludeRule(string $word, array $resources): static
    {
        if (isset(static::$globalExcludeRules[$word])) {
            static::$globalExcludeRules[$word] = array_merge(static::$globalExcludeRules[$word], $resources);
        } else {
            static::$globalExcludeRules[$word] = $resources;
        }

        return $this;
    }

    /**
     * Exclude a single resource when a word is searched
     *
     * @param  string  $word  The word that triggers exclusion
     * @param  string  $resourceClass  The resource class to exclude
     */
    public function excludeResourceOnWord(string $word, string $resourceClass): static
    {
        return $this->searchExcludeRule($word, [$resourceClass]);
    }

    /**
     * Exclude multiple resources when a word is searched
     *
     * @param  string  $word  The word that triggers exclusion
     * @param  array  $resourceClasses  Array of resource classes to exclude
     */
    public function excludeResourcesOnWord(string $word, array $resourceClasses): static
    {
        return $this->searchExcludeRule($word, $resourceClasses);
    }

    /**
     * Exclude a resource when any of multiple words are searched
     *
     * @param  array  $words  Array of words that trigger exclusion
     * @param  string  $resourceClass  The resource class to exclude
     */
    public function excludeResourceOnWords(array $words, string $resourceClass): static
    {
        foreach ($words as $word) {
            $this->excludeResourceOnWord($word, $resourceClass);
        }

        return $this;
    }

    /**
     * Exclude multiple resources when any of multiple words are searched
     *
     * @param  array  $words  Array of words that trigger exclusion
     * @param  array  $resourceClasses  Array of resource classes to exclude
     */
    public function excludeResourcesOnWords(array $words, array $resourceClasses): static
    {
        foreach ($words as $word) {
            $this->excludeResourcesOnWord($word, $resourceClasses);
        }

        return $this;
    }

    /**
     * Exclude resource on progressive prefix match (c, ce, cer, cert, etc.)
     *
     * @param  string  $word  The full word to match progressively
     * @param  string  $resourceClass  The resource class to exclude
     */
    public function excludeResourceOnPrefix(string $word, string $resourceClass): static
    {
        return $this->excludeResourceOnWord('^' . $word, $resourceClass);
    }

    /**
     * Exclude multiple resources on progressive prefix match
     *
     * @param  string  $word  The full word to match progressively
     * @param  array  $resourceClasses  Array of resource classes to exclude
     */
    public function excludeResourcesOnPrefix(string $word, array $resourceClasses): static
    {
        return $this->excludeResourcesOnWord('^' . $word, $resourceClasses);
    }

    /**
     * Exclude resource on exact match only
     *
     * @param  string  $word  The exact word to match
     * @param  string  $resourceClass  The resource class to exclude
     */
    public function excludeResourceOnExactMatch(string $word, string $resourceClass): static
    {
        return $this->excludeResourceOnWord($word . '$', $resourceClass);
    }

    /**
     * Get all configured exclusion rules
     */
    public static function getSearchExcludeRules(): array
    {
        return static::$globalExcludeRules;
    }

    /**
     * Check if a resource should be excluded for a given query (static)
     *
     * @param  string  $query  The search query
     * @param  string  $resourceClass  The resource class to check
     */
    public static function shouldExcludeResourceStatic(string $query, string $resourceClass): bool
    {
        foreach (static::$globalExcludeRules as $word => $resources) {
            if (static::matchesExcludePattern($query, $word) && in_array($resourceClass, $resources)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if a resource should be excluded for a given query (instance)
     *
     * @param  string  $query  The search query
     * @param  string  $resourceClass  The resource class to check
     */
    public function shouldExcludeResource(string $query, string $resourceClass): bool
    {
        return static::shouldExcludeResourceStatic($query, $resourceClass);
    }

    /**
     * Get excluded resources for a query (static)
     *
     * @param  string  $query  The search query
     * @return array Array of resource classes that should be excluded
     */
    public static function getExcludedResourcesForQueryStatic(string $query): array
    {
        $excludedResources = [];

        foreach (static::$globalExcludeRules as $word => $resources) {
            if (static::matchesExcludePattern($query, $word)) {
                $excludedResources = array_merge($excludedResources, $resources);
            }
        }

        return array_unique($excludedResources);
    }

    /**
     * Get excluded resources for a query (instance)
     *
     * @param  string  $query  The search query
     * @return array Array of resource classes that should be excluded
     */
    public function getExcludedResourcesForQuery(string $query): array
    {
        return static::getExcludedResourcesForQueryStatic($query);
    }

    /**
     * Check if a query matches an exclude pattern
     * Supports prefix matching for progressive exclusion
     *
     * @param  string  $query  The search query
     * @param  string  $pattern  The exclude pattern
     */
    protected static function matchesExcludePattern(string $query, string $pattern): bool
    {
        $query = strtolower(trim($query));
        $pattern = strtolower(trim($pattern));

        // If pattern starts with '^', it's a prefix match
        if (str_starts_with($pattern, '^')) {
            $cleanPattern = ltrim($pattern, '^');

            return str_starts_with($cleanPattern, $query) && strlen($query) > 0;
        }

        // If pattern ends with '$', it's an exact match
        if (str_ends_with($pattern, '$')) {
            $cleanPattern = rtrim($pattern, '$');

            return $query === $cleanPattern;
        }

        // If pattern contains '*', it's a wildcard match
        if (str_contains($pattern, '*')) {
            $regexPattern = str_replace('*', '.*', preg_quote($pattern, '/'));

            return preg_match("/^{$regexPattern}$/i", $query);
        }

        // Default: contains match (original behavior)
        return str_contains($query, $pattern) || str_contains($pattern, $query);
    }

    /**
     * Initialize search exclusions from configuration
     * This method should be called in the plugin's boot or panel configuration
     */
    protected function initializeSearchExclusions(): void
    {
        $configuredRules = $this->configureSearchExclusions();
        if (! empty($configuredRules)) {
            $this->searchExcludeRules($configuredRules);
        }
    }

    /**
     * Clear all exclusion rules
     */
    public static function clearSearchExclusions(): static
    {
        static::$globalExcludeRules = [];

        return new static;
    }

    /**
     * Exclude resources for non-admin users (helper method)
     *
     * @param  string  $word  The word that triggers exclusion
     * @param  array  $resources  Array of resource classes to exclude
     */
    public function excludeForNonAdmins(string $word, array $resources): static
    {
        if (auth()->check() && method_exists(auth()->user(), 'isAdmin') && ! auth()->user()->isAdmin()) {
            $this->excludeResourcesOnWord($word, $resources);
        }

        return $this;
    }

    /**
     * Exclude resources based on user roles (helper method)
     *
     * @param  string  $word  The word that triggers exclusion
     * @param  array  $resources  Array of resource classes to exclude
     * @param  array  $roles  Array of roles that should have resources excluded
     */
    public function excludeForRoles(string $word, array $resources, array $roles): static
    {
        if (auth()->check()) {
            $user = auth()->user();

            if (method_exists($user, 'hasAnyRole') && $user->hasAnyRole($roles)) {
                $this->excludeResourcesOnWord($word, $resources);
            } elseif (method_exists($user, 'roles')) {
                $userRoles = $user->roles()->pluck('name')->toArray();
                if (! empty(array_intersect($roles, $userRoles))) {
                    $this->excludeResourcesOnWord($word, $resources);
                }
            }
        }

        return $this;
    }

    /**
     * Get the search limit from configuration
     *
     * @return int
     */
    public function getSearchLimit(): int
    {
        return config('scout_search_limit', env('SCOUT_SEARCH_LIMIT', 100));
    }

    /**
     * Set custom search limit
     *
     * @param int $limit
     * @return static
     */
    public function setSearchLimit(int $limit): static
    {
        config(['scout_search_limit' => $limit]);

        return $this;
    }
}
