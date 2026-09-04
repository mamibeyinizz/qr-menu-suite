<?php
/**
 * Servis Paneli ayarları.
 *
 * @package QR_Menu_Suite
 */

defined( 'ABSPATH' ) || exit;

/**
 * Ekranı basar.
 *
 * @return void
 */
function qrms_sp_ayarlar_sayfasi() {
	if ( ! current_user_can( QRMS_Admin::CAPABILITY ) ) {
		wp_die( esc_html__( 'Bu sayfayı görüntüleme yetkiniz yok.', 'qrms' ) );
	}

	$kaydedildi = qrms_sp_ayar_kaydet();
	$ayar       = QRMS_SP_Veri::ayarlar();
	$hazir      = QRMS_SP_Veri::hazir_mi();
	?>
	<div class="wrap qrms-wrap qrms-sp">
		<h1 class="qrms-title"><?php esc_html_e( 'Servis Paneli Ayarları', 'qrms' ); ?></h1>

		<?php if ( $kaydedildi ) : ?>
			<div class="qrms-alert qrms-alert-success">
				<p><?php esc_html_e( 'Ayarlar kaydedildi.', 'qrms' ); ?></p>
			</div>
		<?php endif; ?>

		<div class="qrms-card">
			<h2 class="qrms-card-title"><?php esc_html_e( 'Bağlantı Durumu', 'qrms' ); ?></h2>

			<?php if ( $hazir ) : ?>
				<p class="qrms-sp-durum-ok">
					<span class="dashicons dashicons-yes-alt" aria-hidden="true"></span>
					<?php esc_html_e( 'Firebase yapılandırılmış; panel canlı veri alıyor.', 'qrms' ); ?>
				</p>
			<?php else : ?>
				<p class="qrms-sp-durum-eksik">
					<span class="dashicons dashicons-warning" aria-hidden="true"></span>
					<?php esc_html_e( 'Firebase yapılandırılmamış. Service account ve şube kimliği girilene kadar panel boş kalır.', 'qrms' ); ?>
				</p>
				<p>
					<a class="button" href="<?php echo esc_url( add_query_arg( 'page', 'qrms-analiz-ayarlar', admin_url( 'admin.php' ) ) ); ?>">
						<?php esc_html_e( 'Firebase & Şube Ayarları', 'qrms' ); ?>
					</a>
				</p>
			<?php endif; ?>
		</div>

		<form method="post">
			<?php wp_nonce_field( 'qrms_sp_ayar' ); ?>

			<div class="qrms-card">
				<h2 class="qrms-card-title"><?php esc_html_e( 'Uyarılar', 'qrms' ); ?></h2>

				<label class="qrms-switch">
					<input type="checkbox" name="ses" value="1" <?php checked( $ayar['ses'], 1 ); ?>>
					<span><?php esc_html_e( 'Yeni kayıt geldiğinde sesli uyar (varsayılan)', 'qrms' ); ?></span>
				</label>

				<p class="qrms-muted">
					<?php esc_html_e( 'Personel kendi cihazında sesi panelin üstündeki düğmeden kapatabilir; bu ayar yalnızca varsayılanı belirler.', 'qrms' ); ?>
				</p>

				<div class="qrms-field qrms-field-ikili">
					<div>
						<label for="qrms-sp-sari"><?php esc_html_e( 'Sarı uyarı (saniye)', 'qrms' ); ?></label>
						<input type="number" id="qrms-sp-sari" name="esik_sari" min="30" max="3600" step="10" value="<?php echo esc_attr( $ayar['esik_sari'] ); ?>">
					</div>
					<div>
						<label for="qrms-sp-kirmizi"><?php esc_html_e( 'Kırmızı uyarı (saniye)', 'qrms' ); ?></label>
						<input type="number" id="qrms-sp-kirmizi" name="esik_kirmizi" min="60" max="7200" step="10" value="<?php echo esc_attr( $ayar['esik_kirmizi'] ); ?>">
					</div>
				</div>

				<p class="qrms-muted">
					<?php esc_html_e( 'Bekleme süresi bu eşikleri geçtiğinde kartın kenarı renk değiştirir ve kart listenin başına çıkar.', 'qrms' ); ?>
				</p>
			</div>

			<div class="qrms-card">
				<h2 class="qrms-card-title"><?php esc_html_e( 'Panel Davranışı', 'qrms' ); ?></h2>

				<div class="qrms-field qrms-field-ikili">
					<div>
						<label for="qrms-sp-yenileme"><?php esc_html_e( 'Yenileme aralığı (saniye)', 'qrms' ); ?></label>
						<input type="number" id="qrms-sp-yenileme" name="yenileme" min="3" max="60" step="1" value="<?php echo esc_attr( $ayar['yenileme'] ); ?>">
						<p class="qrms-muted"><?php esc_html_e( 'Sekme arka plandayken aralık kendiliğinden 30 saniyeye çıkar.', 'qrms' ); ?></p>
					</div>
					<div>
						<label for="qrms-sp-pencere"><?php esc_html_e( 'Tamamlananları göster (saat)', 'qrms' ); ?></label>
						<input type="number" id="qrms-sp-pencere" name="tamam_penceresi" min="1" max="24" step="1" value="<?php echo esc_attr( $ayar['tamam_penceresi'] ); ?>">
					</div>
				</div>

				<div class="qrms-field">
					<span class="qrms-field-label"><?php esc_html_e( 'Gösterilecek tipler', 'qrms' ); ?></span>
					<?php foreach ( QRMS_SP_Veri::tipler() as $anahtar => $ad ) : ?>
						<label class="qrms-switch">
							<input type="checkbox" name="tipler[]" value="<?php echo esc_attr( $anahtar ); ?>" <?php checked( in_array( $anahtar, $ayar['tipler'], true ) ); ?>>
							<span><?php echo esc_html( $ad ); ?></span>
						</label>
					<?php endforeach; ?>
				</div>
			</div>

			<div class="qrms-card">
				<h2 class="qrms-card-title"><?php esc_html_e( 'Personel Erişimi', 'qrms' ); ?></h2>

				<p class="qrms-muted">
					<?php
					printf(
						/* translators: %s: rolün görünen adı. */
						esc_html__( 'Garson ve mutfak personeline "%s" rolü verin: bu rolle giren kullanıcı yönetim panelinde YALNIZCA Servis Panelini görür, menüye ve ayarlara erişemez.', 'qrms' ),
						esc_html__( 'Servis Personeli', 'qrms' )
					);
					?>
				</p>

				<p>
					<a class="button" href="<?php echo esc_url( admin_url( 'user-new.php' ) ); ?>">
						<?php esc_html_e( 'Yeni personel kullanıcısı ekle', 'qrms' ); ?>
					</a>
				</p>
			</div>

			<p>
				<button type="submit" name="qrms_sp_ayar_kaydet" value="1" class="button button-primary">
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
 * @return bool
 */
function qrms_sp_ayar_kaydet() {
	// phpcs:ignore WordPress.Security.NonceVerification.Missing
	if ( empty( $_POST['qrms_sp_ayar_kaydet'] ) ) {
		return false;
	}

	check_admin_referer( 'qrms_sp_ayar' );

	if ( ! current_user_can( QRMS_Admin::CAPABILITY ) ) {
		wp_die( esc_html__( 'Bu işlem için yetkiniz yok.', 'qrms' ) );
	}

	// phpcs:disable WordPress.Security.NonceVerification.Missing -- nonce yukarıda.
	$ham = array(
		'ses'             => isset( $_POST['ses'] ) ? 1 : 0,
		'esik_sari'       => isset( $_POST['esik_sari'] ) ? absint( $_POST['esik_sari'] ) : 180,
		'esik_kirmizi'    => isset( $_POST['esik_kirmizi'] ) ? absint( $_POST['esik_kirmizi'] ) : 420,
		'yenileme'        => isset( $_POST['yenileme'] ) ? absint( $_POST['yenileme'] ) : 5,
		'tamam_penceresi' => isset( $_POST['tamam_penceresi'] ) ? absint( $_POST['tamam_penceresi'] ) : 2,
		'tipler'          => isset( $_POST['tipler'] ) && is_array( $_POST['tipler'] )
			? array_map( 'sanitize_key', wp_unslash( $_POST['tipler'] ) )
			: array(),
	);
	// phpcs:enable

	update_option( QRMS_SP_Veri::OPTION, QRMS_SP_Veri::ayar_temizle( $ham ) );

	delete_transient( QRMS_SP_Veri::ONBELLEK_ANAHTAR );

	return true;
}
