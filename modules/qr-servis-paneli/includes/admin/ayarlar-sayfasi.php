<?php
/**
 * Servis paneli ayarlar sayfası.
 *
 * @package QR_Menu_Suite
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'qrms_sp_ayarlar_sayfasi' ) ) {
	/**
	 * Ayarlar ekranını basar.
	 *
	 * @return void
	 */
	function qrms_sp_ayarlar_sayfasi() {
		if ( ! current_user_can( QRMS_Admin::CAPABILITY ) ) {
			wp_die( esc_html__( 'Bu sayfayı görüntüleme yetkiniz yok.', 'qrms' ) );
		}

		$ayarlar = QRMS_SP_Veri::ayarlar();
		$hazir   = class_exists( 'QMO_Firestore' ) && QMO_Firestore::hazir_mi();
		?>
		<div class="wrap qrms-wrap qrms-sp-wrap">
			<h1 class="qrms-title"><?php esc_html_e( 'Servis Paneli Ayarları', 'qrms' ); ?></h1>

			<div class="qrms-card">
				<h2><?php esc_html_e( 'Firebase durumu', 'qrms' ); ?></h2>
				<p>
					<?php if ( $hazir ) : ?>
						<span class="dashicons dashicons-yes-alt" style="color:#1f9d55"></span> <?php esc_html_e( 'Yapılandırılmış', 'qrms' ); ?>
					<?php else : ?>
						<span class="dashicons dashicons-warning" style="color:#c0392b"></span> <?php esc_html_e( 'Yapılandırılmamış', 'qrms' ); ?>
						— <a href="<?php echo esc_url( admin_url( 'admin.php?page=qrms-analiz-ayarlar' ) ); ?>"><?php esc_html_e( 'Firebase ayarlarına git', 'qrms' ); ?></a>
					<?php endif; ?>
				</p>
			</div>

			<form id="qrms-sp-ayarlar-form" class="qrms-card">
				<table class="form-table">
					<tr>
						<th><?php esc_html_e( 'Ses varsayılanı', 'qrms' ); ?></th>
						<td><label><input type="checkbox" name="ses_acik" value="1" <?php checked( ! empty( $ayarlar['ses_acik'] ) ); ?> /> <?php esc_html_e( 'Açık', 'qrms' ); ?></label></td>
					</tr>
					<tr>
						<th><label for="esik_sari"><?php esc_html_e( 'Sarı eşik (dk)', 'qrms' ); ?></label></th>
						<td><input type="number" id="esik_sari" name="esik_sari" min="1" value="<?php echo esc_attr( (string) $ayarlar['esik_sari'] ); ?>" /></td>
					</tr>
					<tr>
						<th><label for="esik_kirmizi"><?php esc_html_e( 'Kırmızı eşik (dk)', 'qrms' ); ?></label></th>
						<td><input type="number" id="esik_kirmizi" name="esik_kirmizi" min="1" value="<?php echo esc_attr( (string) $ayarlar['esik_kirmizi'] ); ?>" /></td>
					</tr>
					<tr>
						<th><label for="otomatik_tamam"><?php esc_html_e( 'Otomatik tamamlama (dk)', 'qrms' ); ?></label></th>
						<td><input type="number" id="otomatik_tamam" name="otomatik_tamam" min="30" value="<?php echo esc_attr( (string) $ayarlar['otomatik_tamam'] ); ?>" /></td>
					</tr>
					<tr>
						<th><label for="yenileme_araligi"><?php esc_html_e( 'Yenileme aralığı (sn)', 'qrms' ); ?></label></th>
						<td><input type="number" id="yenileme_araligi" name="yenileme_araligi" min="3" max="60" value="<?php echo esc_attr( (string) $ayarlar['yenileme_araligi'] ); ?>" /></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Gösterilecek tipler', 'qrms' ); ?></th>
						<td>
							<?php foreach ( array( 'siparis' => __( 'Sipariş', 'qrms' ), 'garson' => __( 'Garson', 'qrms' ), 'hesap' => __( 'Hesap', 'qrms' ) ) as $tip => $etiket ) : ?>
								<label style="margin-right:12px"><input type="checkbox" name="tipler[]" value="<?php echo esc_attr( $tip ); ?>" <?php checked( in_array( $tip, $ayarlar['tipler'], true ) ); ?> /> <?php echo esc_html( $etiket ); ?></label>
							<?php endforeach; ?>
						</td>
					</tr>
				</table>
				<p><button type="submit" class="button button-primary"><?php esc_html_e( 'Kaydet', 'qrms' ); ?></button></p>
			</form>
		</div>
		<?php
	}
}
