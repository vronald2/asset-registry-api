<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Config;

/**
 * Pagination parameters shared by the list endpoints.
 */
final class IndexRequest extends FormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:' . Config::integer('api.pagination.max_per_page')],
        ];
    }

    public function perPage(): int
    {
        return $this->safe()->integer('per_page', Config::integer('api.pagination.default_per_page'));
    }
}
