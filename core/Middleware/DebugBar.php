<?php

declare(strict_types=1);

namespace Mphp\Middleware;

use Closure;
use Mphp\Database\Connection;
use Mphp\Http\Middleware;
use Mphp\Http\Request;
use Mphp\Http\Response;
use Mphp\Routing\RouteDefinition;
use Mphp\View\View;

/**
 * Development toolbar, only registered when APP_DEBUG=true:
 *  - HTML pages get a bar at the bottom (status, time, memory, matched route, every SQL query,
 *    repeated queries flagged as a likely N+1). It needs no JavaScript: it is a <details> element.
 *  - every response gets a Server-Timing header, so API calls show app/db time in the browser's Network tab.
 */
final class DebugBar implements Middleware
{
    public const CSS_PATH = '/_mphp/bar.css';

    /** @param Closure(): ?RouteDefinition $matchedRoute */
    public function __construct(
        private readonly Connection $db,
        private readonly View $view,
        private readonly Closure $matchedRoute,
    ) {}

    #[\Override]
    public function handle(Request $request, callable $next, string ...$arguments): Response
    {
        if ($request->path === self::CSS_PATH) {
            return new Response(self::css(), 200, ['Content-Type' => 'text/css; charset=utf-8', 'Cache-Control' => 'no-store']);
        }

        $start = hrtime(true);
        $this->db->enableQueryLog();

        $response = $next($request);

        $ms = (hrtime(true) - $start) / 1e6;
        $queries = $this->db->queries;
        $dbMs = array_sum(array_column($queries, 'ms'));

        $response = $response->withHeader('Server-Timing', sprintf('app;dur=%.1f, db;dur=%.1f;desc="%d queries"', $ms, $dbMs, count($queries)));

        $position = strripos($response->body, '</body>');
        if ($position === false || !str_contains($response->header('Content-Type') ?? '', 'text/html')) {
            return $response;
        }

        $counts = array_count_values(array_column($queries, 'sql'));
        $route = ($this->matchedRoute)();
        $bar = $this->view->render('_debug/bar', [
            'status' => $response->status,
            'method' => $request->method,
            'path' => $request->path,
            'ms' => $ms,
            'dbMs' => $dbMs,
            'memory' => memory_get_peak_usage(true),
            'queries' => $queries,
            'repeated' => array_filter($counts, static fn(int $n): bool => $n >= 3),
            'route' => $route === null ? '(no route)' : trim(($route->name ?? '') . ' ' . (is_array($route->handler) ? substr((string) strrchr($route->handler[0], '\\'), 1) . '@' . $route->handler[1] : 'Closure')),
        ]);

        return new Response(substr_replace($response->body, $bar, $position, 0), $response->status, $response->headers());
    }

    private static function css(): string
    {
        return <<<'CSS'
            #mphp-debug{position:fixed;left:0;right:0;bottom:0;z-index:2147483647;font:12px/1.4 ui-monospace,SFMono-Regular,Menlo,monospace;color:#e5e7eb}
            #mphp-debug summary{display:flex;gap:.9rem;align-items:center;padding:.4rem .8rem;background:#111827;cursor:pointer;list-style:none;white-space:nowrap;overflow:auto}
            #mphp-debug summary::-webkit-details-marker{display:none}
            #mphp-debug .dot{width:.65rem;height:.65rem;border-radius:50%;background:#22c55e;flex:none}
            #mphp-debug .s4 .dot{background:#f59e0b}
            #mphp-debug .s5 .dot{background:#ef4444}
            #mphp-debug .warn{color:#fbbf24}
            #mphp-debug .panel{max-height:50vh;overflow:auto;padding:.8rem 1rem;background:#1f2937;border-top:1px solid #374151}
            #mphp-debug h4{margin:.8rem 0 .3rem;color:#9ca3af;text-transform:uppercase;font-size:11px;letter-spacing:.08em}
            #mphp-debug h4:first-child{margin-top:0}
            #mphp-debug table{border-collapse:collapse;width:100%}
            #mphp-debug td{padding:.2rem .6rem .2rem 0;vertical-align:top;border-bottom:1px solid #374151;word-break:break-all}
            #mphp-debug td.n{white-space:nowrap;text-align:right;color:#93c5fd}
            #mphp-debug code{color:#d1d5db}
            CSS;
    }
}
