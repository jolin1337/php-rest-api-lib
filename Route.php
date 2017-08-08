<?php

namespace gd\rest;
use Exception;

include_once 'Request.php';
include_once 'Response.php';


class Route {
	private $method = 'get';
	private $path = '';
	private $function = null;

	// Generated variables for performance
	private $params = [];
	private $partsOfPath;
	private $processedRequest = False;
	
	function __construct (String $method, String $path, $function) {
		$this->method = $method;
		$this->path = $this->parsePath($path);
		$this->function = $function;
	}
	private function parsePath (String $path) {
		$path = Route::removeDoubleSlashes($path); // Remove double slashes (//)
		$parts = explode('/', $path);
		foreach ($parts as $index => $part) {
			if (strpos($part, '{') === 0 && strpos($part, '}') === strlen($part) - 1) {
				$this->params[substr($part, 1, -1)] = $index;
			}
		}
		$this->partsOfPath = $parts;
		return $path;
	}

	public function getParams() {
		if ($this->processedRequest !== False) {
			$path = Route::removeDoubleSlashes($this->processedRequest->getPathName());
			$parts = explode('/', $path);
			$params = [];
			$isLastParamIndicator = False;
			foreach ($this->params as $param => $index) {
				$isLastParamIndicator = strpos($param, '?') !== False;
				if (isset($parts[$index])) {
					$params[$param] = $parts[$index];
				} else {
					$params[$param] = null;
				}

				if ($isLastParamIndicator) {
					$params[$param] .= implode('/', array_slice($parts, $index+1));
				}
			}
			return (object) $params;
		}
		throw new Exception("Error Request not processed for this route", 1);
	}

	public function getPath () {
		return $this->path;
	}
	public function getMethod () {
		return $this->method;
	}
	public function setMethod (String $method) {
		$this->method = $method;
	}
	public function matchRequest (Request $req) {
		if ($this->method !== $req->getMethod()) return False;

		$reqPathName = Route::removeDoubleSlashes($req->getPathName());
		$parts = explode('/', $reqPathName);
		foreach ($this->partsOfPath as $index => $part) {
			$isParameter = in_array($index, $this->params);
			if ($isParameter && strpos($part, '?') !== False) return True;
			if (!$isParameter && (!isset($parts[$index]) || $part !== $parts[$index])) {
				return False;
			}
		}
		return count($parts) === count($this->partsOfPath);
	}
	public function processRequest (Request $req, Response $resp) {
		if ($this->processedRequest) throw new Exception("Error Request processed twise", 1);
		$this->processedRequest = $req;
		if ($this->matchRequest($req)) {
			$callback = $this->function;
			if (is_callable($callback))
				return !$callback($req, $resp);
			else if (method_exists($callback, 'processRequest')) {
				return $callback->processedRequest($req, $resp);
			}
		}
		return $this;
	}

	public static function removeDoubleSlashes (String $string) {
		return preg_replace('/\/\/+/', '/', $string); // Remove double slashes (//)
	}
}

?>