<?php

namespace Tests\Feature\Api;

use App\Exceptions\BusinessException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\TestCase;

class ErrorResponseTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Throw-away routes that raise each kind of error, so the central renderer can be tested in isolation.
        Route::prefix('api/v1/_test')->group(function () {
            Route::get('/unauthenticated', fn () => throw new AuthenticationException);
            Route::get('/forbidden', fn () => throw new AuthorizationException('You cannot do this.'));
            Route::get('/missing-model', fn () => throw (new ModelNotFoundException)->setModel('App\\Models\\Order', [1]));
            Route::post('/validation', fn (Request $request) => $request->validate(['name' => 'required']));
            Route::get('/conflict', fn () => throw new class('This order was changed by someone else.') extends BusinessException
            {
                public function status(): int
                {
                    return 409;
                }
            });
            Route::get('/business-rule', fn () => throw new class('Not enough stock.') extends BusinessException
            {
                public function errors(): array
                {
                    return ['items' => ['Not enough stock.']];
                }
            });
            Route::get('/crash', fn () => throw new RuntimeException('SQLSTATE secret details'));
        });
    }

    public function test_unknown_api_route_returns_json_404(): void
    {
        $this->getJson('/api/v1/does-not-exist')
            ->assertNotFound()
            ->assertExactJson(['success' => false, 'message' => 'Resource not found.']);
    }

    public function test_unknown_api_route_returns_json_even_without_accept_header(): void
    {
        $this->get('/api/v1/does-not-exist')
            ->assertNotFound()
            ->assertHeader('Content-Type', 'application/json')
            ->assertJson(['success' => false]);
    }

    public function test_missing_model_returns_404_without_leaking_the_class_name(): void
    {
        $this->getJson('/api/v1/_test/missing-model')
            ->assertNotFound()
            ->assertExactJson(['success' => false, 'message' => 'Resource not found.']);
    }

    public function test_unauthenticated_returns_401(): void
    {
        $this->getJson('/api/v1/_test/unauthenticated')
            ->assertUnauthorized()
            ->assertExactJson(['success' => false, 'message' => 'Unauthenticated.']);
    }

    public function test_forbidden_returns_403(): void
    {
        $this->getJson('/api/v1/_test/forbidden')
            ->assertForbidden()
            ->assertExactJson(['success' => false, 'message' => 'You cannot do this.']);
    }

    public function test_validation_error_returns_422_with_field_errors(): void
    {
        $this->postJson('/api/v1/_test/validation', [])
            ->assertUnprocessable()
            ->assertJson(['success' => false])
            ->assertJsonValidationErrors(['name']);
    }

    public function test_business_exception_uses_its_own_status(): void
    {
        $this->getJson('/api/v1/_test/conflict')
            ->assertConflict()
            ->assertExactJson(['success' => false, 'message' => 'This order was changed by someone else.']);
    }

    public function test_business_exception_defaults_to_422_with_errors(): void
    {
        $this->getJson('/api/v1/_test/business-rule')
            ->assertUnprocessable()
            ->assertExactJson([
                'success' => false,
                'message' => 'Not enough stock.',
                'errors' => ['items' => ['Not enough stock.']],
            ]);
    }

    public function test_unexpected_error_returns_generic_500_without_details(): void
    {
        $this->getJson('/api/v1/_test/crash')
            ->assertInternalServerError()
            ->assertExactJson(['success' => false, 'message' => 'Something went wrong. Please try again later.']);
    }
}
