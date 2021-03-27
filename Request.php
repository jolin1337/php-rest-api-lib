<?php

namespace gd\rest;

/**
*
*/
class Request {
	private $app = null;
	private $route = null;
	private $serverOptions = [];
	private $cookieOptions = [];
	private $extraParams = [];
	function __construct ($app, $route, $options=[], $extraParams=[]) {
		if (!is_array($options)) $options = [];
		$this->serverOptions = isset($options['SERVER']) ? $options['SERVER'] : $_SERVER;
		$this->cookieOptions = isset($options['COOKIE']) ? $options['COOKIE'] : $_COOKIE;
		$this->extraParams   = $extraParams;
		$this->app = $app;
		$this->route = $route;
	}

	public function getRoute () {
		return $this->route;
	}
	public function getApp () {
		return $this->app;
	}

	public function usingSSL () {
		return ( ! empty( $this->serverOptions['HTTPS'] ) && $this->serverOptions['HTTPS'] == 'on' );
	}

	public function getIp () {
		$ipaddress = '';
	    if ($this->serverOptions['HTTP_CLIENT_IP'] != '127.0.0.1')
	        $ipaddress = $this->serverOptions['HTTP_CLIENT_IP'];
	    else if ($this->serverOptions['REMOTE_ADDR'] != '127.0.0.1')
	        $ipaddress = $this->serverOptions['REMOTE_ADDR'];
	    else if ($this->serverOptions['HTTP_X_FORWARDED_FOR'] != '127.0.0.1')
	        $ipaddress = $this->serverOptions['HTTP_X_FORWARDED_FOR'];
	    else if ($this->serverOptions['HTTP_X_FORWARDED'] != '127.0.0.1')
	        $ipaddress = $this->serverOptions['HTTP_X_FORWARDED'];
	    else if ($this->serverOptions['HTTP_FORWARDED_FOR'] != '127.0.0.1')
	        $ipaddress = $this->serverOptions['HTTP_FORWARDED_FOR'];
	    else if ($this->serverOptions['HTTP_FORWARDED'] != '127.0.0.1')
	        $ipaddress = $this->serverOptions['HTTP_FORWARDED'];
	    else
	        $ipaddress = 'UNKNOWN';
	    return $ipaddress;
	}

	public function getHostName ($useForwardedHost = false) {
		$host = ( $useForwardedHost && isset( $this->serverOptions['HTTP_X_FORWARDED_HOST'] ) ) ? $this->serverOptions['HTTP_X_FORWARDED_HOST'] : ( isset( $this->serverOptions['HTTP_HOST'] ) ? $this->serverOptions['HTTP_HOST'] : null );
	    $host = isset( $host ) ? $host : $this->serverOptions['SERVER_NAME'];
	    return $host;
	}

	public function getPort () {
		$ssl = $this->usingSSL();
		$port     = $this->serverOptions['SERVER_PORT'];
	    $port     = ( ( ! $ssl && $port=='80' ) || ( $ssl && $port=='443' ) ) ? '' : $port;
	    return $port;
	}

	public function getHost ($useForwardedHost = false) {
		return $this->getHostName() . ':' . $this->getPort();
	}

	public function getProtocol () {
		$ssl      = $this->usingSSL();
		$sp       = strtolower( $this->serverOptions['SERVER_PROTOCOL'] );
		$protocol = substr( $sp, 0, strpos( $sp, '/' ) ) . ( ( $ssl ) ? 's' : '' );
		return $protocol;
	}


	public function getOrigin ($useForwardedHost = false) {
	    $host     = $this->getHostName($useForwardedHost);
	    $protocol = $this->getProtocol();
	    return $protocol . '://' . $host;
	}

	public function getPath () {
		return $this->serverOptions['REQUEST_URI'];
	}

	public function getPathName () {
		return $this->serverOptions['PHP_SELF'];
	}

	public function getUrl ($useForwardedHost = false) {
	    return $this->getOrigin($this->serverOptions, $useForwardedHost) . $this->getPath();
	}

	public function getMethod () {
		$options = $this->serverOptions;
		return Request::method($options);
	}

	public function getCookies () {
		return $this->cookieOptions;
	}
	public function getOptions () {
		return clone (object) [
			'COOKIE' => $this->cookieOptions,
			'SERVER' => $this->serverOptions
		];
	}

	public function getParams ($expectedParams=null, $inputParams=[], bool $validate) {
		$inputParams = array_merge(is_array($inputParams) ? $inputParams : [], is_array($this->extraParams) ? $this->extraParams : []);
		$requestParams = $validate ? Request::validateParams($expectedParams, $inputParams) : $inputParams;
		return $requestParams ? (object) $requestParams : False;
	}

	public function getPayloadParams ($payloadRequest=null, $inputParams=[], bool $validate=True) {
		return $this->getPutParams($payloadRequest, $inputParams, $validate);
	}

	public function getPostParams ($postRequest=null, $inputParams=[], bool $validate=True) {
		return $this->getParams($postRequest, array_merge($inputParams, $_POST), $validate);
	}
	public function getPutParams ($putRequest=null, $inputParams=[], bool $validate=True) {
		$fileContents = file_get_contents("php://input");
		$_PUT = json_decode($fileContents, True);
		if (!$_PUT)
			parse_str($fileContents, $_PUT);
		return $this->getParams($putRequest, array_merge($inputParams, $_PUT), $validate);
	}
	public function getDeleteParams ($deleteRequest=null, $inputParams=[], bool $validate=True) {
		parse_str(file_get_contents("php://input"), $_DELETE);
		return $this->getParams($deleteRequest, array_merge($inputParams, $_DELETE), $validate);
	}
	public function getQueryParams ($queryRequest=null, $inputParams=[], bool $validate=True) {
		return $this->getParams($queryRequest, array_merge($inputParams, $_GET), $validate);
	}
	public function getFileParams ($fileRequest=null, $inputParams=[], bool $validate=True) {
		// TODO: change structure of files param
		return $this->getParams($fileRequest, array_merge($inputParams, $_FILES), $validate);
	}

	public function getUrlParams($urlRequest=null, $inputParams=[], bool $validate=True) {
		return $this->getParams($urlRequest, array_merge($inputParams, (array) $this->route->getParams()), $validate);
	}
	public function getAllParams($urlRequest=null, $inputParams=[], bool $validate=True) {
		return $this->getParams($urlRequest, array_merge(
			(array) $this->getUrlParams($urlRequest, $inputParams, False),
			(array) $this->getQueryParams($urlRequest, $inputParams, False),
			(array) $this->getPostParams($urlRequest, $inputParams, False),
			(array) $this->getPutParams($urlRequest, $inputParams, False)
			// (array)  $this->getDeleteParams($urlRequest, $inputParams, False), // Since this is the same as put params for now we ignore this and save performance
			// (array)  $this->getFileParams($urlRequest, $inputParams, False) // TODO: Make this more lite the other params
		), $validate);
	}

	public static function method ($serverOptions) {
		$method = isset($serverOptions['HTTP_ACCESS_CONTROL_REQUEST_METHOD']) ?
								$serverOptions['HTTP_ACCESS_CONTROL_REQUEST_METHOD'] : $serverOptions['REQUEST_METHOD'];
		return strtolower($method);
	}

	public static function validateParams ($parameters, $validateAgainst, $defaultdefault = NULL) {
		if (!$parameters) return $validateAgainst;
		// global $_GET, $_POST;
		if(is_string($validateAgainst))
			$t_vals = &$GLOBALS[$validateAgainst];
		else if(is_array($validateAgainst))
			$t_vals = $validateAgainst;
		if(!isset($t_vals) || !is_array($t_vals))
			return False;
		$res = array();
		$defaultOptions = array('optional' => False, 'default' => $defaultdefault, 'match' => False, 'values' => False);
		foreach ($parameters as $param => $options) {
			if(!is_array($options)) {
				$param = $options;
				$options = array('optional' => $defaultOptions['optional'], 'default' => $options, 'match' => $defaultOptions['match']);
			}
			if(!isset($options['optional'])) $options['optional'] = $defaultOptions['optional'];
			if(!isset($options['default'])) $options['default'] = $defaultdefault;
			if(!isset($options['match'])) $options['match'] = $defaultOptions['match'];
			if(!isset($options['values']) || !is_array($options['values'])) $options['values'] = $defaultOptions['values'];

			if (!isset($t_vals[$param]) && $options['optional'] === True) {
				$res[$param] = $options['default'];
				continue;
			}
			else if (!isset($t_vals[$param]) && $options['optional'] === False) {
				// var_dump($param, /*$t_vals[$param], */$options, $t_vals);
				return False;
			}

			if((!is_array($options['values']) || in_array($t_vals[$param], $options['values'])) && (!is_string($options['match']) || preg_match($options['match'], $t_vals[$param]))) {
				$res[$param] = $t_vals[$param];
				continue;
			}
			return False;
		}
		return $res;
	}
}

?>
