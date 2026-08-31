<?php
/**
 * İstatistikler modülünün HUB EKRANI ve kategori alt sayfalarının tanımı.
 *
 * Modül v1.0'da tek sayfaydı: sol menüdeki "İstatistikler" satırı doğrudan
 * analitik panelini açıyordu. Panel büyüdükçe (zaman kesitleri, masalar,
 * ürünler, sepet, etkileşim…) tek ekran bir tablo yığınına dönüştü. Artık
 * suite'in diğer modülleriyle AYNI deseni kullanır: modül satırı bir hub'dır,
 * her konu kendi alt sayfasındadır (bkz. modules/qr-galeri ve
 * modules/restoran-menu).
 *
 * SOL MENÜ TEK SEVİYELİ KALIR. Alt sayfalar gerçek WordPress sayfaları olarak
 * kaydedilir ama menüye satır EKLEMEZ; QRMS_Admin::hide_module_subpages()
 * onları boyanmadan hemen önce düşürür.
 *
 * PAYLAŞILAN FİLTRE. Kategoriler aynı verinin farklı kesitleridir, bu yüzden
 * zaman aralığı ve masa seçimi sayfalar arasında TAŞINIR; her bağlantı
 * QRMS_Analitik_Filtre::url() üzerinden kurulur (bkz. o sınıfın başlığı).
 *
 * @package QR_Menu_Suite
 */

defined( 'ABSPATH' ) || exit;

/**
 * NOT: "Genel Bakış" (genel-sayfasi.php), "Ürünler" (urunler-sayfasi.php) ve
 * "Masalar" (masalar-sayfasi.php) kategorileri artık doludur ve kendi
 * dosyalarındadır; buradaki placeholder'lar yalnızca henüz taşınmamış
 * kategoriler içindir.
 *
 * Klasik (tek sayfalık) analitik panelinin yeni slug'ı.
 *
 * Kategori sayfaları doldurulana kadar mevcut panel olduğu gibi erişilebilir
 * kalır; hub'da kendi kartı vardır. Kategoriler tamamlandığında bu ekran
 * kalkacak, slug'ı hub'a yönlenecektir.
 */
const QRMS_ANALITIK_KLASIK_SAYFA = 'qrms-an-klasik';

if ( ! function_exists( 'qrms_analitik_onbellek_kutusu' ) ) {

	/**
	 * İSTEK İÇİ ÖNBELLEK — kategori sayfalarının ürettiği listelerin kutusu.
	 *
	 * Ayrı ayrı `static` değişkenler yerine tek bir kutu kullanılır: aynı
	 * istekte ekranı ve CSV'yi besleyen çağrılar aynı veriyi paylaşır (ürün
	 * listesi, kategori adları, masa listesi), ve önbellek gerektiğinde TEK
	 * yerden düşürülebilir.
	 *
	 * @return array Kutuya referans.
	 */
	function &qrms_analitik_onbellek_kutusu() {
		static $kutu = array();

		return $kutu;
	}
}

if ( ! function_exists( 'qrms_analitik_onbellek_sifirla' ) ) {

	/**
	 * İstek içi önbelleği boşaltır.
	 *
	 * @return void
	 */
	function qrms_analitik_onbellek_sifirla() {
		$kutu = &qrms_analitik_onbellek_kutusu();
		$kutu = array();
	}
}

if ( ! function_exists( 'qrms_module_qr_analiz_sayfalar' ) ) {

	/**
	 * Modülün kategori alt sayfaları — TEK KAYNAK.
	 *
	 * Sayfa kaydı (add_submenu_page) ve hub kartları aynı listeden beslenir;
	 * dizideki sıra kart sırasıdır. Her kalem:
	 *
	 *   title  : sayfa başlığı (tarayıcı sekmesi ve "geri" breadcrumb'ı).
	 *   render : sayfayı basan çağrılabilir.
	 *   desc   : hub kartındaki tek cümlelik açıklama.
	 *   icon   : dashicon — EMOJİ DEĞİL (bkz. QRMS_Admin::render_hub başlığı).
	 *
	 * @return array<string,array{title:string,render:callable,desc:string,icon:string}>
	 */
	function qrms_module_qr_analiz_sayfalar() {
		return array(
			'qrms-an-genel'     => array(
				'title'  => __( 'Genel Bakış', 'qrms' ),
				'render' => 'qrms_analitik_sayfa_genel',
				'desc'   => __( 'Menü okutma, tekil ziyaretçi ve zaman içindeki hareket grafiği.', 'qrms' ),
				'icon'   => 'dashicons-dashboard',
			),
			'qrms-an-urunler'   => array(
				'title'  => __( 'Ürünler', 'qrms' ),
				'render' => 'qrms_analitik_sayfa_urunler',
				'desc'   => __( 'En çok ve en az tıklanan ürünler ile kategori dağılımı.', 'qrms' ),
				'icon'   => 'dashicons-food',
			),
			'qrms-an-masalar'   => array(
				'title'  => __( 'Masalar', 'qrms' ),
				'render' => 'qrms_analitik_sayfa_masalar',
				'desc'   => __( 'Hangi masadan kaç hareket geldi; hiç okutulmayan masalar dahil.', 'qrms' ),
				'icon'   => 'dashicons-editor-table',
			),
			'qrms-an-sepet'     => array(
				'title'  => __( 'Sepet & Sipariş', 'qrms' ),
				'render' => 'qrms_analitik_sayfa_sepet',
				'desc'   => __( 'Sepete eklenen, gönderilen ve terk edilen siparişler.', 'qrms' ),
				'icon'   => 'dashicons-cart',
			),
			'qrms-an-etkilesim' => array(
				'title'  => __( 'Müşteri Etkileşimi', 'qrms' ),
				'render' => 'qrms_analitik_sayfa_etkilesim',
				'desc'   => __( 'Chatbot mesajları, yorum ve form gönderimleri, ödül kodları.', 'qrms' ),
				'icon'   => 'dashicons-groups',
			),
			'qrms-an-acilis'    => array(
				'title'  => __( 'Açılış Ekranı', 'qrms' ),
				'render' => 'qrms_analitik_sayfa_acilis',
				'desc'   => __( 'Açılış ekranı gösterimi, menüye geçiş ve atlanma oranları.', 'qrms' ),
				'icon'   => 'dashicons-visibility',
			),
			'qrms-an-sistem'    => array(
				'title'  => __( 'Veri & Sistem', 'qrms' ),
				'render' => 'qrms_analitik_sayfa_sistem',
				'desc'   => __( 'CSV dışa aktarma, saklama süresi, tablo boyutu ve teşhis.', 'qrms' ),
				'icon'   => 'dashicons-admin-tools',
			),
		);
	}
}

if ( ! function_exists( 'qrms_module_qr_analiz_hub_kartlari' ) ) {

	/**
	 * Hub kartları: kategoriler + klasik görünüm.
	 *
	 * Kart adresleri aktif filtreyi taşır — kullanıcı "Son 7 gün + Masa 3"
	 * seçtikten sonra hub'a dönüp başka bir kategoriye girdiğinde seçimi
	 * sıfırlanmaz.
	 *
	 * @return array<int,array{url:string,title:string,desc:string,icon:string}>
	 */
	function qrms_module_qr_analiz_hub_kartlari() {
		$kartlar = array();

		foreach ( qrms_module_qr_analiz_sayfalar() as $slug => $sayfa ) {
			$kartlar[] = array(
				'url'   => QRMS_Analitik_Filtre::url( $slug ),
				'title' => $sayfa['title'],
				'desc'  => $sayfa['desc'],
				'icon'  => $sayfa['icon'],
			);
		}

		// Klasik panel: kategoriler doldurulana kadar tüm veriye erişimin
		// kesintisiz kaldığı yer. Kategori listesinin SONUNDA durur.
		$kartlar[] = array(
			'url'   => QRMS_Analitik_Filtre::url( QRMS_ANALITIK_KLASIK_SAYFA ),
			'title' => __( 'Tüm Veriler (klasik görünüm)', 'qrms' ),
			'desc'  => __( 'Henüz taşınmamış bölüm: veri yönetimi düğmeleri ve teşhis.', 'qrms' ),
			'icon'  => 'dashicons-list-view',
		);

		return $kartlar;
	}
}

if ( ! function_exists( 'qrms_module_qr_analiz_teshis_html' ) ) {

	/**
	 * "Neden veri yok?" kutusunun HTML'i (sorun yoksa boş string).
	 *
	 * Hub'ın `notice` argümanına verilir: kullanıcı hangi kategoriye girmek
	 * üzere olursa olsun, önce engeli görür. Kutu klasik panelde de aynı
	 * bulgularla basılır (bkz. analitik-sayfasi.php) — kaynak tektir:
	 * QRMS_Analitik::teshis().
	 *
	 * @return string wp_kses_post ile basılabilir işaretleme.
	 */
	function qrms_module_qr_analiz_teshis_html() {
		$bulgular = QRMS_Analitik::teshis();

		if ( empty( $bulgular ) ) {
			return '';
		}

		ob_start();

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

		return (string) ob_get_clean();
	}
}

if ( ! function_exists( 'qrms_module_qr_analiz_hub_ozet' ) ) {

	/**
	 * Hub'ın üstündeki özet kutuları.
	 *
	 * Dördü de genel_bakis()'in TEK çağrısından çıkar; o metot sekiz kovayı
	 * iki indeksli sorguda toplar (gerekçesi kendi gövdesindeki uzun yorumda).
	 * Kutular ayrı ayrı sorulsaydı hub açılışı dört ek tarama demek olurdu.
	 *
	 * @return array<int,array{label:string,value:string,url:string,accent:string}>
	 */
	function qrms_module_qr_analiz_hub_ozet() {
		// Tablo hiç kurulamamış olabilir (veritabanı kullanıcısının CREATE
		// yetkisi yoksa). O durumda sorgu çalıştırmak yalnızca hata üretir;
		// kullanıcı zaten teşhis kutusunda nedenini okur.
		if ( ! QRMS_Analitik::tablo_var_mi() ) {
			return array();
		}

		$genel = QRMS_Analitik::genel_bakis( QRMS_Analitik_Filtre::masa() );

		$genel_url  = QRMS_Analitik_Filtre::url( 'qrms-an-genel' );
		$masa_url   = QRMS_Analitik_Filtre::url( 'qrms-an-masalar' );

		return array(
			array(
				'label'  => __( 'Bugün Menü Okutma', 'qrms' ),
				'value'  => number_format_i18n( $genel['mv_bugun'] ),
				'url'    => $genel_url,
				'accent' => '#35d1b4',
			),
			array(
				'label'  => __( 'Tekil Ziyaretçi (bugün)', 'qrms' ),
				'value'  => number_format_i18n( $genel['uv_bugun'] ),
				'url'    => $genel_url,
				'accent' => '#5cb0f0',
			),
			array(
				'label'  => __( 'Bugün Aktif Masa', 'qrms' ),
				'value'  => number_format_i18n( $genel['masa_gun'] ),
				'url'    => $masa_url,
				'accent' => '#f59547',
			),
			array(
				'label'  => __( 'Bu Ay Okutma', 'qrms' ),
				'value'  => number_format_i18n( $genel['mv_ay'] ),
				'url'    => $genel_url,
				'accent' => '#f27cb8',
			),
		);
	}
}

if ( ! function_exists( 'qrms_module_qr_analiz_hub' ) ) {

	/**
	 * "İstatistikler" satırının açtığı hub ekranı.
	 *
	 * @return void
	 */
	function qrms_module_qr_analiz_hub() {
		if ( ! current_user_can( QRMS_Admin::CAPABILITY ) ) {
			wp_die( esc_html__( 'Bu sayfayı görüntüleme yetkiniz yok.', 'qrms' ) );
		}

		QRMS_Admin::render_hub(
			array(
				// Başlık sol menüdeki satırla ve "geri" bağlantısıyla aynı
				// kelimeyi kullanır (QRMS_Helpers::get_module_name).
				'title'  => QRMS_Helpers::get_module_name( 'qr-analiz' ),
				'intro'  => __( 'Menünüzle ilgili sayılar konularına göre ayrıldı. Hangisini merak ediyorsanız kartına dokunun.', 'qrms' ),
				'accent' => '#35d1b4',
				'notice' => qrms_module_qr_analiz_teshis_html(),
				'stats'  => qrms_module_qr_analiz_hub_ozet(),
				'cards'  => qrms_module_qr_analiz_hub_kartlari(),
			)
		);
	}
}

if ( ! function_exists( 'qrms_analitik_hazirlaniyor' ) ) {

	/**
	 * Henüz doldurulmamış kategori sayfasının içeriği.
	 *
	 * Kategoriler mevcut panelden kademe kademe taşınıyor; taşınmayan bölüm
	 * BOŞ EKRAN bırakmaz, ne olacağını söyler ve verinin bugün nerede
	 * durduğunu (klasik görünüm) gösterir.
	 *
	 * @param string $baslik  Sayfa başlığı.
	 * @param string $aciklama Kategorinin tek cümlelik tarifi.
	 * @return void
	 */
	function qrms_analitik_hazirlaniyor( $baslik, $aciklama ) {
		if ( ! current_user_can( QRMS_Admin::CAPABILITY ) ) {
			wp_die( esc_html__( 'Bu sayfayı görüntüleme yetkiniz yok.', 'qrms' ) );
		}
		?>
		<div class="wrap qrms-wrap">
			<h1 class="qrms-title"><?php echo esc_html( $baslik ); ?></h1>

			<div class="qrms-card">
				<p><?php echo esc_html( $aciklama ); ?></p>
				<p class="qrms-muted"><?php esc_html_e( 'Bu bölüm hazırlanıyor. O zamana kadar tüm veriler klasik görünümde duruyor.', 'qrms' ); ?></p>
				<p>
					<a class="qrms-button qrms-button-primary" href="<?php echo esc_url( QRMS_Analitik_Filtre::url( QRMS_ANALITIK_KLASIK_SAYFA ) ); ?>">
						<?php esc_html_e( 'Tüm Veriler (klasik görünüm)', 'qrms' ); ?>
					</a>
				</p>
			</div>
		</div>
		<?php
	}
}

if ( ! function_exists( 'qrms_analitik_sayfa_sepet' ) ) {

	/**
	 * Sepet & Sipariş kategorisi (Faz 7'de doldurulacak).
	 *
	 * @return void
	 */
	function qrms_analitik_sayfa_sepet() {
		$sayfalar = qrms_module_qr_analiz_sayfalar();

		qrms_analitik_hazirlaniyor( $sayfalar['qrms-an-sepet']['title'], $sayfalar['qrms-an-sepet']['desc'] );
	}
}

if ( ! function_exists( 'qrms_analitik_sayfa_etkilesim' ) ) {

	/**
	 * Müşteri Etkileşimi kategorisi (Faz 8'de doldurulacak).
	 *
	 * @return void
	 */
	function qrms_analitik_sayfa_etkilesim() {
		$sayfalar = qrms_module_qr_analiz_sayfalar();

		qrms_analitik_hazirlaniyor( $sayfalar['qrms-an-etkilesim']['title'], $sayfalar['qrms-an-etkilesim']['desc'] );
	}
}

if ( ! function_exists( 'qrms_analitik_sayfa_acilis' ) ) {

	/**
	 * Açılış Ekranı kategorisi (Faz 8'de doldurulacak).
	 *
	 * @return void
	 */
	function qrms_analitik_sayfa_acilis() {
		$sayfalar = qrms_module_qr_analiz_sayfalar();

		qrms_analitik_hazirlaniyor( $sayfalar['qrms-an-acilis']['title'], $sayfalar['qrms-an-acilis']['desc'] );
	}
}

if ( ! function_exists( 'qrms_analitik_sayfa_sistem' ) ) {

	/**
	 * Veri & Sistem kategorisi (Faz 5'te doldurulacak).
	 *
	 * @return void
	 */
	function qrms_analitik_sayfa_sistem() {
		$sayfalar = qrms_module_qr_analiz_sayfalar();

		qrms_analitik_hazirlaniyor( $sayfalar['qrms-an-sistem']['title'], $sayfalar['qrms-an-sistem']['desc'] );
	}
}
