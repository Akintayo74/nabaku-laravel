<?php

use App\Http\Middleware\AdminOnly;
use App\Http\Middleware\JwtAuthenticate;
use App\Http\Middleware\RequestContext;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(web: __DIR__.'/../routes/web.php', api: __DIR__.'/../routes/api.php', commands: __DIR__.'/../routes/console.php', health: '/up', apiPrefix: 'api/v1')
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias(['jwt' => JwtAuthenticate::class, 'admin' => AdminOnly::class]);
        $middleware->append(RequestContext::class);
        $middleware->trimStrings(except: ['password', 'password_confirmation', 'current_password']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(fn (Request $r, Throwable $e) => true);
        $exceptions->render(function (Throwable $e, Request $r) {
            $status = 500;
            $message = 'Server Error';
            if ($e instanceof ValidationException) {
                $status = 400;
                $message = collect($e->errors())->flatten()->first();
            } elseif ($e instanceof ModelNotFoundException) {
                $status = 404;
                $message = 'Resource Not Found';
            } elseif ($e instanceof HttpExceptionInterface) {
                $status = $e->getStatusCode();
                $message = $e->getMessage() ?: 'Request failed';
            } elseif ((int) $e->getCode() === 11000) {
                $status = 400;
                $message = 'Duplicate field value entered';
            } elseif ($e instanceof InvalidArgumentException) {
                $status = 400;
                $message = $e->getMessage();
            }

            return response()->json(['success' => false, 'message' => $message], $status)->header('X-Request-ID', (string) $r->attributes->get('request_id', ''));
        });
    })->create();
