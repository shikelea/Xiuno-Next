<?php

if (!class_exists('XiunoLogger', false)) {
	class XiunoLogger {
		public static function emergency($message, $context = array(), $channel = 'error') {
			return self::log('emergency', $message, $context, $channel);
		}

		public static function alert($message, $context = array(), $channel = 'error') {
			return self::log('alert', $message, $context, $channel);
		}

		public static function critical($message, $context = array(), $channel = 'error') {
			return self::log('critical', $message, $context, $channel);
		}

		public static function error($message, $context = array(), $channel = 'error') {
			return self::log('error', $message, $context, $channel);
		}

		public static function warning($message, $context = array(), $channel = 'error') {
			return self::log('warning', $message, $context, $channel);
		}

		public static function notice($message, $context = array(), $channel = 'error') {
			return self::log('notice', $message, $context, $channel);
		}

		public static function info($message, $context = array(), $channel = 'info') {
			return self::log('info', $message, $context, $channel);
		}

		public static function debug($message, $context = array(), $channel = 'debug') {
			return self::log('debug', $message, $context, $channel);
		}

		public static function log($level, $message, $context = array(), $channel = 'error') {
			if(defined('DEBUG') && DEBUG == 0 && !in_array($level, array('emergency', 'alert', 'critical', 'error'))) return TRUE;
			$message = '['.$level.'] '.self::interpolate($message, $context);
			return self::write($channel, $message);
		}

		public static function write($channel, $message) {
			$time = isset($_SERVER['time']) ? $_SERVER['time'] : time();
			$ip = isset($_SERVER['ip']) ? $_SERVER['ip'] : (isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '127.0.0.1');
			$conf = _SERVER('conf', array());
			$uid = intval(G('uid'));
			$day = date('Ym', $time);
			$mtime = date('Y-m-d H:i:s', $time);
			$url = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
			$logpath = (isset($conf['log_path']) ? $conf['log_path'] : './log/').$day;
			$channel = self::channel($channel);

			if(!is_dir($logpath) && !mkdir($logpath, 0777, true) && !is_dir($logpath)) {
				return FALSE;
			}

			$message = str_replace(array("\r\n", "\n", "\t"), ' ', (string)$message);
			$line = "<?php exit;?>\t$mtime\t$ip\t$url\t$uid\t$message\r\n";
			return @error_log($line, 3, $logpath.'/'.$channel.'.php');
		}

		private static function interpolate($message, $context) {
			if(empty($context) || !is_array($context)) return (string)$message;
			$replace = array();
			foreach($context as $key => $value) {
				if(is_scalar($value) || (is_object($value) && method_exists($value, '__toString'))) {
					$replace['{'.$key.'}'] = (string)$value;
				}
			}
			return strtr((string)$message, $replace);
		}

		private static function channel($channel) {
			$channel = preg_replace('#[^\w\-.]+#', '_', (string)$channel);
			$channel = trim($channel, '._-');
			return $channel === '' ? 'error' : $channel;
		}
	}
}

?>
