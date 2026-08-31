<?php
/**
 * KATEGORİ: Ürünler (qrms-an-urunler).
 *
 * Üç bölüm:
 *   1. En çok tıklanan ürünler — klasik panelden taşındı.
 *   2. En az tıklanan ürünler — YENİ. Menüde duran ama kimsenin bakmadığı
 *      ürünleri görünür kılar.
 *   3. Kategori dağılımı — YENİ. Hangi kategori ne kadar ilgi görüyor.
 *
 * İKİ KAYNAĞIN BİRLEŞTİĞİ TEK YER. "En az tıklanan" listesi yalnızca analitik
 * tablosundan üretilemez: hiç tıklanmamış bir ürünün orada SATIRI YOKTUR, oysa
 * asıl aranan ürün tam da odur. Bu yüzden liste yayınlanmış ürünlerden (CPT)
 * başlar, tıklama sayaçları onun üzerine yazılır.
 *
 * PERFORMANS — ürün başına sorgu (N+1) YASAK. Ürün sayısı ne olursa olsun
 * sorgu sayısı SABİTTİR: bir WordPress sorgusu (get_posts; meta ve terim
 * önbellekleri de o çağrıda toplu doldurulur, yani get_post_meta ile
 * get_the_terms ürün başına yeni sorgu AÇMAZ) ve dört analitik sorgusu —
 * tıklama sayaçları, en çok tıklananlar, kategori dağılımı ve kategorisi
 * kaydedilmemiş tıklamaların sayımı. Sonuncusu ayrı durur çünkü dağılım
 * sorgusu LIMIT'lidir: boş adlı kova sıralamada limitin dışında kalabilir ve
 * sessizce kaybolurdu.
 *
 * Eşleştirme ve sıralama PHP tarafındadır. Ürün listesi ayrıca ÜST SINIRLIDIR
 * (QRMS_ANALITIK_URUN_TAVANI) ve sayfalanır; on binlerce ürünlü bir kurulumda
 * ekran tek seferde her şeyi çekmeye çalışmaz.
 *
 * "Detay modalı açılma oranı" bilinçli olarak YOKTUR: modal açılışı şu an
 * ürün tıklamasıyla AYNI olaya yazılıyor (product_click), ikisi ayrıştırılamaz.
 * Ayrı bir olay tipi eklendikten sonra bu sayfaya eklenecek.
 *
 * @package QR_Menu_Suite
 */

defined( 'ABSPATH' ) || exit;

/**
 * "En az tıklanan" listesine alınacak azami ürün sayısı.
 *
 * Sıralama tıklama sayısına göredir ve o sayı analitik tablosunda durur, yani
 * WordPress sorgusuyla sıralanamaz: bütün ürünler çekilip PHP'de sıralanır.
 * Tavan, bu "hepsini çek" adımının sınırsız büyümesini engeller.
 */
const QRMS_ANALITIK_URUN_TAVANI = 2000;

/**
 * Bir sayfada gösterilecek ürün sayısı.
 */
const QRMS_ANALITIK_URUN_SAYFA = 50;

if ( ! function_exists( 'qrms_analitik_yayindaki_urunler' ) ) {

	/**
	 * Yayınlanmış menü ürünleri — istek içi önbellekli.
	 *
	 * Tek get_posts çağrısıdır; WordPress aynı çağrıda meta ve terim
	 * önbelleklerini de doldurur, bu yüzden aşağıdaki get_post_meta ve
	 * get_the_terms çağrıları ürün başına yeni sorgu açmaz.
	 *
	 * @return array<int,array{id:int,ad:string,kategori:string,tukendi:bool}>
	 */
	function qrms_analitik_yayindaki_urunler() {
		$kutu = &qrms_analitik_onbellek_kutusu();

		if ( isset( $kutu['urunler'] ) ) {
			return $kutu['urunler'];
		}

		$kutu['urunler'] = array();

		if ( ! post_type_exists( 'rma_menu_item' ) ) {
			return $kutu['urunler'];
		}

		$kayitlar = get_posts(
			array(
				'post_type'        => 'rma_menu_item',
				'post_status'      => 'publish',
				'posts_per_page'   => QRMS_ANALITIK_URUN_TAVANI,
				'orderby'          => 'title',
				'order'            => 'ASC',
				'no_found_rows'    => true,
				'suppress_filters' => false,
			)
		);

		$urunler = array();

		foreach ( (array) $kayitlar as $kayit ) {
			$terimler = get_the_terms( $kayit->ID, 'rma_category' );
			$kategori = ( ! is_wp_error( $terimler ) && ! empty( $terimler ) ) ? (string) $terimler[0]->name : '';

			$urunler[] = array(
				'id'       => (int) $kayit->ID,
				'ad'       => (string) $kayit->post_title,
				'kategori' => $kategori,
				// "Tükendi" ile "gizli" aynı şey değildir: tükendi ürün menüde
				// görünmeye devam eder. Bayrak restoran-menu modülüyle ORTAKTIR
				// (aynı _rma_tukendi meta'sı; bkz. RMA_Tukendi).
				'tukendi'  => class_exists( 'RMA_Tukendi' )
					? RMA_Tukendi::meta_tukendi_mi( get_post_meta( $kayit->ID, RMA_Tukendi::META, true ) )
					: ( '1' === (string) get_post_meta( $kayit->ID, '_rma_tukendi', true ) ),
			);
		}

		$kutu['urunler'] = $urunler;

		return $urunler;
	}
}

if ( ! function_exists( 'qrms_analitik_en_az_tiklananlar' ) ) {

	/**
	 * En az tıklanan ürünler — hiç tıklanmamışlar dahil.
	 *
	 * @param array $sayaclar QRMS_Analitik::urun_tiklama_sayaclari() çıktısı.
	 * @return array{satirlar:array<int,array<string,mixed>>,toplam:int,tukendi:int,hic:int}
	 */
	function qrms_analitik_en_az_tiklananlar( array $sayaclar ) {
		$satirlar = array();
		$tukendi  = 0;
		$hic      = 0;

		foreach ( qrms_analitik_yayindaki_urunler() as $urun ) {
			$sayac = isset( $sayaclar[ $urun['id'] ] )
				? $sayaclar[ $urun['id'] ]
				: array(
					'toplam' => 0,
					'tekil'  => 0,
					'son'    => '',
				);

			if ( 0 === $sayac['toplam'] ) {
				++$hic;
			}

			if ( $urun['tukendi'] ) {
				++$tukendi;
			}

			$satirlar[] = array(
				'id'       => $urun['id'],
				'ad'       => $urun['ad'],
				'kategori' => $urun['kategori'],
				'tukendi'  => $urun['tukendi'],
				'toplam'   => $sayac['toplam'],
				'tekil'    => $sayac['tekil'],
				'son'      => $sayac['son'],
			);
		}

		// Artan tıklama; eşitlikte ada göre (sıralama her yüklemede aynı
		// olsun diye — aksi hâlde sayfalama satırları kaydırırdı).
		usort(
			$satirlar,
			static function ( $a, $b ) {
				if ( $a['toplam'] === $b['toplam'] ) {
					return strcmp( $a['ad'], $b['ad'] );
				}

				return $a['toplam'] < $b['toplam'] ? -1 : 1;
			}
		);

		return array(
			'satirlar' => $satirlar,
			'toplam'   => count( $satirlar ),
			'tukendi'  => $tukendi,
			'hic'      => $hic,
		);
	}
}

if ( ! function_exists( 'qrms_analitik_mevcut_kategoriler' ) ) {

	/**
	 * Şu an var olan kategori adları (küçük harfe indirgenmiş) — istek içi
	 * önbellekli.
	 *
	 * Dağılım tablosundaki adlar TIKLAMA ANINDAKİ adlardır; kategori sonradan
	 * yeniden adlandırıldıysa listede artık var olmayan bir ad görünür. Bu
	 * küme, o satırları işaretlemeye yarar — sayı düzeltilmez, yalnızca
	 * kullanıcı "böyle bir kategorim yok ki" demesin diye etiketlenir.
	 *
	 * @return array<string,true>
	 */
	function qrms_analitik_mevcut_kategoriler() {
		$kutu = &qrms_analitik_onbellek_kutusu();

		if ( isset( $kutu['kategoriler'] ) ) {
			return $kutu['kategoriler'];
		}

		$kutu['kategoriler'] = array();
		$adlar               = array();

		if ( ! taxonomy_exists( 'rma_category' ) ) {
			return $adlar;
		}

		$terimler = get_terms(
			array(
				'taxonomy'   => 'rma_category',
				'hide_empty' => false,
				'fields'     => 'names',
			)
		);

		if ( is_wp_error( $terimler ) ) {
			return $adlar;
		}

		foreach ( (array) $terimler as $ad ) {
			$adlar[ qrms_analitik_kategori_anahtari( $ad ) ] = true;
		}

		$kutu['kategoriler'] = $adlar;

		return $adlar;
	}
}

if ( ! function_exists( 'qrms_analitik_kategori_anahtari' ) ) {

	/**
	 * Kategori adının karşılaştırma anahtarı.
	 *
	 * Büyük/küçük harf ve baştaki/sondaki boşluk farkı "bu kategori artık yok"
	 * demek değildir; anahtar bu yüzden normalize edilir.
	 *
	 * @param string $ad Kategori adı.
	 * @return string
	 */
	function qrms_analitik_kategori_anahtari( $ad ) {
		$ad = trim( (string) $ad );

		return function_exists( 'mb_strtolower' ) ? mb_strtolower( $ad, 'UTF-8' ) : strtolower( $ad );
	}
}

if ( ! function_exists( 'qrms_analitik_urun_verisi' ) ) {

	/**
	 * Sayfanın üç bölümünün verisi — TEK yerde toplanır.
	 *
	 * Hem ekran (AJAX ucu) hem CSV aynı fonksiyondan beslenir: indirilen dosya
	 * ekranda görünenle birebir aynı olsun diye.
	 *
	 * @param array $aralik QRMS_Analitik_Filtre::aralik() çıktısı.
	 * @param string $masa  Masa filtresi.
	 * @param int    $sayfa En az tıklananlar listesinin sayfası (1'den başlar).
	 * @param int    $limit Sayfa başına ürün (0 = hepsi; CSV bunu kullanır).
	 * @return array<string,mixed>
	 */
	function qrms_analitik_urun_verisi( array $aralik, $masa = '', $sayfa = 1, $limit = QRMS_ANALITIK_URUN_SAYFA ) {
		$sayaclar = QRMS_Analitik::urun_tiklama_sayaclari( $aralik['bas'], $aralik['bit'], $masa );
		$en_az    = qrms_analitik_en_az_tiklananlar( $sayaclar );
		$dagilim  = QRMS_Analitik::kategori_dagilimi( $aralik['bas'], $aralik['bit'], $masa );
		$mevcut   = qrms_analitik_mevcut_kategoriler();

		foreach ( $dagilim['satirlar'] as $i => $satir ) {
			// Taksonomide karşılığı kalmayan ad = kategori yeniden
			// adlandırılmış (ya da silinmiş) demektir.
			$dagilim['satirlar'][ $i ]['eski_ad'] = ! isset( $mevcut[ qrms_analitik_kategori_anahtari( $satir['kategori'] ) ] );
		}

		$sayfa   = max( 1, (int) $sayfa );
		$limit   = max( 0, (int) $limit );
		$satirlar = $en_az['satirlar'];

		if ( $limit > 0 ) {
			$satirlar = array_slice( $satirlar, ( $sayfa - 1 ) * $limit, $limit );
		}

		return array(
			'encok'      => QRMS_Analitik::urun_siralamasi( $aralik['bas'], $aralik['bit'], $masa, 30 ),
			'enaz'       => $satirlar,
			'enazOzet'   => array(
				'toplam'  => $en_az['toplam'],
				'tukendi' => $en_az['tukendi'],
				'hic'     => $en_az['hic'],
				'sayfa'   => $sayfa,
				'sayfalar' => $limit > 0 ? (int) max( 1, ceil( $en_az['toplam'] / $limit ) ) : 1,
				'tavan'   => QRMS_ANALITIK_URUN_TAVANI,
				'dolu'    => $en_az['toplam'] >= QRMS_ANALITIK_URUN_TAVANI,
			),
			'kategoriler' => $dagilim['satirlar'],
			'kategorisiz' => $dagilim['kategorisiz'],
		);
	}
}

if ( ! function_exists( 'qrms_analitik_sayfa_urunler' ) ) {

	/**
	 * Ürünler ekranı.
	 *
	 * @return void
	 */
	function qrms_analitik_sayfa_urunler() {
		if ( ! current_user_can( QRMS_Admin::CAPABILITY ) ) {
			wp_die( esc_html__( 'Bu sayfayı görüntüleme yetkiniz yok.', 'qrms' ) );
		}

		$csv_url = add_query_arg(
			array(
				'action'   => 'qrms_analitik_csv',
				'kategori' => 'urunler',
				'donem'    => QRMS_Analitik_Filtre::donem(),
				'bas'      => QRMS_Analitik_Filtre::bas(),
				'bit'      => QRMS_Analitik_Filtre::bit(),
				'masa'     => QRMS_Analitik_Filtre::masa(),
				'security' => wp_create_nonce( QRMS_Analitik::NONCE_CSV ),
			),
			admin_url( 'admin-ajax.php' )
		);
		?>
		<div class="wrap qrms-an qrms-an-urunler">

			<div class="qrms-an-header">
				<div class="qrms-an-header-text">
					<h1 class="qrms-an-title"><?php esc_html_e( 'Ürünler', 'qrms' ); ?></h1>
					<p class="qrms-an-subtitle">
						<?php esc_html_e( 'Hangi ürünler ilgi görüyor, hangileri menüde öylece duruyor.', 'qrms' ); ?>
					</p>
				</div>

				<div class="qrms-an-header-actions">
					<?php
					/*
					 * Bu sayfanın KENDİ indirmesi: ekranda ne görünüyorsa
					 * (en çok + en az + kategori dağılımı) onu verir. Sistem
					 * sayfasındaki "tümünü indir" ondan ayrıdır.
					 */
					?>
					<a class="qrms-an-btn" href="<?php echo esc_url( $csv_url ); ?>">
						<span class="dashicons dashicons-download" aria-hidden="true"></span>
						<?php esc_html_e( 'Bu sayfayı CSV indir', 'qrms' ); ?>
					</a>
				</div>
			</div>

			<?php qrms_analitik_filtre_cubugu( 'qrms-an-urunler' ); ?>

			<div class="qrms-an-panel">
				<div class="qrms-an-panel-header">
					<h2 class="qrms-an-panel-title">
						<span class="dashicons dashicons-star-filled" aria-hidden="true"></span>
						<?php esc_html_e( 'En Çok Tıklanan Ürünler', 'qrms' ); ?>
					</h2>
				</div>

				<div id="qrms-an-products">
					<div class="qrms-an-loading"><?php esc_html_e( 'Yükleniyor', 'qrms' ); ?></div>
				</div>
			</div>

			<div class="qrms-an-panel">
				<div class="qrms-an-panel-header">
					<h2 class="qrms-an-panel-title">
						<span class="dashicons dashicons-hidden" aria-hidden="true"></span>
						<?php esc_html_e( 'En Az Tıklanan Ürünler', 'qrms' ); ?>
					</h2>
				</div>

				<p class="qrms-an-panel-note">
					<?php esc_html_e( 'Yayındaki bütün ürünler, en az tıklanandan başlayarak. Hiç tıklanmamış ürünler de listededir; "Tükendi" işaretli olanlar tıklanmamış olabilir, o yüzden ayrıca işaretlenir.', 'qrms' ); ?>
				</p>

				<div id="qrms-an-least"></div>
			</div>

			<div class="qrms-an-panel">
				<div class="qrms-an-panel-header">
					<h2 class="qrms-an-panel-title">
						<span class="dashicons dashicons-category" aria-hidden="true"></span>
						<?php esc_html_e( 'Kategori Dağılımı', 'qrms' ); ?>
					</h2>
				</div>

				<p class="qrms-an-panel-note">
					<?php esc_html_e( 'Kategori adı tıklama anında kaydedilir. Bir kategoriyi sonradan yeniden adlandırdıysanız eski kayıtlar eski adla durmaya devam eder; artık var olmayan adlar "eski ad" olarak işaretlenir.', 'qrms' ); ?>
				</p>

				<div id="qrms-an-cats-dist"></div>
			</div>
		</div>
		<?php
	}
}
