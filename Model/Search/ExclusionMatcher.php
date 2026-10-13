<?php
/**
 * Magelan_SearchCms
 */
declare(strict_types=1);

namespace Magelan\SearchCms\Model\Search;

/**
 * "Excluded pages": exact identifiers, or a prefix with a trailing "*" ("cookie*"). Case insensitive.
 */
class ExclusionMatcher
{
    /**
     * @param array<int, string> $patterns
     */
    public function isExcluded(string $identifier, array $patterns): bool
    {
        $identifier = strtolower(trim($identifier, '/'));
        foreach ($patterns as $pattern) {
            $pattern = strtolower(trim($pattern));
            if ($pattern === '') {
                continue;
            }
            if (str_ends_with($pattern, '*')) {
                $prefix = trim(substr($pattern, 0, -1), '/');
                if ($prefix === '' || str_starts_with($identifier, $prefix)) {
                    return true;
                }
                continue;
            }
            if ($identifier === trim($pattern, '/')) {
                return true;
            }
        }

        return false;
    }
}
