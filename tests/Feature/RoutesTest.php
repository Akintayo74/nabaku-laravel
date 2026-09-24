<?php

namespace Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class RoutesTest extends TestCase
{
    public function test_all_legacy_routes_resolve_to_implemented_controllers(): void
    {
        $routes = json_decode(file_get_contents(base_path('tests/fixtures/routes.json')), true);
        foreach ($routes as $route) {
            $url = preg_replace('/:[A-Za-z]+/', '507f1f77bcf86cd799439011', $route['url']);
            $match = Route::getRoutes()->match(Request::create($url, $route['method']));
            if ($url === '/') {
                $this->assertTrue($match->getAction('uses') instanceof \Closure);

                continue;
            }
            [$controller,$method] = explode('@', $match->getActionName());
            $this->assertTrue(method_exists($controller, $method), $route['method'].' '.$route['url']);
        }
        $this->getJson('/')->assertOk()->assertJsonPath('message', 'NABAKU api is running.');
        $this->getJson('/api/v1/authentication/states')->assertOk()->assertJsonCount(37, 'data');
        $this->getJson('/api/v1/wallet')->assertUnauthorized();
    }
}
