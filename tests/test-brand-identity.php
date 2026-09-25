<?php
/**
 * Merkezi restoran markası testleri.
 *
 * @package QR_Menu_Suite
 */

echo "\nRestoran markası (merkezi)\n";

qrms_test(
	'varsayılan depo ve sanitize',
	function () {
		delete_option( QRMS_Brand_Identity::OPTION );

		$def = QRMS_Brand_Identity::get_settings();
		qrms_assert_same( 0, $def['logo'], 'logo varsayılan' );
		qrms_assert_same( '', $def['ad'], 'ad varsayılan' );

		$temiz = QRMS_Brand_Identity::sanitize_settings(
			array(
				'logo'   => '42',
				'ad'     => '  Deneme Restoran  ',
				'alt_ad' => '<b>Alt</b>',
			)
		);

		qrms_assert_same( 42, $temiz['logo'], 'logo absint' );
		qrms_assert_same( 'Deneme Restoran', $temiz['ad'], 'ad temiz' );
		qrms_assert_same( 'Alt', $temiz['alt_ad'], 'alt_ad temiz' );
	}
);

qrms_test(
	'resolve_name ve resolve_short_name merkezi ve legacy önceliği',
	function () {
		update_option(
			QRMS_Brand_Identity::OPTION,
			array(
				'ad'     => 'Merkezi Ad',
				'alt_ad' => 'Merkezi Alt',
			)
		);

		qrms_assert_same( 'Merkezi Ad', QRMS_Brand_Identity::resolve_name( 'Legacy Üst' ), 'A: merkezi ad' );
		qrms_assert_same( 'Merkezi Alt', QRMS_Brand_Identity::resolve_short_name( 'Legacy Alt' ), 'A: merkezi alt' );

		update_option(
			QRMS_Brand_Identity::OPTION,
			array(
				'ad'     => 'Sadece Ad',
				'alt_ad' => '',
			)
		);
		qrms_assert_same( 'Sadece Ad', QRMS_Brand_Identity::resolve_name( 'Legacy Üst' ), 'B: merkezi ad only' );
		qrms_assert_same( 'Legacy Alt', QRMS_Brand_Identity::resolve_short_name( 'Legacy Alt' ), 'B: legacy alt fallback' );

		update_option(
			QRMS_Brand_Identity::OPTION,
			array(
				'ad'     => '',
				'alt_ad' => 'Sadece Alt',
			)
		);
		qrms_assert_same( 'Legacy Üst', QRMS_Brand_Identity::resolve_name( 'Legacy Üst' ), 'C: legacy üst' );
		qrms_assert_same( 'Sadece Alt', QRMS_Brand_Identity::resolve_short_name( 'Legacy Alt' ), 'C: merkezi alt only' );

		delete_option( QRMS_Brand_Identity::OPTION );
		qrms_assert_same( 'Legacy Üst', QRMS_Brand_Identity::resolve_name( 'Legacy Üst' ), 'E: merkezi boş legacy' );
		qrms_assert_same( 'Legacy Alt', QRMS_Brand_Identity::resolve_short_name( 'Legacy Alt' ), 'E: merkezi boş legacy alt' );
	}
);

qrms_test(
	'resolve_logo_id merkezi ve legacy önceliği',
	function () {
		update_option( QRMS_Brand_Identity::OPTION, array( 'logo' => 100 ) );
		qrms_assert_same( 100, QRMS_Brand_Identity::resolve_logo_id( 200 ), 'geçerli merkezi' );

		delete_option( QRMS_Brand_Identity::OPTION );
		qrms_assert_same( 200, QRMS_Brand_Identity::resolve_logo_id( 200 ), 'merkezi yok' );

		update_option( QRMS_Brand_Identity::OPTION, array( 'logo' => 999 ) );
		$GLOBALS['qrms_test']['missing_attachment_ids'][999] = true;
		qrms_assert_same( 200, QRMS_Brand_Identity::resolve_logo_id( 200 ), 'geçersiz merkezi legacy' );
		unset( $GLOBALS['qrms_test']['missing_attachment_ids'][999] );
	}
);

qrms_test(
	'helper logo url ve ad',
	function () {
		update_option(
			QRMS_Brand_Identity::OPTION,
			array(
				'logo'   => 7,
				'ad'     => 'Sahil Cafe',
				'alt_ad' => 'Since 1990',
			)
		);

		qrms_assert_same( 7, QRMS_Brand_Identity::get_logo_id(), 'logo id' );
		qrms_assert_contains( '/7-medium.jpg', QRMS_Brand_Identity::get_logo_url(), 'logo url' );
		qrms_assert_same( 'Sahil Cafe', QRMS_Brand_Identity::get_name(), 'ad' );
		qrms_assert_same( 'Since 1990', QRMS_Brand_Identity::get_short_name(), 'alt ad' );
		qrms_assert_same( 'Sahil Cafe', QRMS_Brand_Identity::get_logo_alt_text(), 'alt metin addan' );
	}
);

qrms_test(
	'shell marka: logo yoksa QR MENU OFFICIAL yedek',
	function () {
		delete_option( QRMS_Brand_Identity::OPTION );

		ob_start();
		QRMS_Brand_Identity::render_shell_brand();
		$html = ob_get_clean();

		qrms_assert_contains( 'qrms-shell__brand-mark', $html, 'gradient işaret' );
		qrms_assert_contains( 'QR MENU', $html, 'yedek üst satır' );
		qrms_assert_contains( 'OFFICIAL', $html, 'yedek alt satır' );
		qrms_assert_false( strpos( $html, 'qrms-shell__brand--has-logo' ), 'logo modu değil' );
	}
);

qrms_test(
	'shell marka: logo varsa metin yedek gösterilmez',
	function () {
		update_option(
			QRMS_Brand_Identity::OPTION,
			array(
				'logo' => 9,
				'ad'   => '',
				'alt_ad' => '',
			)
		);

		ob_start();
		QRMS_Brand_Identity::render_shell_brand();
		$html = ob_get_clean();

		qrms_assert_contains( 'qrms-shell__brand--has-logo', $html, 'logo modu' );
		qrms_assert_contains( '/9-medium.jpg', $html, 'logo src' );
		qrms_assert_false( strpos( $html, 'QR MENU' ), 'hard-coded yedek yok' );
		qrms_assert_false( strpos( $html, 'OFFICIAL' ), 'hard-coded alt yok' );
	}
);

qrms_test(
	'shell marka: logo + ad',
	function () {
		update_option(
			QRMS_Brand_Identity::OPTION,
			array(
				'logo'   => 3,
				'ad'     => 'Bistro X',
				'alt_ad' => 'Fine dining',
			)
		);

		ob_start();
		QRMS_Brand_Identity::render_shell_brand();
		$html = ob_get_clean();

		qrms_assert_contains( 'Bistro X', $html, 'marka adı' );
		qrms_assert_contains( 'Fine dining', $html, 'alt satır' );
		qrms_assert_false( strpos( $html, 'QR MENU' ), 'yedek üst satır yok' );
	}
);

qrms_test(
	'shell marka: logo yok + yalnızca ad (OFFICIAL yedek gösterilmez)',
	function () {
		update_option(
			QRMS_Brand_Identity::OPTION,
			array(
				'logo'   => 0,
				'ad'     => 'Lezzet Sokağı',
				'alt_ad' => '',
			)
		);

		ob_start();
		QRMS_Brand_Identity::render_shell_brand();
		$html = ob_get_clean();

		qrms_assert_contains( 'Lezzet Sokağı', $html, 'marka adı' );
		qrms_assert_false( strpos( $html, 'OFFICIAL' ), 'boş alt_ad için OFFICIAL yedek yok' );
		qrms_assert_false( strpos( $html, 'qrms-shell__brand-sub' ), 'alt satır öğesi basılmaz' );
	}
);

qrms_test(
	'shell marka: logo yok + yalnızca alt_ad',
	function () {
		update_option(
			QRMS_Brand_Identity::OPTION,
			array(
				'logo'   => 0,
				'ad'     => '',
				'alt_ad' => 'Fine Dining',
			)
		);

		ob_start();
		QRMS_Brand_Identity::render_shell_brand();
		$html = ob_get_clean();

		qrms_assert_contains( 'Fine Dining', $html, 'alt satır' );
		qrms_assert_false( strpos( $html, 'QR MENU' ), 'boş ad için QR MENU yedek yok' );
		qrms_assert_false( strpos( $html, 'qrms-shell__brand-name' ), 'üst satır öğesi basılmaz' );
	}
);

qrms_test(
	'shell marka: logo yok + boş ad ve alt_ad tam yedek',
	function () {
		update_option(
			QRMS_Brand_Identity::OPTION,
			array(
				'logo'   => 0,
				'ad'     => '',
				'alt_ad' => '',
			)
		);

		ob_start();
		QRMS_Brand_Identity::render_shell_brand();
		$html = ob_get_clean();

		qrms_assert_contains( 'QR MENU', $html, 'yedek üst' );
		qrms_assert_contains( 'OFFICIAL', $html, 'yedek alt' );
	}
);

qrms_test(
	'hesap dropdown rol rengi yalnızca menü meta içinde koyu',
	function () {
		$css = file_get_contents( QRMS_PLUGIN_DIR . 'assets/css/admin-shell.css' );

		qrms_assert_contains(
			'.qrms-shell__account-menu-meta .qrms-shell__account-role',
			$css,
			'scoped selector'
		);
		qrms_assert_true(
			(bool) preg_match(
				'/\.qrms-shell__account-menu-meta \.qrms-shell__account-role\s*\{[^}]*color:\s*#1a1a1a/i',
				$css
			),
			'dropdown rol rengi #1a1a1a'
		);
	}
);

qrms_test(
	'ayar sekmesi kayıtlı',
	function () {
		$tabs = QRMS_Admin::get_settings_tabs();
		qrms_assert_true( isset( $tabs['marka'] ), 'marka sekmesi' );
		qrms_assert_same( 'Restoran Markası', $tabs['marka']['label'], 'sekme etiketi' );
	}
);

qrms_test(
	'is_settings_tab marka sekmesini tanır',
	function () {
		$_GET['page'] = QRMS_Admin::SETTINGS_SLUG;
		$_GET['tab']  = 'marka';

		qrms_assert_true( QRMS_Brand_Identity::is_settings_tab(), 'marka sekmesi aktif' );

		$_GET['tab'] = 'genel';
		qrms_assert_false( QRMS_Brand_Identity::is_settings_tab(), 'genel sekme değil' );

		unset( $_GET['page'], $_GET['tab'] );
	}
);
