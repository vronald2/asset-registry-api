<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\DeviceAlreadyAttachedException;
use App\Exceptions\DeviceNotAttachedException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\AttachDeviceRequest;
use App\Http\Resources\Api\V1\ContractDeviceResource;
use App\Models\Contract;
use App\Models\Device;
use App\Services\ContractDeviceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * Adding devices to and removing them from a contract.
 *
 * @tags Contracts
 */
final class ContractDeviceController extends Controller
{
    public function __construct(private readonly ContractDeviceService $assignments)
    {
    }

    /**
     * Add a device to a contract
     *
     * The serial number identifies the concrete unit and has to be unique across all contracts. A device type can
     * be on any number of contracts, but at most once on the same contract.
     *
     * @status 201
     *
     * @throws DeviceAlreadyAttachedException
     */
    public function store(AttachDeviceRequest $request, Contract $contract): JsonResponse
    {
        $device = $this->assignments->attach($contract, $request->deviceId(), $request->serialNumber());

        return ContractDeviceResource::make($device)->response()->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Remove a device from a contract
     *
     * Only the assignment is deleted; the device itself is kept.
     *
     * @throws DeviceNotAttachedException
     */
    public function destroy(Contract $contract, Device $device): Response
    {
        $this->assignments->detach($contract, $device);

        return response()->noContent();
    }
}
