<?php
/**
 * Menü mühendisliği ayarlar sayfası.
 *
 * @package QR_Menu_Suite
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'qrms_mm_ayarlar_sayfasi' ) ) {
	/**
	 * Ayarlar ekranını basar.
	 *
	 * @return void
	 */
	function qrms_mm_ayarlar_sayfasi() {
		if ( ! current_user_can( QRMS_Admin::CAPABILITY ) ) {
			wp_die( esc_html__( 'Bu sayfayı görüntüleme yetkiniz yok.', 'qrms' ) );
		}

		$ayarlar = QRMS_MM_Maliyet::ayarlar();
		?>
		<div class="wrap qrms-wrap qrms-mm-wrap">
			<h1 class="qrms-title"><?php esc_html_e( 'Menü Mühendisliği Ayarları', 'qrms' ); ?></h1>

			<form id="qrms-mm-ayarlar-form" class="qrms-card">
				<table class="form-table">
					<tr>
						<th><label for="populerlik_esigi"><?php esc_html_e( 'Popülerlik eşiği', 'qrms' ); ?></label></th>
						<td><input type="number" id="populerlik_esigi" name="populerlik_esigi" min="0.5" max="1" step="0.05" value="<?php echo esc_attr( (string) $ayarlar['populerlik_esigi'] ); ?>" /></td>
					</tr>
					<tr>
						<th><label for="fire_yuzdesi"><?php esc_html_e( 'Fire yüzdesi', 'qrms' ); ?></label></th>
						<td><input type="number" id="fire_yuzdesi" name="fire_yuzdesi" min="0" max="100" step="0.1" value="<?php echo esc_attr( (string) $ayarlar['fire_yuzdesi'] ); ?>" /> %</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'KDV', 'qrms' ); ?></th>
						<td><label><input type="checkbox" name="kdv_dahil" value="1" <?php checked( ! empty( $ayarlar['kdv_dahil'] ) ); ?> /> <?php esc_html_e( 'Fiyatlar KDV dahil', 'qrms' ); ?></label></td>
					</tr>
					<tr>
						<th><label for="varsayilan_aralik"><?php esc_html_e( 'Varsayılan aralık (gün)', 'qrms' ); ?></label></th>
						<td><input type="number" id="varsayilan_aralik" name="varsayilan_aralik" min="7" max="365" value="<?php echo esc_attr( (string) $ayarlar['varsayilan_aralik'] ); ?>" /></td>
					</tr>
				</table>
				<p><button type="submit" class="button button-primary"><?php esc_html_e( 'Kaydet', 'qrms' ); ?></button></p>
			</form>
		</div>
		<?php
	}
}
