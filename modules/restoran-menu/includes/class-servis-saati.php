<?php
/**
 * Zamanlı servis kısıtı ("kahvaltı 07:00–11:00, hafta içi").
 *
 * Kural KATEGORİDE tanımlanır (kahvaltı kategorisine bir kez yazılır, tüm
 * ürünleri kapsar); ürün ekranından tek ürün için ezilebilir. Servis dışı
 * ürün menüden KALDIRILMAZ — "Tükendi" mantığıyla aynı: yerinde kalır,
 * üzerine saat etiketi basılır ve sepete eklenemez. Sipariş ucunda da
 * sunucu tarafında kesilir (`qmo_siparis_onay_oncesi`).
 *
 * Gün numaraları ISO-8601'dir: 1 = Pazartesi … 7 = Pazar.
 * Başlangıç saati bitişten büyükse pencere gece yarısını aşar (22:00–02:00).
 *
 * @package QR_Menu_Suite
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'RMA_Servis_Saati' ) ) :

class RMA_Servis_Saati {

	/* Kategori (term) meta anahtarları. */
	const TERIM_AKTIF  = 'rma_servis_aktif';
	const TERIM_GUNLER = 'rma_servis_gunler';
	const TERIM_BAS    = 'rma_servis_bas';
	const TERIM_BIT    = 'rma_servis_bit';

	/* Ürün (post) meta anahtarları. */
	const META_MOD    = '_rma_servis_mod';   // devral | kapali | ozel
	const META_GUNLER = '_rma_servis_gunler';
	const META_BAS    = '_rma_servis_bas';
	const META_BIT    = '_rma_servis_bit';

	/** Etiket ve müşteri mesajı. */
	const ETIKET = 'Servis dışı';

	/** İstek içi bellek. */
	private static $memo = array();

	/**
	 * Gün adları (ISO numarası => ad).
	 *
	 * @return array<int,string>
	 */
	public static function gunler() {
		return array(
			1 => 'Pazartesi',
			2 => 'Salı',
			3 => 'Çarşamba',
			4 => 'Perşembe',
			5 => 'Cuma',
			6 => 'Cumartesi',
			7 => 'Pazar',
		);
	}

	/* =============================================================
	   KURAL ÇÖZÜMLEME
	============================================================= */

	/**
	 * Ürün için geçerli kural.
	 *
	 * Ürün modu 'ozel' ise ürünün kendi kuralı, 'kapali' ise kısıt yok,
	 * 'devral' (varsayılan) ise ürünün kategorilerindeki İLK aktif kural.
	 *
	 * @param int $post_id Ürün ID.
	 * @return array{gunler:int[],bas:string,bit:string,kaynak:string}|null
	 */
	public static function kural( $post_id ) {
		$post_id = (int) $post_id;

		if ( isset( self::$memo['kural'][ $post_id ] ) ) {
			return self::$memo['kural'][ $post_id ];
		}

		$kural = null;
		$mod   = (string) get_post_meta( $post_id, self::META_MOD, true );

		if ( 'ozel' === $mod ) {
			$kural = self::normalize(
				get_post_meta( $post_id, self::META_GUNLER, true ),
				get_post_meta( $post_id, self::META_BAS, true ),
				get_post_meta( $post_id, self::META_BIT, true ),
				'urun'
			);
		} elseif ( 'kapali' !== $mod ) {
			$terimler = get_the_terms( $post_id, 'rma_category' );

			if ( ! is_wp_error( $terimler ) && ! empty( $terimler ) ) {
				foreach ( $terimler as $terim ) {
					if ( '1' !== (string) get_term_meta( $terim->term_id, self::TERIM_AKTIF, true ) ) {
						continue;
					}

					$kural = self::normalize(
						get_term_meta( $terim->term_id, self::TERIM_GUNLER, true ),
						get_term_meta( $terim->term_id, self::TERIM_BAS, true ),
						get_term_meta( $terim->term_id, self::TERIM_BIT, true ),
						'kategori'
					);

					if ( $kural ) {
						break;
					}
				}
			}
		}

		self::$memo['kural'][ $post_id ] = $kural;

		return $kural;
	}

	/**
	 * Ham değerleri kurala çevirir; eksikse null.
	 *
	 * @param mixed  $gunler Gün numaraları.
	 * @param mixed  $bas    Başlangıç (HH:MM).
	 * @param mixed  $bit    Bitiş (HH:MM).
	 * @param string $kaynak 'urun' | 'kategori'.
	 * @return array|null
	 */
	private static function normalize( $gunler, $bas, $bit, $kaynak ) {
		$gunler = self::gunleri_temizle( $gunler );
		$bas    = self::saati_temizle( $bas );
		$bit    = self::saati_temizle( $bit );

		if ( empty( $gunler ) || '' === $bas || '' === $bit || $bas === $bit ) {
			return null;
		}

		return array(
			'gunler' => $gunler,
			'bas'    => $bas,
			'bit'    => $bit,
			'kaynak' => $kaynak,
		);
	}

	/**
	 * Gün dizisini 1-7 aralığına indirger.
	 *
	 * @param mixed $ham Ham dizi.
	 * @return int[]
	 */
	public static function gunleri_temizle( $ham ) {
		if ( ! is_array( $ham ) ) {
			return array();
		}

		$temiz = array();

		foreach ( $ham as $gun ) {
			$gun = (int) $gun;
			if ( $gun >= 1 && $gun <= 7 && ! in_array( $gun, $temiz, true ) ) {
				$temiz[] = $gun;
			}
		}

		sort( $temiz );

		return $temiz;
	}

	/**
	 * "HH:MM" doğrulaması. Geçersizse boş string.
	 *
	 * @param mixed $ham Ham saat.
	 * @return string
	 */
	public static function saati_temizle( $ham ) {
		$ham = trim( (string) $ham );

		if ( ! preg_match( '/^([01]\d|2[0-3]):([0-5]\d)$/', $ham ) ) {
			return '';
		}

		return $ham;
	}

	/* =============================================================
	   DURUM
	============================================================= */

	/**
	 * Ürün şu an servis dışı mı?
	 *
	 * @param int $post_id Ürün ID.
	 * @return bool
	 */
	public static function servis_disi_mi( $post_id ) {
		$kural = self::kural( $post_id );

		if ( ! $kural ) {
			return false;
		}

		return ! self::pencerede_mi( $kural );
	}

	/**
	 * Kural şu an geçerli mi? Gece yarısını aşan pencereler desteklenir.
	 *
	 * @param array    $kural  Kural dizisi.
	 * @param int|null $zaman  Test için sabit zaman damgası (site saati).
	 * @return bool
	 */
	public static function pencerede_mi( array $kural, $zaman = null ) {
		if ( null === $zaman ) {
			$zaman = function_exists( 'current_time' ) ? current_time( 'timestamp' ) : time();
		}

		$gun     = (int) gmdate( 'N', $zaman );
		$dakika  = ( (int) gmdate( 'G', $zaman ) * 60 ) + (int) gmdate( 'i', $zaman );
		$bas     = self::dakika( $kural['bas'] );
		$bit     = self::dakika( $kural['bit'] );
		$dun     = ( 1 === $gun ) ? 7 : $gun - 1;

		// Normal pencere: aynı gün içinde.
		if ( $bas < $bit ) {
			return in_array( $gun, $kural['gunler'], true ) && $dakika >= $bas && $dakika < $bit;
		}

		// Gece yarısını aşan pencere: başlangıç günü veya ertesi günün sabahı.
		if ( in_array( $gun, $kural['gunler'], true ) && $dakika >= $bas ) {
			return true;
		}

		return in_array( $dun, $kural['gunler'], true ) && $dakika < $bit;
	}

	/**
	 * "HH:MM" → gün içi dakika.
	 *
	 * @param string $saat Saat.
	 * @return int
	 */
	private static function dakika( $saat ) {
		list( $s, $d ) = array_pad( explode( ':', (string) $saat ), 2, '0' );

		return ( (int) $s * 60 ) + (int) $d;
	}

	/* =============================================================
	   GÖSTERİM
	============================================================= */

	/**
	 * "Servis: 07:00–11:00 (Hafta içi)" — kural yoksa boş.
	 *
	 * @param int $post_id Ürün ID.
	 * @return string
	 */
	public static function aciklama( $post_id ) {
		$kural = self::kural( $post_id );

		if ( ! $kural ) {
			return '';
		}

		return sprintf(
			'%s %s–%s · %s',
			self::cevir( 'Servis saatleri:' ),
			$kural['bas'],
			$kural['bit'],
			self::gun_metni( $kural['gunler'] )
		);
	}

	/**
	 * Gün listesinin okunabilir hâli.
	 *
	 * @param int[] $gunler Gün numaraları.
	 * @return string
	 */
	public static function gun_metni( array $gunler ) {
		if ( count( $gunler ) === 7 ) {
			return self::cevir( 'Her gün' );
		}
		if ( array( 1, 2, 3, 4, 5 ) === $gunler ) {
			return self::cevir( 'Hafta içi' );
		}
		if ( array( 6, 7 ) === $gunler ) {
			return self::cevir( 'Hafta sonu' );
		}

		$adlar = self::gunler();
		$out   = array();

		foreach ( $gunler as $gun ) {
			if ( isset( $adlar[ $gun ] ) ) {
				$out[] = self::cevir( $adlar[ $gun ] );
			}
		}

		return implode( ', ', $out );
	}

	/**
	 * Kart görselinin üstüne basılan etiket.
	 *
	 * @param int $post_id Ürün ID.
	 * @return string
	 */
	public static function rozet_html( $post_id ) {
		if ( ! self::servis_disi_mi( $post_id ) ) {
			return '';
		}

		return '<span class="rma-servis-rozet">' . esc_html( self::etiket() ) . '</span>';
	}

	/**
	 * Çevrilmiş etiket.
	 *
	 * @return string
	 */
	public static function etiket() {
		return self::cevir( self::ETIKET );
	}

	/**
	 * Müşteriye dönen engel mesajı.
	 *
	 * @param int $post_id Ürün ID.
	 * @return string
	 */
	public static function mesaj( $post_id ) {
		$kural = self::kural( $post_id );

		if ( ! $kural ) {
			return self::cevir( 'Bu ürün şu an servis edilmiyor' );
		}

		return sprintf(
			'%s (%s–%s · %s)',
			self::cevir( 'Bu ürün şu an servis edilmiyor' ),
			$kural['bas'],
			$kural['bit'],
			self::gun_metni( $kural['gunler'] )
		);
	}

	/**
	 * Menü önbelleği imzası.
	 *
	 * Servis penceresi açılıp kapandığında önbelleğe alınmış menü HTML'i
	 * kendiliğinden geçersizleşsin diye anahtara girer. Hiç kural yoksa
	 * sabit '0' döner — kısıt kullanmayan işletmelerde önbellek eskisi
	 * gibi uzun ömürlü kalır.
	 *
	 * @return string
	 */
	public static function imza() {
		if ( ! self::kural_var_mi() ) {
			return '0';
		}

		$simdi = function_exists( 'current_time' ) ? current_time( 'timestamp' ) : time();

		// Beş dakikalık kova: pencere sınırı en geç 5 dakikada yansır.
		return (string) (int) floor( $simdi / ( 5 * 60 ) );
	}

	/**
	 * Sitede tanımlı en az bir kural var mı? (kategori veya ürün)
	 *
	 * @return bool
	 */
	public static function kural_var_mi() {
		if ( isset( self::$memo['kural_var'] ) ) {
			return self::$memo['kural_var'];
		}

		$terimler = get_terms(
			array(
				'taxonomy'   => 'rma_category',
				'hide_empty' => false,
				'fields'     => 'ids',
				'number'     => 1,
				'meta_query' => array(
					array(
						'key'   => self::TERIM_AKTIF,
						'value' => '1',
					),
				),
			)
		);

		$var = ! is_wp_error( $terimler ) && ! empty( $terimler );

		if ( ! $var ) {
			$posts = get_posts(
				array(
					'post_type'              => 'rma_menu_item',
					'post_status'            => 'publish',
					'posts_per_page'         => 1,
					'fields'                 => 'ids',
					'no_found_rows'          => true,
					'update_post_meta_cache' => false,
					'update_post_term_cache' => false,
					'meta_key'               => self::META_MOD,
					'meta_value'             => 'ozel',
				)
			);

			$var = ! empty( $posts );
		}

		self::$memo['kural_var'] = $var;

		return $var;
	}

	/* =============================================================
	   SİPARİŞ ENGELİ
	============================================================= */

	/**
	 * Sipariş kalemleri arasında servis dışı ürün var mı?
	 *
	 * Kalemler öncelikle `item_id` ile eşleştirilir (sepet porsiyon adını
	 * ürün adına eklediği için ada göre eşleşme güvenilmez); id yoksa ada
	 * göre düşülür.
	 *
	 * @param array $kalemler Temizlenmiş sipariş kalemleri.
	 * @return array{mesaj:string,item_id:int,item_name:string}|null
	 */
	public static function siparis_engeli_detay( array $kalemler ) {
		foreach ( $kalemler as $kalem ) {
			if ( ! is_array( $kalem ) ) {
				continue;
			}

			$id = isset( $kalem['item_id'] ) ? (int) $kalem['item_id'] : 0;
			$ad = isset( $kalem['urunAdi'] ) ? (string) $kalem['urunAdi'] : '';

			if ( $id < 1 ) {
				$id = self::ada_gore_id( $ad );
			}

			if ( $id < 1 || 'rma_menu_item' !== get_post_type( $id ) ) {
				continue;
			}

			if ( self::servis_disi_mi( $id ) ) {
				return array(
					'mesaj'     => self::mesaj( $id ),
					'item_id'   => $id,
					'item_name' => get_post_field( 'post_title', $id ),
				);
			}
		}

		return null;
	}

	/**
	 * Ürün adına göre ID. Porsiyon eki "( … )" varsa atılır.
	 *
	 * @param string $ad Ürün adı.
	 * @return int
	 */
	private static function ada_gore_id( $ad ) {
		$ad = trim( preg_replace( '/\s*\([^()]*\)\s*$/u', '', (string) $ad ) );

		if ( '' === $ad ) {
			return 0;
		}

		// get_page_by_title() WP 6.2'de kullanımdan kaldırıldı; başlık
		// eşleşmesi doğrudan sorgulanır.
		$bulunan = get_posts(
			array(
				'post_type'              => 'rma_menu_item',
				'post_status'            => 'publish',
				'title'                  => $ad,
				'posts_per_page'         => 1,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		return ! empty( $bulunan ) ? (int) $bulunan[0] : 0;
	}

	/**
	 * `qmo_siparis_onay_oncesi` filtresi — önceki engel varsa dokunmaz.
	 *
	 * @param string|array|null $engel    Önceki filtrenin sonucu.
	 * @param array             $kalemler Sipariş kalemleri.
	 * @return string|array|null
	 */
	public static function siparis_filtresi( $engel, $kalemler ) {
		if ( is_array( $engel ) && ! empty( $engel['mesaj'] ) ) {
			return $engel;
		}

		if ( is_string( $engel ) && '' !== $engel ) {
			return $engel;
		}

		if ( ! is_array( $kalemler ) ) {
			return $engel;
		}

		$detay = self::siparis_engeli_detay( $kalemler );

		return null !== $detay ? $detay : $engel;
	}

	/**
	 * Sabit arayüz metni çevirisi.
	 *
	 * @param string $metin Türkçe metin.
	 * @return string
	 */
	private static function cevir( $metin ) {
		return function_exists( 'rma_ceviri_ui' ) ? rma_ceviri_ui( $metin ) : $metin;
	}
}

endif;

/**
 * Ürün şu an servis dışı mı — modül dışından okunabilsin diye global köprü.
 *
 * @param int $post_id Ürün ID.
 * @return bool
 */
function rma_urun_servis_disi( $post_id ) {
	return class_exists( 'RMA_Servis_Saati' ) ? RMA_Servis_Saati::servis_disi_mi( $post_id ) : false;
}
