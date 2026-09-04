<?php
/**
 * Malzeme Fiyatları ekranı.
 *
 * Malzeme taksonomisinin terimleri listelenir; her biri için birim (kg / lt /
 * adet) ve o birimin fiyatı girilir. Kaydedildiğinde reçete tabanlı ürün
 * maliyetleri yeniden hesaplanır.
 *
 * @package QR_Menu_Suite
 */

defined( 'ABSPATH' ) || exit;

/**
 * Ekranı basar.
 *
 * @return void
 */
function qrms_mm_malzeme_sayfasi() {
	if ( ! current_user_can( QRMS_Admin::CAPABILITY ) ) {
		wp_die( esc_html__( 'Bu sayfayı görüntüleme yetkiniz yok.', 'qrms' ) );
	}

	$sonuc = qrms_mm_malzeme_kaydet();

	$malzemeler = qrms_mm_malzeme_listesi();
	$fiyatlar   = QRMS_MM_Maliyet::malzeme_fiyatlari();
	$birimler   = QRMS_MM_Maliyet::birimler();
	?>
	<div class="wrap qrms-wrap qrms-mm">
		<h1 class="qrms-title"><?php esc_html_e( 'Malzeme Fiyatları', 'qrms' ); ?></h1>

		<?php if ( is_array( $sonuc ) ) : ?>
			<div class="qrms-alert qrms-alert-success">
				<p>
					<?php
					printf(
						/* translators: 1: kaydedilen malzeme sayısı, 2: yeniden hesaplanan ürün sayısı. */
						esc_html__( '%1$d malzeme fiyatı kaydedildi, %2$d reçeteli ürünün maliyeti yeniden hesaplandı.', 'qrms' ),
						(int) $sonuc['malzeme'],
						(int) $sonuc['urun']
					);
					?>
					<?php if ( $sonuc['arka_plan'] ) : ?>
						<?php esc_html_e( 'Ürün sayısı fazla olduğu için yeniden hesaplama arka planda sürüyor.', 'qrms' ); ?>
					<?php endif; ?>
				</p>
			</div>
		<?php endif; ?>

		<p class="qrms-muted">
			<?php esc_html_e( 'Fiyatı girilmeyen malzeme reçete hesabında sıfır sayılır. Birimi seçtiğinizde reçetede hangi ölçüyle yazacağınız yanında görünür.', 'qrms' ); ?>
		</p>

		<?php if ( empty( $malzemeler ) ) : ?>
			<div class="qrms-card qrms-mm-bos">
				<span class="dashicons dashicons-carrot" aria-hidden="true"></span>
				<h2><?php esc_html_e( 'Henüz malzeme yok', 'qrms' ); ?></h2>
				<p><?php esc_html_e( 'Malzemeler, Restoran Menü modülündeki ürün düzenleme ekranında "Malzemeler" kutusundan eklenir. Ürünlere malzeme etiketledikçe burada görünürler.', 'qrms' ); ?></p>
			</div>
		<?php else : ?>
			<form method="post">
				<?php wp_nonce_field( 'qrms_mm_malzeme' ); ?>

				<div class="qrms-mm-tablo-kap">
					<table class="widefat striped">
						<thead>
							<tr>
								<th scope="col"><?php esc_html_e( 'Malzeme', 'qrms' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Birim', 'qrms' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Birim fiyatı (₺)', 'qrms' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Reçetede yazılacak ölçü', 'qrms' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $malzemeler as $term_id => $ad ) : ?>
								<?php
								$birim = isset( $fiyatlar[ $term_id ]['birim'] ) ? $fiyatlar[ $term_id ]['birim'] : 'kg';
								$fiyat = isset( $fiyatlar[ $term_id ]['fiyat'] ) ? $fiyatlar[ $term_id ]['fiyat'] : '';
								?>
								<tr>
									<td data-etiket="<?php esc_attr_e( 'Malzeme', 'qrms' ); ?>"><?php echo esc_html( $ad ); ?></td>
									<td data-etiket="<?php esc_attr_e( 'Birim', 'qrms' ); ?>">
										<select name="malzeme[<?php echo esc_attr( $term_id ); ?>][birim]" class="qrms-mm-birim" aria-label="<?php esc_attr_e( 'Birim', 'qrms' ); ?>">
											<?php foreach ( $birimler as $anahtar => $b ) : ?>
												<option value="<?php echo esc_attr( $anahtar ); ?>" <?php selected( $birim, $anahtar ); ?> data-miktar="<?php echo esc_attr( $b['miktar'] ); ?>">
													<?php echo esc_html( $b['ad'] ); ?>
												</option>
											<?php endforeach; ?>
										</select>
									</td>
									<td data-etiket="<?php esc_attr_e( 'Birim fiyatı (₺)', 'qrms' ); ?>">
										<input type="text" inputmode="decimal" name="malzeme[<?php echo esc_attr( $term_id ); ?>][fiyat]" value="<?php echo esc_attr( $fiyat ); ?>" aria-label="<?php esc_attr_e( 'Birim fiyatı', 'qrms' ); ?>">
									</td>
									<td data-etiket="<?php esc_attr_e( 'Reçetede yazılacak ölçü', 'qrms' ); ?>" class="qrms-mm-olcu">
										<?php echo esc_html( isset( $birimler[ $birim ] ) ? $birimler[ $birim ]['miktar'] : '' ); ?>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>

				<p>
					<button type="submit" name="qrms_mm_malzeme_kaydet" value="1" class="button button-primary">
						<?php esc_html_e( 'Fiyatları Kaydet', 'qrms' ); ?>
					</button>
				</p>
			</form>
		<?php endif; ?>
	</div>
	<?php
}

/**
 * Malzeme formunu işler.
 *
 * @return array|null Kaydedildiyse özet, aksi hâlde null.
 */
function qrms_mm_malzeme_kaydet() {
	// phpcs:ignore WordPress.Security.NonceVerification.Missing
	if ( empty( $_POST['qrms_mm_malzeme_kaydet'] ) ) {
		return null;
	}

	check_admin_referer( 'qrms_mm_malzeme' );

	if ( ! current_user_can( QRMS_Admin::CAPABILITY ) ) {
		wp_die( esc_html__( 'Bu işlem için yetkiniz yok.', 'qrms' ) );
	}

	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- malzeme_temizle() alan alan temizler.
	$ham = isset( $_POST['malzeme'] ) && is_array( $_POST['malzeme'] ) ? wp_unslash( $_POST['malzeme'] ) : array();

	$temiz = QRMS_MM_Maliyet::malzeme_temizle( $ham );

	update_option( QRMS_MM_Maliyet::OPTION_MALZEME, $temiz );

	$urun      = QRMS_MM_Maliyet::receteleri_yenile();
	$arka_plan = ( -1 === $urun );

	QRMS_MM_Maliyet::onbellek_temizle();

	return array(
		'malzeme'   => count( $temiz ),
		'urun'      => max( 0, $urun ),
		'arka_plan' => $arka_plan,
	);
}
