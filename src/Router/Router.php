<?php


namespace Lil\Router;

use Lil\Router\Interfaces\RouterInterface;
use Lil\Http\Request;

class Router implements RouterInterface
{
    private $routes;

    private $resolver;

    public function __construct(RouteCollection $routes, ControllerResolver $resolver)
    {
        $this->routes = $routes;
        $this->resolver = $resolver;
    }

    public function match(Request $request)
    {
        $method = $request->getMethod();
        if (strtoupper($request->getMethod()) === 'POST'){
            $method = $this->getMethodFromForm($request);
        }

        /**
         * @var $route Route
         */
        foreach ($this->routes->getRoutes() as $route) {
            if (!in_array($method, $route->getMethods())) {
                continue;
            }

            $request_path = $request->path();
            $route_path = $route->getPattern();

            $pattern = preg_replace_callback('/\{([^\}]+)\}/',function($match) use ($route) {
                $token = '[^\}]+';
                $param = $match[1]; // 'id'
                if (array_key_exists($param, $route->getConstraints())) {
                    $token = $route->getConstraints()[$param];
                }
                return '(?P<'. $param . '>' . $token . ')';
            }, $route_path);

            if (preg_match('~^' . $pattern . '$~i', $request_path, $matches)) {
                $this->dispatchMiddlewares($route, $request);
                $matches = array_filter($matches, '\is_string', ARRAY_FILTER_USE_KEY);
                $resolved = $this->resolver->getController($route, $matches);

                return $resolved;
            }
        }

        throw new \Exception('404');
    }

    private function dispatchMiddlewares (Route $route, Request $request)
    {
        foreach ($this->routes->getMiddlewares() as $middleware) {
            $middleware($request);
        }

        foreach ($route->getMiddlewares() as $middleware) {
            $middleware($request);
        }
    }

    private function getMethodFromForm(Request $request)
    {
        $body = $request->getContent();
        if (!empty($body)) {
            if (is_object($body)) {
                if (property_exists($body, '_method'))  {
                    return $body->{'_method'};
                }
            } else {
                if (isset($body['_method'])) {
                    return $body['_method'];
                }
            }
        }

        return $request->getMethod();
    }

    private function dropSlashes(string $path)
    {
        return trim(trim($path, ' '), '/');
    }
}