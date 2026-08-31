<?php
/**
 * İstatistikler modülünün PAYLAŞILAN FİLTRE BAĞLAMI.
 *
 * Diğer modüllerin alt sayfaları birbirinden bağımsızdır; İstatistikler'in
 * kategorileri ise aynı sorunun farklı kesitleridir. Kullanıcı "Son 7 gün +
 * Masa 3" seçip Ürünler'den Sepet'e geçtiğinde seçimini KAYBETMEMELİDİR.
 *
 * Bu yüzden filtre tek bir yerden okunur, tek bir yerden URL'ye yazılır:
 * hiçbir sayfa kendi başına $_GET'e bakmaz. Sayfalar arası taşıma query arg
 * ile yapılır (oturum/çerez değil), çünkü adres çubuğundaki değer hem
 * paylaşılabilir hem yer imine alınabilir hem de geri/ileri düğmeleriyle
 * doğru çalışır.
 *
 * TAŞINAN ALANLAR
 *   donem : bugun | hafta | ay | ozel   (varsayılan: bugun)
 *   masa  : masa slug'ı ('' = tüm masalar)
 *   bas   : özel aralığın başlangıcı (YYYY-MM-DD), yalnızca donem=ozel iken
 *   bit   : özel aralığın bitişi     (YYYY-MM-DD), yalnızca donem=ozel iken
 *
 * @package QR_Menu_Suite
 */

defined( 'ABSPATH' ) || exit;

if ( class_exists( 'QRMS_Analitik_Filtre' ) ) {
	return;
}

/**
 * İstatistik alt sayfalarının ortak zaman aralığı ve masa filtresi.
 */
class QRMS_Analitik_Filtre {

	/**
	 * Desteklenen dönem anahtarları.
	 *
	 * @var string[]
	 */
	const DONEMLER = array( 'bugun', 'hafta', 'ay', 'ozel' );

	/**
	 * Hiç seçim yapılmadığında geçerli olan dönem.
	 */
	const VARSAYILAN = 'bugun';

	/**
	 * Query arg adları — URL'de görünen tek yer burasıdır.
	 */
	const ARG_DONEM = 'donem';
	const ARG_MASA  = 'masa';
	const ARG_BAS   = 'bas';
	const ARG_BIT   = 'bit';

	/**
	 * Masa slug'ının azami uzunluğu (şemayla aynı).
	 */
	const MASA_UZUNLUK = 64;

	/**
	 * İstek içi önbellek: $_GET yalnızca bir kez çözülür.
	 *
	 * @var array|null
	 */
	private static $baglam = null;

	/**
	 * Geçerli isteğin filtre bağlamı.
	 *
	 * @return array{donem:string,masa:string,bas:string,bit:string}
	 */
	public static function baglam() {
		if ( null === self::$baglam ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Yalnızca okuma; hiçbir durum değişmez.
			self::$baglam = self::coz( (array) wp_unslash( $_GET ) );
		}

		return self::$baglam;
	}

	/**
	 * Bağlamı elle ayarlar (test ve yönlendirme için).
	 *
	 * @param array $kaynak Ham query arg dizisi.
	 * @return array Çözülmüş bağlam.
	 */
	public static function ayarla( array $kaynak ) {
		self::$baglam = self::coz( $kaynak );

		return self::$baglam;
	}

	/**
	 * Önbelleği boşaltır (test yardımcısı).
	 *
	 * @return void
	 */
	public static function sifirla() {
		self::$baglam = null;
	}

	/**
	 * Ham query arg dizisini geçerli bir filtre bağlamına indirger.
	 *
	 * Saf fonksiyon (süper globallere dokunmaz), bu yüzden doğrudan test
	 * edilir. Kurallar:
	 *
	 *   - Tanınmayan dönem varsayılana düşer.
	 *   - "ozel" seçilmiş ama tarihlerden biri eksik/bozuksa yine varsayılana
	 *     düşülür: yarım bir aralık sessizce tüm tabloyu taratırdı.
	 *   - Aralık ters verilmişse (bas > bit) uçlar takas edilir.
	 *   - Dönem "ozel" değilse tarihler taşınmaz; URL'de artık kalmaz.
	 *
	 * @param array $kaynak Ham değerler ($_GET ya da benzeri).
	 * @return array{donem:string,masa:string,bas:string,bit:string}
	 */
	public static function coz( array $kaynak ) {
		$donem = self::anahtar( $kaynak, self::ARG_DONEM );

		if ( ! in_array( $donem, self::DONEMLER, true ) ) {
			$donem = self::VARSAYILAN;
		}

		$bas = self::tarih( self::anahtar( $kaynak, self::ARG_BAS ) );
		$bit = self::tarih( self::anahtar( $kaynak, self::ARG_BIT ) );

		if ( 'ozel' === $donem && ( '' === $bas || '' === $bit ) ) {
			$donem = self::VARSAYILAN;
		}

		if ( 'ozel' !== $donem ) {
			$bas = '';
			$bit = '';
		} elseif ( $bas > $bit ) {
			$takas = $bas;
			$bas   = $bit;
			$bit   = $takas;
		}

		return array(
			'donem' => $donem,
			'masa'  => self::masa_temizle( self::anahtar( $kaynak, self::ARG_MASA ) ),
			'bas'   => $bas,
			'bit'   => $bit,
		);
	}

	/**
	 * Dizideki değeri düz metne indirger (dizi/nesne gelirse boş string).
	 *
	 * @param array  $kaynak   Ham dizi.
	 * @param string $anahtar  Aranan anahtar.
	 * @return string
	 */
	private static function anahtar( array $kaynak, $anahtar ) {
		if ( ! isset( $kaynak[ $anahtar ] ) || ! is_scalar( $kaynak[ $anahtar ] ) ) {
			return '';
		}

		return trim( (string) $kaynak[ $anahtar ] );
	}

	/**
	 * YYYY-MM-DD biçimindeki tarih (geçersizse boş string).
	 *
	 * Yalnızca biçim değil, TAKVİM de doğrulanır: "2026-02-31" biçime uyar
	 * ama gerçek bir gün değildir ve SQL tarafında sessizce NULL'a düşerdi.
	 *
	 * @param string $ham Ham değer.
	 * @return string
	 */
	private static function tarih( $ham ) {
		if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', (string) $ham, $p ) ) {
			return '';
		}

		return checkdate( (int) $p[2], (int) $p[3], (int) $p[1] ) ? $ham : '';
	}

	/**
	 * Masa slug'ını normalize eder ve sütun uzunluğuna sığdırır.
	 *
	 * @param string $ham Ham değer.
	 * @return string
	 */
	private static function masa_temizle( $ham ) {
		return substr( sanitize_title( (string) $ham ), 0, self::MASA_UZUNLUK );
	}

	/**
	 * Aktif dönem anahtarı.
	 *
	 * @return string
	 */
	public static function donem() {
		$baglam = self::baglam();

		return $baglam['donem'];
	}

	/**
	 * Aktif masa filtresi ('' = tüm masalar).
	 *
	 * @return string
	 */
	public static function masa() {
		$baglam = self::baglam();

		return $baglam['masa'];
	}

	/**
	 * Özel aralığın başlangıcı ('' = özel aralık seçili değil).
	 *
	 * @return string
	 */
	public static function bas() {
		$baglam = self::baglam();

		return $baglam['bas'];
	}

	/**
	 * Özel aralığın bitişi ('' = özel aralık seçili değil).
	 *
	 * @return string
	 */
	public static function bit() {
		$baglam = self::baglam();

		return $baglam['bit'];
	}

	/**
	 * Dönemin insan tarafından okunan adı.
	 *
	 * @param array|null $baglam Bağlam (boş bırakılırsa aktif bağlam).
	 * @return string
	 */
	public static function etiket( $baglam = null ) {
		$baglam = is_array( $baglam ) ? self::coz( $baglam ) : self::baglam();

		switch ( $baglam['donem'] ) {
			case 'hafta':
				return __( 'Son 7 gün', 'qrms' );

			case 'ay':
				return __( 'Bu ay', 'qrms' );

			case 'ozel':
				return sprintf(
					/* translators: 1: başlangıç tarihi, 2: bitiş tarihi. */
					__( '%1$s – %2$s', 'qrms' ),
					$baglam['bas'],
					$baglam['bit']
				);

			case 'bugun':
			default:
				return __( 'Bugün', 'qrms' );
		}
	}

	/* -----------------------------------------------------------------
	   ZAMAN ARALIĞI
	----------------------------------------------------------------- */

	/**
	 * Sitenin yerel saatiyle "şimdi" (unix damgası).
	 *
	 * Kayıtlar current_time('mysql') ile yazıldığı için aralıklar da yerel
	 * saatle hesaplanmalıdır (bkz. QRMS_Analitik::simdi).
	 *
	 * @return int
	 */
	private static function simdi() {
		return (int) current_time( 'timestamp' ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested
	}

	/**
	 * Seçili dönemin MySQL biçiminde [bas, bit] aralığı.
	 *
	 * Bitiş her zaman bugünün sonudur (gelecekte kayıt olamaz); "ozel"de ise
	 * kullanıcının verdiği günün sonudur. Aralık KAPALIDIR (BETWEEN), böylece
	 * sorgular created_at üzerindeki idx_date / idx_masa_td indekslerini
	 * aralık taraması olarak kullanabilir.
	 *
	 * @param array|null $baglam Bağlam (boş bırakılırsa aktif bağlam).
	 * @return array{bas:string,bit:string,gun:int}
	 */
	public static function aralik( $baglam = null ) {
		$baglam = is_array( $baglam ) ? self::coz( $baglam ) : self::baglam();
		$simdi  = self::simdi();
		$bugun  = gmdate( 'Y-m-d', $simdi );

		switch ( $baglam['donem'] ) {
			case 'hafta':
				$bas = gmdate( 'Y-m-d', strtotime( '-6 days', $simdi ) );
				$bit = $bugun;
				break;

			case 'ay':
				$bas = gmdate( 'Y-m-01', $simdi );
				$bit = $bugun;
				break;

			case 'ozel':
				$bas = $baglam['bas'];
				$bit = $baglam['bit'];
				break;

			case 'bugun':
			default:
				$bas = $bugun;
				$bit = $bugun;
				break;
		}

		return array(
			'bas' => $bas . ' 00:00:00',
			'bit' => $bit . ' 23:59:59',
			'gun' => self::gun_sayisi( $bas, $bit ),
		);
	}

	/**
	 * Karşılaştırma için bir ÖNCEKİ eşit uzunluktaki pencerenin başlangıcı.
	 *
	 * "Bugün" için dün, "son 7 gün" için ondan önceki 7 gün… Özet kartları
	 * değişim yüzdesini bu pencereye göre gösterir.
	 *
	 * @param array|null $baglam Bağlam (boş bırakılırsa aktif bağlam).
	 * @return string MySQL biçiminde zaman damgası.
	 */
	public static function onceki_baslangic( $baglam = null ) {
		$aralik = self::aralik( $baglam );
		$bas    = substr( $aralik['bas'], 0, 10 );

		return gmdate( 'Y-m-d', strtotime( '-' . $aralik['gun'] . ' days', (int) strtotime( $bas ) ) ) . ' 00:00:00';
	}

	/**
	 * İki tarih arasındaki gün sayısı (uçlar dahil, en az 1).
	 *
	 * @param string $bas YYYY-MM-DD.
	 * @param string $bit YYYY-MM-DD.
	 * @return int
	 */
	private static function gun_sayisi( $bas, $bit ) {
		$fark = (int) floor( ( strtotime( $bit ) - strtotime( $bas ) ) / DAY_IN_SECONDS );

		return max( 1, $fark + 1 );
	}

	/* -----------------------------------------------------------------
	   GRAFİK KIRILIMI
	----------------------------------------------------------------- */

	/**
	 * Grafik kırılımının query arg adı.
	 *
	 * Kırılım kategoriler arasında TAŞINMAZ: aralık ve masa "hangi veriye
	 * bakıyorum" sorusunun cevabıdır ve her kategoride aynı anlama gelir,
	 * kırılım ise yalnızca Genel Bakış'taki grafiğin nasıl gruplandığıdır.
	 */
	const ARG_KIRILIM = 'kirilim';

	/**
	 * Bir aralık için ANLAMLI kırılımlar (ilki varsayılandır).
	 *
	 * Geçersiz kombinasyonlar hiç gösterilmez: "Bugün" seçiliyken aylık
	 * kırılım tek çubuk üretirdi, üç aylık bir aralıkta saatlik kırılım ise
	 * bütün günlerin saatlerini üst üste toplayıp yanıltıcı olurdu (SQL
	 * HOUR(created_at) ile grupladığı için gün bilgisi düşer).
	 *
	 * Saf fonksiyon, doğrudan test edilir.
	 *
	 * @param int $gun Aralığın gün sayısı.
	 * @return string[]
	 */
	public static function kirilimlar( $gun ) {
		$gun = max( 1, (int) $gun );

		// Tek gün: yalnızca saatlik anlamlı (günlük tek çubuk olurdu).
		if ( 1 === $gun ) {
			return array( 'hourly' );
		}

		$liste = array();

		// Günlük çubuk sayısı makul kaldığı sürece (≈3 ay) varsayılandır.
		if ( $gun <= 92 ) {
			$liste[] = 'daily';
		}

		// Haftalık en az iki tam haftada anlam kazanır.
		if ( $gun >= 14 ) {
			$liste[] = 'weekly';
		}

		// Aylık en az iki ayı kapsayan aralıklarda.
		if ( $gun >= 60 ) {
			$liste[] = 'monthly';
		}

		// 92 günden uzun ama 14 günden kısa bir aralık matematiksel olarak
		// imkânsız; yine de liste boş kalmasın (emniyet kemeri).
		return empty( $liste ) ? array( 'daily' ) : $liste;
	}

	/**
	 * İstenen kırılım — aralıkla uyumsuzsa en yakın anlamlıya düşer.
	 *
	 * Kırılım taşınan alanlardan biri OLMADIĞI için ayrı okunur: kaynak
	 * verilmezse isteğin kendi query arg'larına bakılır.
	 *
	 * @param array|null $kaynak Ham query arg'ları (boş bırakılırsa $_GET).
	 * @return string
	 */
	public static function kirilim( $kaynak = null ) {
		if ( ! is_array( $kaynak ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Yalnızca okuma.
			$kaynak = (array) wp_unslash( $_GET );
			$aralik = self::aralik();
		} else {
			$aralik = self::aralik( $kaynak );
		}

		$gecerli = self::kirilimlar( $aralik['gun'] );
		$istenen = self::anahtar( $kaynak, self::ARG_KIRILIM );

		return in_array( $istenen, $gecerli, true ) ? $istenen : $gecerli[0];
	}

	/**
	 * URL'de taşınacak query arg'lar.
	 *
	 * Varsayılan değerler yazılmaz: filtre dokunulmadığında adresler temiz
	 * kalır, dokunulduğunda seçim her bağlantıya yapışır.
	 *
	 * @param array|null $baglam Bağlam (boş bırakılırsa aktif bağlam).
	 * @return array<string,string>
	 */
	public static function args( $baglam = null ) {
		$baglam = is_array( $baglam ) ? self::coz( $baglam ) : self::baglam();
		$args   = array();

		if ( self::VARSAYILAN !== $baglam['donem'] ) {
			$args[ self::ARG_DONEM ] = $baglam['donem'];
		}

		if ( '' !== $baglam['masa'] ) {
			$args[ self::ARG_MASA ] = $baglam['masa'];
		}

		if ( 'ozel' === $baglam['donem'] ) {
			$args[ self::ARG_BAS ] = $baglam['bas'];
			$args[ self::ARG_BIT ] = $baglam['bit'];
		}

		return $args;
	}

	/**
	 * Bir yönetim sayfasının AKTİF FİLTREYİ TAŞIYAN adresi.
	 *
	 * Hub kartları, alt sayfalardaki bağlantılar ve "geri" bağlantısı bu tek
	 * üreticiden geçer; başka hiçbir yerde elle adres kurulmaz.
	 *
	 * @param string $sayfa  Sayfa slug'ı.
	 * @param array  $ekstra Ek query arg'ları (filtreyi ezebilir).
	 * @return string
	 */
	public static function url( $sayfa, array $ekstra = array() ) {
		return add_query_arg(
			array_merge( array( 'page' => $sayfa ), self::args(), $ekstra ),
			admin_url( 'admin.php' )
		);
	}
}
