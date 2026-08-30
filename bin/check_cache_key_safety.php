<?php

$root = dirname(__DIR__);

function fail($message) {
	fwrite(STDERR, "FAIL: $message\n");
	exit(1);
}

class cache_key_probe {
	public $cachepre = 'bbs_';
	public $max_key_length = 32;
	public $values = array();
	public $calls = array();

	public function get($key) {
		$this->calls[] = array('get', $key);
		return array_key_exists($key, $this->values) ? $this->values[$key] : NULL;
	}

	public function get_master($key) {
		$this->calls[] = array('get_master', $key);
		return array_key_exists($key, $this->values) ? $this->values[$key] : NULL;
	}

	public function set($key, $value, $life = 0) {
		$this->calls[] = array('set', $key, $life);
		$this->values[$key] = $value;
		return TRUE;
	}

	public function delete($key) {
		$this->calls[] = array('delete', $key);
		unset($this->values[$key]);
		return TRUE;
	}
}

require $root.'/xiunophp/cache.func.php';

$probe = new cache_key_probe();
$_SERVER['cache'] = $probe;
$raw = 'user_login_rate_'.str_repeat('a', 16);
strlen($raw) === 32 || fail('Fixture must exercise a raw 32-byte cache key.');

cache_set($raw, array('count'=>1), 900) || fail('Prefixed cache key fixture could not be stored.');
$stored_keys = array_keys($probe->values);
count($stored_keys) === 1 || fail('Fixture must publish exactly one normalized cache key.');
$stored = $stored_keys[0];
strlen($stored) <= 32 || fail('Driver-limited final cache key exceeded CHAR(32).');
strpos($stored, 'bbs_') === 0 || fail('A normal four-byte cache namespace should remain visible.');
cache_get($raw) === array('count'=>1) || fail('Replica read did not use the same normalized key as write.');
cache_get_primary($raw) === array('count'=>1) || fail('Primary read did not use the same normalized key as write.');
cache_delete($raw) || fail('Delete did not use the same normalized key as write.');
empty($probe->values) || fail('Delete left the normalized cache entry behind.');

$called_keys = array();
foreach($probe->calls as $call) $called_keys[] = $call[1];
count(array_unique($called_keys)) === 1 || fail('Cache operations disagreed on final key normalization.');

$unlimited = new cache_key_probe();
$unlimited->max_key_length = 0;
$unlimited_key = cache_key_normalize($raw, $unlimited);
$unlimited_key === 'bbs_'.$raw || fail('Unlimited drivers must preserve the historical short raw key.');

$oversized_prefix = new cache_key_probe();
$oversized_prefix->cachepre = str_repeat('p', 40);
$oversized_key = cache_key_normalize('same-key', $oversized_prefix);
strlen($oversized_key) === 32 || fail('Oversized namespaces must still produce a bounded key.');
$oversized_prefix->cachepre = str_repeat('q', 40);
cache_key_normalize('same-key', $oversized_prefix) !== $oversized_key
	|| fail('Oversized namespaces must remain bound into the normalized digest.');

$mysql_source = file_get_contents($root.'/xiunophp/cache_mysql.class.php');
strpos($mysql_source, 'public $max_key_length = 32;') !== FALSE
	|| fail('MySQL cache driver must declare its storage key limit.');

echo "OK: cache key safety checks passed\n";
