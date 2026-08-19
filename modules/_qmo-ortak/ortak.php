<?php
/**
 * Modüllerin ortak çalışma zamanı bağımlılıkları.
 *
 * Taşınan üç modülün de (qr-masa, qr-chatbot, qr-masa-oturum-guvenligi)
 * ihtiyaç duyduğu yardımcılar eski birleşik eklentide tek bir yerde
 * duruyordu. Modül başına kopyalamak yerine burada bir kez tutulur; her
 * modülün module.php dosyası bu dosyayı require_once eder.
 *
 * class-qmo-oturum.php da buradadır: masa oturumu üç modülün de ortak
 * zeminidir (helpers.php'deki qmo_oturum() / qmo_oturum_zorla() doğrudan
 * QMO_Oturum ve qmo_cookie_yaz() üzerine kuruludur), oysa modüller birbirinden
 * bağımsız lisanslanır. Sınıf yalnızca qr-masa-oturum-guvenligi altında
 * dursaydı, o modül lisanslı değilken chatbot her render'da fatal verirdi.
 * Kilit ekranı ve sayfa kilidi (masa-dogrulama.php) modülünde kalır.
 *
 * Dosya içerikleri eski eklentiden AYNEN taşındı. Hepsi function_exists() /
 * defined() guard'lı olduğundan tekrarlı yükleme ve eski qr-menu-official
 * eklentisiyle yan yana çalışma sorunsuzdur.
 *
 * @package QR_Menu_Suite
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/class-qmo-oturum.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/color-defaults.php';
require_once __DIR__ . '/assets.php';
