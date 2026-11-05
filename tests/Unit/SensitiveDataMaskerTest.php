<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Logging\ApiTraffic\SensitiveDataMasker;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SensitiveDataMaskerTest extends TestCase
{
    #[Test]
    public function masksSensitiveKeysCaseInsensitivelyAtAnyDepth(): void
    {
        $masker = new SensitiveDataMasker(['password', 'Authorization', 'token'], '***');

        $masked = $masker->mask([
            'name' => 'Latitude 5540',
            'Password' => 'hunter2',
            'nested' => [
                'TOKEN' => 'abc',
                'items' => [['password' => 'x', 'serial_number' => 'SN-1']],
            ],
            'authorization' => ['Bearer abc'],
            'token_count' => 3,
        ]);

        $this->assertSame([
            'name' => 'Latitude 5540',
            'Password' => '***',
            'nested' => [
                'TOKEN' => '***',
                'items' => [['password' => '***', 'serial_number' => 'SN-1']],
            ],
            'authorization' => '***',
            'token_count' => 3,
        ], $masked);
    }

    #[Test]
    public function leavesDataWithoutSensitiveKeysUntouched(): void
    {
        $data = ['name' => 'Latitude 5540', 'tags' => ['laptop', 'dell'], 'count' => 2, 'empty' => null];

        $this->assertSame($data, (new SensitiveDataMasker(['password'], '***'))->mask($data));
    }
}
