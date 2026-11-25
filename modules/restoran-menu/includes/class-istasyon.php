<?php
/**
 * Hazırlık istasyonu (mutfak / bar) eşlemesi.
 *
 * Sipariş kalemi hangi istasyona düşecek? Karar sırası:
 *   1) Ürün override'ı (`_rma_istasyon`: mutfak | bar | yok)
 *   2) Ürünün kategorilerinden ilk dolu `rma_istasyon` term meta'sı
 *   3) Hiçbiri yoksa 'yok' — kalem istasyona gitmez, garson görür ve uyarılır.
 *
 * Karar mantığı saftır (`coz`) ve WordPress'e bağımlı değildir; test edilebilir.
 *
 * @package QR_Menu_Suite
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'RMA_Istasyon' ) ) :

class RMA_Istasyon {

	/** Ürün post meta anahtarı (alt çizgili: özel alan listesinde görünmez). */
	const META = '_rma_istasyon';

	/** Kategori term meta anahtarı. */
	const TERIM_META = 'rma_istasyon';

	/** Ürün meta kutusunun nonce'ı (Ürün Detayları'ndan AYRI). */
	const NONCE = 'rma_istasyon_nonce';

	/**
	 * Geçerli istasyon değerleri.
	 *
	 * @return string[]
	 */
	public static function degerler() {
		return array( 'mutfak', 'bar', 'yok' );
	}

	/**
	 * Ham değeri geçerli bir istasyona ya da boş dizeye indirger.
	 *
	 * @param mixed $deger Ham değer.
	 * @return string mutfak | bar | yok | '' (belirtilmemiş)
	 */
	public static function temizle( $deger ) {
		$deger = is_string( $deger ) ? strtolower( trim( $deger ) ) : '';

		return in_array( $deger, self::degerler(), true ) ? $deger : '';
	}

	/**
	 * Saf karar: override > kategori > yok.
	 *
	 * @param mixed $override  Ürün override'ı.
	 * @param array $kategoriler Kategori term meta değerleri (sırayla).
	 * @return string mutfak | bar | yok
	 */
	public static function coz( $override, array $kategoriler ) {
		$o = self::temizle( $override );
		if ( '' !== $o ) {
			return $o;
		}

		foreach ( $kategoriler as $k ) {
			$k = self::temizle( $k );
			if ( '' !== $k ) {
				return $k;
			}
		}

		return 'yok';
	}

	/**
	 * Ürünün istasyonu.
	 *
	 * @param int $item_id Ürün ID.
	 * @return string mutfak | bar | yok
	 */
	public static function urun_istasyonu( $item_id ) {
		$item_id = absint( $item_id );
		if ( $item_id < 1 ) {
			return 'yok';
		}

		$kategoriler = array();
		$terimler    = get_the_terms( $item_id, 'rma_category' );

		if ( is_array( $terimler ) ) {
			foreach ( $terimler as $terim ) {
				$kategoriler[] = get_term_meta( $terim->term_id, self::TERIM_META, true );
			}
		}

		return self::coz( get_post_meta( $item_id, self::META, true ), $kategoriler );
	}

	/* =============================================================
	   YÖNETİM ALANLARI
	============================================================= */

	/**
	 * Kancaları kaydeder.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'rma_category_add_form_fields', array( __CLASS__, 'kategori_ekle_alani' ) );
		add_action( 'rma_category_edit_form_fields', array( __CLASS__, 'kategori_duzenle_alani' ) );
		add_action( 'created_rma_category', array( __CLASS__, 'kategori_kaydet' ) );
		add_action( 'edited_rma_category', array( __CLASS__, 'kategori_kaydet' ) );
		add_action( 'add_meta_boxes', array( __CLASS__, 'meta_kutusu_ekle' ) );
		add_action( 'save_post_rma_menu_item', array( __CLASS__, 'urun_kaydet' ) );
	}

	/**
	 * Seçenek listesi (select) HTML'i.
	 *
	 * @param string $secili Seçili değer.
	 * @param string $bos    "Belirtilmemiş" seçeneğinin metni.
	 * @param string $name   Alan adı.
	 * @return void
	 */
	private static function secim( $secili, $bos, $name ) {
		$etiket = array(
			''       => $bos,
			'mutfak' => __( 'Mutfak', 'qrms' ),
			'bar'    => __( 'Bar', 'qrms' ),
			'yok'    => __( 'Hiçbiri (garson taşır)', 'qrms' ),
		);

		echo '<select name="' . esc_attr( $name ) . '" id="' . esc_attr( $name ) . '">';
		foreach ( $etiket as $deger => $metin ) {
			printf(
				'<option value="%1$s"%2$s>%3$s</option>',
				esc_attr( $deger ),
				selected( $secili, $deger, false ),
				esc_html( $metin )
			);
		}
		echo '</select>';
	}

	/**
	 * Kategori "ekle" formu alanı.
	 *
	 * @return void
	 */
	public static function kategori_ekle_alani() {
		wp_nonce_field( self::NONCE, self::NONCE );
		echo '<div class="form-field"><label for="rma_cat_istasyon">' . esc_html__( 'Hazırlık istasyonu', 'qrms' ) . '</label>';
		self::secim( '', __( 'Belirtilmemiş', 'qrms' ), 'rma_cat_istasyon' );
		echo '<p>' . esc_html__( 'Bu kategorideki ürünler mutfak ya da bar ekranına düşer (Mutfak/Bar özelliği şubede açıksa).', 'qrms' ) . '</p></div>';
	}

	/**
	 * Kategori "düzenle" formu alanı.
	 *
	 * @param WP_Term $term Kategori.
	 * @return void
	 */
	public static function kategori_duzenle_alani( $term ) {
		$secili = self::temizle( get_term_meta( $term->term_id, self::TERIM_META, true ) );

		wp_nonce_field( self::NONCE, self::NONCE );
		echo '<tr class="form-field"><th><label for="rma_cat_istasyon">' . esc_html__( 'Hazırlık istasyonu', 'qrms' ) . '</label></th><td>';
		self::secim( $secili, __( 'Belirtilmemiş', 'qrms' ), 'rma_cat_istasyon' );
		echo '<p class="description">' . esc_html__( 'Bu kategorideki ürünler mutfak ya da bar ekranına düşer. Ürün bazında değiştirilebilir.', 'qrms' ) . '</p></td></tr>';
	}

	/**
	 * Kategori kaydı.
	 *
	 * @param int $term_id Kategori ID.
	 * @return void
	 */
	public static function kategori_kaydet( $term_id ) {
		// Alanı basmayan formlardan (hızlı düzenleme) gelen kayıtlarda dokunma.
		if ( ! isset( $_POST[ self::NONCE ], $_POST['rma_cat_istasyon'] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE ] ) ), self::NONCE ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_categories' ) ) {
			return;
		}

		$deger = self::temizle( wp_unslash( $_POST['rma_cat_istasyon'] ) );

		if ( '' === $deger ) {
			delete_term_meta( $term_id, self::TERIM_META );
		} else {
			update_term_meta( $term_id, self::TERIM_META, $deger );
		}
	}

	/**
	 * Ürün ekranına küçük meta kutusu.
	 *
	 * @return void
	 */
	public static function meta_kutusu_ekle() {
		add_meta_box(
			'rma_istasyon_kutusu',
			__( 'Hazırlık istasyonu', 'qrms' ),
			array( __CLASS__, 'meta_kutusu' ),
			'rma_menu_item',
			'side',
			'default'
		);
	}

	/**
	 * Meta kutusu içeriği.
	 *
	 * @param WP_Post $post Ürün.
	 * @return void
	 */
	public static function meta_kutusu( $post ) {
		wp_nonce_field( self::NONCE, self::NONCE );
		self::secim(
			self::temizle( get_post_meta( $post->ID, self::META, true ) ),
			__( 'Kategoriden devral', 'qrms' ),
			'rma_istasyon'
		);
	}

	/**
	 * Ürün kaydı.
	 *
	 * @param int $post_id Ürün ID.
	 * @return void
	 */
	public static function urun_kaydet( $post_id ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! isset( $_POST[ self::NONCE ], $_POST['rma_istasyon'] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE ] ) ), self::NONCE ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$deger = self::temizle( wp_unslash( $_POST['rma_istasyon'] ) );

		if ( '' === $deger ) {
			delete_post_meta( $post_id, self::META );
		} else {
			update_post_meta( $post_id, self::META, $deger );
		}
	}
}

endif;
