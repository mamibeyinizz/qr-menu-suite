<?php
/**
 * Servis paneli ana ekranı.
 *
 * @package QR_Menu_Suite
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'qrms_sp_panel_sayfasi' ) ) {
	/**
	 * Panel ekranını basar.
	 *
	 * @return void
	 */
	function qrms_sp_panel_sayfasi() {
		if ( ! QRMS_SP_Rol::yetkili_mi() ) {
			wp_die( esc_html__( 'Bu sayfayı görüntüleme yetkiniz yok.', 'qrms' ) );
		}

		$firebase_hazir = class_exists( 'QMO_Firestore' ) && QMO_Firestore::hazir_mi();
		$ayarlar        = QRMS_SP_Veri::ayarlar();
		$firebase_url   = admin_url( 'admin.php?page=qrms-analiz-ayarlar' );
		?>
		<div class="wrap qrms-wrap qrms-sp-wrap" id="qrms-sp-panel"
			data-esik-sari="<?php echo esc_attr( (string) $ayarlar['esik_sari'] ); ?>"
			data-esik-kirmizi="<?php echo esc_attr( (string) $ayarlar['esik_kirmizi'] ); ?>"
			data-yenileme="<?php echo esc_attr( (string) $ayarlar['yenileme_araligi'] ); ?>"
			data-ses="<?php echo esc_attr( (string) $ayarlar['ses_acik'] ); ?>">
			<div class="qrms-sp-baslik">
				<h1 class="qrms-title"><?php esc_html_e( 'Servis Paneli', 'qrms' ); ?></h1>
				<div class="qrms-sp-kontroller">
					<label><input type="checkbox" id="qrms-sp-ses" <?php checked( ! empty( $ayarlar['ses_acik'] ) ); ?> /> <?php esc_html_e( 'Ses', 'qrms' ); ?></label>
					<input type="range" id="qrms-sp-ses-seviye" min="0" max="100" value="80" />
					<button type="button" class="button" id="qrms-sp-bildirim"><?php esc_html_e( 'Masaüstü bildirimi', 'qrms' ); ?></button>
				</div>
			</div>

			<div class="qrms-sp-hata" id="qrms-sp-hata" hidden><?php esc_html_e( 'Bağlantı yok, yeniden deneniyor…', 'qrms' ); ?></div>

			<?php if ( ! $firebase_hazir ) : ?>
				<div class="qrms-card qrms-sp-uyari">
					<p><?php esc_html_e( 'Firebase yapılandırılmamış. Sipariş ve çağrılar görüntülenemez.', 'qrms' ); ?></p>
					<?php if ( current_user_can( QRMS_Admin::CAPABILITY ) ) : ?>
						<p><a href="<?php echo esc_url( $firebase_url ); ?>"><?php esc_html_e( 'Güvenlik Ayarı → Firebase & Şube Ayarları', 'qrms' ); ?></a></p>
					<?php endif; ?>
				</div>
			<?php else : ?>
				<div class="qrms-sp-filtre">
					<label>
						<?php esc_html_e( 'Tip', 'qrms' ); ?>
						<select id="qrms-sp-tip-filtre">
							<option value=""><?php esc_html_e( 'Hepsi', 'qrms' ); ?></option>
							<option value="siparis"><?php esc_html_e( 'Sipariş', 'qrms' ); ?></option>
							<option value="garson"><?php esc_html_e( 'Garson', 'qrms' ); ?></option>
							<option value="hesap"><?php esc_html_e( 'Hesap', 'qrms' ); ?></option>
						</select>
					</label>
					<label>
						<?php esc_html_e( 'Masa ara', 'qrms' ); ?>
						<input type="search" id="qrms-sp-masa-ara" placeholder="<?php esc_attr_e( 'Masa adı…', 'qrms' ); ?>" />
					</label>
					<label><input type="checkbox" id="qrms-sp-aktif" checked /> <?php esc_html_e( 'Yalnızca aktif', 'qrms' ); ?></label>
				</div>

				<div class="qrms-sp-sekmeler" id="qrms-sp-sekmeler">
					<button type="button" class="qrms-sp-sekme is-active" data-durum="bekliyor"><?php esc_html_e( 'Bekliyor', 'qrms' ); ?> <span class="qrms-sp-rozet" data-durum="bekliyor">0</span></button>
					<button type="button" class="qrms-sp-sekme" data-durum="hazirlaniyor"><?php esc_html_e( 'Hazırlanıyor', 'qrms' ); ?> <span class="qrms-sp-rozet" data-durum="hazirlaniyor">0</span></button>
					<button type="button" class="qrms-sp-sekme" data-durum="serviste"><?php esc_html_e( 'Serviste', 'qrms' ); ?> <span class="qrms-sp-rozet" data-durum="serviste">0</span></button>
					<button type="button" class="qrms-sp-sekme" data-durum="tamamlandi"><?php esc_html_e( 'Tamamlandı', 'qrms' ); ?></button>
				</div>

				<div class="qrms-sp-kanban" id="qrms-sp-kanban">
					<?php foreach ( array( 'bekliyor', 'hazirlaniyor', 'serviste', 'tamamlandi' ) as $durum ) : ?>
						<div class="qrms-sp-sutun" data-durum="<?php echo esc_attr( $durum ); ?>">
							<h2 class="qrms-sp-sutun-baslik"><?php echo esc_html( ucfirst( $durum ) ); ?></h2>
							<div class="qrms-sp-kartlar" data-durum="<?php echo esc_attr( $durum ); ?>"></div>
						</div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}
}
