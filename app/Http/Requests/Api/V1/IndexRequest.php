<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Pagination parameters of the list endpoints.
 */
final class IndexRequest extends FormRequest
{
    public const int DEFAULT_PER_PAGE = 15;

    public const int MAX_PER_PAGE = 100;

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            /** The page to return, starting from 1. */
            'page' => ['sometimes', 'integer', 'min:1'],
            /** Items per page (default: 15). */
            'per_page' => ['sometimes', 'integer', 'between:1,' . self::MAX_PER_PAGE],
        ];
    }

    public function perPage(): int
    {
        return $this->integer('per_page', self::DEFAULT_PER_PAGE);
    }
}
