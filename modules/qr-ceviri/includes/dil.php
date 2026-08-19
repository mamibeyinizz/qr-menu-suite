<?php
/**
 * Aktif dilin çözümü, cookie yönetimi ve dil meta etiketleri.
 *
 * Dil her zaman URL'de görünür (?lang=en). Cookie yalnızca "bu ziyaretçi en
 * son hangi dili seçmişti" bilgisini taşır ve gerektiğinde tek seferlik bir
 * yönlendirme ile URL'ye taşınır. Sebep: sayfa cache'leri ve arama motorları
 * cookie'yi görmez — dil URL'de olmazsa EN ziyaretçiye TR sayfa servis
 * edilebilir ve çevrilmiş içerik indekslenmez.
 *
 * @package QRMenu_Ceviri
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Sağdan sola yazılan diller. */
if ( ! function_exists( 'rma_ceviri_rtl_diller' ) ) {
	function rma_ceviri_rtl_diller() {
		return array( 'ar', 'he', 'fa', 'ur' );
	}
}

/**
 * Dil kodu geçerli ve aktif mi?
 *
 * @param string $lang Dil kodu.
 * @return bool
 */
if ( ! function_exists( 'rma_ceviri_dil_gecerli_mi' ) ) {
	function rma_ceviri_dil_gecerli_mi( $lang ) {
		if ( ! is_string( $lang ) || '' === $lang ) {
			return false;
		}
		return in_array( $lang, rma_ceviri_aktif_diller(), true );
	}
}

/**
 * İstekten dil kodu oku (ham, doğrulanmamış).
 *
 * AJAX isteklerinde $_GET yerine $_REQUEST'e bakılır: RMA tarafı lang'ı POST
 * gövdesinde gönderebilir. Hiçbiri yoksa cookie devreye girer — cookie'yi
 * tarayıcı admin-ajax.php'ye de otomatik gönderdiği için menü kartları,
 * RMA'nın JS'ine hiç dokunmadan da doğru dilde döner.
 *
 * @return string
 */
if ( ! function_exists( 'rma_ceviri_istek_dili' ) ) {
	function rma_ceviri_istek_dili() {
		if ( isset( $_REQUEST['lang'] ) ) {
			return sanitize_text_field( wp_unslash( $_REQUEST['lang'] ) );
		}
		if ( isset( $_COOKIE[ RMA_CEVIRI_COOKIE ] ) ) {
			return sanitize_text_field( wp_unslash( $_COOKIE[ RMA_CEVIRI_COOKIE ] ) );
		}
		return 'tr';
	}
}

/**
 * Geçerli dil: ?lang → cookie → 'tr'.
 *
 * Sonuç istek boyunca sabittir (static). Geçersiz/kapalı dil 'tr'ye düşer.
 *
 * @return string
 */
if ( ! function_exists( 'rma_get_current_lang' ) ) {
	function rma_get_current_lang() {
		static $dil = null;

		if ( null !== $dil ) {
			return $dil;
		}

		$aday = rma_ceviri_istek_dili();
		$dil  = rma_ceviri_dil_gecerli_mi( $aday ) ? $aday : 'tr';

		return $dil;
	}
}

/**
 * Şu an TR dışında bir dil mi görüntüleniyor?
 *
 * @return bool
 */
if ( ! function_exists( 'rma_ceviri_aktif_mi' ) ) {
	function rma_ceviri_aktif_mi() {
		return 'tr' !== rma_get_current_lang();
	}
}

/**
 * Geçerli isteğin tam URL'i.
 *
 * @return string
 */
if ( ! function_exists( 'rma_ceviri_mevcut_url' ) ) {
	function rma_ceviri_mevcut_url() {
		$yol = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '/';
		return home_url( $yol );
	}
}

/**
 * Bir URL'in belirli dildeki karşılığı.
 *
 * @param string $lang Dil kodu.
 * @param string $url  Temel URL; boşsa geçerli istek.
 * @return string
 */
if ( ! function_exists( 'rma_ceviri_dil_url' ) ) {
	function rma_ceviri_dil_url( $lang, $url = '' ) {
		$url = $url ? $url : rma_ceviri_mevcut_url();

		if ( 'tr' === $lang ) {
			return remove_query_arg( 'lang', $url );
		}

		return add_query_arg( 'lang', $lang, $url );
	}
}

/**
 * Bu istekte dil mekanizması çalışmalı mı?
 * Yönetim, AJAX, REST, feed ve robots.txt dışarıda.
 *
 * @return bool
 */
if ( ! function_exists( 'rma_ceviri_on_yuz_istegi_mi' ) ) {
	function rma_ceviri_on_yuz_istegi_mi() {
		if ( is_admin() || wp_doing_ajax() || is_feed() || is_robots() ) {
			return false;
		}
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return false;
		}
		if ( defined( 'DOING_CRON' ) && DOING_CRON ) {
			return false;
		}
		return true;
	}
}

add_action( 'template_redirect', 'rma_ceviri_dil_yerlestir', 1 );
/**
 * Cookie'yi güncelle; gerekiyorsa dili URL'ye taşı.
 *
 * - ?lang geldiyse: cookie'yi 1 yıl set et.
 * - ?lang yok ama cookie TR dışı bir dil diyorsa: tek seferlik 302 ile
 *   ?lang=xx ekle. TR ziyaretçi (çoğunluk) hiçbir zaman yönlendirilmez.
 */
if ( ! function_exists( 'rma_ceviri_dil_yerlestir' ) ) {
	function rma_ceviri_dil_yerlestir() {
		if ( ! rma_ceviri_on_yuz_istegi_mi() ) {
			return;
		}

		$cookie = isset( $_COOKIE[ RMA_CEVIRI_COOKIE ] )
			? sanitize_text_field( wp_unslash( $_COOKIE[ RMA_CEVIRI_COOKIE ] ) )
			: '';

		if ( isset( $_GET['lang'] ) ) {
			$secilen = sanitize_text_field( wp_unslash( $_GET['lang'] ) );
			$secilen = rma_ceviri_dil_gecerli_mi( $secilen ) ? $secilen : 'tr';

			if ( $secilen !== $cookie && ! headers_sent() ) {
				setcookie( RMA_CEVIRI_COOKIE, $secilen, time() + YEAR_IN_SECONDS, COOKIEPATH ? COOKIEPATH : '/', COOKIE_DOMAIN, is_ssl(), false );
			}
			return;
		}

		// Yönlendirme kapatılabilir (ör. agresif sayfa cache'i olan kurulumlar).
		if ( ! get_option( 'rma_ceviri_url_yonlendir', 1 ) ) {
			return;
		}

		if ( 'tr' === $cookie || ! rma_ceviri_dil_gecerli_mi( $cookie ) ) {
			return;
		}

		if ( ! empty( $_POST ) || headers_sent() ) {
			return;
		}

		wp_safe_redirect( rma_ceviri_dil_url( $cookie ), 302 );
		exit;
	}
}

add_filter( 'language_attributes', 'rma_ceviri_html_dil_ozniteligi' );
/**
 * <html lang="…"> ve RTL diller için dir="rtl".
 *
 * @param string $ciktilar Mevcut öznitelikler.
 * @return string
 */
if ( ! function_exists( 'rma_ceviri_html_dil_ozniteligi' ) ) {
	function rma_ceviri_html_dil_ozniteligi( $ciktilar ) {
		if ( ! rma_ceviri_aktif_mi() ) {
			return $ciktilar;
		}

		$dil = rma_get_current_lang();

		// Mevcut lang/dir özniteliklerini temizleyip kendimizinkini koyuyoruz.
		$ciktilar = preg_replace( '/\s*(lang|dir)="[^"]*"/i', '', (string) $ciktilar );
		$ciktilar = trim( (string) $ciktilar );

		$yeni = 'lang="' . esc_attr( $dil ) . '"';
		if ( in_array( $dil, rma_ceviri_rtl_diller(), true ) ) {
			$yeni .= ' dir="rtl"';
		}

		return $ciktilar ? $ciktilar . ' ' . $yeni : $yeni;
	}
}

add_action( 'wp_head', 'rma_ceviri_hreflang_etiketleri', 2 );
/**
 * Arama motorlarına dil alternatiflerini bildir.
 * Sunucu tarafı çevirinin SEO değerini asıl bu görünür kılıyor.
 */
if ( ! function_exists( 'rma_ceviri_hreflang_etiketleri' ) ) {
	function rma_ceviri_hreflang_etiketleri() {
		if ( ! rma_ceviri_on_yuz_istegi_mi() || is_404() ) {
			return;
		}

		$temel = remove_query_arg( 'lang', rma_ceviri_mevcut_url() );

		foreach ( rma_ceviri_aktif_diller() as $kod ) {
			printf(
				'<link rel="alternate" hreflang="%1$s" href="%2$s" />' . "\n",
				esc_attr( $kod ),
				esc_url( rma_ceviri_dil_url( $kod, $temel ) )
			);
		}

		printf(
			'<link rel="alternate" hreflang="x-default" href="%s" />' . "\n",
			esc_url( $temel )
		);
	}
}
