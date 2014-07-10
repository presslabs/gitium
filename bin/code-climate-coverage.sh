#!/bin/bash
set -e
CODECLIMATE_REPO_TOKEN=819c356e2d022f98e4e2717dbd459c3801fef95241a2db7261596a0482dd6ca3 vendor/bin/test-reporter --stdout > codeclimate.json
curl -X POST -d @codeclimate.json -H 'Content-Type: application/json' -H 'User-Agent: Code Climate (PHP Test Reporter v1.0.1-dev)' https://codeclimate.com/test_reports
