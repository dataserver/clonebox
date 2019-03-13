<?php
/**
 * Explode any single-dimensional array into a full blown tree structure,
 * based on the delimiters found in it's keys.
 *
 * The following code block can be utilized by PEAR's Testing_DocTest
 * <code>
 * // Input //
 * $key_files = array(
 *	 "/etc/php5" => "/etc/php5",
 *	 "/etc/php5/cli" => "/etc/php5/cli",
 *	 "/etc/php5/cli/conf.d" => "/etc/php5/cli/conf.d",
 *	 "/etc/php5/cli/php.ini" => "/etc/php5/cli/php.ini",
 *	 "/etc/php5/conf.d" => "/etc/php5/conf.d",
 *	 "/etc/php5/conf.d/mysqli.ini" => "/etc/php5/conf.d/mysqli.ini",
 *	 "/etc/php5/conf.d/curl.ini" => "/etc/php5/conf.d/curl.ini",
 *	 "/etc/php5/conf.d/snmp.ini" => "/etc/php5/conf.d/snmp.ini",
 *	 "/etc/php5/conf.d/gd.ini" => "/etc/php5/conf.d/gd.ini",
 *	 "/etc/php5/apache2" => "/etc/php5/apache2",
 *	 "/etc/php5/apache2/conf.d" => "/etc/php5/apache2/conf.d",
 *	 "/etc/php5/apache2/php.ini" => "/etc/php5/apache2/php.ini"
 * );
 *
 * // Execute //
 * $tree = explodeTree($key_files, "/", true);
 *
 * // Show //
 * print_r($tree);
 *
 * // expects:
 * // Array
 * // (
 * //	 [etc] => Array
 * //		 (
 * //			 [php5] => Array
 * //				 (
 * //					 [__base_val] => /etc/php5
 * //					 [cli] => Array
 * //						 (
 * //							 [__base_val] => /etc/php5/cli
 * //							 [conf.d] => /etc/php5/cli/conf.d
 * //							 [php.ini] => /etc/php5/cli/php.ini
 * //						 )
 * //
 * //					 [conf.d] => Array
 * //						 (
 * //							 [__base_val] => /etc/php5/conf.d
 * //							 [mysqli.ini] => /etc/php5/conf.d/mysqli.ini
 * //							 [curl.ini] => /etc/php5/conf.d/curl.ini
 * //							 [snmp.ini] => /etc/php5/conf.d/snmp.ini
 * //							 [gd.ini] => /etc/php5/conf.d/gd.ini
 * //						 )
 * //
 * //					 [apache2] => Array
 * //						 (
 * //							 [__base_val] => /etc/php5/apache2
 * //							 [conf.d] => /etc/php5/apache2/conf.d
 * //							 [php.ini] => /etc/php5/apache2/php.ini
 * //						 )
 * //
 * //				 )
 * //
 * //		 )
 * //
 * // )
 * </code>
 * @author  Kevin van Zonneveld &lt;kevin@vanzonneveld.net>
 * @author  Lachlan Donald
 * @author  Takkie
 * @copyright 2008 Kevin van Zonneveld (http://kevin.vanzonneveld.net)
 * @license   http://www.opensource.org/licenses/bsd-license.php New BSD Licence
 * @version   SVN: Release: $Id: explodeTree.inc.php 89 2008-09-05 20:52:48Z kevin $
 * @link      http://kevin.vanzonneveld.net/
 *
 * @param array   $array
 * @param string  $delimiter
 * @param boolean $baseval
 *
 * @return array
 */
function explodeTree(array $array, string $delimiter = '_', bool $baseval = false)
{
	if(!is_array($array)) return false;
	$splitRE   = '/' . preg_quote($delimiter, '/') . '/';
	$returnArr = array();
	foreach ($array as $key => $val) {
		// Get parent parts and the current leaf
		$parts  = preg_split($splitRE, $key, -1, PREG_SPLIT_NO_EMPTY);
		$leafPart = array_pop($parts);

		// Build parent structure
		// Might be slow for really deep and large structures
		$parentArr = &$returnArr;
		foreach ($parts as $part) {
			if (!isset($parentArr[$part])) {
				$parentArr[$part] = array();
			} elseif (!is_array($parentArr[$part])) {
				if ($baseval) {
					$parentArr[$part] = array('__base_val' => $parentArr[$part]);
				} else {
					$parentArr[$part] = array();
				}
			}
			$parentArr = &$parentArr[$part];
		}

		// Add the final part to the structure
		if (empty($parentArr[$leafPart])) {
			$parentArr[$leafPart] = $val;
		} elseif ($baseval && is_array($parentArr[$leafPart])) {
			$parentArr[$leafPart]['__base_val'] = $val;
		}
	}
	return $returnArr;
}

function getFileExt(string $filename) {
	return substr( strrchr( $filename, '.'), 1);
}
if (! function_exists('reArrayFiles'))
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
	function reArrayFiles(array &$file_post) {
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

/**
 * 
 * Convert bytes to 'humna' readable format (Kbytes/Megabytes/Giga/Tera)
 *
 * @param string  $file file pointer path to a file
 *
 * @return array mixed bytes and 'human' format
 */
function get_file_size(string $file) {
	$bytes = @filesize($file);

	if ($bytes < 1024)
		$human = $bytes.'b';
	elseif ($bytes < 1048576)
		$human = round($bytes / 1024, 2).'kb';
	elseif ($bytes < 1073741824)
		$human = round($bytes / 1048576, 2).'mb';
	elseif ($bytes < 1099511627776)
		$human = round($bytes / 1073741824, 2).'gb';
	else
		$human = round($bytes / 1099511627776, 2).'tb';
	return [
		'bytes' => $bytes,
		'human' => $human,
	];
}

/**
 * 
 * Output a quick JSON error response in Google JSON Style
 * https://google.github.io/styleguide/jsoncstyleguide.xml
 * 
 * @param int     $code HTTP code
 * @param string  $message Message displayed
 * @param array   $errors array of errors.
 *
 * @return array mixed bytes and 'human' format
 */
function oops(int $code = 400, string $message, array $errors = []) {

	$json = [
		'method' => APP_METHOD,
		'code' => $code,
		'error' => [
			'message' => $message,
		]
	];
	if (count($errors) > 0) {
		$json['error']['errors'] = $errors;
	}
	if ( CURR_FOLDER_ID != '' ) {
		$json['params']['folder'] = CURR_FOLDER_ID;
	}
	if ( CURR_FILE_ID != '' ) {
		$json['params']['file'] = CURR_FILE_ID;	}

	http_response_code($code);
	header('Content-type: application/json');
	echo json_encode($json);
	exit;
}

function filter_ids_array(array $array = []) {
	$array = filter_var($array, FILTER_VALIDATE_INT, [
												  'flags'   => FILTER_REQUIRE_ARRAY,
												  'options' => ['min_range' => 1]
												]
					);
	$filtered = array_filter($array, 'is_int');
	return $filtered;
}

function filter_ids_array2(array $array = []) {
	$array = array_filter( $array, 'strlen' );
	$array = array_map(function($value) {
		return intval($value);
	}, $array);

	return $array;
}

/**
 * 
 * Output a random alpanumeric lowercase string of $length
 * 
 * @param int     $length lenght of random string, default is 10.
 *
 * @return string a random string of $length
 */
function generateRandomString(int $length = 10) {
	$characters = '0123456789abcdefghijklmnopqrstuvwxyz';
	// https://en.wikipedia.org/wiki/Base64
	// $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ' base 36;
	// $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/';  // base 64 for RFC 4648 - 'The Base16, Base32, and Base64 Data Encodings'
	// $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-_';  // base 64 base64url URL- and filename-safe (RFC 4648 §5)
	// $characters = ' !"#$%&\'()*+,-./0123456789:;<=>?@ABCDEFGHIJKLMNOPQRSTUVWXYZ[\]^_'; // Uuencoding
	$charactersLength = strlen($characters);
	$randomString = '';

	for ( $i = 0; $i < $length; $i++ ) {
		$randomString .= $characters[ rand(0, $charactersLength - 1) ];
	}
	return $randomString;
}
