#!/usr/bin/env bash
# QR Menu Suite — Docker test ortamı: WordPress kurulumu + eklenti aktivasyonu.
#
# Lisans sunucusuna hiç bağlanmaz: qrms_active_modules option'ı doğrudan
# yazılır (QRMS_License_Client sadece "active" cevabında bu option'ı
# günceller; test ortamında dış ağ olmadığı için burada elle set edilir).
set -euo pipefail

WP="wp --path=/var/www/html --allow-root"

echo "[qrms] Veritabanı bekleniyor..."
until mariadb-admin ping \
  -h "$WORDPRESS_DB_HOST" \
  -u "$WORDPRESS_DB_USER" \
  -p"$WORDPRESS_DB_PASSWORD" \
  --skip-ssl >/dev/null 2>&1; do
  sleep 2
done

if [ ! -f /var/www/html/wp-config.php ]; then
  echo "[qrms] wp-config.php oluşturuluyor..."
  $WP config create \
    --dbname="$WORDPRESS_DB_NAME" \
    --dbuser="$WORDPRESS_DB_USER" \
    --dbpass="$WORDPRESS_DB_PASSWORD" \
    --dbhost="$WORDPRESS_DB_HOST" \
    --skip-check
fi

if ! $WP core is-installed >/dev/null 2>&1; then
  echo "[qrms] WordPress kuruluyor..."
  $WP core install \
    --url="$WP_SITE_URL" \
    --title="$WP_SITE_TITLE" \
    --admin_user="$WP_ADMIN_USER" \
    --admin_password="$WP_ADMIN_PASSWORD" \
    --admin_email="$WP_ADMIN_EMAIL" \
    --skip-email
else
  echo "[qrms] WordPress zaten kurulu, kurulum atlanıyor."
fi

echo "[qrms] Eklenti etkinleştiriliyor..."
$WP plugin activate qr-menu-suite

echo "[qrms] Tüm modüller test için aktif ediliyor (lisans sunucusu bypass, sadece izole test DB'sinde)..."
$WP option update qrms_active_modules '["restoran-menu","yorum-feedback","qr-masa","qr-analiz","qr-galeri","qr-ceviri","qr-chatbot","qr-calisma-saatleri","qr-masa-oturum-guvenligi","qr-acilis-ekrani","header-footer-builder","qr-servis-paneli","qr-menu-muhendisligi"]' --format=json
$WP option update qrms_last_status active

echo "[qrms] Hazır: $WP_SITE_URL/wp-admin (kullanıcı: $WP_ADMIN_USER)"
