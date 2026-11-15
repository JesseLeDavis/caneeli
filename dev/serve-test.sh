#!/bin/bash
# Run the site against the sample database at http://localhost:8080
#
#   dev/serve-test.sh          start the test site
#   dev/serve-test.sh --reset  rebuild the sample data first
#
# First time: php dev/setup-test.php (creates .env.test and the database).
cd "$(dirname "$0")/.." || exit 1

if [ ! -f .env.test ]; then
  echo "No .env.test yet. Setting up the test environment first..."
  php dev/setup-test.php || exit 1
elif [ "$1" = "--reset" ]; then
  php dev/setup-test.php || exit 1
fi

# Rebuild CSS so the preview matches the SCSS.
if [ -x node_modules/.bin/sass ]; then
  node_modules/.bin/sass assets/scss/main.scss:assets/css/style.css --no-source-map
fi

PORT=$(grep -E '^SITE_URL=' .env.test | sed -E 's/.*:([0-9]+).*/\1/')
PORT=${PORT:-8080}

echo ""
echo "  Test site:  http://localhost:$PORT"
echo "  Admin:      http://localhost:$PORT/admin/   (login is in .env.test)"
echo "  Stop with Ctrl+C"
echo ""
CANEELI_ENV=test exec php -S "localhost:$PORT" dev/router.php
