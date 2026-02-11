<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Validator;

trait Searchable
{
    /**
     * Main search handler
     *
     * @param array $searchColumns Columns to search in
     * @param array $withRelationships Relationships to eager load
     * @param int $perPage Items per page
     * @param string $shipmentColumn Column for ordering
     * @param string $shipmentDirection Shipment direction (asc/desc)
     */
    protected function handleSearch(
        array $searchColumns = ['name'],
        array $withRelationships = [],
        int $perPage = 10,
        string $shipmentColumn = 'id',
        string $shipmentDirection = 'desc'
    ): mixed {
        $query = $this->modelQuery()
            ->with($withRelationships)
            ->orderBy($shipmentColumn, $shipmentDirection);

        if ($searchTerm = $this->getValidatedSearchTerm()) {
            $this->applySearchConditions($query, $searchTerm, $searchColumns);
        }

        return $this->paginateOrGet($query, $perPage);
    }

    /**
     * Apply search conditions to query
     */
    protected function applySearchConditions(
        Builder $query,
        string $searchTerm,
        array $searchColumns
    ): void {
        $formattedTerm = $this->formatSearchTerm($searchTerm);

        $query->where(function ($q) use ($searchColumns, $formattedTerm) {
            foreach ($searchColumns as $column) {
                if (str_contains($column, '.')) {
                    // Relationship column (e.g., driver.name)
                    [$relation, $relColumn] = explode('.', $column);
                    $q->orWhereHas($relation, function ($rq) use ($relColumn, $formattedTerm) {
                        $rq->where($relColumn, 'LIKE', $formattedTerm);
                    });
                } else {
                    // Base table column
                    $q->orWhere($column, 'LIKE', $formattedTerm);
                }
            }
        });
    }


    /**
     * Get validated search term
     */
    protected function getValidatedSearchTerm(): ?string
    {
        $validator = Validator::make(request()->all(), [
            'search' => ['sometimes', 'string', 'min:1', 'max:255'] // change from 'query'
        ]);

        return $validator->fails() ? null : trim(request('search'));
    }


    /**
     * Format search term for LIKE queries
     */
    protected function formatSearchTerm(string $term): string
    {
        $escaped = str_replace(['%', '_'], ['\%', '\_'], $term);
        return "%{$escaped}%";
    }

    /**
     * Paginate or get results
     */
    protected function paginateOrGet(Builder $query, int $perPage): mixed
    {
        return request()->has('query')
            ? $query->get()
            : $query->paginate($perPage);
    }

    /**
     * Get base model query
     */
    abstract protected function modelQuery();
}
