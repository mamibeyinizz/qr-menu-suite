<?php
/**
 * KATEGORİ: Genel Bakış (qrms-an-genel).
 *
 * Klasik panelin dört özet kartı ve zaman grafiği buraya taşındı. İki ayrı
 * zaman kavramı vardır, karıştırılmamalı:
 *
 *   ARALIK  — üstteki paylaşılan filtre çubuğundan gelir (bugün / son 7 gün /
 *             bu ay / özel). Hangi VERİYE bakıldığını söyler ve bütün kategori
 *             sayfalarında ortaktır.
 *   KIRILIM — grafiğin kendi seçicisi (saatlik / günlük / haftalık / aylık).
 *             Aynı verinin nasıl GRUPLANDIĞINI söyler; yalnızca bu sayfaya
 *             aittir, taşınmaz ve sayfayı yenilemez.
 *
 * Aralıkla uyumsuz kırılımlar hiç basılmaz: "Bugün" seçiliyken aylık kırılım
 * tek çubuk olurdu, üç aylık bir aralıkta saatlik kırılım bütün günlerin
 * saatlerini üst üste toplardı (bkz. QRMS_Analitik_Filtre::kirilimlar).
 *
 * Sayfa iskeleti PHP'den gelir; kartlar, grafik ve tablo tek bir AJAX
 * çağrısıyla (qrms_analitik_genel) doldurulur — kırılım değişimi sayfayı
 * yenilemeden aynı uçtan gelir.
 *
 * @package QR_Menu_Suite
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'qrms_analitik_kirilim_etiketleri' ) ) {

	/**
	 * Kırılım seçicisinin etiketleri ve dashicon'ları.
	 *
	 * Emoji DEĞİL: klasik paneldeki ⏰ 📅 📆 📊 chip'leri buraya taşınırken
	 * dashicons'a çevrildi (bkz. QRMS_Admin::render_hub başlığındaki gerekçe).
	 *
	 * @return array<string,array{label:string,icon:string}>
	 */
	function qrms_analitik_kirilim_etiketleri() {
		return array(
			'hourly'  => array(
				'label' => __( 'Saatlik', 'qrms' ),
				'icon'  => 'dashicons-clock',
			),
			'daily'   => array(
				'label' => __( 'Günlük', 'qrms' ),
				'icon'  => 'dashicons-calendar-alt',
			),
			'weekly'  => array(
				'label' => __( 'Haftalık', 'qrms' ),
				'icon'  => 'dashicons-calendar',
			),
			'monthly' => array(
				'label' => __( 'Aylık', 'qrms' ),
				'icon'  => 'dashicons-chart-bar',
			),
		);
	}
}

if ( ! function_exists( 'qrms_analitik_sayfa_genel' ) ) {

	/**
	 * Genel Bakış ekranı.
	 *
	 * @return void
	 */
	function qrms_analitik_sayfa_genel() {
		if ( ! current_user_can( QRMS_Admin::CAPABILITY ) ) {
			wp_die( esc_html__( 'Bu sayfayı görüntüleme yetkiniz yok.', 'qrms' ) );
		}

		$aralik   = QRMS_Analitik_Filtre::aralik();
		$kirilim  = QRMS_Analitik_Filtre::kirilim();
		$gecerli  = QRMS_Analitik_Filtre::kirilimlar( $aralik['gun'] );
		$etiketler = qrms_analitik_kirilim_etiketleri();
		?>
		<div class="wrap qrms-an qrms-an-genel">

			<div class="qrms-an-header">
				<div class="qrms-an-header-text">
					<h1 class="qrms-an-title"><?php esc_html_e( 'Genel Bakış', 'qrms' ); ?></h1>
					<p class="qrms-an-subtitle">
						<?php esc_html_e( 'Seçili aralıkta menüye kaç kişi baktı, kaç ürün tıklandı, kaç masa hareket etti.', 'qrms' ); ?>
					</p>
				</div>
			</div>

			<?php qrms_analitik_filtre_cubugu( 'qrms-an-genel' ); ?>

			<div class="qrms-an-cards" id="qrms-an-cards" aria-live="polite">
				<div class="qrms-an-card qrms-an-skeleton"></div>
				<div class="qrms-an-card qrms-an-skeleton"></div>
				<div class="qrms-an-card qrms-an-skeleton"></div>
				<div class="qrms-an-card qrms-an-skeleton"></div>
			</div>

			<div class="qrms-an-panel">
				<div class="qrms-an-chart-header">
					<div class="qrms-an-chart-title" id="qrms-an-chart-title"></div>

					<div class="qrms-an-legend">
						<span><span class="qrms-an-dot qrms-an-dot-gold"></span><?php esc_html_e( 'Menü Görüntüleme', 'qrms' ); ?></span>
						<span><span class="qrms-an-dot qrms-an-dot-blue"></span><?php esc_html_e( 'Ürün Tıklama', 'qrms' ); ?></span>
					</div>
				</div>

				<?php
				// Tek geçerli kırılım varsa seçici basılmaz: tek seçenekli bir
				// seçim kullanıcıya karar veriyormuş hissi verir, oysa yoktur.
				if ( count( $gecerli ) > 1 ) :
					?>
					<div class="qrms-an-cats qrms-an-kirilimlar" role="tablist" aria-label="<?php esc_attr_e( 'Grafik kırılımı', 'qrms' ); ?>">
						<?php foreach ( $gecerli as $anahtar ) : ?>
							<?php $secili = ( $anahtar === $kirilim ); ?>
							<button type="button"
								class="qrms-an-tab<?php echo $secili ? ' is-active' : ''; ?>"
								role="tab"
								aria-selected="<?php echo $secili ? 'true' : 'false'; ?>"
								data-kirilim="<?php echo esc_attr( $anahtar ); ?>">
								<span class="dashicons <?php echo esc_attr( $etiketler[ $anahtar ]['icon'] ); ?>" aria-hidden="true"></span>
								<?php echo esc_html( $etiketler[ $anahtar ]['label'] ); ?>
							</button>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>

				<div class="qrms-an-chart-scroll" id="qrms-an-chart">
					<div class="qrms-an-loading"><?php esc_html_e( 'Grafik yükleniyor', 'qrms' ); ?></div>
				</div>

				<div class="qrms-an-tablewrap" id="qrms-an-table"></div>
			</div>
		</div>
		<?php
	}
}
