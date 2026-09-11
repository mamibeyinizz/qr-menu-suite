<?php
/**
 * Ürün ekle / düzenle ekranının arayüz katmanı.
 *
 * Yalnızca GÖRSEL bir katmandır: hiçbir meta anahtarı, taksonomi, nonce ya da
 * kayıt akışı burada tanımlanmaz. Yaptığı iş, `rma_menu_item` düzenleme
 * ekranına kendi stil/betik dosyalarını kuyruğa almak, gövdeye kapsam sınıfını
 * eklemek ve başlığın hemen altına "Temel Bilgiler" kartının iskeletini
 * basmaktır. Kartın içi istemci tarafında, WordPress'in KENDİ kutuları
 * (kategori ve öne çıkarılan görsel) olduğu gibi taşınarak doldurulur —
 * böylece çekirdeğin olay bağları ve form alanları aynen korunur.
 *
 * @package QR_Menu_Suite
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'RMA_Urun_Editor' ) ) :

class RMA_Urun_Editor {

	/** Ekranın kapsam sınıfı — tüm yeni CSS bu sınıfın altındadır. */
	const KAPSAM = 'qrms-product-editor';

	/**
	 * Kancalar.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_filter( 'admin_body_class', array( __CLASS__, 'body_class' ) );
		add_action( 'edit_form_top', array( __CLASS__, 'basligi_tamamla' ) );
		add_action( 'edit_form_after_title', array( __CLASS__, 'temel_bilgiler_karti' ) );
	}

	/**
	 * Ürün ekle/düzenle ekranında mıyız?
	 *
	 * @return bool
	 */
	public static function ekran_mi() {
		if ( ! function_exists( 'get_current_screen' ) ) {
			return false;
		}

		$screen = get_current_screen();

		return $screen && 'post' === $screen->base && 'rma_menu_item' === $screen->post_type;
	}

	/**
	 * Ekranın stil ve betikleri.
	 *
	 * Varlıklar yalnızca bu ekranda yüklenir; başka hiçbir yönetim sayfası
	 * etkilenmez. Yeni bir kütüphane/CDN/font eklenmez.
	 *
	 * @return void
	 */
	public static function assets() {
		if ( ! self::ekran_mi() ) {
			return;
		}

		$url  = defined( 'QRMS_PLUGIN_URL' ) ? QRMS_PLUGIN_URL . 'modules/restoran-menu/' : RMA_PLUGIN_URL;
		$goreli = 'modules/restoran-menu/';

		$surum = static function ( $yol ) use ( $goreli ) {
			return class_exists( 'QRMS_Helpers' ) ? QRMS_Helpers::asset_version( $goreli . $yol ) : null;
		};

		wp_enqueue_style(
			'rma-urun-editor',
			$url . 'assets/css/urun-editor.css',
			array(),
			$surum( 'assets/css/urun-editor.css' )
		);

		wp_enqueue_script(
			'rma-urun-editor',
			$url . 'assets/js/urun-editor.js',
			array(),
			$surum( 'assets/js/urun-editor.js' ),
			true
		);

		wp_localize_script(
			'rma-urun-editor',
			'RMA_URUN_EDITOR',
			array(
				'i18n' => array(
					'sec'       => __( 'Seçin…', 'qrms' ),
					'ara'       => __( 'Ara…', 'qrms' ),
					'kaldir'    => __( 'Kaldır', 'qrms' ),
					'temizle'   => __( 'Seçimi temizle', 'qrms' ),
					'sonucYok'  => __( 'Sonuç yok', 'qrms' ),
					'secildi'   => __( '%d seçildi', 'qrms' ),
					'urunAdi'   => __( 'Ürün adı', 'qrms' ),
					'aciklama'  => __( 'Açıklama', 'qrms' ),
					'aciklamaNot' => __( 'Misafirlerin menüde göreceği ürün açıklaması.', 'qrms' ),
					'kategori'    => __( 'Kategori', 'qrms' ),
					'kategoriSec' => __( 'Kategori seçin…', 'qrms' ),
					'kategoriAra' => __( 'Kategori ara…', 'qrms' ),
					'gorsel'    => __( 'Ürün Görseli', 'qrms' ),
					'fiyat'     => __( 'Fiyat', 'qrms' ),
				),
			)
		);
	}

	/**
	 * Gövde sınıfı — yeni CSS'in kapsamı.
	 *
	 * @param string $classes Mevcut sınıflar.
	 * @return string
	 */
	public static function body_class( $classes ) {
		if ( ! self::ekran_mi() ) {
			return $classes;
		}

		return trim( $classes . ' ' . self::KAPSAM );
	}

	/**
	 * Başlığın altındaki kısa açıklama.
	 *
	 * @param WP_Post $post Ürün.
	 * @return void
	 */
	public static function basligi_tamamla( $post ) {
		if ( ! $post || 'rma_menu_item' !== $post->post_type ) {
			return;
		}

		echo '<p class="qrms-pe-ust-not">' . esc_html__( 'Menünüzde görünecek ürün bilgilerini aşağıdan düzenleyin.', 'qrms' ) . '</p>';
	}

	/**
	 * "Temel Bilgiler" kartının iskeleti.
	 *
	 * Başlıkla açıklama alanı arasına girer. İçi boş gelir ve `hidden`
	 * durumdadır: fiyat alanı ile WordPress'in kategori / öne çıkarılan görsel
	 * kutuları istemcide buraya TAŞINIR. Betik çalışmazsa kart hiç görünmez,
	 * alanlar kendi özgün yerlerinde çalışmaya devam eder.
	 *
	 * @param WP_Post $post Ürün.
	 * @return void
	 */
	public static function temel_bilgiler_karti( $post ) {
		if ( ! $post || 'rma_menu_item' !== $post->post_type ) {
			return;
		}
		?>
		<div class="qrms-pe-kart qrms-pe-temel" id="qrms-pe-temel" hidden>
			<div class="qrms-pe-kart-bas">
				<span class="qrms-pe-sec-ikon" aria-hidden="true">🍽</span>
				<span class="qrms-pe-kart-baslik"><?php esc_html_e( 'Temel Bilgiler', 'qrms' ); ?></span>
			</div>
			<div class="qrms-pe-temel-izgara">
				<div class="qrms-pe-yuva" data-qrms-pe-yuva="fiyat"></div>
				<div class="qrms-pe-yuva" data-qrms-pe-yuva="kategori"></div>
				<div class="qrms-pe-yuva" data-qrms-pe-yuva="gorsel"></div>
			</div>
		</div>
		<?php
	}
}

endif;
