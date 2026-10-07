<?php
/**
 * Kampanya Banner görsel/attachment bağlama testleri (1-2-14).
 *
 * Canvas üretimi, Media Library seçimi ve frontend payload aynı
 * `_qmo_banner_gorsel_id` + `QMO_Banner_CPT::is_valid_image` yolunu kullanır.
 *
 * Yükleyen: tests/test-suite.php — doğrudan çalıştırmayın.
 *
 * @package QR_Menu_Suite
 */

if ( ! defined( 'QMO_PLUGIN_DIR' ) ) {
	define( 'QMO_PLUGIN_DIR', QRMS_PLUGIN_DIR . 'modules/restoran-menu/' );
}
if ( ! defined( 'QMO_PLUGIN_URL' ) ) {
	define( 'QMO_PLUGIN_URL', 'https://example.test/wp-content/plugins/qr-menu-suite/modules/restoran-menu/' );
}

require_once QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/class-banner-slider-settings.php';
require_once QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/class-banner-kirpma.php';
require_once QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/admin-cpt-banner.php';
require_once QRMS_PLUGIN_DIR . 'modules/_qmo-ortak/helpers.php';
require_once QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/shortcode-banner-slider.php';
require_once QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/trait-kampanya-banner-admin.php';

if ( ! class_exists( 'RMA_Test_Banner_Gorsel_Harness' ) ) {
	class RMA_Test_Banner_Gorsel_Harness {
		use RMA_Kampanya_Banner_Admin_Trait;

		/**
		 * @param string $slug Sayfa slug.
		 * @param array  $args Query.
		 * @return string
		 */
		public function admin_page_url( $slug, $args = array() ) {
			return 'https://restoran.test/wp-admin/admin.php?page=' . rawurlencode( (string) $slug ) . ( $args ? '&' . http_build_query( $args ) : '' );
		}
	}
}

/**
 * 1×1 PNG (gerçek imza — üretim ucu PNG dosya başlığını doğrular).
 *
 * @return string
 */
function qrms_banner_png_data_uri() {
	$ham = base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==', true );

	return 'data:image/png;base64,' . base64_encode( $ham );
}

/**
 * Görsel ek kaydı.
 *
 * @param int    $id   Kimlik.
 * @param string $mime MIME.
 * @return void
 */
function qrms_banner_gorsel_ek( $id, $mime = 'image/jpeg' ) {
	qrms_test_yaziyi_yaz(
		$id,
		array(
			'post_type'      => 'attachment',
			'post_title'     => 'banner-' . $id,
			'post_status'    => 'inherit',
			'post_mime_type' => $mime,
		)
	);
	$GLOBALS['qrms_test']['attachment_meta'][ $id ] = array(
		'width'  => 1600,
		'height' => 900,
	);
}

echo "\nKampanya Banner — görsel attachment akışı\n";

qrms_test(
	'is_valid_image: görsel kabul, PDF/silinmiş/geçersiz reddedilir',
	function () {
		qrms_banner_gorsel_ek( 70, 'image/jpeg' );
		qrms_assert_true( QMO_Banner_CPT::is_valid_image( 70 ), 'jpeg ek' );

		qrms_banner_gorsel_ek( 71, 'application/pdf' );
		qrms_assert_true( ! QMO_Banner_CPT::is_valid_image( 71 ), 'PDF reddedilir' );

		qrms_assert_true( ! QMO_Banner_CPT::is_valid_image( 0 ), 'sıfır' );
		qrms_assert_true( ! QMO_Banner_CPT::is_valid_image( 999999 ), 'olmayan ID' );

		$GLOBALS['qrms_test']['post_types'][ 80 ] = QMO_Banner_CPT::POST_TYPE;
		qrms_assert_true( ! QMO_Banner_CPT::is_valid_image( 80 ), 'banner CPT ek değildir' );
	}
);

qrms_test(
	'save_meta: görsel seç, değiştir, kaldır; geçersiz ID meta yazmaz',
	function () {
		$banner = 201;
		qrms_test_yaziyi_yaz(
			$banner,
			array(
				'post_type'   => QMO_Banner_CPT::POST_TYPE,
				'post_title'  => 'Yaz',
				'post_status' => 'publish',
			)
		);
		qrms_banner_gorsel_ek( 90 );
		qrms_banner_gorsel_ek( 91 );

		$_POST[ QMO_Banner_CPT::NONCE_FIELD ] = wp_create_nonce( QMO_Banner_CPT::NONCE_ACTION );
		$_POST[ QMO_Banner_CPT::META_IMAGE ]  = '90';

		QMO_Banner_CPT::save_meta( $banner );
		qrms_assert_same( 90, (int) get_post_meta( $banner, QMO_Banner_CPT::META_IMAGE, true ), 'ilk görsel bağlandı' );

		$_POST[ QMO_Banner_CPT::NONCE_FIELD ] = wp_create_nonce( QMO_Banner_CPT::NONCE_ACTION );
		$_POST[ QMO_Banner_CPT::META_IMAGE ]  = '91';
		QMO_Banner_CPT::save_meta( $banner );
		qrms_assert_same( 91, (int) get_post_meta( $banner, QMO_Banner_CPT::META_IMAGE, true ), 'yeni görsel eskisinin yerini aldı' );

		$_POST[ QMO_Banner_CPT::NONCE_FIELD ] = wp_create_nonce( QMO_Banner_CPT::NONCE_ACTION );
		$_POST[ QMO_Banner_CPT::META_IMAGE ]  = '0';
		QMO_Banner_CPT::save_meta( $banner );
		qrms_assert_same( '', get_post_meta( $banner, QMO_Banner_CPT::META_IMAGE, true ), 'kaldırınca meta silinir' );

		update_post_meta( $banner, QMO_Banner_CPT::META_IMAGE, 91 );
		$_POST[ QMO_Banner_CPT::NONCE_FIELD ] = wp_create_nonce( QMO_Banner_CPT::NONCE_ACTION );
		$_POST[ QMO_Banner_CPT::META_IMAGE ]  = '71';
		qrms_banner_gorsel_ek( 71, 'application/pdf' );
		QMO_Banner_CPT::save_meta( $banner );
		qrms_assert_same( '', get_post_meta( $banner, QMO_Banner_CPT::META_IMAGE, true ), 'PDF yazılmaz, eski geçersiz bağ kopar' );
	}
);

qrms_test(
	'save_meta: nonce yok / yetki yok / görselsiz kayıt mevcut görseli korur veya siler kuralına uyar',
	function () {
		$banner = 202;
		qrms_test_yaziyi_yaz(
			$banner,
			array(
				'post_type'   => QMO_Banner_CPT::POST_TYPE,
				'post_title'  => 'Koruma',
				'post_status' => 'publish',
			)
		);
		qrms_banner_gorsel_ek( 92 );
		update_post_meta( $banner, QMO_Banner_CPT::META_IMAGE, 92 );

		$_POST[ QMO_Banner_CPT::META_IMAGE ] = '0';
		QMO_Banner_CPT::save_meta( $banner );
		qrms_assert_same( 92, (int) get_post_meta( $banner, QMO_Banner_CPT::META_IMAGE, true ), 'nonce yok: silinmez' );

		$_POST[ QMO_Banner_CPT::NONCE_FIELD ] = wp_create_nonce( QMO_Banner_CPT::NONCE_ACTION );
		$GLOBALS['qrms_test']['can_edit_post'][ $banner ] = false;
		$_POST[ QMO_Banner_CPT::META_IMAGE ] = '0';
		QMO_Banner_CPT::save_meta( $banner );
		qrms_assert_same( 92, (int) get_post_meta( $banner, QMO_Banner_CPT::META_IMAGE, true ), 'yetki yok: silinmez' );
	}
);

qrms_test(
	'olustur_kayit görseli META_IMAGE yazar; görselsiz ve PDF reddedilir',
	function () {
		qrms_banner_gorsel_ek( 93 );

		$bos = QMO_Banner_CPT::olustur_kayit( array( 'baslik' => 'A', 'gorsel_id' => 0 ) );
		qrms_assert_true( is_wp_error( $bos ), 'görselsiz hata' );

		qrms_banner_gorsel_ek( 94, 'application/pdf' );
		$pdf = QMO_Banner_CPT::olustur_kayit( array( 'baslik' => 'A', 'gorsel_id' => 94 ) );
		qrms_assert_true( is_wp_error( $pdf ), 'PDF hata' );

		$id = QMO_Banner_CPT::olustur_kayit(
			array(
				'baslik'    => 'Bahar',
				'gorsel_id' => 93,
				'link'      => 'https://ornek.test/kampanya',
			)
		);

		qrms_assert_true( is_int( $id ) && $id > 0, 'kayıt ID' );
		qrms_assert_same( QMO_Banner_CPT::POST_TYPE, get_post_type( $id ), 'CPT slug' );
		qrms_assert_same( 93, (int) get_post_meta( $id, QMO_Banner_CPT::META_IMAGE, true ), 'attachment bağlandı' );
		qrms_assert_same( 'https://ornek.test/kampanya', get_post_meta( $id, QMO_Banner_CPT::META_LINK, true ), 'link' );
	}
);

qrms_test(
	'frontend payload kaydedilen attachment URL kullanır; görselsiz ve silinmiş ek atlanır',
	function () {
		QMO_Banner_Slider_Settings::kaydet( array( 'oran' => '16:9' ) );

		qrms_banner_gorsel_ek( 95 );
		$a = QMO_Banner_CPT::olustur_kayit( array( 'baslik' => 'Aktif', 'gorsel_id' => 95 ) );

		$pasif = wp_insert_post(
			array(
				'post_type'   => QMO_Banner_CPT::POST_TYPE,
				'post_title'  => 'Taslak',
				'post_status' => 'draft',
			)
		);
		update_post_meta( $pasif, QMO_Banner_CPT::META_IMAGE, 95 );

		$silik = wp_insert_post(
			array(
				'post_type'   => QMO_Banner_CPT::POST_TYPE,
				'post_title'  => 'Kırık',
				'post_status' => 'publish',
			)
		);
		update_post_meta( $silik, QMO_Banner_CPT::META_IMAGE, 404404 );
		$GLOBALS['qrms_test']['missing_attachment_ids'][ 404404 ] = true;

		$gorselsiz = wp_insert_post(
			array(
				'post_type'   => QMO_Banner_CPT::POST_TYPE,
				'post_title'  => 'Boş',
				'post_status' => 'publish',
			)
		);
		unset( $gorselsiz );

		$liste = QMO_Shortcode_Banner_Slider::payloadlar();
		qrms_assert_same( 1, count( $liste ), 'yalnız yayın + geçerli görsel' );
		qrms_assert_same( wp_get_attachment_image_url( 95, 'full' ), $liste[0]['img'], 'frontend aynı ek URL' );
		qrms_assert_same( 'Aktif', $liste[0]['title'], 'başlık' );

		$html = QMO_Shortcode_Banner_Slider::render_shortcode( array() );
		qrms_assert_contains( $liste[0]['img'], $html, 'kısa kod src' );
		qrms_assert_false( strpos( $html, 'src=""' ), 'boş src yok' );
	}
);

qrms_test(
	'ajax satır kaydı: görsel değiştir/kaldır, geçersiz ID ve yetki',
	function () {
		$h = new RMA_Test_Banner_Gorsel_Harness();
		qrms_banner_gorsel_ek( 96 );
		qrms_banner_gorsel_ek( 97 );
		$banner = QMO_Banner_CPT::olustur_kayit( array( 'baslik' => 'Satır', 'gorsel_id' => 96 ) );

		$_POST['nonce']  = wp_create_nonce( 'qmo_banner_satir_kaydet' );
		$_POST['banner'] = (string) $banner;
		$_POST['gorsel'] = '97';
		$h->ajax_banner_satir_kaydet();
		qrms_assert_true( $GLOBALS['qrms_test']['json']['success'], 'değiştirme başarılı' );
		qrms_assert_same( 97, (int) get_post_meta( $banner, QMO_Banner_CPT::META_IMAGE, true ), '97 bağlandı' );

		$_POST['gorsel'] = '0';
		$h->ajax_banner_satir_kaydet();
		qrms_assert_same( '', get_post_meta( $banner, QMO_Banner_CPT::META_IMAGE, true ), 'kaldırıldı' );

		update_post_meta( $banner, QMO_Banner_CPT::META_IMAGE, 97 );
		qrms_banner_gorsel_ek( 98, 'application/pdf' );
		qrms_assert_true( ! QMO_Banner_CPT::is_valid_image( 98 ), 'PDF ek geçerli görsel değil' );
		$GLOBALS['qrms_test']['json'] = null;
		$_POST['nonce']  = wp_create_nonce( 'qmo_banner_satir_kaydet' );
		$_POST['banner'] = (string) $banner;
		$_POST['gorsel'] = '98';
		$h->ajax_banner_satir_kaydet();
		qrms_assert_true( empty( $GLOBALS['qrms_test']['json']['success'] ), 'PDF hata' );
		qrms_assert_same( 97, (int) get_post_meta( $banner, QMO_Banner_CPT::META_IMAGE, true ), 'eski görsel durur' );

		$_POST['banner'] = '12';
		$_POST['gorsel'] = '97';
		$h->ajax_banner_satir_kaydet();
		qrms_assert_true( empty( $GLOBALS['qrms_test']['json']['success'] ), 'geçersiz post ID' );

		$_POST['banner'] = (string) $banner;
		$GLOBALS['qrms_test']['can'] = false;
		$h->ajax_banner_satir_kaydet();
		qrms_assert_true( empty( $GLOBALS['qrms_test']['json']['success'] ), 'yetki yok' );
		qrms_assert_same( 403, (int) $GLOBALS['qrms_test']['json']['status'], '403' );
	}
);

qrms_test(
	'canvas üretimi: PNG attachment + banner META_IMAGE; başarısız kayıtta başarı yok',
	function () {
		$h = new RMA_Test_Banner_Gorsel_Harness();

		$_POST['nonce']  = 'x';
		$_POST['baslik'] = 'Canvas Kampanya';
		$_POST['gorsel'] = qrms_banner_png_data_uri();

		$h->ajax_banner_gorsel_olustur();

		qrms_assert_true( ! empty( $GLOBALS['qrms_test']['json']['success'] ), 'üretim başarılı' );
		$data = $GLOBALS['qrms_test']['json']['data'];
		$bid  = (int) $data['id'];
		$ek   = (int) get_post_meta( $bid, QMO_Banner_CPT::META_IMAGE, true );

		qrms_assert_true( $bid > 0, 'banner ID' );
		qrms_assert_true( QMO_Banner_CPT::is_valid_image( $ek ), 'üretilen ek görsel' );
		qrms_assert_same( 'image/png', get_post_mime_type( $ek ), 'png mime' );
		qrms_assert_same( QMO_Banner_CPT::POST_TYPE, get_post_type( $bid ), 'banner CPT' );
		qrms_assert_same( $bid, (int) $GLOBALS['qrms_test']['post_parent'][ $ek ], 'ek banner çocuğu' );
		qrms_assert_contains( 'yayına alındı', $data['message'], 'mesaj kayıt sonrası' );

		$liste = QMO_Shortcode_Banner_Slider::payloadlar();
		qrms_assert_true( count( $liste ) >= 1, 'frontend listede' );
		qrms_assert_same( wp_get_attachment_image_url( $ek, 'full' ), $liste[0]['img'], 'aynı attachment URL' );

		$GLOBALS['qrms_test']['json'] = null;
		$GLOBALS['qrms_test']['wp_insert_post_error_types'][ QMO_Banner_CPT::POST_TYPE ] = true;
		$_POST['baslik'] = 'Başarısız';
		$h->ajax_banner_gorsel_olustur();
		qrms_assert_true( empty( $GLOBALS['qrms_test']['json']['success'] ), 'kayıt hatasında success yok' );
		qrms_assert_contains( 'Kayıt oluşturulamadı', $GLOBALS['qrms_test']['json']['data']['message'], 'hata mesajı' );
	}
);

qrms_test(
	'üretim önizlemesi kayıtsızdır: geçersiz data URI attachment oluşturmaz',
	function () {
		$h = new RMA_Test_Banner_Gorsel_Harness();
		$_POST['baslik'] = 'Sahte';
		$_POST['gorsel'] = 'data:image/png;base64,AAAA';
		$h->ajax_banner_gorsel_olustur();
		qrms_assert_true( empty( $GLOBALS['qrms_test']['json']['success'] ), 'imza hatası' );

		$_POST['gorsel'] = 'http://evil.test/x.png';
		$h->ajax_banner_gorsel_olustur();
		qrms_assert_true( empty( $GLOBALS['qrms_test']['json']['success'] ), 'URL kabul edilmez' );

		$js = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/assets/js/banner-olustur.js' );
		qrms_assert_contains( "toDataURL('image/png')", $js, 'canvas PNG' );
		qrms_assert_contains( "json.success", $js, 'yalnızca success mesajı' );
		qrms_assert_false( strpos( $js, 'openai' ) !== false, 'harici AI yok' );
	}
);

qrms_test(
	'slider CPT meta alanları banner görsel meta ile çakışmaz',
	function () {
		$slide = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/admin-cpt-slide.php' );
		$cpt   = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/admin-cpt-banner.php' );

		qrms_assert_contains( "register_post_type( 'qmo_slide'", $slide, 'slider CPT' );
		qrms_assert_contains( "POST_TYPE    = 'qmo_banner_slide'", $cpt, 'banner CPT' );
		qrms_assert_false( strpos( $slide, '_qmo_banner_gorsel_id' ) !== false, 'slider banner görsel meta yazmaz' );
		qrms_assert_contains( "remove_action( 'save_post_qmo_slide'", $slide, 'slider save kancası duruyor' );
	}
);

qrms_test(
	'ajax üretim nonce/yetki ve geçersiz CPT',
	function () {
		$h = new RMA_Test_Banner_Gorsel_Harness();
		$GLOBALS['qrms_test']['can'] = false;
		$_POST['gorsel'] = qrms_banner_png_data_uri();
		$_POST['baslik'] = 'X';
		$h->ajax_banner_gorsel_olustur();
		qrms_assert_true( empty( $GLOBALS['qrms_test']['json']['success'] ), 'yetkisiz üretim yok' );
		qrms_assert_same( 403, (int) $GLOBALS['qrms_test']['json']['status'], '403' );
	}
);
