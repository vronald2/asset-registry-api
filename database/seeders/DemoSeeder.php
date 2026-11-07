<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Contract;
use App\Models\Device;
use Illuminate\Database\Seeder;

/**
 * Demo devices and contracts for trying out the API. Idempotent: running it again changes nothing.
 */
final class DemoSeeder extends Seeder
{
    private const array DEVICES = [
        [
            'name' => 'Dell Latitude 5440',
            'manufacturer' => 'Dell',
            'model' => 'Latitude 5440',
            'description' => '14" business laptop, 16 GB RAM',
        ],
        [
            'name' => 'Lenovo ThinkPad T14',
            'manufacturer' => 'Lenovo',
            'model' => 'ThinkPad T14 Gen 5',
            'description' => null,
        ],
        [
            'name' => 'HP LaserJet Pro M404dn',
            'manufacturer' => 'HP',
            'model' => 'M404dn',
            'description' => 'Monochrome laser printer',
        ],
        [
            'name' => 'Samsung ViewFinity S8',
            'manufacturer' => 'Samsung',
            'model' => 'S27B800',
            'description' => '27" 4K monitor',
        ],
        [
            'name' => 'Cisco Catalyst 9200',
            'manufacturer' => 'Cisco',
            'model' => 'C9200-24P',
            'description' => '24-port PoE+ switch',
        ],
        [
            'name' => 'Apple iPhone 16',
            'manufacturer' => 'Apple',
            'model' => 'iPhone 16',
            'description' => 'Company phone',
        ],
    ];

    /**
     * The devices of a contract are listed as model => serial number. The Dell Latitude 5440 is on two contracts,
     * with a different serial number on each.
     */
    private const array CONTRACTS = [
        [
            'attributes' => [
                'contract_number' => 'SZ-2026-001',
                'partner_name' => 'Acme Kft.',
                'start_date' => '2026-01-01',
                'end_date' => '2026-12-31',
                'description' => 'Lease of the office laptops, printers and monitors.',
            ],
            'devices' => ['Latitude 5440' => '5CG2341XYZ', 'M404dn' => 'PHBQK12345', 'S27B800' => 'H4ZT500123'],
        ],
        [
            'attributes' => [
                'contract_number' => 'SZ-2026-002',
                'partner_name' => 'Globex Zrt.',
                'start_date' => '2026-03-01',
                'end_date' => null,
                'description' => 'Maintenance of the network equipment.',
            ],
            'devices' => ['C9200-24P' => 'FOC2412X0AB', 'Latitude 5440' => '5CG2341ABC'],
        ],
        [
            'attributes' => [
                'contract_number' => 'SZ-2026-003',
                'partner_name' => 'Initech Bt.',
                'start_date' => '2026-06-15',
                'end_date' => '2027-06-14',
                'description' => null,
            ],
            'devices' => ['iPhone 16' => 'F2LXK0ABCD12', 'ThinkPad T14 Gen 5' => 'PF4ABC12'],
        ],
    ];

    public function run(): void
    {
        $devices = [];

        foreach (self::DEVICES as $attributes) {
            $devices[$attributes['model']] = Device::query()->firstOrCreate(
                ['manufacturer' => $attributes['manufacturer'], 'model' => $attributes['model']],
                $attributes,
            );
        }

        foreach (self::CONTRACTS as $definition) {
            $contract = Contract::query()->firstOrCreate(
                ['contract_number' => $definition['attributes']['contract_number']],
                $definition['attributes'],
            );

            foreach ($definition['devices'] as $model => $serialNumber) {
                $contract->devices()->syncWithoutDetaching([
                    $devices[$model]->id => ['serial_number' => $serialNumber],
                ]);
            }
        }
    }
}
