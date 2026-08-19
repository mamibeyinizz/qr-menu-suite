<?php
/**
 * Yönetim sayfası: QR Menü → QR Çalışma Saatleri
 *
 * @package QR_Menu_Suite
 */

defined( 'ABSPATH' ) || exit;

/**
 * Form gönderimini işler ve kaydedildiyse true döner.
 *
 * @return bool
 */
function qrms_cs_handle_save() {
	if ( ! isset( $_POST['qrms_cs_kaydet'] ) ) {
		return false;
	}

	if ( ! current_user_can( QRMS_Admin::CAPABILITY ) ) {
		return false;
	}

	check_admin_referer( 'qrms_cs_ayar', 'qrms_cs_nonce' );

	$raw = isset( $_POST['qrms_cs'] ) ? wp_unslash( $_POST['qrms_cs'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- qrms_cs_sanitize temizler.

	update_option( QRMS_CS_OPTION, qrms_cs_sanitize( $raw ) );

	return true;
}

/**
 * Çalışma saatleri yönetim ekranı.
 *
 * @return void
 */
function qrms_cs_admin_sayfasi() {
	if ( ! current_user_can( QRMS_Admin::CAPABILITY ) ) {
		wp_die( esc_html__( 'Bu sayfayı görüntüleme yetkiniz yok.', 'qrms' ) );
	}

	$saved = qrms_cs_handle_save();
	$hours = qrms_cs_get();
	$labels = qrms_cs_day_labels();
	?>
	<div class="wrap qrms-wrap qrms-cs-wrap">
		<h1 class="qrms-title"><?php esc_html_e( 'QR Çalışma Saatleri', 'qrms' ); ?></h1>

		<p class="qrms-muted">
			<?php esc_html_e( 'Restoranın haftanın her günü için açılış ve kapanış saatlerini girin. O gün kapalıysa kutuyu işaretleyin.', 'qrms' ); ?>
		</p>

		<?php if ( $saved ) : ?>
			<div class="qrms-alert qrms-alert-success">
				<p><?php esc_html_e( 'Çalışma saatleri kaydedildi.', 'qrms' ); ?></p>
			</div>
		<?php endif; ?>

		<form method="post" action="" class="qrms-form">
			<?php wp_nonce_field( 'qrms_cs_ayar', 'qrms_cs_nonce' ); ?>

			<?php foreach ( qrms_cs_day_keys() as $key ) : ?>
				<?php
				$day    = $hours[ $key ];
				$closed = ! empty( $day['closed'] );
				?>
				<div class="qrms-card qrms-cs-day" data-day="<?php echo esc_attr( $key ); ?>">
					<div class="qrms-cs-day-head">
						<h2 class="qrms-card-title qrms-cs-day-title"><?php echo esc_html( $labels[ $key ] ); ?></h2>
						<label class="qrms-cs-closed-label">
							<input
								type="checkbox"
								class="qrms-cs-closed"
								name="qrms_cs[<?php echo esc_attr( $key ); ?>][closed]"
								value="1"
								<?php echo $closed ? 'checked="checked"' : ''; ?>
							>
							<?php esc_html_e( 'Kapalı', 'qrms' ); ?>
						</label>
					</div>

					<div class="qrms-cs-times">
						<div class="qrms-field qrms-cs-time-field">
							<label class="qrms-label" for="qrms-cs-<?php echo esc_attr( $key ); ?>-open">
								<?php esc_html_e( 'Açılış', 'qrms' ); ?>
							</label>
							<input
								type="time"
								id="qrms-cs-<?php echo esc_attr( $key ); ?>-open"
								class="qrms-input qrms-cs-open"
								name="qrms_cs[<?php echo esc_attr( $key ); ?>][open]"
								value="<?php echo esc_attr( $day['open'] ); ?>"
								<?php echo $closed ? 'disabled="disabled"' : ''; ?>
							>
						</div>
						<div class="qrms-field qrms-cs-time-field">
							<label class="qrms-label" for="qrms-cs-<?php echo esc_attr( $key ); ?>-close">
								<?php esc_html_e( 'Kapanış', 'qrms' ); ?>
							</label>
							<input
								type="time"
								id="qrms-cs-<?php echo esc_attr( $key ); ?>-close"
								class="qrms-input qrms-cs-close"
								name="qrms_cs[<?php echo esc_attr( $key ); ?>][close]"
								value="<?php echo esc_attr( $day['close'] ); ?>"
								<?php echo $closed ? 'disabled="disabled"' : ''; ?>
							>
						</div>
					</div>
					<p class="qrms-help">
						<?php esc_html_e( 'Kapanış açılıştan küçükse mesai gece yarısını aşar (ör. 18:00–02:00). İki saat eşitse gün boyunca açık kabul edilir.', 'qrms' ); ?>
					</p>
				</div>
			<?php endforeach; ?>

			<button type="submit" name="qrms_cs_kaydet" value="1" class="qrms-button qrms-button-primary">
				<?php esc_html_e( 'Kaydet', 'qrms' ); ?>
			</button>
		</form>

		<div class="qrms-card">
			<h2 class="qrms-card-title"><?php esc_html_e( 'Kısa kod', 'qrms' ); ?></h2>
			<p class="qrms-muted">
				<?php esc_html_e( 'Saatleri menü sayfasında göstermek için:', 'qrms' ); ?>
			</p>
			<p><code>[qr_calisma_saatleri]</code></p>
		</div>
	</div>
	<?php
}
