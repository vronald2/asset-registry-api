<?php

declare(strict_types=1);

namespace App\Logging\ApiTraffic;

use Illuminate\Container\Attributes\Config;

/**
 * Replaces the values of sensitive keys (passwords, tokens, auth headers, ...) before they are stored.
 */
final readonly class SensitiveDataMasker
{
    /**
     * Lower-cased sensitive keys, as a lookup table.
     *
     * @var array<string, true>
     */
    private array $sensitiveKeys;

    /**
     * @param list<string> $sensitiveKeys
     */
    public function __construct(
        #[Config('api.logging.masked_keys')] array $sensitiveKeys,
        #[Config('api.logging.mask')] private string $replacement,
    ) {
        $this->sensitiveKeys = array_fill_keys(array_map(strtolower(...), $sensitiveKeys), true);
    }

    /**
     * Masks the values of the sensitive keys at any depth; keys are matched case-insensitively.
     *
     * @param array<array-key, mixed> $data
     * @return array<array-key, mixed>
     */
    public function mask(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_string($key) && isset($this->sensitiveKeys[strtolower($key)])) {
                $data[$key] = $this->replacement;
            } elseif (is_array($value)) {
                $data[$key] = $this->mask($value);
            }
        }

        return $data;
    }
}
