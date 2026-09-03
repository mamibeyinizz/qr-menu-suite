<?php
/**
 * Menü mühendisliği hub sayfası.
 *
 * @package QR_Menu_Suite
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'qrms_mm_hub_sayfasi' ) ) {
	/**
	 * Hub ekranını basar.
	 *
	 * @return void
	 */
	function qrms_mm_hub_sayfasi() {
		if ( ! current_user_can( QRMS_Admin::CAPABILITY ) ) {
			wp_die( esc_html__( 'Bu sayfayı görüntüleme yetkiniz yok.', 'qrms' ) );
		}

		$istat = QRMS_MM_Maliyet::hub_istatistikleri();

		QRMS_Admin::render_hub(
			array(
				'title'  => __( 'Menü Mühendisliği', 'qrms' ),
				'intro'  => __( 'Menünüzün hangi ürünü para kazandırıyor, hangisi kaybettiriyor — somut aksiyonlarla.', 'qrms' ),
				'accent' => '#7c5cff',
				'stats'  => array(
					array(
						'label' => __( 'Maliyeti girilmiş ürün', 'qrms' ),
						'value' => $istat['maliyetli'] . ' / ' . $istat['toplam'],
						'url'   => admin_url( 'admin.php?page=qrms-mm-maliyet' ),
					),
					array(
						'label' => __( 'Son dönem toplam katkı', 'qrms' ),
						'value' => number_format_i18n( $istat['toplam_katki'], 0 ) . ' ₺',
						'url'   => admin_url( 'admin.php?page=qrms-mm-rapor' ),
					),
					array(
						'label' => __( 'En çok kaybettiren', 'qrms' ),
						'value' => '' !== $istat['en_kayip'] ? $istat['en_kayip'] : '—',
						'url'   => admin_url( 'admin.php?page=qrms-mm-rapor' ),
					),
				),
				'cards'  => array(
					array(
						'url'   => admin_url( 'admin.php?page=qrms-mm-rapor' ),
						'title' => __( 'Menü Mühendisliği Raporu', 'qrms' ),
						'desc'  => __( 'Kasavana–Smith matrisi ve aksiyon önerileri.', 'qrms' ),
						'icon'  => 'dashicons-chart-pie',
					),
					array(
						'url'   => admin_url( 'admin.php?page=qrms-mm-maliyet' ),
						'title' => __( 'Ürün Maliyetleri', 'qrms' ),
						'desc'  => __( 'Ürün bazında maliyet ve reçete yönetimi.', 'qrms' ),
						'icon'  => 'dashicons-money-alt',
					),
					array(
						'url'   => admin_url( 'admin.php?page=qrms-mm-malzeme' ),
						'title' => __( 'Malzeme Fiyatları', 'qrms' ),
						'desc'  => __( 'Malzeme birim fiyatları ve toplu güncelleme.', 'qrms' ),
						'icon'  => 'dashicons-carrot',
					),
					array(
						'url'   => admin_url( 'admin.php?page=qrms-mm-ayarlar' ),
						'title' => __( 'Ayarlar', 'qrms' ),
						'desc'  => __( 'Popülerlik eşiği, fire ve varsayılan aralık.', 'qrms' ),
						'icon'  => 'dashicons-admin-settings',
					),
				),
			)
		);
	}
}
