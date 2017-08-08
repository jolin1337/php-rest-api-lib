<?php

namespace gd\rest;
use Exception;

class Response {
	private $app = null;
	private $ended = False;

	function __construct ($app) {
		$this->app = $app;
	}
	public function getApp () {
		return $this->app;
	}

	public function withStatus ($code) {
		http_response_code((int) $code);
		return $this;
	}

	public function setHeader($headerName, $headerValue) {
		header($headerName . ': ' . $headerValue);
		return $this;
	}

	public function withContentType ($type) {
		return $this->setHeader('Content-Type', $type);
	}
	public function redirectTo ($url) {
		return $this->setHeader('Location', $url);
	}

	public function write ($data) {
		if ($this->ended !== True) {
			if (is_array($data) || $data instanceof stdClass)
				$data = json_encode($data, JSON_UNESCAPED_UNICODE);
			echo $data;
		} else {
			throw new Exception("Error already ended response", 1);
		}
		return $this;
	}

	public function end ($moreData = '') {
		$this->write($moreData);
		$this->ended = True;
		session_write_close();
			// fastcgi_finish_request();
		return $this;
	}
}
?>