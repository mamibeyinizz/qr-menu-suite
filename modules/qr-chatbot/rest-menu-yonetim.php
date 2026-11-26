<?php
/**
 * REST: menü yönetimi (müdür / admin) — fiyat, yeni ürün, ürün güncelleme.
 *
 *   POST /wp-json/qrservis/v1/menu-price        {idToken, itemId, fiyat}
 *   POST /wp-json/qrservis/v1/menu-item         {idToken, ad, fiyat, kategoriId, aciklama?,
 *                                                gorselBase64?, aktif?, istasyon?}
 *   POST /wp-json/qrservis/v1/menu-item-update  {idToken, itemId, alanlar:{ad?, fiyat?, aciklama?,
 *                                                aktif?, tukendi?, kategoriId?, istasyon?}}
 *
 * Yetki: müdür kendi şubesinde, admin her şubede (qmo_staff_dogrula). Garson
 * YALNIZCA `tukendi` alanını değiştirebilir. Ürün silinmez; yalnızca pasife alınır.
 *
 * @package QR_Menu_Official
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'rest_api_init', 'qmo_rest_menu_yonetim_kaydet' );

if ( ! function_exists( 'qmo_rest_menu_yonetim_kaydet' ) ) {
	/**
	 * Rotaları kaydet.
	 *
	 * @return void
	 */
	function qmo_rest_menu_yonetim_kaydet() {
		foreach ( array(
			'/menu-price'       => 'qmo_rest_menu_price',
			'/menu-item'        => 'qmo_rest_menu_item',
			'/menu-item-update' => 'qmo_rest_menu_item_update',
		) as $yol => $cb ) {
			register_rest_route(
				'qrservis/v1',
				$yol,
				array(
					'methods'             => 'POST',
					'callback'            => $cb,
					// Yetki içeride: Firebase ID token + rol + şube kontrolü.
					'permission_callback' => '__return_true',
				)
			);
		}
	}
}

if ( ! function_exists( 'qmo_menu_fiyat_temizle' ) ) {
	/**
	 * Fiyatı doğrular (RMA ile aynı kural: en fazla 2 ondalık, 0'dan büyük, ≤ 999999.99).
	 *
	 * @param mixed $deger Ham değer.
	 * @return string|null Biçimli fiyat ya da geçersizse null.
	 */
	function qmo_menu_fiyat_temizle( $deger ) {
		if ( is_int( $deger ) || is_float( $deger ) ) {
			$deger = rtrim( rtrim( number_format( (float) $deger, 2, '.', '' ), '0' ), '.' );
		}
		$deger = trim( (string) $deger );
		if ( ! preg_match( '/^\d+(\.\d{1,2})?$/', $deger ) ) {
			return null;
		}
		$sayi = (float) $deger;
		if ( $sayi <= 0 || $sayi > 999999.99 ) {
			return null;
		}
		return $deger;
	}
}

if ( ! function_exists( 'qmo_menu_degisti' ) ) {
	/**
	 * Menü değişince önbellekleri geçersiz kıl: müşteri menüsü (RMA sürüm damgası),
	 * personel menü önbelleği.
	 *
	 * @return void
	 */
	function qmo_menu_degisti() {
		update_option( 'rma_cache_version', (string) time() . '-' . wp_rand( 100, 999 ) );
		delete_transient( 'qmo_staff_menu_v1' );
		/**
		 * Üçüncü taraf sayfa önbelleklerini temizlemek için kanca.
		 */
		do_action( 'qmo_menu_degisti' );
	}
}

if ( ! function_exists( 'qmo_menu_urun_bul' ) ) {
	/**
	 * itemId → yayınlanmış ya da pasif ürün; değilse hata cevabı.
	 *
	 * @param mixed $item_id Ham ID.
	 * @return int|WP_REST_Response
	 */
	function qmo_menu_urun_bul( $item_id ) {
		$id = absint( $item_id );
		if ( $id < 1 || 'rma_menu_item' !== get_post_type( $id ) || 'publish' !== get_post_status( $id ) ) {
			return qmo_staff_hata( 'Ürün bulunamadı', 404 );
		}
		return $id;
	}
}

if ( ! function_exists( 'qmo_menu_baslik_kullanimda' ) ) {
	/**
	 * Aynı başlıklı başka yayınlanmış ürün var mı? (Sipariş adla da çözülebildiği
	 * için çift ad belirsizlik yaratır.)
	 *
	 * @param string $ad       Başlık.
	 * @param int    $haric_id Hariç tutulacak ürün.
	 * @return bool
	 */
	function qmo_menu_baslik_kullanimda( $ad, $haric_id = 0 ) {
		$sorgu = new WP_Query(
			array(
				'post_type'              => 'rma_menu_item',
				'post_status'            => 'publish',
				'title'                  => $ad,
				'posts_per_page'         => 2,
				'fields'                 => 'ids',
				'post__not_in'           => $haric_id ? array( (int) $haric_id ) : array(),
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);
		return ! empty( $sorgu->posts );
	}
}

if ( ! function_exists( 'qmo_menu_kategori_gecerli' ) ) {
	/**
	 * Kategori mevcut mu?
	 *
	 * @param mixed $kategori_id Ham ID.
	 * @return int 0 = geçersiz.
	 */
	function qmo_menu_kategori_gecerli( $kategori_id ) {
		$id = absint( $kategori_id );
		return ( $id > 0 && term_exists( $id, 'rma_category' ) ) ? $id : 0;
	}
}

if ( ! function_exists( 'qmo_menu_gorsel_ekle' ) ) {
	/**
	 * Base64 görseli medya kitaplığına ekler ve öne çıkan görsel yapar.
	 *
	 * @param int    $post_id Ürün.
	 * @param string $b64     Base64 (data: öneki olabilir).
	 * @return bool
	 */
	function qmo_menu_gorsel_ekle( $post_id, $b64 ) {
		$b64 = preg_replace( '#^data:image/[a-z+]+;base64,#i', '', trim( (string) $b64 ) );
		// Base64 ~%33 şişer: 4 MB ham ≈ 5.6 MB metin.
		if ( '' === $b64 || strlen( $b64 ) > 6000000 ) {
			return false;
		}
		$ham = base64_decode( $b64, true );
		if ( false === $ham || strlen( $ham ) > 4194304 ) {
			return false;
		}
		$bilgi = @getimagesizefromstring( $ham ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		$tip   = is_array( $bilgi ) && ! empty( $bilgi['mime'] ) ? $bilgi['mime'] : '';
		$uzant = array(
			'image/jpeg' => 'jpg',
			'image/png'  => 'png',
			'image/webp' => 'webp',
		);
		if ( ! isset( $uzant[ $tip ] ) || $bilgi[0] > 6000 || $bilgi[1] > 6000 ) {
			return false;
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$tmp = wp_tempnam( 'urun.' . $uzant[ $tip ] );
		if ( ! $tmp || false === file_put_contents( $tmp, $ham ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			return false;
		}

		$dosya = array(
			'name'     => 'urun-' . $post_id . '.' . $uzant[ $tip ],
			'tmp_name' => $tmp,
		);
		$att   = media_handle_sideload( $dosya, $post_id );
		if ( is_wp_error( $att ) ) {
			@unlink( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged,WordPress.WP.AlternativeFunctions.unlink_unlink
			return false;
		}

		set_post_thumbnail( $post_id, $att );
		return true;
	}
}

if ( ! function_exists( 'qmo_rest_menu_price' ) ) {
	/**
	 * Fiyat güncelle.
	 *
	 * @param WP_REST_Request $req İstek.
	 * @return WP_REST_Response
	 */
	function qmo_rest_menu_price( WP_REST_Request $req ) {
		$k = qmo_staff_dogrula( $req, array( 'mudur' ), 300 );
		if ( $k instanceof WP_REST_Response ) {
			return $k;
		}

		$id = qmo_menu_urun_bul( $req->get_param( 'itemId' ) );
		if ( $id instanceof WP_REST_Response ) {
			return $id;
		}

		$fiyat = qmo_menu_fiyat_temizle( $req->get_param( 'fiyat' ) );
		if ( null === $fiyat ) {
			return qmo_staff_hata( 'Geçersiz fiyat (0\'dan büyük, en fazla 2 ondalık).', 400 );
		}

		$eski = (string) get_post_meta( $id, 'rma_price', true );
		update_post_meta( $id, 'rma_price', $fiyat );
		qmo_menu_degisti();

		return new WP_REST_Response(
			array(
				'success'  => true,
				'itemId'   => $id,
				'eskiFiyat' => '' === $eski ? null : (float) $eski,
				'fiyat'    => (float) $fiyat,
			),
			200
		);
	}
}

if ( ! function_exists( 'qmo_rest_menu_item' ) ) {
	/**
	 * Yeni ürün.
	 *
	 * @param WP_REST_Request $req İstek.
	 * @return WP_REST_Response
	 */
	function qmo_rest_menu_item( WP_REST_Request $req ) {
		$k = qmo_staff_dogrula( $req, array( 'mudur' ), 200 );
		if ( $k instanceof WP_REST_Response ) {
			return $k;
		}
		if ( ! post_type_exists( 'rma_menu_item' ) ) {
			return qmo_staff_hata( 'Menü modülü bu sitede etkin değil.', 404 );
		}

		$ad = trim( sanitize_text_field( (string) $req->get_param( 'ad' ) ) );
		if ( '' === $ad || mb_strlen( $ad ) > 120 ) {
			return qmo_staff_hata( 'Ürün adı gerekli (en fazla 120 karakter).', 400 );
		}
		if ( qmo_menu_baslik_kullanimda( $ad ) ) {
			return qmo_staff_hata( 'Bu adla bir ürün zaten var.', 409 );
		}

		$fiyat = qmo_menu_fiyat_temizle( $req->get_param( 'fiyat' ) );
		if ( null === $fiyat ) {
			return qmo_staff_hata( 'Geçersiz fiyat (0\'dan büyük, en fazla 2 ondalık).', 400 );
		}

		$kategori = qmo_menu_kategori_gecerli( $req->get_param( 'kategoriId' ) );
		if ( $kategori < 1 ) {
			return qmo_staff_hata( 'Geçersiz kategori.', 400 );
		}

		$aciklama = mb_substr( sanitize_textarea_field( (string) $req->get_param( 'aciklama' ) ), 0, 500 );
		$aktif    = null === $req->get_param( 'aktif' ) ? true : (bool) $req->get_param( 'aktif' );

		$id = wp_insert_post(
			array(
				'post_type'    => 'rma_menu_item',
				'post_status'  => 'publish',
				'post_title'   => $ad,
				'post_excerpt' => $aciklama,
			),
			true
		);
		if ( is_wp_error( $id ) || ! $id ) {
			return qmo_staff_hata( 'Ürün oluşturulamadı.', 500 );
		}

		update_post_meta( $id, 'rma_price', $fiyat );
		update_post_meta( $id, 'rma_active', $aktif ? '1' : '0' );
		wp_set_object_terms( $id, array( $kategori ), 'rma_category' );

		$ist = class_exists( 'RMA_Istasyon' ) ? RMA_Istasyon::temizle( $req->get_param( 'istasyon' ) ) : '';
		if ( '' !== $ist ) {
			update_post_meta( $id, RMA_Istasyon::META, $ist );
		}

		$gorsel_hata = false;
		$b64         = $req->get_param( 'gorselBase64' );
		if ( is_string( $b64 ) && '' !== $b64 ) {
			$gorsel_hata = ! qmo_menu_gorsel_ekle( $id, $b64 );
		}

		qmo_menu_degisti();

		return new WP_REST_Response(
			array(
				'success'    => true,
				'itemId'     => (int) $id,
				'gorselHata' => $gorsel_hata,
			),
			200
		);
	}
}

if ( ! function_exists( 'qmo_rest_menu_item_update' ) ) {
	/**
	 * Ürün güncelle (kısmi).
	 *
	 * @param WP_REST_Request $req İstek.
	 * @return WP_REST_Response
	 */
	function qmo_rest_menu_item_update( WP_REST_Request $req ) {
		$k = qmo_staff_dogrula( $req, array( 'mudur', 'garson' ), 600 );
		if ( $k instanceof WP_REST_Response ) {
			return $k;
		}

		$id = qmo_menu_urun_bul( $req->get_param( 'itemId' ) );
		if ( $id instanceof WP_REST_Response ) {
			return $id;
		}

		$alanlar = $req->get_param( 'alanlar' );
		if ( ! is_array( $alanlar ) || empty( $alanlar ) ) {
			return qmo_staff_hata( 'Güncellenecek alan yok.', 400 );
		}

		// Garson yalnızca "tükendi" değiştirebilir.
		$yonetici = in_array( $k['rol'], array( 'mudur', 'admin' ), true );
		if ( ! $yonetici && array_keys( $alanlar ) !== array( 'tukendi' ) ) {
			return qmo_staff_hata( 'Bu işlem için yetkiniz yok', 403 );
		}

		$izinli = array( 'ad', 'fiyat', 'aciklama', 'aktif', 'tukendi', 'kategoriId', 'istasyon' );
		foreach ( array_keys( $alanlar ) as $anahtar ) {
			if ( ! in_array( $anahtar, $izinli, true ) ) {
				return qmo_staff_hata( 'Geçersiz alan: ' . sanitize_key( (string) $anahtar ), 400 );
			}
		}

		// Önce hepsini doğrula, sonra yaz (kısmi yazım bırakma).
		$yaz = array();

		if ( array_key_exists( 'ad', $alanlar ) ) {
			$ad = trim( sanitize_text_field( (string) $alanlar['ad'] ) );
			if ( '' === $ad || mb_strlen( $ad ) > 120 ) {
				return qmo_staff_hata( 'Ürün adı gerekli (en fazla 120 karakter).', 400 );
			}
			if ( qmo_menu_baslik_kullanimda( $ad, $id ) ) {
				return qmo_staff_hata( 'Bu adla bir ürün zaten var.', 409 );
			}
			$yaz['ad'] = $ad;
		}
		if ( array_key_exists( 'fiyat', $alanlar ) ) {
			$fiyat = qmo_menu_fiyat_temizle( $alanlar['fiyat'] );
			if ( null === $fiyat ) {
				return qmo_staff_hata( 'Geçersiz fiyat (0\'dan büyük, en fazla 2 ondalık).', 400 );
			}
			$yaz['fiyat'] = $fiyat;
		}
		if ( array_key_exists( 'kategoriId', $alanlar ) ) {
			$kat = qmo_menu_kategori_gecerli( $alanlar['kategoriId'] );
			if ( $kat < 1 ) {
				return qmo_staff_hata( 'Geçersiz kategori.', 400 );
			}
			$yaz['kategoriId'] = $kat;
		}
		if ( array_key_exists( 'istasyon', $alanlar ) ) {
			$yaz['istasyon'] = class_exists( 'RMA_Istasyon' ) ? RMA_Istasyon::temizle( $alanlar['istasyon'] ) : '';
		}

		if ( isset( $yaz['ad'] ) ) {
			wp_update_post(
				array(
					'ID'         => $id,
					'post_title' => $yaz['ad'],
				)
			);
		}
		if ( array_key_exists( 'aciklama', $alanlar ) ) {
			wp_update_post(
				array(
					'ID'           => $id,
					'post_excerpt' => mb_substr( sanitize_textarea_field( (string) $alanlar['aciklama'] ), 0, 500 ),
				)
			);
		}
		if ( isset( $yaz['fiyat'] ) ) {
			update_post_meta( $id, 'rma_price', $yaz['fiyat'] );
		}
		if ( isset( $yaz['kategoriId'] ) ) {
			wp_set_object_terms( $id, array( $yaz['kategoriId'] ), 'rma_category' );
		}
		if ( array_key_exists( 'istasyon', $yaz ) ) {
			if ( '' === $yaz['istasyon'] ) {
				delete_post_meta( $id, RMA_Istasyon::META );
			} else {
				update_post_meta( $id, RMA_Istasyon::META, $yaz['istasyon'] );
			}
		}
		if ( array_key_exists( 'aktif', $alanlar ) ) {
			update_post_meta( $id, 'rma_active', $alanlar['aktif'] ? '1' : '0' );
		}
		if ( array_key_exists( 'tukendi', $alanlar ) && class_exists( 'RMA_Tukendi' ) ) {
			RMA_Tukendi::kaydet( $id, (bool) $alanlar['tukendi'] );
		}

		qmo_menu_degisti();

		return new WP_REST_Response(
			array(
				'success' => true,
				'itemId'  => $id,
			),
			200
		);
	}
}
