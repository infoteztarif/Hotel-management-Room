#!/usr/bin/env bash
set -euo pipefail

if git ls-files --error-unmatch config/settings.inc.php >/dev/null 2>&1; then
  echo 'config/settings.inc.php must never be committed'
  exit 1
fi

if git ls-files | grep -Ei '(^|/)(\.env|id_rsa|.*private.*\.pem|.*\.key)$' >/dev/null; then
  echo 'A secret-like file is tracked'
  exit 1
fi

required_saas_files=(
  modules/teztarifsaas/teztarifsaas.php
  modules/teztarifsaas/classes/TzSaasModels.php
  modules/teztarifsaas/classes/TzSaasService.php
  modules/teztarifsaas/controllers/admin/AdminTzSaasDashboardController.php
)

for required_file in "${required_saas_files[@]}"; do
  test -s "$required_file" || { echo "Missing required SaaS file: $required_file"; exit 1; }
done

if grep -R --include='*.php' -nE '(AKIA[0-9A-Z]{16}|sk_live_[0-9A-Za-z]{20,}|-----BEGIN (RSA |EC )?PRIVATE KEY-----)' \
  modules/teztarifsaas; then
  echo 'A credential pattern was found in the SaaS module'
  exit 1
fi

if grep -R --include='employee_password.*' --include='password.*' -n '{passwd}' mails/en; then
  echo 'Password reset emails must not contain plaintext password placeholders'
  exit 1
fi

echo 'Security smoke checks passed'
