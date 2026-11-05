<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\DeviceInUseException;
use App\Http\Controllers\Controller;
use App\Http\Requests\DeviceRequest;
use App\Http\Requests\IndexRequest;
use App\Http\Resources\DeviceResource;
use App\Models\Device;
use App\Services\DeviceService;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

#[Group('Devices', 'Device types: catalogue items that can be put on contracts with serial numbers.', weight: 1)]
final class DeviceController extends Controller
{
    public function __construct(
        private readonly DeviceService $devices,
    ) {
    }

    /**
     * List devices.
     */
    public function index(IndexRequest $request): AnonymousResourceCollection
    {
        return DeviceResource::collection($this->devices->paginate($request->perPage()));
    }

    /**
     * Create a device.
     *
     * @status 201
     */
    public function store(DeviceRequest $request): JsonResponse
    {
        $device = $this->devices->create($request->toData());

        return DeviceResource::make($device)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED)
            ->header('Location', route('api.v1.devices.show', $device));
    }

    /**
     * Get a device.
     */
    public function show(Device $device): DeviceResource
    {
        return DeviceResource::make($device);
    }

    /**
     * Update a device.
     *
     * Replaces the whole representation: omitted optional fields are cleared.
     */
    public function update(DeviceRequest $request, Device $device): DeviceResource
    {
        return DeviceResource::make($this->devices->update($device, $request->toData()));
    }

    /**
     * Delete a device.
     *
     * A device that is assigned to any contract cannot be deleted (`409 device_in_use`).
     *
     * @throws DeviceInUseException
     */
    public function destroy(Device $device): Response
    {
        $this->devices->delete($device);

        return response()->noContent();
    }
}
