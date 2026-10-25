#!/usr/bin/env bash
# Runs every unit test script. Exits non-zero if any of them fails.
set -u
cd "$(dirname "$0")/.."

failed=0
total=0
for f in tests/*-test.php; do
	total=$((total + 1))
	if out=$(php "$f" 2>&1); then
		echo "ok   $f"
	else
		failed=$((failed + 1))
		echo "FAIL $f"
		echo "$out" | grep -E 'FAIL|Fatal|Warning|Error' | sed 's/^/     /'
	fi
done

echo "$((total - failed))/$total test files passed"
[ "$failed" -eq 0 ]
