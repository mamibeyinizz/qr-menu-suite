<?php
/**
 * Ürün maliyetleri sayfası.
 *
 * @package QR_Menu_Suite
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'qrms_mm_maliyet_sayfasi' ) ) {
	/**
	 * Maliyet ekranını basar.
	 *
	 * @return void
	 */
	function qrms_mm_maliyet_sayfasi() {
		if ( ! current_user_can( QRMS_Admin::CAPABILITY ) ) {
			wp_die( esc_html__( 'Bu sayfayı görüntüleme yetkiniz yok.', 'qrms' ) );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$arama   = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$kat_f   = isset( $_GET['kategori'] ) ? sanitize_text_field( wp_unslash( $_GET['kategori'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$eksik   = ! empty( $_GET['eksik'] );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$sayfa   = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
		$per     = 50;

		$urunler = QRMS_MM_Maliyet::urun_listesi();
		$filtre  = array();

		foreach ( $urunler as $u ) {
			if ( $eksik && (float) $u['maliyet'] > 0 ) {
				continue;
			}
			if ( '' !== $arama && false === stripos( $u['ad'], $arama ) ) {
				continue;
			}
			if ( '' !== $kat_f && $u['kategori'] !== $kat_f ) {
				continue;
			}
			$filtre[] = $u;
		}

		$toplam_sayfa = max( 1, (int) ceil( count( $filtre ) / $per ) );
		$offset       = ( $sayfa - 1 ) * $per;
		$sayfa_urun   = array_slice( $filtre, $offset, $per );

		$kategoriler = array_unique( array_filter( wp_list_pluck( $urunler, 'kategori' ) ) );
		sort( $kategoriler );

		$malzemeler = array();
		if ( class_exists( 'RMA_Ingredient_Taxonomy' ) ) {
			$terms = get_terms(
				array(
					'taxonomy'   => RMA_Ingredient_Taxonomy::TAXONOMY,
					'hide_empty' => false,
				)
			);
			if ( ! is_wp_error( $terms ) ) {
				foreach ( $terms as $t ) {
					$malzemeler[] = array( 'id' => $t->term_id, 'ad' => $t->name );
				}
			}
		}
		?>
		<div class="wrap qrms-wrap qrms-mm-wrap">
			<h1 class="qrms-title"><?php esc_html_e( 'Ürün Maliyetleri', 'qrms' ); ?></h1>

			<form class="qrms-mm-filtre" method="get">
				<input type="hidden" name="page" value="qrms-mm-maliyet" />
				<input type="search" name="s" value="<?php echo esc_attr( $arama ); ?>" placeholder="<?php esc_attr_e( 'Ürün ara…', 'qrms' ); ?>" />
				<select name="kategori">
					<option value=""><?php esc_html_e( 'Tüm kategoriler', 'qrms' ); ?></option>
					<?php foreach ( $kategoriler as $kat ) : ?>
						<option value="<?php echo esc_attr( $kat ); ?>" <?php selected( $kat_f, $kat ); ?>><?php echo esc_html( $kat ); ?></option>
					<?php endforeach; ?>
				</select>
				<label><input type="checkbox" name="eksik" value="1" <?php checked( $eksik ); ?> /> <?php esc_html_e( 'Yalnızca maliyeti eksik', 'qrms' ); ?></label>
				<button type="submit" class="button"><?php esc_html_e( 'Filtrele', 'qrms' ); ?></button>
			</form>

			<div class="qrms-mm-toplu">
				<label><?php esc_html_e( 'Seçililere maliyet %', 'qrms' ); ?> <input type="number" id="qrms-mm-toplu-yuzde" min="0" max="100" step="0.1" /></label>
				<button type="button" class="button" id="qrms-mm-toplu-uygula"><?php esc_html_e( 'Uygula', 'qrms' ); ?></button>
			</div>

			<div class="qrms-mm-tablo-wrap">
				<table class="widefat striped qrms-mm-maliyet-tablo">
					<thead>
						<tr>
							<th><input type="checkbox" id="qrms-mm-tumunu-sec" /></th>
							<th><?php esc_html_e( 'Ürün', 'qrms' ); ?></th>
							<th><?php esc_html_e( 'Kategori', 'qrms' ); ?></th>
							<th><?php esc_html_e( 'Fiyat', 'qrms' ); ?></th>
							<th><?php esc_html_e( 'Maliyet', 'qrms' ); ?></th>
							<th><?php esc_html_e( 'Katkı', 'qrms' ); ?></th>
							<th><?php esc_html_e( 'Marj %', 'qrms' ); ?></th>
							<th></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $sayfa_urun as $u ) :
							$cm   = $u['fiyat'] - $u['maliyet'];
							$marj = $u['fiyat'] > 0 ? ( $cm / $u['fiyat'] ) * 100 : 0;
							?>
							<tr class="qrms-mm-maliyet-satir" data-id="<?php echo esc_attr( (string) $u['id'] ); ?>" data-fiyat="<?php echo esc_attr( (string) $u['fiyat'] ); ?>">
								<td data-label=""><input type="checkbox" class="qrms-mm-sec" value="<?php echo esc_attr( (string) $u['id'] ); ?>" /></td>
								<td data-label="<?php esc_attr_e( 'Ürün', 'qrms' ); ?>"><?php echo esc_html( $u['ad'] ); ?></td>
								<td data-label="<?php esc_attr_e( 'Kategori', 'qrms' ); ?>"><?php echo esc_html( $u['kategori'] ); ?></td>
								<td data-label="<?php esc_attr_e( 'Fiyat', 'qrms' ); ?>"><?php echo esc_html( number_format_i18n( $u['fiyat'], 2 ) ); ?> ₺</td>
								<td data-label="<?php esc_attr_e( 'Maliyet', 'qrms' ); ?>">
									<input type="number" class="qrms-mm-maliyet-input" step="0.01" min="0" value="<?php echo esc_attr( $u['maliyet'] > 0 ? (string) $u['maliyet'] : '' ); ?>" <?php echo 'recete' === $u['kaynak'] ? 'readonly' : ''; ?> />
								</td>
								<td class="qrms-mm-cm" data-label="<?php esc_attr_e( 'Katkı', 'qrms' ); ?>"><?php echo esc_html( number_format_i18n( $cm, 2 ) ); ?></td>
								<td class="qrms-mm-marj" data-label="<?php esc_attr_e( 'Marj %', 'qrms' ); ?>"><?php echo esc_html( number_format_i18n( $marj, 1 ) ); ?></td>
								<td data-label="">
									<button type="button" class="button qrms-mm-recete-btn"><?php esc_html_e( 'Reçete', 'qrms' ); ?></button>
								</td>
							</tr>
							<tr class="qrms-mm-recete-satir" hidden>
								<td colspan="8">
									<div class="qrms-mm-recete-panel" data-recete="<?php echo esc_attr( wp_json_encode( $u['recete'] ) ); ?>">
										<?php if ( ! empty( $malzemeler ) ) : ?>
											<div class="qrms-mm-recete-satirlari"></div>
											<button type="button" class="button qrms-mm-recete-ekle"><?php esc_html_e( 'Malzeme ekle', 'qrms' ); ?></button>
											<button type="button" class="button button-primary qrms-mm-recete-kaydet"><?php esc_html_e( 'Reçeteyi kaydet', 'qrms' ); ?></button>
										<?php else : ?>
											<p><?php esc_html_e( 'Malzeme taksonomisi bulunamadı. Restoran Menü modülünden malzeme ekleyin.', 'qrms' ); ?></p>
										<?php endif; ?>
									</div>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>

			<?php if ( $toplam_sayfa > 1 ) : ?>
				<div class="tablenav">
					<div class="tablenav-pages">
						<?php
						echo wp_kses_post(
							paginate_links(
								array(
									'base'    => add_query_arg( 'paged', '%#%' ),
									'format'  => '',
									'current' => $sayfa,
									'total'   => $toplam_sayfa,
								)
							)
						);
						?>
					</div>
				</div>
			<?php endif; ?>
		</div>
		<script type="application/json" id="qrms-mm-malzemeler"><?php echo wp_json_encode( $malzemeler ); ?></script>
		<?php
	}
}
