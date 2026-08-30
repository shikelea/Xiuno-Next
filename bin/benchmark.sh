#!/usr/bin/env bash
set -euo pipefail

script_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
php_binary="${XIUNO_PHP_BINARY:-}"
if [[ -z "$php_binary" ]]; then
	php_binary="$(command -v php 2>/dev/null || true)"
fi
if [[ -z "$php_binary" || ! -x "$php_binary" ]]; then
	echo 'FAIL: PHP is required. Set XIUNO_PHP_BINARY to the PHP CLI executable.' >&2
	exit 2
fi

exec "$php_binary" "$script_dir/benchmark.php" "$@"
