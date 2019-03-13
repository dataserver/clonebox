<?php
/**
 * CodeIgniter
 *
 * An open source application development framework for PHP
 *
 * This content is released under the MIT License (MIT)
 *
 * Copyright (c) 2014-2019 British Columbia Institute of Technology
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 *
 * @package    CodeIgniter
 * @author     CodeIgniter Dev Team
 * @copyright  2014-2019 British Columbia Institute of Technology (https://bcit.ca/)
 * @license    https://opensource.org/licenses/MIT	MIT License
 * @link       https://codeigniter.com
 * @since      Version 3.0.0
 * 
 * This file was modified for Clonebox project.
 * 
 * @filesource
 */

class Request {

	/**
	 * IP address of the current user.
	 *
	 * @var string
	 */
	protected $ipAddress = '';

	/**
	 * Proxy IPs
	 *
	 * @var string|array
	 */
	protected $proxyIPs;

	/**
	 * Request method.
	 *
	 * @var string
	 */
	protected $method;

	/**
	 * Stores values we've retrieved from
	 * PHP globals.
	 *
	 * @var array
	 */
	protected $globals = [];
	//--------------------------------------------------------------------

	/**
	 * Constructor.
	 *
	 * @param object $config
	 */
	public function __construct()
	{
		$this->body = file_get_contents('php://input');
		$this->method = $this->getServer('REQUEST_METHOD') ?? 'GET';
	}
	//--------------------------------------------------------------------

	/**
	 * Gets the user's IP address.
	 *
	 * @return string IP address
	 */
	public function getIPAddress(): string
	{
		if (! empty($this->ipAddress))
		{
			return $this->ipAddress;
		}

		$proxy_ips = $this->proxyIPs;
		if (! empty($this->proxyIPs) && ! is_array($this->proxyIPs))
		{
			$proxy_ips = explode(',', str_replace(' ', '', $this->proxyIPs));
		}

		$this->ipAddress = $this->getServer('REMOTE_ADDR');

		if ($proxy_ips)
		{
			foreach (['HTTP_X_FORWARDED_FOR', 'HTTP_CLIENT_IP', 'HTTP_X_CLIENT_IP', 'HTTP_X_CLUSTER_CLIENT_IP'] as $header)
			{
				if (($spoof = $this->getServer($header)) !== null)
				{
					// Some proxies typically list the whole chain of IP
					// addresses through which the client has reached us.
					// e.g. client_ip, proxy_ip1, proxy_ip2, etc.
					sscanf($spoof, '%[^,]', $spoof);

					if (! $this->isValidIP($spoof))
					{
						$spoof = null;
					}
					else
					{
						break;
					}
				}
			}

			if ($spoof)
			{
				for ($i = 0, $c = count($proxy_ips); $i < $c; $i ++)
				{
					// Check if we have an IP address or a subnet
					if (strpos($proxy_ips[$i], '/') === false)
					{
						// An IP address (and not a subnet) is specified.
						// We can compare right away.
						if ($proxy_ips[$i] === $this->ipAddress)
						{
							$this->ipAddress = $spoof;
							break;
						}

						continue;
					}

					// We have a subnet ... now the heavy lifting begins
					isset($separator) || $separator = $this->isValidIP($this->ipAddress, 'ipv6') ? ':' : '.';

					// If the proxy entry doesn't match the IP protocol - skip it
					if (strpos($proxy_ips[$i], $separator) === false)
					{
						continue;
					}

					// Convert the REMOTE_ADDR IP address to binary, if needed
					if (! isset($ip, $sprintf))
					{
						if ($separator === ':')
						{
							// Make sure we're have the "full" IPv6 format
							$ip = explode(':', str_replace('::', str_repeat(':', 9 - substr_count($this->ipAddress, ':')), $this->ipAddress
									)
							);

							for ($j = 0; $j < 8; $j ++)
							{
								$ip[$j] = intval($ip[$j], 16);
							}

							$sprintf = '%016b%016b%016b%016b%016b%016b%016b%016b';
						}
						else
						{
							$ip      = explode('.', $this->ipAddress);
							$sprintf = '%08b%08b%08b%08b';
						}

						$ip = vsprintf($sprintf, $ip);
					}

					// Split the netmask length off the network address
					sscanf($proxy_ips[$i], '%[^/]/%d', $netaddr, $masklen);

					// Again, an IPv6 address is most likely in a compressed form
					if ($separator === ':')
					{
						$netaddr = explode(':', str_replace('::', str_repeat(':', 9 - substr_count($netaddr, ':')), $netaddr));
						for ($i = 0; $i < 8; $i ++)
						{
							$netaddr[$i] = intval($netaddr[$i], 16);
						}
					}
					else
					{
						$netaddr = explode('.', $netaddr);
					}

					// Convert to binary and finally compare
					if (strncmp($ip, vsprintf($sprintf, $netaddr), $masklen) === 0)
					{
						$this->ipAddress = $spoof;
						break;
					}
				}
			}
		}

		if (! $this->isValidIP($this->ipAddress))
		{
			return $this->ipAddress = '0.0.0.0';
		}

		return empty($this->ipAddress) ? '' : $this->ipAddress;
	}

	//--------------------------------------------------------------------

	/**
	 * Validate an IP address
	 *
	 * @param string $ip    IP Address
	 * @param string $which IP protocol: 'ipv4' or 'ipv6'
	 *
	 * @return boolean
	 */
	public function isValidIP(string $ip = null, string $which = null): bool
	{
		switch (strtolower($which))
		{
			case 'ipv4':
				$which = FILTER_FLAG_IPV4;
				break;
			case 'ipv6':
				$which = FILTER_FLAG_IPV6;
				break;
			default:
				$which = null;
				break;
		}

		return (bool) filter_var($ip, FILTER_VALIDATE_IP, $which);
	}

	//--------------------------------------------------------------------

	/**
	 * Get the request method.
	 *
	 * @param boolean $upper Whether to return in upper or lower case.
	 *
	 * @return string
	 */
	public function getMethod($upper = false): string
	{
		return ($upper) ? strtoupper($this->method) : strtolower($this->method);
	}

	//--------------------------------------------------------------------

	/**
	 * Sets the request method. Used when spoofing the request.
	 *
	 * @param string $method
	 *
	 * @return Request
	 */
	public function setMethod(string $method)
	{
		$this->method = $method;

		return $this;
	}

	//--------------------------------------------------------------------

	/**
	 * Fetch an item from the $_SERVER array.
	 *
	 * @param integer|null $index  Index for item to be fetched from $_SERVER
	 * @param integer|null $filter A filter name to be applied
	 * @param null         $flags
	 *
	 * @return mixed
	 */
	public function getServer($index = null, $filter = null, $flags = null)
	{
		return $this->fetchGlobal('server', $index, $filter, $flags);
	}

	//--------------------------------------------------------------------

	/**
	 * Fetch an item from the $_ENV array.
	 *
	 * @param null $index  Index for item to be fetched from $_ENV
	 * @param null $filter A filter name to be applied
	 * @param null $flags
	 *
	 * @return mixed
	 */
	public function getEnv($index = null, $filter = null, $flags = null)
	{
		return $this->fetchGlobal('env', $index, $filter, $flags);
	}

	//--------------------------------------------------------------------

	/**
	 * Allows manually setting the value of PHP global, like $_GET, $_POST, etc.
	 *
	 * @param string $method
	 * @param $value
	 *
	 * @return $this
	 */
	public function setGlobal(string $method, $value)
	{
		$this->globals[$method] = $value;

		return $this;
	}

	//--------------------------------------------------------------------

	/**
	 * Fetches one or more items from a global, like cookies, get, post, etc.
	 * Can optionally filter the input when you retrieve it by passing in
	 * a filter.
	 *
	 * If $type is an array, it must conform to the input allowed by the
	 * filter_input_array method.
	 *
	 * http://php.net/manual/en/filter.filters.sanitize.php
	 *
	 * @param integer      $method Input filter constant
	 * @param string|array $index
	 * @param integer      $filter Filter constant
	 * @param null         $flags
	 *
	 * @return mixed
	 */
	public function fetchGlobal($method, $index = null, $filter = null, $flags = null)
	{
		$method = strtolower($method);

		if (! isset($this->globals[$method]))
		{
			$this->populateGlobals($method);
		}

		// Null filters cause null values to return.
		if (is_null($filter))
		{
			$filter = FILTER_DEFAULT;
		}

		// Return all values when $index is null
		if (is_null($index))
		{
			$values = [];
			foreach ($this->globals[$method] as $key => $value)
			{
				$values[$key] = is_array($value)
					? $this->fetchGlobal($method, $key, $filter, $flags)
					: filter_var($value, $filter, $flags);
			}

			return $values;
		}

		// allow fetching multiple keys at once
		if (is_array($index))
		{
			$output = [];

			foreach ($index as $key)
			{
				$output[$key] = $this->fetchGlobal($method, $key, $filter, $flags);
			}

			return $output;
		}

		// Does the index contain array notation?
		if (($count = preg_match_all('/(?:^[^\[]+)|\[[^]]*\]/', $index, $matches)) > 1)
		{
			$value = $this->globals[$method];
			for ($i = 0; $i < $count; $i++)
			{
				$key = trim($matches[0][$i], '[]');

				if ($key === '') // Empty notation will return the value as array
				{
					break;
				}

				if (isset($value[$key]))
				{
					$value = $value[$key];
				}
				else
				{
					return null;
				}
			}
		}

		if (empty($value))
		{
			$value = $this->globals[$method][$index] ?? null;
		}

		// Cannot filter these types of data automatically...
		if (is_array($value) || is_object($value) || is_null($value))
		{
			return $value;
		}

		return filter_var($value, $filter, $flags);
	}

	//--------------------------------------------------------------------

	/**
	 * Saves a copy of the current state of one of several PHP globals
	 * so we can retrieve them later.
	 *
	 * @param string $method
	 */
	protected function populateGlobals(string $method)
	{
		if (! isset($this->globals[$method]))
		{
			$this->globals[$method] = [];
		}

		// Don't populate ENV as it might contain
		// sensitive data that we don't want to get logged.
		switch($method)
		{
			case 'get':
				$this->globals['get'] = $_GET;
				break;
			case 'post':
				$this->globals['post'] = $_POST;
				break;
			case 'request':
				$this->globals['request'] = $_REQUEST;
				break;
			case 'cookie':
				$this->globals['cookie'] = $_COOKIE;
				break;
			case 'server':
				$this->globals['server'] = $_SERVER;
				break;
		}
	}

	//--------------------------------------------------------------------

	/**
	 * Fetch an item from the $_REQUEST object. This is the simplest way
	 * to grab data from the request object and can be used in lieu of the
	 * other get* methods in most cases.
	 *
	 * @param null $index
	 * @param null $filter
	 * @param null $flags
	 *
	 * @return mixed
	 */
	public function getVar($index = null, $filter = null, $flags = null)
	{
		return $this->fetchGlobal('request', $index, $filter, $flags);
	}

	//--------------------------------------------------------------------

	/**
	 * A convenience method that grabs the raw input stream and decodes
	 * the JSON into an array.
	 *
	 * If $assoc == true, then all objects in the response will be converted
	 * to associative arrays.
	 *
	 * @param boolean $assoc   Whether to return objects as associative arrays
	 * @param integer $depth   How many levels deep to decode
	 * @param integer $options Bitmask of options
	 *
	 * @see http://php.net/manual/en/function.json-decode.php
	 *
	 * @return mixed
	 */
	public function getJSON(bool $assoc = false, int $depth = 512, int $options = 0)
	{
		return json_decode($this->body, $assoc, $depth, $options);
	}

	//--------------------------------------------------------------------

	/**
	 * A convenience method that grabs the raw input stream(send method in PUT, PATCH, DELETE) and decodes
	 * the String into an array.
	 *
	 * @return mixed
	 */
	public function getRawInput()
	{
		parse_str($this->body, $output);

		return $output;
	}

	//--------------------------------------------------------------------

	/**
	 * Fetch an item from GET data.
	 *
	 * @param null $index  Index for item to fetch from $_GET.
	 * @param null $filter A filter name to apply.
	 * @param null $flags
	 *
	 * @return mixed
	 */
	public function getGet($index = null, $filter = null, $flags = null)
	{
		return $this->fetchGlobal('get', $index, $filter, $flags);
	}

	//--------------------------------------------------------------------

	/**
	 * Fetch an item from POST.
	 *
	 * @param null $index  Index for item to fetch from $_POST.
	 * @param null $filter A filter name to apply
	 * @param null $flags
	 *
	 * @return mixed
	 */
	public function getPost($index = null, $filter = null, $flags = null)
	{
		return $this->fetchGlobal('post', $index, $filter, $flags);
	}

	//--------------------------------------------------------------------

	/**
	 * Fetch an item from FILES.
	 *
	 * @param null $index  Index for item to fetch from $_FILES.
	 *
	 * @return array
	 */
	public function getFiles($index = null)
	{
		if ( is_null($index) )
		{
			$output = [];
			foreach ($_FILES as $key => $value) {
				$output[] = reArrayFilesPost($_FILES[$key]);
			}
			return $output;
		}

		return reArrayFilesPost($_FILES[$index]);
	}

	//--------------------------------------------------------------------

	/**
	 * Fetch an item from POST data with fallback to GET.
	 *
	 * @param null $index  Index for item to fetch from $_POST or $_GET
	 * @param null $filter A filter name to apply
	 * @param null $flags
	 *
	 * @return mixed
	 */
	public function getPostGet($index = null, $filter = null, $flags = null)
	{
		// Use $_POST directly here, since filter_has_var only
		// checks the initial POST data, not anything that might
		// have been added since.
		return isset($_POST[$index]) ? $this->getPost($index, $filter, $flags) : $this->getGet($index, $filter, $flags);
	}

	//--------------------------------------------------------------------

	/**
	 * Fetch an item from GET data with fallback to POST.
	 *
	 * @param null $index  Index for item to be fetched from $_GET or $_POST
	 * @param null $filter A filter name to apply
	 * @param null $flags
	 *
	 * @return mixed
	 */
	public function getGetPost($index = null, $filter = null, $flags = null)
	{
		// Use $_GET directly here, since filter_has_var only
		// checks the initial GET data, not anything that might
		// have been added since.
		return isset($_GET[$index]) ? $this->getGet($index, $filter, $flags) : $this->getPost($index, $filter, $flags);
	}

	//--------------------------------------------------------------------

	/**
	 * Fetch an item from the COOKIE array.
	 *
	 * @param null $index  Index for item to be fetched from $_COOKIE
	 * @param null $filter A filter name to be applied
	 * @param null $flags
	 *
	 * @return mixed
	 */
	public function getCookie($index = null, $filter = null, $flags = null)
	{
		return $this->fetchGlobal('cookie', $index, $filter, $flags);
	}

	//--------------------------------------------------------------------

	/**
	 * Test to see if a request contains the HTTP_X_REQUESTED_WITH header.
	 *
	 * @return boolean
	 */
	public function isAJAX(): bool
	{
		return ( ! empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
				strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');
	}

	//--------------------------------------------------------------------

	/**
	 * Determines if this request was made from the command line (CLI).
	 *
	 * @return boolean
	 */
	public function isCLI(): bool
	{
		return is_cli();
	}
	
	//--------------------------------------------------------------------

	/**
	 * Attempts to detect if the current connection is secure through
	 * a few different methods.
	 *
	 * @return boolean
	 */
	public function isSecure(): bool
	{
		if (! empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off')
		{
			return true;
		}
		elseif (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
		{
			return true;
		}
		elseif (! empty($_SERVER['HTTP_FRONT_END_HTTPS']) && strtolower($_SERVER['HTTP_FRONT_END_HTTPS']) !== 'off')
		{
			return true;
		}

		return false;
	}
}

if (! function_exists('is_cli'))
{
	/**
	 * Is CLI?
	 *
	 * Test to see if a request was made from the command line.
	 *
	 * @return boolean
	 */
	function is_cli()
	{
		return (PHP_SAPI === 'cli' || defined('STDIN'));
	}
}

if (! function_exists('stringify_attributes'))
{
	/**
	 * Stringify attributes for use in HTML tags.
	 *
	 * Helper function used to convert a string, array, or object
	 * of attributes to a string.
	 *
	 * @param mixed   $attributes string, array, object
	 * @param boolean $js
	 *
	 * @return string
	 */
	function stringify_attributes($attributes, $js = false): string
	{
		$atts = '';

		if (empty($attributes))
		{
			return $atts;
		}

		if (is_string($attributes))
		{
			return ' ' . $attributes;
		}

		$attributes = (array) $attributes;

		foreach ($attributes as $key => $val)
		{
			$atts .= ($js) ? $key . '=' . esc($val, 'js') . ',' : ' ' . $key . '="' . esc($val, 'attr') . '"';
		}

		return rtrim($atts, ',');
	}
}

if (! function_exists('reArrayFilesPost'))
{
	/**
	 * 
	 * Reorganize $_FILES array to a more logical one.
	 * http://php.net/manual/en/features.file-upload.multiple.php
	 *
	 * $_FILES Array
	 * (
	 *     [name] => Array
	 *         (
	 *             [0] => foo.txt
	 *             [1] => bar.txt
	 *         )
	 *     [type] => Array
	 *         (
	 *             [0] => text/plain
	 *             [1] => text/plain
	 *         )
	 *     [tmp_name] => Array
	 *         (
	 *             [0] => /tmp/phpYzdqkD
	 *             [1] => /tmp/phpeEwEWG
	 *         )
	 *     [error] => Array
	 *         (
	 *             [0] => 0
	 *             [1] => 0
	 *         )
	 *     [size] => Array
	 *         (
	 *             [0] => 123
	 *             [1] => 456
	 *         )
	 * )
	 * 
	 * Array
	 * (
	 *     [0] => Array
	 *         (
	 *             [name] => foo.txt
	 *             [type] => text/plain
	 *             [tmp_name] => /tmp/phpYzdqkD
	 *             [error] => 0
	 *             [size] => 123
	 *         )
	 *     [1] => Array
	 *         (
	 *             [name] => bar.txt
	 *             [type] => text/plain
	 *             [tmp_name] => /tmp/phpeEwEWG
	 *             [error] => 0
	 *             [size] => 456
	 *         )
	 * )
	 * 
	 * @param array   $_FILES post
	 *
	 * @return array
	 */
	function reArrayFilesPost(array &$file_post) {
		$file_ary = array();
		$file_count = count($file_post['name']);
		$file_keys = array_keys($file_post);

		for ($i=0; $i<$file_count; $i++) {
			foreach ($file_keys as $key) {
				$file_ary[$i][$key] = $file_post[$key][$i];
			}
		}

		return $file_ary;
	}
}