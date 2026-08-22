<?php
/**
 * Yavaş sorgu teşhisi — eşiği aşan sorguyu süresi ve çağrı yeriyle günlüğe yazar.
 *
 * NEDEN VAR
 * ---------
 * Canlıda "sorgular 1 saniyenin altında bitmiyor, bağlantılar birikiyor"
 * teşhisi konduğunda elde yalnızca semptom vardı: HANGİ sorgunun yavaşladığı
 * tahmin ediliyordu. Bu sınıf tahmini ölçüme çevirir — eşiği aşan her sorguyu
 * süresi, kısaltılmış metni ve onu açan fonksiyon zinciriyle birlikte yazar.
 *
 * ÜRETİMDE TAMAMEN SESSİZDİR
 * --------------------------
 * Ölçüm WordPress'in kendi SAVEQUERIES mekanizmasına dayanır ve o mekanizma
 * her sorgu için zaman + çağrı yığını biriktirir; yani bedava değildir. Bu
 * yüzden sınıf üç koşulun HEPSİ sağlanmadıkça hiçbir şey yapmaz:
 *
 *   1. WP_DEBUG açık,
 *   2. SAVEQUERIES tanımlı ve açık (wp-config.php'de elle açılır),
 *   3. `qrms_yavas_sorgu_esigi` filtresi 0'dan büyük bir eşik döndürüyor.
 *
 * Yani varsayılan bir kurulumda tek bir satır bile çalışmaz; teşhis gerektiğinde
 * wp-config.php'ye iki satır eklenip açılır, sonra kapatılır.
 *
 * KULLANIM
 * --------
 *   define( 'WP_DEBUG', true );
 *   define( 'WP_DEBUG_LOG', true );
 *   define( 'SAVEQUERIES', true );
 *
 * Günlük satırı şöyle görünür:
 *
 *   [QRMS yavaş sorgu] 1.284 sn | SELECT * FROM wp_qrm_reviews WHERE status = 1
 *   ORDER BY created_at DESC… | çağrı: qrm_pro_shortcode -> qrm_pro_fetch_approved_reviews
 *
 * @package QR_Menu_Suite
 */

defined( 'ABSPATH' ) || exit;

/**
 * Yavaş sorgu günlükçüsü.
 */
class QRMS_Query_Monitor {

	/**
	 * Varsayılan eşik (saniye).
	 *
	 * 0,5 sn bilinçli olarak "kullanıcının fark ettiği an"ın altındadır:
	 * hosting'in şikâyet ettiği 1 saniyeye ulaşmadan önce sorunu görmek gerekir.
	 */
	const VARSAYILAN_ESIK = 0.5;

	/**
	 * Tek bir istekte yazılacak azami satır. Günlüğü şişirmemek içindir.
	 */
	const AZAMI_SATIR = 20;

	/**
	 * Günlüğe yazılan sorgu metninin azami uzunluğu.
	 */
	const METIN_UZUNLUK = 300;

	/**
	 * Hook kayıtları.
	 *
	 * @return void
	 */
	public static function init() {
		if ( ! self::etkin_mi() ) {
			return;
		}

		// `shutdown` bilinçli: $wpdb->queries ancak istek bittiğinde tamamdır.
		add_action( 'shutdown', array( __CLASS__, 'raporla' ), 999 );
	}

	/**
	 * İzleme açık mı?
	 *
	 * @return bool
	 */
	public static function etkin_mi() {
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
			return false;
		}

		if ( ! defined( 'SAVEQUERIES' ) || ! SAVEQUERIES ) {
			return false;
		}

		return self::esik() > 0;
	}

	/**
	 * Yavaş sayılma eşiği (saniye).
	 *
	 * @return float 0 veya altı = izleme kapalı.
	 */
	public static function esik() {
		/**
		 * Yavaş sorgu eşiği (saniye). 0 döndürmek izlemeyi kapatır.
		 *
		 * @param float $esik Varsayılan eşik.
		 */
		return (float) apply_filters( 'qrms_yavas_sorgu_esigi', self::VARSAYILAN_ESIK );
	}

	/**
	 * $wpdb->queries kayıtlarından eşiği aşanları ayıklar.
	 *
	 * Saf fonksiyon (WordPress'e ve $wpdb'ye bağımlılığı yok), bu yüzden
	 * doğrudan test edilir. Girdi biçimi WordPress'in kendi biçimidir:
	 * [0] sorgu metni, [1] süre (saniye), [2] çağrı zinciri.
	 *
	 * @param array $kayitlar $wpdb->queries.
	 * @param float $esik     Saniye.
	 * @param int   $azami    Azami satır sayısı.
	 * @return array<int,array{sure:float,sorgu:string,cagri:string}> En yavaş önce.
	 */
	public static function yavaslari_ayikla( array $kayitlar, $esik, $azami = self::AZAMI_SATIR ) {
		$yavas = array();

		foreach ( $kayitlar as $kayit ) {
			if ( ! is_array( $kayit ) || ! isset( $kayit[0], $kayit[1] ) ) {
				continue;
			}

			$sure = (float) $kayit[1];

			if ( $sure < $esik ) {
				continue;
			}

			$yavas[] = array(
				'sure'  => $sure,
				'sorgu' => self::sorguyu_kisalt( (string) $kayit[0] ),
				'cagri' => self::cagriyi_kisalt( isset( $kayit[2] ) ? (string) $kayit[2] : '' ),
			);
		}

		// En yavaş sorgu günlüğün başında dursun; asıl suçlu odur.
		usort(
			$yavas,
			static function ( $a, $b ) {
				if ( $a['sure'] === $b['sure'] ) {
					return 0;
				}

				return ( $a['sure'] < $b['sure'] ) ? 1 : -1;
			}
		);

		return array_slice( $yavas, 0, max( 1, (int) $azami ) );
	}

	/**
	 * Sorgu metnini tek satıra indirir ve kırpar.
	 *
	 * @param string $sorgu Ham SQL.
	 * @return string
	 */
	public static function sorguyu_kisalt( $sorgu ) {
		$sorgu = trim( preg_replace( '/\s+/', ' ', $sorgu ) );

		if ( strlen( $sorgu ) <= self::METIN_UZUNLUK ) {
			return $sorgu;
		}

		return substr( $sorgu, 0, self::METIN_UZUNLUK ) . '…';
	}

	/**
	 * Çağrı zincirini okunabilir hâle getirir.
	 *
	 * WordPress zinciri en içteki çağrıdan dışa doğru, virgülle yazar ve baştaki
	 * halkalar daima wpdb'nin kendi metotlarıdır — teşhise katkısı olmadığı için
	 * atılır. Kalan zincir dıştan içe çevrilir: okuyan kişi "hangi ekran" diye
	 * bakar, "hangi wpdb metodu" diye değil.
	 *
	 * @param string $cagri $wpdb->queries[n][2].
	 * @return string
	 */
	public static function cagriyi_kisalt( $cagri ) {
		$cagri = trim( $cagri );

		if ( '' === $cagri ) {
			return 'bilinmiyor';
		}

		$halkalar = array_filter( array_map( 'trim', explode( ',', $cagri ) ) );

		$halkalar = array_values(
			array_filter(
				$halkalar,
				static function ( $halka ) {
					return 0 !== strpos( $halka, 'wpdb->' )
						&& 0 !== strpos( $halka, 'require' )
						&& 0 !== strpos( $halka, 'include' );
				}
			)
		);

		if ( empty( $halkalar ) ) {
			return 'bilinmiyor';
		}

		// En fazla dört halka: daha fazlası satırı okunmaz yapıyor.
		$halkalar = array_slice( $halkalar, 0, 4 );

		return implode( ' -> ', array_reverse( $halkalar ) );
	}

	/**
	 * İstek sonunda yavaş sorguları günlüğe yazar.
	 *
	 * @return void
	 */
	public static function raporla() {
		global $wpdb;

		if ( ! isset( $wpdb ) || ! is_object( $wpdb ) || empty( $wpdb->queries ) || ! is_array( $wpdb->queries ) ) {
			return;
		}

		$yavas = self::yavaslari_ayikla( $wpdb->queries, self::esik() );

		if ( empty( $yavas ) ) {
			return;
		}

		foreach ( $yavas as $kayit ) {
			self::yaz(
				sprintf(
					'[QRMS yavaş sorgu] %.3f sn | %s | çağrı: %s',
					$kayit['sure'],
					$kayit['sorgu'],
					$kayit['cagri']
				)
			);
		}
	}

	/**
	 * Günlüğe yazar. Ayrı metot: testte devre dışı bırakılabilsin.
	 *
	 * @param string $mesaj Satır.
	 * @return void
	 */
	protected static function yaz( $mesaj ) {
		if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			error_log( $mesaj ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
	}
}
