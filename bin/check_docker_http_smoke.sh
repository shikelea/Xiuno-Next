#!/usr/bin/env bash

set -Eeuo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

BASE_URL="${XIUNO_HTTP_SMOKE_URL:-http://127.0.0.1:8080}"
ADMIN_USER="${XIUNO_HTTP_SMOKE_ADMIN_USER:-admin}"
ADMIN_EMAIL="${XIUNO_HTTP_SMOKE_ADMIN_EMAIL:-admin@example.com}"
ADMIN_PASSWORD="${XIUNO_HTTP_SMOKE_ADMIN_PASSWORD:-XiunoSmoke-451!}"
NEW_PASSWORD="${XIUNO_HTTP_SMOKE_NEW_PASSWORD:-XiunoSmoke-452!}"
DB_NAME="${XIUNO_HTTP_SMOKE_DB_NAME:-xiunobbs}"
DB_USER="${XIUNO_HTTP_SMOKE_DB_USER:-xiuno}"
DB_PASSWORD="${XIUNO_HTTP_SMOKE_DB_PASSWORD:-xiuno_password_changeme}"
WORK_DIR="$(mktemp -d)"
INSTALL_COOKIES="$WORK_DIR/install.cookies"
SITE_COOKIES="$WORK_DIR/site.cookies"
UPLOAD_PROBE="$ROOT/upload/xiuno-http-smoke.php"
COMPOSE=(docker compose)
COMPOSE_STARTED=0
REMOVE_INSTALL_STATE=0
PROBE_CREATED=0

fail() {
	echo "FAIL: $*" >&2
	return 1
}

cleanup() {
	local status=$?
	trap - EXIT INT TERM
	set +e
	if (( status != 0 && COMPOSE_STARTED == 1 )); then
		"${COMPOSE[@]}" ps >&2
		"${COMPOSE[@]}" logs --no-color >&2
	fi
	if (( COMPOSE_STARTED == 1 )); then
		"${COMPOSE[@]}" down -v --remove-orphans >/dev/null 2>&1
	fi
	if (( PROBE_CREATED == 1 )); then
		rm -f "$UPLOAD_PROBE"
	fi
	if (( REMOVE_INSTALL_STATE == 1 )); then
		rm -f "$ROOT/conf/conf.php" \
			"$ROOT/conf/conf.backup.php" \
			"$ROOT/conf/.installed.lock"
	fi
	rm -rf "$WORK_DIR"
	exit "$status"
}
trap cleanup EXIT INT TERM

if [[ -e "$ROOT/conf/conf.php" || -e "$ROOT/conf/.installed.lock" ]]; then
	fail "Docker HTTP smoke requires a clean, uninstalled checkout."
fi
[[ ! -e "$UPLOAD_PROBE" ]] || fail "$UPLOAD_PROBE already exists."

for command in docker curl jq md5sum; do
	command -v "$command" >/dev/null 2>&1 || fail "$command is required."
done
docker compose version >/dev/null 2>&1 || fail "Docker Compose v2 is required."

mkdir -p "$ROOT/conf" "$ROOT/log" "$ROOT/tmp" "$ROOT/upload" "$ROOT/plugin"
chmod 0777 "$ROOT/conf" "$ROOT/log" "$ROOT/tmp" "$ROOT/upload" "$ROOT/plugin"
echo '<?php echo "UPLOAD_PHP_EXECUTED";' > "$UPLOAD_PROBE"
PROBE_CREATED=1

"${COMPOSE[@]}" config -q
COMPOSE_STARTED=1
"${COMPOSE[@]}" up -d --build

for _ in $(seq 1 60); do
	if "${COMPOSE[@]}" exec -T db mysqladmin ping -h localhost -uroot -proot_password_changeme --silent >/dev/null 2>&1; then
		break
	fi
	sleep 2
done
"${COMPOSE[@]}" exec -T db mysqladmin ping -h localhost -uroot -proot_password_changeme --silent >/dev/null \
	|| fail "MySQL did not become ready."

for _ in $(seq 1 60); do
	if curl -fsS --max-time 5 "$BASE_URL/install/index.php?action=db" >/dev/null 2>&1; then
		break
	fi
	sleep 2
done
curl -fsS --max-time 10 "$BASE_URL/install/index.php?action=db" >/dev/null \
	|| fail "Nginx/PHP-FPM did not become ready."

"${COMPOSE[@]}" exec -T web nginx -t

assert_status() {
	local path=$1
	local expected=$2
	local actual
	actual="$(curl -sS -o "$WORK_DIR/status-body" -w '%{http_code}' --max-time 15 "$BASE_URL$path")"
	[[ "$actual" == "$expected" ]] \
		|| fail "$path returned HTTP $actual; expected $expected."
}

assert_json_code() {
	local payload=$1
	local expected=$2
	printf '%s' "$payload" | jq -e --arg expected "$expected" '.code == $expected' >/dev/null \
		|| fail "Unexpected JSON response: $payload"
}

assert_json_not_code() {
	local payload=$1
	local rejected=$2
	printf '%s' "$payload" | jq -e --arg rejected "$rejected" '.code != $rejected' >/dev/null \
		|| fail "Unexpected successful JSON response: $payload"
}

extract_install_token() {
	sed -n 's/.*name="_token" value="\([^"]*\)".*/\1/p' "$1" | head -n 1
}

extract_site_token() {
	sed -n 's/.*name="csrf-token" content="\([^"]*\)".*/\1/p' "$1" | head -n 1
}

md5_value() {
	printf '%s' "$1" | md5sum | awk '{print $1}'
}

site_token() {
	local path=$1
	local page="$WORK_DIR/site-page.html"
	curl -fsS -c "$SITE_COOKIES" -b "$SITE_COOKIES" --max-time 15 "$BASE_URL$path" -o "$page"
	local token
	token="$(extract_site_token "$page")"
	[[ "$token" =~ ^[a-f0-9]{64}$ ]] || fail "Missing CSRF token on $path."
	printf '%s' "$token"
}

site_post() {
	local path=$1
	local token=$2
	shift 2
	curl -fsS -c "$SITE_COOKIES" -b "$SITE_COOKIES" \
		-H 'X-Requested-With: XMLHttpRequest' \
		--data-urlencode "_token=$token" \
		"$@" \
		--max-time 20 "$BASE_URL$path"
}

login_with_password() {
	local password_hash
	password_hash="$(md5_value "$1")"
	local token
	token="$(site_token '/?user-login.htm')"
	local response
	response="$(site_post '/?user-login.htm' "$token" \
		--data-urlencode "email=$ADMIN_USER" \
		--data-urlencode "password=$password_hash")"
	assert_json_code "$response" '0'
}

logout_user() {
	curl -fsS -c "$SITE_COOKIES" -b "$SITE_COOKIES" --max-time 15 \
		"$BASE_URL/?user-logout.htm" >/dev/null
}

assert_status '/install/' '302'
assert_status '/install/install.func.php' '404'
assert_status '/model/check.func.php' '404'
assert_status '/view/htm/header.inc.htm' '404'
assert_status '/admin/route/update.php' '404'
assert_status '/upload/xiuno-http-smoke.php' '404'

curl -fsS -c "$INSTALL_COOKIES" -b "$INSTALL_COOKIES" --max-time 15 \
	"$BASE_URL/install/index.php?action=db" -o "$WORK_DIR/install.html"
INSTALL_TOKEN="$(extract_install_token "$WORK_DIR/install.html")"
[[ "$INSTALL_TOKEN" =~ ^[a-f0-9]{64}$ ]] || fail "Installer CSRF token is missing."

REMOVE_INSTALL_STATE=1
INSTALL_RESPONSE="$(curl -fsS -c "$INSTALL_COOKIES" -b "$INSTALL_COOKIES" \
	-H 'X-Requested-With: XMLHttpRequest' \
	--data-urlencode "_token=$INSTALL_TOKEN" \
	--data-urlencode 'type=pdo_mysql' \
	--data-urlencode 'engine=innodb' \
	--data-urlencode 'host=db' \
	--data-urlencode "name=$DB_NAME" \
	--data-urlencode "user=$DB_USER" \
	--data-urlencode "password=$DB_PASSWORD" \
	--data-urlencode "adminemail=$ADMIN_EMAIL" \
	--data-urlencode "adminuser=$ADMIN_USER" \
	--data-urlencode "adminpass=$ADMIN_PASSWORD" \
	--max-time 60 "$BASE_URL/install/index.php?action=db")"
assert_json_code "$INSTALL_RESPONSE" '0'
[[ -f "$ROOT/conf/conf.php" && -f "$ROOT/conf/.installed.lock" ]] \
	|| fail "Web installer did not persist installation state."

assert_status '/' '200'
login_with_password "$ADMIN_PASSWORD"
curl -fsS -c "$SITE_COOKIES" -b "$SITE_COOKIES" --max-time 15 "$BASE_URL/" -o "$WORK_DIR/home.html"
grep -Eq 'var uid = 1;' "$WORK_DIR/home.html" || fail "Username login did not create an authenticated session."

logout_user
login_with_password "$ADMIN_PASSWORD"

PASSWORD_TOKEN="$(site_token '/?my-password.htm')"
EMPTY_PASSWORD_RESPONSE="$(site_post '/?my-password.htm' "$PASSWORD_TOKEN" \
	--data-urlencode "password_old=$(md5_value "$ADMIN_PASSWORD")" \
	--data-urlencode 'password_new=d41d8cd98f00b204e9800998ecf8427e' \
	--data-urlencode 'password_new_repeat=d41d8cd98f00b204e9800998ecf8427e')"
assert_json_not_code "$EMPTY_PASSWORD_RESPONSE" '0'

PASSWORD_TOKEN="$(site_token '/?my-password.htm')"
PASSWORD_RESPONSE="$(site_post '/?my-password.htm' "$PASSWORD_TOKEN" \
	--data-urlencode "password_old=$(md5_value "$ADMIN_PASSWORD")" \
	--data-urlencode "password_new=$(md5_value "$NEW_PASSWORD")" \
	--data-urlencode "password_new_repeat=$(md5_value "$NEW_PASSWORD")")"
assert_json_code "$PASSWORD_RESPONSE" '0'

logout_user
login_with_password "$NEW_PASSWORD"

echo "OK: Docker Nginx, installer, username login, logout/re-login, and password HTTP smoke passed"
