<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ContractRequest;
use App\Http\Requests\IndexRequest;
use App\Http\Resources\ContractResource;
use App\Models\Contract;
use App\Services\ContractService;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

#[Group('Contracts', 'Contracts. The detail view also lists the device units on the contract.', weight: 2)]
final class ContractController extends Controller
{
    public function __construct(
        private readonly ContractService $contracts,
    ) {
    }

    /**
     * List contracts.
     *
     * The list does not contain the devices of the contracts; they are returned by the detail endpoint.
     */
    public function index(IndexRequest $request): AnonymousResourceCollection
    {
        return ContractResource::collection($this->contracts->paginate($request->perPage()));
    }

    /**
     * Create a contract.
     *
     * @status 201
     */
    public function store(ContractRequest $request): JsonResponse
    {
        $contract = $this->contracts->create($request->toData());

        return ContractResource::make($contract)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED)
            ->header('Location', route('api.v1.contracts.show', $contract));
    }

    /**
     * Get a contract with its devices.
     *
     * `devices` lists the units on the contract; the `id` of a unit is used to remove it from the contract.
     */
    public function show(Contract $contract): ContractResource
    {
        return ContractResource::make($this->contracts->loadDevices($contract));
    }

    /**
     * Update a contract.
     *
     * Replaces the whole representation: omitted optional fields are cleared.
     */
    public function update(ContractRequest $request, Contract $contract): ContractResource
    {
        return ContractResource::make($this->contracts->update($contract, $request->toData()));
    }

    /**
     * Delete a contract.
     *
     * The devices on the contract are removed from it; the device types themselves are kept.
     */
    public function destroy(Contract $contract): Response
    {
        $this->contracts->delete($contract);

        return response()->noContent();
    }
}
