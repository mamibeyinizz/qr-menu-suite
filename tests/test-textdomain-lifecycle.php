<?php
/**
 * qrms metin alanı — init öncesi çeviri kullanımı regresyon testleri.
 *
 * Yükleyen: tests/test-suite.php — doğrudan çalıştırmayın.
 *
 * @package QR_Menu_Suite
 */

require_once QRMS_PLUGIN_DIR . 'modules/qr-analiz/class-qrms-siparis-iptal-uzlastirma.php';

echo "Metin alanı yaşam döngüsü\n";

qrms_test(
	'cron_araliklari: init öncesi wp_get_schedules yolunda qrms __() yok',
	function () {
		$kaynak = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-analiz/class-qrms-siparis-iptal-uzlastirma.php' );
		$bas    = strpos( $kaynak, 'function cron_araliklari' );
		qrms_assert_true( false !== $bas, 'cron_araliklari bulundu' );
		$govde = substr( $kaynak, $bas, 600 );

		qrms_assert_false(
			(bool) preg_match( "/__\s*\([^)]*,\s*'qrms'\s*\)/", $govde ),
			'cron_araliklari qrms çevirisi kullanmaz'
		);

		QRMS_Siparis_Iptal_Uzlastirma::init();
		$aralik = QRMS_Siparis_Iptal_Uzlastirma::cron_araliklari( array() );
		qrms_assert_same(
			QRMS_Siparis_Iptal_Uzlastirma::CRON_ARALIK_ETIKET,
			$aralik[ QRMS_Siparis_Iptal_Uzlastirma::CRON_ARALIK ]['display'],
			'display etiketi sabit'
		);
	}
);

qrms_test(
	'chatbot modül yüklemesi: wp_schedule_event cron_schedules filtresini tetikler',
	function () {
		$kaynak = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-chatbot/chatbot.php' );
		qrms_assert_contains( 'wp_schedule_event', $kaynak, 'chatbot dosya yüklemesinde planlama' );
	}
);
