<?php
/**
 * Yönetim sayfası: QR Menü → QR Analiz (Menü Analitiği).
 *
 * Sayfa yalnızca iskeleti basar; tablolar `assets/js/analitik.js` tarafından
 * tek bir AJAX çağrısıyla doldurulur.
 *
 * KAPSAM DARALIYOR. Özet kartları ve zaman grafiği "Genel Bakış" kategorisine
 * taşındı (bkz. genel-sayfasi.php); burada masa kesiti ve ürün kesiti kaldı,
 * ikisi de kendi kategorilerine taşınana kadar. Sayfa taşıma bitince
 * kalkacak, slug'ı hub'a yönlenecek.
 *
 * KATEGORİLER — kalan iki kesit tek bir chip şeridiyle bölünür (masalara
 * göre, en çok tıklanan ürünler) ve her seferinde YALNIZCA seçili kesitin
 * bölümü görünür.
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
						<?php esc_html_e( 'Menüye kaç kişi baktı, hangi ürünler tıklandı, hangi masadan geldi.', 'qrms' ); ?>
					</p>
				</div>

				<div class="qrms-an-header-actions">
					<?php
					/*
					 * CSV, kategori bölümlerinin DIŞINDA durur: indirilen dosya
					 * ekranda ne görünüyorsa odur (masalar kategorisinde masa
					 * özeti, diğerlerinde ürün listesi), bu yüzden düğmenin
					 * yalnızca tek bir kategoride görünmesi indirmenin bir
					 * kısmını erişilemez kılardı.
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
			 * KATEGORİ ŞERİDİ — kalan iki kesit. Zaman kategorileri
			 * (saatlik/günlük/haftalık/aylık) "Genel Bakış" sayfasına, orada
			 * grafiğin KIRILIM seçicisi olarak taşındı; verinin penceresi
			 * artık üstteki ortak filtre çubuğundan gelir. Şerit dar ekranda
			 * yatay kayar; seçili chip görünür kalsın diye JS onu görüş
			 * alanına alır.
			 */
			?>
			<div class="qrms-an-cats" role="tablist" aria-label="<?php esc_attr_e( 'Veri kategorisi', 'qrms' ); ?>">
				<button type="button" class="qrms-an-tab is-active" role="tab" aria-selected="true" aria-controls="qrms-an-cat-veri" data-cat="masalar">
					<span class="dashicons dashicons-editor-table" aria-hidden="true"></span> <?php esc_html_e( 'Masalara Göre', 'qrms' ); ?>
				</button>
				<button type="button" class="qrms-an-tab" role="tab" aria-selected="false" aria-controls="qrms-an-cat-urunler" data-cat="urunler">
					<span class="dashicons dashicons-star-filled" aria-hidden="true"></span> <?php esc_html_e( 'En Çok Tıklananlar', 'qrms' ); ?>
				</button>
			</div>

			<div class="qrms-an-panel qrms-an-cat-panel" id="qrms-an-cat-veri" role="tabpanel">
				<div class="qrms-an-tablewrap" id="qrms-an-table"></div>
			</div>

			<div class="qrms-an-panel qrms-an-cat-panel" id="qrms-an-cat-urunler" role="tabpanel" hidden>
				<div class="qrms-an-panel-header">
					<h2 class="qrms-an-panel-title"><span class="dashicons dashicons-star-filled" aria-hidden="true"></span> <?php esc_html_e( 'En Çok Tıklanan Ürünler', 'qrms' ); ?></h2>

					<div class="qrms-an-panel-actions">
						<?php
						/*
						 * Ürün listesinin kendi penceresi. Bu kesit Faz 3'te
						 * "Ürünler" kategorisine taşınacak ve o zaman pencere
						 * de ortak filtre çubuğundan gelecek; şimdilik kendi
						 * seçicisiyle çalışır.
						 */
						?>
						<label class="qrms-an-filter-label" for="qrms-an-urun-donem"><?php esc_html_e( 'Dönem', 'qrms' ); ?></label>
						<select id="qrms-an-urun-donem" class="qrms-an-select qrms-an-select-small">
							<option value="hourly"><?php esc_html_e( 'Bugün', 'qrms' ); ?></option>
							<option value="daily"><?php esc_html_e( 'Son 30 gün', 'qrms' ); ?></option>
							<option value="weekly"><?php esc_html_e( 'Son 12 hafta', 'qrms' ); ?></option>
							<option value="monthly"><?php esc_html_e( 'Son 12 ay', 'qrms' ); ?></option>
						</select>
					</div>
				</div>
				<div id="qrms-an-products">
					<div class="qrms-an-loading"><?php esc_html_e( 'Yükleniyor', 'qrms' ); ?></div>
				</div>
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
