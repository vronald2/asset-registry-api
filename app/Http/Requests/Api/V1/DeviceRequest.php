<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

/**
 * The payload of creating and updating a device type.
 */
final class DeviceRequest extends ResourceWriteRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    protected function fieldRules(): array
    {
        return [
            /**
             * Name of the device type.
             * @example Dell Latitude 5440
             */
            'name' => ['required', 'string', 'max:255'],
            /** @example Dell */
            'manufacturer' => ['required', 'string', 'max:255'],
            /** @example Latitude 5440 */
            'model' => ['required', 'string', 'max:255'],
            /** @example 14" business laptop, 16 GB RAM */
            'description' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
