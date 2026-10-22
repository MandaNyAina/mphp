<?php

declare(strict_types=1);

namespace Mphp;

use Closure;
use Mphp\Database\Connection;
use Mphp\Database\Model;
use Mphp\Http\ErrorRenderer;
use Mphp\Http\FormRequest;
use Mphp\Http\HttpException;
use Mphp\Http\Middleware;
use Mphp\Http\Request;
use Mphp\Http\Response;
use Mphp\Middleware\DebugBar;
use Mphp\Middleware\SecurityHeaders;
use Mphp\Middleware\Throttle;
use Mphp\Middleware\VerifyCsrf;
use Mphp\Routing\RouteDefinition;
use Mphp\Routing\RouteScanner;
use Mphp\Routing\Router;
use Mphp\Security\Crypto;
use Mphp\Security\Csrf;
use Mphp\Security\Session;
use Mphp\Storage\Uploader;
use Mphp\Support\Config;
use Mphp\Support\Env;
use Mphp\Support\Log;
use Mphp\View\View;
use Mphp\View\Vite;
use Throwable;

/**
 * The kernel: boots config + services, loads routes, and turns a Request into a Response.
 *
 *   $app = new App(__DIR__ . '/..');
 *   $app->run();                       // web
 *   $app->handle(Request::create(...)) // tests
 */
final class App
{
    public readonly Container $container;

    public readonly Config $config;

    public readonly Router $router;

    private ?Request $request = null;

    private const ALIASES = [
        'csrf' => VerifyCsrf::class,
        'throttle' => Throttle::class,
    ];

    /** Where .env, storage/ and the SQLite file live. Differs from $basePath only inside a PHAR (read-only code). */
    public readonly string $dataPath;

    private ?RouteDefinition $matched = null;

    public function __construct(public readonly string $basePath, ?string $dataPath = null)
    {
        $this->dataPath = $dataPath ?? $basePath;

        Env::load($this->dataPath . '/.env');
        $this->config = new Config($basePath . '/config');
        $this->container = new Container();
        $this->router = new Router();

        Log::setDirectory($this->dataPath . '/storage/logs');
        $this->registerServices();
        $this->loadRoutes();
    }

    public function isPhar(): bool
    {
        return str_starts_with($this->basePath, 'phar://');
    }

    public function debug(): bool
    {
        return (bool) $this->config->get('app.debug', false);
    }

    public function run(): void
    {
        $this->handle(Request::capture((bool) $this->config->get('app.trust_proxy', false)))->send();
    }

    public function handle(Request $request): Response
    {
        $this->request = $request;
        $this->container->instance(Request::class, $request);

        // Security headers wrap everything, including error pages.
        $outer = $this->debug() ? [SecurityHeaders::class, DebugBar::class] : [SecurityHeaders::class];
        $response = $this->through($outer, $request, function (Request $r): Response {
            try {
                return $this->through((array) $this->config->get('app.middleware', ['csrf']), $r, $this->dispatch(...));
            } catch (Throwable $e) {
                return $this->container->get(ErrorRenderer::class)->render($r, $e);
            }
        });

        return $request->method === 'HEAD' ? new Response('', $response->status, $response->headers()) : $response;
    }

    private function dispatch(Request $request): Response
    {
        if ($request->bodyError !== null) {
            throw new HttpException(400, $request->bodyError);
        }

        [$route, $params] = $this->router->match($request->method, $request->path);
        $this->matched = $route;
        $request = $request->withAttributes($params);
        $this->container->instance(Request::class, $request);

        return $this->through($route->middleware, $request, fn(Request $r): Response => $this->invoke($route, $r));
    }

    private function invoke(RouteDefinition $route, Request $request): Response
    {
        $named = $request->attributes + ['request' => $request];

        // Actions that type-hint a FormRequest get it already validated (422 otherwise).
        if (is_array($route->handler)) {
            foreach ((new \ReflectionMethod($route->handler[0], $route->handler[1]))->getParameters() as $parameter) {
                $type = $parameter->getType();
                if ($type instanceof \ReflectionNamedType && is_subclass_of($type->getName(), FormRequest::class)) {
                    $named[$parameter->getName()] = $type->getName()::from($request);
                }
            }
        }

        $result = $this->container->call($route->handler, $named);

        return match (true) {
            $result instanceof Response => $result,
            $result === null => Response::noContent(),
            is_string($result) => Response::html($result),
            is_array($result), is_object($result) => Response::json($result),
            default => Response::text((string) $result),
        };
    }

    /**
     * Run $core inside a stack of middleware. Each entry is an alias ("csrf"),
     * "alias:arg1,arg2" (e.g. "throttle:5,60") or a Middleware class name.
     *
     * @param list<string> $stack
     * @param Closure(Request): Response $core
     */
    private function through(array $stack, Request $request, Closure $core): Response
    {
        $next = $core;

        foreach (array_reverse($stack) as $spec) {
            [$name, $argument] = array_pad(explode(':', $spec, 2), 2, null);
            $middleware = $this->container->get(self::ALIASES[$name] ?? $name);
            if (!$middleware instanceof Middleware) {
                throw new \LogicException("[$name] does not implement " . Middleware::class . '.');
            }
            $arguments = $argument === null ? [] : explode(',', $argument);
            $following = $next;
            $next = static fn(Request $r): Response => $middleware->handle($r, $following, ...$arguments);
        }

        return $next($request);
    }

    private function registerServices(): void
    {
        $c = $this->container;
        $base = $this->basePath;

        $c->instance(self::class, $this);
        $c->instance(Container::class, $c);
        $c->instance(Config::class, $this->config);
        $c->instance(Router::class, $this->router);

        $c->singleton(Connection::class, function (): Connection {
            $config = (array) $this->config->get('database.connections.' . $this->config->get('database.default'));
            if (($config['driver'] ?? '') === 'sqlite' && ($config['database'] ?? ':memory:') !== ':memory:' && !str_starts_with($config['database'], '/')) {
                $config['database'] = $this->dataPath . '/' . $config['database'];
            }

            return new Connection($config);
        });
        $c->singleton(Session::class, fn(): Session => new Session([
            'name' => $this->config->get('app.session_name', 'mphp_session'),
            'secure' => $this->request?->secure ?? false,
        ]));
        $c->singleton(Csrf::class, fn(Container $c): Csrf => new Csrf($c->get(Session::class)));
        $c->singleton(Crypto::class, fn(): Crypto => new Crypto((string) $this->config->get('app.key', '')));
        $c->singleton(Vite::class, fn(): Vite => new Vite($base . '/public'));
        $c->singleton(View::class, fn(Container $c): View => new View($base . '/resources/views', $c->get(Vite::class), $c->get(Csrf::class)));
        $c->singleton(Uploader::class, fn(): Uploader => new Uploader($this->dataPath . '/storage/uploads'));
        $c->singleton(DebugBar::class, fn(Container $c): DebugBar => new DebugBar($c->get(Connection::class), $c->get(View::class), fn(): ?RouteDefinition => $this->matched));
        $c->singleton(ErrorRenderer::class, fn(Container $c): ErrorRenderer => new ErrorRenderer($c->get(View::class), $this->debug()));
        $c->singleton(Throttle::class, fn(): Throttle => new Throttle($this->dataPath . '/storage/cache/throttle'));
        $c->singleton(VerifyCsrf::class, fn(Container $c): VerifyCsrf => new VerifyCsrf($c->get(Csrf::class), (array) $this->config->get('app.csrf_except', [])));

        Model::setConnection($c->get(Connection::class));
    }

    private function loadRoutes(): void
    {
        $cache = $this->basePath . '/storage/cache/routes.php';

        if ($this->config->get('app.route_cache', !$this->debug()) && is_file($cache)) {
            $this->router->import(require $cache);

            return;
        }

        (new RouteScanner($this->basePath . '/app/Controllers', 'App\\Controllers'))->scanInto($this->router);

        $file = $this->basePath . '/routes/web.php';
        if (is_file($file)) {
            (static function (Router $router, self $app) use ($file): void {
                require $file;
            })($this->router, $this);
        }
    }
}
