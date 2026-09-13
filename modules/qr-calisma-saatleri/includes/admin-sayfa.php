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
 * Gün kartındaki durum etiketi (sınıf eki + metin).
 *
 * Dallanma qrms_cs_format_day() ile aynıdır; fark yalnızca aralık dalında:
 * kartta saatin kendisi zaten alanlarda yazdığı için kısaca "Açık" denir.
 * JS aynı üç dalı canlı olarak tekrarlar (bkz. assets/js/admin.js).
 *
 * @param array $day Gün satırı.
 * @return array{0:string,1:string} Sınıf eki ve etiket metni.
 */
function qrms_cs_admin_durum( $day ) {
	$day = is_array( $day ) ? $day : array();

	if ( ! empty( $day['closed'] ) ) {
		return array( 'is-shut', __( 'Kapalı', 'qrms' ) );
	}

	if ( isset( $day['open'], $day['close'] ) && $day['open'] === $day['close'] ) {
		return array( 'is-open', __( '24 saat açık', 'qrms' ) );
	}

	return array( 'is-open', __( 'Açık', 'qrms' ) );
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

	$saved  = qrms_cs_handle_save();
	$hours  = qrms_cs_get();
	$labels = qrms_cs_day_labels();
	$colors = qrms_cs_get_colors();
	$fields = qrms_cs_color_fields();
	?>
	<div class="wrap qrms-wrap qrms-cs-wrap">
		<h1 class="qrms-title"><?php esc_html_e( 'Çalışma Saatleri', 'qrms' ); ?></h1>

		<p class="qrms-muted qrms-cs-lead">
			<?php esc_html_e( 'Restoranınızın haftalık çalışma saatlerini belirleyin. Kapalı olduğunuz günleri işaretleyebilir, gece yarısını aşan çalışma saatlerini de tanımlayabilirsiniz.', 'qrms' ); ?>
		</p>

		<?php if ( $saved ) : ?>
			<div class="qrms-alert qrms-alert-success" role="status">
				<p><?php esc_html_e( 'Çalışma saatleri kaydedildi.', 'qrms' ); ?></p>
			</div>
		<?php endif; ?>

		<form method="post" action="" class="qrms-form qrms-cs-form" id="qrms-cs-form">
			<?php wp_nonce_field( 'qrms_cs_ayar', 'qrms_cs_nonce' ); ?>

			<div class="qrms-cs-layout">
				<div class="qrms-cs-main">

					<?php
					/*
					 * Hızlı işlemler YALNIZCA JS ile çalışır (hazır gün
					 * kartlarını doldurur, sunucuya gitmez). JS kapalıysa
					 * kutu hiç görünmesin: çalışmayan düğme, olmayan
					 * düğmeden kötüdür. Açan satır admin.js içindedir.
					 */
					?>
					<div class="qrms-card qrms-cs-quick" id="qrms-cs-quick" hidden>
						<h2 class="qrms-card-title"><?php esc_html_e( 'Hızlı işlemler', 'qrms' ); ?></h2>
						<p class="qrms-help qrms-cs-quick-help">
							<?php esc_html_e( 'Seçtiğiniz günün saatleri diğer günlere kopyalanır. Kaydetmeden önce geri alabilirsiniz.', 'qrms' ); ?>
						</p>

						<div class="qrms-field qrms-cs-quick-source">
							<label class="qrms-label" for="qrms-cs-quick-day"><?php esc_html_e( 'Kaynak gün', 'qrms' ); ?></label>
							<select id="qrms-cs-quick-day" class="qrms-input">
								<?php foreach ( qrms_cs_day_keys() as $key ) : ?>
									<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $labels[ $key ] ); ?></option>
								<?php endforeach; ?>
							</select>
						</div>

						<div class="qrms-cs-quick-actions">
							<button type="button" class="qrms-cs-chip" data-qrms-cs-action="weekdays">
								<?php esc_html_e( 'Hafta içine uygula', 'qrms' ); ?>
							</button>
							<button type="button" class="qrms-cs-chip" data-qrms-cs-action="weekend">
								<?php esc_html_e( 'Hafta sonuna uygula', 'qrms' ); ?>
							</button>
							<button type="button" class="qrms-cs-chip" data-qrms-cs-action="all">
								<?php esc_html_e( 'Tüm günlere kopyala', 'qrms' ); ?>
							</button>
							<button type="button" class="qrms-cs-chip" data-qrms-cs-action="open-all">
								<?php esc_html_e( 'Tüm günleri aç', 'qrms' ); ?>
							</button>
							<button type="button" class="qrms-cs-chip qrms-cs-chip-warn" data-qrms-cs-action="close-all">
								<?php esc_html_e( 'Tüm günleri kapat', 'qrms' ); ?>
							</button>
							<button type="button" class="qrms-cs-chip qrms-cs-chip-undo" data-qrms-cs-action="undo" id="qrms-cs-undo" hidden>
								<?php esc_html_e( 'Geri al', 'qrms' ); ?>
							</button>
						</div>

						<p class="qrms-cs-toast" id="qrms-cs-toast" role="status" aria-live="polite" hidden></p>
					</div>

					<div class="qrms-card qrms-cs-days">
						<h2 class="qrms-card-title"><?php esc_html_e( 'Haftalık plan', 'qrms' ); ?></h2>
						<p class="qrms-help qrms-cs-days-help">
							<?php esc_html_e( 'Kapanış saati açılıştan önceyse çalışma süresi ertesi güne taşar. Örneğin 18:00 – 02:00.', 'qrms' ); ?>
							<br>
							<?php esc_html_e( 'Açılış ve kapanış saatleri aynıysa restoran 24 saat açık kabul edilir.', 'qrms' ); ?>
						</p>

						<div class="qrms-cs-day-list">
							<?php foreach ( qrms_cs_day_keys() as $key ) : ?>
								<?php
								$day              = $hours[ $key ];
								$closed           = ! empty( $day['closed'] );
								list( $dc, $dt )  = qrms_cs_admin_durum( $day );
								$card_class       = 'qrms-cs-day' . ( $closed ? ' is-closed' : '' );
								?>
								<div class="<?php echo esc_attr( $card_class ); ?>" data-day="<?php echo esc_attr( $key ); ?>">
									<div class="qrms-cs-day-head">
										<div class="qrms-cs-day-name">
											<span class="qrms-cs-day-title"><?php echo esc_html( $labels[ $key ] ); ?></span>
											<span class="qrms-cs-state <?php echo esc_attr( $dc ); ?>">
												<span class="qrms-cs-state-dot" aria-hidden="true"></span>
												<span class="qrms-cs-state-text"><?php echo esc_html( $dt ); ?></span>
											</span>
										</div>

										<?php // Anahtar (switch) görünümlü onay kutusu; işaretliyken gün kapalıdır. ?>
										<label class="qrms-cs-switch">
											<input
												type="checkbox"
												class="qrms-cs-closed"
												name="qrms_cs[<?php echo esc_attr( $key ); ?>][closed]"
												value="1"
												<?php echo $closed ? 'checked="checked"' : ''; ?>
											>
											<span class="qrms-cs-switch-track" aria-hidden="true"></span>
											<span class="qrms-cs-switch-text"><?php esc_html_e( 'Kapalı', 'qrms' ); ?></span>
										</label>
									</div>

									<div class="qrms-cs-times">
										<div class="qrms-field qrms-cs-time-field">
											<label class="qrms-label" for="qrms-cs-<?php echo esc_attr( $key ); ?>-open">
												<?php esc_html_e( 'Açılış saati', 'qrms' ); ?>
											</label>
											<input
												type="time"
												id="qrms-cs-<?php echo esc_attr( $key ); ?>-open"
												class="qrms-input qrms-cs-open"
												name="qrms_cs[<?php echo esc_attr( $key ); ?>][open]"
												value="<?php echo esc_attr( $day['open'] ); ?>"
											>
										</div>
										<div class="qrms-field qrms-cs-time-field">
											<label class="qrms-label" for="qrms-cs-<?php echo esc_attr( $key ); ?>-close">
												<?php esc_html_e( 'Kapanış saati', 'qrms' ); ?>
											</label>
											<input
												type="time"
												id="qrms-cs-<?php echo esc_attr( $key ); ?>-close"
												class="qrms-input qrms-cs-close"
												name="qrms_cs[<?php echo esc_attr( $key ); ?>][close]"
												value="<?php echo esc_attr( $day['close'] ); ?>"
											>
										</div>
									</div>
								</div>
							<?php endforeach; ?>
						</div>
					</div>

					<div class="qrms-card qrms-cs-colors">
						<h2 class="qrms-card-title"><?php esc_html_e( 'Liste görünümü', 'qrms' ); ?></h2>
						<p class="qrms-muted">
							<?php esc_html_e( 'Çalışma saatleri listesinin renklerini ve yazı tipini özelleştirin. Değişiklikler canlı önizlemede anında görüntülenir.', 'qrms' ); ?>
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
							<label class="qrms-label" for="qrms-cs-renk-font"><?php esc_html_e( 'Yazı tipi', 'qrms' ); ?></label>
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
								<?php esc_html_e( 'Georgia, serif ve sans-serif sistem yazı tipidir; diğerleri Google Fonts üzerinden yüklenir.', 'qrms' ); ?>
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
				</div>

				<div class="qrms-cs-side">
					<div class="qrms-card qrms-cs-preview-card">
						<h2 class="qrms-card-title"><?php esc_html_e( 'Canlı önizleme', 'qrms' ); ?></h2>
						<p class="qrms-help">
							<?php esc_html_e( 'Müşterinin göreceği liste. Değişiklikler kaydetmeden burada görünür.', 'qrms' ); ?>
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
				</div>
			</div>

			<div class="qrms-cs-save">
				<button type="submit" name="qrms_cs_kaydet" value="1" class="qrms-button qrms-button-primary qrms-cs-save-button">
					<?php esc_html_e( 'Değişiklikleri kaydet', 'qrms' ); ?>
				</button>
				<p class="qrms-cs-dirty" id="qrms-cs-dirty" role="status" hidden>
					<?php esc_html_e( 'Kaydedilmemiş değişiklikleriniz var.', 'qrms' ); ?>
				</p>
			</div>
		</form>
	</div>
	<?php
}
