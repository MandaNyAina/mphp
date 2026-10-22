<?php

declare(strict_types=1);

use Mphp\Container;
use Mphp\Http\Request;
use Mphp\Http\Response;
use Mphp\Routing\Router;

final class ContainerFixtureA {}

final class ContainerFixtureB
{
    public function __construct(public ContainerFixtureA $a, public int $limit = 5) {}

    public function handle(Request $request, int $id): string
    {
        return $request->path . ':' . $id . ':' . $this->limit;
    }
}

describe('Router', function () {
    it('matches static and parameterised routes', function () {
        $router = new Router();
        $router->get('/users', fn() => 1, name: 'users.index');
        $router->get('/users/{id:\d+}', fn() => 2, name: 'users.show');

        [$route, $params] = $router->match('GET', '/users/42');

        expect($route->name)->toBe('users.show')
            ->and($params)->toBe(['id' => '42'])
            ->and($router->match('GET', '/users')[0]->name)->toBe('users.index')
            ->and($router->url('users.show', ['id' => 7]))->toBe('/users/7');
    });

    it('answers 404 for unknown paths and failed constraints', function () {
        $router = new Router();
        $router->get('/users/{id:\d+}', fn() => 1);

        expect(httpStatus(fn() => $router->match('GET', '/nope'))[0])->toBe(404)
            ->and(httpStatus(fn() => $router->match('GET', '/users/abc'))[0])->toBe(404);
    });

    it('answers 405 with an Allow header when only the method is wrong', function () {
        $router = new Router();
        $router->get('/users', fn() => 1);
        $router->post('/users', fn() => 1);

        [$status, $headers] = httpStatus(fn() => $router->match('DELETE', '/users'));

        expect($status)->toBe(405)->and($headers['Allow'])->toBe('GET, POST');
    });

    it('does not decode route parameters twice', function () {
        $router = new Router();
        $router->get('/files/{name}', fn() => 1);

        // Request already decoded the URL once: "%2520" arrives here as "%20" and must stay that way.
        expect($router->match('GET', '/files/a%20b')[1])->toBe(['name' => 'a%20b']);
    });

    it('survives an export/import round trip (route cache)', function () {
        $router = new Router();
        $router->add('GET', '/x/{id}', [ContainerFixtureB::class, 'handle'], ['csrf'], 'x.show');

        $copy = new Router();
        $copy->import($router->export());

        expect($copy->match('GET', '/x/1')[0]->middleware)->toBe(['csrf'])
            ->and($router->isCacheable())->toBeTrue();

        $router->get('/closure', fn() => 1);
        expect($router->isCacheable())->toBeFalse();
    });
});

describe('Request / Response', function () {
    it('normalises the path and parses the query string', function () {
        $request = Request::create('get', '/a/b/?x=1');

        expect($request->method)->toBe('GET')->and($request->path)->toBe('/a/b')->and($request->input('x'))->toBe('1');
    });

    it('reads the bearer token and detects JSON callers', function () {
        $request = Request::create('GET', '/api/x', headers: ['Authorization' => 'Bearer abc']);

        expect($request->bearerToken())->toBe('abc')->and($request->wantsJson())->toBeTrue();
    });

    it('refuses header values with line breaks (response splitting)', function () {
        expect(fn() => Response::text('x')->withHeader('X-A', "a\r\nSet-Cookie: pwned=1"))->toThrow(InvalidArgumentException::class);
        expect(fn() => Response::redirect("/ok\r\nSet-Cookie: pwned=1"))->toThrow(InvalidArgumentException::class);
    });

    it('escapes markup in JSON so it cannot break out of a script tag', function () {
        $body = Response::json(['x' => '</script><script>alert(1)</script>'])->body;

        expect($body)->not->toContain('</script>')->and(json_decode($body, true)['x'])->toContain('</script>');
    });
});

describe('Container', function () {
    it('autowires dependencies and default values', function () {
        $b = (new Container())->get(ContainerFixtureB::class);

        expect($b->a)->toBeInstanceOf(ContainerFixtureA::class)->and($b->limit)->toBe(5);
    });

    it('calls actions with the request, route params (cast by type) and services', function () {
        $container = new Container();
        $request = Request::create('GET', '/users/9');

        $result = $container->call([ContainerFixtureB::class, 'handle'], ['id' => '9', 'request' => $request]);

        expect($result)->toBe('/users/9:9:5');
    });
});
