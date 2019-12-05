<?php


namespace Calc\Http\Router;


use Psr\Container\ContainerInterface;

class ControllerResolver
{

    protected $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function getController(Route $route) : ResolvedController
    {
        $arguments = [];

        if ($route->getHandler() instanceof \Closure) {
            return new ResolvedController($route->getHandler(), null, []);
        }

        $exploded = explode('@', $route->getHandler());
        $controller_class = 'App\\Http\\Controllers\\' . $exploded[0] ;

        if (count($exploded) <> 2) {
            throw new \Exception('Controller name and class invalid in route ' .$route->getName());
        }
        if (class_exists($controller_class)) {

            try {
                $instance = $this->container->get($controller_class);
            } catch (\Exception $e) {
                throw new \Exception("Controller class $controller_class can't be resolveed : " .$e->getMessage());
            }

            if (!method_exists($instance, $exploded[1])) {
                throw new \Exception('Method ' . $exploded[0] . " does not exist in $controller_class");
            }

            $arguments = $this->resolveControllerMethod($controller_class, $exploded[1]);

            return new ResolvedController($instance, $exploded[1], $arguments);
        }
    }

    protected function getControllerDirectory()
    {
        $dir = $this->container->get('config.controllers.directory');
        if (is_dir($dir)) {

            $uppercase_dir = ucwords(str_replace(DIRECTORY_SEPARATOR, ' ', $dir));

            return str_replace(' ', '\\', $uppercase_dir) . '\\';
        }

        throw new \Exception("Dir $dir does not exist");
    }

    protected function resolveControllerMethod(string $controller_class, string $method )
    {
        $reflection = new \ReflectionMethod($controller_class, $method);

        $params = $reflection->getParameters();

        $arguments = [];

        /**
         * @var $parameter \ReflectionParameter
         */
        foreach ($params as $parameter) {
            if ($paramClass = $parameter->getClass()) {
                $arguments[] = $this->container->get($paramClass->getName());
            } elseif ($parameter->isArray()) {
                $arguments[] = [];
            } else {
                if (!$parameter->isDefaultValueAvailable()) {
                    throw new \Exception('Unable to resolve "' . $parameter->getName() . '"" in service "' . $controller_class . '"');
                }
                $arguments[] = $parameter->getDefaultValue();
            }
        }

        return $arguments;
    }
}