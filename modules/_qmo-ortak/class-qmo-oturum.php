<?php
/**
 * QMO Oturum — HMAC imzalı masa oturumu.
 *
 * Müşteri masadaki QR kodu okuttuğunda (/?masa=masa-31) imzalı bir cookie
 * verilir. Cookie içinde masa slug'ı, veriliş zamanı, son işlem zamanı ve
 * masa "epoch" değeri bulunur; hepsi birlikte HMAC-SHA256 ile imzalanır.
 * İstemci cookie'yi değiştiremez, başka bir masa adına oturum uyduramaz.
 *
 * ÖNEMLİ: masa slug'ı bir STRING'dir (masa-31, vip-salon, 7 ...).
 * Hiçbir yerde absint() kullanılmaz — her yerde sanitize_title().
 *
 * @package QR_Menu_Official
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'QMO_Oturum' ) ) {

	/**
	 * Masa oturumu üretimi ve doğrulaması.
	 */
	class QMO_Oturum {

		/** Cookie adı — canlı sitelerdeki mevcut oturumlarla uyum için değişmez. */
		const COOKIE = 'qr_masa_token';

		/** HMAC anahtarının option adı. */
		const OPT_KEY = 'qr_masa_hmac_key';

		/** Ayar option adı. */
		const OPT = 'qr_masa_ayar';

		/**
		 * Oturum ayarları (admin sayfasından yönetilir).
		 *
		 * @return array{hard_cap:int,idle_cap:int,chat_limit:int}
		 */
		public static function ayar() {
			$varsayilan = array(
				'hard_cap'   => 90,
				'idle_cap'   => 30,
				'chat_limit' => 25,
			);
			return wp_parse_args( get_option( self::OPT, array() ), $varsayilan );
		}

		/** Oturumun toplam ömrü (saniye). */
		public static function hard_cap() {
			return max( 5, (int) self::ayar()['hard_cap'] ) * 60;
		}

		/** Hareketsizlik limiti (saniye). */
		public static function idle_cap() {
			return max( 1, (int) self::ayar()['idle_cap'] ) * 60;
		}

		/** Oturum başına chatbot mesaj limiti. */
		public static function chat_limit() {
			return max( 1, (int) self::ayar()['chat_limit'] );
		}

		/**
		 * HMAC anahtarını döndürür, yoksa üretir.
		 *
		 * @return string
		 */
		private static function anahtar() {
			$k = get_option( self::OPT_KEY );
			if ( ! $k ) {
				$k = wp_generate_password( 64, true, true );
				add_option( self::OPT_KEY, $k, '', 'no' );
			}
			return $k;
		}

		/** Aktivasyonda anahtarı önceden üret. */
		public static function anahtar_hazirla() {
			self::anahtar();
		}

		/**
		 * Masanın epoch değeri. Masa "kapatıldığında" artar ve o masaya ait
		 * tüm eski token'lar tek seferde geçersizleşir.
		 *
		 * @param string $masa Masa slug'ı.
		 * @return int
		 */
		private static function epoch( $masa ) {
			$masa = sanitize_title( $masa );
			if ( '' === $masa ) {
				return 0;
			}
			// Option adı md5 ile sabit uzunlukta tutulur (slug uzun olabilir).
			return (int) get_option( 'qr_masa_epoch_' . md5( $masa ), 0 );
		}

		/**
		 * Masayı kapat — o masadaki tüm açık oturumları geçersiz kılar.
		 *
		 * @param string $masa Masa slug'ı.
		 */
		public static function masayi_kapat( $masa ) {
			$masa = sanitize_title( $masa );
			if ( '' === $masa ) {
				return;
			}
			update_option( 'qr_masa_epoch_' . md5( $masa ), self::epoch( $masa ) + 1, false );
		}

		/**
		 * Payload imzası.
		 *
		 * @param string $p İmzalanacak metin.
		 * @return string
		 */
		private static function imzala( $p ) {
			return hash_hmac( 'sha256', $p, self::anahtar() );
		}

		/**
		 * Yeni token üret.
		 *
		 * @param string   $masa   Masa slug'ı.
		 * @param int|null $issued Oturumun ilk veriliş zamanı (tazelemede korunur).
		 * @param int|null $last   Son işlem zamanı.
		 * @return string
		 */
		public static function token_uret( $masa, $issued = null, $last = null ) {
			$masa   = sanitize_title( $masa );
			$issued = $issued ? (int) $issued : time();
			$last   = $last ? (int) $last : time();
			$epoch  = self::epoch( $masa );
			$p      = "$masa|$issued|$last|$epoch";
			return $p . '|' . self::imzala( $p );
		}

		/**
		 * Token'ı doğrula.
		 *
		 * @param string $token Cookie değeri.
		 * @return array{masa:string,issued:int,last:int,epoch:int}|false
		 */
		public static function dogrula( $token ) {
			if ( ! $token || ! is_string( $token ) || 4 !== substr_count( $token, '|' ) ) {
				return false;
			}
			list( $masa, $issued, $last, $epoch, $sig ) = explode( '|', $token );

			$p = "$masa|$issued|$last|$epoch";
			if ( ! hash_equals( self::imzala( $p ), $sig ) ) {
				return false;
			}

			$now = time();
			if ( ( $now - (int) $issued ) > self::hard_cap() ) {
				return false;
			}
			if ( ( $now - (int) $last ) > self::idle_cap() ) {
				return false;
			}
			if ( (int) $epoch !== self::epoch( $masa ) ) {
				return false;
			}

			return array(
				'masa'   => $masa,
				'issued' => (int) $issued,
				'last'   => (int) $last,
				'epoch'  => (int) $epoch,
			);
		}
	}
}

/**
 * Oturum cookie'sini yaz.
 *
 * @param string $token Token metni.
 */
if ( ! function_exists( 'qmo_cookie_yaz' ) ) {
	function qmo_cookie_yaz( $token ) {
		if ( headers_sent() ) {
			return;
		}
		setcookie(
			QMO_Oturum::COOKIE,
			$token,
			array(
				// Yol ve alan adı bilinçli olarak '/' ve boş: canlı sitelerde
				// hâlihazırda bu ayarla yazılmış oturumlar geçerli kalsın.
				'expires'  => time() + QMO_Oturum::hard_cap(),
				'path'     => '/',
				'domain'   => '',
				'secure'   => is_ssl(),
				'httponly' => true,
				'samesite' => 'Lax',
			)
		);
		$_COOKIE[ QMO_Oturum::COOKIE ] = $token;
	}
}

/**
 * QR okutulduğunda oturum aç; her sayfa gezinmesinde idle sayacını tazele.
 *
 * Eski WPCode snippet'i de aynı işi yapıyorsa (QR_MASA_OTURUM_INIT tanımlı)
 * ikinci kez kaydetmeyiz — çift cookie yazımı olmasın.
 */
if ( ! defined( 'QR_MASA_OTURUM_INIT' ) ) {
	define( 'QR_MASA_OTURUM_INIT', true );

	add_action( 'init', 'qmo_oturum_init', 1 );

	/**
	 * Oturum açma / tazeleme.
	 */
	function qmo_oturum_init() {
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
			return;
		}

		$mevcut = QMO_Oturum::dogrula( isset( $_COOKIE[ QMO_Oturum::COOKIE ] ) ? wp_unslash( $_COOKIE[ QMO_Oturum::COOKIE ] ) : '' );

		// 1) QR okutulmuş: ?masa=... geldiyse o masa için oturum aç.
		if ( ! empty( $_GET['masa'] ) ) {
			$masa = sanitize_title( wp_unslash( $_GET['masa'] ) );

			// Masa gerçekten kayıtlı mı? Kayıtlı değilse oturum AÇILMAZ —
			// aksi halde istemci istediği masa adına oturum uydurabilirdi.
			if ( '' !== $masa && qmo_masa_gecerli_mi( $masa ) ) {
				if ( ! $mevcut || $mevcut['masa'] !== $masa ) {
					qmo_cookie_yaz( QMO_Oturum::token_uret( $masa ) );
					return;
				}
			}
		}

		// 2) Oturum zaten varsa: sayfa gezinmesi de "işlem" sayılır, idle
		//    sayacını yenile. (Eskiden yalnızca AJAX yeniliyordu, bu yüzden
		//    menüyü sessizce inceleyen müşteriler erken düşüyordu.)
		if ( $mevcut ) {
			qmo_cookie_yaz( QMO_Oturum::token_uret( $mevcut['masa'], $mevcut['issued'] ) );
		}
	}
}

/* -------------------------------------------------------------------------
 * Geriye dönük uyumluluk
 * ---------------------------------------------------------------------- */

// Eski snippet'ler QR_Masa_Oturum sınıf adını kullanıyor.
if ( ! class_exists( 'QR_Masa_Oturum' ) ) {
	class_alias( 'QMO_Oturum', 'QR_Masa_Oturum' );
}

if ( ! function_exists( 'qr_masa_cookie_yaz' ) ) {
	/**
	 * @deprecated qmo_cookie_yaz() kullanın.
	 * @param string $token Token metni.
	 */
	function qr_masa_cookie_yaz( $token ) {
		qmo_cookie_yaz( $token );
	}
}

if ( ! function_exists( 'qr_masa_kapat' ) ) {
	/**
	 * @deprecated QMO_Oturum::masayi_kapat() kullanın.
	 * @param string $masa Masa slug'ı.
	 */
	function qr_masa_kapat( $masa ) {
		QMO_Oturum::masayi_kapat( $masa );
	}
}
