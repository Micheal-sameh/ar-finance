<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ErrorPagesTest extends TestCase
{
    /**
     * @return array<string, array{int, string}>
     */
    public static function statusPages(): array
    {
        return [
            '401' => [401, 'Sign in required'],
            '403' => [403, 'Access denied'],
            '404' => [404, 'Page not found'],
            '419' => [419, 'Page expired'],
            '429' => [429, 'Too many requests'],
            '500' => [500, 'Something went wrong'],
            '502' => [502, 'Bad gateway'],
            '503' => [503, 'Down for maintenance'],
            '504' => [504, 'Gateway timeout'],
            '418 falls back to 4xx' => [418, 'Request error'],
            '507 falls back to 5xx' => [507, 'Server error'],
        ];
    }

    #[DataProvider('statusPages')]
    public function test_status_renders_branded_error_page(int $status, string $title): void
    {
        Route::get("/_test-error/{$status}", fn () => abort($status));

        $this->get("/_test-error/{$status}")
            ->assertStatus($status)
            ->assertSee($title)
            ->assertSee((string) $status);
    }

    public function test_json_requests_still_receive_json(): void
    {
        Route::get('/_test-error/json', fn () => abort(403));

        $this->getJson('/_test-error/json')->assertForbidden()->assertJsonStructure(['message']);
    }
}
