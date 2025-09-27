<?php

namespace App\Http\Middleware;

use App\Support\PerformanceMonitor;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PerformanceTrackingMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $monitor = PerformanceMonitor::start('request.' . $request->route()?->getName() ?? 'unknown');
        
        $monitor->mark('request_started');

        $response = $next($request);

        $monitor->mark('response_ready');

        // 將效能資料添加到響應頭
        $performanceData = $monitor->summary();
        $response->headers->set('X-Performance-Data', json_encode($performanceData));

        return $response;
    }
}