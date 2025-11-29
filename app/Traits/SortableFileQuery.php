<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;

trait SortableFileQuery
{
    /**
     * @param string|null $sortBy
     * @return array
     */
    protected function getSortRule(?string $sortBy): ?array
    {
        $sortMap = [
            'alphabetical'        => ['column' => 'files.name', 'direction' => 'asc'],
            'reverse_alphabetical'=> ['column' => 'files.name', 'direction' => 'desc'],
            'latest'              => ['column' => 'files.created_at', 'direction' => 'desc'],
            'oldest'              => ['column' => 'files.created_at', 'direction' => 'asc'],
            'largest'             => ['column' => 'files.size', 'direction' => 'desc'],
            'smallest'            => ['column' => 'files.size', 'direction' => 'asc'],
        ];

        return $sortMap[$sortBy] ?? null;
    }

    /**
     * @param Builder $query
     * @param string|null $sortBy
     * @return Builder
     */
    protected function applyDmsSorting(Builder $query, ?string $sortBy): Builder
    {
        // folders always appear first
        $query->orderBy('is_folder', 'desc');

        // the requested user sort
        $secondarySortRule = $this->getSortRule($sortBy);
        if ($secondarySortRule) {
            $query->orderBy($secondarySortRule['column'], $secondarySortRule['direction']);
        }

        return $query->orderBy('files.created_at', 'desc');
    }
}
