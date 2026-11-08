<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * The persistence operations every resource of the API shares; the subclasses add their own business rules.
 *
 * @template TModel of Model
 */
abstract readonly class EloquentCrudService
{
    /**
     * @return class-string<TModel>
     */
    abstract protected function modelClass(): string;

    /**
     * A page of the resources, ordered by their identifier.
     *
     * The pagination links carry only the page number and the page size. The rest of the query string would echo
     * whatever the client sent into the response and, through it, into the request log, secrets included.
     *
     * @return LengthAwarePaginator<int, TModel>
     */
    public function paginate(int $perPage): LengthAwarePaginator
    {
        return $this->modelClass()::query()->orderBy('id')->paginate($perPage)->appends(['per_page' => $perPage]);
    }

    /**
     * @param array<string, mixed> $attributes
     *
     * @return TModel
     */
    public function create(array $attributes): Model
    {
        return $this->modelClass()::query()->create($attributes);
    }

    /**
     * @param TModel $model
     * @param array<string, mixed> $attributes
     *
     * @return TModel
     */
    public function update(Model $model, array $attributes): Model
    {
        $model->update($attributes);

        return $model;
    }
}
