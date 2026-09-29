<?php
/**
 * qrms metin alanı — init öncesi çeviri kullanımı regresyon testleri.
 *
 * Yükleyen: tests/test-suite.php — doğrudan çalıştırmayın.
 *
 * @package QR_Menu_Suite
 */

require_once QRMS_PLUGIN_DIR . 'modules/qr-analiz/class-qrms-siparis-iptal-uzlastirma.php';
require_once QRMS_PLUGIN_DIR . 'modules/qr-servis-paneli/module.php';

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

qrms_test(
	'servis rolü: plugins_loaded sırasında qrms çevirisi yok, init sonrası rol kurulur',
	function () {
		$modul = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-servis-paneli/module.php' );
		$rol   = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-servis-paneli/includes/class-qrms-sp-rol.php' );

		qrms_assert_contains(
			"add_action( 'init', array( 'QRMS_SP_Rol', 'kur' ), 1 )",
			$modul,
			'rol kurulumu init 1 ile kayıtlı'
		);
		qrms_assert_false(
			(bool) preg_match( '/^\s*QRMS_SP_Rol::kur\s*\(\s*\)\s*;/m', $modul ),
			'modül init doğrudan kur() çağırmaz'
		);
		qrms_assert_contains(
			"__( 'Servis Personeli', 'qrms' )",
			$rol,
			'rol adı çevirisi korunur'
		);

		delete_option( QRMS_SP_Rol::OPTION_SURUM );
		$GLOBALS['qrms_test']['roles'] = array(
			'administrator' => array(
				'name' => 'Administrator',
				'caps' => array(),
			),
			'editor'        => array(
				'name' => 'Editor',
				'caps' => array(),
			),
		);
		$GLOBALS['qrms_test']['translate_calls'] = array();
		$GLOBALS['qrms_test']['track_translate'] = true;
		$GLOBALS['qrms_test']['current_filter']    = 'plugins_loaded';

		qrms_module_qr_servis_paneli_init();

		foreach ( $GLOBALS['qrms_test']['translate_calls'] as $cagri ) {
			qrms_assert_false(
				'qrms' === $cagri['domain'],
				'plugins_loaded sırasında qrms çevirisi: ' . $cagri['text']
			);
		}

		qrms_assert_false(
			isset( $GLOBALS['qrms_test']['roles'][ QRMS_SP_Rol::ROL ] ),
			'rol henüz oluşturulmadı'
		);

		$GLOBALS['qrms_test']['current_filter'] = 'init';
		do_action( 'init' );

		$GLOBALS['qrms_test']['track_translate'] = false;

		qrms_assert_true(
			isset( $GLOBALS['qrms_test']['roles'][ QRMS_SP_Rol::ROL ] ),
			'servis rolü oluşturuldu'
		);
		qrms_assert_same(
			'Servis Personeli',
			$GLOBALS['qrms_test']['roles'][ QRMS_SP_Rol::ROL ]['name'],
			'rol görünen adı'
		);
		qrms_assert_true(
			! empty( $GLOBALS['qrms_test']['roles'][ QRMS_SP_Rol::ROL ]['caps']['read'] ),
			'read yeteneği'
		);
		qrms_assert_true(
			! empty( $GLOBALS['qrms_test']['roles'][ QRMS_SP_Rol::ROL ]['caps'][ QRMS_SP_Rol::YETENEK ] ),
			'servis panel yeteneği'
		);
		qrms_assert_true(
			! empty( $GLOBALS['qrms_test']['roles']['administrator']['caps'][ QRMS_SP_Rol::YETENEK ] ),
			'administrator yeteneği güncellendi'
		);
		qrms_assert_same(
			(int) QRMS_SP_Rol::SURUM,
			(int) get_option( QRMS_SP_Rol::OPTION_SURUM ),
			'sürüm option yazıldı'
		);
	}
);
