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

	// Renkler ayrı option'da durur (bkz. includes/renkler.php), ama aynı
	// formdan gelir: restoran sahibi için tek bir "Kaydet" vardır.
	$renkler = isset( $_POST['qrms_cs_renk'] ) ? wp_unslash( $_POST['qrms_cs_renk'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- qrms_cs_sanitize_colors temizler.

	update_option( QRMS_CS_COLORS_OPTION, qrms_cs_sanitize_colors( $renkler ) );

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

	$saved   = qrms_cs_handle_save();
	$hours   = qrms_cs_get();
	$labels  = qrms_cs_day_labels();
	$colors  = qrms_cs_get_colors();
	$fields  = qrms_cs_color_fields();
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

			<div class="qrms-card qrms-cs-colors">
				<h2 class="qrms-card-title"><?php esc_html_e( 'Görünüm', 'qrms' ); ?></h2>
				<p class="qrms-muted">
					<?php esc_html_e( 'Boş bıraktığınız renk ve yazı tipi temanızdan devralınır. Aşağıdaki önizleme kaydetmeden güncellenir.', 'qrms' ); ?>
				</p>

				<?php
				/*
				 * Yazı tipi listesi Restoran Menü'nün Görünüm sayfasındakiyle
				 * BİREBİR aynıdır (bkz. qrms_cs_font_options): restoran sahibi
				 * iki ekranda farklı listeler görüp hangisini seçtiğini
				 * karıştırmasın.
				 */
				$font = isset( $colors['font'] ) ? $colors['font'] : '';
				?>
				<div class="qrms-field qrms-cs-font-field">
					<label class="qrms-label" for="qrms-cs-renk-font"><?php esc_html_e( 'Yazı fontu', 'qrms' ); ?></label>
					<select
						id="qrms-cs-renk-font"
						class="qrms-input qrms-cs-font-picker"
						name="qrms_cs_renk[font]"
						data-css-var="<?php echo esc_attr( QRMS_CS_FONT_VAR ); ?>"
					>
						<option value=""<?php selected( '', $font ); ?>><?php esc_html_e( 'Temadan devral', 'qrms' ); ?></option>
						<?php foreach ( qrms_cs_font_options() as $secenek ) : ?>
							<option
								value="<?php echo esc_attr( $secenek ); ?>"
								data-family="<?php echo esc_attr( qrms_cs_font_family( $secenek ) ); ?>"
								data-google="<?php echo esc_attr( qrms_cs_google_font_url( $secenek ) ); ?>"
								<?php selected( $secenek, $font ); ?>
							>
								<?php echo esc_html( $secenek ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<p class="qrms-help">
						<?php esc_html_e( 'Listedeki adlandırılmış yazı tipleri ön yüzde Google Fonts üzerinden yüklenir; Georgia, serif ve sans-serif sistem yazı tipidir, dış istek yapılmaz.', 'qrms' ); ?>
					</p>
				</div>

				<div class="qrms-cs-color-grid">
					<?php foreach ( $fields as $key => $field ) : ?>
						<div class="qrms-field qrms-cs-color-field">
							<label class="qrms-label" for="qrms-cs-renk-<?php echo esc_attr( $key ); ?>">
								<?php echo esc_html( $field['label'] ); ?>
							</label>
							<input
								type="text"
								id="qrms-cs-renk-<?php echo esc_attr( $key ); ?>"
								class="qrms-cs-color-picker"
								name="qrms_cs_renk[<?php echo esc_attr( $key ); ?>]"
								value="<?php echo esc_attr( isset( $colors[ $key ] ) ? $colors[ $key ] : '' ); ?>"
								data-default-color="<?php echo esc_attr( $field['fallback'] ); ?>"
								data-css-var="<?php echo esc_attr( $field['var'] ); ?>"
							>
							<p class="qrms-help"><?php echo esc_html( $field['desc'] ); ?></p>
						</div>
					<?php endforeach; ?>
				</div>
			</div>

			<button type="submit" name="qrms_cs_kaydet" value="1" class="qrms-button qrms-button-primary">
				<?php esc_html_e( 'Kaydet', 'qrms' ); ?>
			</button>
		</form>

		<div class="qrms-card qrms-cs-preview-card">
			<h2 class="qrms-card-title"><?php esc_html_e( 'Canlı Önizleme', 'qrms' ); ?></h2>
			<p class="qrms-muted">
				<?php esc_html_e( 'Müşterinin sayfada göreceği liste. Saat, kapalı gün, renk ve yazı tipi değişiklikleri kaydetmeden burada görünür.', 'qrms' ); ?>
			</p>
			<?php
			/*
			 * Önizleme kısa kodun TAKLİDİ DEĞİL, kendisidir: aynı fonksiyon,
			 * aynı stylesheet. Ayrı bir şablon tutulsaydı ikisi zamanla
			 * ayrışır ve önizleme yalan söylemeye başlardı.
			 */
			?>
			<div class="qrms-cs-preview" id="qrms-cs-preview">
				<?php echo qrms_cs_shortcode( array() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kısa kod kendi çıktısını kaçırır. ?>
			</div>
		</div>

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
