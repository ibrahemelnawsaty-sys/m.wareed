#!/usr/bin/env bash
# ============================================================
#  وريد — سكربت ما بعد النشر (Hostinger، عبر SSH)
#  شغّله من جذر المشروع على الخادم بعد كل سحب جديد للكود:
#     bash deploy.sh
# ============================================================
set -euo pipefail
cd "$(dirname "$0")"

echo "→ وضع الصيانة"
php artisan down --render="errors::503" || true

echo "→ تثبيت الحزم (إنتاج)"
composer install --no-dev --optimize-autoloader --no-interaction

echo "→ الهجرات"
php artisan migrate --force

echo "→ رابط التخزين"
php artisan storage:link || true

echo "→ تحسين الكاش"
php artisan optimize:clear
php artisan optimize          # config + route + view + event cache

echo "→ إنهاء الصيانة"
php artisan up

echo "✅ اكتمل النشر."
