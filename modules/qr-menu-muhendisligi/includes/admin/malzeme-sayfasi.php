<?php
/**
 * Malzeme fiyatları sayfası.
 *
 * @package QR_Menu_Suite
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'qrms_mm_malzeme_sayfasi' ) ) {
	/**
	 * Malzeme fiyat ekranını basar.
	 *
	 * @return void
	 */
	function qrms_mm_malzeme_sayfasi() {
		if ( ! current_user_can( QRMS_Admin::CAPABILITY ) ) {
			wp_die( esc_html__( 'Bu sayfayı görüntüleme yetkiniz yok.', 'qrms' ) );
		}

		$fiyatlar = QRMS_MM_Maliyet::malzeme_fiyatlari();
		$terms    = array();

		if ( class_exists( 'RMA_Ingredient_Taxonomy' ) ) {
			$raw = get_terms(
				array(
					'taxonomy'   => RMA_Ingredient_Taxonomy::TAXONOMY,
					'hide_empty' => false,
				)
			);
			if ( ! is_wp_error( $raw ) ) {
				$terms = $raw;
			}
		}
		?>
		<div class="wrap qrms-wrap qrms-mm-wrap">
			<h1 class="qrms-title"><?php esc_html_e( 'Malzeme Fiyatları', 'qrms' ); ?></h1>

			<?php if ( empty( $terms ) ) : ?>
				<div class="qrms-card">
					<p><?php esc_html_e( 'Henüz malzeme tanımlanmamış. Restoran Menü → Malzemeler bölümünden ekleyin.', 'qrms' ); ?></p>
				</div>
			<?php else : ?>
				<form id="qrms-mm-malzeme-form">
					<div class="qrms-mm-tablo-wrap">
						<table class="widefat striped">
							<thead>
								<tr>
									<th><?php esc_html_e( 'Malzeme', 'qrms' ); ?></th>
									<th><?php esc_html_e( 'Birim', 'qrms' ); ?></th>
									<th><?php esc_html_e( 'Birim fiyat (₺)', 'qrms' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $terms as $t ) :
									$mf = isset( $fiyatlar[ $t->term_id ] ) ? $fiyatlar[ $t->term_id ] : array( 'birim' => 'kg', 'fiyat' => 0 );
									?>
									<tr>
										<td data-label="<?php esc_attr_e( 'Malzeme', 'qrms' ); ?>"><?php echo esc_html( $t->name ); ?></td>
										<td data-label="<?php esc_attr_e( 'Birim', 'qrms' ); ?>">
											<select name="fiyatlar[<?php echo esc_attr( (string) $t->term_id ); ?>][birim]">
												<option value="kg" <?php selected( $mf['birim'], 'kg' ); ?>>kg</option>
												<option value="lt" <?php selected( $mf['birim'], 'lt' ); ?>>lt</option>
												<option value="adet" <?php selected( $mf['birim'], 'adet' ); ?>>adet</option>
											</select>
										</td>
										<td data-label="<?php esc_attr_e( 'Birim fiyat', 'qrms' ); ?>">
											<input type="number" step="0.01" min="0" name="fiyatlar[<?php echo esc_attr( (string) $t->term_id ); ?>][fiyat]" value="<?php echo esc_attr( (string) (float) $mf['fiyat'] ); ?>" />
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
					<p><button type="submit" class="button button-primary"><?php esc_html_e( 'Toplu kaydet', 'qrms' ); ?></button></p>
				</form>
			<?php endif; ?>
		</div>
		<?php
	}
}
