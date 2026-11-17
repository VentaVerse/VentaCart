<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Pipeline\Pipeline;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Illuminate\Routing\SortedMiddleware;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lets one extension draw the checkout page itself (a checkout split by
 * seller, say). While it is registered, the `checkout.index` route answers
 * with the extension's controller; the route, its middleware (signed-in
 * customers only) and every other checkout route stay core's, so placing
 * the order, saving addresses, shipping rates and payment returns still
 * run through core's checks.
 *
 * A controller is an invokable class, or one with a public index() method.
 * When the registered class is missing or is neither, core draws its own
 * checkout and the problem is logged.
 *
 * The controller's own middleware (HasMiddleware::middleware(), or the
 * getMiddleware() of a controller that registers it in its constructor)
 * runs before it, after the route's, exactly as if the route named that
 * controller: a throttle or an authorization check an extension declares
 * there holds.
 */
final class CheckoutPage
{
    private static ?string $controller = null;

    /** @var array<string, true> */
    private static array $logged = [];

    /**
     * Register the controller, from an extension's service provider. One
     * per store: a second, different one is refused and logged.
     *
     * @param  class-string  $controllerClass
     */
    public static function use(string $controllerClass): void
    {
        $controllerClass = ltrim($controllerClass, '\\');
        if (self::$controller !== null && self::$controller !== $controllerClass) {
            self::warn('second', 'A second checkout page was refused; the store keeps the first.', ['kept' => self::$controller, 'refused' => $controllerClass]);

            return;
        }

        self::$controller = $controllerClass;
    }

    /** The registered controller's class, or null. */
    public static function controller(): ?string
    {
        return self::$controller;
    }

    public static function forget(): void
    {
        self::$controller = null;
        self::$logged = [];
    }

    /**
     * The registered controller and the method to call, or null when core
     * draws the checkout.
     *
     * @return array{0: object, 1: string}|null
     */
    public static function resolve(): ?array
    {
        $class = self::$controller;
        if ($class === null) {
            return null;
        }

        if (! class_exists($class)) {
            self::warn('missing', 'The registered checkout page does not exist; core draws the checkout.', ['class' => $class]);

            return null;
        }

        $method = self::method($class);
        if ($method === null) {
            self::warn('shape', 'The registered checkout page is not a controller (invokable, or with an index method); core draws the checkout.', ['class' => $class]);

            return null;
        }

        return [app($class), $method];
    }

    /**
     * The registered controller's answer, through its own middleware, or
     * null when core draws the checkout.
     */
    public static function respond(Request $request): ?Response
    {
        $takeover = self::resolve();
        if ($takeover === null) {
            return null;
        }
        [$controller, $method] = $takeover;

        /** @var Router $router */
        $router = app('router');
        // A route naming the controller works out its middleware as the
        // router would, HasMiddleware and getMiddleware() alike.
        $route = (new Route(['GET'], $request->path(), ['uses' => $controller::class . '@' . $method, 'controller' => $controller::class . '@' . $method]))
            ->setRouter($router)
            ->setContainer(app());
        $route->controller = $controller;
        $middleware = (new SortedMiddleware($router->middlewarePriority, $router->resolveMiddleware($route->controllerMiddleware())))->all();

        return (new Pipeline(app()))
            ->send($request)
            ->through($middleware)
            ->then(fn (Request $request) => Router::toResponse($request, app()->call([$controller, $method])));
    }

    private static function method(string $class): ?string
    {
        $reflection = new \ReflectionClass($class);
        if (! $reflection->isInstantiable()) {
            return null;
        }
        foreach (['__invoke', 'index'] as $name) {
            if ($reflection->hasMethod($name)) {
                $method = $reflection->getMethod($name);
                if ($method->isPublic() && ! $method->isStatic()) {
                    return $name;
                }
            }
        }

        return null;
    }

    private static function warn(string $reason, string $message, array $context): void
    {
        if (isset(self::$logged[$reason])) {
            return;
        }
        self::$logged[$reason] = true;
        Log::warning($message, $context);
    }
}
