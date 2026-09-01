<?php
/**
 * Alt sayfa: Kapsam (qrms-cv-kapsam).
 *
 * Hangi post type / taxonomy / Elementor sayfalarının çeviri kaynağı
 * olacağı. Eski "Gelişmiş Ayarlar" kaynak seçiminin karşılığı. Kayıt
 * YALNIZCA bu adımın seçeneklerini yazar.
 *
 * @package QR_Menu_Suite
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'rma_ceviri_kapsami_kaydet' ) ) {

	/**
	 * Kaynak seçimini kaydet — dillere, toplama listesine, CSV'ye dokunmaz.
	 *
	 * @return void
	 */
	function rma_ceviri_kapsami_kaydet() {
		check_admin_referer( 'qrms_cv_save_kapsam', 'qrms_cv_kapsam_nonce' );

		$gonderilen = static function ( $ad ) {
			return isset( $_POST[ $ad ] ) ? array_map( 'sanitize_key', (array) wp_unslash( $_POST[ $ad ] ) ) : array();
		};

		update_option(
			'rma_ceviri_urun_tipleri',
			array_values( array_intersect( $gonderilen( 'rma_urun_tipleri' ), array_keys( rma_ceviri_uygun_urun_tipleri() ) ) ),
			false
		);

		$uygun_taks = array_keys( rma_ceviri_uygun_taksonomiler() );

		list( $kategori_taks, $alerjen_taks ) = rma_ceviri_taks_ayir(
			array_intersect( $gonderilen( 'rma_kategori_taks' ), $uygun_taks ),
			array_intersect( $gonderilen( 'rma_alerjen_taks' ), $uygun_taks )
		);

		update_option( 'rma_ceviri_kategori_taks', $kategori_taks, false );
		update_option( 'rma_ceviri_alerjen_taks', $alerjen_taks, false );

		$secili = isset( $_POST['elementor_sayfalar'] )
			? array_map( 'intval', (array) $_POST['elementor_sayfalar'] )
			: array();
		$secili = function_exists( 'rma_ceviri_elementor_secimini_ele' )
			? rma_ceviri_elementor_secimini_ele( $secili )
			: array_values( array_unique( $secili ) );
		update_option( 'rma_ceviri_elementor_sayfalar', $secili, false );

		if ( function_exists( 'rma_ceviri_onbellek_temizle' ) ) {
			rma_ceviri_onbellek_temizle();
		}

		echo '<div class="updated"><p>Kapsam ayarları kaydedildi.</p></div>';
	}
}

if ( ! function_exists( 'rma_ceviri_kapsam_alanlari' ) ) {

	/**
	 * Post type / taxonomy seçim kutuları.
	 *
	 * @return void
	 */
	function rma_ceviri_kapsam_alanlari() {
		$post_tipleri = rma_ceviri_uygun_urun_tipleri();
		$taksonomiler = rma_ceviri_uygun_taksonomiler();
		?>
		<p class="description qrc-limit">
			Hangi içeriklerin CSV'ye çıkacağını belirler. Varsayılanlar sitenizden
			otomatik bulunur.
		</p>
		<table class="form-table">
			<tr>
				<th>Menü ürünleri</th>
				<td>
					<?php rma_ceviri_secim_kutulari( 'rma_urun_tipleri', $post_tipleri, rma_ceviri_urun_tipleri() ); ?>
					<p class="description">
						Yazı, sayfa ve Elementor şablonları menü ürünü değildir; onların
						metinleri aşağıdaki sayfa listesinden, sayfa başlıkları dahil çevrilir.
					</p>
				</td>
			</tr>
			<tr>
				<th>Kategoriler</th>
				<td><?php rma_ceviri_secim_kutulari( 'rma_kategori_taks', $taksonomiler, rma_ceviri_taksonomiler( 'category' ) ); ?></td>
			</tr>
			<tr>
				<th>Alerjenler</th>
				<td>
					<?php rma_ceviri_secim_kutulari( 'rma_alerjen_taks', $taksonomiler, rma_ceviri_taksonomiler( 'allergen' ) ); ?>
					<p class="description">
						Aynı liste iki yerde işaretlenirse kaydederken tek yerde bırakılır;
						aksi hâlde aynı isimler CSV'ye iki kez çıkardı.
					</p>
				</td>
			</tr>
		</table>
		<?php
	}
}

if ( ! function_exists( 'rma_ceviri_elementor_liste_alani' ) ) {

	/**
	 * "Ayrıca şu sayfaları dahil et" kutusu.
	 *
	 * Silinmiş sayfalar seçili kalamaz: liste mevcut içerikle süzülür.
	 *
	 * @return void
	 */
	function rma_ceviri_elementor_liste_alani() {
		$liste  = function_exists( 'rma_ceviri_elementor_liste_ve_secili' )
			? rma_ceviri_elementor_liste_ve_secili()
			: array( rma_ceviri_elementor_sayfalari(), rma_ceviri_secili_elementor_sayfalari() );
		$sayfalar = $liste[0];
		$secili   = $liste[1];
		$dustu    = isset( $liste[2] ) ? (int) $liste[2] : 0;
		?>
		<?php if ( $dustu > 0 ) : ?>
			<div class="notice notice-warning inline"><p>
				<?php printf( '%d silinmiş sayfa seçimden çıkarıldı.', $dustu ); ?>
			</p></div>
		<?php endif; ?>
		<table class="form-table">
			<tr>
				<th>Ayrıca şu sayfaları dahil et</th>
				<td>
					<?php if ( empty( $sayfalar ) ) : ?>
						<em>Elementor ile düzenlenmiş içerik bulunamadı.</em>
					<?php else : ?>
						<div class="qrc-scrollbox">
							<?php foreach ( $sayfalar as $id => $baslik ) : ?>
								<label class="qrc-check qrc-check-block">
									<input type="checkbox" name="elementor_sayfalar[]" value="<?php echo (int) $id; ?>" <?php checked( in_array( (int) $id, $secili, true ) ); ?>>
									<span><?php echo esc_html( $baslik ); ?> <small class="qrc-muted">#<?php echo (int) $id; ?></small></span>
								</label>
							<?php endforeach; ?>
						</div>
						<p class="description">
							Sayfa başlıkları ve içindeki metinler CSV'ye eklenir. Çevirmek
							istediklerinizi seçin. Silinmiş sayfalar listede kalmaz.
						</p>
					<?php endif; ?>
				</td>
			</tr>
		</table>
		<?php
	}
}

if ( ! function_exists( 'qrms_module_qr_ceviri_sayfa_kapsam' ) ) {

	/**
	 * Kapsam ekranı.
	 *
	 * @return void
	 */
	function qrms_module_qr_ceviri_sayfa_kapsam() {
		if ( isset( $_POST['qrms_cv_kapsam_save'] ) ) {
			rma_ceviri_kapsami_kaydet();
		}

		qrms_module_qr_ceviri_sayfa_ac( 'qrms-cv-kapsam' );
		qrms_module_qr_ceviri_baslik( 'dashicons-filter', 'Kapsam', 'h1' );
		?>
		<form method="POST">
			<?php wp_nonce_field( 'qrms_cv_save_kapsam', 'qrms_cv_kapsam_nonce' ); ?>
			<?php rma_ceviri_kapsam_alanlari(); ?>
			<?php rma_ceviri_elementor_liste_alani(); ?>
			<p class="submit">
				<input type="submit" name="qrms_cv_kapsam_save" class="button button-primary button-large" value="Kapsamı kaydet">
				<span class="description">Yalnızca bu sayfadaki kaynak seçimi kaydedilir.</span>
			</p>
		</form>
		<?php
		qrms_module_qr_ceviri_sayfa_kapat();
	}
}
