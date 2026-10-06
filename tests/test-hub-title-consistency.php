<?php
/**
 * Menü Yönetimi hub kartı ↔ sayfa başlığı tutarlılığı.
 *
 * Yükleyen: tests/test-suite.php — doğrudan çalıştırmayın.
 *
 * @package QR_Menu_Suite
 */

echo "\nMenü Yönetimi — hub kartı / sayfa başlığı\n";

qrms_test(
	'hub kartı, alt sayfa başlığı ve slug aynı özelliği adlandırır',
	function () {
		$php = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/trait-admin-pages.php' );

		$eslesmeler = array(
			'qrms-rm-gorunum'         => 'Menü Görünümü',
			'qrms-rm-one-cikanlar'    => 'Öne Çıkanlar',
			'qrms-rm-kampanya'        => 'Kampanyalar',
			'qrms-rm-kampanya-banner' => 'Kampanya Görselleri',
			'qrms-rm-secenekler'      => 'Ekstralar & Ürün Rozetleri',
			'qrms-rm-diger'           => 'Diğer Ayarlar',
			'qrms-rm-urunum-yok'      => 'Tükenen Ürünler',
		);

		foreach ( $eslesmeler as $slug => $baslik ) {
			qrms_assert_contains( "'" . $slug . "'", $php, $slug . ' kayıtlı' );
			qrms_assert_contains( "'title'      => '" . $baslik . "'", $php, $slug . ' sayfa başlığı' );
		}

		qrms_assert_contains( "__( 'Tükenen Ürünler', 'qrms' )", $php, 'hub kartı Tükenen Ürünler' );
		qrms_assert_contains( "__( 'Diğer Ayarlar', 'qrms' )", $php, 'hub kartı Diğer Ayarlar' );
		qrms_assert_contains( "__( 'Ekstralar & Ürün Rozetleri', 'qrms' )", $php, 'hub kartı ekstralar' );
		qrms_assert_false( false !== strpos( $php, 'Ürün Durumu' ), 'eski hub adı Ürün Durumu yok' );
		qrms_assert_false( false !== strpos( $php, 'Menü Araçları' ), 'eski hub adı Menü Araçları yok' );
		qrms_assert_false( false !== strpos( $php, 'Ekstralar & Etiketler' ), 'eski hub adı Etiketler yok' );
		qrms_assert_false( false !== strpos( $php, '&larr; Restoran Menü' ), 'suite dışı geri etiketi Menü Yönetimi' );
		qrms_assert_contains( '&larr; Menü Yönetimi', $php, 'suite dışı geri Menü Yönetimi' );

		qrms_assert_contains( "admin_url( 'edit.php?post_type=rma_menu_item' )", $php, 'Ürünler CPT rotası' );
		qrms_assert_contains( "admin_url( 'post-new.php?post_type=rma_menu_item' )", $php, 'Ürün Ekle rotası' );
		qrms_assert_contains( 'taxonomy=rma_category', $php, 'Kategoriler rotası' );
		qrms_assert_contains( 'taxonomy=rma_allergen', $php, 'Alerjenler rotası' );
		qrms_assert_contains( 'taxonomy=rma_ingredient', $php, 'Malzemeler rotası' );
	}
);

qrms_test(
	'Menü Yönetimi alt sayfaları hub\'a Türkçe geri döner',
	function () {
		$slugs = array(
			'qrms-rm-gorunum',
			'qrms-rm-kampanya',
			'qrms-rm-secenekler',
			'qrms-rm-diger',
			'qrms-rm-urunum-yok',
			'qrms-rm-one-cikanlar',
			'qrms-rm-kampanya-banner',
		);

		foreach ( $slugs as $slug ) {
			qrms_assert_same(
				'Menü Yönetimi\'ne Dön',
				QRMS_Admin::resolve_subpage_back_label( 'restoran-menu', $slug ),
				$slug . ' geri etiketi'
			);
			qrms_assert_same(
				QRMS_Admin::get_module_page_url( 'restoran-menu' ),
				QRMS_Admin::resolve_subpage_back_url( 'restoran-menu', $slug ),
				$slug . ' geri URL'
			);
		}

		qrms_assert_same( 'Menü Yönetimi', QRMS_Helpers::get_module_name( 'restoran-menu' ), 'modül görünen adı' );
	}
);

qrms_test(
	'shell breadcrumb ve hybrid geri ürün/kategori ekranlarında Menü Yönetimi der',
	function () {
		$shell = file_get_contents( QRMS_PLUGIN_DIR . 'includes/class-admin-shell.php' );
		$css   = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/assets/css/hub.css' );

		qrms_assert_false(
			(bool) preg_match( "/__\(\s*'Restoran Menü',\s*'qrms'\s*\)/", $shell ),
			'shell kullanıcı metninde Restoran Menü kalmadı'
		);
		qrms_assert_contains( "__( 'Ürün Ekle', 'qrms' )", $shell, 'ürün ekle shell başlığı hub kartıyla aynı' );
		qrms_assert_contains( "__( 'Kategoriler', 'qrms' )", $shell, 'kategori shell başlığı hub kartıyla aynı' );
		qrms_assert_contains( "__( 'Kampanya Görselleri', 'qrms' )", $shell, 'banner CPT başlığı hub kartıyla aynı' );
		qrms_assert_contains( 'is_menu_item_list_screen() || self::is_menu_item_edit_screen()', $shell, 'ürün listesi/düzenleme geri bağlantısı' );
		qrms_assert_contains( "__( 'Menü Yönetimi\'ne Dön', 'qrms' )", $shell, 'hybrid geri Menü Yönetimi' );
		qrms_assert_contains( 'overflow-wrap: anywhere', $css, 'mobil kart başlığı taşmaz' );

		qrms_assert_contains( "'qrms-rm-gorunum'", file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/trait-admin-pages.php' ), 'görünüm slug korunur' );
		qrms_assert_contains( "'qrms-rm-kampanya'", file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/trait-admin-pages.php' ), 'kampanya slug korunur' );
	}
);

qrms_test(
	'kampanya listesi fiyat ekranıdır, hub kartı Kampanyalar kalır',
	function () {
		$kampanya = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/trait-kampanya-admin.php' );
		$sayfa    = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/trait-admin-pages.php' );

		qrms_assert_contains( "'hub_title'  => 'Kampanyalar'", $sayfa, 'hub kısa adı Kampanyalar' );
		qrms_assert_contains( "'title'      => 'Kampanyalar'", $sayfa, 'WP sayfa başlığı Kampanyalar' );
		qrms_assert_contains( "'Fiyat Kampanyaları'", $kampanya, 'liste H1 fiyat kampanyasını ayırır' );
	}
);
