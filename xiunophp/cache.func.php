<?php

function cache_new($cacheconf) {
	// 缓存初始化，这里并不会产生连接！在真正使用的时候才连接。
	// 这里采用最笨拙的方式而不采用 new $classname 的方式，有利于 opcode 缓存。
	if($cacheconf && !empty($cacheconf['enable'])) {
		switch ($cacheconf['type']) {
			case 'redis': 	  $cache = new cache_redis($cacheconf['redis']); 	     break;
			case 'memcached': $cache = new cache_memcached($cacheconf['memcached']); break;
			case 'pdo_mysql': 	  
			case 'mysql': 	  
					$cache = new cache_mysql($cacheconf['mysql']); break;
			case 'xcache': 	  $cache = new cache_xcache($cacheconf['xcache']); 	break;
			case 'apc': 	  $cache = new cache_apc($cacheconf['apc']); 	break;
			case 'yac': 	  $cache = new cache_yac($cacheconf['yac']); 	break;
			default: return xn_error(-1, '不支持的 cache type:'.$cacheconf['type']);
		}
		return $cache;
	}
	return NULL;
}

// Keep the historical raw-key hashing rule, then apply a driver-specific final-key limit after
// the configured namespace prefix is known. MySQL stores cache keys in CHAR(32); checking the raw
// key before adding cachepre allowed a 32-byte key plus "bbs_" to be truncated by the database.
function cache_key_normalize($k, $c) {
	$k = (string)$k;
	strlen($k) > 32 AND $k = md5($k);

	$prefix = isset($c->cachepre) ? (string)$c->cachepre : '';
	$limit = isset($c->max_key_length) ? intval($c->max_key_length) : 0;
	if($limit > 0 && strlen($prefix.$k) > $limit) {
		$available = $limit - strlen($prefix);
		if($available >= 16) {
			$k = substr(hash('sha256', $k), 0, $available);
		} else {
			// An oversized namespace cannot be preserved literally. Bind it into the digest so two
			// configured namespaces still do not alias even though the stored key has no prefix.
			return substr(hash('sha256', $prefix."\0".$k), 0, $limit);
		}
	}
	return $prefix.$k;
}

function cache_get($k, $c = NULL) {
	$cache = $_SERVER['cache'];
	$c = $c ? $c : $cache;
	if(!$c) return FALSE;

	$k = cache_key_normalize($k, $c);
	$r = $c->get($k);
	return $r;
}

// Authentication counters and other read-modify-write state need a primary read when the MySQL
// cache reuses a replicated database connection. Single-endpoint cache drivers use their normal get.
function cache_get_primary($k, $c = NULL) {
	$cache = $_SERVER['cache'];
	$c = $c ? $c : $cache;
	if(!$c) return FALSE;

	$k = cache_key_normalize($k, $c);
	return method_exists($c, 'get_master') ? $c->get_master($k) : $c->get($k);
}

function cache_set($k, $v, $life = 0, $c = NULL) {
	$cache = $_SERVER['cache'];
	$c = $c ? $c : $cache;
	if(!$c) return FALSE;

	$k = cache_key_normalize($k, $c);
	$r = $c->set($k, $v, $life);
	return $r;
}

function cache_delete($k, $c = NULL) {
	$cache = $_SERVER['cache'];
	$c = $c ? $c : $cache;
	if(!$c) return FALSE;

	$k = cache_key_normalize($k, $c);
	$r = $c->delete($k);
	return $r;
}

// 尽量避免调用此方法，不会清理保存在 kv 中的数据，逐条 cache_delete() 比较保险
function cache_truncate($c = NULL) {
	$cache = $_SERVER['cache'];
	$c = $c ? $c : $cache;
	if(!$c) return FALSE;
	
	//$k = $c->cachepre.$k;
	$r = $c->truncate();
	return $r;
}

?>
