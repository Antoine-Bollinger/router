<?php 
/*
 * This file is part of the Abollinger\Router package.
 *
 * (c) Antoine Bollinger <abollinger@partez.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

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
    /** @var string $requestedRoute The requested route obtained from the URL */
    protected string $requestedRoute;

    /** @var array $routes Collection of routes defined for the application */
    protected array $routes;

    /** @var array $route Information about the current matched route */
    protected array $route;

    /**
     * Obtains the requested route from the provided URI.
     *
     * @param string $uri The URI string
     * @param string $subdir The subdirectory containing the app and that should be deleted from the URI
     * 
     * @return string The sanitized requested route
     */
    protected function getRequestedRoute(
        string $uri = "",
        string $subdir = ""
    ) :string {
        return str_replace($subdir, "", ($uri !== "/" && substr($uri, -1) === "/") ? rtrim($uri) : $uri);
    }
    
    /**
     * Finds a matching route based on the provided routes and route string.
     *
     * @param array $routes Collection of routes to search within
     * @param string $route The route string to match
     * 
     * @return array Information about the matching route, if found
     */
    protected function findMatchingRoute(
        array $routes = [],
        string $route = ""
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
     * 
     * @return array        An array of parsed routes from YAML files
     */
    protected function getRoutesFromYaml(
        string $dir = ""
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
     * 
     * @return array                An array containing extracted routes with their path, name, and controller information.
     */
    protected function getRoutesFromDirectory(
        string $directory
    ) :array {
        try {
            $routes = [];
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory));
        
            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getExtension() === "php") {
                    $filePath = $file->getPathname();
                    $namespace = $this->getNamespaceFromFile($filePath);
                    $className = $this->getClassNameFromFile($filePath);
    
                    if ($className) {
                        $fullClassName = $namespace ? $namespace . "\\" . $className : $className;
    
                        if (class_exists($fullClassName)) {
                            $reflectionClass = new \ReflectionClass($fullClassName);
                            $reflectionMethods = $reflectionClass->getMethods();

                            foreach ($reflectionMethods as $reflectionMethod) {
                                $docComment = $reflectionMethod->getDocComment();
    
                                if ($docComment && strpos($docComment, "@Route") !== false) {
                                    $route = $this->parseRouteAnnotation($docComment);
    
                                    if ($route !== null) {
                                        $routes[] = [
                                            "path" => $route["path"],
                                            "name" => $route["name"],
                                            "auth" => $route["auth"],
                                            "admin" => $route["admin"],
                                            "verb" => $route["verb"],
                                            "controller" => $fullClassName,
                                            "method" => $reflectionMethod->getName()
                                        ];
                                    }
                                }
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
     * Extracts the namespace from a PHP file.
     *
     * @param string $filePath The file path.
     * 
     * @return string|null The extracted namespace, or null if not found.
     */
    private function getNamespaceFromFile(
        string $filePath
    ): ?string {
        $namespace = null;
        $handle = fopen($filePath, "r");
        
        while (($line = fgets($handle)) !== false) {
            if (preg_match('/^namespace\s+(.+);$/', trim($line), $matches)) {
                $namespace = trim($matches[1]);
                break;
            }
        }
    
        fclose($handle);
        return $namespace;
    }

    /**
     * Extracts the class name from a PHP file.
     *
     * @param string $filePath The file path.
     * 
     * @return string|null The extracted class name, or null if not found.
     */
    private function getClassNameFromFile(
        string $filePath
    ): ?string {
        $className = null;
        $handle = fopen($filePath, "r");
    
        while (($line = fgets($handle)) !== false) {
            if (preg_match('/^(final|abstract)?\s*class\s+(\w+)/', trim($line), $matches)) {
                $className = trim($matches[2]);
                break;
            }
        }
    
        fclose($handle);
        return $className;
    }

    /**
     * Parses the @Route annotation from a docblock comment.
     *
     * @param string $docComment The docblock comment containing the @Route annotation.
     *
     * @return array|null An array with the extracted path and name if found, otherwise null.
     */
    private function parseRouteAnnotation(
        string $docComment
    ) :array|null {
        // Match the full @Route(...) string
        if (!preg_match('/@Route\((.*?)\)/', $docComment, $routeMatch)) {
            return null;
        }

        $paramsString = $routeMatch[1];
        $params = [];

        // Match key="value" or key=value (for booleans)
        preg_match_all('/(\w+)=("([^"]+)"|(true|false))/', $paramsString, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $key = $match[1];
            $value = $match[3] !== '' ? $match[3] : $match[4]; // If string, use it; else it's a boolean
            $params[$key] = ($value === 'true') ? true : (($value === 'false') ? false : $value);
        }

        // Ensure required values exist
        if (!isset($params['path'], $params['name'])) {
            return null;
        }

        // Apply fallbacks
        $params['auth'] = $params['auth'] ?? (bool)($_ENV['APP_AUTH'] ?? false);
        $params['admin'] = $params['admin'] ?? false;
        $params['verb'] = isset($params['verb']) ? strtolower($params['verb']) : 'get';
        
        return $params;
    }

}