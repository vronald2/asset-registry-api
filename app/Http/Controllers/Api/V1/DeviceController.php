<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\DeviceInUseException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\DeviceRequest;
use App\Http\Requests\Api\V1\IndexRequest;
use App\Http\Resources\Api\V1\DeviceResource;
use App\Models\Device;
use App\Services\DeviceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * Device types, e.g. "Dell Latitude 5440". The serial numbers of the concrete units are given when a device is
 * assigned to a contract.
 *
 * @tags Devices
 */
final class DeviceController extends Controller
{
    public function __construct(private readonly DeviceService $devices)
    {
    }

    /**
     * List devices
     *
     * Returns the devices page by page, ordered by their identifier.
     */
    public function index(IndexRequest $request): AnonymousResourceCollection
    {
        return DeviceResource::collection($this->devices->paginate($request->perPage()));
    }

    /**
     * Create a device
     *
     * @status 201
     */
    public function store(DeviceRequest $request): JsonResponse
    {
        $device = $this->devices->create($request->payload());

        return DeviceResource::make($device)->response()->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Get a device
     */
    public function show(Device $device): DeviceResource
    {
        return DeviceResource::make($device);
    }

    /**
     * Update a device
     *
     * `PUT` replaces the whole device (the optional fields that are left out are cleared), `PATCH` changes only
     * the fields that are sent.
     */
    public function update(DeviceRequest $request, Device $device): DeviceResource
    {
        return DeviceResource::make($this->devices->update($device, $request->payload()));
    }

    /**
     * Delete a device
     *
     * A device that is assigned to a contract cannot be deleted; remove it from the contracts first.
     *
     * @throws DeviceInUseException
     */
    public function destroy(Device $device): Response
    {
        $this->devices->delete($device);

        return response()->noContent();
    }
}
