<?php
/**
 * Menü Mühendisliği ayarları.
 *
 * @package QR_Menu_Suite
 */

defined( 'ABSPATH' ) || exit;

/**
 * Ekranı basar.
 *
 * @return void
 */
function qrms_mm_ayarlar_sayfasi() {
	if ( ! current_user_can( QRMS_Admin::CAPABILITY ) ) {
		wp_die( esc_html__( 'Bu sayfayı görüntüleme yetkiniz yok.', 'qrms' ) );
	}

	$kaydedildi = qrms_mm_ayar_kaydet();
	$ayar       = QRMS_MM_Maliyet::ayarlar();
	?>
	<div class="wrap qrms-wrap qrms-mm">
		<h1 class="qrms-title"><?php esc_html_e( 'Menü Mühendisliği Ayarları', 'qrms' ); ?></h1>

		<?php if ( $kaydedildi ) : ?>
			<div class="qrms-alert qrms-alert-success">
				<p><?php esc_html_e( 'Ayarlar kaydedildi. Rapor önbelleği temizlendi.', 'qrms' ); ?></p>
			</div>
		<?php endif; ?>

		<form method="post">
			<?php wp_nonce_field( 'qrms_mm_ayar' ); ?>

			<div class="qrms-card">
				<h2 class="qrms-card-title"><?php esc_html_e( 'Popülerlik eşiği', 'qrms' ); ?></h2>

				<p class="qrms-muted">
					<?php esc_html_e( 'Bir ürünün "çok satan" sayılması için gereken menü payı katsayısı. Klasik değer 0,70\'tir: eşit dağılımın %70\'i kadar satan ürün popüler kabul edilir. Yükseltirseniz Yıldız ve İş Atı sayısı azalır.', 'qrms' ); ?>
				</p>

				<div class="qrms-field">
					<label for="qrms-mm-esik">
						<?php esc_html_e( 'Katsayı (0,50 – 1,00)', 'qrms' ); ?>
						<span class="qrms-deger" data-icin="qrms-mm-esik"><?php echo esc_html( number_format_i18n( $ayar['populerlik_esigi'], 2 ) ); ?></span>
					</label>
					<input type="range" id="qrms-mm-esik" name="populerlik_esigi" min="0.5" max="1" step="0.05" value="<?php echo esc_attr( $ayar['populerlik_esigi'] ); ?>">
				</div>
			</div>

			<div class="qrms-card">
				<h2 class="qrms-card-title"><?php esc_html_e( 'Fire yüzdesi', 'qrms' ); ?></h2>

				<p class="qrms-muted">
					<?php esc_html_e( 'Reçeteden hesaplanan maliyete eklenir: hazırlık sırasındaki kayıp, atık ve porsiyon sapması. Yalnızca reçeteli ürünleri etkiler.', 'qrms' ); ?>
				</p>

				<div class="qrms-field">
					<label for="qrms-mm-fire">
						<?php esc_html_e( 'Fire (%)', 'qrms' ); ?>
						<span class="qrms-deger" data-icin="qrms-mm-fire"><?php echo esc_html( $ayar['fire_yuzdesi'] ); ?>%</span>
					</label>
					<input type="range" id="qrms-mm-fire" name="fire_yuzdesi" min="0" max="50" step="1" value="<?php echo esc_attr( $ayar['fire_yuzdesi'] ); ?>">
				</div>
			</div>

			<div class="qrms-card">
				<h2 class="qrms-card-title"><?php esc_html_e( 'Varsayılan rapor aralığı', 'qrms' ); ?></h2>

				<div class="qrms-field">
					<div class="qrms-secim">
						<?php foreach ( QRMS_MM_Rapor::araliklar() as $gun => $etiket ) : ?>
							<label class="qrms-secim-secenek">
								<input type="radio" name="varsayilan_aralik" value="<?php echo esc_attr( $gun ); ?>" <?php checked( $ayar['varsayilan_aralik'], $gun ); ?>>
								<span><?php echo esc_html( $etiket ); ?></span>
							</label>
						<?php endforeach; ?>
					</div>
				</div>
			</div>

			<p>
				<button type="submit" name="qrms_mm_ayar_kaydet" value="1" class="button button-primary">
					<?php esc_html_e( 'Ayarları Kaydet', 'qrms' ); ?>
				</button>
			</p>
		</form>
	</div>
	<?php
}

/**
 * Ayar formunu işler.
 *
 * @return bool Kaydedildi mi?
 */
function qrms_mm_ayar_kaydet() {
	// phpcs:ignore WordPress.Security.NonceVerification.Missing
	if ( empty( $_POST['qrms_mm_ayar_kaydet'] ) ) {
		return false;
	}

	check_admin_referer( 'qrms_mm_ayar' );

	if ( ! current_user_can( QRMS_Admin::CAPABILITY ) ) {
		wp_die( esc_html__( 'Bu işlem için yetkiniz yok.', 'qrms' ) );
	}

	// phpcs:disable WordPress.Security.NonceVerification.Missing -- nonce yukarıda.
	$ham = array(
		'populerlik_esigi'  => isset( $_POST['populerlik_esigi'] ) ? sanitize_text_field( wp_unslash( $_POST['populerlik_esigi'] ) ) : '',
		'fire_yuzdesi'      => isset( $_POST['fire_yuzdesi'] ) ? absint( $_POST['fire_yuzdesi'] ) : 0,
		'varsayilan_aralik' => isset( $_POST['varsayilan_aralik'] ) ? absint( $_POST['varsayilan_aralik'] ) : 30,
	);
	// phpcs:enable

	$eski = QRMS_MM_Maliyet::ayarlar();
	$yeni = QRMS_MM_Maliyet::ayar_temizle( $ham );

	update_option( QRMS_MM_Maliyet::OPTION_AYAR, $yeni );

	// Fire değiştiyse reçeteli maliyetler artık yanlış; yeniden hesaplanır.
	if ( (int) $eski['fire_yuzdesi'] !== (int) $yeni['fire_yuzdesi'] ) {
		QRMS_MM_Maliyet::receteleri_yenile();
	}

	QRMS_MM_Maliyet::onbellek_temizle();

	return true;
}
