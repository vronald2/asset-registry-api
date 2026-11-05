<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Data\DeviceData;
use App\Enums\DeviceCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Full representation of a device type, shared by create (POST) and full update (PUT).
 */
final class DeviceRequest extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'manufacturer' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', Rule::enum(DeviceCategory::class)],
            'description' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function toData(): DeviceData
    {
        $input = $this->safe();

        return new DeviceData(
            name: $input->string('name')->value(),
            manufacturer: $input->string('manufacturer')->value(),
            category: DeviceCategory::from($input->string('category')->value()),
            description: $input->filled('description') ? $input->string('description')->value() : null,
        );
    }
}
