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
     * Get all configured exclusion rules
     */
    public static function getSearchExcludeRules(): array
    {
        return static::$globalExcludeRules;
    }

    /**
     * Check if a resource should be excluded for a given query
     *
     * @param  string  $query  The search query
     * @param  string  $resourceClass  The resource class to check
     */
    public static function shouldExcludeResource(string $query, string $resourceClass): bool
    {
        foreach (static::$globalExcludeRules as $word => $resources) {
            if (stripos($query, $word) !== false && in_array($resourceClass, $resources)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get excluded resources for a query
     *
     * @param  string  $query  The search query
     * @return array Array of resource classes that should be excluded
     */
    public static function getExcludedResourcesForQuery(string $query): array
    {
        $excludedResources = [];

        foreach (static::$globalExcludeRules as $word => $resources) {
            if (stripos($query, $word) !== false) {
                $excludedResources = array_merge($excludedResources, $resources);
            }
        }

        return array_unique($excludedResources);
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
}
