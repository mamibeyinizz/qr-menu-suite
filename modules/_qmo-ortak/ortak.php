<?php
/**
 * Modüllerin ortak çalışma zamanı bağımlılıkları.
 *
 * Taşınan modüllerin (qr-masa, qr-chatbot, qr-masa-oturum-guvenligi,
 * qr-analiz) ihtiyaç duyduğu yardımcılar eski birleşik eklentide tek bir
 * yerde duruyordu. Modül başına kopyalamak yerine burada bir kez tutulur;
 * ihtiyacı olan her modülün module.php dosyası bu dosyayı require_once eder.
 * (restoran-menu buna bağımlı değildir; kendi RMA_/QMO_ ad alanıyla tamamen
 * kendi kendine yeterlidir ve ortak.php'yi yüklemez.)
 *
 * class-qmo-oturum.php da buradadır: masa oturumu üç modülün de ortak
 * zeminidir (helpers.php'deki qmo_oturum() / qmo_oturum_zorla() doğrudan
 * QMO_Oturum ve qmo_cookie_yaz() üzerine kuruludur), oysa modüller birbirinden
 * bağımsız lisanslanır. Sınıf yalnızca qr-masa-oturum-guvenligi altında
 * dursaydı, o modül lisanslı değilken chatbot her render'da fatal verirdi.
 * Kilit ekranı ve sayfa kilidi (masa-dogrulama.php) modülünde kalır.
 *
 * class-qmo-firestore.php da aynı gerekçeyle buradadır: qr-analiz'in REST ucu
 * çağıranın kimliğini ve rolünü QMO_Firestore üzerinden doğrular, ama sınıfı
 * henüz taşınmamış dört dosya daha kullanır (rest-order, ajax-waiter-bill,
 * rest-create-user, admin/settings-page). Tek modülün altında dursaydı o
 * modül lisanslı değilken diğerleri fatal verirdi.
 *
 * Dosya içerikleri eski eklentiden AYNEN taşındı. Hepsi function_exists() /
 * defined() guard'lı olduğundan tekrarlı yükleme ve eski qr-menu-official
 * eklentisiyle yan yana çalışma sorunsuzdur.
 *
 * @package QR_Menu_Suite
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/class-qmo-oturum.php';
require_once __DIR__ . '/class-qmo-firestore.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/color-defaults.php';
require_once __DIR__ . '/assets.php';
