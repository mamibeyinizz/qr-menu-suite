<?php
/**
 * Menü mühendisliği rapor sayfası.
 *
 * @package QR_Menu_Suite
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'qrms_mm_rapor_sayfasi' ) ) {
	/**
	 * Rapor ekranını basar.
	 *
	 * @return void
	 */
	function qrms_mm_rapor_sayfasi() {
		if ( ! current_user_can( QRMS_Admin::CAPABILITY ) ) {
			wp_die( esc_html__( 'Bu sayfayı görüntüleme yetkiniz yok.', 'qrms' ) );
		}

		$ayarlar = QRMS_MM_Maliyet::ayarlar();
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$aralik = isset( $_GET['aralik'] ) ? absint( $_GET['aralik'] ) : (int) $ayarlar['varsayilan_aralik'];
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$ozel_bas = isset( $_GET['bas'] ) ? sanitize_text_field( wp_unslash( $_GET['bas'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$ozel_bit = isset( $_GET['bit'] ) ? sanitize_text_field( wp_unslash( $_GET['bit'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$kategori = isset( $_GET['kategori'] ) ? sanitize_text_field( wp_unslash( $_GET['kategori'] ) ) : '';

		if ( $ozel_bas && $ozel_bit ) {
			$bas = $ozel_bas . ' 00:00:00';
			$bit = $ozel_bit . ' 23:59:59';
		} else {
			$bit = current_time( 'mysql' );
			$bas = gmdate( 'Y-m-d H:i:s', strtotime( '-' . max( 1, $aralik ) . ' days', strtotime( $bit ) ) );
		}

		$rapor = QRMS_MM_Maliyet::rapor_hesapla( $bas, $bit, $kategori );
		$etiketler = QRMS_MM_Hesap::kutu_etiketleri();
		$kutu_renk = array(
			QRMS_MM_Hesap::KUTU_YILDIZ  => '#1f9d55',
			QRMS_MM_Hesap::KUTU_IS_ATI  => '#2b7cd3',
			QRMS_MM_Hesap::KUTU_BULMACA => '#e08a1e',
			QRMS_MM_Hesap::KUTU_KOPEK   => '#c0392b',
		);
		$kutu_aciklama = array(
			QRMS_MM_Hesap::KUTU_YILDIZ  => __( 'Yüksek satış, yüksek kâr — koruyun.', 'qrms' ),
			QRMS_MM_Hesap::KUTU_IS_ATI  => __( 'Yüksek satış, düşük kâr — maliyet veya fiyat.', 'qrms' ),
			QRMS_MM_Hesap::KUTU_BULMACA => __( 'Düşük satış, yüksek kâr — görünürlük artırın.', 'qrms' ),
			QRMS_MM_Hesap::KUTU_KOPEK   => __( 'Düşük satış, düşük kâr — çıkarmayı değerlendirin.', 'qrms' ),
		);

		$kategoriler = array();
		foreach ( QRMS_MM_Maliyet::urun_listesi() as $u ) {
			if ( '' !== $u['kategori'] ) {
				$kategoriler[ $u['kategori'] ] = $u['kategori'];
			}
		}
		sort( $kategoriler );

		$csv_url = wp_nonce_url(
			add_query_arg(
				array(
					'action'   => 'qrms_mm_export_csv',
					'bas'      => $bas,
					'bit'      => $bit,
					'kategori' => $kategori,
				),
				admin_url( 'admin-post.php' )
			),
			'qrms_mm_csv',
			'nonce'
		);
		?>
		<div class="wrap qrms-wrap qrms-mm-wrap">
			<h1 class="qrms-title"><?php esc_html_e( 'Menü Mühendisliği Raporu', 'qrms' ); ?></h1>

			<?php if ( '' !== $rapor['uyari'] ) : ?>
				<div class="qrms-mm-uyari"><?php echo esc_html( $rapor['uyari'] ); ?></div>
			<?php endif; ?>

			<form class="qrms-mm-filtre" method="get">
				<input type="hidden" name="page" value="qrms-mm-rapor" />
				<div class="qrms-mm-filtre-row">
					<label>
						<?php esc_html_e( 'Aralık', 'qrms' ); ?>
						<select name="aralik">
							<?php foreach ( array( 7, 30, 90 ) as $g ) : ?>
								<option value="<?php echo esc_attr( (string) $g ); ?>" <?php selected( $aralik, $g ); ?>><?php echo esc_html( sprintf( __( '%d gün', 'qrms' ), $g ) ); ?></option>
							<?php endforeach; ?>
						</select>
					</label>
					<label><?php esc_html_e( 'Özel başlangıç', 'qrms' ); ?> <input type="date" name="bas" value="<?php echo esc_attr( $ozel_bas ); ?>" /></label>
					<label><?php esc_html_e( 'Özel bitiş', 'qrms' ); ?> <input type="date" name="bit" value="<?php echo esc_attr( $ozel_bit ); ?>" /></label>
					<label>
						<?php esc_html_e( 'Kategori', 'qrms' ); ?>
						<select name="kategori">
							<option value=""><?php esc_html_e( 'Tümü', 'qrms' ); ?></option>
							<?php foreach ( $kategoriler as $kat ) : ?>
								<option value="<?php echo esc_attr( $kat ); ?>" <?php selected( $kategori, $kat ); ?>><?php echo esc_html( $kat ); ?></option>
							<?php endforeach; ?>
						</select>
					</label>
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Filtrele', 'qrms' ); ?></button>
					<a href="<?php echo esc_url( $csv_url ); ?>" class="button"><?php esc_html_e( 'CSV indir', 'qrms' ); ?></a>
				</div>
			</form>

			<?php if ( empty( $rapor['urunler'] ) && empty( $rapor['eksik'] ) ) : ?>
				<div class="qrms-card">
					<p><?php esc_html_e( 'Bu dönemde rapor oluşturmak için yeterli veri yok. Önce ürün maliyetlerini girin ve menüden sipariş alınmaya başlayın.', 'qrms' ); ?></p>
					<p><a href="<?php echo esc_url( admin_url( 'admin.php?page=qrms-mm-maliyet' ) ); ?>"><?php esc_html_e( 'Ürün maliyetlerine git →', 'qrms' ); ?></a></p>
				</div>
			<?php else : ?>
				<div class="qrms-mm-ozet">
					<div class="qrms-mm-ozet-kutu"><span><?php esc_html_e( 'Toplam ciro', 'qrms' ); ?></span><strong><?php echo esc_html( number_format_i18n( $rapor['ozet']['toplam_ciro'], 0 ) ); ?> ₺</strong></div>
					<div class="qrms-mm-ozet-kutu"><span><?php esc_html_e( 'Toplam katkı', 'qrms' ); ?></span><strong><?php echo esc_html( number_format_i18n( $rapor['ozet']['toplam_katkı'], 0 ) ); ?> ₺</strong></div>
					<div class="qrms-mm-ozet-kutu"><span><?php esc_html_e( 'Ort. marj', 'qrms' ); ?></span><strong><?php echo esc_html( number_format_i18n( $rapor['ozet']['ortalama_marj'], 1 ) ); ?>%</strong></div>
					<div class="qrms-mm-ozet-kutu qrms-mm-kayip"><span><?php esc_html_e( 'Kayıp fırsat', 'qrms' ); ?></span><strong><?php echo esc_html( number_format_i18n( $rapor['ozet']['kayip_firsat'], 0 ) ); ?> ₺</strong></div>
				</div>

				<div class="qrms-mm-matris">
					<div class="qrms-mm-matris-eksen">
						<span class="qrms-mm-eksen-y"><?php esc_html_e( '↑ Kârlılık', 'qrms' ); ?></span>
						<span class="qrms-mm-eksen-x"><?php esc_html_e( 'Popülerlik →', 'qrms' ); ?></span>
					</div>
					<?php foreach ( array( QRMS_MM_Hesap::KUTU_YILDIZ, QRMS_MM_Hesap::KUTU_IS_ATI, QRMS_MM_Hesap::KUTU_BULMACA, QRMS_MM_Hesap::KUTU_KOPEK ) as $kutu ) : ?>
						<details class="qrms-mm-kutu" style="--qrms-mm-kutu-renk:<?php echo esc_attr( $kutu_renk[ $kutu ] ); ?>">
							<summary>
								<span class="dashicons dashicons-marker" aria-hidden="true"></span>
								<?php echo esc_html( $etiketler[ $kutu ] ); ?>
								<span class="qrms-mm-kutu-sayi">(<?php echo esc_html( (string) count( $rapor['kutular'][ $kutu ] ) ); ?>)</span>
							</summary>
							<p class="qrms-mm-kutu-aciklama"><?php echo esc_html( $kutu_aciklama[ $kutu ] ); ?></p>
							<ul class="qrms-mm-kutu-liste">
								<?php foreach ( $rapor['kutular'][ $kutu ] as $u ) : ?>
									<li><button type="button" class="qrms-mm-urun-link" data-id="<?php echo esc_attr( (string) $u['id'] ); ?>"><?php echo esc_html( $u['ad'] ); ?> — <?php echo esc_html( (string) $u['satis'] ); ?> / <?php echo esc_html( number_format_i18n( $u['cm'], 0 ) ); ?> ₺ / <?php echo esc_html( number_format_i18n( $u['marj'], 0 ) ); ?>%</button></li>
								<?php endforeach; ?>
							</ul>
						</details>
					<?php endforeach; ?>
				</div>

				<div class="qrms-mm-tablo-wrap">
					<table class="widefat striped qrms-mm-tablo" id="qrms-mm-rapor-tablo">
						<thead>
							<tr>
								<th data-sort="ad"><?php esc_html_e( 'Ürün', 'qrms' ); ?></th>
								<th data-sort="kategori"><?php esc_html_e( 'Kategori', 'qrms' ); ?></th>
								<th data-sort="satis"><?php esc_html_e( 'Satış', 'qrms' ); ?></th>
								<th data-sort="ciro"><?php esc_html_e( 'Ciro', 'qrms' ); ?></th>
								<th data-sort="cm"><?php esc_html_e( 'Katkı', 'qrms' ); ?></th>
								<th data-sort="marj"><?php esc_html_e( 'Marj %', 'qrms' ); ?></th>
								<th><?php esc_html_e( 'Kutu', 'qrms' ); ?></th>
								<th><?php esc_html_e( 'Aksiyon', 'qrms' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $rapor['urunler'] as $u ) : ?>
								<tr data-id="<?php echo esc_attr( (string) $u['id'] ); ?>">
									<td><?php echo esc_html( $u['ad'] ); ?></td>
									<td><?php echo esc_html( $u['kategori'] ); ?></td>
									<td data-val="<?php echo esc_attr( (string) $u['satis'] ); ?>"><?php echo esc_html( (string) $u['satis'] ); ?></td>
									<td data-val="<?php echo esc_attr( (string) $u['ciro'] ); ?>"><?php echo esc_html( number_format_i18n( $u['ciro'], 0 ) ); ?></td>
									<td data-val="<?php echo esc_attr( (string) $u['cm'] ); ?>"><?php echo esc_html( number_format_i18n( $u['cm'], 0 ) ); ?></td>
									<td data-val="<?php echo esc_attr( (string) $u['marj'] ); ?>"><?php echo esc_html( number_format_i18n( $u['marj'], 0 ) ); ?>%</td>
									<td><span class="qrms-mm-rozet" style="background:<?php echo esc_attr( $kutu_renk[ $u['kutu'] ] ); ?>"><?php echo esc_html( $etiketler[ $u['kutu'] ] ); ?></span></td>
									<td class="qrms-mm-aksiyon"><?php echo esc_html( $u['aksiyon'] ); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>

				<?php if ( ! empty( $rapor['eksik'] ) ) : ?>
					<div class="qrms-card qrms-mm-eksik">
						<h2><?php esc_html_e( 'Eksik veri', 'qrms' ); ?></h2>
						<ul>
							<?php foreach ( $rapor['eksik'] as $e ) : ?>
								<li>
									<?php echo esc_html( $e['ad'] ); ?>
									<a href="<?php echo esc_url( admin_url( 'admin.php?page=qrms-mm-maliyet&s=' . rawurlencode( $e['ad'] ) ) ); ?>"><?php esc_html_e( 'Maliyet gir', 'qrms' ); ?></a>
								</li>
							<?php endforeach; ?>
						</ul>
					</div>
				<?php endif; ?>
			<?php endif; ?>
		</div>
		<?php
	}
}
