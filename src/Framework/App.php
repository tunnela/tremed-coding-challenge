<?php

namespace Tunnela\TremedCodingChallenge\Framework;

class App
{
    protected $database;

    protected $routes = [];

    protected $middlewares = [];

    protected $options = [];

    public function __construct($options = [])
    {
        $this->options = $options;
        $this->database = new Database($this->options['database'] ?? []);
    }

    public function run() 
    {
        foreach ($this->routes as $route) {
            if (($matches = $this->matches($route)) === false) {
                continue;
            }
            $routeParams = array_filter(
                $matches, 
                fn($key) => is_string($key), 
                ARRAY_FILTER_USE_KEY
            );

            $response = $this->call(
                $route['handler'],
                $routeParams
            );

            if ($response === null) {
                continue;
            }
            $middlewares = $this->middlewares;

            usort($middlewares, function($a, $b) {
                return $a['priority'] <=> $a['priority'];
            });

            foreach ($middlewares as $middleware) {
                $response = $this->call(
                    $middleware['handler'], 
                    [
                        'response' => $response, 
                        'route' => $route
                    ] + $routeParams
                );
            }
            echo $response;
            exit;
        }
        http_response_code(404);
    }

    public function options($key = null) 
    {
        $options = $this->options;

        if ($key === null) {
            return $options;
        }
        $parts = explode('.', $key);

        foreach ($parts as $part) {
            if (!isset($options[$part])) {
                return null;
            }
            $options = $options[$part];
        }
        return $options;
    }

    protected function call($handler, $additionalData = []) 
    {
        $data = [];
        $data['app'] = $this;
        $data['database'] = $this->database;
        $data['data'] = (object) $this->data();
        $data['headers'] = (object) $this->headers();

        $data += $additionalData;

        if (is_string($handler) && class_exists($handler, true)) {
            $handler = [new $handler, '__invoke'];
        }
        if (is_array($handler) && is_string($handler[0])) {
            $handler = [new $handler[0], $handler[1]];
        }
        // get args in the route handler func
        $argumentNames = $this->argumentNames($handler);

        // let's make each arg null by default
        $args = array_fill_keys($argumentNames, null);

        // based on what args route handler 
        // func wants, we'll inject just them
        $args = array_merge(
            $args,
            array_intersect_key(
                $data,
                $args
            )
        );

        $handler = $handler instanceof \Closure ? 
            \Closure::bind($handler, $this, null) :
            $handler;

        return call_user_func_array($handler, $args);
    }

    public function database() 
    {
        return $this->database;
    }

    public function middleware($handler, $priority = 0, $options = []) 
    {
        $this->middlewares[] = [
            'priority' => 0,
            'handler' => $handler
        ] + $options;

        return $this;
    }

    public function route($path, $handler, $options = []) 
    {
        $paths = is_array($path) ? $path : [$path];

        foreach ($paths as $singlePath) {
            $this->routes[] = [
                'regex' => $this->regexify($singlePath),
                'handler' => $handler
            ] + $options;
        }
        return $this;
    }

    public function get($path, $handler, $options = []) 
    {
        return $this->route($path, $handler, ['method' => 'GET'] + $options);
    }

    public function post($path, $handler, $options = []) 
    {
        return $this->route($path, $handler, ['method' => 'POST'] + $options);
    }

    public function put($path, $handler, $options = []) 
    {
        return $this->route($path, $handler, ['method' => 'PUT'] + $options);
    }

    public function delete($path, $handler, $options = []) 
    {
        return $this->route($path, $handler, ['method' => 'DELETE'] + $options);
    }

    public function view($name, $status = 200)
    {
        http_response_code($status);

        header('Content-Type: text/html');

        return file_get_contents(
            $this->options['viewPath'] . '/' . $this->escapeFileName($name) . '.html'
        );
    }

    public function json($data, $status = 200)
    {
        http_response_code($status);
        
        header('Content-Type: application/json');

        return json_encode($data, JSON_PRETTY_PRINT);
    }

    public function method() 
    {
        return $_SERVER['REQUEST_METHOD'];
    }

    public function path() 
    {
        return $_SERVER['REQUEST_URI'];
    }

    public function headers() 
    {
        return getallheaders();
    }

    public function data()
    {
        $method = $this->method();
        $headers = $this->headers();

        if ($method == 'GET') {
            return $_GET;
        } else if ($method == 'POST' || $method == 'PUT') {
            $input = file_get_contents('php://input');
            $contentType = $headers['Content-Type'] ?? null;

            if ($contentType == 'application/json') {
                return json_decode($input, true);
            }
            parse_str($input, $vars);

            return $vars ?: [];
        }
        return [];
    }

    protected function regexify($path)
    {
        $trimmed = rtrim($path, '/');

        return preg_replace_callback(
            '#\*#', 
            function($matches) {
                return '.*';
            }, 
            preg_replace_callback(
                '#\{([a-z0-9_\-]+)\}#', 
                function($matches) {
                    return '(?P<' . $matches[1] . '>[^/]+)';
                }, 
                $trimmed
            )
        );
    }

    protected function matches($route)
    {
        $requestUri = rtrim($_SERVER['REQUEST_URI'], '/');
        $isMatch = preg_match(
            '#^' . $route['regex'] . '$#', 
            $requestUri, 
            $matches
        );
        
        $isValidMethod = !isset($route['method']) || 
            $route['method'] == $_SERVER['REQUEST_METHOD'];

        if (!$isValidMethod || !$isMatch) {
            return false;
        }
        return $matches ?: [];
    }

    protected function argumentNames($func) 
    {
        $reflection = is_array($func) ? 
            new \ReflectionMethod($func[0], $func[1]) : 
            ($func instanceof \Closure ? 
                new \ReflectionFunction($func) : 
                new \ReflectionMethod($func, '__invoke'));

        $result = [];

        foreach ($reflection->getParameters() as $param) {
            $result[] = $param->name;   
        }
        return $result;
    }

    protected function escapeFileName($filename) 
    {
        return str_replace(['\\', '/'], '', $filename);
    }

    public function isJson($text) 
    {
        return is_string($text) && preg_match('#^[\{\[]#', $text);
    }
}
