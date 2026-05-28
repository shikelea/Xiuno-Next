<?php

if (!class_exists('XiunoHttp', false)) {
	class XiunoHttp {
		public static function get($url, $options = array()) {
			$options['method'] = 'GET';
			return self::request($url, $options);
		}

		public static function post($url, $data = array(), $options = array()) {
			$options['method'] = 'POST';
			$options['body'] = is_array($data) ? http_build_query($data) : $data;
			$headers = isset($options['headers']) && is_array($options['headers']) ? $options['headers'] : array();
			$headers['Content-Type'] = 'application/x-www-form-urlencoded';
			$options['headers'] = $headers;
			return self::request($url, $options);
		}

		public static function json($url, $data = array(), $options = array()) {
			$options['method'] = isset($options['method']) ? $options['method'] : 'POST';
			$options['body'] = is_string($data) ? $data : json_encode($data);
			$headers = isset($options['headers']) && is_array($options['headers']) ? $options['headers'] : array();
			$headers['Content-Type'] = 'application/json';
			$headers['Accept'] = 'application/json';
			$options['headers'] = $headers;
			return self::request($url, $options);
		}

		public static function request($url, $options = array()) {
			$method = strtoupper(isset($options['method']) ? $options['method'] : 'GET');
			$query = isset($options['query']) ? $options['query'] : array();
			if(!empty($query)) {
				$url .= (strpos($url, '?') === FALSE ? '?' : '&').(is_array($query) ? http_build_query($query) : $query);
			}

			if(function_exists('curl_init')) {
				return self::requestCurl($method, $url, $options);
			}
			return self::requestStream($method, $url, $options);
		}

		private static function requestCurl($method, $url, $options) {
			$timeout = isset($options['timeout']) ? intval($options['timeout']) : 30;
			$connectTimeout = isset($options['connect_timeout']) ? intval($options['connect_timeout']) : 10;
			$verify = array_key_exists('verify_tls', $options) ? (bool)$options['verify_tls'] : TRUE;
			$headers = self::headers(isset($options['headers']) ? $options['headers'] : array());
			$body = isset($options['body']) ? $options['body'] : NULL;

			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
			curl_setopt($ch, CURLOPT_HEADER, TRUE);
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
			curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
			curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $connectTimeout);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $verify);
			curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $verify ? 2 : 0);
			curl_setopt($ch, CURLOPT_USERAGENT, isset($options['user_agent']) ? $options['user_agent'] : 'Xiuno-Next');
			empty(ini_get('open_basedir')) AND curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
			curl_setopt($ch, CURLOPT_MAXREDIRS, isset($options['max_redirects']) ? intval($options['max_redirects']) : 5);

			if(!empty($headers)) curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
			if($body !== NULL) curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
			if(!empty($options['proxy'])) curl_setopt($ch, CURLOPT_PROXY, $options['proxy']);
			if(!empty($options['proxy_auth'])) curl_setopt($ch, CURLOPT_PROXYUSERPWD, $options['proxy_auth']);

			$raw = curl_exec($ch);
			$errno = curl_errno($ch);
			$errstr = curl_error($ch);
			$code = intval(curl_getinfo($ch, CURLINFO_HTTP_CODE));
			$headerSize = intval(curl_getinfo($ch, CURLINFO_HEADER_SIZE));
			curl_close($ch);

			$rawHeaders = $raw !== FALSE ? substr($raw, 0, $headerSize) : '';
			$responseBody = $raw !== FALSE ? substr($raw, $headerSize) : '';
			return self::response($code, $rawHeaders, $responseBody, $errno, $errstr);
		}

		private static function requestStream($method, $url, $options) {
			$timeout = isset($options['timeout']) ? intval($options['timeout']) : 30;
			$verify = array_key_exists('verify_tls', $options) ? (bool)$options['verify_tls'] : TRUE;
			$headers = self::headers(isset($options['headers']) ? $options['headers'] : array());
			$body = isset($options['body']) ? $options['body'] : NULL;

			$context = stream_context_create(array(
				'http' => array(
					'method' => $method,
					'header' => implode("\r\n", $headers),
					'content' => $body === NULL ? '' : $body,
					'timeout' => $timeout,
					'ignore_errors' => TRUE,
				),
				'ssl' => array(
					'verify_peer' => $verify,
					'verify_peer_name' => $verify,
				),
			));
			$body = @file_get_contents($url, FALSE, $context);
			if(function_exists('http_get_last_response_headers')) {
				$responseHeaders = http_get_last_response_headers();
			} else {
				$headerVar = 'http_response_header';
				$responseHeaders = isset($$headerVar) ? $$headerVar : array();
			}
			$rawHeaders = $responseHeaders ? implode("\r\n", $responseHeaders) : '';
			$code = self::statusCode($rawHeaders);
			return self::response($code, $rawHeaders, $body === FALSE ? '' : $body, $body === FALSE ? 1 : 0, $body === FALSE ? 'HTTP request failed' : '');
		}

		private static function response($code, $rawHeaders, $body, $errno, $errstr) {
			$json = NULL;
			if($body !== '') {
				$jsonBody = trim($body, "\xEF\xBB\xBF");
				$jsonBody = trim($jsonBody, "\xFE\xFF");
				$decoded = json_decode($jsonBody, TRUE);
				if(json_last_error() === JSON_ERROR_NONE) $json = $decoded;
			}
			return array(
				'ok' => $errno == 0 && $code >= 200 && $code < 300,
				'code' => $code,
				'headers' => self::parseHeaders($rawHeaders),
				'body' => $body,
				'json' => $json,
				'errno' => $errno,
				'errstr' => $errstr,
			);
		}

		private static function headers($headers) {
			$list = array();
			foreach((array)$headers as $key => $value) {
				$list[] = is_int($key) ? $value : $key.': '.$value;
			}
			return $list;
		}

		private static function parseHeaders($rawHeaders) {
			$headers = array();
			foreach(preg_split('/\r\n|\r|\n/', trim($rawHeaders)) as $line) {
				if(strpos($line, ':') === FALSE) continue;
				list($key, $value) = explode(':', $line, 2);
				$headers[strtolower(trim($key))] = trim($value);
			}
			return $headers;
		}

		private static function statusCode($rawHeaders) {
			if(preg_match_all('#^HTTP/\S+\s+(\d{3})#m', $rawHeaders, $m)) {
				return intval(end($m[1]));
			}
			return 0;
		}
	}
}

?>
