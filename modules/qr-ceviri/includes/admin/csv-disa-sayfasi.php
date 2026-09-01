<?php
/**
 * Alt sayfa: CSV Dışa Aktar (qrms-cv-disa).
 *
 * Ayraç seçimi ve dahil edilecek sayfalar. Asıl indirme admin-post
 * ucundadır (csv-export.php); burası yalnızca formu basar.
 *
 * @package QR_Menu_Suite
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'rma_ceviri_csv_disa_formu' ) ) {

	/**
	 * Dışa aktarma formu (admin-post).
	 *
	 * @return void
	 */
	function rma_ceviri_csv_disa_formu() {
		?>
		<p class="description qrc-limit">
			Menü ürünleri, kategoriler, menü linkleri, sabit metinler ve yönetici
			ayarları (form başlığı, chatbot metinleri, form alan etiketleri…) her zaman
			dahildir. Mevcut çeviriler dolu gelir — sıfırdan başlamanız gerekmez.
			<strong>Sadece dil sütunlarını doldurun</strong>, ilk sütunlara dokunmayın.
		</p>
		<details class="qrc-limit qrc-details">
			<summary>Bu sütunlara neden dokunmamalıyım?</summary>
			<p class="description" style="margin-top:6px;">
				<code>item_id</code>, <code>item_type</code> ve <code>field</code> sütunları
				her satırın sitedeki hangi metne ait olduğunu tutar; değişirlerse satır
				eşleşmez ve içe aktarımda atlanır. <code>original_hash</code> ise orijinal
				metin sonradan değiştiyse sizi uyarmak için kullanılır.
			</p>
		</details>
		<form method="POST" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="rma_ceviri_export">
			<?php wp_nonce_field( 'rma_ceviri_export_action', 'rma_ceviri_export_nonce' ); ?>

			<table class="form-table">
				<tr>
					<th>Sütun ayracı</th>
					<td>
						<label><input type="radio" name="ayrac" value=";" checked> Noktalı virgül <code>;</code> (Excel Türkçe)</label><br>
						<label><input type="radio" name="ayrac" value=","> Virgül <code>,</code> (Google Sheets)</label>
					</td>
				</tr>
			</table>

			<?php rma_ceviri_elementor_liste_alani(); ?>

			<button type="submit" class="button button-primary">Çeviri CSV'sini Dışa Aktar</button>
		</form>
		<?php
	}
}

if ( ! function_exists( 'qrms_module_qr_ceviri_sayfa_disa' ) ) {

	/**
	 * CSV dışa aktarma ekranı.
	 *
	 * @return void
	 */
	function qrms_module_qr_ceviri_sayfa_disa() {
		qrms_module_qr_ceviri_sayfa_ac( 'qrms-cv-disa' );
		qrms_module_qr_ceviri_baslik( 'dashicons-download', 'CSV Dışa Aktar', 'h1' );
		rma_ceviri_csv_disa_formu();
		qrms_module_qr_ceviri_sayfa_kapat();
	}
}
