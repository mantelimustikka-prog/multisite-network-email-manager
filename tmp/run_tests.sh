#!/bin/bash
cd /home/runner/work/multisite-network-email-manager/multisite-network-email-manager
echo "=== Running Full PHPUnit Test Suite ==="
echo ""
php vendor/bin/phpunit --verbose 2>&1 | tee /tmp/test_results.txt
EXIT_CODE=${PIPESTATUS[0]}
echo ""
echo "=== Test Summary ==="
tail -20 /tmp/test_results.txt
exit $EXIT_CODE
