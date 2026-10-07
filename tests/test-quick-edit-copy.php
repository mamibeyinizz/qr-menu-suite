<?php
/**
 * Hızlı Düzenle ve ürün çoğaltma (1-2-12).
 *
 * Yükleyen: tests/test-suite.php — doğrudan çalıştırmayın.
 *
 * @package QR_Menu_Suite
 */

require_once QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/class-tukendi.php';
require_once QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/trait-helpers.php';
require_once QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/trait-post-types.php';
require_once QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/trait-admin-columns.php';

if ( ! function_exists( 'wp_is_post_revision' ) ) {
	function wp_is_post_revision( $post_id ) {
		unset( $post_id );
		return false;
	}
}

if ( ! function_exists( 'wp_insert_post' ) ) {
	function wp_insert_post( $arr, $wp_error = false ) {
		if ( ! empty( $GLOBALS['qrms_test']['wp_insert_post_error'] ) ) {
			$err = new WP_Error( 'db_insert', 'insert failed' );
			return $wp_error ? $err : 0;
		}

		$id = isset( $GLOBALS['qrms_test']['next_post_id'] ) ? (int) $GLOBALS['qrms_test']['next_post_id'] : 9000;
		$GLOBALS['qrms_test']['next_post_id'] = $id + 1;

		$obj               = (object) $arr;
		$obj->ID           = $id;
		$obj->post_type    = isset( $arr['post_type'] ) ? $arr['post_type'] : 'rma_menu_item';
		$obj->post_status  = isset( $arr['post_status'] ) ? $arr['post_status'] : 'publish';
		$obj->post_title   = isset( $arr['post_title'] ) ? $arr['post_title'] : '';
		$obj->post_content = isset( $arr['post_content'] ) ? $arr['post_content'] : '';
		$obj->post_excerpt = isset( $arr['post_excerpt'] ) ? $arr['post_excerpt'] : '';

		$GLOBALS['qrms_test']['posts_by_id'][ $id ] = $obj;

		return $id;
	}
}

if ( ! function_exists( 'get_post_custom' ) ) {
	function get_post_custom( $post_id ) {
		$meta = isset( $GLOBALS['qrms_test']['post_meta'][ $post_id ] )
			? $GLOBALS['qrms_test']['post_meta'][ $post_id ]
			: array();
		$out  = array();
		foreach ( $meta as $key => $value ) {
			$out[ $key ] = array( is_array( $value ) || is_object( $value ) ? serialize( $value ) : (string) $value );
		}
		return $out;
	}
}

if ( ! function_exists( 'add_post_meta' ) ) {
	function add_post_meta( $post_id, $key, $value, $unique = false ) {
		if ( $unique && isset( $GLOBALS['qrms_test']['post_meta'][ $post_id ][ $key ] ) ) {
			return false;
		}
		$GLOBALS['qrms_test']['post_meta'][ $post_id ][ $key ] = $value;
		return true;
	}
}

if ( ! function_exists( 'get_post_thumbnail_id' ) ) {
	function get_post_thumbnail_id( $post_id ) {
		return (int) get_post_meta( $post_id, '_thumbnail_id', true );
	}
}

if ( ! function_exists( 'set_post_thumbnail' ) ) {
	function set_post_thumbnail( $post_id, $thumb_id ) {
		update_post_meta( $post_id, '_thumbnail_id', (int) $thumb_id );
		return true;
	}
}

if ( ! function_exists( 'delete_post_thumbnail' ) ) {
	function delete_post_thumbnail( $post_id ) {
		delete_post_meta( $post_id, '_thumbnail_id' );
		return true;
	}
}

if ( ! function_exists( 'get_object_taxonomies' ) ) {
	function get_object_taxonomies( $type ) {
		unset( $type );
		return array( 'rma_category', 'rma_allergen', 'rma_ingredient' );
	}
}

if ( ! function_exists( 'wp_get_object_terms' ) ) {
	function wp_get_object_terms( $object_id, $taxonomy, $args = array() ) {
		$terms = isset( $GLOBALS['qrms_test']['object_terms'][ $object_id ][ $taxonomy ] )
			? $GLOBALS['qrms_test']['object_terms'][ $object_id ][ $taxonomy ]
			: array();
		unset( $args );
		return array_values( (array) $terms );
	}
}

if ( ! class_exists( 'RMA_Test_Qe_Harness' ) ) {
	class RMA_Test_Qe_Harness {
		use RMA_Helpers_Trait;
		use RMA_Post_Types_Trait;
		use RMA_Admin_Columns_Trait;
	}
}

/**
 * Kaynak ürün nesnesi.
 *
 * @param int    $id    ID.
 * @param string $title Başlık.
 * @return object
 */
function qrms_qe_urun( $id, $title = 'Lahmacun' ) {
	return (object) array(
		'ID'           => $id,
		'post_type'    => 'rma_menu_item',
		'post_title'   => $title,
		'post_content' => 'Açıklama A',
		'post_excerpt' => 'Kısa A',
		'post_status'  => 'publish',
	);
}

echo "\nHızlı Düzenle\n";

qrms_test(
	'nonce yoksa veya geçersizse görsel ve alerjen değişmez',
	function () {
		$h = new RMA_Test_Qe_Harness();
		update_post_meta( 10, '_thumbnail_id', 55 );
		$GLOBALS['qrms_test']['object_terms'][10]['rma_allergen'] = array( 3, 4 );
		$GLOBALS['qrms_test']['post_types'][55]                   = 'attachment';

		$_POST = array(
			'rma_qe_thumbnail_id' => '0',
			'rma_qe_allergens'    => array( 0 ),
		);
		$h->save_quick_edit_fields( 10 );
		qrms_assert_same( 55, (int) get_post_meta( 10, '_thumbnail_id', true ), 'nonce yok: görsel durur' );
		qrms_assert_same( array( 3, 4 ), $GLOBALS['qrms_test']['object_terms'][10]['rma_allergen'], 'nonce yok: alerjen durur' );

		$_POST['rma_qe_nonce'] = 'sahte';
		$h->save_quick_edit_fields( 10 );
		qrms_assert_same( 55, (int) get_post_meta( 10, '_thumbnail_id', true ), 'sahte nonce: görsel durur' );
	}
);

qrms_test(
	'yetkisiz kullanıcı hızlı düzenleme yazamaz',
	function () {
		$h = new RMA_Test_Qe_Harness();
		update_post_meta( 11, '_thumbnail_id', 55 );
		$GLOBALS['qrms_test']['post_types'][55]     = 'attachment';
		$GLOBALS['qrms_test']['can_edit_post'][11]  = false;

		$_POST = array(
			'rma_qe_nonce'        => wp_create_nonce( 'rma_quick_edit' ),
			'rma_qe_thumbnail_id' => '0',
			'rma_qe_allergens'    => array( 9 ),
		);
		$h->save_quick_edit_fields( 11 );
		qrms_assert_same( 55, (int) get_post_meta( 11, '_thumbnail_id', true ), 'yetkisiz: görsel durur' );
	}
);

qrms_test(
	'boş thumbnail alanı mevcut görseli silmez; 0 kaldırır; attachment olmayan ID yazılmaz',
	function () {
		$h = new RMA_Test_Qe_Harness();
		update_post_meta( 12, '_thumbnail_id', 55 );
		$GLOBALS['qrms_test']['post_types'][55] = 'attachment';
		$GLOBALS['qrms_test']['post_types'][77] = 'rma_menu_item';

		$_POST = array(
			'rma_qe_nonce'        => wp_create_nonce( 'rma_quick_edit' ),
			'rma_qe_thumbnail_id' => '',
			'rma_qe_allergens'    => array( 0, 8 ),
		);
		$h->save_quick_edit_fields( 12 );
		qrms_assert_same( 55, (int) get_post_meta( 12, '_thumbnail_id', true ), 'JS doldurmadı: görsel korunur' );
		qrms_assert_same( array( 8 ), $GLOBALS['qrms_test']['object_terms'][12]['rma_allergen'], 'alerjen 8' );

		$_POST['rma_qe_thumbnail_id'] = '77';
		$h->save_quick_edit_fields( 12 );
		qrms_assert_same( 55, (int) get_post_meta( 12, '_thumbnail_id', true ), 'ürün ID görsel olmaz' );

		$_POST['rma_qe_thumbnail_id'] = '0';
		$h->save_quick_edit_fields( 12 );
		qrms_assert_same( '', get_post_meta( 12, '_thumbnail_id', true ), '0 görseli kaldırır' );
	}
);

qrms_test(
	'geçerli görsel ve alerjenler kaydedilir; fiyat ve diğer meta dokunulmaz',
	function () {
		$h = new RMA_Test_Qe_Harness();
		update_post_meta( 13, 'rma_price', '100' );
		update_post_meta( 13, 'rma_calories', '250' );
		update_post_meta( 13, '_rma_porsiyonlar', array( array( 'ad' => 'Büyük', 'fark' => 40 ) ) );
		update_post_meta( 13, '_qmo_kombin_fiyat', '180' );
		$GLOBALS['qrms_test']['post_types'][60] = 'attachment';

		$_POST = array(
			'rma_qe_nonce'        => wp_create_nonce( 'rma_quick_edit' ),
			'rma_qe_thumbnail_id' => '60',
			'rma_qe_allergens'    => array( 0, 2, 5 ),
		);
		$h->save_quick_edit_fields( 13 );
		$h->save_menu_item_meta( 13 );

		qrms_assert_same( 60, (int) get_post_meta( 13, '_thumbnail_id', true ), 'görsel 60' );
		qrms_assert_same( array( 2, 5 ), $GLOBALS['qrms_test']['object_terms'][13]['rma_allergen'], 'alerjenler' );
		qrms_assert_same( '100', get_post_meta( 13, 'rma_price', true ), 'fiyat korunur' );
		qrms_assert_same( '250', get_post_meta( 13, 'rma_calories', true ), 'kalori korunur' );
		qrms_assert_same( '180', get_post_meta( 13, '_qmo_kombin_fiyat', true ), 'kombin korunur' );
		qrms_assert_same( 'Büyük', get_post_meta( 13, '_rma_porsiyonlar', true )[0]['ad'], 'porsiyon korunur' );
	}
);

qrms_test(
	'alerjen alanı yoksa taksonomi silinmez',
	function () {
		$h = new RMA_Test_Qe_Harness();
		$GLOBALS['qrms_test']['object_terms'][14]['rma_allergen'] = array( 1 );
		$_POST = array(
			'rma_qe_nonce'        => wp_create_nonce( 'rma_quick_edit' ),
			'rma_qe_thumbnail_id' => '',
		);
		$h->save_quick_edit_fields( 14 );
		qrms_assert_same( array( 1 ), $GLOBALS['qrms_test']['object_terms'][14]['rma_allergen'], 'alerjen durur' );
	}
);

echo "\nÜrün çoğaltma\n";

qrms_test(
	'çoğaltma yeni ID üretir, katalog alanlarını kopyalar, işlem metasını kopyalamaz',
	function () {
		$h    = new RMA_Test_Qe_Harness();
		$kaynak = qrms_qe_urun( 20 );

		update_post_meta( 20, 'rma_price', '100' );
		update_post_meta( 20, 'rma_active', '0' );
		update_post_meta( 20, 'rma_views', '42' );
		update_post_meta( 20, '_edit_lock', '1:admin' );
		update_post_meta( 20, '_thumbnail_id', 55 );
		update_post_meta( 20, '_rma_porsiyonlar', array( array( 'ad' => 'Orta', 'fark' => 20 ) ) );
		update_post_meta( 20, '_rma_ekstra_manuel', array( array( 'ad' => 'Peynir', 'fiyat' => 15 ) ) );
		update_post_meta( 20, '_qmo_is_kombin', '1' );
		update_post_meta( 20, '_qmo_kombin_fiyat', '180' );
		update_post_meta( 20, '_qmo_kombin_urun_1', 8 );
		update_post_meta( 20, '_rma_ozel_rozetler', array( 'aci' ) );
		RMA_Tukendi::kaydet( 20, true );

		$GLOBALS['qrms_test']['object_terms'][20] = array(
			'rma_category'   => array( 'pizza' ),
			'rma_allergen'   => array( 'gluten' ),
			'rma_ingredient' => array( 'hamur' ),
		);

		$yeni = $h->duplicate_menu_item( $kaynak );

		qrms_assert_true( $yeni > 0 && $yeni !== 20, 'yeni ID' );
		qrms_assert_same( 'Lahmacun (Kopya)', $GLOBALS['qrms_test']['posts_by_id'][ $yeni ]->post_title, 'başlık eki' );
		qrms_assert_same( 'Açıklama A', $GLOBALS['qrms_test']['posts_by_id'][ $yeni ]->post_content, 'içerik' );
		qrms_assert_same( '100', get_post_meta( $yeni, 'rma_price', true ), 'fiyat' );
		qrms_assert_same( '1', get_post_meta( $yeni, 'rma_active', true ), 'kopya görünür' );
		qrms_assert_same( '0', get_post_meta( $yeni, RMA_Tukendi::META, true ), 'kopya stokta' );
		qrms_assert_same( '', get_post_meta( $yeni, 'rma_views', true ), 'görüntüleme kopyalanmaz' );
		qrms_assert_same( '', get_post_meta( $yeni, '_edit_lock', true ), 'kilit kopyalanmaz' );
		qrms_assert_same( 55, (int) get_post_meta( $yeni, '_thumbnail_id', true ), 'görsel ID paylaşılır' );
		qrms_assert_same( 'Orta', get_post_meta( $yeni, '_rma_porsiyonlar', true )[0]['ad'], 'porsiyon' );
		qrms_assert_same( 'Peynir', get_post_meta( $yeni, '_rma_ekstra_manuel', true )[0]['ad'], 'extra' );
		qrms_assert_same( '180', get_post_meta( $yeni, '_qmo_kombin_fiyat', true ), 'combo paket' );
		qrms_assert_same( array( 'pizza' ), $GLOBALS['qrms_test']['object_terms'][ $yeni ]['rma_category'], 'kategori slug' );
		qrms_assert_same( array( 'gluten' ), $GLOBALS['qrms_test']['object_terms'][ $yeni ]['rma_allergen'], 'alerjen' );
		qrms_assert_same( array( 'hamur' ), $GLOBALS['qrms_test']['object_terms'][ $yeni ]['rma_ingredient'], 'malzeme' );
	}
);

qrms_test(
	'kopya ve asıl ürün meta düzeyinde bağımsızdır',
	function () {
		$h = new RMA_Test_Qe_Harness();
		update_post_meta( 21, 'rma_price', '100' );
		update_post_meta( 21, '_rma_ekstra_manuel', array( array( 'ad' => 'Sos', 'fiyat' => 10 ) ) );

		$yeni = $h->duplicate_menu_item( qrms_qe_urun( 21, 'Pide' ) );

		update_post_meta( $yeni, 'rma_price', '150' );
		$kopya_ex           = get_post_meta( $yeni, '_rma_ekstra_manuel', true );
		$kopya_ex[0]['ad']  = 'Mayonez';
		update_post_meta( $yeni, '_rma_ekstra_manuel', $kopya_ex );

		qrms_assert_same( '100', get_post_meta( 21, 'rma_price', true ), 'asıl fiyat' );
		qrms_assert_same( '150', get_post_meta( $yeni, 'rma_price', true ), 'kopya fiyat' );
		qrms_assert_same( 'Sos', get_post_meta( 21, '_rma_ekstra_manuel', true )[0]['ad'], 'asıl extra' );
		qrms_assert_same( 'Mayonez', get_post_meta( $yeni, '_rma_ekstra_manuel', true )[0]['ad'], 'kopya extra' );
	}
);

qrms_test(
	'çoğaltma yanlış yazı tipini ve WP_Error eklemeyi reddeder',
	function () {
		$h = new RMA_Test_Qe_Harness();
		$sayfa = (object) array(
			'ID'           => 22,
			'post_type'    => 'page',
			'post_title'   => 'Sayfa',
			'post_content' => '',
			'post_excerpt' => '',
		);
		$err = $h->duplicate_menu_item( $sayfa );
		qrms_assert_true( is_wp_error( $err ), 'sayfa kopyalanmaz' );

		$GLOBALS['qrms_test']['wp_insert_post_error'] = true;
		$err2 = $h->duplicate_menu_item( qrms_qe_urun( 23 ) );
		qrms_assert_true( is_wp_error( $err2 ), 'insert hatası WP_Error' );
	}
);

qrms_test(
	'çoğaltma eylemi nonce, yetki ve yönlendirme kullanır',
	function () {
		$h = new RMA_Test_Qe_Harness();
		$kaynak = qrms_qe_urun( 24 );
		$GLOBALS['qrms_test']['posts_by_id'][24] = $kaynak;
		update_post_meta( 24, 'rma_price', '80' );

		$_GET = array( 'post' => '24', 'nonce' => 'yok' );
		try {
			$h->duplicate_post_action();
			qrms_assert_true( false, 'sahte nonce wp_die etmeli' );
		} catch ( RuntimeException $e ) {
			qrms_assert_contains( 'Güvenlik hatası', $e->getMessage(), 'nonce' );
		}

		$GLOBALS['qrms_test']['can_edit_post'][24] = false;
		$_GET['nonce'] = wp_create_nonce( 'rma_duplicate_post_24' );
		try {
			$h->duplicate_post_action();
			qrms_assert_true( false, 'yetkisiz wp_die' );
		} catch ( RuntimeException $e ) {
			qrms_assert_contains( 'Yetkiniz yok', $e->getMessage(), 'yetki' );
		}

		unset( $GLOBALS['qrms_test']['can_edit_post'][24] );
		try {
			$h->duplicate_post_action();
			qrms_assert_true( false, 'yönlendirme beklenir' );
		} catch ( QRMS_Test_Redirect $yon ) {
			qrms_assert_contains( 'post.php?action=edit&post=', $yon->getMessage(), 'kopya düzenleme ekranı' );
		}
	}
);

echo "\nKaynak sözleşmesi\n";

qrms_test(
	'hızlı düzenle JS alerjenleri value ile işaretler; kopya WP_Error nesnesini başarı saymaz',
	function () {
		$php = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/trait-admin-columns.php' );
		$js  = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/assets/js/admin-ui.js' );

		qrms_assert_contains( "ids.indexOf(String(this.value))", $js, 'alerjen checkbox value' );
		qrms_assert_contains( "value=\"\"", $php, 'thumbnail varsayılan boş' );
		qrms_assert_contains( "is_wp_error( \$new_id )", $php, 'WP_Error kontrolü' );
		qrms_assert_contains( "'rma_menu_item' !== \$post->post_type", $php, 'yalnız menü ürünü' );
		qrms_assert_contains( 'rma_views', $php, 'görüntüleme atlanır' );
		qrms_assert_contains( 'post.php?action=edit&post=', $php, 'kopyaya yönlenir' );
		qrms_assert_false( false !== strpos( $js, '$boxes.val(ids)' ), 'eski val(ids) yok' );
	}
);
