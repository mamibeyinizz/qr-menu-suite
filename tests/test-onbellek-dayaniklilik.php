<?php
/**
 * Canlı test bulgularının düzeltmeleri: tam sayfa önbelleği dayanıklılığı,
 * Servis Paneli masaüstü kanbanı, chatbot (mükerrer sipariş, hesap yedeği,
 * menü bağlamı), çalışma saati sipariş engeli, QR PDF sıkıştırma, Servis
 * Paneli fiyat/toplam.
 *
 * Yükleyen: tests/test-suite.php — doğrudan çalıştırmayın.
 *
 * @package QR_Menu_Suite
 */

require_once QRMS_PLUGIN_DIR . 'modules/_qmo-ortak/taze-anahtar.php';
require_once QRMS_PLUGIN_DIR . 'modules/qr-calisma-saatleri/includes/hours.php';
require_once QRMS_PLUGIN_DIR . 'modules/qr-chatbot/includes/ajax-chat.php';

echo "\nÖnbellek dayanıklılığı ve canlı test düzeltmeleri\n";

qrms_test(
	'?masa= isteği tam sayfa önbelleğinden çıkarılır (DONOTCACHEPAGE + nocache)',
	function () {
		$eski_get = $_GET;
		$GLOBALS['qrms_test']['nocache_headers'] = false;

		$_GET = array();
		qmo_masa_istegi_onbellege_alinmasin();
		qrms_assert_false( ! empty( $GLOBALS['qrms_test']['nocache_headers'] ), 'masasız istek dokunulmaz' );

		$_GET = array( 'masa' => 'masa-1' );
		qmo_masa_istegi_onbellege_alinmasin();
		qrms_assert_true( defined( 'DONOTCACHEPAGE' ) && DONOTCACHEPAGE, 'DONOTCACHEPAGE tanımlı' );

		$_GET = $eski_get;

		$php = file_get_contents( QRMS_PLUGIN_DIR . 'modules/_qmo-ortak/taze-anahtar.php' );
		qrms_assert_contains( "do_action( 'litespeed_control_set_nocache'", $php, 'LiteSpeed API çağrısı' );
		qrms_assert_contains( 'X-LiteSpeed-Cache-Control: no-cache', $php, 'sunucu düzeyi LSCache başlığı' );
		qrms_assert_contains( "add_action( 'init', 'qmo_masa_istegi_onbellege_alinmasin', 0 )", $php, 'oturum init (1) öncesi' );
	}
);

qrms_test(
	'tazeleme ucu güncel nonce\'ları döndürür, nopriv kayıtlıdır ve numaralandırma sayacına tabidir',
	function () {
		$anahtar = qmo_taze_anahtarlar();
		foreach ( array( 'qmo', 'rest', 'rma' ) as $k ) {
			qrms_assert_true( ! empty( $anahtar[ $k ] ), $k . ' nonce var' );
		}

		$php = file_get_contents( QRMS_PLUGIN_DIR . 'modules/_qmo-ortak/taze-anahtar.php' );
		qrms_assert_contains( "add_action( 'wp_ajax_nopriv_qmo_taze_anahtar'", $php, 'ziyaretçi ucu' );
		qrms_assert_contains( "'qmo_masa_deneme_' . qmo_ip_hash()", $php, 'masa_dogrulama ile aynı sayaç' );
		qrms_assert_contains( 'qmo_masa_gecerli_mi( $masa )', $php, 'yalnızca kayıtlı masa' );
		qrms_assert_contains( 'nocache_headers();', $php, 'yanıt önbelleğe girmez' );

		$ortak = file_get_contents( QRMS_PLUGIN_DIR . 'modules/_qmo-ortak/ortak.php' );
		qrms_assert_contains( "require_once __DIR__ . '/taze-anahtar.php';", $ortak, 'ortak yükleyici' );
	}
);

qrms_test(
	'taze-anahtar.js yalnızca bayat sayfada istek atar ve tüketiciler qmoTazeHazir bekler',
	function () {
		$js = file_get_contents( QRMS_PLUGIN_DIR . 'modules/_qmo-ortak/assets/js/taze-anahtar.js' );
		qrms_assert_contains( "yas > esik || ( '' !== masa && yas > 30 )", $js, 'yaş eşiği' );
		qrms_assert_contains( 'w.qmoTazeHazir = w.Promise ? Promise.resolve( false )', $js, 'taze sayfada beklemesiz' );
		foreach ( array( 'qmoData.nonce', 'qmoSepet.restNonce', 'w.NONCE = v.rma', 'RMA_MODAL_CFG.nonce', 'qrmsAnalitikOnyuzCfg.nonce', "'qrm_cf_nonce'", "'qrm_ts'" ) as $hedef ) {
			qrms_assert_contains( $hedef, $js, 'yazılır: ' . $hedef );
		}

		$tuketiciler = array(
			'modules/qr-chatbot/assets/js/sepet.js',
			'modules/qr-chatbot/assets/js/qmo-chatbot-shared.js',
			'modules/qr-chatbot/assets/js/chatbot.js',
			'modules/restoran-menu/assets/js/rma-frontend.js',
			'modules/restoran-menu/assets/js/rma-detail-modal.js',
			'modules/qr-analiz/assets/js/analitik-onyuz.js',
			'modules/yorum-feedback/includes/frontend/form-script.php',
			'modules/yorum-feedback/includes/forms/render.php',
		);
		foreach ( $tuketiciler as $dosya ) {
			qrms_assert_contains( 'qmoTazeHazir', file_get_contents( QRMS_PLUGIN_DIR . $dosya ), 'bekler: ' . $dosya );
		}
	}
);

qrms_test(
	'Servis Paneli masaüstü kuralı mobil gizleme kuralından zayıf değildir; 5 sütun',
	function () {
		$css = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-servis-paneli/assets/css/panel.css' );
		// Mobil gizleme: .qrms-sp-kanban[data-aktif="x"] .qrms-sp-sutun:not([data-durum="x"]) = 0,4,0.
		qrms_assert_contains( '.qrms-sp-kanban[data-aktif] .qrms-sp-sutun[data-durum] {', $css, 'masaüstü göster 0,4,0' );
		qrms_assert_contains( 'grid-template-columns: repeat( 5, minmax( 0, 1fr ) );', $css, 'beş durum sütunu' );
		qrms_assert_true(
			strpos( $css, '.qrms-sp-kanban[data-aktif] .qrms-sp-sutun[data-durum] {' ) > strpos( $css, '.qrms-sp-kanban[data-aktif="iptal"] .qrms-sp-sutun:not([data-durum="iptal"])' ),
			'eşit özgüllükte sonra gelen kazanır'
		);
		qrms_assert_contains( '.qrms-wrap.qrms-sp {', $css, '640px genel sınırı kaldırılır' );
	}
);

qrms_test(
	'çalışma saati sipariş engeli varsayılan kapalı; açıkken yalnız kapalı saatte keser',
	function () {
		delete_option( QRMS_CS_ENGEL_OPTION );
		qrms_assert_false( qrms_cs_siparis_engeli_acik(), 'varsayılan kapalı' );
		qrms_assert_same( null, qrms_cs_siparis_filtresi( null, array() ), 'kapalıyken dokunmaz' );

		$tum_kapali = array();
		foreach ( qrms_cs_day_keys() as $g ) {
			$tum_kapali[ $g ] = array( 'closed' => true, 'open' => '09:00', 'close' => '22:00' );
		}
		update_option( QRMS_CS_OPTION, $tum_kapali );
		update_option( QRMS_CS_ENGEL_OPTION, '1' );

		$engel = qrms_cs_siparis_filtresi( null, array() );
		qrms_assert_true( is_array( $engel ) && 'closed' === $engel['reason'], 'kapalı saatte engel' );

		$onceki = array( 'mesaj' => 'Tükendi', 'reason' => 'sold_out' );
		qrms_assert_same( $onceki, qrms_cs_siparis_filtresi( $onceki, array() ), 'önceki engele dokunmaz' );

		$acik = array();
		foreach ( qrms_cs_day_keys() as $g ) {
			$acik[ $g ] = array( 'closed' => false, 'open' => '00:00', 'close' => '00:00' ); // 24 saat
		}
		update_option( QRMS_CS_OPTION, $acik );
		qrms_assert_same( null, qrms_cs_siparis_filtresi( null, array() ), 'açık saatte geçer' );

		$talimat = qmo_chat_calisma_saati_talimati();
		qrms_assert_contains( 'ÇALIŞMA SAATLERİ', $talimat, 'asistan saatleri bilir' );
		qrms_assert_contains( '24 saat açık', $talimat, 'eşit saat = 24 saat' );

		delete_option( QRMS_CS_ENGEL_OPTION );
		delete_option( QRMS_CS_OPTION );
	}
);

qrms_test(
	'asistan menü satırı rozet/alerjen/kaloriyi taşır, boş alan eklemez, servis dışı ürün önerilmez',
	function () {
		$sade = qmo_chat_oneri_json_satir( array( 'urunAdi' => 'Ayran', 'fiyat' => '40' ) );
		qrms_assert_same( array( 'kategori', 'urunAdi', 'aciklama', 'fiyat' ), array_keys( $sade ), 'eski şekil korunur' );

		$dolu = qmo_chat_oneri_json_satir(
			array(
				'urunAdi'    => 'Filtre Kahve',
				'ozellikler' => array( 'Vegan', 'Glütensiz' ),
				'alerjenler' => array( 'Süt' ),
				'kalori'     => '10',
			)
		);
		qrms_assert_same( array( 'Vegan', 'Glütensiz' ), $dolu['ozellikler'], 'diyet rozetleri' );
		qrms_assert_same( array( 'Süt' ), $dolu['alerjenler'], 'alerjenler' );
		qrms_assert_same( '10', $dolu['kalori'], 'kalori' );

		qrms_assert_false( qmo_chat_oneri_havuza_uygun_mu( 1, array( 'servisDisi' => true ) ), 'servis dışı önerilmez' );
		qrms_assert_true( qmo_chat_oneri_havuza_uygun_mu( 1, array() ), 'normal ürün önerilir' );

		$prompt = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-chatbot/includes/ajax-chat.php' );
		qrms_assert_contains( '. qmo_chat_calisma_saati_talimati()', $prompt, 'saatler prompt\'a eklenir' );
	}
);

qrms_test(
	'chatbot: mükerrer sipariş kısa pencerede tekrar gönderilmez; hesap niyetinde onay butonu',
	function () {
		$js = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-chatbot/assets/js/chatbot.js' );
		qrms_assert_contains( 'var MUKERRER_PENCERE_MS = 2 * 60 * 1000;', $js, 'pencere' );
		qrms_assert_contains( 'onceki.imza === imza', $js, 'aynı imza kontrolü' );
		qrms_assert_contains( 'sonBotSiparisYaz( null );', $js, 'hata sonrası tekrar denenebilir' );
		qrms_assert_contains( 'var hesapButonu = ! hesapCagrildi && HESAP_NIYETI.test(', $js, 'etiket yoksa buton' );
		qrms_assert_contains( "istek( { action: 'hesap_iste' } )", $js, 'buton gerçek çağrı yapar' );

		$i18n = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-chatbot/chatbot.php' );
		foreach ( array( "'hesapSorusu'", "'hesapIste'", "'hesapIstendi'", "'siparisZatenIletildi'" ) as $k ) {
			qrms_assert_contains( $k, $i18n, 'çevrilebilir metin ' . $k );
		}
	}
);

qrms_test(
	'QR PDF: jsPDF sıkıştırma açık ve PNG FAST ile gömülür',
	function () {
		$js = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-masa/assets/js/admin-masalar.js' );
		qrms_assert_contains( "doc.addImage( dataUri, 'PNG', 35, 60, 140, 140, undefined, 'FAST' );", $js, 'FAST' );
		qrms_assert_same( 2, substr_count( $js, 'new window.jspdf.jsPDF( { compress: true } )' ), 'tekli + toplu' );
		qrms_assert_false( strpos( $js, 'new window.jspdf.jsPDF()' ), 'sıkıştırmasız belge kalmadı' );
	}
);

qrms_test(
	'sipariş kalemine sunucu birim fiyatı yazılır, panel yalnız tam fiyatlı kayıtta toplam gösterir',
	function () {
		$php = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-chatbot/rest-order.php' );
		qrms_assert_contains( "\$alanlar['birimFiyat'] = array( 'doubleValue'", $php, 'Firestore alanı' );
		qrms_assert_contains( 'qmo_siparis_kalem_unit_price(', $php, 'istemci değil sunucu fiyatı' );

		$veri = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-servis-paneli/includes/class-qrms-sp-veri.php' );
		qrms_assert_contains( "'fiyat' => isset( \$kalem['birimFiyat'] )", $veri, 'panel verisi' );

		$js = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-servis-paneli/assets/js/panel.js' );
		qrms_assert_contains( 'if ( fiyatliMi && toplam > 0 )', $js, 'eksik fiyatta toplam yok' );
	}
);
