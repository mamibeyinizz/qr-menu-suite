<?php
/**
 * KATEGORİ: Veri & Sistem (qrms-an-sistem).
 *
 * Sayıların anlatıldığı yer değil, VERİNİN KENDİSİNİN yönetildiği yer: ham
 * kaydı indirmek, ne kadar saklanacağına karar vermek, tablonun ne kadar
 * büyüdüğünü görmek ve gerektiğinde silmek.
 *
 * Klasik panelin son kalıntıları (CSV / Yenile / Verileri Sil / teşhis kutusu)
 * buraya taşındı; bu fazla birlikte eski tek-sayfa yapısı tamamen kapandı.
 *
 * TEŞHİS İKİ YERDE, TEK KAYNAKTAN. Hub ekranı bulguları kısa bir uyarı olarak
 * kartların üstünde gösterir (kullanıcı hangi kategoriye girerse girsin engeli
 * görsün diye); burada ise aynı bulgular tam liste hâlinde, çözüm adımlarıyla
 * durur. İkisi de QRMS_Analitik::teshis() çıktısını basar — metin kopyalanmaz.
 *
 * SİLME AKIŞI AYNEN TAŞINDI. Onay modalı, nonce (QRMS_Analitik::NONCE), yetki
 * kontrolü ve uç (qrms_analitik_temizle) değişmedi; yalnızca yeri değişti.
 * Masa filtresi seçiliyken silme yine yalnızca o masayı kapsar.
 *
 * @package QR_Menu_Suite
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'qrms_analitik_boyut_metni' ) ) {

	/**
	 * Bayt değerini okunabilir birime çevirir.
	 *
	 * @param int $bayt Bayt.
	 * @return string
	 */
	function qrms_analitik_boyut_metni( $bayt ) {
		$bayt = (int) $bayt;

		if ( $bayt <= 0 ) {
			return '—';
		}

		if ( $bayt < 1024 * 1024 ) {
			return sprintf(
				/* translators: %s: kilobayt cinsinden boyut. */
				__( '%s KB', 'qrms' ),
				number_format_i18n( $bayt / 1024, 1 )
			);
		}

		return sprintf(
			/* translators: %s: megabayt cinsinden boyut. */
			__( '%s MB', 'qrms' ),
			number_format_i18n( $bayt / ( 1024 * 1024 ), 1 )
		);
	}
}

if ( ! function_exists( 'qrms_analitik_teshis_listesi' ) ) {

	/**
	 * Teşhis bulgularının tam listesi.
	 *
	 * Hub'daki kısa uyarıyla AYNI kaynaktan (QRMS_Analitik::teshis) beslenir;
	 * fark yalnızca sunumdadır.
	 *
	 * @return void
	 */
	function qrms_analitik_teshis_listesi() {
		$bulgular = QRMS_Analitik::teshis();

		if ( empty( $bulgular ) ) {
			?>
			<div class="qrms-an-teshis qrms-an-teshis-bilgi">
				<span class="qrms-an-teshis-icon dashicons dashicons-yes-alt" aria-hidden="true"></span>
				<div class="qrms-an-teshis-body">
					<h2 class="qrms-an-teshis-title"><?php esc_html_e( 'Her şey yolunda', 'qrms' ); ?></h2>
					<p class="qrms-an-teshis-text"><?php esc_html_e( 'İzleme çalışıyor ve kayıtlar tabloya düşüyor. Yapılacak bir şey yok.', 'qrms' ); ?></p>
				</div>
			</div>
			<?php
			return;
		}

		foreach ( $bulgular as $bulgu ) {
			$ikon = 'dashicons-info-outline';

			if ( 'kritik' === $bulgu['tip'] ) {
				$ikon = 'dashicons-warning';
			} elseif ( 'uyari' === $bulgu['tip'] ) {
				$ikon = 'dashicons-flag';
			}
			?>
			<div class="qrms-an-teshis qrms-an-teshis-<?php echo esc_attr( $bulgu['tip'] ); ?>">
				<span class="qrms-an-teshis-icon dashicons <?php echo esc_attr( $ikon ); ?>" aria-hidden="true"></span>

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
		}
	}
}

if ( ! function_exists( 'qrms_analitik_sayfa_sistem' ) ) {

	/**
	 * Veri & Sistem ekranı.
	 *
	 * @return void
	 */
	function qrms_analitik_sayfa_sistem() {
		if ( ! current_user_can( QRMS_Admin::CAPABILITY ) ) {
			wp_die( esc_html__( 'Bu sayfayı görüntüleme yetkiniz yok.', 'qrms' ) );
		}

		$masa    = QRMS_Analitik_Filtre::masa();
		$istat   = QRMS_Analitik::tablo_istatistikleri();
		$saklama = QRMS_Analitik::saklama_ayari();
		$kilitli = QRMS_Analitik::saklama_kilitli_mi();
		$sonraki = wp_next_scheduled( QRMS_Analitik::CRON_TEMIZLIK );

		$csv_args = array(
			'action'   => 'qrms_analitik_csv',
			'donem'    => QRMS_Analitik_Filtre::donem(),
			'bas'      => QRMS_Analitik_Filtre::bas(),
			'bit'      => QRMS_Analitik_Filtre::bit(),
			'masa'     => $masa,
			'security' => wp_create_nonce( QRMS_Analitik::NONCE_CSV ),
		);

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Yalnızca bildirim metni seçer.
		$kaydedildi = isset( $_GET['saklama_msg'] ) && 'kaydedildi' === sanitize_key( wp_unslash( $_GET['saklama_msg'] ) );
		?>
		<div class="wrap qrms-an qrms-an-sistem">

			<div class="qrms-an-header">
				<div class="qrms-an-header-text">
					<h1 class="qrms-an-title"><?php esc_html_e( 'Veri & Sistem', 'qrms' ); ?></h1>
					<p class="qrms-an-subtitle">
						<?php esc_html_e( 'Kayıtları indirin, ne kadar saklanacağını belirleyin, tablonun durumunu görün.', 'qrms' ); ?>
					</p>
				</div>

				<div class="qrms-an-header-actions">
					<button type="button" id="qrms-an-refresh" class="qrms-an-btn">
						<span class="dashicons dashicons-update" aria-hidden="true"></span>
						<?php esc_html_e( 'Yenile', 'qrms' ); ?>
					</button>
					<button type="button" id="qrms-an-clear" class="qrms-an-btn qrms-an-btn-danger">
						<span class="dashicons dashicons-trash" aria-hidden="true"></span>
						<?php esc_html_e( 'Verileri Sil', 'qrms' ); ?>
					</button>
				</div>
			</div>

			<?php if ( $kaydedildi ) : ?>
				<div class="notice notice-success is-dismissible">
					<p><?php esc_html_e( 'Saklama süresi kaydedildi.', 'qrms' ); ?></p>
				</div>
			<?php endif; ?>

			<?php qrms_analitik_filtre_cubugu( 'qrms-an-sistem' ); ?>

			<div class="qrms-an-panel">
				<div class="qrms-an-panel-header">
					<h2 class="qrms-an-panel-title">
						<span class="dashicons dashicons-download" aria-hidden="true"></span>
						<?php esc_html_e( 'Dışa Aktar', 'qrms' ); ?>
					</h2>
				</div>

				<p class="qrms-an-panel-note">
					<?php esc_html_e( 'Ham kayıt, seçili tarih aralığındaki bütün olayları satır satır verir. Özet tablolar (ürünler, masalar) kendi kategori sayfalarından da indirilebilir.', 'qrms' ); ?>
				</p>

				<p>
					<a class="qrms-an-btn qrms-an-btn-primary"
						href="<?php echo esc_url( add_query_arg( array_merge( $csv_args, array( 'kategori' => 'ham' ) ), admin_url( 'admin-ajax.php' ) ) ); ?>">
						<span class="dashicons dashicons-media-spreadsheet" aria-hidden="true"></span>
						<?php esc_html_e( 'Ham kaydı indir (CSV)', 'qrms' ); ?>
					</a>
					<a class="qrms-an-btn"
						href="<?php echo esc_url( add_query_arg( array_merge( $csv_args, array( 'kategori' => 'urunler' ) ), admin_url( 'admin-ajax.php' ) ) ); ?>">
						<span class="dashicons dashicons-food" aria-hidden="true"></span>
						<?php esc_html_e( 'Ürün özeti', 'qrms' ); ?>
					</a>
					<a class="qrms-an-btn"
						href="<?php echo esc_url( add_query_arg( array_merge( $csv_args, array( 'kategori' => 'masalar' ) ), admin_url( 'admin-ajax.php' ) ) ); ?>">
						<span class="dashicons dashicons-editor-table" aria-hidden="true"></span>
						<?php esc_html_e( 'Masa özeti', 'qrms' ); ?>
					</a>
				</p>

				<p class="qrms-an-panel-note">
					<?php
					printf(
						/* translators: %s: azami satır sayısı. */
						esc_html__( 'Ham indirme en fazla %s satır yazar; aşılırsa dosyanın sonunda uyarı çıkar ve daha dar bir aralık seçmeniz istenir.', 'qrms' ),
						esc_html( number_format_i18n( QRMS_Analitik::CSV_TAVAN ) )
					);
					?>
				</p>
			</div>

			<div class="qrms-an-panel">
				<div class="qrms-an-panel-header">
					<h2 class="qrms-an-panel-title">
						<span class="dashicons dashicons-database" aria-hidden="true"></span>
						<?php esc_html_e( 'Tablo Durumu', 'qrms' ); ?>
					</h2>
				</div>

				<div class="qrms-an-cards">
					<div class="qrms-an-card">
						<div class="qrms-an-card-label"><?php esc_html_e( 'Kayıt sayısı', 'qrms' ); ?></div>
						<div class="qrms-an-card-value"><?php echo esc_html( number_format_i18n( $istat['satir'] ) ); ?></div>
						<div class="qrms-an-card-sub"><?php esc_html_e( 'Tablodaki toplam olay', 'qrms' ); ?></div>
					</div>

					<div class="qrms-an-card">
						<div class="qrms-an-card-label"><?php esc_html_e( 'Disk boyutu', 'qrms' ); ?></div>
						<div class="qrms-an-card-value"><?php echo esc_html( qrms_analitik_boyut_metni( $istat['boyut'] ) ); ?></div>
						<div class="qrms-an-card-sub"><?php esc_html_e( 'Veri + indeks (yaklaşık)', 'qrms' ); ?></div>
					</div>

					<div class="qrms-an-card">
						<div class="qrms-an-card-label"><?php esc_html_e( 'En eski kayıt', 'qrms' ); ?></div>
						<div class="qrms-an-card-value">
							<?php echo esc_html( '' !== $istat['ilk'] ? substr( $istat['ilk'], 0, 10 ) : '—' ); ?>
						</div>
						<div class="qrms-an-card-sub"><?php esc_html_e( 'Bu tarihten öncesi silinmiş', 'qrms' ); ?></div>
					</div>

					<div class="qrms-an-card">
						<div class="qrms-an-card-label"><?php esc_html_e( 'Sonraki temizlik', 'qrms' ); ?></div>
						<div class="qrms-an-card-value">
							<?php echo esc_html( $sonraki ? QRMS_Helpers::format_datetime( (int) $sonraki ) : __( 'Planlanmadı', 'qrms' ) ); ?>
						</div>
						<div class="qrms-an-card-sub"><?php esc_html_e( 'Günlük zamanlanmış görev', 'qrms' ); ?></div>
					</div>
				</div>

				<ul class="qrms-detail-list">
					<li class="qrms-detail">
						<span class="qrms-detail-label"><?php esc_html_e( 'Tablo', 'qrms' ); ?></span>
						<span class="qrms-detail-value qrms-detail-break">
							<?php echo esc_html( QRMS_Analitik::tablo() ); ?>
							<?php if ( $istat['var'] ) : ?>
								<span class="qrms-an-badge"><?php esc_html_e( 'var', 'qrms' ); ?></span>
							<?php else : ?>
								<span class="qrms-an-badge qrms-an-badge-warn"><?php esc_html_e( 'yok', 'qrms' ); ?></span>
							<?php endif; ?>
						</span>
					</li>
					<li class="qrms-detail">
						<span class="qrms-detail-label"><?php esc_html_e( 'Şema sürümü', 'qrms' ); ?></span>
						<span class="qrms-detail-value">
							<?php echo esc_html( QRMS_Analitik::DB_SURUM ); ?>
							<?php if ( $istat['guncel'] ) : ?>
								<span class="qrms-an-badge"><?php esc_html_e( 'güncel', 'qrms' ); ?></span>
							<?php else : ?>
								<span class="qrms-an-badge qrms-an-badge-warn"><?php esc_html_e( 'güncellenecek', 'qrms' ); ?></span>
							<?php endif; ?>
						</span>
					</li>
				</ul>
			</div>

			<div class="qrms-an-panel">
				<div class="qrms-an-panel-header">
					<h2 class="qrms-an-panel-title">
						<span class="dashicons dashicons-clock" aria-hidden="true"></span>
						<?php esc_html_e( 'Veri Saklama Süresi', 'qrms' ); ?>
					</h2>
				</div>

				<p class="qrms-an-panel-note">
					<?php esc_html_e( 'Bu süreden eski ham kayıtlar günlük görevle silinir. Özetler geçmişe dönük yeniden hesaplandığı için süreyi kısaltmak eski dönemlerin raporlarını da boşaltır. 0 yazarsanız temizlik kapanır ve tablo sınırsız büyür.', 'qrms' ); ?>
				</p>

				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<?php wp_nonce_field( 'qrms_analitik_saklama' ); ?>
					<input type="hidden" name="action" value="qrms_analitik_saklama">

					<label class="qrms-an-filtre-label" for="qrms-an-saklama"><?php esc_html_e( 'Saklama süresi (gün)', 'qrms' ); ?></label>
					<input type="number" id="qrms-an-saklama" class="qrms-an-date" name="saklama_gun"
						value="<?php echo esc_attr( $saklama ); ?>" min="0" step="1">

					<button type="submit" class="qrms-an-btn qrms-an-btn-small"><?php esc_html_e( 'Kaydet', 'qrms' ); ?></button>
				</form>

				<p class="qrms-an-panel-note">
					<?php
					printf(
						/* translators: 1: geçerli saklama süresi, 2: alt sınır. */
						esc_html__( 'Şu an geçerli: %1$s. 0 dışında en az %2$d gün kabul edilir.', 'qrms' ),
						esc_html(
							0 === QRMS_Analitik::saklama_gun()
								? __( 'sınırsız (temizlik kapalı)', 'qrms' )
								: sprintf(
									/* translators: %d: gün sayısı. */
									__( '%d gün', 'qrms' ),
									QRMS_Analitik::saklama_gun()
								)
						),
						7
					);
					?>
				</p>

				<?php if ( $kilitli ) : ?>
					<p class="qrms-an-panel-note qrms-an-warn">
						<?php esc_html_e( 'Bu değer sitenizdeki bir kodla (qrms_analitik_saklama_gun filtresi) sabitlenmiş. Buradan kaydettiğiniz sayı saklanır ama geçerli olan filtrenin döndürdüğü değerdir.', 'qrms' ); ?>
					</p>
				<?php endif; ?>
			</div>

			<div class="qrms-an-panel">
				<div class="qrms-an-panel-header">
					<h2 class="qrms-an-panel-title">
						<span class="dashicons dashicons-info-outline" aria-hidden="true"></span>
						<?php esc_html_e( 'Teşhis', 'qrms' ); ?>
					</h2>
				</div>

				<?php qrms_analitik_teshis_listesi(); ?>
			</div>

			<?php
			/*
			 * SİLME ONAY MODALI — klasik panelden AYNEN taşındı. İşaretleme,
			 * kimlikler ve akış değişmedi; yıkıcı bir işlemin onayı, taşıma
			 * sırasında "iyileştirilecek" son yerdir.
			 */
			?>
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
