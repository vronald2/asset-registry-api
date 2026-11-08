<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\Contract;
use App\Models\Device;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

final class PaginationLinksTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Device::factory()->count(20)->create();
        Contract::factory()->count(20)->create();
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function listEndpoints(): iterable
    {
        yield 'devices' => ['/api/v1/devices'];
        yield 'contracts' => ['/api/v1/contracts'];
    }

    #[DataProvider('listEndpoints')]
    public function testLinksCarryOnlyThePaginationParameters(string $endpoint): void
    {
        $response = $this->getJson($endpoint . '?api_token=S3CRET&page=2&per_page=2');

        $response->assertOk();
        $urls = $this->paginationUrls($response);
        self::assertNotEmpty($urls);
        foreach ($urls as $url) {
            self::assertStringNotContainsString('S3CRET', $url);
            self::assertEqualsCanonicalizing(['page', 'per_page'], array_keys($this->queryOf($url)));
        }
    }

    #[DataProvider('listEndpoints')]
    public function testNextLinkKeepsThePageSize(string $endpoint): void
    {
        $query = $this->queryOf($this->nextLink($this->getJson($endpoint . '?page=2&per_page=5')));

        self::assertSame('3', $query['page']);
        self::assertSame('5', $query['per_page']);
    }

    #[DataProvider('listEndpoints')]
    public function testNextLinkLeadsToTheNextPage(string $endpoint): void
    {
        $firstPage = $this->getJson($endpoint . '?per_page=3');

        $secondPage = $this->getJson($this->nextLink($firstPage));

        $secondPage->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('meta.per_page', 3);
    }

    #[DataProvider('listEndpoints')]
    public function testLinksCarryTheDefaultPageSize(string $endpoint): void
    {
        $response = $this->getJson($endpoint);

        $response->assertOk();
        $urls = $this->paginationUrls($response);
        self::assertNotEmpty($urls);
        foreach ($urls as $url) {
            self::assertSame('15', $this->queryOf($url)['per_page']);
        }
    }

    /**
     * Every URL the pagination part of the response holds; the links of a missing page are null.
     *
     * @template TResponse of Response
     *
     * @param TestResponse<TResponse> $response
     *
     * @return list<string>
     */
    private function paginationUrls(TestResponse $response): array
    {
        $links = $response->json('links');
        $pages = $response->json('meta.links');
        self::assertIsArray($links);
        self::assertIsArray($pages);

        return array_values(array_filter([...array_values($links), ...array_column($pages, 'url')], is_string(...)));
    }

    /**
     * @template TResponse of Response
     *
     * @param TestResponse<TResponse> $response
     */
    private function nextLink(TestResponse $response): string
    {
        $link = $response->assertOk()->json('links.next');
        self::assertIsString($link);

        return $link;
    }

    /**
     * @return array<int|string, mixed>
     */
    private function queryOf(string $url): array
    {
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        return $query;
    }
}
