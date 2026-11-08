#!/usr/bin/env bash
set -euo pipefail

REPO_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$REPO_ROOT"

DOCKER_BIN="${DOCKER:-docker}"
PHP_IMAGE="php@sha256:6ce95208609dc66df163ab936c970b3b34cd901b85c747102c5999f08ade9143"
MYSQL_IMAGE="mysql@sha256:dab0a802b44617303694fb17d166501de279c3031ddeb28c56ecf7fcab5ef0da"

TMP_BASE="$(mktemp -d)"
chmod 0700 "$TMP_BASE"
cleanup() {
  rm -rf "$TMP_BASE"
}
trap cleanup EXIT

PHAR_DIR="$TMP_BASE/phpunit"
mkdir -p "$PHAR_DIR"
curl -fsSL -o "$PHAR_DIR/phpunit-4.8.36.phar" https://phar.phpunit.de/phpunit-4.8.36.phar
curl -fsSL -o "$PHAR_DIR/phpunit-4.8.36.phar.asc" https://phar.phpunit.de/phpunit-4.8.36.phar.asc

EXPECTED_SHA="ab8bc3017a64cf75a2112f5e089a1d5b0c4cc2116556d60c1af5e3f584e85c5e"
ACTUAL_SHA="$(sha256sum "$PHAR_DIR/phpunit-4.8.36.phar" | awk '{print $1}')"
if [ "$ACTUAL_SHA" != "$EXPECTED_SHA" ]; then
  echo "PHPUnit phar SHA-256 mismatch" >&2
  exit 1
fi

export GNUPGHOME="$TMP_BASE/gnupg"
mkdir -p "$GNUPGHOME"
chmod 0700 "$GNUPGHOME"
gpg --batch --homedir "$GNUPGHOME" --keyserver hkps://keyserver.ubuntu.com --recv-keys 4AA394086372C20A
if ! gpg --batch --homedir "$GNUPGHOME" --status-fd 1 --verify "$PHAR_DIR/phpunit-4.8.36.phar.asc" "$PHAR_DIR/phpunit-4.8.36.phar" 2>&1 | grep -q '^\[GNUPG:\] VALIDSIG D8406D0D82947747293778314AA394086372C20A'; then
  echo "PHPUnit phar GPG VALIDSIG check failed" >&2
  exit 1
fi

export SBV_PHAR="$PHAR_DIR/phpunit-4.8.36.phar"

$DOCKER_BIN build -t sbv-php56 - <<EOF
FROM $PHP_IMAGE
RUN docker-php-ext-install mysqli
EOF

$DOCKER_BIN rm -f sbv-mysql >/dev/null 2>&1 || true
$DOCKER_BIN run -d --name sbv-mysql \
  -e MYSQL_ROOT_PASSWORD=sbv \
  -e MYSQL_DATABASE=statsbestvouchers \
  -p 127.0.0.1::3306 \
  "$MYSQL_IMAGE" \
  --sql-mode=ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION

PORT="$($DOCKER_BIN port sbv-mysql 3306 | awk -F: 'NR==1 {print $NF}')"
i=0
until $DOCKER_BIN exec sbv-mysql mysqladmin ping -uroot -psbv --silent; do
  i=$((i + 1))
  if [ "$i" -ge 60 ]; then
    echo "mysql did not become ready" >&2
    exit 1
  fi
  sleep 1
done

i=0
until $DOCKER_BIN run --rm --network host -e "PORT=$PORT" sbv-php56 php -r \
  '$p=(int)getenv("PORT"); exit(@mysqli_connect("127.0.0.1","root","sbv","statsbestvouchers",$p)?0:1);'; do
  i=$((i + 1))
  if [ "$i" -ge 60 ]; then
    echo "mysql did not accept connections from PHP on 127.0.0.1:$PORT" >&2
    exit 1
  fi
  sleep 1
done

export STATSBESTVOUCHERS_DB_HOST=127.0.0.1
export STATSBESTVOUCHERS_DB_PORT="$PORT"
export STATSBESTVOUCHERS_DB_USER=root
export STATSBESTVOUCHERS_DB_PASSWORD=sbv
export STATSBESTVOUCHERS_DB_NAME=statsbestvouchers

run_suite() {
  if [ -z "${SBV_SEED:-}" ]; then
    echo "SBV_SEED must be set for run_suite" >&2
    exit 1
  fi
  $DOCKER_BIN run --rm --network host \
    -v "$REPO_ROOT":/src:ro \
    -v "$PHAR_DIR":/opt/phpunit:ro \
    -e "STATSBESTVOUCHERS_DB_HOST=$STATSBESTVOUCHERS_DB_HOST" \
    -e "STATSBESTVOUCHERS_DB_PORT=$STATSBESTVOUCHERS_DB_PORT" \
    -e "STATSBESTVOUCHERS_DB_USER=$STATSBESTVOUCHERS_DB_USER" \
    -e "STATSBESTVOUCHERS_DB_PASSWORD=$STATSBESTVOUCHERS_DB_PASSWORD" \
    -e "STATSBESTVOUCHERS_DB_NAME=$STATSBESTVOUCHERS_DB_NAME" \
    -e "SBV_SEED=$SBV_SEED" \
    sbv-php56 \
    php -d error_reporting=-1 /src/tests/run-suite.php /opt/phpunit/phpunit-4.8.36.phar
}

export SBV_SEED=20261108
run_suite
export SBV_SEED=1
run_suite

echo "Suite passed for seeds 20261108 and 1"
