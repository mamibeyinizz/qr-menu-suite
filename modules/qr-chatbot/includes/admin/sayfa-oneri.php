<?php
/**
 * Öneri Yönetimi alt sayfası.
 *
 * @package QR_Menu_Suite
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_enqueue_scripts', 'qmo_chatbot_oneri_admin_varliklari' );

/**
 * Öneri sayfası varlıklarını kuyruğa alır.
 *
 * @param string $hook_suffix Geçerli yönetim kancası.
 * @return void
 */
function qmo_chatbot_oneri_admin_varliklari( $hook_suffix ) {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$sayfa = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
	if ( 'qrms-chatbot-oneri' !== $sayfa ) {
		return;
	}

	wp_enqueue_script(
		'qmo-admin-oneri',
		QRMS_PLUGIN_URL . 'modules/qr-chatbot/assets/js/admin-oneri.js',
		array( 'jquery' ),
		QRMS_Helpers::asset_version( 'modules/qr-chatbot/assets/js/admin-oneri.js' ),
		true
	);

	wp_localize_script(
		'qmo-admin-oneri',
		'qmoChatbotOneri',
		array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'qmo_chatbot_oneri' ),
			'i18n'    => array(
				'kaydedildi'   => __( 'Ürün ayarları kaydedildi.', 'qrms' ),
				'kuralEklendi' => __( 'Kural eklendi.', 'qrms' ),
				'kuralSilindi' => __( 'Kural silindi.', 'qrms' ),
				'hata'         => __( 'İşlem başarısız.', 'qrms' ),
				'ayniUrun'     => __( 'Kaynak ve hedef ürün aynı olamaz.', 'qrms' ),
			),
		)
	);
}

/**
 * Menü ürünlerini listeler (Restoran Menü CPT).
 *
 * @return array<int,array{id:int,ad:string,kategori:string,fiyat:string,dahil:int,agirlik:int}>
 */
function qmo_chatbot_oneri_urunleri_listele() {
	if ( ! post_type_exists( 'rma_menu_item' ) ) {
		return array();
	}

	$sorgu = new WP_Query(
		array(
			'post_type'              => 'rma_menu_item',
			'post_status'            => 'publish',
			'posts_per_page'         => 500,
			'orderby'                => 'title',
			'order'                  => 'ASC',
			'no_found_rows'          => true,
			'update_post_meta_cache' => true,
			'update_post_term_cache' => true,
		)
	);

	$liste = array();
	foreach ( $sorgu->posts as $post ) {
		$fiyat = function_exists( 'rma_get_effective_price' )
			? rma_get_effective_price( $post->ID )
			: get_post_meta( $post->ID, 'rma_price', true );
		$kat   = wp_get_post_terms( $post->ID, 'rma_category', array( 'fields' => 'names' ) );
		$dahil = (int) get_post_meta( $post->ID, '_qmo_oneri_dahil', true );
		$agir  = (int) get_post_meta( $post->ID, '_qmo_oneri_agirlik', true );
		if ( $agir < 1 ) {
			$agir = 50;
		}

		$liste[] = array(
			'id'       => (int) $post->ID,
			'ad'       => $post->post_title,
			'kategori' => ( is_array( $kat ) && $kat ) ? $kat[0] : '—',
			'fiyat'    => is_numeric( $fiyat ) ? (string) $fiyat : (string) $fiyat,
			'dahil'    => $dahil ? 1 : 0,
			'agirlik'  => max( 0, min( 100, $agir ) ),
		);
	}

	return $liste;
}

/**
 * Tüm öneri kurallarını (aktif/pasif) döndürür.
 *
 * @return array
 */
function qmo_chatbot_oneri_tum_kurallar() {
	global $wpdb;

	QMO_Chatbot_DB::sema_kontrol();

	$tablo = QMO_Chatbot_DB::oneri_kural_tablosu();
	$satir = $wpdb->get_results(
		"SELECT * FROM {$tablo} ORDER BY agirlik DESC, id DESC"
	);

	return is_array( $satir ) ? $satir : array();
}

/**
 * Öneri yönetimi ekranı.
 *
 * @return void
 */
function qmo_chatbot_sayfa_oneri() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Bu sayfaya erişim yetkiniz yok.' );
	}

	QMO_Chatbot_DB::sema_kontrol();

	$urunler  = qmo_chatbot_oneri_urunleri_listele();
	$kurallar = qmo_chatbot_oneri_tum_kurallar();
	$baslik   = array();
	foreach ( $urunler as $u ) {
		$baslik[ $u['id'] ] = $u['ad'];
	}

	qmo_chatbot_sayfa_basligi(
		__( 'Öneri Yönetimi', 'qrms' ),
		__( 'Chatbot öneri motoru için ürün havuzu ve birlikte öneri kurallarını yönetin.', 'qrms' )
	);
	?>
	<div id="qmo-cb-oneri-bildirim" class="qmo-cb-oneri-bildirim" hidden></div>

	<h2 class="qmo-cb-oneri-baslik"><?php esc_html_e( 'Önerilecek Ürünler', 'qrms' ); ?></h2>
	<p class="description"><?php esc_html_e( 'Öneri havuzuna dahil edilecek ürünleri ve temel ağırlıklarını belirleyin.', 'qrms' ); ?></p>

	<?php if ( empty( $urunler ) ) : ?>
		<p class="qmo-cb-oneri-bos"><?php esc_html_e( 'Yayında menü ürünü bulunamadı. Restoran Menü modülünden ürün ekleyin.', 'qrms' ); ?></p>
	<?php else : ?>
		<table class="widefat striped" id="qmo-cb-oneri-urun-tablo">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Ürün', 'qrms' ); ?></th>
					<th><?php esc_html_e( 'Kategori', 'qrms' ); ?></th>
					<th><?php esc_html_e( 'Fiyat', 'qrms' ); ?></th>
					<th class="qmo-cb-oneri-col-dahil"><?php esc_html_e( 'Öneriye dahil', 'qrms' ); ?></th>
					<th class="qmo-cb-oneri-col-agirlik"><?php esc_html_e( 'Ağırlık', 'qrms' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $urunler as $urun ) : ?>
					<tr data-urun-id="<?php echo (int) $urun['id']; ?>">
						<td><?php echo esc_html( $urun['ad'] ); ?></td>
						<td><?php echo esc_html( $urun['kategori'] ); ?></td>
						<td><?php echo esc_html( $urun['fiyat'] ); ?></td>
						<td>
							<input type="checkbox" class="qmo-cb-oneri-dahil" value="1"
								<?php checked( 1, (int) $urun['dahil'] ); ?>>
						</td>
						<td>
							<input type="number" class="qmo-cb-oneri-agirlik small-text" min="0" max="100"
								value="<?php echo (int) $urun['agirlik']; ?>">
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<p>
			<button type="button" class="button button-primary" id="qmo-cb-oneri-urun-kaydet">
				<?php esc_html_e( 'Ürün ayarlarını kaydet', 'qrms' ); ?>
			</button>
		</p>
	<?php endif; ?>

	<hr class="qmo-cb-oneri-ayrac">

	<h2 class="qmo-cb-oneri-baslik"><?php esc_html_e( 'Birlikte Öneri Kuralları', 'qrms' ); ?></h2>
	<p class="description"><?php esc_html_e( 'Bir ürün seçildiğinde önerilecek tamamlayıcı ürünleri tanımlayın.', 'qrms' ); ?></p>

	<?php if ( ! empty( $urunler ) ) : ?>
		<form id="qmo-cb-oneri-kural-form" class="qmo-cb-oneri-kural-form">
			<label>
				<?php esc_html_e( 'Kaynak ürün', 'qrms' ); ?>
				<select id="qmo-cb-oneri-kaynak" class="qmo-cb-oneri-select">
					<option value=""><?php esc_html_e( 'Seçin…', 'qrms' ); ?></option>
					<?php foreach ( $urunler as $urun ) : ?>
						<option value="<?php echo (int) $urun['id']; ?>"><?php echo esc_html( $urun['ad'] ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
			<label>
				<?php esc_html_e( 'Hedef ürün', 'qrms' ); ?>
				<select id="qmo-cb-oneri-hedef" class="qmo-cb-oneri-select">
					<option value=""><?php esc_html_e( 'Seçin…', 'qrms' ); ?></option>
					<?php foreach ( $urunler as $urun ) : ?>
						<option value="<?php echo (int) $urun['id']; ?>"><?php echo esc_html( $urun['ad'] ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
			<label>
				<?php esc_html_e( 'Ağırlık', 'qrms' ); ?>
				<input type="number" id="qmo-cb-oneri-kural-agirlik" class="small-text" min="0" max="100" value="50">
			</label>
			<button type="submit" class="button button-secondary"><?php esc_html_e( 'Ekle', 'qrms' ); ?></button>
		</form>
	<?php endif; ?>

	<table class="widefat striped" id="qmo-cb-oneri-kural-tablo">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Kaynak', 'qrms' ); ?></th>
				<th><?php esc_html_e( 'Hedef', 'qrms' ); ?></th>
				<th><?php esc_html_e( 'Ağırlık', 'qrms' ); ?></th>
				<th><?php esc_html_e( 'Aktif', 'qrms' ); ?></th>
				<th></th>
			</tr>
		</thead>
		<tbody id="qmo-cb-oneri-kural-listesi">
			<?php if ( empty( $kurallar ) ) : ?>
				<tr class="qmo-cb-oneri-kural-bos"><td colspan="5"><?php esc_html_e( 'Henüz kural yok.', 'qrms' ); ?></td></tr>
			<?php else : ?>
				<?php foreach ( $kurallar as $kural ) : ?>
					<?php
					$kaynak_ad = isset( $baslik[ (int) $kural->kaynak_urun ] ) ? $baslik[ (int) $kural->kaynak_urun ] : '#' . (int) $kural->kaynak_urun;
					$hedef_ad  = isset( $baslik[ (int) $kural->hedef_urun ] ) ? $baslik[ (int) $kural->hedef_urun ] : '#' . (int) $kural->hedef_urun;
					?>
					<tr data-kural-id="<?php echo (int) $kural->id; ?>"
						data-kaynak="<?php echo (int) $kural->kaynak_urun; ?>"
						data-hedef="<?php echo (int) $kural->hedef_urun; ?>">
						<td><?php echo esc_html( $kaynak_ad ); ?></td>
						<td><?php echo esc_html( $hedef_ad ); ?></td>
						<td class="qmo-cb-oneri-kural-agirlik"><?php echo (int) $kural->agirlik; ?></td>
						<td>
							<label class="qmo-cb-oneri-toggle">
								<input type="checkbox" class="qmo-cb-oneri-kural-aktif" value="1"
									<?php checked( 1, (int) $kural->aktif ); ?>>
								<span class="qmo-cb-oneri-toggle-ui" aria-hidden="true"></span>
							</label>
						</td>
						<td>
							<button type="button" class="button-link-delete qmo-cb-oneri-kural-sil"
								data-id="<?php echo (int) $kural->id; ?>"><?php esc_html_e( 'Sil', 'qrms' ); ?></button>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>
	<?php
	qmo_chatbot_sayfa_bitir();
}
