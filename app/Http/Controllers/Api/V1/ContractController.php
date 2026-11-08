<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ContractRequest;
use App\Http\Requests\Api\V1\IndexRequest;
use App\Http\Resources\Api\V1\ContractResource;
use App\Models\Contract;
use App\Services\ContractService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * Contracts and the devices assigned to them.
 *
 * @tags Contracts
 */
final class ContractController extends Controller
{
    public function __construct(private readonly ContractService $contracts)
    {
    }

    /**
     * List contracts
     *
     * Returns the contracts page by page, ordered by their identifier. The list does not include the devices of
     * the contracts; request a single contract for them.
     */
    public function index(IndexRequest $request): AnonymousResourceCollection
    {
        return ContractResource::collection($this->contracts->paginate($request->perPage()));
    }

    /**
     * Create a contract
     *
     * @status 201
     */
    public function store(ContractRequest $request): JsonResponse
    {
        $contract = $this->contracts->create($request->payload());

        return ContractResource::make($contract)->response()->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Get a contract
     *
     * The response includes the devices on the contract, with their serial numbers, in the order they were added.
     */
    public function show(Contract $contract): ContractResource
    {
        return ContractResource::make($this->contracts->loadDevices($contract));
    }

    /**
     * Update a contract
     *
     * `PUT` replaces the whole contract (the optional fields that are left out are cleared), `PATCH` changes only
     * the fields that are sent.
     */
    public function update(ContractRequest $request, Contract $contract): ContractResource
    {
        return ContractResource::make($this->contracts->update($contract, $request->payload()));
    }

    /**
     * Delete a contract
     *
     * The device assignments of the contract are deleted with it; the devices themselves are kept.
     */
    public function destroy(Contract $contract): Response
    {
        $this->contracts->delete($contract);

        return response()->noContent();
    }
}
