<?php
/**
 * Yönetim sayfası: QR Menü → Menü Analitiği.
 *
 * Sayfa yalnızca iskeleti basar; kartlar, grafik ve tablolar
 * `assets/js/analitik.js` tarafından tek bir AJAX çağrısıyla doldurulur.
 * Böylece dönem sekmeleri ve masa filtresi sayfa yenilemeden çalışır.
 *
 * @package QR_Menu_Suite
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'qrms_analitik_sayfasi' ) ) {

	/**
	 * Analitik panelini basar.
	 *
	 * @return void
	 */
	function qrms_analitik_sayfasi() {
		if ( ! current_user_can( QRMS_Admin::CAPABILITY ) ) {
			wp_die( esc_html__( 'Bu sayfayı görüntüleme yetkiniz yok.', 'qrms' ) );
		}

		$csv_nonce = wp_create_nonce( QRMS_Analitik::NONCE_CSV );
		$csv_url   = add_query_arg(
			array(
				'action'   => 'qrms_analitik_csv',
				'period'   => 'hourly',
				'masa'     => '',
				'security' => $csv_nonce,
			),
			admin_url( 'admin-ajax.php' )
		);
		?>
		<div class="wrap qrms-an">

			<div class="qrms-an-header">
				<div class="qrms-an-header-text">
					<h1 class="qrms-an-title"><?php esc_html_e( 'Menü Analitiği', 'qrms' ); ?></h1>
					<p class="qrms-an-subtitle">
						<?php esc_html_e( 'Menüye kaç kişi baktı, hangi ürünler tıklandı, hangi masadan geldi.', 'qrms' ); ?>
					</p>
				</div>

				<div class="qrms-an-header-actions">
					<button type="button" id="qrms-an-refresh" class="qrms-an-btn">
						<span aria-hidden="true">↻</span> <?php esc_html_e( 'Yenile', 'qrms' ); ?>
					</button>
					<button type="button" id="qrms-an-clear" class="qrms-an-btn qrms-an-btn-danger">
						<span aria-hidden="true">🗑</span> <?php esc_html_e( 'Verileri Sil', 'qrms' ); ?>
					</button>
				</div>
			</div>

			<?php if ( QRMS_Analitik::eski_eklenti_aktif() ) : ?>
				<div class="notice notice-warning qrms-an-notice">
					<p>
						<?php
						esc_html_e(
							'Eski "RMA Analytics" eklentisi hâlâ etkin. Kayıtları o topluyor; çift sayım olmasın diye bu modül izlemeyi devre dışı bıraktı. Masa bazlı takibin çalışması için eski eklentiyi kapatın.',
							'qrms'
						);
						?>
					</p>
				</div>
			<?php endif; ?>

			<div class="qrms-an-cards" id="qrms-an-cards" aria-live="polite">
				<div class="qrms-an-card qrms-an-skeleton"></div>
				<div class="qrms-an-card qrms-an-skeleton"></div>
				<div class="qrms-an-card qrms-an-skeleton"></div>
				<div class="qrms-an-card qrms-an-skeleton"></div>
			</div>

			<div class="qrms-an-panel">
				<div class="qrms-an-tabbar" role="tablist" aria-label="<?php esc_attr_e( 'Dönem', 'qrms' ); ?>">
					<button type="button" class="qrms-an-tab is-active" role="tab" aria-selected="true" data-period="hourly">⏰ <?php esc_html_e( 'Saatlik', 'qrms' ); ?></button>
					<button type="button" class="qrms-an-tab" role="tab" aria-selected="false" data-period="daily">📅 <?php esc_html_e( 'Günlük', 'qrms' ); ?></button>
					<button type="button" class="qrms-an-tab" role="tab" aria-selected="false" data-period="weekly">📆 <?php esc_html_e( 'Haftalık', 'qrms' ); ?></button>
					<button type="button" class="qrms-an-tab" role="tab" aria-selected="false" data-period="monthly">📊 <?php esc_html_e( 'Aylık', 'qrms' ); ?></button>
					<button type="button" class="qrms-an-tab" role="tab" aria-selected="false" data-period="masalar">🍽️ <?php esc_html_e( 'Masalara Göre', 'qrms' ); ?></button>
				</div>

				<div class="qrms-an-filter">
					<label class="qrms-an-filter-label" for="qrms-an-masa"><?php esc_html_e( 'Masa filtresi', 'qrms' ); ?></label>
					<select id="qrms-an-masa" class="qrms-an-select">
						<option value=""><?php esc_html_e( 'Tüm masalar', 'qrms' ); ?></option>
					</select>
					<button type="button" id="qrms-an-masa-temizle" class="qrms-an-btn qrms-an-btn-small" hidden>
						<?php esc_html_e( 'Filtreyi kaldır', 'qrms' ); ?>
					</button>
				</div>

				<div class="qrms-an-chart-header">
					<div class="qrms-an-chart-title" id="qrms-an-chart-title"></div>
					<div class="qrms-an-legend">
						<span><span class="qrms-an-dot qrms-an-dot-gold"></span><?php esc_html_e( 'Menü Görüntüleme', 'qrms' ); ?></span>
						<span><span class="qrms-an-dot qrms-an-dot-blue"></span><?php esc_html_e( 'Ürün Tıklama', 'qrms' ); ?></span>
					</div>
				</div>

				<div class="qrms-an-chart-scroll" id="qrms-an-chart">
					<div class="qrms-an-loading"><?php esc_html_e( 'Grafik yükleniyor', 'qrms' ); ?></div>
				</div>

				<div class="qrms-an-tablewrap" id="qrms-an-table"></div>
			</div>

			<div class="qrms-an-panel">
				<div class="qrms-an-panel-header">
					<h2 class="qrms-an-panel-title">🔥 <?php esc_html_e( 'En Çok Tıklanan Ürünler', 'qrms' ); ?></h2>
					<a id="qrms-an-csv" class="qrms-an-btn qrms-an-btn-small" href="<?php echo esc_url( $csv_url ); ?>">
						<span aria-hidden="true">⬇</span> <?php esc_html_e( 'CSV indir', 'qrms' ); ?>
					</a>
				</div>
				<div id="qrms-an-products">
					<div class="qrms-an-loading"><?php esc_html_e( 'Yükleniyor', 'qrms' ); ?></div>
				</div>
			</div>

			<div class="qrms-an-modal" id="qrms-an-confirm" hidden>
				<div class="qrms-an-modal-box" role="dialog" aria-modal="true" aria-labelledby="qrms-an-confirm-title">
					<div class="qrms-an-modal-icon" aria-hidden="true">⚠️</div>
					<h2 class="qrms-an-modal-title" id="qrms-an-confirm-title"><?php esc_html_e( 'Kayıtlar silinecek', 'qrms' ); ?></h2>
					<p class="qrms-an-modal-text" id="qrms-an-confirm-text"></p>
					<div class="qrms-an-modal-actions">
						<button type="button" class="qrms-an-btn" id="qrms-an-confirm-cancel"><?php esc_html_e( 'Vazgeç', 'qrms' ); ?></button>
						<button type="button" class="qrms-an-btn qrms-an-btn-danger-solid" id="qrms-an-confirm-ok"><?php esc_html_e( 'Evet, sil', 'qrms' ); ?></button>
					</div>
				</div>
			</div>

		</div>
		<?php
	}
}
