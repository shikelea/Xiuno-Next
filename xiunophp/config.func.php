<?php

if (!class_exists('XiunoConfig', false)) {
	class XiunoConfig {
		public static function get($key = null, $default = null, $source = null) {
			$conf = self::source($source);
			if ($key === null || $key === '') return $conf;

			$keys = is_array($key) ? $key : explode('.', (string)$key);
			$value = $conf;
			foreach ($keys as $segment) {
				if (!is_array($value) || !array_key_exists($segment, $value)) {
					return $default;
				}
				$value = $value[$segment];
			}
			return $value;
		}

		public static function has($key, $source = null) {
			$marker = new stdClass();
			return self::get($key, $marker, $source) !== $marker;
		}

		private static function source($source = null) {
			if (is_array($source)) return $source;
			if (isset($GLOBALS['conf']) && is_array($GLOBALS['conf'])) return $GLOBALS['conf'];
			if (isset($_SERVER['conf']) && is_array($_SERVER['conf'])) return $_SERVER['conf'];
			return array();
		}
	}
}

if (!class_exists('Config', false)) {
	class_alias('XiunoConfig', 'Config');
}

if (!function_exists('conf_get')) {
	function conf_get($key = null, $default = null, $source = null) {
		return XiunoConfig::get($key, $default, $source);
	}
}

if (!function_exists('conf_has')) {
	function conf_has($key, $source = null) {
		return XiunoConfig::has($key, $source);
	}
}

?>
