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
     * A page of the resources, ordered by their identifier; the pagination links keep the query string.
     *
     * @return LengthAwarePaginator<int, TModel>
     */
    public function paginate(int $perPage): LengthAwarePaginator
    {
        return $this->modelClass()::query()->orderBy('id')->paginate($perPage)->withQueryString();
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
