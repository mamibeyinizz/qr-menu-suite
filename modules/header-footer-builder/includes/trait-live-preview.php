<?php
/**
 * Header Footer Builder — canlı önizleme.
 *
 * Eski sürümde önizleme çalışmıyordu; nedenleri ve karşılıkları:
 *
 * 1. Önizleme ayrı bir SEKMEYDİ ve o sekmede form yoktu. JS hem formu hem
 *    `#hfb-preview-inline` hedefini arıyordu, ikisi aynı ekranda hiç
 *    bulunmuyordu → istek hiç kurulmuyordu. Artık önizleme forma komşu
 *    sabit paneldir, form her zaman DOM'dadır.
 * 2. Yönetim tarafında bazı varyantların CSS'i hiç kuyruğa alınmıyordu
 *    (ör. header-menulux) → önizleme stilsiz görünüyordu. Artık tek bir
 *    frontend.css var; eksik kalabilecek dosya yok.
 * 3. JS, sunucudan gelen HTML'in üzerine EKSİK bir CSS değişkeni dizisi
 *    yazıp satır içi stilleri eziyordu. Artık istemci hiç stil yazmaz;
 *    tüm çıktı sunucudan gelir.
 * 4. Formun yalnızca elle sayılan birkaç alanı toplanıyordu; yeni alanlar
 *    önizlemeye yansımıyordu. Artık form olduğu gibi serileştirilir ve
 *    kayıtla AYNI temizleyicilerden geçer. Footer'ın dört adımı (Logo/
 *    Slogan, Hızlı Menü, Çalışma Saatleri+İletişim, Garson/Hesap) da bu
 *    yoldan geçer — ayrı bir önizleme yolu yoktur.
 * 5. İstemci yükü DÜZ bir sözlük olarak kuruyordu. Köşeli parantez taşıyan
 *    alan adları (`hfb_hamburger_blocks[blk_1][enabled]`) bu sözlükte anahtar
 *    olunca istek `data[hfb_hamburger_blocks[blk_1][enabled]]` hâline
 *    geliyordu; PHP parantezleri EŞLEŞTİRMEZ, ilk kapanan paranteze göre
 *    ayrıştırır ve anahtar `hfb_hamburger_blocks[blk_1` diye bozulurdu.
 *    Sonuç: sunucu hiç blok göremiyor, sanitize_hamburger_blocks() KAYITLI
 *    bloklara geri düşüyordu — blok ekleme/silme/sıralama, hizalama, metin
 *    ve buton ayarları önizlemede hiç görünmüyordu. Yük artık istemcide iç
 *    içe kurulur (bkz. assets/js/admin.js -> nameToPath/assignPath) ve
 *    `data[hfb_hamburger_blocks][blk_1][enabled]` olarak gider.
 * 6. Önizleme dinleyicisi `.hfb-preview-trigger` sınıfına bağlıydı; sınıfı
 *    unutulan her yeni alan sessizce önizleme dışında kalıyordu. Dinleyici
 *    artık formun kökünde tek bir delegasyondur ve her input/select/textarea
 *    (JS ile sonradan eklenen blok alanları dâhil) kapsanır.
 *
 * @package QR_Menu_Suite
 */

defined( 'ABSPATH' ) || exit;

trait QRMS_HFB_Live_Preview {

	/**
	 * Form ile yan yana duran sabit önizleme paneli.
	 *
	 * Yerleşim adımı (içerik genişliği + iç boşluklar) da bu panelde
	 * doğrulanır. Değerler sunucuda `--hfb-header-*` değişkenlerine
	 * yazıldığı ve kırılım kap sorgusuna bağlı olduğu için, tuval mobil
	 * genişliğine (390px) indiğinde mobil boşluk seti önizlemede de
	 * kendiliğinden devreye girer; istemci ayrıca stil hesaplamaz.
	 *
	 * @param array<string,mixed>      $header_opts    Header ayarları.
	 * @param array<string,mixed>      $footer_opts    Footer ayarları.
	 * @param array<string,mixed>|null $hamburger_opts Hamburger ayarları.
	 * @return void
	 */
	public function render_live_preview_panel( $header_opts, $footer_opts, $hamburger_opts = null ) {
		if ( null === $hamburger_opts ) {
			$hamburger_opts = $this->get_hamburger_options();
		}
		?>
		<aside class="hfb-layout__side">
			<div class="qrms-card hfb-preview" id="hfb-preview">
				<h2 class="qrms-card-title"><?php esc_html_e( 'Canlı Önizleme', 'qrms' ); ?></h2>
				<p class="qrms-muted">
					<?php esc_html_e( 'Formdaki her değişiklik kaydetmeden burada görünür.', 'qrms' ); ?>
				</p>

				<p class="qrms-muted hfb-preview__hint">
					<?php esc_html_e( 'Yerleşim adımındaki boşluklar da buradan izlenir: masaüstü seti Masaüstü Önizleme\'de, mobil seti Mobil Önizleme\'de geçerlidir. Masaüstü tuvali 1100px olduğu için bunun üzerindeki maksimum genişlik değerleri önizlemede aynı görünür.', 'qrms' ); ?>
				</p>

				<div class="hfb-preview__toolbar">
					<div class="hfb-preview__toggle" role="group" aria-label="<?php esc_attr_e( 'Önizleme kırılımı', 'qrms' ); ?>">
						<button type="button" class="button hfb-preview__mode-btn is-active" data-preview-mode="desktop"><?php esc_html_e( 'Masaüstü Önizleme', 'qrms' ); ?></button>
						<button type="button" class="button hfb-preview__mode-btn" data-preview-mode="mobile"><?php esc_html_e( 'Mobil Önizleme', 'qrms' ); ?></button>
					</div>
					<button type="button" class="button hfb-preview__open-panel" data-hfb-open-panel="1"><?php esc_html_e( 'Önizlemede Aç', 'qrms' ); ?></button>
					<button type="button" class="button hfb-preview__refresh"><?php esc_html_e( 'Yenile', 'qrms' ); ?></button>
					<span class="hfb-preview__status" id="hfb-preview-status" role="status" aria-live="polite"></span>
				</div>

				<?php
				/*
				 * Sahne dar (yan panel), ön yüz kırılımları ise kap
				 * genişliğine bağlı. "Masaüstü" seçiliyken gerçekten
				 * masaüstü yerleşimi görünsün diye tuval 1100px olarak
				 * basılır ve panele sığacak kadar ölçeklenir (bkz.
				 * assets/js/admin.js -> fitPreview). Ölçekleme görsel
				 * bir dönüşümdür; kap sorgusu tuvalin 1100px'ini görür.
				 */
				?>
				<div class="hfb-preview__stage" id="hfb-preview-stage" data-viewport="desktop">
					<div class="hfb-preview__canvas" id="hfb-preview-canvas">
						<div class="hfb-preview__section" data-preview="header">
							<?php echo $this->render_header( $header_opts, $hamburger_opts ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						</div>
						<div class="hfb-preview__placeholder">
							<p><?php esc_html_e( 'Sayfa içeriği alanı', 'qrms' ); ?></p>
						</div>
						<div class="hfb-preview__section" data-preview="footer">
							<?php echo $this->render_footer( $footer_opts ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						</div>
					</div>
				</div>
			</div>
		</aside>
		<?php
	}

	/**
	 * Önizlemenin ön yüzle birebir aynı görünmesi için gereken stiller.
	 *
	 * @return void
	 */
	public function enqueue_preview_styles() {
		$this->enqueue_frontend_styles();

		// Çeviri modülü etkinse dil seçicinin kendi stili de gerekir; yoksa
		// bayrak zaten render edilmez.
		if ( defined( 'RMA_CEVIRI_URL' ) && $this->lang_switcher_available() ) {
			wp_enqueue_style(
				'rma-ceviri',
				RMA_CEVIRI_URL . 'assets/css/ceviri.css',
				array(),
				defined( 'RMA_CEVIRI_VERSION' ) ? RMA_CEVIRI_VERSION : QRMS_VERSION
			);
		}
	}

	/**
	 * AJAX önizleme uç noktası.
	 *
	 * Kaydetmeyle aynı temizleyicileri kullanır; önizleme ile kaydedilen
	 * çıktı birbirinden ayrışamaz.
	 *
	 * @return void
	 */
	public function ajax_preview() {
		check_ajax_referer( 'hfb_preview', 'nonce' );

		if ( ! current_user_can( QRMS_Admin::CAPABILITY ) ) {
			wp_send_json_error( array( 'message' => 'forbidden' ), 403 );
		}

		// Her alan sanitize_*_input() içinde tek tek temizlenir.
		$raw = isset( $_POST['data'] ) ? wp_unslash( $_POST['data'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		if ( ! is_array( $raw ) ) {
			$raw = array();
		}

		$header    = $this->sanitize_header_input( $raw, $this->get_header_options() );
		$footer    = $this->sanitize_footer_input( $raw, $this->get_footer_options() );
		$hamburger = $this->sanitize_hamburger_input( $raw, $this->get_hamburger_options() );

		wp_send_json_success(
			array(
				'header' => $this->render_header( $header, $hamburger ),
				'footer' => $this->render_footer( $footer ),
			)
		);
	}
}
