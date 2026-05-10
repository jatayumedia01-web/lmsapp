<?php
declare(strict_types=1);

namespace Devithor;

use Closure;

/**
 * Pattern-based router. Patterns can include `{name}` placeholders that get
 * captured into [Request::$params]. Handlers can be:
 *   - a Closure
 *   - a [Controller::class, 'method'] tuple
 *   - a 'Controller@method' string
 *
 * Middleware is just an array of callables run before the handler — first one
 * to call [Response::*] short-circuits the chain.
 */
final class Router
{
    /** @var array<int, array{method:string, pattern:string, regex:string, handler:mixed, middleware:array}> */
    private array $routes = [];

    public function get(string $pattern, $handler, array $middleware = []): void { $this->add('GET', $pattern, $handler, $middleware); }
    public function post(string $pattern, $handler, array $middleware = []): void { $this->add('POST', $pattern, $handler, $middleware); }
    public function put(string $pattern, $handler, array $middleware = []): void { $this->add('PUT', $pattern, $handler, $middleware); }
    public function patch(string $pattern, $handler, array $middleware = []): void { $this->add('PATCH', $pattern, $handler, $middleware); }
    public function delete(string $pattern, $handler, array $middleware = []): void { $this->add('DELETE', $pattern, $handler, $middleware); }

    /** Group routes under a common prefix and shared middleware. */
    public function group(string $prefix, array $middleware, Closure $register): void
    {
        $register(new RouterGroup($this, $prefix, $middleware));
    }

    public function add(string $method, string $pattern, $handler, array $middleware = []): void
    {
        $regex = $this->compile($pattern);
        $this->routes[] = [
            'method'     => $method,
            'pattern'    => $pattern,
            'regex'      => $regex,
            'handler'    => $handler,
            'middleware' => $middleware,
        ];
    }

    public function dispatch(Request $request): void
    {
        // Handle CORS preflight inline so we don't have to declare OPTIONS routes.
        if ($request->method === 'OPTIONS') {
            Response::noContent();
        }

        foreach ($this->routes as $route) {
            if ($route['method'] !== $request->method) {
                continue;
            }
            if (preg_match($route['regex'], $request->path, $matches)) {
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
                $request->params = $params;

                // Run middleware. Each receives the request and a continuation;
                // if it doesn't call `next($request)` the chain stops.
                $stack = $route['middleware'];
                $core  = function (Request $req) use ($route) {
                    return $this->call($route['handler'], $req);
                };
                $runner = array_reduce(
                    array_reverse($stack),
                    fn ($next, $mw) => fn ($req) => $mw($req, $next),
                    $core,
                );
                $runner($request);
                return;
            }
        }
        Response::notFound();
    }

    /** Convert "/api/v1/courses/{id}" → "#^/api/v1/courses/(?<id>[^/]+)$#". */
    private function compile(string $pattern): string
    {
        $regex = preg_replace_callback(
            '/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/',
            fn ($m) => '(?<' . $m[1] . '>[^/]+)',
            $pattern,
        );
        return '#^' . $regex . '$#';
    }

    private function call($handler, Request $request)
    {
        if ($handler instanceof Closure) {
            return $handler($request);
        }
        if (is_string($handler) && str_contains($handler, '@')) {
            [$class, $method] = explode('@', $handler);
            return (new $class())->$method($request);
        }
        if (is_array($handler) && count($handler) === 2) {
            [$class, $method] = $handler;
            return (new $class())->$method($request);
        }
        throw new \RuntimeException('Unsupported route handler shape.');
    }
}

/** Sugar around grouped route registration so the group() callback reads cleanly. */
final class RouterGroup
{
    public function __construct(
        private Router $router,
        private string $prefix,
        private array $middleware,
    ) {}

    public function get(string $pattern, $handler, array $extra = []): void
    {
        $this->router->add('GET', $this->prefix . $pattern, $handler, [...$this->middleware, ...$extra]);
    }
    public function post(string $pattern, $handler, array $extra = []): void
    {
        $this->router->add('POST', $this->prefix . $pattern, $handler, [...$this->middleware, ...$extra]);
    }
    public function put(string $pattern, $handler, array $extra = []): void
    {
        $this->router->add('PUT', $this->prefix . $pattern, $handler, [...$this->middleware, ...$extra]);
    }
    public function patch(string $pattern, $handler, array $extra = []): void
    {
        $this->router->add('PATCH', $this->prefix . $pattern, $handler, [...$this->middleware, ...$extra]);
    }
    public function delete(string $pattern, $handler, array $extra = []): void
    {
        $this->router->add('DELETE', $this->prefix . $pattern, $handler, [...$this->middleware, ...$extra]);
    }
}
