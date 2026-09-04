<?php
/**
 * Servis Paneli — canlı kanban ekranı.
 *
 * Sayfa yalnızca iskeleti basar; kartları JavaScript üretir ve her yoklamada
 * yeniler. Canlı bir panelde sunucu tarafında bir kez basılan kart, ikinci
 * saniyede zaten yanlış olurdu; bu yüzden kart şablonu TEK yerdedir
 * (assets/js/panel.js) ve PHP tarafında kopyalanmaz.
 *
 * @package QR_Menu_Suite
 */

defined( 'ABSPATH' ) || exit;

/**
 * Paneli basar.
 *
 * @return void
 */
function qrms_sp_panel_sayfasi() {
	if ( ! current_user_can( QRMS_SP_Rol::YETENEK ) ) {
		wp_die( esc_html__( 'Bu sayfayı görüntüleme yetkiniz yok.', 'qrms' ) );
	}

	$durumlar = QRMS_SP_Veri::durumlar();
	$tipler   = QRMS_SP_Veri::tipler();
	?>
	<div class="wrap qrms-wrap qrms-sp">
		<div class="qrms-sp-ust">
			<h1 class="qrms-title"><?php esc_html_e( 'Servis Paneli', 'qrms' ); ?></h1>

			<div class="qrms-sp-arac">
				<button type="button" class="button qrms-sp-ses" aria-pressed="false">
					<span class="dashicons dashicons-controls-volumeon" aria-hidden="true"></span>
					<span class="qrms-sp-ses-metin"><?php esc_html_e( 'Ses', 'qrms' ); ?></span>
				</button>

				<button type="button" class="button qrms-sp-bildirim">
					<span class="dashicons dashicons-bell" aria-hidden="true"></span>
					<?php esc_html_e( 'Bildirim', 'qrms' ); ?>
				</button>

				<?php if ( current_user_can( QRMS_Admin::CAPABILITY ) ) : ?>
					<a class="button" href="<?php echo esc_url( add_query_arg( 'page', QRMS_SP_AYAR_SAYFA, admin_url( 'admin.php' ) ) ); ?>">
						<span class="dashicons dashicons-admin-settings" aria-hidden="true"></span>
						<?php esc_html_e( 'Ayarlar', 'qrms' ); ?>
					</a>
				<?php endif; ?>
			</div>
		</div>

		<?php
		if ( ! QRMS_SP_Veri::hazir_mi() ) {
			qrms_sp_firebase_uyarisi();
			echo '</div>';

			return;
		}
		?>

		<div class="qrms-sp-serit" id="qrms-sp-serit" hidden role="status"></div>

		<div class="qrms-sp-filtre">
			<label class="qrms-sp-filtre-alan">
				<span><?php esc_html_e( 'Tip', 'qrms' ); ?></span>
				<select id="qrms-sp-tip">
					<option value=""><?php esc_html_e( 'Hepsi', 'qrms' ); ?></option>
					<?php foreach ( $tipler as $anahtar => $ad ) : ?>
						<option value="<?php echo esc_attr( $anahtar ); ?>"><?php echo esc_html( $ad ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>

			<label class="qrms-sp-filtre-alan">
				<span><?php esc_html_e( 'Masa ara', 'qrms' ); ?></span>
				<input type="search" id="qrms-sp-masa" placeholder="<?php esc_attr_e( 'Masa adı', 'qrms' ); ?>">
			</label>
		</div>

		<!-- Dar ekranda sütunlar sekmeye dönüşür; sekme çubuğunu JS doldurur. -->
		<div class="qrms-sp-sekmeler" id="qrms-sp-sekmeler" role="tablist"></div>

		<div class="qrms-sp-kanban" id="qrms-sp-kanban">
			<?php foreach ( $durumlar as $anahtar => $ad ) : ?>
				<section class="qrms-sp-sutun" data-durum="<?php echo esc_attr( $anahtar ); ?>" id="qrms-sp-sutun-<?php echo esc_attr( $anahtar ); ?>">
					<header class="qrms-sp-sutun-basi">
						<h2><?php echo esc_html( $ad ); ?></h2>
						<span class="qrms-sp-sayac" data-sayac="<?php echo esc_attr( $anahtar ); ?>">0</span>
					</header>
					<div class="qrms-sp-kartlar" data-kartlar="<?php echo esc_attr( $anahtar ); ?>"></div>
				</section>
			<?php endforeach; ?>
		</div>

		<noscript>
			<div class="qrms-alert qrms-alert-warning">
				<p><?php esc_html_e( 'Bu ekran canlı veri gösterir ve JavaScript gerektirir.', 'qrms' ); ?></p>
			</div>
		</noscript>
	</div>
	<?php
}

/**
 * Firebase yapılandırılmamışken gösterilen kutu.
 *
 * @return void
 */
function qrms_sp_firebase_uyarisi() {
	?>
	<div class="qrms-alert qrms-alert-warning">
		<p>
			<strong><?php esc_html_e( 'Firebase yapılandırılmamış.', 'qrms' ); ?></strong>
			<?php esc_html_e( 'Siparişler ve garson çağrıları Firestore üzerinden geldiği için panel şu an boş kalır.', 'qrms' ); ?>
		</p>
		<?php if ( current_user_can( QRMS_Admin::CAPABILITY ) ) : ?>
			<p>
				<a class="button button-primary" href="<?php echo esc_url( add_query_arg( 'page', 'qrms-analiz-ayarlar', admin_url( 'admin.php' ) ) ); ?>">
					<?php esc_html_e( 'Firebase ayarlarını aç', 'qrms' ); ?>
				</a>
			</p>
		<?php endif; ?>
	</div>
	<?php
}
