<?php

namespace gd\rest;
use Exception;

require_once 'Request.php';
require_once 'Response.php';
require_once 'Route.php';

if (!class_exists('\gd\rest\App')):

class App {
	private $basePath = '';
	private $requestPathName = null;
	private $router = [
		'get' => [],
		'post' => [],
		'put' => [],
		'delete' => [],
		// '/root/path/to/function => callback'
	];
	private $errorRoutes = [];
	private $subApps = [];

	public function setBasePath ($path) {
		$this->basePath = $path;
		return $this;
	}
	public function getBasePath () {
		return $this->basePath;
	}
	public function getPath () {
		return $this->getBasePath();
	}
	public function setRequestPathName ($path) {
		$this->requestPathName = $path;
		return $this;
	}
	public function getRequestPathName () {
		return $this->requestPathName;
	}

	public function any (String $path, ...$args) {
		$httpRequests = array_keys($this->router);
		foreach ($args as $arg) {
			$this->map($path, $httpRequests, $arg); 
		}
		return $this;
	}

	public function get (String $path, ...$args) {
		foreach ($args as $arg) {
			$this->map($path, ['get'], $arg); 
		}
		return $this;
	}

	public function post (String $path, ...$args) {
		foreach ($args as $arg) {
			$this->map($path, ['post'], $arg); 
		}
		return $this;
	}

	public function put (String $path, ...$args) {
		foreach ($args as $arg) {
			$this->map($path, ['put'], $arg); 
		}
		return $this;
	}

	public function delete (String $path, ...$args) {
		foreach ($args as $arg) {
			$this->map($path, ['delete'], $arg); 
		}
		return $this;
	}

	public function error (callable ...$args) {
		foreach ($args as $arg) {
			$this->errorRoutes[] = $arg;
		}
		return $this;
	}

	public function use ($path, ...$args) {
		foreach ($args as $arg) {
			// if (is_callable($arg))
			// 	$this->subApps[] = $arg;
			// foreach ($arg->router as $method => $routes) {
			// 	if (isset($this->router[$method]))
			// 		$this->router[$method] = array_merge(array_values($this->router[$method]), array_values($routes));
			// }
			if (!is_string($arg))
				$arg->setBasePath($path . '/' . $arg->getBasePath());
			$this->any($path, $arg);
		}
		return $this;
	}

	public function matchRequest($request) {
		$method = $request->getMethod();
		$options = (array)$request->getOptions();
		$basePath = Route::removeDoubleSlashes($this->basePath ? $this->basePath : '/');
		if (strpos($options['SERVER']['PHP_SELF'], $basePath) !== 0)
			return False;
		$options['SERVER']['PHP_SELF'] = substr($options['SERVER']['PHP_SELF'], strlen($basePath)-1);
		foreach ($this->router[$method] as $route) {
			$request = new Request($this, $route, $options);
			if ($route->matchRequest($request)) {
				return True;
			}
		}
		return False;
	}

	public function processRequest ($req=null, $res=null) {
		$isSubRoute = $req instanceof Request;
		$extraParams = null;
		$options = $isSubRoute ? (array) $req->getOptions() : [
			'SERVER' => $_SERVER,
			'COOKIE' => $_COOKIE
		];

		if (!$isSubRoute) $extraParams = $req;
		if ($this->requestPathName) {
			$options['SERVER']['PHP_SELF'] = $this->requestPathName;
		}
		$basePath = Route::removeDoubleSlashes($this->basePath ? $this->basePath : '/');
		if (strpos($options['SERVER']['PHP_SELF'], $basePath) !== 0)
			return $this;
		$options['SERVER']['PHP_SELF'] = substr($options['SERVER']['PHP_SELF'], strlen($basePath)-1);
		$response = $isSubRoute ? $res : new Response($this);

		$method = $isSubRoute ? $req->getMethod() : Request::method($_SERVER);
		$foundOne = False;
		if (isset($this->router[$method])) {
			foreach ($this->router[$method] as $route) {
				$request = new Request($this, $route, $options, $extraParams);
				if ($route->matchRequest($request)) {
					$result = $route->processRequest($request, $response);
					$foundOne = True;
					if (!$result) {
						break;
					}
				}
			}
		}
		if ($foundOne === False) {
			$response->withStatus(404);
			$request = new Request($this, null, $options, $extraParams);
			foreach ($this->errorRoutes as $route) {
				$route($request, $response);
			}
		} else if ($isSubRoute) return False;
		return $this;
	}

	private function map ($path, $methods, $function=False) {
		if (!is_string($path)) {
			$this->map('/', $methods, $path);
			$path = '/';
		}
		$httpRequests = array_keys($this->router);
		foreach ($methods as $method) {
			if (in_array($method, $httpRequests)) {
				if (is_callable($function)) {
					$this->router[$method][] = new Route($method, $path, $function);
				} else if ($function instanceof App) {
					$this->router[$method][] = $function;
				} else if (is_string($function)) {
					$this->router[$method][] = new Route($method, $path . '/{aa?}', function (Request $request, Response $response) use ($path, $function) {
						$reqPath = $request->getPathName();
						$hasTrailingSlash = strpos($reqPath, '/');
						$reqPath = substr($request->getPathName(), strlen($path) + ($hasTrailingSlash ? 1 : 0));
						$includePath = $function . (!$hasTrailingSlash ? '/' . $reqPath : '');
						$includePath = preg_replace('/[^\.a-zA-Z0-9\-\_\/]/', '', $includePath);
						if (substr($includePath, -strlen('.php')) === '.php' && is_file($includePath)) {
							$response->withContentType('text/html');
							include($includePath);
						} else if (substr($includePath, -strlen('.html')) === '.html' && is_file($includePath)) {
							$response->withContentType('text/html');
							include($includePath);
						} else if (substr($includePath, -strlen('.css')) === '.css' && is_file($includePath)) {
							$response->withContentType('text/css');
							include($includePath);
						} else if (substr($includePath, -strlen('.js')) === '.js' && is_file($includePath)) {
							$response->withContentType('text/javascript');
							include($includePath);
						} else if (is_file($includePath . '/index.php')) {
							$response->withContentType('text/html');
							include($includePath . '/index.php');
						} else if (is_file($includePath . '/index.html')) {
							$response->withContentType('text/html');
							include($includePath . '/index.html');
						} else echo "--=( " . $includePath;
					});
				}
			}
		}
		return $this;
	}
}

endif;
?>