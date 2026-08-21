<?php
/**
 * Yönetim sayfası: QR Menü → QR Analiz (Menü Analitiği).
 *
 * Sayfa yalnızca iskeleti basar; kartlar, grafik ve tablolar
 * `assets/js/analitik.js` tarafından tek bir AJAX çağrısıyla doldurulur.
 * Böylece kategori chip'leri ve masa filtresi sayfa yenilemeden çalışır.
 *
 * KATEGORİLER — veriler tek bir chip şeridiyle bölünür (saatlik, günlük,
 * haftalık, aylık, masalara göre, en çok tıklanan ürünler) ve her seferinde
 * YALNIZCA seçili kategorinin bölümü görünür. Böylece hem ekran bir tablo
 * yığınına dönüşmez hem de dar ekranda tek bir liste okunur kalır.
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
						<span aria-hidden="true">⬇</span> <?php esc_html_e( 'CSV indir', 'qrms' ); ?>
					</a>
					<button type="button" id="qrms-an-refresh" class="qrms-an-btn">
						<span aria-hidden="true">↻</span> <?php esc_html_e( 'Yenile', 'qrms' ); ?>
					</button>
					<button type="button" id="qrms-an-clear" class="qrms-an-btn qrms-an-btn-danger">
						<span aria-hidden="true">🗑</span> <?php esc_html_e( 'Verileri Sil', 'qrms' ); ?>
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

			<div class="qrms-an-cards" id="qrms-an-cards" aria-live="polite">
				<div class="qrms-an-card qrms-an-skeleton"></div>
				<div class="qrms-an-card qrms-an-skeleton"></div>
				<div class="qrms-an-card qrms-an-skeleton"></div>
				<div class="qrms-an-card qrms-an-skeleton"></div>
			</div>

			<?php
			/*
			 * KATEGORİ ŞERİDİ — chip'ler hem sekme hem kategori: zaman
			 * kategorileri (saatlik/günlük/haftalık/aylık) verinin PENCERESİNİ,
			 * "masalar" ve "ürünler" ise aynı pencerenin farklı KESİTİNİ
			 * gösterir. Şerit dar ekranda yatay kayar; seçili chip görünür
			 * kalsın diye JS onu görüş alanına alır.
			 */
			?>
			<div class="qrms-an-cats" role="tablist" aria-label="<?php esc_attr_e( 'Veri kategorisi', 'qrms' ); ?>">
				<button type="button" class="qrms-an-tab is-active" role="tab" aria-selected="true" aria-controls="qrms-an-cat-veri" data-cat="hourly">⏰ <?php esc_html_e( 'Saatlik', 'qrms' ); ?></button>
				<button type="button" class="qrms-an-tab" role="tab" aria-selected="false" aria-controls="qrms-an-cat-veri" data-cat="daily">📅 <?php esc_html_e( 'Günlük', 'qrms' ); ?></button>
				<button type="button" class="qrms-an-tab" role="tab" aria-selected="false" aria-controls="qrms-an-cat-veri" data-cat="weekly">📆 <?php esc_html_e( 'Haftalık', 'qrms' ); ?></button>
				<button type="button" class="qrms-an-tab" role="tab" aria-selected="false" aria-controls="qrms-an-cat-veri" data-cat="monthly">📊 <?php esc_html_e( 'Aylık', 'qrms' ); ?></button>
				<button type="button" class="qrms-an-tab" role="tab" aria-selected="false" aria-controls="qrms-an-cat-veri" data-cat="masalar">🍽️ <?php esc_html_e( 'Masalara Göre', 'qrms' ); ?></button>
				<button type="button" class="qrms-an-tab" role="tab" aria-selected="false" aria-controls="qrms-an-cat-urunler" data-cat="urunler">🔥 <?php esc_html_e( 'En Çok Tıklananlar', 'qrms' ); ?></button>
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

			<?php // Zaman kategorileri (saatlik/günlük/haftalık/aylık) ve masa kesiti aynı bölümü kullanır: grafik + tablo. ?>
			<div class="qrms-an-panel qrms-an-cat-panel" id="qrms-an-cat-veri" role="tabpanel">
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

			<div class="qrms-an-panel qrms-an-cat-panel" id="qrms-an-cat-urunler" role="tabpanel" hidden>
				<div class="qrms-an-panel-header">
					<h2 class="qrms-an-panel-title">🔥 <?php esc_html_e( 'En Çok Tıklanan Ürünler', 'qrms' ); ?></h2>

					<div class="qrms-an-panel-actions">
						<?php
						/*
						 * Ürün listesi de dönem bağımlıdır (aynı AJAX yanıtından
						 * gelir). Kategori artık dönemi taşımadığı için pencere
						 * burada seçilir; seçim zaman kategorileriyle ORTAKTIR,
						 * yani kullanıcı saatlikten gelip ürünlere geçtiğinde
						 * aynı pencerede kalır.
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
