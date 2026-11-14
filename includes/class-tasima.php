<?php
/**
 * Taşıma: ayarları, menü içeriğini ve görselleri tek dosyayla başka siteye aktarır.
 *
 * Neden ayrı bir araç: WordPress'in kendi dışa aktarması eklenti ayarlarını
 * (option) taşımaz; taşıdığı içerikte de görsel, menü ve sayfa kimlikleri yeni
 * sitede değiştiği için ayarlar yanlış kayda bağlı kalır. Bu sınıf kimlikleri
 * yeni siteye göre yeniden eşler.
 *
 * Güvenlik kuralları:
 *  - Paket yalnızca JSON'dur; unserialize() hiçbir yerde kullanılmaz.
 *  - Lisans/API anahtarı, şifre/PIN özeti, durum ve önbellek alanları pakete
 *    girmez ve içe aktarmada da (paket elle değiştirilmiş olsa bile) yazılmaz.
 *  - Görseller yalnızca paketin kaynak alan adından ve /wp-content/uploads/
 *    yolundan indirilir (rastgele adrese istek atılamaz).
 *  - Her istek yetki (manage_options) ve nonce ister.
 *
 * @package QR_Menu_Suite
 */

defined( 'ABSPATH' ) || exit;

/**
 * Taşıma ekranı ve dışa/içe aktarma işlemleri.
 */
class QRMS_Tasima {

	const FORMAT     = 'qrms-tasima';
	const SURUM      = 1;
	const NONCE      = 'qrms_tasima';
	const MAX_BOYUT  = 26214400; // 25 MB.
	const ADIM_SURE  = 18;       // Bir istekte en fazla bu kadar saniye çalış.
	const MEDYA_ADIM = 4;
	const YAZI_ADIM  = 12;

	/**
	 * Seçilebilir gruplar: etiket, yazı tipleri, sınıflandırmalar.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function gruplar() {
		return array(
			'ayarlar'   => array(
				'etiket' => __( 'Ayarlar (renkler, header/footer, çalışma saatleri, çeviri, asistan görünümü)', 'qrms' ),
				'tipler' => array(),
				'taxlar' => array(),
			),
			'menu'      => array(
				'etiket' => __( 'Menü: ürünler, kategoriler, alerjenler', 'qrms' ),
				'tipler' => array( 'rma_menu_item' ),
				'taxlar' => array( 'rma_category', 'rma_allergen' ),
			),
			'galeri'    => array(
				'etiket' => __( 'Fotoğraf galerisi', 'qrms' ),
				'tipler' => array( 'qrmgm_section', 'qrmgm_image' ),
				'taxlar' => array(),
			),
			'sayfalar'  => array(
				'etiket' => __( 'Sayfalar, header/footer şablonları, Elementor kiti ve menüler', 'qrms' ),
				'tipler' => array( 'page', 'elementor-hf', 'elementor_library', 'nav_menu_item' ),
				'taxlar' => array( 'nav_menu' ),
			),
			'ceviriler' => array(
				'etiket' => __( 'Diller ve çeviriler (ürün, kategori, sayfa, menü ve arayüz metinleri)', 'qrms' ),
				'tipler' => array(),
				'taxlar' => array(),
			),
			'bannerlar' => array(
				'etiket' => __( 'Bannerlar ve açılış slaytları', 'qrms' ),
				'tipler' => array( 'qmo_slide', 'qmo_banner_slide' ),
				'taxlar' => array(),
			),
		);
	}

	/** Görsel (ek) kimliği taşıyan yazı meta anahtarları. */
	const EK_META = array( '_thumbnail_id', '_qmo_banner_gorsel_id', '_qrmgm_attachment_id' );

	/** Görsel kimliği taşıyan ayar alanları (iç içe dizilerde de aranır). */
	const EK_ALAN = array( 'logo', 'bg_image', 'bg_image_wide', 'arkaplan_gorsel' );

	/** Gezinme menüsü (nav_menu) kimliği taşıyan ayar alanları. */
	const MENU_ALAN = array( 'menu_id' );

	/** Yazı kimliği listesi taşıyan ayar alanları. */
	const YAZI_ALAN = array( 'manual_ids' );

	/** Yazı durumları. */
	const DURUMLAR = array( 'publish', 'draft', 'private', 'pending', 'future' );

	/**
	 * Kancaları bağlar.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_post_qrms_tasima_disa', array( __CLASS__, 'disa_aktar' ) );
		add_action( 'wp_ajax_qrms_tasima_hazirla', array( __CLASS__, 'ajax_hazirla' ) );
		add_action( 'wp_ajax_qrms_tasima_adim', array( __CLASS__, 'ajax_adim' ) );
	}

	/* ------------------------------------------------------------------
	 * Saf yardımcılar (WordPress çağrısı yok; tests/tasima-test.php sınar)
	 * ---------------------------------------------------------------- */

	/**
	 * Ayar adı pakete girebilir mi?
	 *
	 * @param string $ad Option adı.
	 * @return bool
	 */
	public static function secenek_izinli( $ad ) {
		$ad = (string) $ad;

		$izin = '/^(hfb_|qmo_banner_slider_settings$|qmo_chatbot_|qmo_sepet_aktif$|qmo_ana_site$|gemini_|qrm_settings$|qrm_reward_templates$|qrmenu_|qrmgm_settings$|qrms_calisma_saatleri|qrms_marka_kimligi$|qrms_login_ayarlar$|qrms_iu_imlec$|qrms_mm_malzeme_fiyat$|rma_color_settings$|rma_nav_design_settings$|rma_typo_settings$|rma_ceviri_(ek_metinler|url_yonlendir|urun_tipleri|tampon_acik|toplama_acik)$|rma_ozel_rozetler$|rma_suggestions_settings$|splash_screen_options$|blogname$|blogdescription$)/';
		if ( ! preg_match( $izin, $ad ) ) {
			return false;
		}

		// Gizli anahtar, kimlik doğrulama ve siteye/sunucuya bağlı durum asla taşınmaz.
		$yasak = '/(api_key|apikey|secret|hash|pin$|_pin|license|lisans|_sa$|firebase|menu_json_data|db_surum|version|cache|hmac|token|branch_id|session|salt|password|parola)/i';

		return ! preg_match( $yasak, $ad );
	}

	/** Yazı (post) kimliğiyle anahtarlı çeviri tipleri. */
	const CEVIRI_YAZI_TIPLERI = array( 'product', 'nav_menu', 'elementor' );

	/** Terim kimliğiyle anahtarlı çeviri tipleri. */
	const CEVIRI_TERIM_TIPLERI = array( 'category', 'allergen' );

	/**
	 * Bir çeviri satırının hedef sitedeki kimliğini verir.
	 *
	 * @param string $tip     Çeviri öğe tipi.
	 * @param int    $eski_id Kaynak sitedeki kimlik (0: sabit metin).
	 * @param array  $harita  array('yazi' => [...], 'terim' => [...]).
	 * @return int|null Hedef kimlik; 0 sabit metin; null: eşlenemedi (satır atlanır).
	 */
	public static function ceviri_kimlik_cevir( $tip, $eski_id, array $harita ) {
		$eski_id = (int) $eski_id;
		if ( 0 === $eski_id ) {
			return 0;
		}
		if ( in_array( $tip, self::CEVIRI_YAZI_TIPLERI, true ) ) {
			return isset( $harita['yazi'][ $eski_id ] ) ? (int) $harita['yazi'][ $eski_id ] : null;
		}
		if ( in_array( $tip, self::CEVIRI_TERIM_TIPLERI, true ) ) {
			return isset( $harita['terim'][ $eski_id ] ) ? (int) $harita['terim'][ $eski_id ] : null;
		}

		// Başka modüllerin tabloları (yorum formları vb.) bu pakette taşınmaz.
		return null;
	}

	/**
	 * Çeviri satırı geçerli mi (paket güvenilmez kabul edilir)?
	 *
	 * @param mixed $r Satır.
	 * @return bool
	 */
	public static function ceviri_satiri_gecerli( $r ) {
		return is_array( $r )
			&& isset( $r['tip'], $r['alan'], $r['dil'], $r['orijinal'], $r['ceviri'] )
			&& is_string( $r['tip'] ) && preg_match( '/^[a-z0-9_]{1,20}$/', $r['tip'] )
			&& is_string( $r['alan'] ) && '' !== $r['alan'] && strlen( $r['alan'] ) <= 191
			&& is_string( $r['dil'] ) && 'tr' !== $r['dil'] && preg_match( '/^[A-Za-z]{2,3}(-[A-Za-z0-9]{2,4})?$/', $r['dil'] )
			&& is_string( $r['orijinal'] ) && is_string( $r['ceviri'] );
	}

	/**
	 * Ayar değerinin içindeki kimlik bilgisi alanlarını (API anahtarı, gizli anahtar,
	 * belirteç…) ayıklar. Müşteriye gösterilen alanlara (ör. Wi-Fi şifresi) dokunmaz.
	 *
	 * @param mixed $deger Ayar değeri.
	 * @return mixed
	 */
	public static function gizli_ayikla( $deger ) {
		if ( ! is_array( $deger ) ) {
			return $deger;
		}

		foreach ( $deger as $k => $v ) {
			if ( ! is_int( $k ) && preg_match( '/(api_?key|secret|token|private_?key|credential)/i', (string) $k ) ) {
				unset( $deger[ $k ] );
				continue;
			}
			$deger[ $k ] = self::gizli_ayikla( $v );
		}

		return $deger;
	}

	/**
	 * Yazı meta anahtarı taşınabilir mi?
	 *
	 * @param string $anahtar Meta anahtarı.
	 * @return bool
	 */
	public static function meta_izinli( $anahtar ) {
		$anahtar = (string) $anahtar;

		if ( ! preg_match( '/^[A-Za-z0-9_\-:.]{1,191}$/', $anahtar ) ) {
			return false;
		}

		// Sunucuya özgü, geçici veya yeniden üretilen alanlar.
		return ! preg_match( '/^(_wp_(?!page_template$)|_edit_|_encloseme|_pingme|_oembed_|_qrmgm_webp_|_elementor_css|_elementor_element_cache|_elementor_page_assets|_elementor_inline_svg|rma_views$|_qrms_kaynak_)/', $anahtar );
	}

	/**
	 * Boyut ekini (-300x200) atıp özgün görsel adresini verir.
	 *
	 * @param string $url Görsel adresi.
	 * @return string
	 */
	public static function ozgun_url( $url ) {
		return (string) preg_replace( '/-\d{2,5}x\d{2,5}(\.[A-Za-z0-9]{2,5})$/', '$1', (string) $url );
	}

	/**
	 * Görsel adresi indirmeye uygun mu? Yalnızca paketin kendi kaynak sitesinin
	 * uploads klasöründeki görseller; yol geçişi (..), sorgu ve görsel dışı uzantı reddedilir.
	 *
	 * @param string $url             İndirilecek adres.
	 * @param string $kaynak_uploads  Paketin bildirdiği uploads taban adresi.
	 * @return bool
	 */
	public static function medya_url_guvenli( $url, $kaynak_uploads ) {
		$url = (string) $url;
		$u   = parse_url( $url );
		$k   = parse_url( (string) $kaynak_uploads );

		if ( ! is_array( $u ) || ! is_array( $k ) || empty( $u['host'] ) || empty( $k['host'] ) || empty( $u['path'] ) || empty( $k['path'] ) ) {
			return false;
		}
		if ( ! in_array( isset( $u['scheme'] ) ? strtolower( $u['scheme'] ) : '', array( 'http', 'https' ), true ) ) {
			return false;
		}
		if ( isset( $u['query'] ) || isset( $u['fragment'] ) || isset( $u['user'] ) || isset( $u['pass'] ) ) {
			return false;
		}
		if ( strtolower( $u['host'] ) !== strtolower( $k['host'] ) || ( isset( $u['port'] ) ? $u['port'] : 0 ) !== ( isset( $k['port'] ) ? $k['port'] : 0 ) ) {
			return false;
		}

		$kok = rtrim( $k['path'], '/' ) . '/';
		if ( 0 !== strpos( $u['path'], $kok ) ) {
			return false;
		}
		// Yol geçişi: düz, kodlanmış ve ters eğik çizgili biçimler.
		if ( preg_match( '#(^|/|\\\\)\.\.?(/|\\\\|$)|%2e|%2f|%5c|\\\\|\x00#i', substr( $u['path'], strlen( $kok ) ) ) ) {
			return false;
		}

		return (bool) preg_match( '/\.(jpe?g|png|gif|webp|avif|bmp|ico)$/i', $u['path'] );
	}

	/**
	 * Metindeki uploads adreslerini bulur (JSON kaçışlı "\/" biçimi dahil).
	 *
	 * @param string $metin     Aranan metin.
	 * @param string $yukleme   Kaynak sitenin uploads taban adresi.
	 * @return string[] Tekil adresler (kaçışsız).
	 */
	public static function medya_url_cikar( $metin, $yukleme ) {
		$metin   = str_replace( '\\/', '/', (string) $metin );
		$yukleme = rtrim( (string) $yukleme, '/' );

		if ( '' === $yukleme || false === strpos( $metin, $yukleme ) ) {
			return array();
		}

		$bulunan = array();
		if ( preg_match_all( '~' . preg_quote( $yukleme, '~' ) . '/[^\s"\'<>\\\\)\],;]+~i', $metin, $m ) ) {
			foreach ( $m[0] as $u ) {
				$bulunan[ $u ] = true;
			}
		}

		return array_keys( $bulunan );
	}

	/**
	 * Değerdeki (iç içe) bütün dizgilerde bir dönüştürücü çalıştırır.
	 *
	 * @param mixed    $deger Değer.
	 * @param callable $fn    function(string): string.
	 * @return mixed
	 */
	public static function dizgileri_dolas( $deger, $fn ) {
		if ( is_string( $deger ) ) {
			return $fn( $deger );
		}
		if ( is_array( $deger ) ) {
			foreach ( $deger as $k => $v ) {
				$deger[ $k ] = self::dizgileri_dolas( $v, $fn );
			}
		}

		return $deger;
	}

	/**
	 * Kaynak sitenin adreslerini ve görsel adreslerini yeni siteye çevirir.
	 *
	 * @param string $metin       Metin.
	 * @param string $kaynak_home Kaynak site ana adresi.
	 * @param string $hedef_home  Bu sitenin ana adresi.
	 * @param array  $medya       Özgün kaynak adres => array('url' => yeni özgün adres, 'boyutlar' => array('300x200' => adres)).
	 * @return string
	 */
	public static function url_cevir( $metin, $kaynak_home, $hedef_home, array $medya ) {
		$metin = (string) $metin;
		if ( '' === $metin ) {
			return $metin;
		}

		$kaynak_home = rtrim( (string) $kaynak_home, '/' );
		$hedef_home  = rtrim( (string) $hedef_home, '/' );

		// Aynı dönüşüm önce düz, sonra JSON kaçışlı ("https:\/\/…") biçimde uygulanır.
		$metin = self::url_cevir_tek( $metin, $kaynak_home, $hedef_home, $medya );

		if ( false !== strpos( $metin, '\\/' ) ) {
			$kacis = function ( $x ) {
				return str_replace( '/', '\\/', $x );
			};
			$medya_k = array();
			foreach ( $medya as $eski => $yeni ) {
				$boyut = array();
				foreach ( isset( $yeni['boyutlar'] ) ? (array) $yeni['boyutlar'] : array() as $k => $u ) {
					$boyut[ $k ] = $kacis( $u );
				}
				$medya_k[ $kacis( $eski ) ] = array( 'url' => $kacis( $yeni['url'] ), 'boyutlar' => $boyut );
			}
			$metin = self::url_cevir_tek( $metin, $kacis( $kaynak_home ), $kacis( $hedef_home ), $medya_k );
		}

		return $metin;
	}

	/**
	 * url_cevir() için tek biçimli (düz) çeviri.
	 *
	 * @param string $metin       Metin.
	 * @param string $kaynak_home Kaynak ana adres.
	 * @param string $hedef_home  Hedef ana adres.
	 * @param array  $medya       Görsel eşlemesi.
	 * @return string
	 */
	private static function url_cevir_tek( $metin, $kaynak_home, $hedef_home, array $medya ) {
		foreach ( $medya as $eski => $yeni ) {
			$govde  = (string) preg_replace( '/(\.[A-Za-z0-9]{2,5})$/', '', (string) $eski );
			$uzanti = substr( (string) $eski, strlen( $govde ) );
			if ( '' === $govde || false === strpos( $metin, $govde ) ) {
				continue;
			}

			$metin = preg_replace_callback(
				'~' . preg_quote( $govde, '~' ) . '(?:-(\d{2,5}x\d{2,5}))?' . preg_quote( $uzanti, '~' ) . '~',
				function ( $m ) use ( $yeni ) {
					if ( ! empty( $m[1] ) && ! empty( $yeni['boyutlar'][ $m[1] ] ) ) {
						return $yeni['boyutlar'][ $m[1] ];
					}

					return $yeni['url'];
				},
				$metin
			);
		}

		if ( '' !== $kaynak_home && $kaynak_home !== $hedef_home ) {
			$metin = str_replace( $kaynak_home, $hedef_home, $metin );
		}

		return $metin;
	}

	/**
	 * Ayar değerindeki kimlikleri yeni siteye göre çevirir.
	 *
	 * @param mixed $deger Ayar değeri.
	 * @param array $harita array('ek' => [eski=>yeni], 'menu' => [...], 'yazi' => [...]).
	 * @return mixed
	 */
	public static function kimlik_cevir( $deger, array $harita ) {
		if ( ! is_array( $deger ) ) {
			return $deger;
		}

		foreach ( $deger as $anahtar => $v ) {
			if ( is_array( $v ) ) {
				if ( in_array( $anahtar, self::YAZI_ALAN, true ) ) {
					$yeni = array();
					foreach ( $v as $id ) {
						if ( isset( $harita['yazi'][ (int) $id ] ) ) {
							$yeni[] = $harita['yazi'][ (int) $id ];
						}
					}
					$deger[ $anahtar ] = $yeni;
				} else {
					$deger[ $anahtar ] = self::kimlik_cevir( $v, $harita );
				}
				continue;
			}

			if ( ! is_int( $anahtar ) && is_numeric( $v ) && (int) $v > 0 ) {
				$id = (int) $v;
				if ( in_array( $anahtar, self::EK_ALAN, true ) ) {
					$deger[ $anahtar ] = isset( $harita['ek'][ $id ] ) ? $harita['ek'][ $id ] : 0;
				} elseif ( in_array( $anahtar, self::MENU_ALAN, true ) ) {
					$deger[ $anahtar ] = isset( $harita['menu'][ $id ] ) ? $harita['menu'][ $id ] : 0;
				}
			}
		}

		return $deger;
	}

	/* ------------------------------------------------------------------
	 * Dışa aktarma
	 * ---------------------------------------------------------------- */

	/**
	 * Seçilen gruplar: formdan gelen değeri bilinen gruplara indirger.
	 *
	 * @param mixed $girdi Ham değer.
	 * @return string[]
	 */
	private static function secili_gruplar( $girdi ) {
		$girdi = is_array( $girdi ) ? array_map( 'sanitize_key', wp_unslash( $girdi ) ) : array();

		return array_values( array_intersect( array_keys( self::gruplar() ), $girdi ) );
	}

	/**
	 * Seçili gruplardan yazı tipleri ve sınıflandırmalar.
	 *
	 * @param string[] $secili Seçili gruplar.
	 * @return array{0:string[],1:string[]}
	 */
	private static function tip_ve_taxlar( array $secili ) {
		$tipler = array();
		$taxlar = array();

		foreach ( self::gruplar() as $ad => $g ) {
			if ( in_array( $ad, $secili, true ) ) {
				$tipler = array_merge( $tipler, $g['tipler'] );
				$taxlar = array_merge( $taxlar, $g['taxlar'] );
			}
		}

		return array( array_values( array_unique( $tipler ) ), array_values( array_unique( $taxlar ) ) );
	}

	/**
	 * Paketi hazırlar.
	 *
	 * @param string[] $secili Seçili gruplar.
	 * @return array<string,mixed>
	 */
	public static function paket_olustur( array $secili ) {
		global $wpdb;

		$yukleme = wp_get_upload_dir();
		$paket   = array(
			'format'          => self::FORMAT,
			'surum'           => self::SURUM,
			'eklenti_surumu'  => defined( 'QRMS_VERSION' ) ? QRMS_VERSION : '',
			'kaynak'          => array(
				'home'    => untrailingslashit( home_url() ),
				'uploads' => untrailingslashit( $yukleme['baseurl'] ),
				'tarih'   => gmdate( 'c' ),
			),
			'gruplar'         => $secili,
			'secenekler'      => array(),
			'ozel'            => array(),
			'terimler'        => array(),
			'yazilar'         => array(),
			'ceviriler'       => array(),
			'medya'           => array(),
		);

		list( $tipler, $taxlar ) = self::tip_ve_taxlar( $secili );

		// Ayarlar.
		if ( in_array( 'ayarlar', $secili, true ) ) {
			$adlar = $wpdb->get_col( "SELECT option_name FROM {$wpdb->options}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			foreach ( (array) $adlar as $ad ) {
				if ( self::secenek_izinli( $ad ) ) {
					$paket['secenekler'][ $ad ] = self::gizli_ayikla( get_option( $ad ) );
				}
			}
		}

		// Sınıflandırma terimleri (menü kategorileri, gezinme menüleri…).
		foreach ( $taxlar as $tax ) {
			if ( ! taxonomy_exists( $tax ) ) {
				continue;
			}
			$terimler = get_terms( array( 'taxonomy' => $tax, 'hide_empty' => false ) );
			if ( is_wp_error( $terimler ) ) {
				continue;
			}
			foreach ( $terimler as $t ) {
				$meta = array();
				foreach ( (array) get_term_meta( $t->term_id ) as $k => $degerler ) {
					if ( self::meta_izinli( $k ) ) {
						$meta[ $k ] = array_map( 'maybe_unserialize', $degerler );
					}
				}
				$paket['terimler'][] = array(
					'id'     => (int) $t->term_id,
					'tax'    => $tax,
					'slug'   => $t->slug,
					'ad'     => $t->name,
					'ust'    => (int) $t->parent,
					'aciklama' => $t->description,
					'meta'   => $meta,
				);
			}
		}

		// Yazılar.
		foreach ( $tipler as $tip ) {
			$liste = get_posts(
				array(
					'post_type'        => $tip,
					'post_status'      => self::DURUMLAR,
					'numberposts'      => -1,
					'orderby'          => 'ID',
					'order'            => 'ASC',
					'suppress_filters' => true,
				)
			);
			foreach ( $liste as $p ) {
				$meta = array();
				foreach ( (array) get_post_meta( $p->ID ) as $k => $degerler ) {
					if ( self::meta_izinli( $k ) ) {
						$meta[ $k ] = array_map( 'maybe_unserialize', $degerler );
					}
				}
				$ter = array();
				foreach ( $taxlar as $tax ) {
					if ( taxonomy_exists( $tax ) && is_object_in_taxonomy( $tip, $tax ) ) {
						$ids = wp_get_object_terms( $p->ID, $tax, array( 'fields' => 'ids' ) );
						if ( ! is_wp_error( $ids ) && $ids ) {
							$ter[ $tax ] = array_map( 'intval', $ids );
						}
					}
				}
				$paket['yazilar'][] = array(
					'id'       => (int) $p->ID,
					'tip'      => $tip,
					'durum'    => $p->post_status,
					'baslik'   => $p->post_title,
					'slug'     => $p->post_name,
					'icerik'   => $p->post_content,
					'ozet'     => $p->post_excerpt,
					'ust'      => (int) $p->post_parent,
					'sira'     => (int) $p->menu_order,
					'tarih'    => $p->post_date,
					'meta'     => $meta,
					'terimler' => $ter,
				);
			}
		}

		// Ön sayfa ve etkin Elementor kiti (kimlikle; içe aktarmada yeniden eşlenir).
		if ( in_array( 'sayfalar', $secili, true ) ) {
			$paket['ozel'] = array(
				'show_on_front'        => get_option( 'show_on_front' ),
				'page_on_front'        => (int) get_option( 'page_on_front' ),
				'elementor_active_kit' => (int) get_option( 'elementor_active_kit' ),
			);
		}

		// Çeviri tablosu (kimlikler içe aktarmada yeniden eşlenir).
		if ( in_array( 'ceviriler', $secili, true ) && class_exists( 'RMA_Ceviri_Tablo' ) && RMA_Ceviri_Tablo::tablo_var_mi() ) {
			$satirlar = $wpdb->get_results( 'SELECT item_id, item_type, field, lang_code, original_text, translated_text FROM ' . RMA_Ceviri_Tablo::tablo() . ' ORDER BY item_type, item_id, field, lang_code', ARRAY_A ); // phpcs:ignore WordPress.DB
			foreach ( (array) $satirlar as $r ) {
				$paket['ceviriler'][] = array(
					'id'       => (int) $r['item_id'],
					'tip'      => $r['item_type'],
					'alan'     => $r['field'],
					'dil'      => $r['lang_code'],
					'orijinal' => $r['original_text'],
					'ceviri'   => $r['translated_text'],
				);
			}
		}

		self::medya_topla( $paket );

		return $paket;
	}

	/**
	 * Pakette geçen görselleri (kimlikle ve adresle) listeler.
	 *
	 * @param array $paket Paket (referansla güncellenir).
	 * @return void
	 */
	private static function medya_topla( array &$paket ) {
		$yukleme = $paket['kaynak']['uploads'];
		$liste   = array(); // Özgün adres => kayıt.

		$ekle = function ( $url, $kaynak_id = 0 ) use ( &$liste ) {
			$ozgun = QRMS_Tasima::ozgun_url( $url );
			if ( ! isset( $liste[ $ozgun ] ) ) {
				$liste[ $ozgun ] = array( 'url' => $ozgun, 'ids' => array(), 'baslik' => '', 'alt' => '' );
			}
			if ( $kaynak_id ) {
				$liste[ $ozgun ]['ids'][ $kaynak_id ] = true;
			}
		};

		$id_ekle = function ( $id ) use ( $ekle, &$liste ) {
			$id = (int) $id;
			if ( $id <= 0 ) {
				return;
			}
			$url = wp_get_attachment_url( $id );
			if ( ! $url ) {
				return;
			}
			$ekle( $url, $id );
			$ozgun = QRMS_Tasima::ozgun_url( $url );
			$p     = get_post( $id );
			if ( $p ) {
				$liste[ $ozgun ]['baslik'] = $p->post_title;
				$liste[ $ozgun ]['alt']    = (string) get_post_meta( $id, '_wp_attachment_image_alt', true );
			}
		};

		$tara = function ( $deger ) use ( $yukleme, $ekle ) {
			QRMS_Tasima::dizgileri_dolas(
				$deger,
				function ( $s ) use ( $yukleme, $ekle ) {
					foreach ( QRMS_Tasima::medya_url_cikar( $s, $yukleme ) as $u ) {
						$ekle( $u );
					}

					return $s;
				}
			);
		};

		// Ayarlardaki görsel kimlikleri ve adresleri.
		$ek_alan = self::EK_ALAN;
		$gez     = function ( $v ) use ( &$gez, $ek_alan, $id_ekle ) {
			if ( is_array( $v ) ) {
				foreach ( $v as $k => $x ) {
					if ( is_array( $x ) ) {
						$gez( $x );
					} elseif ( ! is_int( $k ) && in_array( $k, $ek_alan, true ) && is_numeric( $x ) ) {
						$id_ekle( $x );
					}
				}
			}
		};
		foreach ( $paket['secenekler'] as $deger ) {
			$gez( $deger );
			$tara( $deger );
		}

		// Yazılar: içerik, meta ve görsel kimlik alanları.
		foreach ( $paket['yazilar'] as $y ) {
			$tara( $y['icerik'] );
			foreach ( $y['meta'] as $k => $degerler ) {
				foreach ( $degerler as $d ) {
					if ( in_array( $k, self::EK_META, true ) ) {
						$id_ekle( $d );
					} else {
						$tara( $d );
					}
				}
			}
		}

		$paket['medya'] = array_values(
			array_map(
				function ( $m ) {
					$m['ids'] = array_map( 'intval', array_keys( $m['ids'] ) );

					return $m;
				},
				$liste
			)
		);
	}

	/**
	 * İndirme isteği: JSON dosyası.
	 *
	 * @return void
	 */
	public static function disa_aktar() {
		if ( ! current_user_can( QRMS_Admin::CAPABILITY ) ) {
			wp_die( esc_html__( 'Bu işlem için yetkiniz yok.', 'qrms' ), 403 );
		}
		check_admin_referer( self::NONCE );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$secili = self::secili_gruplar( isset( $_POST['gruplar'] ) ? $_POST['gruplar'] : array() );
		if ( ! $secili ) {
			wp_die( esc_html__( 'En az bir grup seçin.', 'qrms' ) );
		}

		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 120 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		}

		$json = wp_json_encode( self::paket_olustur( $secili ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR );
		$ad   = 'qrms-tasima-' . sanitize_file_name( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) . '-' . gmdate( 'Ymd-His' ) . '.json';

		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $ad . '"' );
		header( 'X-Content-Type-Options: nosniff' );
		echo $json; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}

	/* ------------------------------------------------------------------
	 * İçe aktarma (adım adım; her istek kısa sürer)
	 * ---------------------------------------------------------------- */

	/**
	 * Geçici durum dosyalarının klasörü (web'den kapalı).
	 *
	 * @return string|false
	 */
	private static function durum_klasoru() {
		$u = wp_get_upload_dir();
		if ( ! empty( $u['error'] ) ) {
			return false;
		}
		$dir = trailingslashit( $u['basedir'] ) . 'qrms-tasima';
		if ( ! wp_mkdir_p( $dir ) ) {
			return false;
		}
		if ( ! file_exists( $dir . '/index.php' ) ) {
			@file_put_contents( $dir . '/index.php', "<?php\n// Sessizlik altındır.\n" ); // phpcs:ignore
		}
		if ( ! file_exists( $dir . '/.htaccess' ) ) {
			@file_put_contents( $dir . '/.htaccess', "Require all denied\nDeny from all\n" ); // phpcs:ignore
		}

		return $dir;
	}

	/**
	 * @param string $token Belirteç.
	 * @return string|false Dosya yolu.
	 */
	private static function durum_yolu( $token ) {
		if ( ! preg_match( '/^[a-f0-9]{32}$/', (string) $token ) ) {
			return false;
		}
		$dir = self::durum_klasoru();

		return $dir ? $dir . '/' . $token . '.json' : false;
	}

	/**
	 * @param string $token Belirteç.
	 * @return array|null
	 */
	private static function durum_oku( $token ) {
		$yol = self::durum_yolu( $token );
		if ( ! $yol || ! is_readable( $yol ) ) {
			return null;
		}
		$d = json_decode( (string) file_get_contents( $yol ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions

		return is_array( $d ) ? $d : null;
	}

	/**
	 * @param string $token Belirteç.
	 * @param array  $durum Durum.
	 * @return bool
	 */
	private static function durum_yaz( $token, array $durum ) {
		$yol = self::durum_yolu( $token );

		return $yol && false !== file_put_contents( $yol, wp_json_encode( $durum, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ); // phpcs:ignore
	}

	/**
	 * Ortak ajax denetimi.
	 *
	 * @return void
	 */
	private static function ajax_yetki() {
		if ( ! current_user_can( QRMS_Admin::CAPABILITY ) ) {
			wp_send_json_error( array( 'mesaj' => __( 'Bu işlem için yetkiniz yok.', 'qrms' ) ), 403 );
		}
		check_ajax_referer( self::NONCE, 'nonce' );
	}

	/**
	 * Paketi yükler, doğrular ve durum dosyasını açar.
	 *
	 * @return void
	 */
	public static function ajax_hazirla() {
		self::ajax_yetki();

		if ( empty( $_FILES['paket']['tmp_name'] ) || ! is_uploaded_file( $_FILES['paket']['tmp_name'] ) ) { // phpcs:ignore
			wp_send_json_error( array( 'mesaj' => __( 'Dosya seçilmedi.', 'qrms' ) ) );
		}
		if ( (int) $_FILES['paket']['size'] > self::MAX_BOYUT ) { // phpcs:ignore
			wp_send_json_error( array( 'mesaj' => __( 'Dosya 25 MB sınırını aşıyor.', 'qrms' ) ) );
		}

		$ham   = file_get_contents( $_FILES['paket']['tmp_name'] ); // phpcs:ignore
		$paket = json_decode( (string) $ham, true, 64 );
		if ( ! is_array( $paket ) || ( isset( $paket['format'] ) ? $paket['format'] : '' ) !== self::FORMAT ) {
			wp_send_json_error( array( 'mesaj' => __( 'Bu dosya bir QR Menu Suite taşıma paketi değil.', 'qrms' ) ) );
		}
		if ( (int) ( isset( $paket['surum'] ) ? $paket['surum'] : 0 ) > self::SURUM ) {
			wp_send_json_error( array( 'mesaj' => __( 'Paket daha yeni bir eklenti sürümüyle hazırlanmış. Önce bu siteyi güncelleyin.', 'qrms' ) ) );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$secili = self::secili_gruplar( isset( $_POST['gruplar'] ) ? $_POST['gruplar'] : array() );
		$secili = array_values( array_intersect( $secili, isset( $paket['gruplar'] ) && is_array( $paket['gruplar'] ) ? $paket['gruplar'] : array() ) );
		if ( ! $secili ) {
			wp_send_json_error( array( 'mesaj' => __( 'Seçtiğiniz gruplar pakette yok.', 'qrms' ) ) );
		}

		$kaynak = isset( $paket['kaynak'] ) && is_array( $paket['kaynak'] ) ? $paket['kaynak'] : array();
		if ( empty( $kaynak['home'] ) || empty( $kaynak['uploads'] ) ) {
			wp_send_json_error( array( 'mesaj' => __( 'Paketin kaynak bilgisi eksik.', 'qrms' ) ) );
		}

		// Eski, yarım kalmış durum dosyalarını sil (1 günden eski).
		$dir = self::durum_klasoru();
		if ( ! $dir ) {
			wp_send_json_error( array( 'mesaj' => __( 'Geçici klasör oluşturulamadı (uploads yazılabilir mi?).', 'qrms' ) ) );
		}
		foreach ( (array) glob( $dir . '/*.json' ) as $f ) {
			if ( filemtime( $f ) < time() - DAY_IN_SECONDS ) {
				@unlink( $f ); // phpcs:ignore
			}
		}

		list( $tipler, $taxlar ) = self::tip_ve_taxlar( $secili );

		// Yalnızca seçili gruplara ait kayıtları tut.
		$paket['terimler'] = array_values(
			array_filter(
				isset( $paket['terimler'] ) ? (array) $paket['terimler'] : array(),
				function ( $t ) use ( $taxlar ) {
					return isset( $t['tax'] ) && in_array( $t['tax'], $taxlar, true );
				}
			)
		);
		$paket['yazilar']  = array_values(
			array_filter(
				isset( $paket['yazilar'] ) ? (array) $paket['yazilar'] : array(),
				function ( $y ) use ( $tipler ) {
					return isset( $y['tip'] ) && in_array( $y['tip'], $tipler, true );
				}
			)
		);
		if ( ! in_array( 'ayarlar', $secili, true ) ) {
			$paket['secenekler'] = array();
		}
		$paket['ceviriler'] = in_array( 'ceviriler', $secili, true ) && isset( $paket['ceviriler'] ) && is_array( $paket['ceviriler'] )
			? array_values( array_filter( $paket['ceviriler'], array( __CLASS__, 'ceviri_satiri_gecerli' ) ) )
			: array();
		if ( ! in_array( 'sayfalar', $secili, true ) ) {
			$paket['ozel'] = array();
		}

		$token = bin2hex( random_bytes( 16 ) );
		$durum = array(
			'paket'    => $paket,
			'faz'      => 'terimler',
			'imlec'    => 0,
			'harita'   => array( 'terim' => array(), 'yazi' => array(), 'ek' => array(), 'medya' => array() ),
			'uyarilar' => array(),
			'sayac'    => array( 'terim' => 0, 'medya' => 0, 'yeni' => 0, 'guncel' => 0, 'ceviri' => 0, 'ceviri_atlanan' => 0 ),
		);
		if ( ! self::durum_yaz( $token, $durum ) ) {
			wp_send_json_error( array( 'mesaj' => __( 'Durum dosyası yazılamadı.', 'qrms' ) ) );
		}

		wp_send_json_success(
			array(
				'token'  => $token,
				'toplam' => array(
					'terim'    => count( $paket['terimler'] ),
					'medya'    => count( isset( $paket['medya'] ) ? (array) $paket['medya'] : array() ),
					'yazi'     => count( $paket['yazilar'] ),
					'ceviri'   => count( $paket['ceviriler'] ),
					'secenek'  => count( $paket['secenekler'] ),
				),
				'kaynak' => $kaynak['home'],
			)
		);
	}

	/**
	 * Bir adım çalıştırır.
	 *
	 * @return void
	 */
	public static function ajax_adim() {
		self::ajax_yetki();

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$token = isset( $_POST['token'] ) ? sanitize_key( wp_unslash( $_POST['token'] ) ) : '';
		$durum = self::durum_oku( $token );
		if ( ! $durum ) {
			wp_send_json_error( array( 'mesaj' => __( 'İçe aktarma oturumu bulunamadı. Dosyayı yeniden yükleyin.', 'qrms' ) ) );
		}

		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 60 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		}
		wp_defer_term_counting( true );
		wp_suspend_cache_invalidation( true );

		$bitti = false;
		$mesaj = '';

		switch ( $durum['faz'] ) {
			case 'terimler':
				self::faz_terimler( $durum );
				$durum['faz']   = 'medya';
				$durum['imlec'] = 0;
				$mesaj          = __( 'Kategoriler aktarıldı.', 'qrms' );
				break;
			case 'medya':
				$mesaj = self::faz_medya( $durum );
				break;
			case 'yazilar':
				$mesaj = self::faz_yazilar( $durum );
				break;
			case 'ceviriler':
				$mesaj = self::faz_ceviriler( $durum );
				break;
			case 'son':
				$mesaj = self::faz_son( $durum );
				$bitti = true;
				break;
		}

		wp_suspend_cache_invalidation( false );
		wp_defer_term_counting( false );

		if ( $bitti ) {
			$yol = self::durum_yolu( $token );
			if ( $yol && file_exists( $yol ) ) {
				@unlink( $yol ); // phpcs:ignore
			}
		} else {
			self::durum_yaz( $token, $durum );
		}

		wp_send_json_success(
			array(
				'faz'      => $durum['faz'],
				'imlec'    => $durum['imlec'],
				'mesaj'    => $mesaj,
				'bitti'    => $bitti,
				'sayac'    => $durum['sayac'],
				'uyarilar' => $bitti ? $durum['uyarilar'] : array_slice( $durum['uyarilar'], -5 ),
			)
		);
	}

	/**
	 * Kategorileri ve diğer terimleri ekler (üst terim önce).
	 *
	 * @param array $d Durum (referans).
	 * @return void
	 */
	private static function faz_terimler( array &$d ) {
		$kalan = $d['paket']['terimler'];
		$tur   = 0;

		while ( $kalan && $tur < 6 ) {
			$sonraki = array();
			foreach ( $kalan as $t ) {
				$tax = isset( $t['tax'] ) ? (string) $t['tax'] : '';
				if ( ! taxonomy_exists( $tax ) ) {
					$d['uyarilar'][] = sprintf( /* translators: %s: taxonomy */ __( '%s sınıflandırması bu sitede kayıtlı değil, atlandı.', 'qrms' ), $tax );
					continue;
				}
				$ust = (int) ( isset( $t['ust'] ) ? $t['ust'] : 0 );
				if ( $ust > 0 && ! isset( $d['harita']['terim'][ $ust ] ) ) {
					$sonraki[] = $t; // Üst terim henüz eklenmedi.
					continue;
				}

				$mevcut = term_exists( (string) $t['slug'], $tax );
				if ( $mevcut ) {
					$yeni = (int) ( is_array( $mevcut ) ? $mevcut['term_id'] : $mevcut );
				} else {
					$r = wp_insert_term(
						(string) $t['ad'],
						$tax,
						array(
							'slug'        => (string) $t['slug'],
							'description' => isset( $t['aciklama'] ) ? (string) $t['aciklama'] : '',
							'parent'      => $ust > 0 ? $d['harita']['terim'][ $ust ] : 0,
						)
					);
					if ( is_wp_error( $r ) ) {
						$d['uyarilar'][] = sprintf( /* translators: %s: term name */ __( '"%s" eklenemedi.', 'qrms' ), $t['ad'] );
						continue;
					}
					$yeni = (int) $r['term_id'];
				}

				$d['harita']['terim'][ (int) $t['id'] ] = $yeni;
				$d['sayac']['terim']++;
				if ( ! empty( $t['meta'] ) && is_array( $t['meta'] ) ) {
					foreach ( $t['meta'] as $k => $degerler ) {
						if ( self::meta_izinli( $k ) ) {
							delete_term_meta( $yeni, $k );
							foreach ( (array) $degerler as $v ) {
								add_term_meta( $yeni, $k, wp_slash( $v ) );
							}
						}
					}
				}
			}
			$kalan = $sonraki;
			$tur++;
		}
	}

	/**
	 * Görselleri indirir (adım başına birkaç tane).
	 *
	 * @param array $d Durum (referans).
	 * @return string
	 */
	private static function faz_medya( array &$d ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$liste  = isset( $d['paket']['medya'] ) ? array_values( (array) $d['paket']['medya'] ) : array();
		$baslar = microtime( true );
		$yapilan = 0;

		while ( $d['imlec'] < count( $liste ) && $yapilan < self::MEDYA_ADIM && ( microtime( true ) - $baslar ) < self::ADIM_SURE ) {
			$m   = $liste[ $d['imlec'] ];
			$url = isset( $m['url'] ) ? (string) $m['url'] : '';
			$d['imlec']++;
			$yapilan++;

			// Yalnızca paketin kendi kaynak sitesinden, uploads yolundaki görseller indirilir.
			if ( ! self::medya_url_guvenli( $url, $d['paket']['kaynak']['uploads'] ) ) {
				$d['uyarilar'][] = sprintf( /* translators: %s: url */ __( 'Güvenli olmayan görsel adresi atlandı: %s', 'qrms' ), esc_url_raw( $url ) );
				continue;
			}

			$yeni_id = self::medya_bul( $url );

			if ( ! $yeni_id ) {
				$gecici = download_url( $url, 45 );
				if ( is_wp_error( $gecici ) ) {
					$d['uyarilar'][] = sprintf( /* translators: 1: file, 2: reason */ __( '%1$s indirilemedi: %2$s', 'qrms' ), wp_basename( $url ), $gecici->get_error_message() );
					continue;
				}
				$id = media_handle_sideload(
					array( 'name' => wp_basename( $url ), 'tmp_name' => $gecici ),
					0,
					isset( $m['baslik'] ) && '' !== $m['baslik'] ? (string) $m['baslik'] : null
				);
				if ( is_wp_error( $id ) ) {
					@unlink( $gecici ); // phpcs:ignore
					$d['uyarilar'][] = sprintf( /* translators: 1: file, 2: reason */ __( '%1$s eklenemedi: %2$s', 'qrms' ), wp_basename( $url ), $id->get_error_message() );
					continue;
				}
				$yeni_id = (int) $id;
				update_post_meta( $yeni_id, '_qrms_kaynak_url', esc_url_raw( $url ) );
				if ( ! empty( $m['alt'] ) ) {
					update_post_meta( $yeni_id, '_wp_attachment_image_alt', sanitize_text_field( $m['alt'] ) );
				}
			}

			$d['sayac']['medya']++;
			foreach ( isset( $m['ids'] ) ? (array) $m['ids'] : array() as $eski ) {
				$d['harita']['ek'][ (int) $eski ] = $yeni_id;
			}

			$boyutlar = array();
			$meta     = wp_get_attachment_metadata( $yeni_id );
			$klasor   = trailingslashit( dirname( (string) wp_get_attachment_url( $yeni_id ) ) );
			if ( ! empty( $meta['sizes'] ) && is_array( $meta['sizes'] ) ) {
				foreach ( $meta['sizes'] as $s ) {
					if ( ! empty( $s['width'] ) && ! empty( $s['height'] ) && ! empty( $s['file'] ) ) {
						$boyutlar[ (int) $s['width'] . 'x' . (int) $s['height'] ] = $klasor . $s['file'];
					}
				}
			}
			$d['harita']['medya'][ $url ] = array(
				'url'      => (string) wp_get_attachment_url( $yeni_id ),
				'boyutlar' => $boyutlar,
			);
		}

		$mesaj = sprintf( /* translators: 1: done, 2: total */ __( 'Görseller: %1$d / %2$d', 'qrms' ), min( $d['imlec'], count( $liste ) ), count( $liste ) );

		if ( $d['imlec'] >= count( $liste ) ) {
			$d['faz']   = 'yazilar';
			$d['imlec'] = 0;
			// Üst kaydı olmayanlar önce, sonra çocuklar; aynı seviyede kimlik sırası.
			usort(
				$d['paket']['yazilar'],
				function ( $a, $b ) {
					return array( (int) ( $a['ust'] > 0 ), (int) $a['id'] ) <=> array( (int) ( $b['ust'] > 0 ), (int) $b['id'] );
				}
			);
		}

		return $mesaj;
	}

	/**
	 * Çevirileri yazar (kimlikler yeni siteye göre çevrilir).
	 *
	 * @param array $d Durum (referans).
	 * @return string
	 */
	private static function faz_ceviriler( array &$d ) {
		$satirlar = isset( $d['paket']['ceviriler'] ) ? array_values( (array) $d['paket']['ceviriler'] ) : array();
		$baslar   = microtime( true );
		$harita   = array( 'yazi' => $d['harita']['yazi'], 'terim' => $d['harita']['terim'] );

		if ( $satirlar && ! class_exists( 'RMA_Ceviri_Tablo' ) ) {
			$d['uyarilar'][] = __( 'Çeviri modülü bu sitede etkin değil; çeviriler atlandı.', 'qrms' );
			$d['sayac']['ceviri_atlanan'] += count( $satirlar );
			$d['imlec'] = count( $satirlar );
		}

		$adim = 0;
		while ( $d['imlec'] < count( $satirlar ) && $adim < 600 && ( microtime( true ) - $baslar ) < self::ADIM_SURE ) {
			$r = $satirlar[ $d['imlec'] ];
			$d['imlec']++;
			$adim++;

			$yeni_id = self::ceviri_kimlik_cevir( (string) $r['tip'], (int) $r['id'], $harita );
			if ( null === $yeni_id ) {
				$d['sayac']['ceviri_atlanan']++;
				continue;
			}
			if ( RMA_Ceviri_Tablo::upsert( $yeni_id, (string) $r['tip'], (string) $r['alan'], (string) $r['dil'], (string) $r['orijinal'], wp_kses_post( (string) $r['ceviri'] ) ) ) {
				$d['sayac']['ceviri']++;
			}
		}

		$mesaj = sprintf( /* translators: 1: done, 2: total */ __( 'Çeviriler: %1$d / %2$d', 'qrms' ), min( $d['imlec'], count( $satirlar ) ), count( $satirlar ) );

		if ( $d['imlec'] >= count( $satirlar ) ) {
			$d['faz']   = 'son';
			$d['imlec'] = 0;
			if ( function_exists( 'rma_ceviri_onbellek_temizle' ) ) {
				rma_ceviri_onbellek_temizle();
			}
		}

		return $mesaj;
	}

	/**
	 * Daha önce bu kaynaktan aktarılmış görseli bulur (tekrar indirmeyi önler).
	 *
	 * @param string $url Özgün kaynak adres.
	 * @return int
	 */
	private static function medya_bul( $url ) {
		global $wpdb;

		$q = get_posts(
			array(
				'post_type'        => 'attachment',
				'post_status'      => 'inherit',
				'numberposts'      => 1,
				'fields'           => 'ids',
				'suppress_filters' => true,
				'meta_key'         => '_qrms_kaynak_url', // phpcs:ignore WordPress.DB.SlowDBQuery
				'meta_value'       => esc_url_raw( $url ), // phpcs:ignore WordPress.DB.SlowDBQuery
			)
		);
		if ( $q ) {
			return (int) $q[0];
		}

		// Yedek: bu araçtan önce başka yolla (ör. WordPress içe aktarıcısı) aktarılmış görsel,
		// aynı dosya adıyla bulunur. Böylece yarım kalmış bir taşıma yeniden başlatılınca
		// görseller ikiye katlanmaz.
		$ad = wp_basename( (string) wp_parse_url( $url, PHP_URL_PATH ) );
		if ( '' === $ad ) {
			return 0;
		}
		$id = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT m.post_id FROM {$wpdb->postmeta} m INNER JOIN {$wpdb->posts} p ON p.ID = m.post_id
				 WHERE m.meta_key = '_wp_attached_file' AND ( m.meta_value = %s OR m.meta_value LIKE %s ) AND p.post_type = 'attachment'
				 ORDER BY m.post_id ASC LIMIT 1",
				$ad,
				'%/' . $wpdb->esc_like( $ad )
			)
		);
		if ( $id ) {
			update_post_meta( $id, '_qrms_kaynak_url', esc_url_raw( $url ) );
		}

		return $id;
	}

	/**
	 * Yazıları ekler/günceller.
	 *
	 * @param array $d Durum (referans).
	 * @return string
	 */
	private static function faz_yazilar( array &$d ) {
		$yazilar = $d['paket']['yazilar'];
		$kaynak  = $d['paket']['kaynak'];
		$baslar  = microtime( true );
		$yapilan = 0;
		$hedef   = untrailingslashit( home_url() );
		$medya   = $d['harita']['medya'];
		$cevir   = function ( $s ) use ( $kaynak, $hedef, $medya ) {
			return QRMS_Tasima::url_cevir( $s, $kaynak['home'], $hedef, $medya );
		};
		$tipler  = array();
		foreach ( self::gruplar() as $g ) {
			$tipler = array_merge( $tipler, $g['tipler'] );
		}

		while ( $d['imlec'] < count( $yazilar ) && $yapilan < self::YAZI_ADIM && ( microtime( true ) - $baslar ) < self::ADIM_SURE ) {
			$y = $yazilar[ $d['imlec'] ];
			$d['imlec']++;
			$yapilan++;

			$tip   = isset( $y['tip'] ) ? (string) $y['tip'] : '';
			$durum = isset( $y['durum'] ) && in_array( $y['durum'], self::DURUMLAR, true ) ? $y['durum'] : 'draft';
			if ( ! in_array( $tip, $tipler, true ) ) {
				continue;
			}

			$alanlar = array(
				'post_type'    => $tip,
				'post_status'  => $durum,
				'post_title'   => (string) $y['baslik'],
				'post_name'    => (string) $y['slug'],
				'post_content' => $cevir( (string) $y['icerik'] ),
				'post_excerpt' => (string) $y['ozet'],
				'menu_order'   => (int) $y['sira'],
				'post_date'    => (string) $y['tarih'],
				'post_parent'  => ( (int) $y['ust'] > 0 && isset( $d['harita']['yazi'][ (int) $y['ust'] ] ) ) ? $d['harita']['yazi'][ (int) $y['ust'] ] : 0,
				'post_author'  => get_current_user_id(),
			);

			$menu_idler = array();
			if ( 'nav_menu_item' === $tip && ! empty( $y['terimler']['nav_menu'] ) ) {
				foreach ( (array) $y['terimler']['nav_menu'] as $eski_menu ) {
					if ( isset( $d['harita']['terim'][ (int) $eski_menu ] ) ) {
						$menu_idler[] = $d['harita']['terim'][ (int) $eski_menu ];
					}
				}
			}
			$nesne = 0;
			if ( 'nav_menu_item' === $tip ) {
				$tur = isset( $y['meta']['_menu_item_type'][0] ) ? (string) $y['meta']['_menu_item_type'][0] : '';
				$obj = isset( $y['meta']['_menu_item_object_id'][0] ) ? (int) $y['meta']['_menu_item_object_id'][0] : 0;
				if ( 'post_type' === $tur && isset( $d['harita']['yazi'][ $obj ] ) ) {
					$nesne = $d['harita']['yazi'][ $obj ];
				} elseif ( 'taxonomy' === $tur && isset( $d['harita']['terim'][ $obj ] ) ) {
					$nesne = $d['harita']['terim'][ $obj ];
				}
			}
			$mevcut = self::yazi_bul( $tip, (int) $y['id'], $kaynak['home'], (string) $y['slug'], (string) $y['baslik'], $menu_idler, $nesne );
			if ( $mevcut ) {
				$alanlar['ID'] = $mevcut;
				$yeni          = wp_update_post( wp_slash( $alanlar ), true );
				$d['sayac']['guncel']++;
			} else {
				$yeni = wp_insert_post( wp_slash( $alanlar ), true );
				$d['sayac']['yeni']++;
			}
			if ( is_wp_error( $yeni ) || ! $yeni ) {
				$d['uyarilar'][] = sprintf( /* translators: %s: title */ __( '"%s" aktarılamadı.', 'qrms' ), $y['baslik'] );
				continue;
			}
			$yeni = (int) $yeni;
			$d['harita']['yazi'][ (int) $y['id'] ] = $yeni;

			update_post_meta( $yeni, '_qrms_kaynak_id', (int) $y['id'] );
			update_post_meta( $yeni, '_qrms_kaynak_home', $kaynak['home'] );

			foreach ( isset( $y['meta'] ) ? (array) $y['meta'] : array() as $k => $degerler ) {
				if ( ! self::meta_izinli( $k ) ) {
					continue;
				}
				delete_post_meta( $yeni, $k );
				foreach ( (array) $degerler as $v ) {
					if ( in_array( $k, self::EK_META, true ) ) {
						$v = isset( $d['harita']['ek'][ (int) $v ] ) ? $d['harita']['ek'][ (int) $v ] : 0;
						if ( ! $v ) {
							$d['uyarilar'][] = sprintf( /* translators: %s: title */ __( '"%s" için görsel eşleşmedi.', 'qrms' ), $y['baslik'] );
							continue;
						}
					} else {
						$v = self::dizgileri_dolas( $v, $cevir );
					}
					add_post_meta( $yeni, $k, wp_slash( $v ) );
				}
			}

			foreach ( isset( $y['terimler'] ) ? (array) $y['terimler'] : array() as $tax => $idler ) {
				if ( ! taxonomy_exists( $tax ) ) {
					continue;
				}
				$yeni_idler = array();
				foreach ( (array) $idler as $eski ) {
					if ( isset( $d['harita']['terim'][ (int) $eski ] ) ) {
						$yeni_idler[] = $d['harita']['terim'][ (int) $eski ];
					}
				}
				if ( ! is_taxonomy_hierarchical( $tax ) ) {
					// Hiyerarşik olmayanlarda tamsayı ad sayılır; slug ile ver.
					$yeni_idler = array_values(
						array_filter(
							array_map(
								function ( $tid ) use ( $tax ) {
									$t = get_term( $tid, $tax );

									return $t && ! is_wp_error( $t ) ? $t->slug : '';
								},
								$yeni_idler
							)
						)
					);
				}
				wp_set_object_terms( $yeni, $yeni_idler, $tax, false );
			}
		}

		$mesaj = sprintf( /* translators: 1: done, 2: total */ __( 'İçerik: %1$d / %2$d', 'qrms' ), min( $d['imlec'], count( $yazilar ) ), count( $yazilar ) );

		if ( $d['imlec'] >= count( $yazilar ) ) {
			$d['faz']   = 'ceviriler';
			$d['imlec'] = 0;
		}

		return $mesaj;
	}

	/**
	 * Aynı kaynaktan daha önce aktarılmış yazıyı bulur.
	 *
	 * @param string $tip      Yazı tipi.
	 * @param int    $kaynak_id Kaynak kimliği.
	 * @param string $home     Kaynak ana adresi.
	 * @param string $slug     Yazı slug'ı (yedek eşleme için).
	 * @param string $baslik   Yazı başlığı (menü öğesi yedeği için).
	 * @param int[]  $menu_idler Menü öğesinin bu sitedeki menü terim kimlikleri.
	 * @param int    $nesne    Menü öğesinin bu sitedeki bağlı nesne kimliği (0: bilinmiyor).
	 * @return int
	 */
	private static function yazi_bul( $tip, $kaynak_id, $home, $slug = '', $baslik = '', array $menu_idler = array(), $nesne = 0 ) {
		$q = get_posts(
			array(
				'post_type'        => $tip,
				'post_status'      => 'any',
				'numberposts'      => 1,
				'fields'           => 'ids',
				'suppress_filters' => true,
				'meta_query'       => array( // phpcs:ignore WordPress.DB.SlowDBQuery
					'relation' => 'AND',
					array( 'key' => '_qrms_kaynak_id', 'value' => $kaynak_id, 'type' => 'NUMERIC' ),
					array( 'key' => '_qrms_kaynak_home', 'value' => $home ),
				),
			)
		);

		if ( $q ) {
			return (int) $q[0];
		}

		// Yedek: bu araçtan önce başka yolla aktarılmış kayıt, aynı tip + aynı slug ile bulunur
		// (gezinme menüsü öğelerinin slug'ı kimlik olduğundan onlara uygulanmaz).
		if ( 'nav_menu_item' === $tip ) {
			// Menü öğesi: aynı menüde aynı başlık ve aynı tür.
			if ( ! $menu_idler ) {
				return 0;
			}
			$arama = array(
				'post_type'        => 'nav_menu_item',
				'post_status'      => 'any',
				'numberposts'      => 1,
				'fields'           => 'ids',
				'suppress_filters' => true,
				'tax_query'        => array( // phpcs:ignore WordPress.DB.SlowDBQuery
					array( 'taxonomy' => 'nav_menu', 'field' => 'term_id', 'terms' => array_map( 'intval', $menu_idler ) ),
				),
			);
			if ( '' !== $baslik ) {
				$arama['title'] = $baslik;
			} elseif ( $nesne > 0 ) {
				// Sayfa/kategori öğelerinin başlığı boştur; bağlı olduğu nesneyle eşle.
				$arama['meta_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery
					array( 'key' => '_menu_item_object_id', 'value' => $nesne, 'type' => 'NUMERIC' ),
				);
			} else {
				return 0;
			}
			$q = get_posts( $arama );

			return $q ? (int) $q[0] : 0;
		}
		if ( '' === $slug ) {
			return 0;
		}
		$q = get_posts(
			array(
				'post_type'        => $tip,
				'post_status'      => 'any',
				'name'             => $slug,
				'numberposts'      => 1,
				'fields'           => 'ids',
				'suppress_filters' => true,
			)
		);

		return $q ? (int) $q[0] : 0;
	}

	/**
	 * Üst kayıt/menü bağlarını düzeltir, ayarları yazar, önbellekleri temizler.
	 *
	 * @param array $d Durum (referans).
	 * @return string
	 */
	private static function faz_son( array &$d ) {
		global $wpdb;

		$kaynak = $d['paket']['kaynak'];
		$hedef  = untrailingslashit( home_url() );
		$yazi   = $d['harita']['yazi'];
		$terim  = $d['harita']['terim'];
		$medya  = $d['harita']['medya'];

		// Üst kayıtlar ve gezinme menüsü bağları (hedef kayıt sonradan eklenmiş olabilir).
		foreach ( $d['paket']['yazilar'] as $y ) {
			if ( empty( $yazi[ (int) $y['id'] ] ) ) {
				continue;
			}
			$yeni = $yazi[ (int) $y['id'] ];

			if ( (int) $y['ust'] > 0 && isset( $yazi[ (int) $y['ust'] ] ) ) {
				$wpdb->update( $wpdb->posts, array( 'post_parent' => $yazi[ (int) $y['ust'] ] ), array( 'ID' => $yeni ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				clean_post_cache( $yeni );
			}

			if ( 'nav_menu_item' === $y['tip'] ) {
				$m   = isset( $y['meta'] ) ? $y['meta'] : array();
				$ust = isset( $m['_menu_item_menu_item_parent'][0] ) ? (int) $m['_menu_item_menu_item_parent'][0] : 0;
				update_post_meta( $yeni, '_menu_item_menu_item_parent', $ust && isset( $yazi[ $ust ] ) ? $yazi[ $ust ] : 0 );

				$tur = isset( $m['_menu_item_type'][0] ) ? (string) $m['_menu_item_type'][0] : '';
				$obj = isset( $m['_menu_item_object_id'][0] ) ? (int) $m['_menu_item_object_id'][0] : 0;
				if ( 'post_type' === $tur && $obj ) {
					update_post_meta( $yeni, '_menu_item_object_id', isset( $yazi[ $obj ] ) ? $yazi[ $obj ] : 0 );
				} elseif ( 'taxonomy' === $tur && $obj ) {
					update_post_meta( $yeni, '_menu_item_object_id', isset( $terim[ $obj ] ) ? $terim[ $obj ] : 0 );
				}
			}
		}

		// Ayarlar.
		$yazilan = 0;
		$harita  = array( 'ek' => $d['harita']['ek'], 'menu' => $terim, 'yazi' => $yazi );
		$cevir   = function ( $s ) use ( $kaynak, $hedef, $medya ) {
			return QRMS_Tasima::url_cevir( $s, $kaynak['home'], $hedef, $medya );
		};
		foreach ( (array) $d['paket']['secenekler'] as $ad => $deger ) {
			if ( ! self::secenek_izinli( $ad ) ) {
				continue; // Paket elle değiştirilmiş olsa bile izin listesi dışı yazılmaz.
			}
			$deger = self::gizli_ayikla( $deger );
			$deger = self::kimlik_cevir( $deger, $harita );
			$deger = self::dizgileri_dolas( $deger, $cevir );
			update_option( $ad, $deger );
			$yazilan++;
		}
		$d['sayac']['secenek'] = $yazilan;

		// Ön sayfa ve etkin Elementor kiti.
		$ozel = isset( $d['paket']['ozel'] ) ? (array) $d['paket']['ozel'] : array();
		if ( $ozel ) {
			$on = isset( $ozel['page_on_front'] ) ? (int) $ozel['page_on_front'] : 0;
			if ( $on && 'page' === ( isset( $ozel['show_on_front'] ) ? $ozel['show_on_front'] : '' ) && isset( $yazi[ $on ] ) ) {
				update_option( 'show_on_front', 'page' );
				update_option( 'page_on_front', $yazi[ $on ] );
			}
			$kit = isset( $ozel['elementor_active_kit'] ) ? (int) $ozel['elementor_active_kit'] : 0;
			if ( $kit && isset( $yazi[ $kit ] ) ) {
				update_option( 'elementor_active_kit', $yazi[ $kit ] );
			}
		}

		// Önbellekler.
		update_option( 'rma_cache_version', time() );
		if ( class_exists( '\Elementor\Plugin' ) && isset( \Elementor\Plugin::$instance->files_manager ) ) {
			\Elementor\Plugin::$instance->files_manager->clear_cache();
		}
		do_action( 'litespeed_purge_all' );
		flush_rewrite_rules( false );

		return __( 'Taşıma tamamlandı.', 'qrms' );
	}

	/* ------------------------------------------------------------------
	 * Ekran
	 * ---------------------------------------------------------------- */

	/**
	 * Sistem Ayarları › Taşıma sekmesi.
	 *
	 * @return void
	 */
	public static function render_settings_tab() {
		$nonce = wp_create_nonce( self::NONCE );
		?>
		<div class="qrms-settings-genel">
			<div class="qrms-card">
				<h2 class="qrms-card-title"><?php esc_html_e( 'Dışa aktar', 'qrms' ); ?></h2>
				<p class="qrms-muted"><?php esc_html_e( 'Seçtiğiniz bölümleri tek bir dosyaya alır. Lisans anahtarı, API anahtarları ve şifreler dosyaya girmez; yeni sitede bunları ayrıca girmeniz gerekir. Siparişler, yorumlar, analiz kayıtları ve QR masalar taşınmaz.', 'qrms' ); ?></p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="qrms_tasima_disa">
					<?php wp_nonce_field( self::NONCE ); ?>
					<?php self::grup_kutulari(); ?>
					<p><button type="submit" class="button button-primary"><?php esc_html_e( 'Paketi indir', 'qrms' ); ?></button></p>
				</form>
			</div>

			<div class="qrms-card">
				<h2 class="qrms-card-title"><?php esc_html_e( 'İçe aktar', 'qrms' ); ?></h2>
				<p class="qrms-muted"><?php esc_html_e( 'Aynı paketi tekrar yüklerseniz kayıtlar çoğalmaz, mevcut olanlar güncellenir. Görseller kaynak siteden indirilir; kaynak sitenin açık olması gerekir. İşlem adım adım ilerler, sayfayı kapatmayın.', 'qrms' ); ?></p>
				<form id="qrms-tasima-ice" enctype="multipart/form-data">
					<p><input type="file" name="paket" accept=".json,application/json" required></p>
					<?php self::grup_kutulari(); ?>
					<p><button type="submit" class="button button-primary"><?php esc_html_e( 'İçe aktarmayı başlat', 'qrms' ); ?></button></p>
					<p id="qrms-tasima-durum" role="status" aria-live="polite"></p>
					<progress id="qrms-tasima-ilerleme" max="100" value="0" hidden></progress>
					<ul id="qrms-tasima-uyari"></ul>
				</form>
			</div>
		</div>
		<script>
		(function () {
			var form = document.getElementById('qrms-tasima-ice');
			if (!form) { return; }
			var ajax = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
			var nonce = <?php echo wp_json_encode( $nonce ); ?>;
			var durum = document.getElementById('qrms-tasima-durum');
			var bar = document.getElementById('qrms-tasima-ilerleme');
			var uyari = document.getElementById('qrms-tasima-uyari');
			var yazMetin = function (m) { durum.textContent = m; };

			function istek(veri) {
				veri.append('nonce', nonce);
				return fetch(ajax, { method: 'POST', body: veri, credentials: 'same-origin' })
					.then(function (r) { return r.json(); })
					.then(function (j) { if (!j.success) { throw new Error((j.data && j.data.mesaj) || 'Hata'); } return j.data; });
			}

			function adim(token, toplam) {
				var v = new FormData();
				v.append('action', 'qrms_tasima_adim');
				v.append('token', token);
				return istek(v).then(function (s) {
					var yuzde = { terimler: 5, medya: 5, yazilar: 45, ceviriler: 80, son: 95 }[s.faz] || 0;
					if (s.faz === 'medya' && toplam.medya) { yuzde = 5 + Math.round(40 * s.imlec / toplam.medya); }
					if (s.faz === 'yazilar' && toplam.yazi) { yuzde = 45 + Math.round(35 * s.imlec / toplam.yazi); }
					if (s.faz === 'ceviriler' && toplam.ceviri) { yuzde = 80 + Math.round(15 * s.imlec / toplam.ceviri); }
					bar.value = s.bitti ? 100 : yuzde;
					yazMetin(s.mesaj);
					uyari.innerHTML = '';
					(s.uyarilar || []).forEach(function (u) { var li = document.createElement('li'); li.textContent = u; uyari.appendChild(li); });
					return s.bitti ? s : adim(token, toplam);
				});
			}

			form.addEventListener('submit', function (e) {
				e.preventDefault();
				var btn = form.querySelector('button[type=submit]');
				btn.disabled = true;
				bar.hidden = false;
				bar.value = 0;
				yazMetin(<?php echo wp_json_encode( __( 'Paket okunuyor…', 'qrms' ) ); ?>);
				var v = new FormData(form);
				v.append('action', 'qrms_tasima_hazirla');
				istek(v)
					.then(function (h) { return adim(h.token, h.toplam); })
					.then(function (s) {
						var c = s.sayac || {};
						yazMetin(<?php echo wp_json_encode( __( 'Tamamlandı. Yeni:', 'qrms' ) ); ?> + ' ' + (c.yeni || 0) + ', ' + <?php echo wp_json_encode( __( 'güncellenen:', 'qrms' ) ); ?> + ' ' + (c.guncel || 0) + ', ' + <?php echo wp_json_encode( __( 'görsel:', 'qrms' ) ); ?> + ' ' + (c.medya || 0) + ', ' + <?php echo wp_json_encode( __( 'çeviri:', 'qrms' ) ); ?> + ' ' + (c.ceviri || 0) + ', ' + <?php echo wp_json_encode( __( 'ayar:', 'qrms' ) ); ?> + ' ' + (c.secenek || 0));
					})
					.catch(function (err) { yazMetin(err.message); })
					.then(function () { btn.disabled = false; });
			});
		})();
		</script>
		<?php
	}

	/**
	 * Grup onay kutuları.
	 *
	 * @return void
	 */
	private static function grup_kutulari() {
		foreach ( self::gruplar() as $ad => $g ) {
			printf(
				'<p><label><input type="checkbox" name="gruplar[]" value="%1$s" checked> %2$s</label></p>',
				esc_attr( $ad ),
				esc_html( $g['etiket'] )
			);
		}
	}
}
