<?php
/**
 * Alt sayfa: Diller (qrms-cv-diller).
 *
 * Menüde gösterilecek diller, her dilin çeviri sayısı ve dil seçici
 * görünümü / davranışı. Kayıt YALNIZCA bu adımın seçeneklerini yazar.
 *
 * @package QR_Menu_Suite
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'rma_ceviri_dilleri_kaydet' ) ) {

	/**
	 * Dil ve görünüm ayarlarını kaydet — kapsam / toplama / CSV'ye dokunmaz.
	 *
	 * @return void
	 */
	function rma_ceviri_dilleri_kaydet() {
		check_admin_referer( 'qrms_cv_save_diller', 'qrms_cv_diller_nonce' );

		$langs         = isset( $_POST['qrmenu_langs'] ) ? array_map( 'sanitize_text_field', (array) $_POST['qrmenu_langs'] ) : array();
		$bg_color_text = isset( $_POST['qrmenu_bg_color_text'] ) ? sanitize_hex_color( $_POST['qrmenu_bg_color_text'] ) : '#111111';
		$bg_color_only = isset( $_POST['qrmenu_bg_color_only'] ) ? sanitize_hex_color( $_POST['qrmenu_bg_color_only'] ) : '#111111';

		update_option( 'qrmenu_active_langs', $langs );
		update_option( 'qrmenu_bg_color_text', $bg_color_text ? $bg_color_text : '#111111' );
		update_option( 'qrmenu_bg_color_only', $bg_color_only ? $bg_color_only : '#111111' );
		update_option( 'rma_ceviri_url_yonlendir', empty( $_POST['rma_url_yonlendir'] ) ? 0 : 1, false );
		update_option( 'rma_ceviri_tampon_acik', empty( $_POST['rma_tampon_acik'] ) ? 0 : 1, false );

		if ( function_exists( 'rma_ceviri_onbellek_temizle' ) ) {
			rma_ceviri_onbellek_temizle();
		}

		echo '<div class="updated"><p>Dil ayarları kaydedildi.</p></div>';
	}
}

if ( ! function_exists( 'rma_ceviri_diller_alanlari' ) ) {

	/**
	 * Dil onay kutuları — form etiketinin içine basılır.
	 *
	 * @return void
	 */
	function rma_ceviri_diller_alanlari() {
		$all_langs    = qrmenu_get_langs();
		$active_langs = get_option( 'qrmenu_active_langs', rma_ceviri_varsayilan_diller() );
		$dil_sayilari = class_exists( 'RMA_Ceviri_Tablo' ) ? RMA_Ceviri_Tablo::dil_sayilari() : array();

		if ( ! is_array( $active_langs ) ) {
			$active_langs = rma_ceviri_varsayilan_diller();
		}
		?>
		<table class="form-table">
			<tr>
				<th>Menüde gösterilecek diller</th>
				<td>
					<div class="qrc-check-grid qrc-lang-grid">
						<?php foreach ( $all_langs as $code => $data ) : ?>
							<label class="qrc-check">
								<input type="checkbox" name="qrmenu_langs[]" value="<?php echo esc_attr( $code ); ?>" <?php checked( in_array( $code, $active_langs, true ) ); ?>>
								<span class="qrc-flag"><?php echo $data['flag']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
								<span class="qrc-lang-name"><?php echo esc_html( $data['name'] ); ?></span>
								<?php if ( isset( $dil_sayilari[ $code ] ) ) : ?>
									<small class="qrc-muted">(<?php echo (int) $dil_sayilari[ $code ]; ?> çeviri)</small>
								<?php endif; ?>
							</label>
						<?php endforeach; ?>
					</div>
					<p class="description">Türkçe orijinal dildir; her zaman listede kalır.</p>
				</td>
			</tr>
		</table>
		<?php
	}
}

if ( ! function_exists( 'rma_ceviri_gorunum_alanlari' ) ) {

	/**
	 * Dil seçici rengi ve davranış kutuları.
	 *
	 * @return void
	 */
	function rma_ceviri_gorunum_alanlari() {
		$bg_color_text = get_option( 'qrmenu_bg_color_text', '#111111' );
		$bg_color_only = get_option( 'qrmenu_bg_color_only', '#111111' );
		?>
		<h3 class="qrc-heading">
			<span class="dashicons dashicons-admin-appearance" aria-hidden="true"></span>
			Görünüm ve davranış
		</h3>
		<table class="form-table">
			<tr>
				<th>"Sadece Bayrak" butonu rengi</th>
				<td><input type="color" name="qrmenu_bg_color_only" value="<?php echo esc_attr( $bg_color_only ); ?>"></td>
			</tr>
			<tr>
				<th>"Bayrak + Metin" butonu rengi</th>
				<td><input type="color" name="qrmenu_bg_color_text" value="<?php echo esc_attr( $bg_color_text ); ?>"></td>
			</tr>
			<tr>
				<th>Dili adrese ekle</th>
				<td>
					<label>
						<input type="checkbox" name="rma_url_yonlendir" value="1" <?php checked( (bool) get_option( 'rma_ceviri_url_yonlendir', 1 ) ); ?>>
						Ziyaretçi dil seçtiyse adreste göster
					</label>
					<p class="description">Açık tutun — arama motorlarının çevrilmiş sayfaları görmesi buna bağlı.</p>
				</td>
			</tr>
			<tr>
				<th>Tema ve eklenti metinleri</th>
				<td>
					<label>
						<input type="checkbox" name="rma_tampon_acik" value="1" <?php checked( (bool) get_option( 'rma_ceviri_tampon_acik', 1 ) ); ?>>
						Temanın ve diğer eklentilerin bastığı sabit metinleri de çevir
					</label>
					<p class="description">Menü dışındaki metinler (footer, formlar, sayfa içerikleri) için gerekli.</p>
				</td>
			</tr>
		</table>
		<?php
	}
}

if ( ! function_exists( 'qrms_module_qr_ceviri_sayfa_diller' ) ) {

	/**
	 * Diller ekranı.
	 *
	 * @return void
	 */
	function qrms_module_qr_ceviri_sayfa_diller() {
		if ( isset( $_POST['qrms_cv_diller_save'] ) ) {
			rma_ceviri_dilleri_kaydet();
		}

		qrms_module_qr_ceviri_sayfa_ac( 'qrms-cv-diller' );
		qrms_module_qr_ceviri_baslik( 'dashicons-translation', 'Diller', 'h1' );
		?>
		<p class="description qrc-limit">
			Menüde hangi dillerin görüneceğini seçin. Her dilin yanında o dilde
			kayıtlı çeviri sayısı durur.
		</p>
		<form method="POST">
			<?php wp_nonce_field( 'qrms_cv_save_diller', 'qrms_cv_diller_nonce' ); ?>
			<?php rma_ceviri_diller_alanlari(); ?>
			<?php rma_ceviri_gorunum_alanlari(); ?>
			<p class="submit">
				<input type="submit" name="qrms_cv_diller_save" class="button button-primary button-large" value="Dil ayarlarını kaydet">
				<span class="description">Yalnızca bu sayfadaki dil ve görünüm ayarları kaydedilir.</span>
			</p>
		</form>
		<hr>
		<h3>Kullanım kısa kodları</h3>
		<p><b>Sadece bayrak:</b> <code>[qrmenu_flags_only]</code></p>
		<p><b>Bayrak ve dil ismi:</b> <code>[qrmenu_flags_text]</code></p>
		<?php
		qrms_module_qr_ceviri_sayfa_kapat();
	}
}
