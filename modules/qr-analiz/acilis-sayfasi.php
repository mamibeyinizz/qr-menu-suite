<?php
/**
 * KATEGORİ: Açılış Ekranı (qrms-an-acilis).
 *
 * Veri kaynağı Faz 8A'nın yazdığı splash_view / splash_action olaylarıdır.
 * Bu sayfa yeni olay ÜRETMEZ.
 *
 * LİSANS. Kategori tamamen qr-acilis-ekrani'ye bağlıdır. Hub kartı lisansta
 * pasifse hiç basılmaz (bkz. qrms_module_qr_analiz_gecerli_sayfalar); sayfa
 * yine kayıtlı kalır ki doğrudan URL boş tablo değil anlamlı bir mesaj
 * göstersin.
 *
 * ORANLAR. Menüye geçiş = splash_action=menu / splash_view. Atlanma =
 * splash_action=atla / splash_view. Payda sıfırken oran 0'dır; bölme
 * yapılmaz.
 *
 * PERFORMANS. Tek GROUP BY (idx_td / idx_masa_td). N+1 yok. Sayaçlar
 * istek içi önbelleklidir.
 *
 * @package QR_Menu_Suite
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'qrms_analitik_acilis_lisansli' ) ) {

	/**
	 * Açılış Ekranı kategorisi bu kurulumda lisanslı mı?
	 *
	 * Modül yükleyicisi yoksa (stub test) evet sayılır.
	 *
	 * @return bool
	 */
	function qrms_analitik_acilis_lisansli() {
		if ( ! class_exists( 'QRMS_Module_Loader' ) ) {
			return true;
		}

		return QRMS_Module_Loader::is_module_active( 'qr-acilis-ekrani' );
	}
}

if ( ! function_exists( 'qrms_analitik_acilis_eylem_etiketleri' ) ) {

	/**
	 * splash_action item_name → okunur etiket.
	 *
	 * @return array<string,string>
	 */
	function qrms_analitik_acilis_eylem_etiketleri() {
		return array(
			'menu'        => __( 'Menüye geçiş', 'qrms' ),
			'atla'        => __( 'Atla', 'qrms' ),
			'wifi'        => __( 'Wi-Fi', 'qrms' ),
			'sosyal'      => __( 'Sosyal', 'qrms' ),
			'odeme'       => __( 'Ödeme', 'qrms' ),
			'rezervasyon' => __( 'Rezervasyon', 'qrms' ),
			'yorum'       => __( 'Yorum', 'qrms' ),
		);
	}
}

if ( ! function_exists( 'qrms_analitik_acilis_hesapla' ) ) {

	/**
	 * GROUP BY satırlarını özet / buton tablosuna çevirir — saf fonksiyon.
	 *
	 * @param array $sayaclar QRMS_Analitik::olay_sayaclari() satırları.
	 * @return array<string,mixed>
	 */
	function qrms_analitik_acilis_hesapla( array $sayaclar ) {
		$ozet = array(
			'view'      => 0,
			'menu'      => 0,
			'atla'      => 0,
			'menu_oran' => 0,
			'atla_oran' => 0,
		);

		$butonlar = array(
			'wifi'        => 0,
			'sosyal'      => 0,
			'odeme'       => 0,
			'rezervasyon' => 0,
			'yorum'       => 0,
		);

		foreach ( $sayaclar as $r ) {
			if ( ! is_array( $r ) ) {
				continue;
			}

			$tip  = isset( $r['event_type'] ) ? (string) $r['event_type'] : '';
			$adet = isset( $r['adet'] ) ? (int) $r['adet'] : 0;
			$ad   = isset( $r['item_name'] ) ? (string) $r['item_name'] : '';

			if ( $adet <= 0 || '' === $tip ) {
				continue;
			}

			if ( 'splash_view' === $tip ) {
				$ozet['view'] += $adet;
			} elseif ( 'splash_action' === $tip ) {
				if ( 'menu' === $ad ) {
					$ozet['menu'] += $adet;
				} elseif ( 'atla' === $ad ) {
					$ozet['atla'] += $adet;
				} elseif ( isset( $butonlar[ $ad ] ) ) {
					$butonlar[ $ad ] += $adet;
				}
			}
		}

		$ozet['menu_oran'] = $ozet['view'] > 0
			? (int) round( ( $ozet['menu'] / $ozet['view'] ) * 100 )
			: 0;

		$ozet['atla_oran'] = $ozet['view'] > 0
			? (int) round( ( $ozet['atla'] / $ozet['view'] ) * 100 )
			: 0;

		$etiket = qrms_analitik_acilis_eylem_etiketleri();
		$satir  = array();

		foreach ( $butonlar as $kod => $adet ) {
			$satir[] = array(
				'kod'  => $kod,
				'ad'   => isset( $etiket[ $kod ] ) ? $etiket[ $kod ] : $kod,
				'adet' => (int) $adet,
				'pay'  => $ozet['view'] > 0 ? (int) round( ( $adet / $ozet['view'] ) * 100 ) : 0,
			);
		}

		$bos = ( 0 === $ozet['view']
			&& 0 === $ozet['menu']
			&& 0 === $ozet['atla']
			&& 0 === array_sum( $butonlar ) );

		return array(
			'ozet'     => $ozet,
			'butonlar' => $satir,
			'bos'      => $bos,
		);
	}
}

if ( ! function_exists( 'qrms_analitik_acilis_verisi' ) ) {

	/**
	 * Sayfanın verisi — TEK yerde toplanır (ekran + CSV).
	 *
	 * @param array  $aralik QRMS_Analitik_Filtre::aralik() çıktısı.
	 * @param string $masa   Masa filtresi.
	 * @return array<string,mixed>
	 */
	function qrms_analitik_acilis_verisi( array $aralik, $masa = '' ) {
		$kutu    = &qrms_analitik_onbellek_kutusu();
		$anahtar = 'acilis|' . $aralik['bas'] . '|' . $aralik['bit'] . '|' . (string) $masa;

		if ( isset( $kutu[ $anahtar ] ) ) {
			return $kutu[ $anahtar ];
		}

		$sayaclar = QRMS_Analitik::olay_sayaclari(
			array( 'splash_view', 'splash_action' ),
			$aralik['bas'],
			$aralik['bit'],
			$masa
		);

		$kutu[ $anahtar ] = qrms_analitik_acilis_hesapla( $sayaclar );

		return $kutu[ $anahtar ];
	}
}

if ( ! function_exists( 'qrms_analitik_sayfa_acilis' ) ) {

	/**
	 * Açılış Ekranı ekranı.
	 *
	 * @return void
	 */
	function qrms_analitik_sayfa_acilis() {
		if ( ! current_user_can( QRMS_Admin::CAPABILITY ) ) {
			wp_die( esc_html__( 'Bu sayfayı görüntüleme yetkiniz yok.', 'qrms' ) );
		}

		if ( ! qrms_analitik_acilis_lisansli() ) {
			?>
			<div class="wrap qrms-an qrms-an-acilis">
				<div class="qrms-an-header">
					<div class="qrms-an-header-text">
						<h1 class="qrms-an-title"><?php esc_html_e( 'Açılış Ekranı', 'qrms' ); ?></h1>
					</div>
				</div>

				<div class="qrms-an-teshis qrms-an-teshis-uyari">
					<span class="qrms-an-teshis-icon dashicons dashicons-lock" aria-hidden="true"></span>
					<div class="qrms-an-teshis-body">
						<h2 class="qrms-an-teshis-title"><?php esc_html_e( 'Bu kategori bu lisansta kapalı', 'qrms' ); ?></h2>
						<p class="qrms-an-teshis-text">
							<?php esc_html_e( 'Açılış ekranı sayıları Açılış Ekranı modülünden gelir. Bu kategori lisansınızda aktif olmadığı için burada tablo yok — boş bir ekran, veri yokmuş gibi görünmesin diye bilinçli olarak basılmıyor.', 'qrms' ); ?>
						</p>
					</div>
				</div>
			</div>
			<?php
			return;
		}

		$csv_url = add_query_arg(
			array(
				'action'   => 'qrms_analitik_csv',
				'kategori' => 'acilis',
				'donem'    => QRMS_Analitik_Filtre::donem(),
				'bas'      => QRMS_Analitik_Filtre::bas(),
				'bit'      => QRMS_Analitik_Filtre::bit(),
				'masa'     => QRMS_Analitik_Filtre::masa(),
				'security' => wp_create_nonce( QRMS_Analitik::NONCE_CSV ),
			),
			admin_url( 'admin-ajax.php' )
		);
		?>
		<div class="wrap qrms-an qrms-an-acilis">

			<div class="qrms-an-header">
				<div class="qrms-an-header-text">
					<h1 class="qrms-an-title"><?php esc_html_e( 'Açılış Ekranı', 'qrms' ); ?></h1>
					<p class="qrms-an-subtitle">
						<?php esc_html_e( 'Gösterim, menüye geçiş, atlanma ve açılış butonlarının kullanımı.', 'qrms' ); ?>
					</p>
				</div>

				<div class="qrms-an-header-actions">
					<a class="qrms-an-btn" href="<?php echo esc_url( $csv_url ); ?>">
						<span class="dashicons dashicons-download" aria-hidden="true"></span>
						<?php esc_html_e( 'Bu sayfayı CSV indir', 'qrms' ); ?>
					</a>
				</div>
			</div>

			<?php qrms_analitik_filtre_cubugu( 'qrms-an-acilis' ); ?>

			<div id="qrms-an-acilis-bos" hidden></div>

			<div class="qrms-an-cards" id="qrms-an-acilis-cards" aria-live="polite">
				<div class="qrms-an-card qrms-an-skeleton"></div>
				<div class="qrms-an-card qrms-an-skeleton"></div>
				<div class="qrms-an-card qrms-an-skeleton"></div>
			</div>

			<div class="qrms-an-panel" id="qrms-an-acilis-buton-panel">
				<div class="qrms-an-panel-header">
					<h2 class="qrms-an-panel-title">
						<span class="dashicons dashicons-screenoptions" aria-hidden="true"></span>
						<?php esc_html_e( 'Buton tıklamaları', 'qrms' ); ?>
					</h2>
				</div>
				<p class="qrms-an-panel-note">
					<?php esc_html_e( 'Wi-Fi, sosyal, ödeme, rezervasyon ve yorum. Pay, gösterime göredir; bir misafir birden fazla butona basabilir.', 'qrms' ); ?>
				</p>
				<div id="qrms-an-acilis-butonlar">
					<div class="qrms-an-loading"><?php esc_html_e( 'Yükleniyor', 'qrms' ); ?></div>
				</div>
			</div>
		</div>
		<?php
	}
}
