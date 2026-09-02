#!/usr/bin/env bash

set -Eeuo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

RUN_ID="${XIUNO_HTTP_SMOKE_RUN_ID:-${BASHPID}_${RANDOM}_${RANDOM}}"
export COMPOSE_PROJECT_NAME="${XIUNO_COMPOSE_PROJECT_NAME:-xiuno_smoke_${RUN_ID}}"
HTTP_PORT="${XIUNO_HTTP_SMOKE_PORT:-8080}"
export XIUNO_HTTP_PORT="$HTTP_PORT"
BASE_URL="${XIUNO_HTTP_SMOKE_URL:-http://127.0.0.1:${HTTP_PORT}}"
ADMIN_USER="${XIUNO_HTTP_SMOKE_ADMIN_USER:-admin}"
ADMIN_EMAIL="${XIUNO_HTTP_SMOKE_ADMIN_EMAIL:-admin@example.com}"
ADMIN_PASSWORD="${XIUNO_HTTP_SMOKE_ADMIN_PASSWORD:-XiunoSmoke-451!}"
NEW_PASSWORD="${XIUNO_HTTP_SMOKE_NEW_PASSWORD:-XiunoSmoke-452!}"
DB_NAME="${XIUNO_HTTP_SMOKE_DB_NAME:-xiunobbs}"
DB_USER="${XIUNO_HTTP_SMOKE_DB_USER:-xiuno}"
DB_PASSWORD="${XIUNO_HTTP_SMOKE_DB_PASSWORD:-xiuno_password_changeme}"
if [[ -n "${XIUNO_TEST_HOME:-}" ]]; then
	mkdir -p "$XIUNO_TEST_HOME"
	WORK_DIR="$(mktemp -d "$XIUNO_TEST_HOME/docker-http.XXXXXX")"
else
	WORK_DIR="$(mktemp -d)"
fi
INSTALL_COOKIES="$WORK_DIR/install.cookies"
SITE_COOKIES="$WORK_DIR/site.cookies"
TOKEN_COOKIES="$WORK_DIR/token.cookies"
PASSWORD_FIXED_COOKIES="$WORK_DIR/password-fixed.cookies"
AUTOLOGIN_FIXED_COOKIES="$WORK_DIR/autologin-fixed.cookies"
OLD_AUTH_COOKIES="$WORK_DIR/old-auth.cookies"
UPLOAD_PROBE="$ROOT/upload/xiuno-http-smoke.php"
COMPOSE=(docker compose)
COMPOSE_STARTED=0
REMOVE_INSTALL_STATE=0
PROBE_CREATED=0
LOCK_DIR="$ROOT/tmp/.xiuno-docker-http-smoke.lock"
LOCK_CREATED=0
PLUGIN_MANIFEST_BEFORE=""

fail() {
	echo "FAIL: $*" >&2
	if [[ "${GITHUB_ACTIONS:-}" == 'true' ]]; then
		printf '::error title=Docker HTTP smoke::%s\n' "$*" >&2
	fi
	return 1
}

report_unhandled_error() {
	local status=$1
	local line=$2
	echo "FAIL: unhandled shell exit $status at bin/check_docker_http_smoke.sh:$line." >&2
	if [[ "${GITHUB_ACTIONS:-}" == 'true' ]]; then
		printf '::error file=bin/check_docker_http_smoke.sh,line=%s,title=Docker HTTP smoke::Unhandled shell exit %s.\n' "$line" "$status" >&2
	fi
	return "$status"
}
trap 'report_unhandled_error "$?" "$LINENO"' ERR

[[ "$RUN_ID" =~ ^[a-zA-Z0-9][a-zA-Z0-9_-]{0,63}$ ]] \
	|| fail 'XIUNO_HTTP_SMOKE_RUN_ID must contain 1-64 ASCII letters, digits, underscores, or non-leading hyphens.'

install_stage_files_exist() {
	compgen -G "$ROOT/conf/conf.php.install-*.tmp" >/dev/null
}

assert_no_unpublished_install_state() {
	[[ ! -e "$ROOT/conf/conf.php" \
		&& ! -e "$ROOT/conf/conf.backup.php" \
		&& ! -e "$ROOT/conf/.installed.lock" ]] \
		&& ! install_stage_files_exist \
		|| fail "Installer left a config, backup, lock, or staging file after rejection."
}

plugin_manifest() {
	if [[ ! -d "$ROOT/plugin" ]]; then
		echo 'ABSENT'
		return
	fi
	find "$ROOT/plugin" -type f -print0 \
		| LC_ALL=C sort -z \
		| xargs -0 -r sha256sum -- \
		| sha256sum \
		| awk '{print $1}'
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
			"$ROOT/conf/.installed.lock" \
			"$ROOT"/conf/conf.php.install-*.tmp
	fi
	if [[ -n "$PLUGIN_MANIFEST_BEFORE" ]]; then
		local plugin_manifest_after
		plugin_manifest_after="$(plugin_manifest)"
		if [[ "$plugin_manifest_after" != "$PLUGIN_MANIFEST_BEFORE" ]]; then
			echo "FAIL: plugin tree changed during Docker HTTP smoke." >&2
			status=1
		fi
	fi
	if (( LOCK_CREATED == 1 )); then
		rmdir "$LOCK_DIR" >/dev/null 2>&1 || status=1
	fi
	rm -rf "$WORK_DIR"
	exit "$status"
}
trap cleanup EXIT INT TERM

if [[ -e "$ROOT/conf/conf.php" \
	|| -e "$ROOT/conf/conf.backup.php" \
	|| -e "$ROOT/conf/.installed.lock" ]] \
	|| install_stage_files_exist; then
	fail "Docker HTTP smoke requires a clean, uninstalled checkout."
fi
[[ ! -e "$UPLOAD_PROBE" ]] || fail "$UPLOAD_PROBE already exists."

for command in docker curl jq md5sum sha256sum; do
	command -v "$command" >/dev/null 2>&1 || fail "$command is required."
done
docker compose version >/dev/null 2>&1 || fail "Docker Compose v2 is required."

mkdir -p "$ROOT/conf" "$ROOT/log" "$ROOT/tmp" "$ROOT/upload"
chmod 0777 "$ROOT/conf" "$ROOT/log" "$ROOT/tmp" "$ROOT/upload"
mkdir "$LOCK_DIR" || fail "Another Docker HTTP smoke is already using this checkout."
LOCK_CREATED=1
PLUGIN_MANIFEST_BEFORE="$(plugin_manifest)"
echo '<?php echo "UPLOAD_PHP_EXECUTED";' > "$UPLOAD_PROBE"
PROBE_CREATED=1

"${COMPOSE[@]}" config -q
COMPOSE_STARTED=1
"${COMPOSE[@]}" up -d --build
if "${COMPOSE[@]}" exec -T app sh -c 'touch /var/www/html/plugin/.xiuno-write-probe' >/dev/null 2>&1; then
	"${COMPOSE[@]}" exec -T app rm -f /var/www/html/plugin/.xiuno-write-probe >/dev/null 2>&1 || true
	fail 'The container can write the plugin tree; the read-only mount contract is broken.'
fi
"${COMPOSE[@]}" exec -T app sh -c \
	'touch /var/www/html/vendor/.xiuno-write-probe && rm -f /var/www/html/vendor/.xiuno-write-probe' \
	|| fail 'The isolated Composer dependency volume is not writable.'

mysql_ready() {
	"${COMPOSE[@]}" exec -T -e MYSQL_PWD=root_password_changeme db \
		mysqladmin ping -h 127.0.0.1 -uroot --silent >/dev/null 2>&1 \
		&& "${COMPOSE[@]}" exec -T -e "MYSQL_PWD=$DB_PASSWORD" db \
			mysql -h 127.0.0.1 -u"$DB_USER" "$DB_NAME" -Nse 'SELECT 1' 2>/dev/null \
			| grep -qx '1'
}

mysql_query() {
	local sql=$1
	"${COMPOSE[@]}" exec -T -e "MYSQL_PWD=$DB_PASSWORD" db \
		mysql -h 127.0.0.1 -u"$DB_USER" "$DB_NAME" -Nse "$sql"
}

for _ in $(seq 1 60); do
	if mysql_ready; then
		break
	fi
	sleep 2
done
mysql_ready || fail "MySQL TCP service and application account did not become ready."

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

api_request() {
	local method=$1
	local path=$2
	local body_file=$3
	local header_file=$4
	shift 4
	curl -sS -D "$header_file" -o "$body_file" -w '%{http_code}' \
		-X "$method" "$@" --max-time 20 "$BASE_URL$path"
}

extract_install_token() {
	sed -n 's/.*name="_token" value="\([^"]*\)".*/\1/p' "$1" | head -n 1
}

fetch_install_token() {
	local page="$WORK_DIR/install.html"
	curl -fsS -c "$INSTALL_COOKIES" -b "$INSTALL_COOKIES" --max-time 15 \
		"$BASE_URL/install/index.php?action=db" -o "$page"
	local token
	token="$(extract_install_token "$page")"
	[[ "$token" =~ ^[a-f0-9]{64}$ ]] || fail "Installer CSRF token is missing."
	printf '%s' "$token"
}

extract_site_token() {
	sed -n 's/.*name="csrf-token" content="\([^"]*\)".*/\1/p' "$1" | head -n 1
}

md5_value() {
	printf '%s' "$1" | md5sum | awk '{print $1}'
}

cookie_value() {
	local file=$1
	local name=$2
	awk -v name="$name" '$6 == name { print $7; exit }' "$file"
}

cookie_host() {
	local authority="${BASE_URL#*://}"
	authority="${authority%%/*}"
	if [[ "$authority" == \[*\]* ]]; then
		authority="${authority#\[}"
		printf '%s' "${authority%%\]*}"
	else
		printf '%s' "${authority%%:*}"
	fi
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

submit_install() {
	local token=$1
	curl -fsS -c "$INSTALL_COOKIES" -b "$INSTALL_COOKIES" \
		-H 'X-Requested-With: XMLHttpRequest' \
		--data-urlencode "_token=$token" \
		--data-urlencode 'type=pdo_mysql' \
		--data-urlencode 'engine=innodb' \
		--data-urlencode 'host=db' \
		--data-urlencode "name=$DB_NAME" \
		--data-urlencode "user=$DB_USER" \
		--data-urlencode "password=$DB_PASSWORD" \
		--data-urlencode "adminemail=$ADMIN_EMAIL" \
		--data-urlencode "adminuser=$ADMIN_USER" \
		--data-urlencode "adminpass=$ADMIN_PASSWORD" \
		--max-time 60 "$BASE_URL/install/index.php?action=db"
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
	local token
	token="$(site_token '/?user-logout.htm')"
	local response
	response="$(site_post '/?user-logout.htm' "$token")"
	assert_json_code "$response" '0'
}

assert_status '/install/' '302'
assert_status '/install/install.func.php' '404'
assert_status '/model/check.func.php' '404'
assert_status '/view/htm/header.inc.htm' '404'
assert_status '/admin/route/update.php' '404'
assert_status '/upload/xiuno-http-smoke.php' '404'
assert_status '/plugin/xiuno-http-smoke/install.php' '404'
assert_status '/plugin/xiuno-http-smoke/unstall.php' '404'
assert_status '/plugin/xiuno-http-smoke/upgrade.php' '404'
assert_status '/plugin/xiuno-http-smoke/setting.php' '404'
assert_status '/plugin/xiuno-http-smoke/hook/probe.php' '404'
assert_status '/plugin/xiuno-http-smoke/overwrite/probe.php' '404'

INITIAL_TABLE_COUNT="$(mysql_query 'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()')"
[[ "$INITIAL_TABLE_COUNT" == '0' ]] || fail "Docker HTTP smoke requires an empty application database."
mysql_query "CREATE TABLE bbs_table_day (sentinel_id INT NOT NULL PRIMARY KEY, marker VARCHAR(64) NOT NULL); INSERT INTO bbs_table_day (sentinel_id, marker) VALUES (451, 'xiuno-install-sentinel')"

INSTALL_TOKEN="$(fetch_install_token)"
REMOVE_INSTALL_STATE=1
SENTINEL_RESPONSE="$(submit_install "$INSTALL_TOKEN")"
assert_json_not_code "$SENTINEL_RESPONSE" '0'
assert_no_unpublished_install_state
SENTINEL_TABLES="$(mysql_query "SELECT GROUP_CONCAT(TABLE_NAME ORDER BY TABLE_NAME) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()")"
[[ "$SENTINEL_TABLES" == 'bbs_table_day' ]] \
	|| fail "Rejected installation created or removed database tables: $SENTINEL_TABLES"
SENTINEL_COLUMNS="$(mysql_query "SELECT GROUP_CONCAT(CONCAT(COLUMN_NAME, ':', DATA_TYPE, ':', COLUMN_KEY) ORDER BY ORDINAL_POSITION SEPARATOR ',') FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'bbs_table_day'")"
[[ "$SENTINEL_COLUMNS" == 'sentinel_id:int:PRI,marker:varchar:' ]] \
	|| fail "Rejected installation changed the sentinel table structure: $SENTINEL_COLUMNS"
SENTINEL_MARKER="$(mysql_query 'SELECT marker FROM `bbs_table_day` WHERE sentinel_id = 451')"
[[ "$SENTINEL_MARKER" == 'xiuno-install-sentinel' ]] \
	|| fail "Rejected installation changed the sentinel table data."

mysql_query 'DROP TABLE `bbs_table_day`'
[[ "$(mysql_query 'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()')" == '0' ]] \
	|| fail "Sentinel cleanup did not restore an empty application database."

INSTALL_TOKEN="$(fetch_install_token)"
INSTALL_RESPONSE="$(submit_install "$INSTALL_TOKEN")"
assert_json_code "$INSTALL_RESPONSE" '0'
[[ -f "$ROOT/conf/conf.php" && -f "$ROOT/conf/.installed.lock" ]] \
	|| fail "Web installer did not persist installation state."
INSTALL_AUTH_KEY="$("${COMPOSE[@]}" exec -T app php -r '$conf = require "/var/www/html/conf/conf.php"; echo isset($conf["auth_key"]) && is_string($conf["auth_key"]) ? $conf["auth_key"] : "";')" \
	|| fail "Application container could not read the installed configuration."
[[ "$INSTALL_AUTH_KEY" =~ ^[a-f0-9]{64}$ ]] \
	|| fail "Web installer did not persist a 64-character hexadecimal auth key."
[[ ! -e "$ROOT/conf/conf.backup.php" ]] && ! install_stage_files_exist \
	|| fail "Successful installation left a config backup or staging file."

assert_status '/' '200'
PASSWORD_FIXED_SID='fixedpasswordsid1234567890123456'
[[ ${#PASSWORD_FIXED_SID} -eq 32 ]] || fail "Password fixed session ID must fit the database primary key."
printf '%s\tFALSE\t/\tFALSE\t0\tbbs_sid\t%s\n' "$(cookie_host)" "$PASSWORD_FIXED_SID" > "$SITE_COOKIES"
PASSWORD_TOKEN="$(site_token '/?user-login.htm')"
PASSWORD_PRELOGIN_SID="$(cookie_value "$SITE_COOKIES" bbs_sid)"
[[ -n "$PASSWORD_PRELOGIN_SID" ]] || fail "Login form did not establish a pre-authentication session ID."
PASSWORD_LOGIN_RESPONSE="$(site_post '/?user-login.htm' "$PASSWORD_TOKEN" \
	--data-urlencode "email=$ADMIN_USER" \
	--data-urlencode "password=$(md5_value "$ADMIN_PASSWORD")")"
assert_json_code "$PASSWORD_LOGIN_RESPONSE" '0'
PASSWORD_ROTATED_SID="$(cookie_value "$SITE_COOKIES" bbs_sid)"

[[ -n "$PASSWORD_ROTATED_SID" && "$PASSWORD_ROTATED_SID" != "$PASSWORD_PRELOGIN_SID" ]] \
	|| fail "Password login did not rotate an attacker-controlled session ID."
printf '%s\tFALSE\t/\tFALSE\t0\tbbs_sid\t%s\n' "$(cookie_host)" "$PASSWORD_PRELOGIN_SID" > "$PASSWORD_FIXED_COOKIES"
curl -fsS -c "$PASSWORD_FIXED_COOKIES" -b "$PASSWORD_FIXED_COOKIES" --max-time 15 "$BASE_URL/" -o "$WORK_DIR/password-fixed-home.html"
grep -Eq 'var uid = 0;' "$WORK_DIR/password-fixed-home.html" || fail "Previous password-login session ID restored authentication."
PASSWORD_REPLAY_SID="$(cookie_value "$PASSWORD_FIXED_COOKIES" bbs_sid)"
[[ "$PASSWORD_REPLAY_SID" != "$PASSWORD_PRELOGIN_SID" ]] || fail "Previous password-login session ID was accepted as a new anonymous session."
curl -fsS -c "$SITE_COOKIES" -b "$SITE_COOKIES" --max-time 15 "$BASE_URL/" -o "$WORK_DIR/home.html"
grep -Eq 'var uid = 1;' "$WORK_DIR/home.html" || fail "Username login did not create an authenticated session."

PERSISTENT_TOKEN="$(cookie_value "$SITE_COOKIES" bbs_token)"
[[ -n "$PERSISTENT_TOKEN" ]] || fail "Password login did not issue a persistent login token."
AUTOLOGIN_FIXED_SID='fixedsessionid123456789012345678'
[[ ${#AUTOLOGIN_FIXED_SID} -eq 32 ]] || fail "Persistent-token fixed session ID must fit the database primary key."
awk '$6 != "bbs_sid"' "$SITE_COOKIES" > "$TOKEN_COOKIES"
printf '%s\tFALSE\t/\tFALSE\t0\tbbs_sid\t%s\n' "$(cookie_host)" "$AUTOLOGIN_FIXED_SID" >> "$TOKEN_COOKIES"
curl -fsS -c "$TOKEN_COOKIES" -b "$TOKEN_COOKIES" --max-time 15 "$BASE_URL/" -o "$WORK_DIR/token-home.html"
grep -Eq 'var uid = 1;' "$WORK_DIR/token-home.html" || fail "Persistent token login did not restore an authenticated session."
AUTOLOGIN_ROTATED_SID="$(cookie_value "$TOKEN_COOKIES" bbs_sid)"
[[ -n "$AUTOLOGIN_ROTATED_SID" && "$AUTOLOGIN_ROTATED_SID" != "$AUTOLOGIN_FIXED_SID" ]] \
	|| fail "Persistent token login did not rotate an attacker-controlled session ID."
printf '%s\tFALSE\t/\tFALSE\t0\tbbs_sid\t%s\n' "$(cookie_host)" "$AUTOLOGIN_FIXED_SID" > "$AUTOLOGIN_FIXED_COOKIES"
curl -fsS -c "$AUTOLOGIN_FIXED_COOKIES" -b "$AUTOLOGIN_FIXED_COOKIES" --max-time 15 "$BASE_URL/" -o "$WORK_DIR/token-fixed-home.html"
grep -Eq 'var uid = 0;' "$WORK_DIR/token-fixed-home.html" || fail "Previous persistent-token session ID restored authentication."
AUTOLOGIN_REPLAY_SID="$(cookie_value "$AUTOLOGIN_FIXED_COOKIES" bbs_sid)"
[[ "$AUTOLOGIN_REPLAY_SID" != "$AUTOLOGIN_FIXED_SID" ]] || fail "Previous persistent-token session ID was accepted as a new anonymous session."

logout_user
login_with_password "$ADMIN_PASSWORD"

LEGACY_MISSING_STATUS="$(api_request GET '/?api-missing-index.htm' "$WORK_DIR/api-legacy-missing.json" "$WORK_DIR/api-legacy-missing.headers")"
[[ "$LEGACY_MISSING_STATUS" == '200' ]] || fail "Legacy API missing route returned HTTP $LEGACY_MISSING_STATUS; expected compatibility HTTP 200."
jq -e '(.code | tostring) == "404"' "$WORK_DIR/api-legacy-missing.json" >/dev/null \
	|| fail 'Legacy API missing route did not preserve JSON code 404.'

V1_MISSING_STATUS="$(api_request GET '/?api-v1-missing-index.htm' "$WORK_DIR/api-v1-missing.json" "$WORK_DIR/api-v1-missing.headers")"
[[ "$V1_MISSING_STATUS" == '404' ]] || fail "API v1 missing route returned HTTP $V1_MISSING_STATUS; expected 404."
jq -e '(.code | tostring) == "404"' "$WORK_DIR/api-v1-missing.json" >/dev/null \
	|| fail 'API v1 missing route did not return JSON code 404.'
grep -Fqi 'X-Xiuno-API-Version: v1' "$WORK_DIR/api-v1-missing.headers" \
	|| fail 'API v1 response did not identify its contract version.'

V1_METHOD_STATUS="$(api_request GET '/?api-v1-thread-create.htm' "$WORK_DIR/api-v1-method.json" "$WORK_DIR/api-v1-method.headers")"
[[ "$V1_METHOD_STATUS" == '405' ]] || fail "API v1 invalid method returned HTTP $V1_METHOD_STATUS; expected 405."
grep -Eqi '^Allow: POST' "$WORK_DIR/api-v1-method.headers" \
	|| fail 'API v1 method rejection did not advertise Allow: POST.'

V1_READ_METHOD_STATUS="$(api_request POST '/?api-v1-forum-list.htm' "$WORK_DIR/api-v1-read-method.json" "$WORK_DIR/api-v1-read-method.headers")"
[[ "$V1_READ_METHOD_STATUS" == '405' ]] || fail "API v1 read endpoint accepted POST with HTTP $V1_READ_METHOD_STATUS; expected 405."
grep -Eqi '^Allow: GET' "$WORK_DIR/api-v1-read-method.headers" \
	|| fail 'API v1 read method rejection did not advertise Allow: GET.'

V1_UNAUTH_STATUS="$(api_request POST '/?api-v1-thread-create.htm' "$WORK_DIR/api-v1-unauth.json" "$WORK_DIR/api-v1-unauth.headers" \
	--data-urlencode 'fid=1' --data-urlencode 'subject=unauthorized' --data-urlencode 'message=unauthorized')"
[[ "$V1_UNAUTH_STATUS" == '401' ]] || fail "API v1 unauthenticated write returned HTTP $V1_UNAUTH_STATUS; expected 401."
jq -e '(.code | tostring) == "-1"' "$WORK_DIR/api-v1-unauth.json" >/dev/null \
	|| fail 'API v1 unauthenticated write did not retain the JSON error envelope.'

V1_LOGIN_STATUS="$(api_request POST '/?api-v1-user-login.htm' "$WORK_DIR/api-v1-login.json" "$WORK_DIR/api-v1-login.headers" \
	--data-urlencode "email=$ADMIN_EMAIL" --data-urlencode "password=$ADMIN_PASSWORD")"
[[ "$V1_LOGIN_STATUS" == '200' ]] || fail "API v1 login returned HTTP $V1_LOGIN_STATUS; expected 200."
jq -e '(.code | tostring) == "0" and (.data.token | type == "string" and length > 20)' "$WORK_DIR/api-v1-login.json" >/dev/null \
	|| fail 'API v1 login did not return the stable success envelope and token.'
API_TOKEN="$(jq -r '.data.token' "$WORK_DIR/api-v1-login.json")"

V1_FORUM_STATUS="$(api_request GET '/?api-v1-forum-list.htm' "$WORK_DIR/api-v1-forums.json" "$WORK_DIR/api-v1-forums.headers" \
	-H "Authorization: Bearer $API_TOKEN")"
[[ "$V1_FORUM_STATUS" == '200' ]] || fail "API v1 forum list returned HTTP $V1_FORUM_STATUS; expected 200."
API_FID="$(jq -r '.data.list[0].fid // empty' "$WORK_DIR/api-v1-forums.json")"
[[ "$API_FID" =~ ^[1-9][0-9]*$ ]] || fail 'API v1 forum list did not expose a readable forum ID.'

API_THREAD_COUNT_BEFORE="$(mysql_query 'SELECT COUNT(*) FROM bbs_thread')"
V1_DOCTYPE_STATUS="$(api_request POST '/?api-v1-thread-create.htm' "$WORK_DIR/api-v1-doctype.json" "$WORK_DIR/api-v1-doctype.headers" \
	-H "Authorization: Bearer $API_TOKEN" --data-urlencode "fid=$API_FID" \
	--data-urlencode 'subject=Unsupported document type' --data-urlencode 'message=Must not persist' --data-urlencode 'doctype=2')"
[[ "$V1_DOCTYPE_STATUS" == '422' ]] || fail "API v1 unsupported document type returned HTTP $V1_DOCTYPE_STATUS; expected 422."
jq -e '(.code | tostring) == "-1" and .message == "Document type is not supported"' "$WORK_DIR/api-v1-doctype.json" >/dev/null \
	|| fail 'API v1 unsupported document type did not return the documented JSON error.'
[[ "$(mysql_query 'SELECT COUNT(*) FROM bbs_thread')" == "$API_THREAD_COUNT_BEFORE" ]] \
	|| fail 'API v1 unsupported document type wrote a thread.'

API_SUBJECT="API v1 HTTP smoke $RUN_ID"
V1_THREAD_CREATE_STATUS="$(api_request POST '/?api-v1-thread-create.htm' "$WORK_DIR/api-v1-thread-create.json" "$WORK_DIR/api-v1-thread-create.headers" \
	-H "Authorization: Bearer $API_TOKEN" --data-urlencode "fid=$API_FID" \
	--data-urlencode "subject=$API_SUBJECT" --data-urlencode 'message=API v1 thread body')"
[[ "$V1_THREAD_CREATE_STATUS" == '200' ]] || fail "API v1 thread create returned HTTP $V1_THREAD_CREATE_STATUS; expected 200."
API_TID="$(jq -r '.data.tid // empty' "$WORK_DIR/api-v1-thread-create.json")"
[[ "$API_TID" =~ ^[1-9][0-9]*$ ]] || fail 'API v1 thread create did not return a thread ID.'
[[ "$(mysql_query "SELECT doctype FROM bbs_post WHERE tid = $API_TID AND isfirst = 1")" == '1' ]] \
	|| fail 'API v1 omitted document type did not default the new thread to plain text.'

V1_THREAD_LIST_STATUS="$(api_request GET "/?api-v1-thread-list.htm&fid=$API_FID" "$WORK_DIR/api-v1-thread-list.json" "$WORK_DIR/api-v1-thread-list.headers" \
	-H "Authorization: Bearer $API_TOKEN")"
[[ "$V1_THREAD_LIST_STATUS" == '200' ]] || fail "API v1 thread list returned HTTP $V1_THREAD_LIST_STATUS; expected 200."
jq -e --arg tid "$API_TID" '(.data.list | type) == "array" and any(.data.list[]; (.tid | tostring) == $tid)' "$WORK_DIR/api-v1-thread-list.json" >/dev/null \
	|| fail 'API v1 thread list was not a JSON array containing the created thread.'

API_VIEWS_BEFORE="$(mysql_query "SELECT views FROM bbs_thread WHERE tid = $API_TID")"
V1_THREAD_READ_STATUS="$(api_request GET "/?api-v1-thread-read.htm&tid=$API_TID" "$WORK_DIR/api-v1-thread-read.json" "$WORK_DIR/api-v1-thread-read.headers" \
	-H "Authorization: Bearer $API_TOKEN")"
[[ "$V1_THREAD_READ_STATUS" == '200' ]] || fail "API v1 thread read returned HTTP $V1_THREAD_READ_STATUS; expected 200."
jq -e --arg tid "$API_TID" '(.code | tostring) == "0" and (.data.thread.tid | tostring) == $tid and (.data.posts[0].doctype | tonumber) == 1' "$WORK_DIR/api-v1-thread-read.json" >/dev/null \
	|| fail 'API v1 thread read did not return the created plain-text thread.'
[[ "$(mysql_query "SELECT views FROM bbs_thread WHERE tid = $API_TID")" == "$API_VIEWS_BEFORE" ]] \
	|| fail 'API v1 thread read changed the view counter.'

V1_POST_CREATE_STATUS="$(api_request POST '/?api-v1-post-create.htm' "$WORK_DIR/api-v1-post-create.json" "$WORK_DIR/api-v1-post-create.headers" \
	-H "Authorization: Bearer $API_TOKEN" --data-urlencode "tid=$API_TID" --data-urlencode 'message=API v1 reply body')"
[[ "$V1_POST_CREATE_STATUS" == '200' ]] || fail "API v1 post create returned HTTP $V1_POST_CREATE_STATUS; expected 200."
jq -e --arg tid "$API_TID" '(.code | tostring) == "0" and (.data.tid | tostring) == $tid and (.data.doctype | tonumber) == 1' "$WORK_DIR/api-v1-post-create.json" >/dev/null \
	|| fail 'API v1 reply did not use the stable success envelope and plain-text default.'

PASSWORD_TOKEN="$(site_token '/?my-password.htm')"
EMPTY_PASSWORD_RESPONSE="$(site_post '/?my-password.htm' "$PASSWORD_TOKEN" \
	--data-urlencode "password_old=$(md5_value "$ADMIN_PASSWORD")" \
	--data-urlencode 'password_new=d41d8cd98f00b204e9800998ecf8427e' \
	--data-urlencode 'password_new_repeat=d41d8cd98f00b204e9800998ecf8427e')"
assert_json_not_code "$EMPTY_PASSWORD_RESPONSE" '0'

PASSWORD_TOKEN="$(site_token '/?my-password.htm')"
cp "$SITE_COOKIES" "$OLD_AUTH_COOKIES"
PRECHANGE_SID="$(cookie_value "$SITE_COOKIES" bbs_sid)"
PASSWORD_RESPONSE="$(site_post '/?my-password.htm' "$PASSWORD_TOKEN" \
	--data-urlencode "password_old=$(md5_value "$ADMIN_PASSWORD")" \
	--data-urlencode "password_new=$(md5_value "$NEW_PASSWORD")" \
	--data-urlencode "password_new_repeat=$(md5_value "$NEW_PASSWORD")")"
assert_json_code "$PASSWORD_RESPONSE" '0'
POSTCHANGE_SID="$(cookie_value "$SITE_COOKIES" bbs_sid)"
[[ -n "$POSTCHANGE_SID" && "$POSTCHANGE_SID" != "$PRECHANGE_SID" ]] \
	|| fail "Self-service password change did not rotate the current session."
curl -fsS -c "$SITE_COOKIES" -b "$SITE_COOKIES" --max-time 15 "$BASE_URL/" -o "$WORK_DIR/password-current-home.html"
grep -Eq 'var uid = 1;' "$WORK_DIR/password-current-home.html" \
	|| fail "Self-service password change did not preserve the rotated current session."
curl -fsS -c "$OLD_AUTH_COOKIES" -b "$OLD_AUTH_COOKIES" --max-time 15 "$BASE_URL/" -o "$WORK_DIR/password-old-home.html"
grep -Eq 'var uid = 0;' "$WORK_DIR/password-old-home.html" \
	|| fail "Password change did not revoke the older Session and persistent token generation."

logout_user
login_with_password "$NEW_PASSWORD"

"${COMPOSE[@]}" stop db >/dev/null
DB_DOWN_STATUS="$(curl -sS -o "$WORK_DIR/db-down.html" -w '%{http_code}' --max-time 15 "$BASE_URL/")"
[[ "$DB_DOWN_STATUS" == '503' ]] || fail 'Database outage did not return HTTP 503.'
grep -Fq '数据库服务暂时不可用' "$WORK_DIR/db-down.html" \
	|| fail 'Database outage browser response did not contain an actionable diagnostic.'
DB_DOWN_AJAX_STATUS="$(curl -sS -H 'X-Requested-With: XMLHttpRequest' -o "$WORK_DIR/db-down.json" -w '%{http_code}' --max-time 15 "$BASE_URL/?user-login.htm")"
[[ "$DB_DOWN_AJAX_STATUS" == '503' ]] || fail 'Database outage AJAX request did not return HTTP 503.'
jq -e '.code == "-1" and (.message | contains("数据库服务暂时不可用"))' "$WORK_DIR/db-down.json" >/dev/null \
	|| fail 'Database outage AJAX response was not structured JSON.'

echo "OK: Docker Nginx, installer preflight, API v1, login rotation, credential-epoch revocation, logout/re-login, password, and database-outage HTTP smoke passed"
