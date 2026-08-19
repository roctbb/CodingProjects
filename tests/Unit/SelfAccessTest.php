<?php

namespace Tests\Unit;

use App\Http\Middleware\SelfAccess;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class SelfAccessTest extends TestCase
{
    public function testAdminCanAccessAnotherUsersProfile(): void
    {
        Auth::shouldReceive('user')->once()->andReturn($this->user(1, 'admin'));

        $response = (new SelfAccess())->handle(
            $this->requestForProfile(2),
            function () {
                return response('allowed');
            }
        );

        $this->assertSame('allowed', $response->getContent());
    }

    public function testUserCanAccessTheirOwnProfile(): void
    {
        Auth::shouldReceive('user')->once()->andReturn($this->user(2, 'student'));

        $response = (new SelfAccess())->handle(
            $this->requestForProfile(2),
            function () {
                return response('allowed');
            }
        );

        $this->assertSame('allowed', $response->getContent());
    }

    public function testUserCannotAccessAnotherUsersProfile(): void
    {
        Auth::shouldReceive('user')->once()->andReturn($this->user(1, 'student'));

        $this->expectException(HttpException::class);

        (new SelfAccess())->handle($this->requestForProfile(2), function () {
            return response('allowed');
        });
    }

    private function requestForProfile(int $id): Request
    {
        $request = Request::create('/insider/profile/' . $id . '/edit', 'GET');
        $route = new Route(['GET'], '/insider/profile/{id}/edit', function () {
            return null;
        });
        $route->bind($request);
        $request->setRouteResolver(function () use ($route) {
            return $route;
        });

        return $request;
    }

    private function user(int $id, string $role): User
    {
        return (new User())->forceFill([
            'id' => $id,
            'role' => $role,
        ]);
    }
}
