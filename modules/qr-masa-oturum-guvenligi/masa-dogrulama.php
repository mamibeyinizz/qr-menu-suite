<?php
/**
 * Masa doğrulama ve isteğe bağlı sayfa kilidi.
 *
 * Davranış:
 *  - Ana sayfa HERKESE AÇIK kalır. Menü zaten ana sayfada (/?masa=masa-31)
 *    yayınlandığı için sayfa bazlı kilit varsayılan olarak KAPALIDIR.
 *  - Adreste ?masa=X varsa ve bu slug qrm_tables'ta kayıtlı değilse, sahte
 *    QR üretilmiş demektir — kilit ekranı gösterilir.
 *  - Ayrıca ayrı bir menü sayfası kullanan siteler için, korunacak sayfa
 *    slug'ları QR Menü → Oturum Ayarları'ndan tanımlanabilir.
 *
 * Chatbot / sepet gibi bileşenler SAYFA bazlı değil OTURUM bazlı korunur;
 * geçerli oturum yoksa kısa kodlar bilgi kutusu basar (bkz. shortcode-*.php).
 *
 * @package QR_Menu_Official
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sayfa bazlı kilidin uygulanacağı slug listesi.
 *
 * @return string[]
 */
if ( ! function_exists( 'qmo_korumali_sluglar' ) ) {
	function qmo_korumali_sluglar() {
		$ham     = (string) get_option( 'qmo_korumali_sayfalar', '' );
		$sluglar = array_filter( array_map( 'sanitize_title', explode( ',', $ham ) ) );
		/**
		 * Korunan sayfa slug'larını değiştir.
		 *
		 * @param string[] $sluglar Slug listesi.
		 */
		return apply_filters( 'qmo_korumali_sluglar', $sluglar );
	}
}

/**
 * Bu istek, sayfa bazlı kilidin uygulanacağı bir sayfa mı?
 *
 * @return bool
 */
if ( ! function_exists( 'qmo_korumali_sayfa_mi' ) ) {
	function qmo_korumali_sayfa_mi() {
		// Ana sayfa hiçbir koşulda kilitlenmez.
		if ( is_front_page() || is_home() ) {
			return false;
		}
		if ( ! is_page() ) {
			return false;
		}

		$sluglar = qmo_korumali_sluglar();
		if ( empty( $sluglar ) ) {
			return false;
		}

		$post = get_queried_object();
		if ( ! $post || empty( $post->post_name ) ) {
			return false;
		}

		return in_array( $post->post_name, $sluglar, true );
	}
}

/**
 * Dil kodu sitede etkin mi?
 *
 * @param string $lang Dil kodu.
 * @return bool
 */
if ( ! function_exists( 'qmo_kilit_dil_aktif_mi' ) ) {
	function qmo_kilit_dil_aktif_mi( $lang ) {
		if ( ! is_string( $lang ) || '' === $lang ) {
			return false;
		}
		if ( function_exists( 'rma_ceviri_dil_gecerli_mi' ) ) {
			return rma_ceviri_dil_gecerli_mi( $lang );
		}
		if ( function_exists( 'rma_ceviri_aktif_diller' ) ) {
			return in_array( $lang, rma_ceviri_aktif_diller(), true );
		}
		return 'tr' === $lang;
	}
}

/**
 * Accept-Language'dan sitede etkin ilk dili seç.
 *
 * Yalnızca kilit ekranı için. rma_get_current_lang() zincirine eklenmez.
 *
 * @return string Etkin dil kodu veya boş (eşleşme yok).
 */
if ( ! function_exists( 'qmo_kilit_tarayici_dili' ) ) {
	function qmo_kilit_tarayici_dili() {
		if ( empty( $_SERVER['HTTP_ACCEPT_LANGUAGE'] ) ) {
			return '';
		}
		if ( ! function_exists( 'rma_ceviri_aktif_diller' ) ) {
			return '';
		}

		$aktif = rma_ceviri_aktif_diller();
		if ( ! is_array( $aktif ) || array() === $aktif ) {
			return '';
		}

		$ham     = sanitize_text_field( wp_unslash( $_SERVER['HTTP_ACCEPT_LANGUAGE'] ) );
		$adaylar = array();

		foreach ( explode( ',', $ham ) as $parca ) {
			$parca = trim( $parca );
			if ( '' === $parca || '*' === $parca[0] ) {
				continue;
			}
			if ( ! preg_match( '/^([a-zA-Z]{1,8}(?:-[a-zA-Z0-9]{1,8})*)(?:\s*;\s*q\s*=\s*([0-9.]+))?/', $parca, $m ) ) {
				continue;
			}
			$kod       = strtolower( $m[1] );
			$q         = isset( $m[2] ) ? (float) $m[2] : 1.0;
			$adaylar[] = array( $kod, $q );
		}

		usort(
			$adaylar,
			static function ( $a, $b ) {
				if ( $a[1] === $b[1] ) {
					return 0;
				}
				return ( $a[1] > $b[1] ) ? -1 : 1;
			}
		);

		foreach ( $adaylar as $aday ) {
			$tam  = $aday[0];
			$kisa = substr( $tam, 0, 2 );
			if ( in_array( $tam, $aktif, true ) ) {
				return $tam;
			}
			if ( in_array( $kisa, $aktif, true ) ) {
				return $kisa;
			}
		}

		return '';
	}
}

/**
 * Kilit ekranı dili: ?lang= → cookie → Accept-Language (etkin) → tr.
 *
 * Masa QR adresleri ?lang= taşımaz (yalnız ?masa=); parametre yine de
 * ilk kaynaktır — elle eklenmiş veya yönlendirilmiş istekler için.
 * Accept-Language genel rma_get_current_lang() zincirine EKLENMEZ.
 *
 * @return string
 */
if ( ! function_exists( 'qmo_kilit_ekrani_dili' ) ) {
	function qmo_kilit_ekrani_dili() {
		if ( isset( $_GET['lang'] ) ) {
			$aday = sanitize_text_field( wp_unslash( $_GET['lang'] ) );
			if ( qmo_kilit_dil_aktif_mi( $aday ) ) {
				return $aday;
			}
		}

		$cookie_adi = defined( 'RMA_CEVIRI_COOKIE' ) ? RMA_CEVIRI_COOKIE : 'rma_lang';
		if ( isset( $_COOKIE[ $cookie_adi ] ) ) {
			$aday = sanitize_text_field( wp_unslash( $_COOKIE[ $cookie_adi ] ) );
			if ( qmo_kilit_dil_aktif_mi( $aday ) ) {
				return $aday;
			}
		}

		$tarayici = qmo_kilit_tarayici_dili();
		if ( '' !== $tarayici ) {
			return $tarayici;
		}

		return 'tr';
	}
}

/**
 * Kilit ekranı başlık ve gövde metinleri (dil çözülmüş).
 *
 * @param string $mesaj Gösterilecek açıklama (Türkçe kaynak / textdomain).
 * @return array{dil:string,baslik:string,mesaj:string,rtl:bool}
 */
if ( ! function_exists( 'qmo_kilit_ekrani_metinleri' ) ) {
	function qmo_kilit_ekrani_metinleri( $mesaj ) {
		$dil    = qmo_kilit_ekrani_dili();
		$baslik = __( 'Oturum Gerekli', 'qrms' );
		$mesaj  = (string) $mesaj;

		if ( function_exists( 'rma_ceviri_modul' ) ) {
			$baslik = rma_ceviri_modul( 'lock', $baslik, $dil );
			$mesaj  = rma_ceviri_modul( 'lock', $mesaj, $dil );
		}

		$rtl = function_exists( 'rma_ceviri_rtl_diller' )
			&& in_array( $dil, rma_ceviri_rtl_diller(), true );

		return array(
			'dil'    => $dil,
			'baslik' => $baslik,
			'mesaj'  => $mesaj,
			'rtl'    => $rtl,
		);
	}
}

/**
 * Kilit ekranı — koyu + altın. İsteği sonlandırır.
 *
 * @param string $mesaj Gösterilecek açıklama.
 */
if ( ! function_exists( 'qmo_kilit_ekrani' ) ) {
	function qmo_kilit_ekrani( $mesaj ) {
		$metin = qmo_kilit_ekrani_metinleri( $mesaj );

		status_header( 403 );
		nocache_headers();
		?>
<!DOCTYPE html>
<html lang="<?php echo esc_attr( $metin['dil'] ); ?>"<?php echo $metin['rtl'] ? ' dir="rtl"' : ''; ?>>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title><?php echo esc_html( $metin['baslik'] ); ?></title>
<link rel="stylesheet" href="<?php echo esc_url( QRMS_PLUGIN_URL . 'modules/qr-masa-oturum-guvenligi/assets/css/kilit.css?ver=' . QRMS_Helpers::asset_version( 'modules/qr-masa-oturum-guvenligi/assets/css/kilit.css' ) ); ?>">
</head>
<body>
	<div class="qmo-kilit-kart">
		<div class="qmo-kilit-ikon">🔒</div>
		<h1><?php echo esc_html( $metin['baslik'] ); ?></h1>
		<p><?php echo esc_html( $metin['mesaj'] ); ?></p>
	</div>
</body>
</html>
		<?php
		exit;
	}
}

if ( ! defined( 'QRSERVIS_KILIT_YUKLENDI' ) ) {
	define( 'QRSERVIS_KILIT_YUKLENDI', true );

	add_action( 'template_redirect', 'qmo_masa_dogrulama', 1 );

	/**
	 * Masa doğrulama denetimi.
	 */
	function qmo_masa_dogrulama() {
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
			return;
		}
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return;
		}
		// Yöneticiler kilitlenmez (masaları yönetirken sitede gezerler).
		if ( is_user_logged_in() && current_user_can( 'manage_options' ) ) {
			return;
		}

		// 1) Adreste masa parametresi varsa slug gerçekten kayıtlı olmalı.
		$gelen_masa = isset( $_GET['masa'] ) ? sanitize_title( wp_unslash( $_GET['masa'] ) ) : '';
		if ( '' !== $gelen_masa ) {
			if ( ! qmo_masa_gecerli_mi( $gelen_masa ) ) {
				qmo_kilit_ekrani( __( 'Bu masa için geçerli bir QR kod bulunamadı. Lütfen masanızdaki QR kodu okutun.', 'qrms' ) );
			}
			return; // Geçerli QR — oturum init aşamasında zaten açıldı.
		}

		// 2) Sayfa bazlı kilit (varsayılan: kapalı).
		if ( ! qmo_korumali_sayfa_mi() ) {
			return;
		}

		if ( ! qmo_oturum() ) {
			qmo_kilit_ekrani( __( 'Oturumunuz sona erdi. Devam etmek için masadaki QR kodu tekrar okutun.', 'qrms' ) );
		}
		// Not: idle sayacı qmo_oturum_init() içinde her sayfa gezinmesinde tazelenir.
	}
}

/* -------------------------------------------------------------------------
 * Geriye dönük uyumluluk
 * ---------------------------------------------------------------------- */

if ( ! function_exists( 'qrservis_korumali_sayfa_mi' ) ) {
	/**
	 * @deprecated qmo_korumali_sayfa_mi() kullanın.
	 * @return bool
	 */
	function qrservis_korumali_sayfa_mi() {
		return qmo_korumali_sayfa_mi();
	}
}

if ( ! function_exists( 'qrservis_kilit_ekrani' ) ) {
	/**
	 * @deprecated qmo_kilit_ekrani() kullanın.
	 * @param string $mesaj Mesaj.
	 */
	function qrservis_kilit_ekrani( $mesaj ) {
		qmo_kilit_ekrani( $mesaj );
	}
}
