<?php
/**
 * Servis paneli testleri.
 *
 * @package QR_Menu_Suite
 */

echo "\nServis Paneli\n";

require_once QRMS_PLUGIN_DIR . 'modules/_qmo-ortak/class-qmo-firestore.php';
require_once QRMS_PLUGIN_DIR . 'modules/qr-servis-paneli/includes/class-qrms-sp-veri.php';
require_once QRMS_PLUGIN_DIR . 'modules/qr-servis-paneli/includes/class-qrms-sp-rol.php';

qrms_test(
	'Firestore yanıtı normalize edilir',
	function () {
		$doc = array(
			'name'   => 'projects/x/databases/(default)/documents/calls/abc123',
			'fields' => array(
				'masaNo'    => array( 'stringValue' => 'masa-1' ),
				'tip'       => array( 'stringValue' => 'siparis' ),
				'durum'     => array( 'stringValue' => 'bekliyor' ),
				'createdAt' => array( 'timestampValue' => '2026-03-01T10:00:00Z' ),
				'items'     => array(
					'arrayValue' => array(
						'values' => array(
							array(
								'mapValue' => array(
									'fields' => array(
										'urunAdi' => array( 'stringValue' => 'Çorba' ),
										'adet'    => array( 'integerValue' => '2' ),
									),
								),
							),
						),
					),
				),
			),
		);
		$n = QRMS_SP_Veri::normalize_doc( $doc );
		qrms_assert_same( 'abc123', $n['id'], 'id' );
		qrms_assert_same( 'masa-1', $n['masaNo'], 'masaNo' );
		qrms_assert_same( 'siparis', $n['tip'], 'tip' );
		qrms_assert_same( 'bekliyor', $n['durum'], 'durum varsayılan' );
		qrms_assert_same( 'Çorba', $n['items'][0]['urunAdi'], 'kalem adı' );
		qrms_assert_same( 2, $n['items'][0]['adet'], 'adet integer' );
	}
);

qrms_test(
	'eksik alanlar varsayılana düşer',
	function () {
		$n = QRMS_SP_Veri::normalize_doc( array( 'name' => 'projects/x/databases/(default)/documents/calls/xyz' ) );
		qrms_assert_same( 'xyz', $n['id'], 'id' );
		qrms_assert_same( '', $n['masaNo'], 'boş masa' );
		qrms_assert_same( 'siparis', $n['tip'], 'varsayılan tip' );
		qrms_assert_same( 'bekliyor', $n['durum'], 'varsayılan durum' );
		qrms_assert_same( array(), $n['items'], 'boş kalemler' );
	}
);

qrms_test(
	'geçersiz durum geçişi reddedilir',
	function () {
		qrms_assert_true( QRMS_SP_Veri::gecis_gecerli_mi( 'bekliyor', 'hazirlaniyor' ), 'ileri geçerli' );
		qrms_assert_false( QRMS_SP_Veri::gecis_gecerli_mi( 'bekliyor', 'tamamlandi' ), 'atlama geçersiz' );
		qrms_assert_false( QRMS_SP_Veri::gecis_gecerli_mi( 'tamamlandi', 'bekliyor' ), 'geri terminalden geçersiz' );
	}
);

qrms_test(
	'yeteneği olmayan kullanıcı panel yetkisiz',
	function () {
		$GLOBALS['qrms_test']['can'] = false;
		qrms_assert_false( QRMS_SP_Rol::yetkili_mi(), 'yetki yok' );
		$GLOBALS['qrms_test']['can'] = true;
	}
);

qrms_test(
	'modül slug kayıtlı',
	function () {
		qrms_assert_true( QRMS_Helpers::is_valid_module( 'qr-servis-paneli' ), 'slug geçerli' );
		qrms_assert_contains( 'dashicons-bell', QRMS_Helpers::get_module_icon( 'qr-servis-paneli' ), 'ikon' );
	}
);
