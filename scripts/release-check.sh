#!/bin/sh
# release-check.sh — pre-release gates for the Brother Tours Laos repo.
#
# Runs, in order, stopping at the first failure:
#   1. php -l over every .php file in themes/ and plugins/
#      (excluding vendor/ and node_modules/)
#   2. the brand linter (scripts/brand-lint.php — see docs/ for the rules)
#   3. a secret scan over tracked files
#   4. a check that no .env file is tracked by git
#
# POSIX sh (also runs under bash). Exit 0 = all gates pass; non-zero on the
# first failing gate.

set -u

# Resolve the repo root from this script's location so the check can be run
# from anywhere.
REPO_ROOT=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd) || exit 2
cd "$REPO_ROOT" || exit 2

GATES_TOTAL=4

fail() {
    # fail <gate-number> <gate-name>
    echo ""
    echo "======================================================"
    echo "RELEASE CHECK: FAIL at gate $1/$GATES_TOTAL ($2)"
    echo "======================================================"
    exit 1
}

# ----------------------------------------------------------------------------
# Gate 1: PHP syntax lint
# ----------------------------------------------------------------------------
echo "=== Gate 1/$GATES_TOTAL: PHP syntax (php -l over themes/ and plugins/) ==="

command -v php >/dev/null 2>&1 || { echo "php not found on PATH"; fail 1 "php -l"; }

# Only lint directories that exist so find never errors out.
set --
[ -d themes ]  && set -- "$@" themes
[ -d plugins ] && set -- "$@" plugins

if [ "$#" -eq 0 ]; then
    echo "No themes/ or plugins/ directory found - nothing to lint."
else
    # The while loop runs in a subshell (pipeline); its exit status is the
    # pipeline status, so `exit 1` inside it propagates to the if-test.
    if ! find "$@" \( -type d \( -name vendor -o -name node_modules \) \) -prune \
            -o -type f -name '*.php' -print | {
        n=0
        while IFS= read -r f; do
            n=$((n + 1))
            if ! out=$(php -l "$f" 2>&1); then
                printf '%s\n' "$out"
                echo "Syntax error in: $f"
                exit 1
            fi
        done
        echo "OK: $n PHP file(s) pass php -l"
    }; then
        fail 1 "php -l"
    fi
fi

# ----------------------------------------------------------------------------
# Gate 2: Brand lint
# ----------------------------------------------------------------------------
echo ""
echo "=== Gate 2/$GATES_TOTAL: Brand lint (scripts/brand-lint.php) ==="

if ! php scripts/brand-lint.php; then
    fail 2 "brand lint"
fi

# ----------------------------------------------------------------------------
# Gate 3: Secret scan
# ----------------------------------------------------------------------------
echo ""
echo "=== Gate 3/$GATES_TOTAL: Secret scan (tracked files) ==="

# Likely committed-secret shapes. POSIX character classes so the patterns
# work with git grep -E everywhere. This script is excluded — it necessarily
# contains the patterns themselves.
SECRET_PATTERNS='sk_live_
pk_live_
-----BEGIN .* PRIVATE KEY-----
AKIA[0-9A-Z]{16}
password[[:space:]]*=[[:space:]]*['\''"][^'\''"]{6,}
api[_-]?key[[:space:]]*=[[:space:]]*['\''"][^'\''"]{12,}'

SECRETS_FOUND=0
if git rev-parse --git-dir >/dev/null 2>&1; then
    # Scan tracked files only, excluding this script.
    while IFS= read -r pat; do
        [ -n "$pat" ] || continue
        if git grep -nIE -e "$pat" -- ':!scripts/release-check.sh' 2>/dev/null; then
            echo "^ matches pattern: $pat"
            SECRETS_FOUND=1
        fi
    done <<EOF
$SECRET_PATTERNS
EOF
else
    echo "(not a git repo - falling back to a working-tree scan)"
    while IFS= read -r pat; do
        [ -n "$pat" ] || continue
        if grep -RnIE --exclude-dir=.git --exclude=release-check.sh -e "$pat" . 2>/dev/null; then
            echo "^ matches pattern: $pat"
            SECRETS_FOUND=1
        fi
    done <<EOF
$SECRET_PATTERNS
EOF
fi

if [ "$SECRETS_FOUND" -ne 0 ]; then
    echo "Likely secret(s) committed - rotate them and purge from history."
    fail 3 "secret scan"
fi
echo "OK: no likely secrets found in tracked files"

# ----------------------------------------------------------------------------
# Gate 4: No tracked .env
# ----------------------------------------------------------------------------
echo ""
echo "=== Gate 4/$GATES_TOTAL: No .env tracked by git ==="

if git rev-parse --git-dir >/dev/null 2>&1; then
    TRACKED_ENV=$(git ls-files | grep -E '(^|/)\.env(\.[A-Za-z0-9_.-]+)?$' || true)
    if [ -n "$TRACKED_ENV" ]; then
        echo "Tracked .env file(s) found:"
        printf '%s\n' "$TRACKED_ENV"
        echo "Untrack them (git rm --cached) and add to .gitignore."
        fail 4 ".env tracked"
    fi
    echo "OK: no .env files tracked"
else
    echo "(not a git repo - skipping tracked-file check)"
fi

# ----------------------------------------------------------------------------
echo ""
echo "======================================================"
echo "RELEASE CHECK: PASS ($GATES_TOTAL/$GATES_TOTAL gates)"
echo "======================================================"
exit 0
