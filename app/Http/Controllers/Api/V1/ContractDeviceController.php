<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\SerialNumberAlreadyAssignedException;
use App\Http\Controllers\Controller;
use App\Http\Requests\AttachContractDeviceRequest;
use App\Http\Resources\ContractDeviceResource;
use App\Models\Contract;
use App\Models\ContractDevice;
use App\Services\ContractDeviceService;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

#[Group('Contract devices', 'Adding device units (with serial numbers) to contracts and removing them.', weight: 3)]
final class ContractDeviceController extends Controller
{
    public function __construct(
        private readonly ContractDeviceService $contractDevices,
    ) {
    }

    /**
     * Add a device to a contract.
     *
     * Puts one physical unit of a device type, identified by its serial number, on the contract.
     * The same device type can be on several contracts (and several times on one contract),
     * but every unit needs a different serial number.
     *
     * @status 201
     *
     * @throws SerialNumberAlreadyAssignedException
     */
    public function store(AttachContractDeviceRequest $request, Contract $contract): JsonResponse
    {
        $contractDevice = $this->contractDevices->attach($contract, $request->toData());

        return ContractDeviceResource::make($contractDevice)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Remove a device from a contract.
     *
     * `contractDevice` is the `id` of the unit in the `devices` list of the contract. The binding is
     * scoped to the contract: a unit of another contract is not found.
     */
    public function destroy(Contract $contract, ContractDevice $contractDevice): Response
    {
        $this->contractDevices->detach($contractDevice);

        return response()->noContent();
    }
}
