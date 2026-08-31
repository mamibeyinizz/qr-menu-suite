<?php
/**
 * Yönetim sayfası: QR Menü → QR Analiz (Menü Analitiği).
 *
 * Sayfa yalnızca iskeleti basar; tablolar `assets/js/analitik.js` tarafından
 * tek bir AJAX çağrısıyla doldurulur.
 *
 * KAPSAM DARALIYOR. Özet kartları ve zaman grafiği "Genel Bakış" kategorisine
 * (genel-sayfasi.php), ürün listesi "Ürünler" kategorisine (urunler-sayfasi.php)
 * taşındı. Geriye masa kesiti ile veri/sistem düğmeleri kaldı; ikisi de kendi
 * kategorilerine taşınınca bu sayfa kalkacak ve slug'ı hub'a yönlenecek.
 *
 * Tek kesit kaldığı için kategori chip şeridi de kalktı: tek seçenekli bir
 * seçici kullanıcıya karar veriyormuş hissi verir, oysa yoktur.
 *
 * MASA FİLTRESİ sayfanın kendi kutusunda değil, bütün kategorilerle ORTAK
 * filtre çubuğundadır: seçim adreste taşınır (bkz. filtre-cubugu.php).
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
				'period'   => 'masalar',
				'masa'     => QRMS_Analitik_Filtre::masa(),
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
						<?php esc_html_e( 'Henüz kendi kategorisine taşınmamış bölümler: masa kesiti ve veri yönetimi.', 'qrms' ); ?>
					</p>
				</div>

				<div class="qrms-an-header-actions">
					<?php
					/*
					 * Bu sayfanın CSV'si ekranda ne görünüyorsa onu verir:
					 * masa özeti. Ürünlerin kendi indirmesi kendi
					 * kategorisindedir (kategori=urunler), tümünü indiren
					 * seçenek ise "Veri & Sistem" sayfasına gelecek.
					 */
					?>
					<a id="qrms-an-csv" class="qrms-an-btn" href="<?php echo esc_url( $csv_url ); ?>">
						<span class="dashicons dashicons-download" aria-hidden="true"></span> <?php esc_html_e( 'CSV indir', 'qrms' ); ?>
					</a>
					<button type="button" id="qrms-an-refresh" class="qrms-an-btn">
						<span class="dashicons dashicons-update" aria-hidden="true"></span> <?php esc_html_e( 'Yenile', 'qrms' ); ?>
					</button>
					<button type="button" id="qrms-an-clear" class="qrms-an-btn qrms-an-btn-danger">
						<span class="dashicons dashicons-trash" aria-hidden="true"></span> <?php esc_html_e( 'Verileri Sil', 'qrms' ); ?>
					</button>
				</div>
			</div>

			<?php
			/*
			 * "Neden veri yok?" kutusu. Yalnızca gerçek bir engel varsa basılır;
			 * her şey yolundaysa hiç görünmez (bkz. QRMS_Analitik::teshis).
			 * Her bulgunun bir de EYLEMİ vardır — kullanıcı sorunu okuyup ne
			 * yapacağını aramak zorunda kalmasın.
			 */
			foreach ( QRMS_Analitik::teshis() as $bulgu ) :
				?>
				<div class="qrms-an-teshis qrms-an-teshis-<?php echo esc_attr( $bulgu['tip'] ); ?>">
					<span class="qrms-an-teshis-icon dashicons <?php echo 'kritik' === $bulgu['tip'] ? 'dashicons-warning' : ( 'uyari' === $bulgu['tip'] ? 'dashicons-flag' : 'dashicons-info-outline' ); ?>" aria-hidden="true"></span>

					<div class="qrms-an-teshis-body">
						<h2 class="qrms-an-teshis-title"><?php echo esc_html( $bulgu['baslik'] ); ?></h2>
						<p class="qrms-an-teshis-text"><?php echo esc_html( $bulgu['mesaj'] ); ?></p>

						<?php if ( '' !== $bulgu['url'] ) : ?>
							<a class="qrms-an-btn qrms-an-teshis-action<?php echo 'kritik' === $bulgu['tip'] ? ' qrms-an-btn-danger-solid' : ''; ?>"
								href="<?php echo esc_url( $bulgu['url'] ); ?>">
								<?php echo esc_html( $bulgu['etiket'] ); ?>
							</a>
						<?php endif; ?>
					</div>
				</div>
				<?php
			endforeach;
			?>

			<?php qrms_analitik_filtre_cubugu( QRMS_ANALITIK_KLASIK_SAYFA ); ?>

			<?php
			/*
			 * Kalan tek kesit: masa özeti (son 30 gün). Kimliği JS'in
			 * aradığıyla aynı kalır; sayfa "Masalar" kategorisine taşınınca
			 * bu bölüm de oraya gidecek.
			 */
			?>
			<div class="qrms-an-panel" id="qrms-an-cat-veri">
				<div class="qrms-an-panel-header">
					<h2 class="qrms-an-panel-title">
						<span class="dashicons dashicons-editor-table" aria-hidden="true"></span>
						<?php esc_html_e( 'Masalara Göre', 'qrms' ); ?>
					</h2>
				</div>

				<div class="qrms-an-tablewrap" id="qrms-an-table"></div>
			</div>

			<div class="qrms-an-modal" id="qrms-an-confirm" hidden>
				<div class="qrms-an-modal-box" role="dialog" aria-modal="true" aria-labelledby="qrms-an-confirm-title">
					<span class="qrms-an-modal-icon dashicons dashicons-warning" aria-hidden="true"></span>
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
