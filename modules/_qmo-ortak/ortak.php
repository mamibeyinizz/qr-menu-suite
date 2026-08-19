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
 * class-qmo-firestore.php da aynı gerekçeyle buradadır: sınıfı iki modül
 * birden kullanır — qr-analiz (rest-analytics, rest-create-user: ID token
 * doğrulama + rol okuma) ve qr-chatbot (rest-order, ajax-order,
 * ajax-waiter-bill: çağrı ve sipariş yazma). Tek modülün altında dursaydı o
 * modül lisanslı değilken diğeri fatal verirdi.
 *
 * firebase-ayarlari.php (sınıfın okuduğu qmo_branch_id / qmo_firebase_sa /
 * qmo_ana_site option'larının kaydı ve ortak form bölümü) bilinçli olarak
 * BURADAN yüklenmez: yalnızca yönetim tarafında gerekir, bu yüzden onu
 * ihtiyacı olan modül kendi module.php'sindeki is_admin() dalında
 * require_once eder (qr-masa'nın masalar-sayfasi.php'yi yüklemesiyle aynı
 * düzen).
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
