#!/usr/bin/env bash

set -euo pipefail

php_binary="${XIUNO_PHP_BINARY:-${PHP_BINARY:-}}"
while (($# > 0)); do
	case "$1" in
		--php-binary)
			[[ $# -ge 2 ]] || { echo 'FAIL: --php-binary requires an absolute PHP executable path' >&2; exit 2; }
			php_binary="$2"
			shift 2
			;;
		*)
			echo "FAIL: unknown browser runner argument: $1" >&2
			exit 2
			;;
	esac
done
if [[ -z "$php_binary" ]]; then
	echo 'FAIL: pass --php-binary or set XIUNO_PHP_BINARY; the browser runner does not resolve php from PATH' >&2
	exit 2
fi
if [[ "$php_binary" != /* || ! -x "$php_binary" ]]; then
	echo "FAIL: PHP binary is not an absolute executable file: $php_binary" >&2
	exit 2
fi

repo_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
if [[ -n "${XIUNO_TEST_HOME:-}" ]]; then
	mkdir -p -- "$XIUNO_TEST_HOME"
	fixture_tmp="$(mktemp -d "$XIUNO_TEST_HOME/xiuno-bs4-browser.XXXXXX")"
else
	fixture_tmp="$(mktemp -d)"
fi
server_pid=""

cleanup() {
	if [[ -n "$server_pid" ]] && kill -0 "$server_pid" 2>/dev/null; then
		kill "$server_pid" 2>/dev/null || true
		wait "$server_pid" 2>/dev/null || true
	fi
	if [[ -n "$fixture_tmp" && -d "$fixture_tmp" ]]; then
		rm -rf -- "$fixture_tmp"
	fi
}
trap cleanup EXIT

browser="${CHROME_BIN:-}"
if [[ -n "$browser" && ! -x "$browser" ]]; then
	echo "FAIL: CHROME_BIN is not an executable file: $browser" >&2
	exit 1
fi
for candidate in google-chrome-stable google-chrome chromium chromium-browser; do
	[[ -n "$browser" ]] && break
	if command -v "$candidate" >/dev/null 2>&1; then
		browser="$(command -v "$candidate")"
		break
	fi
done
if [[ -z "$browser" ]]; then
	echo "FAIL: a Chromium browser is required for the BS4 behavior fixture" >&2
	exit 1
fi

port="$((18000 + RANDOM % 1000))"
"$php_binary" -S "127.0.0.1:${port}" -t "$repo_root" >"$fixture_tmp/php-server.log" 2>&1 &
server_pid="$!"

ready=0
for _ in {1..50}; do
	if curl --fail --silent --show-error "http://127.0.0.1:${port}/bin/fixtures/bs4_compat_runtime.html" >/dev/null; then
		ready=1
		break
	fi
	sleep 0.1
done
if [[ "$ready" -ne 1 ]]; then
	echo "FAIL: fixture HTTP server did not become ready" >&2
	cat "$fixture_tmp/php-server.log" >&2
	exit 1
fi

for asset_mode in source min; do
	query=""
	if [[ "$asset_mode" == "min" ]]; then
		query="?assets=min"
	fi
	result_file="$fixture_tmp/result-${asset_mode}.html"
	browser_log="$fixture_tmp/browser-${asset_mode}.log"
	"$browser" \
		--headless=new \
		--no-sandbox \
		--disable-gpu \
		--disable-dev-shm-usage \
		--disable-background-networking \
		--disable-breakpad \
		--disable-crash-reporter \
		--no-first-run \
		--no-default-browser-check \
		--user-data-dir="$fixture_tmp/profile-${asset_mode}" \
		--disk-cache-dir="$fixture_tmp/cache-${asset_mode}" \
		--virtual-time-budget=30000 \
		--dump-dom \
		"http://127.0.0.1:${port}/bin/fixtures/bs4_compat_runtime.html${query}" \
		>"$result_file" 2>"$browser_log"

	if ! grep -q 'data-complete="1"' "$result_file" || ! grep -q 'data-failed="0"' "$result_file"; then
		echo "FAIL: Chromium BS4 compatibility behavior fixture failed for ${asset_mode} assets or did not complete" >&2
		cat "$browser_log" >&2
		cat "$result_file" >&2
		exit 1
	fi
done

echo "OK: Chromium BS4 compatibility behavior fixture passed for source and generated assets"
