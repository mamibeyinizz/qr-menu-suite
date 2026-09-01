<?php
/**
 * Alt sayfa: Metin Toplama (qrms-cv-toplama).
 *
 * Aç/kapa, toplanan metinler, "Çevrilecek sabit metinler" kutusu.
 * Kayıt YALNIZCA bu adımın seçeneklerini yazar.
 *
 * @package QR_Menu_Suite
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'rma_ceviri_toplama_kaydet' ) ) {

	/**
	 * Toplama ve sabit metin listesini kaydet — dillere ve kapsama dokunmaz.
	 *
	 * @return void
	 */
	function rma_ceviri_toplama_kaydet() {
		check_admin_referer( 'qrms_cv_save_toplama', 'qrms_cv_toplama_nonce' );

		update_option(
			'rma_ceviri_ek_metinler',
			isset( $_POST['rma_ek_metinler'] ) ? sanitize_textarea_field( wp_unslash( $_POST['rma_ek_metinler'] ) ) : '',
			false
		);
		update_option( 'rma_ceviri_toplama_acik', empty( $_POST['rma_toplama_acik'] ) ? 0 : 1, false );

		$aktarilan = 0;
		if ( ! empty( $_POST['rma_bulunan'] ) ) {
			$secilenler = array_map(
				static function ( $m ) {
					return sanitize_text_field( wp_unslash( $m ) );
				},
				(array) $_POST['rma_bulunan']
			);
			$aktarilan = rma_ceviri_bulunanlari_aktar( $secilenler );
		}

		if ( ! empty( $_POST['rma_bulunanlari_temizle'] ) ) {
			rma_ceviri_bulunanlari_yaz( array() );
		}

		if ( function_exists( 'rma_ceviri_onbellek_temizle' ) ) {
			rma_ceviri_onbellek_temizle();
		}

		$mesaj = 'Metin toplama ayarları kaydedildi.';
		if ( $aktarilan > 0 ) {
			$mesaj .= sprintf( ' %d metin listeye eklendi — bir sonraki CSV dışa aktarımında çıkacak.', $aktarilan );
		}

		echo '<div class="updated"><p>' . esc_html( $mesaj ) . '</p></div>';
	}
}

if ( ! function_exists( 'rma_ceviri_toplama_alanlari' ) ) {

	/**
	 * Toplama aç/kapa, bulunanlar ve sabit metin kutusu.
	 *
	 * @return void
	 */
	function rma_ceviri_toplama_alanlari() {
		$bulunanlar = rma_ceviri_bulunan_metinler();
		?>
		<p class="description qrc-limit">
			Sitede çevrilmeyen bir metin mi var? Türkçe hâlini aşağıdaki kutuya
			ekleyip kaydedin — sonraki CSV dışa aktarımında otomatik çıkar.
			Tek tek aramak istemiyorsanız toplamayı açıp siteyi bir kez gezin.
		</p>
		<table class="form-table">
			<tr>
				<th>Metin Toplama</th>
				<td>
					<label>
						<input type="checkbox" name="rma_toplama_acik" value="1" <?php checked( rma_ceviri_toplama_acik_mi() ); ?>>
						Siteyi gezerken çevrilmemiş metinleri topla
					</label>
					<p class="description">
						Açıkken siteyi Türkçe gezin (footer, formlar, iletişim sayfası…);
						gördüğü metinler aşağıda listelenir. İşiniz bitince kapatın.
						Yalnızca siz (giriş yapmış yönetici) siteyi Türkçe görüntülerken
						çalışır; en fazla <?php echo (int) RMA_CEVIRI_TOPLAMA_LIMIT; ?> aday saklanır.
					</p>
				</td>
			</tr>

			<?php if ( ! empty( $bulunanlar ) ) : ?>
			<tr>
				<th>Bulunan metinler (<?php echo count( $bulunanlar ); ?>)</th>
				<td>
					<div class="qrc-scrollbox">
						<?php foreach ( array_keys( $bulunanlar ) as $metin ) : ?>
							<label class="qrc-check qrc-check-block">
								<input type="checkbox" name="rma_bulunan[]" value="<?php echo esc_attr( $metin ); ?>">
								<span><?php echo esc_html( $metin ); ?></span>
							</label>
						<?php endforeach; ?>
					</div>
					<p class="description">
						Çevrilmesini istediklerinizi işaretleyip kaydedin; aşağıdaki
						listeye eklenir ve buradan kalkar.
					</p>
					<label>
						<input type="checkbox" name="rma_bulunanlari_temizle" value="1">
						Listeyi tamamen temizle
					</label>
				</td>
			</tr>
			<?php endif; ?>

			<tr>
				<th>Çevrilecek sabit metinler</th>
				<td>
					<textarea name="rma_ek_metinler" rows="8" class="large-text code" placeholder="Her satıra bir metin&#10;Bize Ulaşın&#10;Yemek Lezzeti"><?php echo esc_textarea( get_option( 'rma_ceviri_ek_metinler', '' ) ); ?></textarea>
					<p class="description">
						Her satır bir metin. Menünün kendi metinleri (Sepete Ekle, Filtrele…)
						zaten dahildir, onları eklemenize gerek yok.
					</p>
				</td>
			</tr>
		</table>
		<?php
	}
}

if ( ! function_exists( 'qrms_module_qr_ceviri_sayfa_toplama' ) ) {

	/**
	 * Metin Toplama ekranı.
	 *
	 * @return void
	 */
	function qrms_module_qr_ceviri_sayfa_toplama() {
		if ( isset( $_POST['qrms_cv_toplama_save'] ) ) {
			rma_ceviri_toplama_kaydet();
		}

		qrms_module_qr_ceviri_sayfa_ac( 'qrms-cv-toplama' );
		qrms_module_qr_ceviri_baslik( 'dashicons-editor-ul', 'Metin Toplama', 'h1' );
		?>
		<form method="POST">
			<?php wp_nonce_field( 'qrms_cv_save_toplama', 'qrms_cv_toplama_nonce' ); ?>
			<?php rma_ceviri_toplama_alanlari(); ?>
			<p class="submit">
				<input type="submit" name="qrms_cv_toplama_save" class="button button-primary button-large" value="Toplama ayarlarını kaydet">
				<span class="description">Yalnızca bu sayfadaki toplama ve sabit metin listesi kaydedilir.</span>
			</p>
		</form>
		<?php
		qrms_module_qr_ceviri_sayfa_kapat();
	}
}
