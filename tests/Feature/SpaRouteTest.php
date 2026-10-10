<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SpaRouteTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // The shell is rendered without compiled assets in tests.
        $this->withoutVite();
    }

    public static function spaPaths(): array
    {
        return [
            'root' => ['/'],
            'nested page' => ['/orders/5'],
            'deep page with query' => ['/orders?status=pending&page=2'],
            'page starting with "up"' => ['/updates'],
            'page starting with "api"' => ['/apis'],
        ];
    }

    #[DataProvider('spaPaths')]
    public function test_spa_paths_return_the_app_shell(string $path): void
    {
        $this->get($path)
            ->assertOk()
            ->assertViewIs('app')
            ->assertSee('<div id="app"></div>', false);
    }

    public function test_unknown_api_path_is_not_caught_by_the_spa(): void
    {
        $this->getJson('/api/v1/does-not-exist')
            ->assertNotFound()
            ->assertJsonPath('success', false);
    }

    public function test_health_check_route_is_not_caught_by_the_spa(): void
    {
        $this->get('/up')
            ->assertOk()
            ->assertDontSee('<div id="app"></div>', false);
    }
}
