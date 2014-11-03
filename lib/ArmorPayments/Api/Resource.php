<?php
namespace ArmorPayments\Api;

abstract class Resource {
	protected $host          = null;
	protected $authenticator = null;
	protected $uri_root      = null;

	protected $last_headers = array();

	/**
	 * Construct a new resource
	 * 
	 * @param string                           $host
	 * @param \ArmorPayments\Api\Authenticator $authenticator
	 * @param string                           $uri_root
	 */
	public function __construct($host, $authenticator, $uri_root) {
		$this->host          = $host;
		$this->authenticator = $authenticator;
		$this->uri_root      = $uri_root;
	}

	/**
	 * Get the URI for this object
	 * @return string
	 */
	public function uri($object_id = null) {
		$resource_name = $this->resource_name();
		$uri  = "{$this->uri_root}/{$resource_name}";
		$uri .= empty($object_id) ? '' : "/{$object_id}";
		return $uri;
	}

	/**
	 * Get all objects matching this resource
	 * 
	 * @return array
	 */
	public function all() {
		return $this->request('GET', $this->uri());
	}

	/**
	 * Get a single object with the specified ID
	 * 
	 * @param string $object_id
	 * @return Object
	 */
	public function get($object_id) {
		return $this->request('GET', $this->uri($object_id));
	}

	public function process_header($ch, $header_line) {
		$parts = explode(':', $header_line);
		if (count($parts) == 2) {
			$this->last_headers[$parts[0]] = $parts[1];
		}
		return strlen($header_line);
	}

	///////////////////////////////////////////////////////////////////////
	// PROTECTED METHODS /////////////////////////////////////////////////
	/////////////////////////////////////////////////////////////////////

	protected function connection($uri, $method, $params) {
		$ch = curl_init("{$this->host}/{$uri}");
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($ch, CURLOPT_FRESH_CONNECT,  true);
		curl_setopt($ch, CURLOPT_HEADERFUNCTION, array($this, 'process_header'));
		curl_setopt($ch, CURLOPT_HTTPGET,        ($method == 'GET'));
		curl_setopt($ch, CURLOPT_POST,           ($method == 'POST'));
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		// Add headers
		$headers = array();
		$auth_headers = $this->authenticator->secure_headers($method, $uri);
		foreach ($auth_headers as $key => $value) {
			$headers[] = "{$key}:{$value}";
		}
		$headers[] = 'Accept:application/json';
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		// Add body params
		if (!empty($params)) {
			$params = is_array($params) ? $params : (array)$params;
			curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
		}

		return $ch;
	}

	/**
	 * Send API request
	 * 
	 * @param string $method The HTTP request method (GET, POST, etc)
	 * @param array  $params An array of params
	 * @return mixed An array or object, decoded from the API JSON response
	 */
	protected function request($method, $url, $params=array()) {
		$this->last_headers = array();
		$ch = $this->connection($url, $method, $params);
		$response = curl_exec($ch);
		if (!empty($this->last_headers['Content-Type']) && (stripos($this->last_headers['Content-Type'], 'json') !== false)) {
			$response = json_decode($response);
		}
		return $response;
	}

	protected function resource_name() {
		return strtolower(array_pop(explode('\\', get_class($this))));
	}
}