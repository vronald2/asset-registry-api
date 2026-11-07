<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Base of the requests that create (POST), replace (PUT) or partially update (PATCH) a resource.
 *
 * The rules are defined once, for the full representation. On PATCH every rule only applies to the fields present
 * in the payload; on PUT the optional fields that are left out are cleared.
 */
abstract class ResourceWriteRequest extends FormRequest
{
    /**
     * The rules of the full representation.
     *
     * @return array<string, list<mixed>>
     */
    abstract protected function fieldRules(): array;

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $rules = $this->fieldRules();

        if (! $this->isMethod('PATCH')) {
            return $rules;
        }

        return array_map(static fn (array $fieldRules): array => ['sometimes', ...$fieldRules], $rules);
    }

    /**
     * The validated attributes to persist.
     *
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        if (! $this->isMethod('PUT')) {
            return $validated;
        }

        // PUT replaces the whole representation: an omitted optional field means "no value".
        return [...array_fill_keys(array_keys($this->fieldRules()), null), ...$validated];
    }
}
