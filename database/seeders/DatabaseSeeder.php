<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\DeviceCategory;
use App\Models\Contract;
use App\Models\Device;
use Illuminate\Database\Seeder;

/**
 * Realistic demo data: a small device catalogue and three contracts with serial-numbered units.
 * The same device type appears on several contracts, each unit with a different serial number.
 */
final class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $devices = $this->seedDevices();

        $this->seedContract(
            attributes: [
                'contract_number' => 'SZ-2026/001',
                'partner_name' => 'Példa Kft.',
                'start_date' => '2026-01-01',
                'end_date' => '2026-12-31',
                'description' => 'Irodai informatikai eszközök bérlése',
            ],
            units: [
                [$devices['latitude'], '5CD4211XKQ'],
                [$devices['latitude'], '5CD4211XKR'],
                [$devices['latitude'], '5CD4211XKS'],
                [$devices['monitor'], 'CN-0P2723-74261'],
                [$devices['monitor'], 'CN-0P2723-74262'],
                [$devices['printer'], 'PHBBK12345'],
            ],
        );

        $this->seedContract(
            attributes: [
                'contract_number' => 'SZ-2026/002',
                'partner_name' => 'Minta Zrt.',
                'start_date' => '2026-03-01',
                'end_date' => null,
                'description' => 'Határozatlan idejű mobil eszköz bérlet',
            ],
            units: [
                [$devices['latitude'], '5CD4307ABC'],
                [$devices['thinkpad'], 'PF4XK2LM'],
                [$devices['iphone'], 'F2LXK9ABCDEF'],
                [$devices['iphone'], 'F2LXK9ABCDEG'],
                [$devices['tablet'], 'R52W30ABCDE'],
            ],
        );

        $this->seedContract(
            attributes: [
                'contract_number' => 'SZ-2026/003',
                'partner_name' => 'Teszt Bt.',
                'start_date' => '2026-06-15',
                'end_date' => '2027-06-14',
                'description' => 'Hálózati és munkaállomás üzemeltetés',
            ],
            units: [
                [$devices['switch'], 'FOC2741X0AB'],
                [$devices['switch'], 'FOC2741X0AC'],
                [$devices['desktop'], 'CZC4251XYZ'],
            ],
        );
    }

    /**
     * @return array<string, Device>
     */
    private function seedDevices(): array
    {
        $catalogue = [
            'latitude' => ['Latitude 5540', 'Dell', DeviceCategory::Laptop, '15.6" business laptop'],
            'thinkpad' => ['ThinkPad T14 Gen 5', 'Lenovo', DeviceCategory::Laptop, '14" business laptop'],
            'desktop' => ['EliteDesk 800 G9 Mini', 'HP', DeviceCategory::Desktop, null],
            'monitor' => ['P2723DE', 'Dell', DeviceCategory::Monitor, '27" QHD USB-C monitor'],
            'printer' => ['LaserJet Pro M404dn', 'HP', DeviceCategory::Printer, 'Monochrome laser printer'],
            'iphone' => ['iPhone 16', 'Apple', DeviceCategory::Phone, null],
            'tablet' => ['Galaxy Tab S9', 'Samsung', DeviceCategory::Tablet, null],
            'switch' => ['Catalyst 1000-24T', 'Cisco', DeviceCategory::Network, '24-port gigabit switch'],
        ];

        return array_map(
            static fn (array $item): Device => Device::query()->create([
                'name' => $item[0],
                'manufacturer' => $item[1],
                'category' => $item[2],
                'description' => $item[3],
            ]),
            $catalogue,
        );
    }

    /**
     * @param array<string, string|null> $attributes
     * @param list<array{Device, string}> $units
     */
    private function seedContract(array $attributes, array $units): void
    {
        $contract = Contract::query()->create($attributes);

        foreach ($units as [$device, $serialNumber]) {
            $contract->contractDevices()->create([
                'device_id' => $device->id,
                'serial_number' => $serialNumber,
            ]);
        }
    }
}
