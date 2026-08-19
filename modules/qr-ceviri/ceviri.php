<?php
/**
 * Plugin Name: QRMenu Premium Dropdown Translator
 * Description: CSV ile toplu yönetilen, veritabanı tabanlı çoklu dil çeviri sistemi. Sunucu tarafında render eder (?lang=), çeviri API'sine bağlanmaz.
 * Version: 2.0.0
 * Author: QRMenu Official
 *
 * v2.0.0 ile Google Translate widget'ı kaldırıldı. Artık çeviriler
 * {prefix}rma_translations tablosunda tutulur, CSV ile dışa/içe aktarılır ve
 * sayfa sunucu tarafında seçili dilde basılır. Böylece arama motorları
 * çevrilmiş içeriği görür ve fiyat/sayı biçimleri bozulmaz.
 *
 * Not: Menü eklentisinin (RMA) kaynağına dokunulmaz. Çeviri, WordPress
 * çıktısı üzerinde metin düğümü bazlı değiştirme ile uygulanır; kaynak
 * eriştiğinde render noktaları rma_translate_field() ile doğrudan
 * beslenebilir (bkz. includes/sozluk.php).
 *
 * @package QRMenu_Ceviri
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'RMA_CEVIRI_VERSION' ) ) {
	define( 'RMA_CEVIRI_VERSION', '2.0.0' );
}
if ( ! defined( 'RMA_CEVIRI_FILE' ) ) {
	define( 'RMA_CEVIRI_FILE', __FILE__ );
}
if ( ! defined( 'RMA_CEVIRI_DIR' ) ) {
	define( 'RMA_CEVIRI_DIR', plugin_dir_path( __FILE__ ) );
}
if ( ! defined( 'RMA_CEVIRI_URL' ) ) {
	define( 'RMA_CEVIRI_URL', plugin_dir_url( __FILE__ ) );
}

/** Dil cookie'sinin adı — sepet.js ve RMA tarafı da bu adı bilir. */
if ( ! defined( 'RMA_CEVIRI_COOKIE' ) ) {
	define( 'RMA_CEVIRI_COOKIE', 'rma_lang' );
}

/**
 * Çekirdek — sıralama önemli: önce veri katmanı, sonra dil/sözlük,
 * sonra çıktıya dokunan parçalar, en son arayüz.
 */
require_once RMA_CEVIRI_DIR . 'includes/class-rma-ceviri-tablo.php';
require_once RMA_CEVIRI_DIR . 'includes/dil.php';
require_once RMA_CEVIRI_DIR . 'includes/degistirici.php';
require_once RMA_CEVIRI_DIR . 'includes/sozluk.php';
require_once RMA_CEVIRI_DIR . 'includes/ui-stringler.php';
require_once RMA_CEVIRI_DIR . 'includes/elementor-tarama.php';
require_once RMA_CEVIRI_DIR . 'includes/kaynaklar.php';
require_once RMA_CEVIRI_DIR . 'includes/metin-toplama.php';

/* Ön yüz */
require_once RMA_CEVIRI_DIR . 'includes/frontend.php';
require_once RMA_CEVIRI_DIR . 'includes/shortcodes.php';

/* Yönetim arayüzü ve CSV uçları (admin-post.php de is_admin() sayılır) */
if ( is_admin() ) {
	require_once RMA_CEVIRI_DIR . 'includes/csv-export.php';
	require_once RMA_CEVIRI_DIR . 'includes/csv-import.php';
	require_once RMA_CEVIRI_DIR . 'includes/admin/admin-sayfa.php';
}

/* =====================================================================
   DİL LİSTESİ — geriye dönük uyumluluk için isim ve içerik korunuyor
===================================================================== */
if ( ! function_exists( 'qrmenu_get_langs' ) ) {
	/**
	 * Desteklenen tüm diller.
	 *
	 * @return array<string,array{name:string,flag:string}>
	 */
	function qrmenu_get_langs() {
		return array(
			'tr'    => array(
				'name' => 'Türkçe',
				'flag' => '🇹🇷',
			),
			'en'    => array(
				'name' => 'English',
				'flag' => '🇬🇧',
			),
			'de'    => array(
				'name' => 'Deutsch',
				'flag' => '🇩🇪',
			),
			'fr'    => array(
				'name' => 'Français',
				'flag' => '🇫🇷',
			),
			'es'    => array(
				'name' => 'Español',
				'flag' => '🇪🇸',
			),
			'it'    => array(
				'name' => 'Italiano',
				'flag' => '🇮🇹',
			),
			'ru'    => array(
				'name' => 'Русский',
				'flag' => '🇷🇺',
			),
			'ar'    => array(
				'name' => 'العربية',
				'flag' => '🇸🇦',
			),
			'zh-CN' => array(
				'name' => '中文',
				'flag' => '🇨🇳',
			),
			'ja'    => array(
				'name' => '日本語',
				'flag' => '🇯🇵',
			),
			'ko'    => array(
				'name' => '한국어',
				'flag' => '🇰🇷',
			),
			'pt'    => array(
				'name' => 'Português',
				'flag' => '🇵🇹',
			),
			'nl'    => array(
				'name' => 'Nederlands',
				'flag' => '🇳🇱',
			),
			'el'    => array(
				'name' => 'Ελληνικά',
				'flag' => '🇬🇷',
			),
			'sv'    => array(
				'name' => 'Svenska',
				'flag' => '🇸🇪',
			),
			'pl'    => array(
				'name' => 'Polski',
				'flag' => '🇵🇱',
			),
			'id'    => array(
				'name' => 'Bahasa Indonesia',
				'flag' => '🇮🇩',
			),
			'hi'    => array(
				'name' => 'हिन्दी',
				'flag' => '🇮🇳',
			),
			'uk'    => array(
				'name' => 'Українська',
				'flag' => '🇺🇦',
			),
			'ro'    => array(
				'name' => 'Română',
				'flag' => '🇷🇴',
			),
			'cs'    => array(
				'name' => 'Čeština',
				'flag' => '🇨🇿',
			),
			'hu'    => array(
				'name' => 'Magyar',
				'flag' => '🇭🇺',
			),
			'th'    => array(
				'name' => 'ไทย',
				'flag' => '🇹🇭',
			),
			'vi'    => array(
				'name' => 'Tiếng Việt',
				'flag' => '🇻🇳',
			),
			'he'    => array(
				'name' => 'עברית',
				'flag' => '🇮🇱',
			),
			'fa'    => array(
				'name' => 'فارسی',
				'flag' => '🇮🇷',
			),
			'bg'    => array(
				'name' => 'Български',
				'flag' => '🇧🇬',
			),
			'da'    => array(
				'name' => 'Dansk',
				'flag' => '🇩🇰',
			),
			'fi'    => array(
				'name' => 'Suomi',
				'flag' => '🇫🇮',
			),
			'no'    => array(
				'name' => 'Norsk',
				'flag' => '🇳🇴',
			),
		);
	}
}

/**
 * Aktif dillerin varsayılanı — birden çok yerde okunuyor, tek yerde dursun.
 *
 * @return string[]
 */
if ( ! function_exists( 'rma_ceviri_varsayilan_diller' ) ) {
	function rma_ceviri_varsayilan_diller() {
		return array( 'tr', 'en', 'de', 'fr', 'es', 'ar', 'ru' );
	}
}

/**
 * Yöneticinin açtığı diller. 'tr' her zaman listededir (orijinal dil).
 *
 * @return string[]
 */
if ( ! function_exists( 'rma_ceviri_aktif_diller' ) ) {
	function rma_ceviri_aktif_diller() {
		$aktif = get_option( 'qrmenu_active_langs', rma_ceviri_varsayilan_diller() );
		if ( ! is_array( $aktif ) ) {
			$aktif = rma_ceviri_varsayilan_diller();
		}

		$tumu  = qrmenu_get_langs();
		$aktif = array_values( array_intersect( $aktif, array_keys( $tumu ) ) );

		if ( ! in_array( 'tr', $aktif, true ) ) {
			array_unshift( $aktif, 'tr' );
		}

		return $aktif;
	}
}

/**
 * Çeviri yazılabilecek diller — aktif diller eksi 'tr'.
 * Orijinal metin zaten WordPress'te durduğu için 'tr' tabloya yazılmaz.
 *
 * @return string[]
 */
if ( ! function_exists( 'rma_ceviri_hedef_diller' ) ) {
	function rma_ceviri_hedef_diller() {
		return array_values( array_diff( rma_ceviri_aktif_diller(), array( 'tr' ) ) );
	}
}

/**
 * Aktivasyon: çeviri tablosunu oluştur.
 */
function rma_ceviri_aktivasyon() {
	RMA_Ceviri_Tablo::tablo_kur();
	update_option( 'rma_ceviri_db_version', RMA_CEVIRI_VERSION, false );
}
register_activation_hook( __FILE__, 'rma_ceviri_aktivasyon' );

/**
 * Eklenti güncellendiğinde şemayı sessizce tazele.
 * (Aktivasyon hook'u güncellemelerde çalışmaz — QMO'daki desenin aynısı.)
 */
add_action( 'plugins_loaded', 'rma_ceviri_surum_kontrol', 25 );
function rma_ceviri_surum_kontrol() {
	if ( get_option( 'rma_ceviri_db_version' ) === RMA_CEVIRI_VERSION ) {
		return;
	}

	RMA_Ceviri_Tablo::tablo_kur();
	rma_ceviri_onbellek_temizle();
	update_option( 'rma_ceviri_db_version', RMA_CEVIRI_VERSION, false );
}
