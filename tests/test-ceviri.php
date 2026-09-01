<?php
/**
 * QR Çeviri (mobil) testleri.
 *
 * Yükleyen: tests/test-suite.php — doğrudan çalıştırmayın.
 *
 * @package QR_Menu_Suite
 */

echo "\nQR Çeviri (mobil)\n";

qrms_test(
	'yönetim stili yalnızca modülün kendi sayfasında ve dosya bazlı sürümle yüklenir',
	function () {
		require_once QRMS_PLUGIN_DIR . 'modules/qr-ceviri/module.php';

		$_GET = array( 'page' => 'qrms-overview' );
		qrms_module_qr_ceviri_admin_assets();
		qrms_assert_same( null, qrms_ae_style( 'qrms-ceviri-admin' ), 'başka ekranda yüklenmez' );

		$_GET = array( 'page' => QRMS_Admin::get_module_page_slug( 'qr-ceviri' ) );
		qrms_module_qr_ceviri_admin_assets();

		$stil = qrms_ae_style( 'qrms-ceviri-admin' );

		qrms_assert_true( null !== $stil, 'kendi ekranında yüklenir' );
		qrms_assert_same(
			QRMS_VERSION . '.' . filemtime( QRMS_PLUGIN_DIR . 'modules/qr-ceviri/assets/css/admin.css' ),
			$stil['ver'],
			'sürüm dosyanın kendi zamanını taşır'
		);
	}
);

qrms_test(
	'yönetim sayfasında kırılım noktası olmayan satır içi ölçü kalmadı',
	function () {
		// Asıl kusur buydu: ölçüler markup'a satır içi yazılmıştı
		// (repeat(3,1fr) ızgaralar, max-width:800px kutular). Satır içi stilin
		// medya sorgusu olamaz, bu yüzden ekran darda sıkışıyordu.
		$kaynak = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-ceviri/includes/admin/admin-sayfa.php' );

		qrms_assert_false( strpos( $kaynak, 'grid-template-columns' ), 'satır içi ızgara yok' );
		qrms_assert_false( strpos( $kaynak, 'max-width:800px' ), 'satır içi genişlik sınırı yok' );
		qrms_assert_false( strpos( $kaynak, 'max-height:280px' ), 'satır içi kutu yüksekliği yok' );
		qrms_assert_contains( 'qrc-check-grid', $kaynak, 'ızgara sınıfa taşındı' );
	}
);

qrms_test(
	'durum tablosu dar ekranda karta dönebilsin diye hücreler etiketli',
	function () {
		// Kart görünümünde sütun başlığı yoktur; hangi dile ait olduğunu
		// hücrenin data-label'ı söyler. İkisi birlikte anlamlı: etiket
		// olmadan kart okunmaz, kural olmadan etiket görünmez.
		$kaynak = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-ceviri/includes/admin/admin-sayfa.php' );
		$css    = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-ceviri/assets/css/admin.css' );

		qrms_assert_contains( 'data-label="<?php echo esc_attr( $etiket ); ?>"', $kaynak, 'hücre etiketi basılır' );
		qrms_assert_contains( 'content: attr(data-label)', $css, 'kart görünümü etiketi kullanır' );
		qrms_assert_contains( 'max-width: 782px', $css, 'kırılım noktası tanımlı' );
	}
);

qrms_test(
	'onay kutusu satırları dokunmatik yükseklikte',
	function () {
		// 44-48px, WordPress admin'in kendi dokunma eşiği. Sayı CSS'ten
		// okunur: kural silinirse ya da küçültülürse test düşer.
		$css = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-ceviri/assets/css/admin.css' );

		qrms_assert_true(
			(bool) preg_match( '/\.qrc-check\s*\{[^}]*min-height:\s*(4[4-9]|[5-9]\d)px/s', $css ),
			'satır en az 44px'
		);
		qrms_assert_true(
			(bool) preg_match( '/\.qrc-check input\[type="checkbox"\]\s*\{[^}]*width:\s*20px/s', $css ),
			'kutu büyütülmüş'
		);
	}
);


/* P2 çeviri testleri (birleşme sonrası taşındı) */

echo "\nQR Çeviri (P0 köprü / galeri)\n";

require_once QRMS_PLUGIN_DIR . 'modules/qr-ceviri/includes/ui-stringler.php';
require_once QRMS_PLUGIN_DIR . 'modules/qr-ceviri/includes/fiyat.php';
require_once QRMS_PLUGIN_DIR . 'modules/yorum-feedback/includes/settings.php';
require_once QRMS_PLUGIN_DIR . 'modules/qr-ceviri/includes/veri-kaynaklar.php';
require_once QRMS_PLUGIN_DIR . 'modules/qr-ceviri/includes/kaynaklar.php';

qrms_test(
	'gecerli tipler eski seti korur ve modül tiplerini ekler',
	function () {
		$tipler = rma_ceviri_gecerli_tipler();

		foreach ( array( 'product', 'category', 'allergen', 'nav_menu', 'ui_string', 'elementor' ) as $eski ) {
			qrms_assert_true( in_array( $eski, $tipler, true ), $eski . ' duruyor' );
		}
		foreach ( array( 'splash', 'hours', 'chat', 'cart', 'review', 'gallery', 'lock' ) as $yeni ) {
			qrms_assert_true( in_array( $yeni, $tipler, true ), $yeni . ' eklendi' );
			qrms_assert_true( strlen( $yeni ) <= 20, $yeni . ' varchar(20) içinde' );
		}
	}
);

qrms_test(
	'galeri kaynak metinleri katalogda ve anahtarları kararlı',
	function () {
		$metinler = rma_ceviri_modul_stringleri( 'gallery' );

		qrms_assert_same( 'Tümü', $metinler[ rma_ceviri_ui_anahtari( 'Tümü' ) ], 'Tümü' );
		qrms_assert_same(
			'Galeri bulunamadı.',
			$metinler[ rma_ceviri_ui_anahtari( 'Galeri bulunamadı.' ) ],
			'Galeri bulunamadı'
		);
		qrms_assert_same(
			'Tümü',
			rma_ceviri_guncel_orijinal( 0, 'gallery', rma_ceviri_ui_anahtari( 'Tümü' ) ),
			'guncel orijinal'
		);
	}
);

qrms_test(
	'çeviri yoksa veya fonksiyon yoksa Türkçe döner, anahtar adı basılmaz',
	function () {
		qrms_assert_same( 'Tümü', rma_ceviri_modul( 'gallery', 'Tümü' ), 'tablo yokken Türkçe' );

		if ( ! function_exists( 'rma_translate_field' ) ) {
			/**
			 * @param int    $item_id ID.
			 * @param string $tip     Tip.
			 * @param string $field   Alan.
			 * @param string $orijinal Orijinal.
			 * @return string
			 */
			function rma_translate_field( $item_id, $tip, $field, $orijinal ) {
				$GLOBALS['qrms_test']['ceviri_cagri'] = array( $item_id, $tip, $field, $orijinal );
				return '';
			}
		}

		qrms_assert_same( 'Tümü', rma_ceviri_modul( 'gallery', 'Tümü' ), 'boş çeviri Türkçeye düşer' );
	}
);

qrms_test(
	'galeri ön yüzü rma_ceviri_modul köprüsünü kullanır ve önbellek dile bağlıdır',
	function () {
		$kaynak = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-galeri/includes/trait-frontend.php' );

		qrms_assert_contains( "rma_ceviri_modul( 'gallery'", $kaynak, 'köprü çağrısı' );
		qrms_assert_contains( "__( 'Galeri bulunamadı.', 'qrmenu-gallery-manager' )", $kaynak, 'textdomain duruyor' );
		qrms_assert_contains( "rma_get_current_lang()", $kaynak, 'önbellek dile bağlı' );
		qrms_assert_contains( 'rma_ceviri_onbellek_surumu', $kaynak, 'CSV sonrası önbellek kırılır' );
		qrms_assert_false(
			(bool) preg_match( '/>Tümü</', $kaynak ),
			'Düz Tümü metni kalmadı'
		);
	}
);

qrms_test(
	'Sistem Durumu etiketleri modül tiplerini içerir',
	function () {
		require_once QRMS_PLUGIN_DIR . 'modules/qr-ceviri/includes/admin/admin-sayfa.php';

		qrms_assert_same( 'Galeri', rma_ceviri_tip_etiketi( 'gallery' ), 'galeri etiketi' );
		qrms_assert_same( 'Menü ürünleri', rma_ceviri_tip_etiketi( 'product' ), 'eski etiket duruyor' );
	}
);

echo "\nQR Çeviri (P0 köprü / çalışma saatleri)\n";

qrms_test(
	'P1 tipleri varchar(20) içinde; option modul_sabit değil',
	function () {
		$tipler = rma_ceviri_gecerli_tipler();
		foreach ( array( 'option', 'form_field', 'cf_field', 'cf_form' ) as $tip ) {
			qrms_assert_true( in_array( $tip, $tipler, true ), $tip . ' geçerli' );
			qrms_assert_true( strlen( $tip ) <= 20, $tip . ' varchar(20)' );
		}
		qrms_assert_true( rma_ceviri_hash_kapisi_mi( 'option' ), 'option kapı' );
		qrms_assert_true( rma_ceviri_hash_kapisi_mi( 'form_field' ), 'form_field kapı' );
		qrms_assert_true( rma_ceviri_hash_kapisi_mi( 'cf_field' ), 'cf_field kapı' );
		qrms_assert_true( rma_ceviri_hash_kapisi_mi( 'cf_form' ), 'cf_form kapı' );
		qrms_assert_false( rma_ceviri_hash_kapisi_mi( 'product' ), 'ürün kapı yok' );
		qrms_assert_false( rma_ceviri_modul_sabit_mi( 'option' ), 'option metin anahtarı değil' );
		qrms_assert_false( isset( rma_ceviri_modul_tipleri()['option'] ), 'option modul listesinde yok' );
		qrms_assert_same( 'Yönetici ayarları', rma_ceviri_veri_tipleri()['option'], 'option etiket' );
		qrms_assert_same( 'Form alanları', rma_ceviri_veri_tipleri()['form_field'], 'form_field etiket' );
	}
);

qrms_test(
	'hash kapısı: uyuşmazsa çeviri basılmaz, ürün kapıya girmez',
	function () {
		qrms_assert_true( rma_ceviri_hash_guncel_mi( 'Deneyiminizi Paylaşın', md5( 'Deneyiminizi Paylaşın' ) ), 'aynı' );
		qrms_assert_false( rma_ceviri_hash_guncel_mi( 'Görüşünüz Bizim İçin Değerli', md5( 'Deneyiminizi Paylaşın' ) ), 'eski' );
		qrms_assert_false( rma_ceviri_hash_guncel_mi( 'metin', '' ), 'boş hash' );

		$kapi = static function ( $ceviri, $orijinal, $hash ) {
			return rma_ceviri_hash_guncel_mi( $orijinal, $hash ) ? $ceviri : $orijinal;
		};

		qrms_assert_same( 'Assistant', $kapi( 'Assistant', 'Asistan', md5( 'Asistan' ) ), 'taze' );
		qrms_assert_same( 'Yeni Bot', $kapi( 'Assistant', 'Yeni Bot', md5( 'Asistan' ) ), 'eskimiş yönetici metni' );

		$sozluk = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-ceviri/includes/sozluk.php' );
		qrms_assert_contains( 'rma_ceviri_hash_kapisi_mi', $sozluk, 'ön yüz kapısı' );
		qrms_assert_contains( 'rma_ceviri_hash_guncel_mi', $sozluk, 'hash karşılaştırma' );
		qrms_assert_contains( 'Metin indeksine girmez', $sozluk, 'option tampona girmez' );
	}
);

qrms_test(
	'option defteri ve guncel_orijinal canlı değeri okur',
	function () {
		$defter = rma_ceviri_option_defteri();
		qrms_assert_true( isset( $defter['gemini_bot_name'] ), 'bot adı' );
		qrms_assert_true( isset( $defter['qrm_settings.form_title'] ), 'form başlığı' );
		qrms_assert_true( isset( $defter['qrm_settings.crit_1_name'] ), 'kriter 1' );
		qrms_assert_true( isset( $defter['hfb_footer.links_title'] ), 'HFB başlık' );
		qrms_assert_true( ! empty( $defter['hfb_footer.call_garson_label']['yalniz_ozel'] ), 'çağrı yalnızca özel' );

		update_option(
			'hfb_hamburger_options',
			array(
				'blocks' => array(
					array(
						'id'      => 'blk_2',
						'type'    => 'button',
						'enabled' => true,
						'label'   => 'Menüye Git',
					),
				),
			)
		);
		$defter = rma_ceviri_option_defteri();
		qrms_assert_true( isset( $defter['hfb_hamburger.block.blk_2.label'] ), 'hamburger buton defteri' );

		update_option( 'gemini_bot_name', 'Masa Asistanı' );
		qrms_assert_same( 'Masa Asistanı', rma_ceviri_option_guncel( 'gemini_bot_name' ), 'canlı option' );
		qrms_assert_same(
			'Masa Asistanı',
			rma_ceviri_guncel_orijinal( 0, 'option', 'gemini_bot_name' ),
			'guncel_orijinal option'
		);

		update_option( 'qrm_settings', array( 'form_title' => 'Görüşünüz Bizim İçin Değerli' ) );
		qrms_assert_same(
			'Görüşünüz Bizim İçin Değerli',
			rma_ceviri_guncel_orijinal( 0, 'option', 'qrm_settings.form_title' ),
			'qrm_settings iç anahtar'
		);

		$satirlar = iterator_to_array( rma_ceviri_option_satirlari() );
		$fieldler = array();
		foreach ( $satirlar as $satir ) {
			qrms_assert_same( 'option', $satir['item_type'], 'tip option' );
			qrms_assert_same( 0, $satir['item_id'], 'id 0' );
			$fieldler[ $satir['field'] ] = $satir['original'];
		}
		qrms_assert_same( 'Masa Asistanı', $fieldler['gemini_bot_name'], 'CSV bot adı' );
		qrms_assert_same( 'Görüşünüz Bizim İçin Değerli', $fieldler['qrm_settings.form_title'], 'CSV form başlığı' );
		qrms_assert_false( isset( $fieldler['hfb_footer.call_garson_label'] ), 'varsayılan çağrı CSV dışı' );
	}
);

qrms_test(
	'yetim filtre, uyarı metni, Sistem Durumu etiketleri',
	function () {
		qrms_assert_same(
			array( 9, 4 ),
			rma_ceviri_yetim_idleri_filtrele( array( 1, 9, 4, 1 ), array( 1, 2 ) ),
			'yetim ID'
		);
		qrms_assert_same( '', rma_ceviri_bayat_uyari_metni( 0 ), 'uyarı yok' );
		qrms_assert_contains( '2 dilde', rma_ceviri_bayat_uyari_metni( 2 ), 'uyarı dil' );
		qrms_assert_contains( 'ziyaretçiler Türkçe', rma_ceviri_bayat_uyari_metni( 1 ), 'Türkçe uyarısı' );
		qrms_assert_false(
			(bool) preg_match( '/wp_die|disabled|readonly/', rma_ceviri_bayat_uyari_html( rma_ceviri_bayat_uyari_metni( 1 ) ) ),
			'uyarı kaydı engellemez'
		);

		require_once QRMS_PLUGIN_DIR . 'modules/qr-ceviri/includes/admin/admin-sayfa.php';
		qrms_assert_same( 'Yönetici ayarları', rma_ceviri_tip_etiketi( 'option' ), 'option durum' );
		qrms_assert_same( 'Form alanları', rma_ceviri_tip_etiketi( 'form_field' ), 'form_field durum' );
		qrms_assert_same( 'Özel form alanları', rma_ceviri_tip_etiketi( 'cf_field' ), 'cf_field durum' );
		qrms_assert_same( 'Özel form metinleri', rma_ceviri_tip_etiketi( 'cf_form' ), 'cf_form durum' );
		qrms_assert_same( 'Menü ürünleri', rma_ceviri_tip_etiketi( 'product' ), 'eski etiket' );

		$admin = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-ceviri/includes/admin/admin-sayfa.php' );
		qrms_assert_contains( 'Yetim satır:', $admin, 'yetim gösterge' );
		qrms_assert_contains( 'Yetim satırları temizle', $admin, 'temizle düğmesi' );
		qrms_assert_contains( 'geri alınamaz', $admin, 'onay uyarısı' );
		qrms_assert_contains( 'Eskimiş:', $admin, 'eskimiş rozet' );

		$help = file_get_contents( QRMS_PLUGIN_DIR . 'includes/class-helpers.php' );
		qrms_assert_contains( 'HFB çeviri satırları iki tipte', $help, 'Faz 9 maddesi' );
	}
);

qrms_test(
	'çeviri yoksa yönetici metni; boş option varsayılana düşer',
	function () {
		qrms_assert_same( 'Asistan', rma_ceviri_option( 'gemini_bot_name', 'Asistan' ), 'tablo yok option' );
		qrms_assert_same( 'Adınız Soyadınız', qrm_ceviri_form_alan( 3, 'Adınız Soyadınız' ), 'tablo yok alan' );
		qrms_assert_same( 'Şikayet', qrm_ceviri_cf_form( 1, 'title', 'Şikayet' ), 'tablo yok cf_form' );

		$bot = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-chatbot/includes/admin/admin-sayfa.php' );
		qrms_assert_contains( 'rma_ceviri_bayat_uyari_metni', $bot, 'chatbot uyarı' );

		$hfb = file_get_contents( QRMS_PLUGIN_DIR . 'modules/header-footer-builder/includes/trait-admin.php' );
		qrms_assert_contains( 'hfb_ceviri_bayat_uyari', $hfb, 'HFB uyarı' );

		$set = file_get_contents( QRMS_PLUGIN_DIR . 'modules/yorum-feedback/includes/admin/settings-page.php' );
		qrms_assert_contains( 'rma_ceviri_bayat_uyari_ekran_metni', $set, 'yorum ayar uyarı' );
	}
);


echo "\nQR Çeviri (Bölüm B hub + alt sayfa)\n";

require_once QRMS_PLUGIN_DIR . 'modules/qr-ceviri/includes/admin/hub-sayfasi.php';
require_once QRMS_PLUGIN_DIR . 'modules/qr-ceviri/includes/admin/diller-sayfasi.php';
require_once QRMS_PLUGIN_DIR . 'modules/qr-ceviri/includes/admin/kapsam-sayfasi.php';
require_once QRMS_PLUGIN_DIR . 'modules/qr-ceviri/includes/admin/metin-toplama-sayfasi.php';
require_once QRMS_PLUGIN_DIR . 'modules/qr-ceviri/includes/admin/csv-disa-sayfasi.php';
require_once QRMS_PLUGIN_DIR . 'modules/qr-ceviri/includes/admin/csv-ice-sayfasi.php';
require_once QRMS_PLUGIN_DIR . 'modules/qr-ceviri/includes/admin/sistem-durumu-sayfasi.php';
require_once QRMS_PLUGIN_DIR . 'modules/qr-ceviri/includes/admin/admin-sayfa.php';
require_once QRMS_PLUGIN_DIR . 'modules/qr-ceviri/includes/csv-export.php';
require_once QRMS_PLUGIN_DIR . 'modules/qr-ceviri/includes/elementor-tarama.php';
require_once QRMS_PLUGIN_DIR . 'modules/qr-ceviri/module.php';

if ( ! function_exists( 'rma_ceviri_hedef_diller' ) ) {
	/**
	 * @return string[]
	 */
	function rma_ceviri_hedef_diller() {
		$aktif = function_exists( 'rma_ceviri_aktif_diller' )
			? rma_ceviri_aktif_diller()
			: (array) get_option( 'qrmenu_active_langs', array() );

		return array_values( array_diff( $aktif, array( 'tr' ) ) );
	}
}

qrms_test(
	'modül satırı hub ekranıdır; altı adım kart olur, ikon dashicon\'dur',
	function () {
		$sayfalar = qrms_module_qr_ceviri_sayfalar();

		qrms_assert_same(
			array(
				'qrms-cv-diller',
				'qrms-cv-kapsam',
				'qrms-cv-toplama',
				'qrms-cv-disa',
				'qrms-cv-ice',
				'qrms-cv-durum',
			),
			array_keys( $sayfalar ),
			'adım slug\'ları'
		);

		foreach ( $sayfalar as $slug => $sayfa ) {
			qrms_assert_true( is_callable( $sayfa['render'] ), $slug . ' render edilebilir' );
			qrms_assert_true( 0 === strpos( $sayfa['icon'], 'dashicons-' ), $slug . ' dashicon' );
		}

		$kartlar = qrms_module_qr_ceviri_hub_kartlari();
		qrms_assert_same( 6, count( $kartlar ), 'altı kart' );

		foreach ( $kartlar as $kart ) {
			qrms_assert_false(
				false !== strpos( $kart['url'], QRMS_CEVIRI_KLASIK_SAYFA ),
				'klasik görünüm kartı yok'
			);
			qrms_assert_true( '' !== $kart['desc'], $kart['title'] . ' durum satırı' );
		}
	}
);

qrms_test(
	'alt sayfalar menüye satır EKLEMEZ; eski slug hub\'a yönlendirir',
	function () {
		update_option( 'qrms_active_modules', array( 'qr-ceviri' ) );

		$GLOBALS['submenu'][ QRMS_Admin::MENU_SLUG ] = array(
			qrms_submenu_satiri( 'Dil / Çeviri Ayarları', QRMS_Admin::get_module_page_slug( 'qr-ceviri' ) ),
		);

		qrms_module_qr_ceviri_admin_menu();

		$sluglar = array_map(
			static function ( $item ) {
				return $item['slug'];
			},
			$GLOBALS['qrms_test']['submenus']
		);

		foreach ( array_keys( qrms_module_qr_ceviri_sayfalar() ) as $slug ) {
			qrms_assert_true( in_array( $slug, $sluglar, true ), $slug . ' kayıtlı' );
			qrms_assert_true( QRMS_Admin::is_module_subpage( $slug ), $slug . ' alt sayfa defterinde' );
		}

		qrms_assert_true( in_array( QRMS_CEVIRI_KLASIK_SAYFA, $sluglar, true ), 'klasik slug kayıtlı' );
		qrms_assert_true( in_array( QRMS_CEVIRI_ESKI_SAYFA, $sluglar, true ), 'eski slug kayıtlı' );

		$gizlenen = QRMS_Admin::collect_hidden_rows(
			$GLOBALS['submenu'][ QRMS_Admin::MENU_SLUG ],
			QRMS_Admin::get_menu_row_slugs()
		);

		foreach ( array_keys( qrms_module_qr_ceviri_sayfalar() ) as $slug ) {
			qrms_assert_true( in_array( $slug, $gizlenen, true ), $slug . ' menüden düşer' );
		}

		qrms_assert_true( in_array( QRMS_CEVIRI_KLASIK_SAYFA, $gizlenen, true ), 'klasik menüden düşer' );

		try {
			qrms_module_qr_ceviri_eski_adresi_yonlendir();
			qrms_assert_true( false, 'yönlendirme bekleniyordu' );
		} catch ( QRMS_Test_Redirect $e ) {
			qrms_assert_same(
				QRMS_Admin::get_module_page_url( 'qr-ceviri' ),
				$e->getMessage(),
				'hub\'a gider'
			);
		}
	}
);

qrms_test(
	'eski çeviri slug\'ı yönlendirirken sayacı artırır',
	function () {
		$_GET['page'] = QRMS_CEVIRI_ESKI_SAYFA;

		try {
			qrms_module_qr_ceviri_eski_adresi_yonlendir();
			qrms_assert_true( false, 'yönlendirme bekleniyordu' );
		} catch ( QRMS_Test_Redirect $e ) {
			$hits = QRMS_Helpers::legacy_slug_hits();
			qrms_assert_same( 1, $hits[ QRMS_CEVIRI_ESKI_SAYFA ]['count'], 'vuruş kaydı' );
		}
	}
);

qrms_test(
	'adım şeridi kilitli değildir; diğer adımlara bağlantı vardır',
	function () {
		ob_start();
		qrms_module_qr_ceviri_adim_seridi( 'qrms-cv-kapsam' );
		$html = ob_get_clean();

		qrms_assert_contains( 'Adım 2 / 6', $html, 'ilerleme' );
		qrms_assert_contains( 'istediğiniz adıma geçebilirsiniz', $html, 'kilit yok' );
		qrms_assert_contains( 'page=qrms-cv-diller', $html, 'Diller atlanabilir' );
		qrms_assert_contains( 'page=qrms-cv-durum', $html, 'Durum atlanabilir' );
		qrms_assert_contains( 'aria-current="page"', $html, 'aktif adım' );
		qrms_assert_false( false !== strpos( $html, 'disabled' ), 'disabled yok' );
	}
);

qrms_test(
	'dikkat şeridi: dil yok, eskimiş ve yetim ayrı ayrı',
	function () {
		update_option( 'qrmenu_active_langs', array( 'tr' ) );

		$maddeler = qrms_module_qr_ceviri_dikkatler();
		$metinler = wp_list_pluck( $maddeler, 'metin' );
		$birlesik = implode( ' ', $metinler );

		qrms_assert_contains( 'hedef dil', $birlesik, 'dil yok uyarısı' );

		$html = qrms_module_qr_ceviri_dikkat_html();
		qrms_assert_contains( 'qrc-attention', $html, 'şerit' );
		qrms_assert_contains( 'is-critical', $html, 'dil yok kritik' );
		qrms_assert_contains( 'dashicons-warning', $html, 'dashicon' );
	}
);

qrms_test(
	'Sistem Durumu hücresi çeviri yok ile kaynak yoku ayırır',
	function () {
		qrms_assert_same( '12', rma_ceviri_hucre_durumu( 12, 4 )['metin'], 'sayı' );
		qrms_assert_same( 'çeviri yok', rma_ceviri_hucre_durumu( 0, 3 )['metin'], 'kaynak var' );
		qrms_assert_same( 'kaynak yok', rma_ceviri_hucre_durumu( 0, 0 )['metin'], 'kaynak yok' );
		qrms_assert_same( 'çeviri yok', rma_ceviri_hucre_durumu( 0, -1 )['metin'], 'bilinmiyor' );
		qrms_assert_contains( 'is-no-source', rma_ceviri_hucre_durumu( 0, 0 )['sinif'], 'kaynak sınıfı' );
		qrms_assert_contains( 'is-no-trans', rma_ceviri_hucre_durumu( 0, 2 )['sinif'], 'çeviri sınıfı' );
	}
);

qrms_test(
	'Diller kaydı kapsama ve toplamaya dokunmaz',
	function () {
		update_option( 'rma_ceviri_urun_tipleri', array( 'rma_menu_item' ) );
		update_option( 'rma_ceviri_ek_metinler', 'Eski sabit' );
		update_option( 'rma_ceviri_toplama_acik', 1 );
		update_option( 'qrmenu_active_langs', array( 'tr', 'en' ) );

		$_POST = array(
			'qrms_cv_diller_save' => '1',
			'qrmenu_langs'        => array( 'tr', 'de' ),
			'qrmenu_bg_color_text' => '#222222',
			'qrmenu_bg_color_only' => '#333333',
			'rma_url_yonlendir'   => '1',
		);

		ob_start();
		rma_ceviri_dilleri_kaydet();
		ob_get_clean();

		qrms_assert_same( array( 'tr', 'de' ), get_option( 'qrmenu_active_langs' ), 'diller yazıldı' );
		qrms_assert_same( array( 'rma_menu_item' ), get_option( 'rma_ceviri_urun_tipleri' ), 'kapsam duruyor' );
		qrms_assert_same( 'Eski sabit', get_option( 'rma_ceviri_ek_metinler' ), 'sabit metin duruyor' );
		qrms_assert_same( 1, (int) get_option( 'rma_ceviri_toplama_acik' ), 'toplama duruyor' );
	}
);

qrms_test(
	'Kapsam kaydı dillere dokunmaz; silinmiş sayfa yazılmaz',
	function () {
		update_option( 'qrmenu_active_langs', array( 'tr', 'en', 'fr' ) );
		update_option( 'rma_ceviri_ek_metinler', 'Dokunma' );

		$GLOBALS['qrms_test']['post_status'][10] = 'publish';
		$GLOBALS['qrms_test']['post_status'][99] = false;

		$_POST = array(
			'qrms_cv_kapsam_save' => '1',
			'rma_urun_tipleri'    => array(),
			'elementor_sayfalar'  => array( 10, 99 ),
		);

		ob_start();
		rma_ceviri_kapsami_kaydet();
		ob_get_clean();

		qrms_assert_same( array( 'tr', 'en', 'fr' ), get_option( 'qrmenu_active_langs' ), 'diller duruyor' );
		qrms_assert_same( 'Dokunma', get_option( 'rma_ceviri_ek_metinler' ), 'sabit duruyor' );
		qrms_assert_same( array( 10 ), get_option( 'rma_ceviri_elementor_sayfalar' ), 'silinmiş düştü' );
	}
);

qrms_test(
	'Elementor seçim süzgeci silinmiş ID\'yi düşürür, duranı tutar',
	function () {
		qrms_assert_same(
			array( 5, 8 ),
			rma_ceviri_elementor_secimini_ele( array( 5, 8, 9 ), array( 5, 8 ) ),
			'liste kümesi'
		);

		$GLOBALS['qrms_test']['post_status'][3] = 'publish';
		$GLOBALS['qrms_test']['post_status'][4] = 'trash';
		$GLOBALS['qrms_test']['post_status'][0] = 'publish';

		qrms_assert_same(
			array( 3 ),
			rma_ceviri_elementor_secimini_ele( array( 3, 4, 0 ) ),
			'durum süzmesi'
		);
	}
);

qrms_test(
	'bellek boyutu çözümü ve eşik; CSV zaman metni',
	function () {
		qrms_assert_same( 1048576, rma_ceviri_bayt_coz( '1M' ), '1M' );
		qrms_assert_same( 2048, rma_ceviri_bayt_coz( '2k' ), '2k' );
		qrms_assert_same( -1, rma_ceviri_bayt_coz( '-1' ), 'sınırsız' );
		qrms_assert_same( 512, rma_ceviri_bayt_coz( '512' ), 'düz bayt' );
		qrms_assert_true( is_bool( rma_ceviri_bellek_sinirda_mi() ), 'eşik bool' );
		qrms_assert_same( 'henüz yok', qrms_module_qr_ceviri_zaman_metni( 0 ), 'boş zaman' );
		qrms_assert_true( '' !== qrms_module_qr_ceviri_zaman_metni( 1700000000 ), 'dolu zaman' );
	}
);

qrms_test(
	'klasik sayfa durur; emoji ikon kalmadı; alt sayfa kendi kaydını söyler',
	function () {
		qrms_assert_true( function_exists( 'qrmenu_trans_page' ), 'klasik renderer' );

		$dosyalar = array(
			'modules/qr-ceviri/includes/admin/admin-sayfa.php',
			'modules/qr-ceviri/includes/admin/hub-sayfasi.php',
			'modules/qr-ceviri/includes/admin/diller-sayfasi.php',
			'modules/qr-ceviri/includes/admin/kapsam-sayfasi.php',
			'modules/qr-ceviri/includes/admin/metin-toplama-sayfasi.php',
			'modules/qr-ceviri/includes/admin/csv-disa-sayfasi.php',
			'modules/qr-ceviri/includes/admin/csv-ice-sayfasi.php',
			'modules/qr-ceviri/includes/admin/sistem-durumu-sayfasi.php',
		);

		foreach ( $dosyalar as $yol ) {
			$kaynak = file_get_contents( QRMS_PLUGIN_DIR . $yol );
			qrms_assert_false( false !== strpos( $kaynak, '🔍' ), $yol . ' arama emojisi yok' );
			qrms_assert_false( false !== strpos( $kaynak, '📤' ), $yol . ' dışa emojisi yok' );
			qrms_assert_false( false !== strpos( $kaynak, '📥' ), $yol . ' içe emojisi yok' );
			qrms_assert_false( false !== strpos( $kaynak, '⚙️' ), $yol . ' dişli emojisi yok' );
		}

		$diller = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-ceviri/includes/admin/diller-sayfasi.php' );
		qrms_assert_contains( 'Yalnızca bu sayfadaki dil ve görünüm ayarları kaydedilir', $diller, 'izole kayıt' );
		qrms_assert_false(
			false !== strpos( $diller, 'yukarıdaki tüm ayarları' ),
			'Diller tümünü kaydetmez'
		);

		$css = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-ceviri/assets/css/admin.css' );
		qrms_assert_contains( 'table.widefat', $css, 'tüm widefat tablolar karta döner' );
		qrms_assert_contains( '.qrc-cards', $css, 'kart sınıfı' );
	}
);

qrms_test(
	'modül lisansta aktif değilken çeviri alt sayfası da kaydedilmez',
	function () {
		qrms_module_qr_ceviri_admin_menu();
		qrms_assert_same( array(), $GLOBALS['qrms_test']['submenus'], 'kayıt yok' );
	}
);

echo "\nQR Çeviri (P2 kalanlar)\n";

qrms_test(
	'alerjen fallback etiketleri ui_string listesinde; terim yolu durur',
	function () {
		$ui     = rma_ceviri_varsayilan_ui_metinleri();
		$helper = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/trait-helpers.php' );
		$etiketler = array(
			'Glüten',
			'Süt / Laktoz',
			'Yumurta',
			'Fındık / Kuruyemiş',
			'Yer Fıstığı',
			'Soya',
			'Balık',
			'Kabuklu Deniz Ürünü',
			'Susam',
			'Kereviz',
			'Hardal',
			'Lüpin',
			'Kükürt Dioksit/Sülfit',
			'Yumuşakça',
		);

		foreach ( $etiketler as $etiket ) {
			qrms_assert_true( in_array( $etiket, $ui, true ), $etiket );
		}

		qrms_assert_contains( '$this->t_term( $terim, \'allergen\' )', $helper, 'terim yolu durur' );
		qrms_assert_contains( '$this->t( $label )', $helper, 'ui_string fallback durur' );
	}
);

qrms_test(
	'Dil seç aria ui_string köprüsünde; splash data-sp-attr kullanılmaz',
	function () {
		$php = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-ceviri/includes/shortcodes.php' );
		$ui  = rma_ceviri_varsayilan_ui_metinleri();

		qrms_assert_true( in_array( 'Dil seç', $ui, true ), 'katalog' );
		qrms_assert_contains( "rma_ceviri_modul( 'ui_string', __( 'Dil seç', 'qrms' ) )", $php, 'köprü' );
		qrms_assert_false( (bool) preg_match( '/aria-label="Dil seç"/', $php ), 'sabit Dil seç kalmadı' );
		qrms_assert_false( (bool) preg_match( '/data-sp-attr/', $php ), 'splash modeli yok' );
	}
);

qrms_test(
	'fiyat ayraç tablosu ve üç kalıp; {n} anahtarları çakışmaz',
	function () {
		qrms_assert_same( '1.234,56', rma_ceviri_fiyat_sayi( 1234.56, 'tr' ), 'tr' );
		qrms_assert_same( '1.234,56', rma_ceviri_fiyat_sayi( 1234.56, 'de' ), 'de' );
		qrms_assert_same( '1.234,56', rma_ceviri_fiyat_sayi( 1234.56, 'es' ), 'es' );
		qrms_assert_same( '1.234,56', rma_ceviri_fiyat_sayi( 1234.56, 'it' ), 'it' );
		qrms_assert_same( '1.234,56', rma_ceviri_fiyat_sayi( 1234.56, 'pt' ), 'pt' );
		qrms_assert_same( '1.234,56', rma_ceviri_fiyat_sayi( 1234.56, 'nl' ), 'nl' );
		qrms_assert_same( '1 234,56', rma_ceviri_fiyat_sayi( 1234.56, 'fr' ), 'fr' );
		qrms_assert_same( '1,234.56', rma_ceviri_fiyat_sayi( 1234.56, 'en' ), 'en' );
		qrms_assert_same( '1,234.56', rma_ceviri_fiyat_sayi( 1234.56, 'ar' ), 'ar' );
		qrms_assert_same( '1,234.56', rma_ceviri_fiyat_sayi( 1234.56, 'ru' ), 'ru' );
		qrms_assert_same( '1,234.56', rma_ceviri_fiyat_sayi( 1234.56, 'xx' ), 'diğer' );
		qrms_assert_same( '52', rma_ceviri_fiyat_sayi( 52.0, 'tr' ), 'tam sayı' );
		qrms_assert_same( '52,50 ₺', rma_ceviri_fiyat( 52.5, '{n} ₺', 'tr' ), 'kalıp TR' );
		qrms_assert_same( '52.50 ₺', rma_ceviri_fiyat( 52.5, '{n} ₺', 'en' ), 'kalıp EN' );
		qrms_assert_same( '-10 ₺', rma_ceviri_fiyat( 10, '-{n} ₺', 'tr' ), 'indirim' );
		qrms_assert_same( '%15', rma_ceviri_fiyat( 15, '%{n}', 'tr' ), 'yüzde' );

		$k1 = rma_ceviri_ui_anahtari( '{n} ₺' );
		$k2 = rma_ceviri_ui_anahtari( '-{n} ₺' );
		$k3 = rma_ceviri_ui_anahtari( '%{n}' );
		qrms_assert_true( $k1 !== $k2 && $k2 !== $k3 && $k1 !== $k3, 'üç kalıp ayrı anahtar' );

		$ui = rma_ceviri_varsayilan_ui_metinleri();
		foreach ( array( '{n} ₺', '-{n} ₺', '%{n}' ) as $kalip ) {
			qrms_assert_true( in_array( $kalip, $ui, true ), $kalip );
		}

		$kamp = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/class-kampanya.php' );
		qrms_assert_contains( 'function fiyat_yazi', $kamp, 'kampanya giriş' );
		qrms_assert_contains( "rma_ceviri_fiyat( \$deger, \$kalip )", $kamp, 'köprü' );

		$js = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-chatbot/assets/js/sepet.js' );
		qrms_assert_contains( "'tr', 'de', 'es', 'it', 'pt', 'nl'", $js, 'JS grup 1' );
		qrms_assert_contains( "'fr' === kod", $js, 'JS fr' );
		qrms_assert_false( (bool) preg_match( '/new Intl/', $js ), 'Intl yok' );
	}
);

qrms_test(
	'chatbot yedek ve yeni option defteri; hash kapısı durur',
	function () {
		require_once QRMS_PLUGIN_DIR . 'modules/qr-chatbot/includes/class-ayarlar.php';

		$ajax   = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-chatbot/includes/ajax-chat.php' );
		$bot    = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-chatbot/includes/shortcode-chatbot.php' );
		$defter = rma_ceviri_option_defteri();

		qrms_assert_contains( 'qmo_ceviri_chat( $uyari )', $ajax, 'yasak yedek sarıldı' );
		qrms_assert_true( isset( $defter['qmo_chatbot_teaser_text'] ), 'teaser' );
		qrms_assert_true( isset( $defter['qmo_chatbot_welcome_intro'] ), 'intro' );
		qrms_assert_true( isset( $defter['qmo_chatbot_welcome_btn'] ), 'btn' );
		qrms_assert_true( isset( $defter['qmo_chatbot_closed_message'] ), 'closed' );
		qrms_assert_true( isset( $defter['qmo_chatbot_qr.d1.label'] ), 'soru etiket' );
		qrms_assert_true( isset( $defter['qmo_chatbot_qr.d1.question'] ), 'soru metin' );
		qrms_assert_contains( "rma_ceviri_option( 'qmo_chatbot_teaser_text'", $bot, 'teaser option' );
		qrms_assert_contains( "rma_ceviri_option( 'qmo_chatbot_welcome_intro'", $bot, 'intro option' );
		qrms_assert_contains( "rma_ceviri_option( 'qmo_chatbot_welcome_btn'", $bot, 'btn option' );
		qrms_assert_contains( "rma_ceviri_option( 'qmo_chatbot_closed_message'", $bot, 'closed option' );
		qrms_assert_contains( "rma_ceviri_option( 'qmo_chatbot_qr.'", $bot, 'soru option' );
		qrms_assert_true( rma_ceviri_hash_kapisi_mi( 'option' ), 'hash kapısı' );
		qrms_assert_same(
			'Bir şey sormak ister misiniz?',
			rma_ceviri_option( 'qmo_chatbot_teaser_text', 'Bir şey sormak ister misiniz?' ),
			'tablo yokken Türkçe'
		);
	}
);
