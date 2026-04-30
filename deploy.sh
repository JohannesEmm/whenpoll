#!/usr/bin/env bash
# WhenPoll — deploy via FTP (curl)
# Usage: ./deploy.sh
set -euo pipefail

FTP_HOST="w0109952.kasserver.com"
FTP_USER="f0183e7b"
FTP_PASS='gy5ZXAdQmCk3RQ,i6LgD'
BASE="ftp://$FTP_HOST"
CURL="curl -s --ftp-ssl --insecure --user $FTP_USER:$FTP_PASS"

cd "$(dirname "$0")"

upload() {
  echo -n "  $1 ... "
  $CURL -T "$1" "$BASE/$1" && echo "OK" || echo "FAILED"
}

echo "→ Deploying WhenPoll to $FTP_HOST"
echo ""

echo "── PHP ──"
upload auth.php
upload create.php
upload vote.php
upload index.php
upload results.php
upload calendar.php
upload landing.php
upload db.php
upload api.php

echo "── CSS ──"
upload css/app.css
upload css/landing.css

echo "── Misc ──"
upload .htaccess

echo ""
echo "✓ Done → https://whenpoll.com"
