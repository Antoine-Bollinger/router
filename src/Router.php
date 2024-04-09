<?php 
namespace Abollinger;

use \Symfony\Component\Yaml\Yaml;

/**
 * Abstract class Router
 *
 * Base abstract class defining common functionalities for routing.
 * Child classes should implement the interface Initializer\Router and provide
 * specific implementations for routing-related operations.
 */
abstract class Router implements Initializer\Router
{
    /**
     * @var string $requestedRoute The requested route obtained from the URL
     */
    protected $requestedRoute;

    /**
     * @var array $routes Collection of routes defined for the application
     */
    protected $routes;

    /**
     * @var array $route Information about the current matched route
     */
    protected $route;

    /**
     * Obtains the requested route from the provided URI.
     *
     * @param string $uri The URI string
     * @return string The sanitized requested route
     */
    protected function getRequestedRoute(
        $uri = ""
    ) :string {
        return str_replace(APP_SUBDIR, "", ($uri !== "/" && substr($uri, -1) === "/") ? rtrim($uri) : $uri);
    }
    
    /**
     * Finds a matching route based on the provided routes and route string.
     *
     * @param array $routes Collection of routes to search within
     * @param string $route The route string to match
     * @return array Information about the matching route, if found
     */
    protected function findMatchingRoute(
        $routes = [],
        $route = ""
    ) :array {
        try {
            if (!is_array($routes)) return [];
            foreach($routes as $routeConfig) {
                $pattern = $routeConfig["path"];
                $pattern = preg_replace_callback('/\{(\w+)\}/', function ($matches) {
                    return '(?<' . $matches[1] . '>[^\/]+)';
                }, $pattern);
                $pattern = '~^' . $pattern . '$~';
                if (preg_match_all($pattern, $route, $matches, PREG_SET_ORDER)) {
                    $params = [];
                    foreach ($matches[0] as $key => $value) {
                        if (!is_numeric($key)) {
                            $params[$key] = $value;
                        }
                    }
                    $routeConfig["params"] = $params;
                    return $routeConfig;
                }
            }
            return [];
        } catch(\Exception $e) {
            return [];
        }
    }

    /**
     * Retrieves routes from YAML files present in the specified directory.
     *
     * @param string $dir   The directory containing YAML route files
     * @return array        An array of parsed routes from YAML files
     */
    public function getRoutesFromYaml(
        $dir = ""
    ) :array {
        try {
            $routes = [];
            foreach(scandir($dir) as $file) {
                $extension = pathinfo($file, PATHINFO_EXTENSION);
                if (pathinfo($file, PATHINFO_EXTENSION) === 'yaml') {
                    $tmp = Yaml::parseFile(implode("/", [$dir, $file]));
                    if (is_array($tmp)) {
                        foreach($tmp as $v) {
                            $routes[] = $v;
                        }
                    }
                }
            }
            return $routes;
        } catch(\Exception $e) {
            return [];
        }
    }

    /**
     * Retrieves routes from PHP controller files within the specified directory.
     *
     * @param string $directory     The directory path containing PHP controller files.
    * @return array                 An array containing extracted routes with their path, name, and controller information.
     */
    public function getRoutesFromDirectory(
        $directory,
        $namespace
    ) {
        try {
            $routes = [];
            $controllerFiles = glob($directory . "/*.php");
            foreach ($controllerFiles as $file) {
                require_once $file; 
                $className = basename($file, ".php");
                $fullClassName = $namespace . "\\Controller\\" . $className;
                if (class_exists($fullClassName)) {
                    $reflectionClass = new \ReflectionClass($fullClassName);
                    foreach ($reflectionClass->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
                        $docComment = $method->getDocComment();
                        if (strpos($docComment, "@Route") !== false) {
                            $route = $this->parseRouteAnnotation($docComment);
                            if ($route !== null) {
                                $routes[] = [
                                    "path" => $route["path"],
                                    "name" => $route["name"],
                                    "auth" => $route["auth"],
                                    "controller" => $fullClassName,
                                ];
                            }
                        }
                    }
                }
            }
            return $routes;
        } catch(\Exception $e) {
            return [];
        }
    }
    
    /**
     * Parses the @Route annotation from a docblock comment.
     *
     * @param string $docComment The docblock comment containing the @Route annotation.
     *
     * @return array|null An array with the extracted path and name if found, otherwise null.
     */
    private function parseRouteAnnotation(
        $docComment
    ) {
        $pattern = '/@Route\("(.*?)"[^)]*name="(.*?)"(?:[^)]*auth=(true|false))?/';
    
        preg_match($pattern, $docComment, $matches);
    
        if (count($matches) >= 3) {
            $path = $matches[1];
            $name = $matches[2];
            $auth = isset($matches[3]) ? ($matches[3] === 'true') : false;
    
            return [
                'path' => $path,
                'name' => $name,
                'auth' => $auth,
            ];
        }
        return null;
    }
}