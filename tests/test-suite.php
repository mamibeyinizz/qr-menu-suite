<?php
/**
 * QR Menu Suite — stub tabanlı mantık testleri.
 *
 * Çalıştırmak için: php tests/test-suite.php
 *
 * Gerçek bir WordPress kurulumu gerekmez; WordPress fonksiyonları
 * tests/stubs-wordpress.php içinde taklit edilir. Gövde modül dosyalarına
 * bölünmüştür; sıra ve doğrulama sayısı değişmez.
 *
 * @package QR_Menu_Suite
 */

require_once __DIR__ . '/bootstrap.php';

echo "\nQR Menu Suite — mantık testleri\n\n";

require_once __DIR__ . '/test-core.php';
require_once __DIR__ . '/test-chatbot.php';
require_once __DIR__ . '/test-analiz.php';
require_once __DIR__ . '/test-masa-yorum.php';
require_once __DIR__ . '/test-analiz-izleme.php';
require_once __DIR__ . '/test-masa.php';
require_once __DIR__ . '/test-yorum-admin.php';
require_once __DIR__ . '/test-acilis.php';
require_once __DIR__ . '/test-ceviri.php';
require_once __DIR__ . '/test-calisma-saatleri.php';
require_once __DIR__ . '/test-restoran-menu.php';
require_once __DIR__ . '/test-restoran-secenek.php';
require_once __DIR__ . '/test-yorum-istatistik.php';
require_once __DIR__ . '/test-analiz-schema.php';
require_once __DIR__ . '/test-hfb.php';
require_once __DIR__ . '/test-banner.php';
require_once __DIR__ . '/test-hfb-onbellek.php';
require_once __DIR__ . '/test-iletisim.php';
require_once __DIR__ . '/test-menu-muhendisligi.php';
require_once __DIR__ . '/test-servis-paneli.php';
require_once __DIR__ . '/test-login.php';

if ( empty( $GLOBALS['qrms_failures'] ) ) {
	echo "\033[32mTüm testler geçti\033[0m (" . $GLOBALS['qrms_assertions'] . " doğrulama)\n\n";
	exit( 0 );
}

echo "\033[31m" . count( $GLOBALS['qrms_failures'] ) . " test başarısız\033[0m\n\n";
exit( 1 );
