<?php
/**
 * Çeviri kapsamı: koddaki her sabit ön yüz metni bir çeviri kataloğunda
 * olmalı; yöneticinin girdiği veriler (adım etiketleri, galeri bölümleri,
 * ekstra/rozet adları, özel chatbot mesajları) CSV'ye çıkabilmeli.
 *
 * Katalogda olmayan bir metin CSV dışa aktarımına hiç çıkmaz ve hiçbir
 * dilde çevrilemez — canlı testte 45 sabit metin ve dört veri türü bu
 * durumdaydı. Bu dosya aynı sınıf hatanın geri gelmesini engeller.
 *
 * Yükleyen: tests/test-suite.php — doğrudan çalıştırmayın.
 *
 * @package QR_Menu_Suite
 */

require_once QRMS_PLUGIN_DIR . 'modules/qr-ceviri/includes/ui-stringler.php';
require_once QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/class-filtre.php';
require_once QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/class-servis-saati.php';
require_once QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/class-ekstra.php';
require_once QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/class-ozel-rozet.php';
require_once QRMS_PLUGIN_DIR . 'modules/restoran-menu/module.php';
require_once QRMS_PLUGIN_DIR . 'modules/qr-galeri/module.php';
require_once QRMS_PLUGIN_DIR . 'modules/yorum-feedback/includes/settings.php';
require_once QRMS_PLUGIN_DIR . 'modules/yorum-feedback/includes/frontend/form-steps.php';
require_once QRMS_PLUGIN_DIR . 'modules/qr-chatbot/includes/class-ayarlar.php';

echo "\nÇeviri kapsamı (katalog bekçisi)\n";

/**
 * Modüllerin katalog kancalarını kaydeder (qrms_reset() her testten önce
 * kancaları sıfırlar; üretimde modül dosyası yüklenirken kaydolurlar).
 */
function qrms_test_ceviri_kancalari() {
	add_filter( 'rma_ceviri_ui_metinleri', 'qrms_restoran_menu_ceviri_katalogu' );
	add_filter( 'rma_ceviri_modul_kaynak_metinleri', 'qrm_ceviri_adim_etiketleri_katalogu' );
	add_filter( 'rma_ceviri_modul_kaynak_metinleri', 'qrms_galeri_ceviri_katalogu' );
}

/**
 * Koddaki sarmalayıcı çağrılarından sabit metinleri tip bazında toplar.
 *
 * @return array<string,array<string,string>> tip => metin => ilk konum.
 */
function qrms_test_ceviri_sabit_metinleri() {
	$sarmal = array(
		'qmo_ceviri_chat'   => 'chat',
		'qmo_ceviri_cart'   => 'cart',
		'qrm_ceviri_review' => 'review',
		'qrms_cs_cevir'     => 'hours',
		'rma_ceviri_ui'     => 'ui_string',
		'qmo_ceviri_ui'     => 'ui_string',
	);
	$desen = '/(->|::|\b)(' . implode( '|', array_keys( $sarmal ) ) . '|rma_ceviri_modul|t|cevir)\s*\(\s*(?:\'([a-z_]+)\'\s*,\s*)?(?:(?:esc_html_|esc_attr_)?__\(\s*)?\'((?:[^\'\\\\]|\\\\.)*)\'/';

	$bulunan = array();
	$it      = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( QRMS_PLUGIN_DIR . 'modules', FilesystemIterator::SKIP_DOTS ) );
	foreach ( $it as $dosya ) {
		$yol = (string) $dosya;
		if ( '.php' !== substr( $yol, -4 ) || false !== strpos( $yol, 'qr-ceviri/includes/ui-stringler.php' ) ) {
			continue;
		}
		$kod = file_get_contents( $yol );
		if ( ! preg_match_all( $desen, $kod, $m, PREG_SET_ORDER | PREG_OFFSET_CAPTURE ) ) {
			continue;
		}
		foreach ( $m as $x ) {
			$fn = $x[2][0];
			if ( 't' === $fn || 'cevir' === $fn ) {
				// Yalnızca Restoran Menü'nün $this->t() / self::cevir() yardımcıları (ui_string).
				if ( '' === $x[1][0] || false === strpos( $yol, 'restoran-menu' ) ) {
					continue;
				}
				$tip = 'ui_string';
			} elseif ( 'rma_ceviri_modul' === $fn ) {
				$tip = $x[3][0];
			} else {
				$tip = $sarmal[ $fn ];
			}
			if ( '' === $tip ) {
				continue;
			}
			// Doküman bloklarındaki örnekleri atla ("…" gibi yer tutucular).
			$satir_basi = strrpos( substr( $kod, 0, $x[0][1] ), "\n" );
			$satir      = substr( $kod, false === $satir_basi ? 0 : $satir_basi, 40 );
			if ( preg_match( '/^\s*\*/', ltrim( $satir, "\n" ) ) ) {
				continue;
			}
			$metin = str_replace( array( "\\'", '\\\\' ), array( "'", '\\' ), $x[4][0] );
			if ( ! isset( $bulunan[ $tip ][ $metin ] ) ) {
				$bulunan[ $tip ][ $metin ] = str_replace( QRMS_PLUGIN_DIR, '', $yol );
			}
		}
	}

	return $bulunan;
}

qrms_test(
	'koddaki her sabit çeviri metni kendi kataloğunda (CSV\'ye çıkabilir)',
	function () {
		qrms_test_ceviri_kancalari();
		$katalog              = rma_ceviri_modul_kaynak_metinleri();
		$katalog['ui_string'] = array_values( rma_ceviri_ui_stringleri() );

		$eksik = array();
		foreach ( qrms_test_ceviri_sabit_metinleri() as $tip => $metinler ) {
			if ( ! isset( $katalog[ $tip ] ) ) {
				continue;
			}
			$var = array_flip( array_map( 'strval', $katalog[ $tip ] ) );
			foreach ( $metinler as $metin => $yer ) {
				if ( ! isset( $var[ $metin ] ) ) {
					$eksik[] = $tip . ': "' . $metin . '" (' . $yer . ')';
				}
			}
		}

		qrms_assert_same( array(), $eksik, 'katalog dışı metin yok' );
	}
);

qrms_test(
	'yöneticinin yazdığı form adım etiketleri review kataloğuna girer; varsayılan kalıp numarasız çevrilir',
	function () {
		qrms_test_ceviri_kancalari();
		$eski = get_option( 'qrm_settings', null );
		update_option(
			'qrm_settings',
			array_merge(
				(array) $eski,
				array(
					'qrm_step_labels'        => array( 'rating' => 'Bizi Puanlayın.' ),
					'qrm_review_form_layout' => array( 'step_labels' => array( 2 => 'İletişim Bilgileriniz.' ) ),
				)
			)
		);

		$review = rma_ceviri_modul_kaynak_metinleri()['review'];
		qrms_assert_true( in_array( 'Bizi Puanlayın.', $review, true ), 'Puanlama ayarı etiketi' );
		qrms_assert_true( in_array( 'İletişim Bilgileriniz.', $review, true ), 'form düzeni etiketi' );
		qrms_assert_true( in_array( '%d. Adım', $review, true ), 'varsayılan kalıp katalogda' );

		qrms_assert_same( '3. Adım', qrm_cf_step_label( 3, array() ), 'kalıp çevrilip numara basılır' );

		if ( null === $eski ) {
			delete_option( 'qrm_settings' );
		} else {
			update_option( 'qrm_settings', $eski );
		}
	}
);

qrms_test(
	'Restoran Menü: filtre/porsiyon/servis metinleri ve yönetici ekstra/rozet adları ui_string kataloğunda',
	function () {
		qrms_test_ceviri_kancalari();
		update_option(
			RMA_Ekstra::OPTION,
			array(
				array(
					'id'      => 'sos',
					'ad'      => 'Soslar',
					'urunler' => array( array( 'ad' => 'Acı Sos', 'fiyat' => 10 ) ),
				),
			)
		);
		update_option(
			RMA_Ozel_Rozet::OPTION,
			array( array( 'slug' => 'sef', 'ad' => 'Şefin Seçimi', 'ikon' => '', 'renk' => '#c9a84c' ) )
		);

		$ui = array_values( rma_ceviri_ui_stringleri() );
		foreach ( array( 'Sırala', 'Ekstra ekle', 'Her gün', 'Pazartesi', 'Az Acı', 'Popüler', 'Dana Eti', 'Soslar', 'Acı Sos', 'Şefin Seçimi' ) as $m ) {
			qrms_assert_true( in_array( $m, $ui, true ), 'katalogda: ' . $m );
		}
		qrms_assert_same( count( $ui ), count( array_unique( $ui ) ), 'mükerrer metin yok' );

		delete_option( RMA_Ekstra::OPTION );
		delete_option( RMA_Ozel_Rozet::OPTION );
	}
);

qrms_test(
	'galeri bölüm başlık/açıklamaları gallery kataloğuna girer',
	function () {
		$php = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-galeri/module.php' );
		qrms_assert_contains( "add_filter( 'rma_ceviri_modul_kaynak_metinleri', 'qrms_galeri_ceviri_katalogu' )", $php, 'kanca' );
		qrms_assert_contains( "'_qrmgm_desc'", $php, 'meta açıklama da' );

		$katalog = qrms_galeri_ceviri_katalogu( array( 'gallery' => array( 'Tümü' ) ) );
		qrms_assert_true( in_array( 'Tümü', $katalog['gallery'], true ), 'mevcut korunur' );
	}
);

qrms_test(
	'özel chatbot mesajı option defterinden, varsayılanı chat kataloğundan çevrilir',
	function () {
		delete_option( 'qmo_chatbot_banned_msg' );
		$varsayilan = qmo_chatbot_yeni_varsayilanlar()['qmo_chatbot_banned_msg'];
		qrms_assert_same( $varsayilan, qmo_chatbot_ayar_cevir( 'qmo_chatbot_banned_msg' ), 'çeviri yokken Türkçe varsayılan' );
		qrms_assert_true( in_array( $varsayilan, rma_ceviri_modul_kaynak_metinleri()['chat'], true ), 'varsayılan chat kataloğunda' );

		update_option( 'qmo_chatbot_banned_msg', 'Bu konuyu konuşamıyoruz.' );
		qrms_assert_same( 'Bu konuyu konuşamıyoruz.', qmo_chatbot_ayar_cevir( 'qmo_chatbot_banned_msg' ), 'özel metin (çeviri yoksa aynen)' );
		qrms_assert_true( isset( rma_ceviri_option_defteri()['qmo_chatbot_banned_msg'] ), 'özel metin CSV\'ye çıkar' );
		delete_option( 'qmo_chatbot_banned_msg' );
	}
);

qrms_test(
	'metin değiştirici: anahtar kelime ortasında eşleşmez; en uzun önce, yeniden tarama yok',
	function () {
		require_once QRMS_PLUGIN_DIR . 'modules/qr-ceviri/includes/degistirici.php';

		$sozluk = array(
			'En Az Karb'  => 'Lowest Carb',
			'Sepet'       => 'Cart',
			'Sepete Ekle' => 'Add to cart',
			'Kapat'       => 'Close',
		);

		// Canlı hata: "Lowest Carbonhidratlı".
		qrms_assert_same( 'En Az Karbonhidratlı', rma_ceviri_metin_degistir( 'En Az Karbonhidratlı', $sozluk ), 'kelime ortası korunur' );
		qrms_assert_same( 'Close', rma_ceviri_metin_degistir( 'Kapat', $sozluk ), 'tam eşleşme' );
		qrms_assert_same( '  Close ', rma_ceviri_metin_degistir( '  Kapat ', $sozluk ), 'çevre boşluğu korunur' );
		qrms_assert_same( 'Add to cart · Cart', rma_ceviri_metin_degistir( 'Sepete Ekle · Sepet', $sozluk ), 'en uzun önce, iki eşleşme' );
		qrms_assert_same( 'Lowest Carb (kcal)', rma_ceviri_metin_degistir( 'En Az Karb (kcal)', $sozluk ), 'noktalama sınırı eşleşir' );
		qrms_assert_same( 'Sepetiniz', rma_ceviri_metin_degistir( 'Sepetiniz', $sozluk ), 'Türkçe ek almış kelime bozulmaz' );

		$html = '<div class="sepet" data-x="Sepet"><span>Sepet</span> <b>En Az Karbonhidratlı</b><script>var a="Sepet";</script></div>';
		qrms_assert_same(
			'<div class="sepet" data-x="Sepet"><span>Cart</span> <b>En Az Karbonhidratlı</b><script>var a="Sepet";</script></div>',
			rma_ceviri_html_degistir( $html, $sozluk ),
			'yalnız metin düğümleri, script dokunulmaz'
		);
	}
);

qrms_test(
	'CSV: dışa aktarma koruma tırnağı içe aktarmada geri alınır',
	function () {
		require_once QRMS_PLUGIN_DIR . 'modules/qr-ceviri/includes/csv-export.php';

		foreach ( array( '-{n} ₺', '=1+1', '+90 555', '@etiket', 'Normal metin', "'alıntı" ) as $ham ) {
			qrms_assert_same( $ham, rma_ceviri_csv_hucre_coz( rma_ceviri_csv_hucre_kacir( $ham ) ), 'gidiş-dönüş: ' . $ham );
		}
		qrms_assert_same( "'-{n} ₺", rma_ceviri_csv_hucre_kacir( '-{n} ₺' ), 'dışa aktarma hâlâ korur' );

		$imp = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-ceviri/includes/csv-import.php' );
		qrms_assert_contains( "array_map( 'rma_ceviri_csv_hucre_coz', \$satir )", $imp, 'içe aktarma satırı çözer' );
	}
);

qrms_test(
	'footer menü başlığı ve metin blokları çeviriden geçer ve kataloğa girer',
	function () {
		require_once QRMS_PLUGIN_DIR . 'modules/header-footer-builder/module.php';
		add_filter( 'rma_ceviri_ui_metinleri', 'qrms_hfb_ceviri_katalogu' );

		update_option(
			'hfb_footer_options',
			array(
				'layout' => array(
					'rows' => array(
						array(
							'cols' => array(
								array(
									'blocks' => array(
										array( 'type' => 'menu', 'title' => 'Kurumsal', 'menu_id' => 3 ),
										array( 'type' => 'text', 'content' => 'Dünya Mutfağına Hoşgeldiniz' ),
										array( 'type' => 'logo' ),
									),
								),
							),
						),
					),
				),
			)
		);

		$ui = array_values( rma_ceviri_ui_stringleri() );
		qrms_assert_true( in_array( 'Kurumsal', $ui, true ), 'menü başlığı katalogda' );
		qrms_assert_true( in_array( 'Dünya Mutfağına Hoşgeldiniz', $ui, true ), 'metin bloğu katalogda' );

		$kod = file_get_contents( QRMS_PLUGIN_DIR . 'modules/header-footer-builder/includes/trait-frontend.php' );
		qrms_assert_contains( '$content = $this->hfb_cevir_ui( $content );', $kod, 'metin bloğu çevrilir' );
		qrms_assert_contains( "\$title   = '' !== \$title ? \$this->hfb_cevir_ui( \$title ) : '';", $kod, 'menü başlığı çevrilir' );

		delete_option( 'hfb_footer_options' );
	}
);

qrms_test(
	'RTL: ön yüzde ekran dışına -9999px itilen öğe yok; banner izi LTR',
	function () {
		// left:-9999px sağdan sola dillerde kaydırılabilir taşma yaratır
		// (canlı: Arapça'da belge 10.389px, mobilde ekran simsiyah).
		$dosyalar = array(
			'modules/yorum-feedback/includes/frontend/form-render.php',
			'modules/yorum-feedback/includes/rewards/popup-render.php',
			'modules/yorum-feedback/includes/forms/render.php',
		);
		foreach ( $dosyalar as $dosya ) {
			qrms_assert_false( strpos( file_get_contents( QRMS_PLUGIN_DIR . $dosya ), '-9999px' ), 'ekran dışı konum yok: ' . $dosya );
		}
		qrms_assert_contains( 'clip-path: inset(50%)', file_get_contents( QRMS_PLUGIN_DIR . 'modules/yorum-feedback/includes/frontend/form-render.php' ), 'tuzak alanı kırpılarak gizli' );
		qrms_assert_contains( '[dir="rtl"] .qmo-banner-root .qmo-banner-track', file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/frontend-banner-slider.css' ), 'banner RTL' );
	}
);
