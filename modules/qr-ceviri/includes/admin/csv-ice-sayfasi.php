<?php
/**
 * Alt sayfa: CSV İçe Aktar (qrms-cv-ice).
 *
 * Asıl yükleme admin-post ucundadır (csv-import.php); burası formu ve
 * bildirimleri basar.
 *
 * @package QR_Menu_Suite
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'rma_ceviri_csv_ice_formu' ) ) {

	/**
	 * İçe aktarma formu (admin-post).
	 *
	 * @return void
	 */
	function rma_ceviri_csv_ice_formu() {
		?>
		<p class="description">
			Dışa aktardığınız dosyanın aynı yapıda olması yeterlidir; ayraç
			(<code>,</code> / <code>;</code>) otomatik algılanır.
		</p>
		<form method="POST" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
			<input type="hidden" name="action" value="rma_ceviri_import">
			<?php wp_nonce_field( 'rma_ceviri_import_action', 'rma_ceviri_import_nonce' ); ?>

			<table class="form-table">
				<tr>
					<th>CSV dosyası</th>
					<td><input type="file" name="rma_ceviri_dosya" accept=".csv,text/csv" required></td>
				</tr>
				<tr>
					<th>Boş hücreler</th>
					<td>
						<label>
							<input type="checkbox" name="bos_hucreleri_temizle" value="1">
							Boş hücreleri temizle (o dildeki mevcut çeviriyi sil)
						</label>
						<p class="description">
							İşaretli değilse boş hücreler atlanır ve mevcut çeviri korunur —
							kısmi güncelleme için güvenli olan budur.
						</p>
					</td>
				</tr>
			</table>

			<button type="submit" class="button button-primary">Çeviri CSV'sini İçe Aktar</button>
		</form>
		<?php
	}
}

if ( ! function_exists( 'qrms_module_qr_ceviri_sayfa_ice' ) ) {

	/**
	 * CSV içe aktarma ekranı.
	 *
	 * @return void
	 */
	function qrms_module_qr_ceviri_sayfa_ice() {
		rma_ceviri_import_bildirimleri();

		qrms_module_qr_ceviri_sayfa_ac( 'qrms-cv-ice' );
		qrms_module_qr_ceviri_baslik( 'dashicons-upload', 'CSV İçe Aktar', 'h1' );
		rma_ceviri_csv_ice_formu();
		qrms_module_qr_ceviri_sayfa_kapat();
	}
}
