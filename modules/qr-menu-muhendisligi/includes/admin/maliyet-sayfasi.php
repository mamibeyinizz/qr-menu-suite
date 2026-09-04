<?php
/**
 * Ürün Maliyetleri ekranı.
 *
 * Satır içinde düzenlenebilir maliyet alanı; kaydetme AJAX ile yapılır ve
 * katkı payı / marj hücreleri sayfa yenilenmeden güncellenir. Reçete
 * düğmesi satırın altında malzeme + miktar satırlarını açar.
 *
 * Dar ekranda tablo KART LİSTESİNE dönüşür (bkz. assets/css/admin.css);
 * yatay kaydırma yoktur.
 *
 * @package QR_Menu_Suite
 */

defined( 'ABSPATH' ) || exit;

/**
 * Sayfa başına ürün.
 */
const QRMS_MM_SAYFA_BOYUTU = 50;

/**
 * Ekranı basar.
 *
 * @return void
 */
function qrms_mm_maliyet_sayfasi() {
	if ( ! current_user_can( QRMS_Admin::CAPABILITY ) ) {
		wp_die( esc_html__( 'Bu sayfayı görüntüleme yetkiniz yok.', 'qrms' ) );
	}

	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- yalnızca okuma/filtre.
	$arama    = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
	$kategori = isset( $_GET['kategori'] ) ? absint( $_GET['kategori'] ) : 0;
	$yalniz   = ! empty( $_GET['eksik'] );
	$sayfa    = isset( $_GET['sayfa'] ) ? max( 1, absint( $_GET['sayfa'] ) ) : 1;
	// phpcs:enable

	$tumu = QRMS_MM_Rapor::urunler( $kategori );

	if ( '' !== $arama ) {
		$tumu = array_values(
			array_filter(
				$tumu,
				static function ( $urun ) use ( $arama ) {
					return false !== mb_stripos( $urun['item_name'], $arama );
				}
			)
		);
	}

	if ( $yalniz ) {
		$tumu = array_values(
			array_filter(
				$tumu,
				static function ( $urun ) {
					return null === $urun['maliyet'] || null === $urun['fiyat'];
				}
			)
		);
	}

	$toplam   = count( $tumu );
	$sayfalar = max( 1, (int) ceil( $toplam / QRMS_MM_SAYFA_BOYUTU ) );
	$sayfa    = min( $sayfa, $sayfalar );
	$gorunen  = array_slice( $tumu, ( $sayfa - 1 ) * QRMS_MM_SAYFA_BOYUTU, QRMS_MM_SAYFA_BOYUTU );

	$malzemeler = qrms_mm_malzeme_listesi();
	?>
	<div class="wrap qrms-wrap qrms-mm">
		<h1 class="qrms-title"><?php esc_html_e( 'Ürün Maliyetleri', 'qrms' ); ?></h1>

		<p class="qrms-muted">
			<?php esc_html_e( 'Maliyet KDV hariç, tek porsiyon için girilir. Reçete kullanırsanız maliyet malzeme fiyatlarından hesaplanır ve alan salt okunur olur.', 'qrms' ); ?>
		</p>

		<form method="get" class="qrms-mm-filtre">
			<input type="hidden" name="page" value="<?php echo esc_attr( QRMS_MM_MALIYET_SAYFA ); ?>">

			<label class="qrms-mm-filtre-alan">
				<span><?php esc_html_e( 'Ara', 'qrms' ); ?></span>
				<input type="search" name="s" value="<?php echo esc_attr( $arama ); ?>" placeholder="<?php esc_attr_e( 'Ürün adı', 'qrms' ); ?>">
			</label>

			<label class="qrms-mm-filtre-alan">
				<span><?php esc_html_e( 'Kategori', 'qrms' ); ?></span>
				<select name="kategori">
					<option value="0"><?php esc_html_e( 'Tümü', 'qrms' ); ?></option>
					<?php foreach ( QRMS_MM_Rapor::kategoriler() as $id => $ad ) : ?>
						<option value="<?php echo esc_attr( $id ); ?>" <?php selected( $kategori, $id ); ?>><?php echo esc_html( $ad ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>

			<label class="qrms-mm-filtre-onay">
				<input type="checkbox" name="eksik" value="1" <?php checked( $yalniz ); ?>>
				<span><?php esc_html_e( 'Yalnızca eksikler', 'qrms' ); ?></span>
			</label>

			<button type="submit" class="button"><?php esc_html_e( 'Filtrele', 'qrms' ); ?></button>
		</form>

		<?php if ( empty( $gorunen ) ) : ?>
			<div class="qrms-card">
				<p class="qrms-muted"><?php esc_html_e( 'Bu filtreye uyan ürün yok.', 'qrms' ); ?></p>
			</div>
		<?php else : ?>

			<div class="qrms-mm-tablo-kap">
				<table class="widefat striped qrms-mm-maliyet-tablo">
					<thead>
						<tr>
							<th scope="col"><?php esc_html_e( 'Ürün', 'qrms' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Kategori', 'qrms' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Fiyat', 'qrms' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Maliyet (₺)', 'qrms' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Katkı payı', 'qrms' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Marj', 'qrms' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Reçete', 'qrms' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $gorunen as $urun ) : ?>
							<?php qrms_mm_maliyet_satiri( $urun, $malzemeler ); ?>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>

			<?php qrms_mm_sayfalama( $sayfa, $sayfalar, $toplam ); ?>
		<?php endif; ?>
	</div>
	<?php
}

/**
 * Tek ürün satırı + reçete paneli.
 *
 * @param array $urun       Ürün.
 * @param array $malzemeler Malzeme listesi (term_id => ad).
 * @return void
 */
function qrms_mm_maliyet_satiri( array $urun, array $malzemeler ) {
	$id       = $urun['item_id'];
	$fiyat    = $urun['fiyat'];
	$maliyet  = $urun['maliyet'];
	$kaynak   = QRMS_MM_Maliyet::kaynak( $id );
	$recete   = QRMS_MM_Maliyet::recete( $id );
	$katki    = ( null !== $fiyat && null !== $maliyet ) ? $fiyat - $maliyet : null;
	$marj     = ( null !== $katki && $fiyat > 0 ) ? ( $katki / $fiyat ) * 100 : null;
	$receteli = 'recete' === $kaynak;
	?>
	<tr class="qrms-mm-satir" data-urun="<?php echo esc_attr( $id ); ?>">
		<td data-etiket="<?php esc_attr_e( 'Ürün', 'qrms' ); ?>">
			<a href="<?php echo esc_url( get_edit_post_link( $id ) ); ?>"><?php echo esc_html( $urun['item_name'] ); ?></a>
		</td>
		<td data-etiket="<?php esc_attr_e( 'Kategori', 'qrms' ); ?>"><?php echo esc_html( $urun['category_name'] ); ?></td>
		<td data-etiket="<?php esc_attr_e( 'Fiyat', 'qrms' ); ?>">
			<?php if ( null === $fiyat ) : ?>
				<span class="qrms-mm-uyari"><?php esc_html_e( 'Girilmemiş', 'qrms' ); ?></span>
			<?php else : ?>
				<?php echo esc_html( qrms_mm_para( $fiyat ) ); ?>
			<?php endif; ?>
		</td>
		<td data-etiket="<?php esc_attr_e( 'Maliyet (₺)', 'qrms' ); ?>">
			<input
				type="text"
				inputmode="decimal"
				class="qrms-mm-maliyet-alan"
				value="<?php echo esc_attr( null === $maliyet ? '' : $maliyet ); ?>"
				<?php echo $receteli ? 'readonly' : ''; ?>
				aria-label="<?php esc_attr_e( 'Maliyet', 'qrms' ); ?>">
			<span class="qrms-mm-durum" aria-live="polite"></span>
		</td>
		<td data-etiket="<?php esc_attr_e( 'Katkı payı', 'qrms' ); ?>" class="qrms-mm-katki">
			<?php echo esc_html( null === $katki ? '—' : qrms_mm_para( $katki ) ); ?>
		</td>
		<td data-etiket="<?php esc_attr_e( 'Marj', 'qrms' ); ?>" class="qrms-mm-marj">
			<?php echo esc_html( null === $marj ? '—' : qrms_mm_yuzde( $marj ) ); ?>
		</td>
		<td data-etiket="<?php esc_attr_e( 'Reçete', 'qrms' ); ?>">
			<button type="button" class="button button-small qrms-mm-recete-ac" aria-expanded="false">
				<?php echo $receteli ? esc_html__( 'Reçeteli', 'qrms' ) : esc_html__( 'Reçete', 'qrms' ); ?>
			</button>
		</td>
	</tr>

	<tr class="qrms-mm-recete-satir" data-urun="<?php echo esc_attr( $id ); ?>" hidden>
		<td colspan="7">
			<div class="qrms-mm-recete">
				<?php if ( empty( $malzemeler ) ) : ?>
					<p class="qrms-muted">
						<?php esc_html_e( 'Henüz malzeme tanımlı değil. Önce ürünlere malzeme etiketleyin, sonra Malzeme Fiyatları ekranından birim fiyatlarını girin.', 'qrms' ); ?>
					</p>
				<?php else : ?>
					<div class="qrms-mm-recete-satirlar">
						<?php if ( empty( $recete ) ) : ?>
							<?php
							/*
							 * Reçetesi olmayan üründe de BİR boş satır basılır:
							 * "+ Malzeme ekle" düğmesi yeni satırı ilk satırı
							 * kopyalayarak üretir, hiç satır yoksa kopyalayacak
							 * şablon bulamaz ve panel kullanılamaz olurdu.
							 */
							qrms_mm_recete_alan( $malzemeler );
							?>
						<?php else : ?>
							<?php foreach ( $recete as $r ) : ?>
								<?php qrms_mm_recete_alan( $malzemeler, $r['term_id'], $r['miktar'] ); ?>
							<?php endforeach; ?>
						<?php endif; ?>
					</div>

					<div class="qrms-mm-recete-alt">
						<button type="button" class="button button-small qrms-mm-recete-ekle"><?php esc_html_e( '+ Malzeme ekle', 'qrms' ); ?></button>
						<button type="button" class="button button-primary button-small qrms-mm-recete-kaydet"><?php esc_html_e( 'Reçeteyi kaydet ve maliyeti hesapla', 'qrms' ); ?></button>
						<span class="qrms-mm-recete-durum" aria-live="polite"></span>
					</div>

					<p class="qrms-muted">
						<?php esc_html_e( 'Miktarı malzemenin birimine göre yazın: kg fiyatı için gram, litre fiyatı için ml, adet fiyatı için adet.', 'qrms' ); ?>
					</p>
				<?php endif; ?>
			</div>
		</td>
	</tr>
	<?php
}

/**
 * Tek reçete satırı (şablon olarak da kullanılır).
 *
 * @param array $malzemeler Malzemeler.
 * @param int   $secili     Seçili terim.
 * @param float $miktar     Miktar.
 * @return void
 */
function qrms_mm_recete_alan( array $malzemeler, $secili = 0, $miktar = 0 ) {
	$fiyatlar = QRMS_MM_Maliyet::malzeme_fiyatlari();
	$birimler = QRMS_MM_Maliyet::birimler();
	?>
	<div class="qrms-mm-recete-alan">
		<select class="qrms-mm-recete-malzeme" aria-label="<?php esc_attr_e( 'Malzeme', 'qrms' ); ?>">
			<option value="0"><?php esc_html_e( 'Malzeme seçin', 'qrms' ); ?></option>
			<?php foreach ( $malzemeler as $term_id => $ad ) : ?>
				<?php
				$birim   = isset( $fiyatlar[ $term_id ]['birim'] ) ? $fiyatlar[ $term_id ]['birim'] : '';
				$etiket  = $ad;

				if ( '' !== $birim && isset( $birimler[ $birim ] ) ) {
					$etiket .= ' (' . $birimler[ $birim ]['miktar'] . ')';
				} else {
					$etiket .= ' — ' . __( 'fiyatı yok', 'qrms' );
				}
				?>
				<option value="<?php echo esc_attr( $term_id ); ?>" <?php selected( $secili, $term_id ); ?>>
					<?php echo esc_html( $etiket ); ?>
				</option>
			<?php endforeach; ?>
		</select>

		<input
			type="text"
			inputmode="decimal"
			class="qrms-mm-recete-miktar"
			value="<?php echo esc_attr( $miktar > 0 ? $miktar : '' ); ?>"
			placeholder="<?php esc_attr_e( 'Miktar', 'qrms' ); ?>"
			aria-label="<?php esc_attr_e( 'Miktar', 'qrms' ); ?>">

		<button type="button" class="button-link qrms-mm-recete-sil" aria-label="<?php esc_attr_e( 'Satırı kaldır', 'qrms' ); ?>">
			<span class="dashicons dashicons-no-alt" aria-hidden="true"></span>
		</button>
	</div>
	<?php
}

/**
 * Malzeme terimleri.
 *
 * @return array<int,string>
 */
function qrms_mm_malzeme_listesi() {
	$terimler = get_terms(
		array(
			'taxonomy'   => QRMS_MM_Maliyet::malzeme_taksonomisi(),
			'hide_empty' => false,
		)
	);

	$cikti = array();

	if ( is_array( $terimler ) ) {
		foreach ( $terimler as $terim ) {
			if ( is_object( $terim ) && isset( $terim->term_id ) ) {
				$cikti[ (int) $terim->term_id ] = (string) $terim->name;
			}
		}
	}

	return $cikti;
}

/**
 * Sayfalama bağlantıları.
 *
 * @param int $sayfa    Geçerli sayfa.
 * @param int $sayfalar Toplam sayfa.
 * @param int $toplam   Toplam ürün.
 * @return void
 */
function qrms_mm_sayfalama( $sayfa, $sayfalar, $toplam ) {
	if ( $sayfalar < 2 ) {
		return;
	}

	// Adres yalnızca BİLİNEN filtrelerden kurulur; $_GET olduğu gibi
	// taşınsaydı sayfalama bağlantıları rastgele parametreleri geri yansıtırdı.
	// phpcs:disable WordPress.Security.NonceVerification.Recommended
	$mevcut = array(
		'page'     => QRMS_MM_MALIYET_SAYFA,
		's'        => isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '',
		'kategori' => isset( $_GET['kategori'] ) ? absint( $_GET['kategori'] ) : 0,
		'eksik'    => empty( $_GET['eksik'] ) ? 0 : 1,
	);
	// phpcs:enable
	?>
	<nav class="qrms-mm-sayfalama" aria-label="<?php esc_attr_e( 'Sayfalar', 'qrms' ); ?>">
		<span class="qrms-muted">
			<?php
			printf(
				/* translators: 1: geçerli sayfa, 2: toplam sayfa, 3: toplam ürün. */
				esc_html__( 'Sayfa %1$d / %2$d — toplam %3$d ürün', 'qrms' ),
				(int) $sayfa,
				(int) $sayfalar,
				(int) $toplam
			);
			?>
		</span>

		<span class="qrms-mm-sayfalama-baglantilar">
			<?php for ( $i = 1; $i <= $sayfalar; $i++ ) : ?>
				<?php if ( $i === $sayfa ) : ?>
					<span class="qrms-mm-sayfa aktif"><?php echo esc_html( $i ); ?></span>
				<?php else : ?>
					<a class="qrms-mm-sayfa" href="<?php echo esc_url( add_query_arg( array_merge( $mevcut, array( 'sayfa' => $i ) ), admin_url( 'admin.php' ) ) ); ?>">
						<?php echo esc_html( $i ); ?>
					</a>
				<?php endif; ?>
			<?php endfor; ?>
		</span>
	</nav>
	<?php
}
