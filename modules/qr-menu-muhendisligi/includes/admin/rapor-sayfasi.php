<?php
/**
 * Menü Mühendisliği raporu.
 *
 * Dört kutulu matris + sıralanabilir tam tablo. Matris saf CSS ızgarasıdır,
 * grafik kütüphanesi yüklenmez; her kutu `<details>` olduğu için dar ekranda
 * JavaScript olmadan katlanır.
 *
 * @package QR_Menu_Suite
 */

defined( 'ABSPATH' ) || exit;

/**
 * Rapor ekranını basar.
 *
 * @return void
 */
function qrms_mm_rapor_sayfasi() {
	if ( ! current_user_can( QRMS_Admin::CAPABILITY ) ) {
		wp_die( esc_html__( 'Bu sayfayı görüntüleme yetkiniz yok.', 'qrms' ) );
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- yalnızca okuma; filtreler adresten gelir.
	$args  = QRMS_MM_Rapor::parametreler( wp_unslash( $_GET ) );
	$rapor = QRMS_MM_Rapor::rapor( $args );
	$ozet  = $rapor['ozet'];
	?>
	<div class="wrap qrms-wrap qrms-mm">
		<h1 class="qrms-title"><?php esc_html_e( 'Menü Mühendisliği Raporu', 'qrms' ); ?></h1>

		<?php qrms_mm_filtre_cubugu( $args ); ?>

		<?php
		if ( empty( $rapor['urunler'] ) ) {
			qrms_mm_bos_durum( $rapor );
			qrms_mm_eksik_listesi( $rapor['eksik'] );
			echo '</div>';

			return;
		}
		?>

		<?php if ( 'vekil' === $rapor['kaynak'] ) : ?>
			<div class="qrms-alert qrms-alert-warning">
				<p>
					<strong><?php esc_html_e( 'Yeterli sipariş verisi yok.', 'qrms' ); ?></strong>
					<?php
					printf(
						/* translators: %d: en az sipariş adedi. */
						esc_html__( 'Seçili dönemde %d adetten az sipariş var; popülerlik ürün görüntülenmeleri ve sepete eklemelerden TAHMİN ediliyor. Sipariş biriktikçe rapor kendiliğinden gerçek satışa geçer.', 'qrms' ),
						(int) QRMS_MM_Hesap::MIN_SATIS
					);
					?>
				</p>
			</div>
		<?php endif; ?>

		<?php qrms_mm_ozet_kutulari( $ozet, $rapor['kaynak'] ); ?>
		<?php qrms_mm_matris( $rapor['urunler'], $ozet ); ?>
		<?php qrms_mm_tablo( $rapor['urunler'] ); ?>
		<?php qrms_mm_eksik_listesi( $rapor['eksik'] ); ?>
	</div>
	<?php
}

/**
 * Filtre çubuğu — aralık, kategori, CSV.
 *
 * Filtreler adres satırına yazılır: bağlantı paylaşılabilir ve tarayıcının
 * geri tuşu beklendiği gibi çalışır.
 *
 * @param array $args Parametreler.
 * @return void
 */
function qrms_mm_filtre_cubugu( array $args ) {
	$kategoriler = QRMS_MM_Rapor::kategoriler();
	?>
	<form method="get" class="qrms-mm-filtre">
		<input type="hidden" name="page" value="<?php echo esc_attr( QRMS_MM_RAPOR_SAYFA ); ?>">

		<label class="qrms-mm-filtre-alan">
			<span><?php esc_html_e( 'Dönem', 'qrms' ); ?></span>
			<select name="gun">
				<?php foreach ( QRMS_MM_Rapor::araliklar() as $gun => $etiket ) : ?>
					<option value="<?php echo esc_attr( $gun ); ?>" <?php selected( $args['gun'], $gun ); ?>>
						<?php echo esc_html( $etiket ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</label>

		<label class="qrms-mm-filtre-alan">
			<span><?php esc_html_e( 'Kategori', 'qrms' ); ?></span>
			<select name="kategori">
				<option value="0"><?php esc_html_e( 'Tüm menü', 'qrms' ); ?></option>
				<?php foreach ( $kategoriler as $id => $ad ) : ?>
					<option value="<?php echo esc_attr( $id ); ?>" <?php selected( $args['kategori'], $id ); ?>>
						<?php echo esc_html( $ad ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</label>

		<button type="submit" class="button"><?php esc_html_e( 'Uygula', 'qrms' ); ?></button>

		<a class="button qrms-mm-csv" href="<?php echo esc_url( qrms_mm_csv_url( $args ) ); ?>">
			<span class="dashicons dashicons-download" aria-hidden="true"></span>
			<?php esc_html_e( 'CSV indir', 'qrms' ); ?>
		</a>
	</form>
	<?php
}

/**
 * Hiç ürün hesaplanamadığında ne yapılacağını anlatır.
 *
 * @param array $rapor Rapor.
 * @return void
 */
function qrms_mm_bos_durum( array $rapor ) {
	$eksik_var = ! empty( $rapor['eksik'] );
	?>
	<div class="qrms-card qrms-mm-bos">
		<span class="dashicons dashicons-chart-pie" aria-hidden="true"></span>
		<h2><?php esc_html_e( 'Rapor için henüz veri yok', 'qrms' ); ?></h2>

		<?php if ( $eksik_var ) : ?>
			<p><?php esc_html_e( 'Menünüzdeki ürünlerin fiyatı ya da maliyeti girilmemiş. Bir ürünün kâr katkısı hesaplanamıyorsa matrise giremez.', 'qrms' ); ?></p>
			<p>
				<a class="button button-primary" href="<?php echo esc_url( add_query_arg( array( 'page' => QRMS_MM_MALIYET_SAYFA, 'eksik' => 1 ), admin_url( 'admin.php' ) ) ); ?>">
					<?php esc_html_e( 'Eksik maliyetleri gir', 'qrms' ); ?>
				</a>
			</p>
		<?php else : ?>
			<p><?php esc_html_e( 'Menüde yayınlanmış ürün bulunamadı. Önce Restoran Menü modülünden ürünlerinizi ekleyin.', 'qrms' ); ?></p>
		<?php endif; ?>
	</div>
	<?php
}

/**
 * Özet kutuları.
 *
 * @param array  $ozet   Özet.
 * @param string $kaynak siparis|vekil.
 * @return void
 */
function qrms_mm_ozet_kutulari( array $ozet, $kaynak ) {
	$kutular = array(
		array(
			'label'  => 'siparis' === $kaynak ? __( 'Satış adedi', 'qrms' ) : __( 'Etkileşim skoru', 'qrms' ),
			'value'  => number_format_i18n( $ozet['toplam_adet'] ),
			'accent' => '#7c5cff',
		),
		array(
			'label'  => __( 'Toplam ciro', 'qrms' ),
			'value'  => qrms_mm_para( $ozet['toplam_ciro'] ),
			'accent' => '#2b7cd3',
		),
		array(
			'label'  => __( 'Toplam katkı payı', 'qrms' ),
			'value'  => qrms_mm_para( $ozet['toplam_katki'] ),
			'accent' => '#1f9d55',
		),
		array(
			'label'  => __( 'Ortalama marj', 'qrms' ),
			'value'  => qrms_mm_yuzde( $ozet['ort_marj'] ),
			'accent' => '#e08a1e',
		),
		array(
			'label'  => __( 'Kayıp fırsat', 'qrms' ),
			'value'  => qrms_mm_para( $ozet['kayip_firsat'] ),
			'accent' => '#c0392b',
		),
	);
	?>
	<div class="qrms-mm-ozet">
		<?php foreach ( $kutular as $kutu ) : ?>
			<div class="qrms-mm-ozet-kutu" style="border-left-color:<?php echo esc_attr( $kutu['accent'] ); ?>">
				<span class="qrms-mm-ozet-etiket"><?php echo esc_html( $kutu['label'] ); ?></span>
				<strong class="qrms-mm-ozet-deger"><?php echo esc_html( $kutu['value'] ); ?></strong>
			</div>
		<?php endforeach; ?>
	</div>

	<p class="qrms-muted qrms-mm-esik">
		<?php
		printf(
			/* translators: 1: popülerlik eşiği yüzdesi, 2: ortalama katkı payı. */
			esc_html__( 'Popülerlik eşiği: menü payı %1$s üzerindeki ürünler "çok satan" sayılır. Kârlılık eşiği: birim katkı payı %2$s üzerindekiler "kârlı" sayılır (adetle ağırlıklı ortalama).', 'qrms' ),
			esc_html( qrms_mm_yuzde( $ozet['esik_pay'] ) ),
			esc_html( qrms_mm_para( $ozet['esik_katki'] ) )
		);
		?>
	</p>
	<?php
}

/**
 * Dört kutulu matris.
 *
 * @param array $urunler Ürünler.
 * @param array $ozet    Özet.
 * @return void
 */
function qrms_mm_matris( array $urunler, array $ozet ) {
	$gruplar = array( 'yildiz' => array(), 'is_ati' => array(), 'bulmaca' => array(), 'kopek' => array() );

	foreach ( $urunler as $urun ) {
		$gruplar[ $urun['kutu'] ][] = $urun;
	}

	// Ekrandaki yerleşim: üst satır çok satanlar, sol sütun kârlılar.
	$sira = array( 'yildiz', 'is_ati', 'bulmaca', 'kopek' );
	$adlar = QRMS_MM_Hesap::kutular();
	?>
	<div class="qrms-mm-matris-sarmal">
		<div class="qrms-mm-eksen qrms-mm-eksen-y" aria-hidden="true">
			<?php esc_html_e( 'Kârlılık →', 'qrms' ); ?>
		</div>

		<div class="qrms-mm-matris">
			<?php foreach ( $sira as $kutu ) : ?>
				<details class="qrms-mm-kutu qrms-mm-kutu-<?php echo esc_attr( $kutu ); ?>" open
					style="--qrms-mm-renk:<?php echo esc_attr( QRMS_MM_Hesap::kutu_rengi( $kutu ) ); ?>">
					<summary class="qrms-mm-kutu-basi">
						<span class="dashicons <?php echo esc_attr( QRMS_MM_Hesap::kutu_ikonu( $kutu ) ); ?>" aria-hidden="true"></span>
						<span class="qrms-mm-kutu-ad"><?php echo esc_html( $adlar[ $kutu ] ); ?></span>
						<span class="qrms-mm-kutu-sayi"><?php echo esc_html( number_format_i18n( $ozet['kutular'][ $kutu ] ) ); ?></span>
					</summary>

					<p class="qrms-mm-kutu-anlam"><?php echo esc_html( QRMS_MM_Hesap::kutu_anlami( $kutu ) ); ?></p>

					<?php if ( empty( $gruplar[ $kutu ] ) ) : ?>
						<p class="qrms-muted"><?php esc_html_e( 'Bu kutuda ürün yok.', 'qrms' ); ?></p>
					<?php else : ?>
						<ul class="qrms-mm-kutu-liste">
							<?php foreach ( $gruplar[ $kutu ] as $urun ) : ?>
								<li>
									<a href="#qrms-mm-urun-<?php echo esc_attr( $urun['item_id'] ); ?>">
										<span class="qrms-mm-urun-ad"><?php echo esc_html( $urun['item_name'] ); ?></span>
										<span class="qrms-mm-urun-sayi">
											<?php echo esc_html( number_format_i18n( $urun['adet'] ) ); ?> ·
											<?php echo esc_html( qrms_mm_yuzde( $urun['marj'] ) ); ?>
										</span>
									</a>
								</li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>

					<p class="qrms-mm-kutu-aksiyon"><?php echo esc_html( QRMS_MM_Hesap::aksiyon( $kutu ) ); ?></p>
				</details>
			<?php endforeach; ?>
		</div>

		<div class="qrms-mm-eksen qrms-mm-eksen-x" aria-hidden="true">
			<?php esc_html_e( 'Popülerlik →', 'qrms' ); ?>
		</div>
	</div>
	<?php
}

/**
 * Tam tablo (JS ile sıralanabilir).
 *
 * @param array $urunler Ürünler.
 * @return void
 */
function qrms_mm_tablo( array $urunler ) {
	$adlar = QRMS_MM_Hesap::kutular();

	$sutunlar = array(
		'ad'           => array( 'etiket' => __( 'Ürün', 'qrms' ), 'tip' => 'metin' ),
		'kategori'     => array( 'etiket' => __( 'Kategori', 'qrms' ), 'tip' => 'metin' ),
		'adet'         => array( 'etiket' => __( 'Adet', 'qrms' ), 'tip' => 'sayi' ),
		'fiyat'        => array( 'etiket' => __( 'Fiyat', 'qrms' ), 'tip' => 'sayi' ),
		'maliyet'      => array( 'etiket' => __( 'Maliyet', 'qrms' ), 'tip' => 'sayi' ),
		'katki'        => array( 'etiket' => __( 'Katkı payı', 'qrms' ), 'tip' => 'sayi' ),
		'marj'         => array( 'etiket' => __( 'Marj', 'qrms' ), 'tip' => 'sayi' ),
		'toplam_katki' => array( 'etiket' => __( 'Toplam katkı', 'qrms' ), 'tip' => 'sayi' ),
		'kutu'         => array( 'etiket' => __( 'Kutu', 'qrms' ), 'tip' => 'metin' ),
	);
	?>
	<h2 class="qrms-mm-baslik"><?php esc_html_e( 'Ürün ayrıntısı', 'qrms' ); ?></h2>

	<div class="qrms-mm-tablo-kap">
		<table class="widefat striped qrms-mm-tablo" id="qrms-mm-tablo">
			<thead>
				<tr>
					<?php foreach ( $sutunlar as $anahtar => $sutun ) : ?>
						<th scope="col">
							<button type="button" class="qrms-mm-sirala" data-sutun="<?php echo esc_attr( $anahtar ); ?>" data-tip="<?php echo esc_attr( $sutun['tip'] ); ?>">
								<?php echo esc_html( $sutun['etiket'] ); ?>
								<span class="qrms-mm-ok" aria-hidden="true"></span>
							</button>
						</th>
					<?php endforeach; ?>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $urunler as $urun ) : ?>
					<tr id="qrms-mm-urun-<?php echo esc_attr( $urun['item_id'] ); ?>">
						<td data-etiket="<?php esc_attr_e( 'Ürün', 'qrms' ); ?>" data-deger="<?php echo esc_attr( $urun['item_name'] ); ?>">
							<a href="<?php echo esc_url( get_edit_post_link( $urun['item_id'] ) ); ?>"><?php echo esc_html( $urun['item_name'] ); ?></a>
							<span class="qrms-mm-aksiyon-metin"><?php echo esc_html( $urun['aksiyon'] ); ?></span>
						</td>
						<td data-etiket="<?php esc_attr_e( 'Kategori', 'qrms' ); ?>" data-deger="<?php echo esc_attr( $urun['category_name'] ); ?>"><?php echo esc_html( $urun['category_name'] ); ?></td>
						<td data-etiket="<?php esc_attr_e( 'Adet', 'qrms' ); ?>" data-deger="<?php echo esc_attr( $urun['adet'] ); ?>"><?php echo esc_html( number_format_i18n( $urun['adet'] ) ); ?></td>
						<td data-etiket="<?php esc_attr_e( 'Fiyat', 'qrms' ); ?>" data-deger="<?php echo esc_attr( $urun['fiyat'] ); ?>"><?php echo esc_html( qrms_mm_para( $urun['fiyat'] ) ); ?></td>
						<td data-etiket="<?php esc_attr_e( 'Maliyet', 'qrms' ); ?>" data-deger="<?php echo esc_attr( $urun['maliyet'] ); ?>"><?php echo esc_html( qrms_mm_para( $urun['maliyet'] ) ); ?></td>
						<td data-etiket="<?php esc_attr_e( 'Katkı payı', 'qrms' ); ?>" data-deger="<?php echo esc_attr( $urun['katki'] ); ?>"><?php echo esc_html( qrms_mm_para( $urun['katki'] ) ); ?></td>
						<td data-etiket="<?php esc_attr_e( 'Marj', 'qrms' ); ?>" data-deger="<?php echo esc_attr( $urun['marj'] ); ?>"><?php echo esc_html( qrms_mm_yuzde( $urun['marj'] ) ); ?></td>
						<td data-etiket="<?php esc_attr_e( 'Toplam katkı', 'qrms' ); ?>" data-deger="<?php echo esc_attr( $urun['toplam_katki'] ); ?>"><?php echo esc_html( qrms_mm_para( $urun['toplam_katki'] ) ); ?></td>
						<td data-etiket="<?php esc_attr_e( 'Kutu', 'qrms' ); ?>" data-deger="<?php echo esc_attr( $urun['kutu'] ); ?>">
							<span class="qrms-mm-rozet" style="background:<?php echo esc_attr( QRMS_MM_Hesap::kutu_rengi( $urun['kutu'] ) ); ?>">
								<?php echo esc_html( $adlar[ $urun['kutu'] ] ); ?>
							</span>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
	<?php
}

/**
 * Fiyatı ya da maliyeti eksik ürünler.
 *
 * @param array $eksik Eksik listesi.
 * @return void
 */
function qrms_mm_eksik_listesi( array $eksik ) {
	if ( empty( $eksik ) ) {
		return;
	}
	?>
	<details class="qrms-card qrms-mm-eksik">
		<summary class="qrms-summary">
			<?php
			printf(
				/* translators: %d: ürün sayısı. */
				esc_html__( 'Rapora giremeyen %d ürün', 'qrms' ),
				count( $eksik )
			);
			?>
		</summary>

		<div class="qrms-details-body">
			<p class="qrms-muted"><?php esc_html_e( 'Fiyatı ya da maliyeti girilmemiş ürünlerin kâr katkısı hesaplanamaz; bu yüzden matrise alınmazlar.', 'qrms' ); ?></p>

			<div class="qrms-mm-tablo-kap">
				<table class="widefat striped">
					<thead>
						<tr>
							<th scope="col"><?php esc_html_e( 'Ürün', 'qrms' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Kategori', 'qrms' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Eksik', 'qrms' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $eksik as $urun ) : ?>
							<tr>
								<td data-etiket="<?php esc_attr_e( 'Ürün', 'qrms' ); ?>"><?php echo esc_html( $urun['item_name'] ); ?></td>
								<td data-etiket="<?php esc_attr_e( 'Kategori', 'qrms' ); ?>"><?php echo esc_html( $urun['category_name'] ); ?></td>
								<td data-etiket="<?php esc_attr_e( 'Eksik', 'qrms' ); ?>"><?php echo esc_html( $urun['sebep'] ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>

			<p>
				<a class="button button-primary" href="<?php echo esc_url( add_query_arg( array( 'page' => QRMS_MM_MALIYET_SAYFA, 'eksik' => 1 ), admin_url( 'admin.php' ) ) ); ?>">
					<?php esc_html_e( 'Eksikleri tamamla', 'qrms' ); ?>
				</a>
			</p>
		</div>
	</details>
	<?php
}
