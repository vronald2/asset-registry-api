<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\Contract;
use App\Models\Device;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class ContractApiTest extends TestCase
{
    use RefreshDatabase;

    private const string ENDPOINT = '/api/v1/contracts';

    private const array VALID_PAYLOAD = [
        'contract_number' => 'SZ-2026-001',
        'partner_name' => 'Acme Kft.',
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
        'description' => 'Lease of the office laptops.',
    ];

    public function testStoreCreatesContract(): void
    {
        $response = $this->postJson(self::ENDPOINT, self::VALID_PAYLOAD);

        $response->assertCreated()
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'contract_number',
                    'partner_name',
                    'start_date',
                    'end_date',
                    'description',
                    'created_at',
                    'updated_at',
                ],
            ])
            ->assertJson(['data' => self::VALID_PAYLOAD]);
        $this->assertDatabaseHas('contracts', ['id' => $response->json('data.id'), ...self::VALID_PAYLOAD]);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function invalidPayloads(): iterable
    {
        yield 'missing contract number' => [self::validPayloadWithout('contract_number'), 'contract_number'];
        yield 'missing partner name' => [self::validPayloadWithout('partner_name'), 'partner_name'];
        yield 'missing start date' => [self::validPayloadWithout('start_date'), 'start_date'];
        yield 'too long contract number' => [
            [...self::VALID_PAYLOAD, 'contract_number' => str_repeat('A', 51)],
            'contract_number',
        ];
        yield 'contract number in use' => [
            [...self::VALID_PAYLOAD, 'contract_number' => 'EXISTING-001'],
            'contract_number',
        ];
        yield 'contract number in use, different case' => [
            [...self::VALID_PAYLOAD, 'contract_number' => 'existing-001'],
            'contract_number',
        ];
        yield 'invalid start date format' => [[...self::VALID_PAYLOAD, 'start_date' => '2026.01.01'], 'start_date'];
        yield 'non-existent end date' => [[...self::VALID_PAYLOAD, 'end_date' => '2026-02-30'], 'end_date'];
        yield 'end date before start date' => [[...self::VALID_PAYLOAD, 'end_date' => '2025-12-31'], 'end_date'];
    }

    /**
     * @param array<string, mixed> $payload
     */
    #[DataProvider('invalidPayloads')]
    public function testStoreRejectsInvalidPayload(array $payload, string $invalidField): void
    {
        Contract::factory()->create(['contract_number' => 'EXISTING-001']);

        $response = $this->postJson(self::ENDPOINT, $payload);

        $this->assertProblemDetails($response, 422);
        $response->assertJsonValidationErrors([$invalidField]);
        $this->assertDatabaseCount('contracts', 1);
    }

    /**
     * @param array<string, mixed> $payload
     */
    #[DataProvider('invalidPayloads')]
    public function testUpdateRejectsInvalidPayload(array $payload, string $invalidField): void
    {
        Contract::factory()->create(['contract_number' => 'EXISTING-001']);
        $contract = Contract::factory()->create();
        $stored = $contract->only(['id', 'contract_number', 'partner_name', 'description']);

        $response = $this->putJson(self::ENDPOINT . "/{$contract->id}", $payload);

        $this->assertProblemDetails($response, 422);
        $response->assertJsonValidationErrors([$invalidField]);
        $this->assertDatabaseHas('contracts', [...$stored, 'start_date' => $contract->start_date->toDateString()]);
    }

    public function testUpdateRejectsDuplicateContractNumber(): void
    {
        Contract::factory()->create(['contract_number' => 'EXISTING-001']);
        $contract = Contract::factory()->create();

        $response = $this->patchJson(self::ENDPOINT . "/{$contract->id}", ['contract_number' => 'EXISTING-001']);

        $this->assertProblemDetails($response, 422);
        $response->assertJsonValidationErrors(['contract_number']);
        $this->assertDatabaseHas('contracts', ['id' => $contract->id, 'contract_number' => $contract->contract_number]);
    }

    public function testIndexListsContractsWithoutDevices(): void
    {
        $first = Contract::factory()->withDevices(2)->create();
        $second = Contract::factory()->create();
        $third = Contract::factory()->withDevices(1)->create();

        $response = $this->getJson(self::ENDPOINT);

        $response->assertOk()
            ->assertJsonStructure(['data', 'links', 'meta'])
            ->assertJsonPath('meta.total', 3);
        self::assertSame([$first->id, $second->id, $third->id], $response->json('data.*.id'));

        $contracts = $response->json('data');
        self::assertIsArray($contracts);
        foreach ($contracts as $contract) {
            self::assertIsArray($contract);
            self::assertArrayNotHasKey('devices', $contract);
        }

        $this->getJson(self::ENDPOINT . '?per_page=2')->assertOk()->assertJsonCount(2, 'data');
        $this->assertProblemDetails($this->getJson(self::ENDPOINT . '?per_page=101'), 422);
    }

    public function testShowIncludesAssignedDevices(): void
    {
        $contract = Contract::factory()->create();
        $laptop = Device::factory()->create();
        $printer = Device::factory()->create();
        $contract->devices()->attach($laptop, ['serial_number' => 'SN-LAPTOP-001']);
        $contract->devices()->attach($printer, ['serial_number' => 'SN-PRINTER-001']);

        $response = $this->getJson(self::ENDPOINT . "/{$contract->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $contract->id)
            ->assertJsonPath('data.contract_number', $contract->contract_number)
            ->assertJsonCount(2, 'data.devices')
            ->assertJsonStructure([
                'data' => [
                    'devices' => [
                        ['id', 'name', 'manufacturer', 'model', 'description', 'serial_number', 'attached_at'],
                    ],
                ],
            ])
            ->assertJsonPath('data.devices.0.id', $laptop->id)
            ->assertJsonPath('data.devices.0.name', $laptop->name)
            ->assertJsonPath('data.devices.0.manufacturer', $laptop->manufacturer)
            ->assertJsonPath('data.devices.0.model', $laptop->model)
            ->assertJsonPath('data.devices.0.serial_number', 'SN-LAPTOP-001')
            ->assertJsonPath('data.devices.1.id', $printer->id)
            ->assertJsonPath('data.devices.1.serial_number', 'SN-PRINTER-001');
        self::assertNotNull($response->json('data.devices.0.attached_at'));
    }

    public function testShowReturnsEmptyDeviceListForContractWithoutDevices(): void
    {
        $contract = Contract::factory()->create();

        $this->getJson(self::ENDPOINT . "/{$contract->id}")
            ->assertOk()
            ->assertJsonPath('data.devices', []);
    }

    public function testShowReturnsNotFoundForUnknownContract(): void
    {
        $response = $this->getJson(self::ENDPOINT . '/999999');

        $this->assertProblemDetails($response, 404);
        $response->assertJsonPath('detail', 'Contract not found.');
    }

    public function testPutReplacesContract(): void
    {
        $contract = Contract::factory()->create(['end_date' => '2027-01-31', 'description' => 'Old description']);
        $payload = ['contract_number' => 'SZ-2026-002', 'partner_name' => 'Globex Zrt.', 'start_date' => '2026-03-01'];

        $this->putJson(self::ENDPOINT . "/{$contract->id}", $payload)
            ->assertOk()
            ->assertJson(['data' => ['id' => $contract->id, ...$payload, 'end_date' => null, 'description' => null]]);

        // The optional fields left out of the PUT are cleared.
        $this->assertDatabaseHas(
            'contracts',
            ['id' => $contract->id, ...$payload, 'end_date' => null, 'description' => null],
        );
    }

    public function testPatchUpdatesOnlyGivenFields(): void
    {
        $contract = Contract::factory()->create(['partner_name' => 'Old Partner Kft.', 'description' => 'Kept']);

        $this->patchJson(self::ENDPOINT . "/{$contract->id}", ['partner_name' => 'New Partner Kft.'])
            ->assertOk()
            ->assertJsonPath('data.partner_name', 'New Partner Kft.');

        $this->assertDatabaseHas('contracts', [
            'id' => $contract->id,
            'contract_number' => $contract->contract_number,
            'partner_name' => 'New Partner Kft.',
            'start_date' => $contract->start_date->toDateString(),
            'description' => 'Kept',
        ]);
    }

    public function testUpdateKeepsOwnContractNumber(): void
    {
        $contract = Contract::factory()->create(['contract_number' => 'SZ-2026-010']);

        $payload = [...self::VALID_PAYLOAD, 'contract_number' => 'SZ-2026-010'];

        $this->putJson(self::ENDPOINT . "/{$contract->id}", $payload)
            ->assertOk()
            ->assertJsonPath('data.contract_number', 'SZ-2026-010');
    }

    public function testPatchValidatesEndDateAgainstStoredStartDate(): void
    {
        $contract = Contract::factory()->create(['start_date' => '2026-06-01', 'end_date' => null]);

        $response = $this->patchJson(self::ENDPOINT . "/{$contract->id}", ['end_date' => '2026-05-31']);

        $this->assertProblemDetails($response, 422);
        $response->assertJsonValidationErrors(['end_date']);

        $this->patchJson(self::ENDPOINT . "/{$contract->id}", ['end_date' => '2026-06-01'])
            ->assertOk()
            ->assertJsonPath('data.end_date', '2026-06-01');
    }

    public function testPatchValidatesStartDateAgainstStoredEndDate(): void
    {
        $contract = Contract::factory()->create(['start_date' => '2026-01-01', 'end_date' => '2026-06-30']);

        $response = $this->patchJson(self::ENDPOINT . "/{$contract->id}", ['start_date' => '2026-12-01']);

        $this->assertProblemDetails($response, 422);
        $response->assertJsonValidationErrors(['start_date']);
        $this->assertDatabaseHas('contracts', ['id' => $contract->id, 'start_date' => '2026-01-01']);

        $this->patchJson(self::ENDPOINT . "/{$contract->id}", ['start_date' => '2026-06-30'])
            ->assertOk()
            ->assertJsonPath('data.start_date', '2026-06-30');
        // Moving both dates together is checked against each other, not against the stored values.
        $bothDates = ['start_date' => '2027-01-01', 'end_date' => '2027-12-31'];
        $this->patchJson(self::ENDPOINT . "/{$contract->id}", $bothDates)->assertOk();
    }

    public function testNonNumericIdentifierIsNotFound(): void
    {
        $contract = Contract::factory()->create();

        $this->assertProblemDetails($this->getJson(self::ENDPOINT . "/{$contract->id}abc"), 404);
        $this->assertProblemDetails($this->deleteJson(self::ENDPOINT . "/{$contract->id}abc"), 404);
        $this->assertModelExists($contract);
    }

    public function testDestroyDeletesContractAndAssignmentsButKeepsDevices(): void
    {
        $contract = Contract::factory()->withDevices(2)->create();
        $otherContract = Contract::factory()->withDevices(1)->create();
        $deviceIds = $contract->devices()->pluck('devices.id')->all();

        $this->deleteJson(self::ENDPOINT . "/{$contract->id}")->assertNoContent();

        $this->assertModelMissing($contract);
        $this->assertDatabaseMissing('contract_device', ['contract_id' => $contract->id]);
        $this->assertDatabaseHas('contract_device', ['contract_id' => $otherContract->id]);
        foreach ($deviceIds as $deviceId) {
            $this->assertDatabaseHas('devices', ['id' => $deviceId]);
        }
    }

    public function testDestroyReturnsNotFoundForUnknownContract(): void
    {
        $this->assertProblemDetails($this->deleteJson(self::ENDPOINT . '/999999'), 404);
    }

    /**
     * @return array<string, string>
     */
    private static function validPayloadWithout(string $field): array
    {
        return array_diff_key(self::VALID_PAYLOAD, [$field => true]);
    }
}
