<?php
/**
 * Admin menü çatısı: üst menü, çekirdek sayfalar ve modül placeholder'ları.
 *
 * @package QR_Menu_Suite
 */

defined( 'ABSPATH' ) || exit;

/**
 * Admin menüsü ve çekirdek admin ekranları.
 */
class QRMS_Admin {

	/**
	 * Üst seviye menü / Genel Bakış sayfası slug'ı.
	 */
	const MENU_SLUG = 'qrms-overview';

	/**
	 * Genel Ayarlar sayfası slug'ı.
	 */
	const SETTINGS_SLUG = 'qrms-settings';

	/**
	 * Kısa Kodlar rehberi sayfası slug'ı.
	 */
	const SHORTCODES_SLUG = 'qrms-shortcodes';

	/**
	 * Modül sayfalarının slug öneki.
	 */
	const MODULE_PAGE_PREFIX = 'qrms-module-';

	/**
	 * Genel Bakış gruplamasındaki çekirdek (lisansa bağlı olmayan) kalemler.
	 *
	 * Modül slug'ı olmadıkları belli olsun diye iki nokta üst üste taşırlar;
	 * sanitize_key() böyle bir değer üretmediği için bir modül slug'ıyla
	 * asla çakışmazlar.
	 */
	const OVERVIEW_CORE_SHORTCODES = 'core:shortcodes';

	/**
	 * Genel Bakış'taki "Genel Ayarlar" kaleminin anahtarı.
	 */
	const OVERVIEW_CORE_SETTINGS = 'core:settings';

	/**
	 * Sayfaların gerektirdiği yetki.
	 */
	const CAPABILITY = 'manage_options';

	/**
	 * Üst menünün konumu.
	 *
	 * Tam sayı yerine bilinçli olarak ondalıklı bir değer kullanılıyor:
	 * WordPress menü öğelerini $menu dizisinde konumu anahtar yaparak tutar
	 * (ör. $menu['30']). Aynı tam sayı konumunu kullanan başka bir plugin
	 * (ör. eski qr-menu-official) diziye doğrudan yazdığında veya bizden sonra
	 * kaydolduğunda o slotu ezer ve menümüz hiç görünmez. Ondalıklı ve bize
	 * özgü bir konum bu çakışmayı pratikte imkânsız kılar.
	 *
	 * @var float
	 */
	const MENU_POSITION = 57.3;

	/**
	 * Modül slug'ı -> kendi sayfasını basan callback.
	 *
	 * Modüller kendi init'lerinde (plugins_loaded, öncelik 20) kayıt olur;
	 * kayıt olmayan modüller placeholder görmeye devam eder.
	 *
	 * @var array<string,callable>
	 */
	private static $module_pages = array();

	/**
	 * Bir modülün kendi yönetim sayfasını kaydeder.
	 *
	 * Modül yükleyici `admin_menu`'den önce çalıştığı için, register_menu()
	 * alt menüyü kurarken bu kaydı görür ve placeholder yerine modülün kendi
	 * ekranını bağlar.
	 *
	 * @param string   $slug     Modül slug'ı.
	 * @param callable $callback Sayfayı basan çağrılabilir.
	 * @return void
	 */
	public static function register_module_page( $slug, $callback ) {
		if ( ! QRMS_Helpers::is_valid_module( $slug ) || ! is_callable( $callback ) ) {
			return;
		}

		self::$module_pages[ $slug ] = $callback;
	}

	/**
	 * Modülün kayıtlı sayfa callback'i (yoksa null).
	 *
	 * @param string $slug Modül slug'ı.
	 * @return callable|null
	 */
	public static function get_module_page_callback( $slug ) {
		return isset( self::$module_pages[ $slug ] ) ? self::$module_pages[ $slug ] : null;
	}

	/**
	 * Alt sayfa slug'ı -> sahibi modülün slug'ı.
	 *
	 * Modüller kendi alt sayfalarını register_module_subpage() ile kaydeder.
	 * Bu defter iki işe yarar: (1) sol menüde hangi modül satırının vurgulanacağı,
	 * (2) alt sayfanın üstündeki "geri" bağlantısının hedefi.
	 *
	 * @var array<string,string>
	 */
	private static $module_subpages = array();

	/**
	 * Bir modülün alt sayfasını kaydeder ve "geri" bağlantısını ekleyen
	 * sarmalanmış callback'i döndürür.
	 *
	 * Kullanımı add_submenu_page() çağrısının içindedir:
	 *
	 *     add_submenu_page(
	 *         QRMS_Admin::MENU_SLUG, $baslik, $baslik, QRMS_Admin::CAPABILITY, $slug,
	 *         QRMS_Admin::register_module_subpage( 'restoran-menu', $slug, $callback )
	 *     );
	 *
	 * Sayfa GERÇEK bir alt menü olarak kaydolmaya devam eder (parent:
	 * MENU_SLUG); menüde görünmemesi hide_module_subpages() ile, route
	 * çözüldükten SONRA sağlanır. Bkz. o metodun başlığındaki not.
	 *
	 * @param string   $module_slug Modül slug'ı.
	 * @param string   $page_slug   Alt sayfanın slug'ı.
	 * @param callable $callback    Sayfayı basan çağrılabilir.
	 * @return callable Sarmalanmış callback (geçersiz kayıtta orijinali döner).
	 */
	public static function register_module_subpage( $module_slug, $page_slug, $callback ) {
		if ( ! QRMS_Helpers::is_valid_module( $module_slug ) || '' === (string) $page_slug ) {
			return $callback;
		}

		self::$module_subpages[ $page_slug ] = $module_slug;

		if ( ! is_callable( $callback ) ) {
			return $callback;
		}

		return static function () use ( $module_slug, $callback ) {
			QRMS_Admin::render_subpage_back_link( $module_slug );
			call_user_func( $callback );
		};
	}

	/**
	 * Kayıtlı alt sayfaların tamamı (slug => modül slug'ı).
	 *
	 * @return array<string,string>
	 */
	public static function get_module_subpages() {
		return self::$module_subpages;
	}

	/**
	 * Bir sayfa kayıtlı bir modül alt sayfası mı?
	 *
	 * @param string $page_slug Sayfa slug'ı.
	 * @return bool
	 */
	public static function is_module_subpage( $page_slug ) {
		return isset( self::$module_subpages[ $page_slug ] );
	}

	/**
	 * Bir modülün başlangıç (hub) sayfasının tam adresi.
	 *
	 * @param string $module_slug Modül slug'ı.
	 * @return string
	 */
	public static function get_module_page_url( $module_slug ) {
		return admin_url( 'admin.php?page=' . self::get_module_page_slug( $module_slug ) );
	}

	/**
	 * Sol menüde KALACAK satırların slug listesi.
	 *
	 * Tek seviyeli menünün tanımı burasıdır: Genel Bakış, lisansta aktif olan
	 * modüllerin satırları, (varsa) Kısa Kodlar ve Genel Ayarlar. Bunun
	 * dışındaki her satır (modül alt sayfaları, çekirdeğin CPT'den ürettiği
	 * liste satırı, sihirbaz) menüden düşürülür.
	 *
	 * @return string[]
	 */
	public static function get_menu_row_slugs() {
		$slugs = array( self::MENU_SLUG );

		foreach ( QRMS_License_Client::get_active_modules() as $slug ) {
			$slugs[] = self::get_module_page_slug( $slug );
		}

		// Koşul register_menu() ile aynı kaynaktan gelir: satır kaydedilmişse
		// beyaz listede de olur, kaydedilmemişse listede aranmaz.
		if ( QRMS_Shortcodes::has_any() ) {
			$slugs[] = self::SHORTCODES_SLUG;
		}

		$slugs[] = self::SETTINGS_SLUG;

		return $slugs;
	}

	/**
	 * Verilen alt menü satırlarından beyaz listede OLMAYANLARIN slug'ları.
	 *
	 * Saf dizi fonksiyonu (WordPress'e bağımlılığı yok), bu yüzden doğrudan
	 * test edilir.
	 *
	 * @param array    $rows $submenu[MENU_SLUG] satırları.
	 * @param string[] $keep Menüde kalacak slug'lar.
	 * @return string[] Menüden düşürülecek slug'lar.
	 */
	public static function collect_hidden_rows( array $rows, array $keep ) {
		$hidden = array();

		foreach ( $rows as $row ) {
			$slug = isset( $row[2] ) ? $row[2] : '';

			if ( '' === $slug || in_array( $slug, $keep, true ) || in_array( $slug, $hidden, true ) ) {
				continue;
			}

			$hidden[] = $slug;
		}

		return $hidden;
	}

	/**
	 * Modül alt sayfalarını sol menüden düşürür.
	 *
	 * ZAMANLAMA — bu metodun `admin_head`'e bağlı olması bilinçlidir ve
	 * değiştirilmemelidir. WordPress bir admin isteğinde sırasıyla:
	 *
	 *   1. wp-admin/menu.php  — route çözülür; sayfanın hook adı hesaplanırken
	 *      üst menü $submenu içinde ARANIR (get_admin_page_parent). Satır bu
	 *      anda silinmiş olursa hook adı "qr-menu_page_X" yerine
	 *      "admin_page_X" çıkar, $_registered_pages ile eşleşmez ve WordPress
	 *      403 "Bu sayfaya erişmenize izin verilmiyor" der. (Bu, sihirbazın
	 *      v1.0'daki hatasıydı; bkz. QRMS_Wizard::hide_page_from_menu.)
	 *   2. current_screen  — route çözüldü ama sayfa başlığı henüz okunmadı.
	 *   3. admin-header.php -> get_admin_page_title() — başlık yine $submenu
	 *      üzerinden bulunur; satır burada da durmalıdır, yoksa tarayıcı
	 *      sekmesi boş kalır (PHP 8.1+ deprecation uyarısıyla birlikte).
	 *   4. admin_head  <-- BURADAYIZ. Route çözüldü, başlık okundu, sol menü
	 *      HTML'i henüz basılmadı.
	 *   5. menu-header.php — sol menü basılır; satır artık yok.
	 *
	 * Yani satırlar "hiç kaydedilmemiş" değil, "boyanmadan hemen önce
	 * düşürülmüş" olur: sayfa slug'ları, hook adları, yetkiler ve adreslerin
	 * hiçbiri değişmez — yalnızca menüde görünmezler.
	 *
	 * @return void
	 */
	public static function hide_module_subpages() {
		global $submenu;

		if ( empty( $submenu[ self::MENU_SLUG ] ) || ! is_array( $submenu[ self::MENU_SLUG ] ) ) {
			return;
		}

		/**
		 * Sol menüde kalacak satırların slug listesi.
		 *
		 * @param string[] $slugs Varsayılan beyaz liste.
		 */
		$keep = apply_filters( 'qrms_menu_row_slugs', self::get_menu_row_slugs() );

		foreach ( self::collect_hidden_rows( $submenu[ self::MENU_SLUG ], (array) $keep ) as $slug ) {
			remove_submenu_page( self::MENU_SLUG, $slug );
		}
	}

	/**
	 * Modül alt sayfalarındayken üst menüyü "QR Menü" üzerinde tutar.
	 *
	 * Emniyet kemeri: route çözümü $parent_file'ı zaten MENU_SLUG yapar, ama
	 * satır menüden düştüğü için yeniden hesaplayan bir kod (ya da modülün
	 * kendi parent_file filtresi) araya girerse menü vurgusu kaybolurdu.
	 *
	 * @param string $parent_file Çekirdeğin belirlediği üst menü dosyası.
	 * @return string
	 */
	public static function filter_parent_file( $parent_file ) {
		return self::is_module_subpage( self::get_current_page() ) ? self::MENU_SLUG : $parent_file;
	}

	/**
	 * Alt sayfadayken SAHİBİ MODÜLÜN satırını vurgular.
	 *
	 * Açık sayfanın kendi satırı menüde olmadığı için WordPress hiçbir satırı
	 * vurgulayamazdı; kullanıcı "Görünüm" ekranındayken sol menüde "Restoran
	 * Menü" seçili görünür.
	 *
	 * @param string $submenu_file Çekirdeğin belirlediği alt menü dosyası.
	 * @return string
	 */
	public static function filter_submenu_file( $submenu_file ) {
		$page = self::get_current_page();

		return self::is_module_subpage( $page )
			? self::get_module_page_slug( self::$module_subpages[ $page ] )
			: $submenu_file;
	}

	/**
	 * İstekteki `page` parametresi.
	 *
	 * @return string
	 */
	private static function get_current_page() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
	}

	/**
	 * Alt sayfanın en üstündeki "← Modül Adı > Aktif sayfa" breadcrumb'ı.
	 *
	 * Sol menüde artık alt satır olmadığı için modüle dönüşün tek yolu budur;
	 * register_module_subpage() her alt sayfanın önüne otomatik ekler. Aktif
	 * sayfa adı WordPress'in sayfa başlığından gelir (`get_admin_page_title`).
	 *
	 * @param string $module_slug Modül slug'ı.
	 * @return void
	 */
	public static function render_subpage_back_link( $module_slug ) {
		$module_name = QRMS_Helpers::get_module_name( $module_slug );
		$current     = function_exists( 'get_admin_page_title' ) ? get_admin_page_title() : '';

		if ( '' === $current && isset( $GLOBALS['title'] ) ) {
			$current = (string) $GLOBALS['title'];
		}

		if ( $current === $module_name ) {
			$current = '';
		}
		?>
		<div class="qrms-subpage-nav">
			<a class="qrms-back-link" href="<?php echo esc_url( self::get_module_page_url( $module_slug ) ); ?>">
				<span class="dashicons dashicons-arrow-left-alt2" aria-hidden="true"></span>
				<?php echo esc_html( $module_name ); ?>
			</a>
			<?php if ( '' !== $current ) : ?>
				<span class="qrms-subpage-sep" aria-hidden="true">&gt;</span>
				<span class="qrms-subpage-current"><?php echo esc_html( $current ); ?></span>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * ORTAK HUB EKRANI — modül satırının açtığı kart ızgarası.
	 *
	 * Sol menü tek seviyeye indiği için her modülün alt işleri buradan
	 * dallanır. Tüm modüller aynı bileşeni kullanır; modül yalnızca kart
	 * listesini verir, sunum tek yerde durur.
	 *
	 * İkonlar dashicons setinden gelir — emoji DEĞİL. Emoji, admin'in yazı
	 * tipi yığınına ve işletim sistemine göre kutu karakterine düşebiliyor;
	 * dashicons WordPress admin'inde her zaman kayıtlı bir ikon fontudur.
	 *
	 * @param array $args {
	 *     @type string $title  Sayfa başlığı.
	 *     @type string $intro  Tek cümlelik açıklama (opsiyonel).
	 *     @type string $accent Vurgu rengi (opsiyonel, modülün markası).
	 *     @type string $notice Kartların üstünde gösterilecek uyarı HTML'i.
	 *     @type array  $stats  Üstteki özet kutuları: label, value, url, accent.
	 *     @type array  $cards  Kartlar: url, title, desc, icon, badge.
	 * }
	 * @return void
	 */
	public static function render_hub( array $args ) {
		$args = array_merge(
			array(
				'title'  => '',
				'intro'  => '',
				'accent' => '',
				'notice' => '',
				'stats'  => array(),
				'cards'  => array(),
			),
			$args
		);

		$style = '' !== $args['accent'] ? 'style="--qrms-hub-accent:' . esc_attr( $args['accent'] ) . '"' : '';
		?>
		<div class="wrap qrms-hub" <?php echo $style; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
			<h1 class="qrms-hub-heading"><?php echo esc_html( $args['title'] ); ?></h1>

			<?php if ( '' !== $args['intro'] ) : ?>
				<p class="qrms-hub-intro"><?php echo esc_html( $args['intro'] ); ?></p>
			<?php endif; ?>

			<?php if ( '' !== $args['notice'] ) : ?>
				<?php echo wp_kses_post( $args['notice'] ); ?>
			<?php endif; ?>

			<?php if ( ! empty( $args['stats'] ) ) : ?>
				<div class="qrms-hub-stats">
					<?php foreach ( $args['stats'] as $stat ) : ?>
						<div class="qrms-hub-stat" style="border-left-color:<?php echo esc_attr( isset( $stat['accent'] ) ? $stat['accent'] : '#c3c4c7' ); ?>">
							<div class="qrms-hub-stat-label"><?php echo esc_html( $stat['label'] ); ?></div>
							<div class="qrms-hub-stat-value">
								<?php if ( ! empty( $stat['url'] ) ) : ?>
									<a href="<?php echo esc_url( $stat['url'] ); ?>"><?php echo esc_html( $stat['value'] ); ?></a>
								<?php else : ?>
									<?php echo esc_html( $stat['value'] ); ?>
								<?php endif; ?>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<div class="qrms-hub-grid">
				<?php
				/*
				 * Kart gövdesi BLOK elemanlardan kurulur (div/h3/p), span'lerden
				 * değil. HTML5'te <a> akış içeriği taşıyabilir ve fark yalnızca
				 * stil dosyası ulaşmadığında ortaya çıkar: span'lerle kart tek
				 * satıra çöküp okunmaz bir bağlantı metnine dönüşürdü, blok
				 * elemanlarla alt alta dizilmiş okunabilir bir liste kalır.
				 */
				foreach ( $args['cards'] as $card ) :
					?>
					<a class="qrms-hub-card" href="<?php echo esc_url( $card['url'] ); ?>">
						<span class="qrms-hub-icon dashicons <?php echo esc_attr( isset( $card['icon'] ) ? $card['icon'] : 'dashicons-admin-generic' ); ?>" aria-hidden="true"></span>
						<div class="qrms-hub-body">
							<h3 class="qrms-hub-card-title">
								<?php echo esc_html( $card['title'] ); ?>
								<?php if ( ! empty( $card['badge'] ) ) : ?>
									<span class="qrms-hub-badge"><?php echo esc_html( $card['badge'] ); ?></span>
								<?php endif; ?>
							</h3>
							<?php if ( ! empty( $card['desc'] ) ) : ?>
								<p class="qrms-hub-desc"><?php echo esc_html( $card['desc'] ); ?></p>
							<?php endif; ?>
						</div>
					</a>
					<?php
				endforeach;
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * Hook kayıtları.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
		add_action( 'admin_menu', array( __CLASS__, 'ensure_menu_registered' ), 999 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );

		// Modül alt sayfaları menüden yalnızca boyanmadan hemen önce düşürülür;
		// gerekçe için hide_module_subpages() başlığına bakın.
		add_action( 'admin_head', array( __CLASS__, 'hide_module_subpages' ) );
		add_filter( 'parent_file', array( __CLASS__, 'filter_parent_file' ) );
		add_filter( 'submenu_file', array( __CLASS__, 'filter_submenu_file' ) );
	}

	/**
	 * Şu an bu plugin'in ekranlarından birinde miyiz?
	 *
	 * @return bool
	 */
	public static function is_plugin_screen() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';

		return ( '' !== $page && 0 === strpos( $page, 'qrms' ) );
	}

	/**
	 * Modül sayfası slug'ı.
	 *
	 * @param string $slug Modül slug'ı.
	 * @return string
	 */
	public static function get_module_page_slug( $slug ) {
		return self::MODULE_PAGE_PREFIX . $slug;
	}

	/**
	 * Menü ikonu (inline SVG data URI).
	 *
	 * @return string
	 */
	private static function get_menu_icon() {
		$svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="black">'
			. '<path d="M2 2h7v7H2V2zm2 2v3h3V4H4zM11 2h7v7h-7V2zm2 2v3h3V4h-3zM2 11h7v7H2v-7zm2 2v3h3v-3H4z"/>'
			. '<path d="M11 11h3v3h-3v-3zm4 0h3v2h-3v-2zm-4 4h2v3h-2v-3zm3 1h4v2h-4v-2zm2-2h2v2h-2v-2z"/>'
			. '</svg>';

		return 'data:image/svg+xml;base64,' . base64_encode( $svg ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}

	/**
	 * Üst menü, çekirdek sayfalar ve SADECE aktif modüllerin alt menülerini kaydeder.
	 *
	 * @return void
	 */
	public static function register_menu() {
		add_menu_page(
			__( 'QR Menü', 'qrms' ),
			__( 'QR Menü', 'qrms' ),
			self::CAPABILITY,
			self::MENU_SLUG,
			array( __CLASS__, 'render_overview' ),
			self::get_menu_icon(),
			self::MENU_POSITION
		);

		// Genel Bakış: her zaman, en üstte.
		add_submenu_page(
			self::MENU_SLUG,
			__( 'Genel Bakış', 'qrms' ),
			__( 'Genel Bakış', 'qrms' ),
			self::CAPABILITY,
			self::MENU_SLUG,
			array( __CLASS__, 'render_overview' )
		);

		// Modüller: yalnızca lisansta aktif olanlar (sabit sırayla).
		$active = QRMS_License_Client::get_active_modules();

		foreach ( QRMS_Helpers::MODULE_SLUGS as $slug ) {
			if ( ! in_array( $slug, $active, true ) ) {
				continue;
			}

			$name = QRMS_Helpers::get_module_name( $slug );

			/**
			 * Modülün sol menüdeki etiketi.
			 *
			 * Alt satırlar kaldırıldığı için bir modülün dikkat isteyen durumu
			 * (ör. okunmamış form gönderimi rozeti) yalnızca kendi satırında
			 * gösterilebilir. Etiket HTML içerebilir; sayfa başlığı düz metin
			 * kalır.
			 *
			 * @param string $name Modülün görünen adı.
			 * @param string $slug Modül slug'ı.
			 */
			$label = apply_filters( 'qrms_module_menu_label', $name, $slug );

			add_submenu_page(
				self::MENU_SLUG,
				$name,
				$label,
				self::CAPABILITY,
				self::get_module_page_slug( $slug ),
				static function () use ( $slug ) {
					QRMS_Admin::render_module_page( $slug );
				}
			);
		}

		// Kısa Kodlar: modüllerin bildirdiği kısa kodların rehberi. Hiç kısa
		// kod yoksa (aktif modül yoksa) boş bir sayfa menüde yer kaplamaz.
		if ( QRMS_Shortcodes::has_any() ) {
			add_submenu_page(
				self::MENU_SLUG,
				__( 'Kısa Kodlar', 'qrms' ),
				__( 'Kısa Kodlar', 'qrms' ),
				self::CAPABILITY,
				self::SHORTCODES_SLUG,
				array( 'QRMS_Shortcodes', 'render_page' )
			);
		}

		// Genel Ayarlar: her zaman, en altta.
		add_submenu_page(
			self::MENU_SLUG,
			__( 'Genel Ayarlar', 'qrms' ),
			__( 'Genel Ayarlar', 'qrms' ),
			self::CAPABILITY,
			self::SETTINGS_SLUG,
			array( __CLASS__, 'render_settings' )
		);
	}

	/**
	 * Üst menü satırı $menu dizisinde duruyor mu?
	 *
	 * @return bool Menü dizisi henüz kurulmamışsa true döner (karışma).
	 */
	private static function is_menu_present() {
		global $menu;

		if ( ! is_array( $menu ) ) {
			return true;
		}

		foreach ( $menu as $item ) {
			if ( isset( $item[2] ) && self::MENU_SLUG === $item[2] ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Üst menü başka bir plugin tarafından ezildiyse geri ekler.
	 *
	 * Menü satırlarını $menu dizisine doğrudan yazan (konum slotunu ezen)
	 * pluginlere karşı emniyet kemeri; `admin_menu` zincirinin sonunda çalışır.
	 * Sayfalar ilk kayıtta zaten $_registered_pages'e girdiği için burada
	 * yalnızca menüdeki satır geri gelir, sayfa erişimi etkilenmez.
	 *
	 * Menüyü bilerek kaldıran siteler `qrms_ensure_menu_registered` filtresini
	 * false döndürerek bu davranışı kapatabilir.
	 *
	 * @return void
	 */
	public static function ensure_menu_registered() {
		/**
		 * Ezilen üst menünün geri eklenip eklenmeyeceğini belirler.
		 *
		 * @param bool $ensure Varsayılan true.
		 */
		if ( ! apply_filters( 'qrms_ensure_menu_registered', true ) ) {
			return;
		}

		if ( self::is_menu_present() ) {
			return;
		}

		add_menu_page(
			__( 'QR Menü', 'qrms' ),
			__( 'QR Menü', 'qrms' ),
			self::CAPABILITY,
			self::MENU_SLUG,
			array( __CLASS__, 'render_overview' ),
			self::get_menu_icon(),
			self::MENU_POSITION
		);
	}

	/**
	 * Admin CSS/JS dosyalarını sadece bu plugin'in ekranlarında yükler.
	 *
	 * @return void
	 */
	public static function enqueue_assets() {
		// Modül alt sayfalarının bir kısmı `qrms` önekini taşımaz (ör. qr-galeri'nin
		// `qrmgm-*` ekranları); "geri" bağlantısı ve hub stilleri orada da gerekli.
		if ( ! self::is_plugin_screen() && ! self::is_module_subpage( self::get_current_page() ) ) {
			return;
		}

		// Hub kartlarının ve "geri" bağlantısının ikonları dashicons setinden
		// gelir; admin'de zaten kayıtlıdır, burada yalnızca kuyruğa alınır.
		wp_enqueue_style( 'dashicons' );

		wp_enqueue_style(
			'qrms-admin',
			QRMS_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			QRMS_Helpers::asset_version( 'assets/css/admin.css' )
		);

		wp_enqueue_script(
			'qrms-admin',
			QRMS_PLUGIN_URL . 'assets/js/admin.js',
			array(),
			QRMS_Helpers::asset_version( 'assets/js/admin.js' ),
			true
		);

		wp_localize_script(
			'qrms-admin',
			'qrmsAdmin',
			array(
				'validating' => __( 'Doğrulanıyor…', 'qrms' ),
			)
		);
	}

	/**
	 * Genel Bakış'ın kategorileri ve her kategorinin kalemleri.
	 *
	 * Kalem ya bir modül slug'ıdır ('restoran-menu') ya da çekirdek sayfa
	 * anahtarı (self::OVERVIEW_CORE_* sabitleri). Gruplama tek yerde,
	 * BURADA durur; kartların sunumu build_overview_groups() ve
	 * render_overview() içindedir.
	 *
	 * Bir modül birden çok kategoriye konmamalı ve hiçbir kategoride
	 * unutulmamalıdır; ikisi de testle korunur. Yine de unutulan bir modül
	 * ekrandan DÜŞMEZ: build_overview_groups() onu sondaki "Diğer Modüller"
	 * kategorisine alır.
	 *
	 * @return array<int,array{title:string,icon:string,items:string[]}>
	 */
	public static function get_overview_groups() {
		return array(
			array(
				'title' => __( 'Menü & Ürünler', 'qrms' ),
				'icon'  => 'dashicons-food',
				'items' => array( 'restoran-menu', 'qr-galeri', 'qr-acilis-ekrani' ),
			),
			array(
				'title' => __( 'Müşteri Etkileşimi', 'qrms' ),
				'icon'  => 'dashicons-groups',
				'items' => array( 'yorum-feedback', 'qr-chatbot', 'qr-ceviri' ),
			),
			array(
				'title' => __( 'Masa & Servis', 'qrms' ),
				'icon'  => 'dashicons-store',
				'items' => array( 'qr-masa', 'qr-masa-oturum-guvenligi', 'qr-calisma-saatleri' ),
			),
			array(
				'title' => __( 'Analiz & Ayarlar', 'qrms' ),
				'icon'  => 'dashicons-admin-tools',
				'items' => array( 'qr-analiz', self::OVERVIEW_CORE_SHORTCODES, self::OVERVIEW_CORE_SETTINGS ),
			),
		);
	}

	/**
	 * Bir çekirdek sayfanın (lisansa bağlı olmayan kalemin) kart bilgisi.
	 *
	 * @param string $key            Kalem anahtarı.
	 * @param bool   $has_shortcodes Kayıtlı kısa kod var mı?
	 * @return array|null Kart dizisi; kalem gösterilmeyecekse null.
	 */
	private static function get_overview_core_card( $key, $has_shortcodes ) {
		if ( self::OVERVIEW_CORE_SHORTCODES === $key ) {
			// Sol menüdeki satırla aynı koşul: hiç kısa kod yoksa kart da yok.
			if ( ! $has_shortcodes ) {
				return null;
			}

			return array(
				'url'   => admin_url( 'admin.php?page=' . self::SHORTCODES_SLUG ),
				'title' => __( 'Kısa Kodlar', 'qrms' ),
				'desc'  => __( 'Modüllerin sunduğu kısa kodların rehberi.', 'qrms' ),
				'icon'  => 'dashicons-editor-code',
				'state' => 'core',
			);
		}

		if ( self::OVERVIEW_CORE_SETTINGS === $key ) {
			return array(
				'url'   => admin_url( 'admin.php?page=' . self::SETTINGS_SLUG ),
				'title' => __( 'Genel Ayarlar', 'qrms' ),
				'desc'  => __( 'Lisans durumu, API anahtarı ve sunucu adresi.', 'qrms' ),
				'icon'  => 'dashicons-admin-settings',
				'state' => 'core',
			);
		}

		return null;
	}

	/**
	 * Genel Bakış'ta basılacak kategorileri kartlarıyla birlikte kurar.
	 *
	 * Saf fonksiyon: lisans istemcisine ve kısa kod defterine kendisi
	 * bakmaz, ikisini de argüman alır — bu yüzden doğrudan test edilir.
	 *
	 * Pasif modüller de listelenir (soluk, tıklanamaz kart): kullanıcı
	 * lisansında olmayanı da görür. Çekirdek sayfalar lisansa bağlı
	 * olmadığı için 'core' durumundadır ve aktiflik sayacına girmez.
	 *
	 * @param string[] $active         Aktif modül slug'ları.
	 * @param bool     $has_shortcodes Kayıtlı kısa kod var mı?
	 * @return array<int,array{title:string,icon:string,cards:array,total:int,active:int}>
	 */
	public static function build_overview_groups( array $active, $has_shortcodes ) {
		$groups  = array();
		$mapped  = array();
		$modules = QRMS_Helpers::MODULE_SLUGS;

		foreach ( self::get_overview_groups() as $group ) {
			$cards  = array();
			$total  = 0;
			$sayaci = 0;

			foreach ( $group['items'] as $item ) {
				if ( ! QRMS_Helpers::is_valid_module( $item ) ) {
					$card = self::get_overview_core_card( $item, (bool) $has_shortcodes );

					if ( null !== $card ) {
						$cards[] = $card;
					}

					continue;
				}

				$mapped[] = $item;
				++$total;

				$is_active = in_array( $item, $active, true );

				if ( $is_active ) {
					++$sayaci;
				}

				$cards[] = array(
					// Pasif modülün sayfası kayıtlı DEĞİLDİR; adres verilirse
					// kart WordPress'in "izin verilmiyor" ekranına götürürdü.
					'url'   => $is_active ? admin_url( 'admin.php?page=' . self::get_module_page_slug( $item ) ) : '',
					'title' => QRMS_Helpers::get_module_name( $item ),
					'desc'  => QRMS_Helpers::get_module_description( $item ),
					'icon'  => QRMS_Helpers::get_module_icon( $item ),
					'state' => $is_active ? 'active' : 'passive',
					/**
					 * Aktif modülün Genel Bakış kartındaki rozet metni (ör. kırmızı
					 * sayı). Varsayılan boş — hiçbir modül dokunmazsa kart değişmez.
					 * Aynı sayaç kaynağının sol menü ve modül hub'ıyla tutarlı
					 * kalması modülün kendi sorumluluğudur (bkz. restoran-menu'nün
					 * qmo_tukendi_urun_sayisi() merkezi sayacı).
					 *
					 * @param string $badge Varsayılan rozet metni (boş).
					 * @param string $slug  Modül slug'ı.
					 */
					'badge' => $is_active ? (string) apply_filters( 'qrms_module_overview_badge', '', $item ) : '',
				);
			}

			if ( empty( $cards ) ) {
				continue;
			}

			$groups[] = array(
				'title'  => $group['title'],
				'icon'   => $group['icon'],
				'cards'  => $cards,
				'total'  => $total,
				'active' => $sayaci,
			);
		}

		// Emniyet kemeri: yeni bir modül eklenip gruplamaya yazılmayı unutursa
		// ekrandan sessizce kaybolmasın.
		$missing = array_values( array_diff( $modules, $mapped ) );

		if ( ! empty( $missing ) ) {
			$cards  = array();
			$sayaci = 0;

			foreach ( $missing as $slug ) {
				$is_active = in_array( $slug, $active, true );

				if ( $is_active ) {
					++$sayaci;
				}

				$cards[] = array(
					'url'   => $is_active ? admin_url( 'admin.php?page=' . self::get_module_page_slug( $slug ) ) : '',
					'title' => QRMS_Helpers::get_module_name( $slug ),
					'desc'  => QRMS_Helpers::get_module_description( $slug ),
					'icon'  => QRMS_Helpers::get_module_icon( $slug ),
					'state' => $is_active ? 'active' : 'passive',
				);
			}

			$groups[] = array(
				'title'  => __( 'Diğer Modüller', 'qrms' ),
				'icon'   => 'dashicons-admin-generic',
				'cards'  => $cards,
				'total'  => count( $missing ),
				'active' => $sayaci,
			);
		}

		return $groups;
	}

	/**
	 * Genel Bakış ekranı: modüller kategorilere ayrılmış kart ızgarasında.
	 *
	 * Kart görseli modül hub'larıyla ORTAKTIR (.qrms-hub-card): aynı ikon +
	 * başlık + açıklama dizilimi, aynı kırılım noktaları. Genel Bakış'a özgü
	 * tek fark kartın lisans durumudur — aktif kart bağlantı, pasif kart
	 * tıklanamaz bir kutudur.
	 *
	 * @return void
	 */
	public static function render_overview() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'Bu sayfayı görüntüleme yetkiniz yok.', 'qrms' ) );
		}

		$active = QRMS_License_Client::get_active_modules();
		$groups = self::build_overview_groups( $active, QRMS_Shortcodes::has_any() );
		?>
		<div class="wrap qrms-hub qrms-overview">
			<h1 class="qrms-hub-heading"><?php esc_html_e( 'QR Menü — Genel Bakış', 'qrms' ); ?></h1>
			<p class="qrms-hub-intro"><?php esc_html_e( 'Modülleriniz konularına göre gruplandı. Ne yapmak istiyorsanız kartına dokunun.', 'qrms' ); ?></p>

			<?php if ( empty( $active ) ) : ?>
				<div class="qrms-alert qrms-overview-alert">
					<p><?php esc_html_e( 'Henüz aktif modül yok. Lisansınızı doğruladığınızda modülleriniz burada açılır.', 'qrms' ); ?></p>
					<a class="qrms-button qrms-button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::SETTINGS_SLUG ) ); ?>">
						<?php esc_html_e( 'Lisansı Doğrula', 'qrms' ); ?>
					</a>
				</div>
			<?php endif; ?>

			<?php foreach ( $groups as $group ) : ?>
				<section class="qrms-overview-group">
					<h2 class="qrms-overview-group-title">
						<span class="qrms-overview-group-icon dashicons <?php echo esc_attr( $group['icon'] ); ?>" aria-hidden="true"></span>
						<span class="qrms-overview-group-name"><?php echo esc_html( $group['title'] ); ?></span>
						<?php if ( $group['total'] > 0 ) : ?>
							<span class="qrms-overview-group-count">
								<?php
								printf(
									/* translators: 1: aktif modül sayısı, 2: kategorideki toplam modül sayısı. */
									esc_html__( '%1$d/%2$d aktif', 'qrms' ),
									(int) $group['active'],
									(int) $group['total']
								);
								?>
							</span>
						<?php endif; ?>
					</h2>

					<div class="qrms-hub-grid">
						<?php
						foreach ( $group['cards'] as $card ) :
							$is_passive = ( 'passive' === $card['state'] );
							$classes    = 'qrms-hub-card qrms-overview-card qrms-overview-card-' . $card['state'];
							?>
							<?php if ( $is_passive ) : ?>
								<div class="<?php echo esc_attr( $classes ); ?>">
							<?php else : ?>
								<a class="<?php echo esc_attr( $classes ); ?>" href="<?php echo esc_url( $card['url'] ); ?>">
							<?php endif; ?>

								<span class="qrms-hub-icon dashicons <?php echo esc_attr( $card['icon'] ); ?>" aria-hidden="true"></span>

								<div class="qrms-hub-body">
									<h3 class="qrms-hub-card-title">
										<?php echo esc_html( $card['title'] ); ?>
										<?php if ( ! empty( $card['badge'] ) ) : ?>
											<span class="qrms-hub-badge"><?php echo esc_html( $card['badge'] ); ?></span>
										<?php endif; ?>
									</h3>
									<?php if ( '' !== $card['desc'] ) : ?>
										<p class="qrms-hub-desc"><?php echo esc_html( $card['desc'] ); ?></p>
									<?php endif; ?>
								</div>

								<?php if ( 'active' === $card['state'] ) : ?>
									<span class="qrms-overview-state qrms-overview-state-active">
										<span class="qrms-check" aria-hidden="true">&#10003;</span>
										<span class="screen-reader-text"><?php esc_html_e( 'Aktif', 'qrms' ); ?></span>
									</span>
								<?php elseif ( $is_passive ) : ?>
									<span class="qrms-overview-state qrms-overview-state-passive"><?php esc_html_e( 'Pasif', 'qrms' ); ?></span>
								<?php endif; ?>

							<?php if ( $is_passive ) : ?>
								</div>
							<?php else : ?>
								</a>
							<?php endif; ?>
						<?php endforeach; ?>
					</div>
				</section>
			<?php endforeach; ?>
		</div>
		<?php
	}

	/**
	 * Modül sayfası: modül kendi ekranını kaydettiyse onu, aksi halde
	 * placeholder'ı basar.
	 *
	 * @param string $slug Modül slug'ı.
	 * @return void
	 */
	public static function render_module_page( $slug ) {
		$callback = self::get_module_page_callback( $slug );

		if ( null === $callback ) {
			self::render_module_placeholder( $slug );
			return;
		}

		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'Bu sayfayı görüntüleme yetkiniz yok.', 'qrms' ) );
		}

		call_user_func( $callback );
	}

	/**
	 * Modül placeholder sayfası ("Yakında").
	 *
	 * @param string $slug Modül slug'ı.
	 * @return void
	 */
	public static function render_module_placeholder( $slug ) {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'Bu sayfayı görüntüleme yetkiniz yok.', 'qrms' ) );
		}
		?>
		<div class="wrap qrms-wrap">
			<h1 class="qrms-title"><?php echo esc_html( QRMS_Helpers::get_module_name( $slug ) ); ?></h1>

			<div class="qrms-card">
				<p class="qrms-muted"><?php esc_html_e( 'Bu modül yakında burada olacak.', 'qrms' ); ?></p>
			</div>
		</div>
		<?php
	}

	/**
	 * Genel Ayarlar ekranı: lisans durumu ve yeniden doğrulama.
	 *
	 * @return void
	 */
	public static function render_settings() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'Bu sayfayı görüntüleme yetkiniz yok.', 'qrms' ) );
		}

		// Yeniden doğrulama formu gönderildiyse işle (otomatik redirect asla tetiklenmez).
		$result = QRMS_Wizard::handle_submission();

		$status  = QRMS_License_Client::get_last_status();
		$active  = QRMS_License_Client::get_active_modules();
		$is_open = ( is_array( $result ) && 'active' !== $result['status'] );
		?>
		<div class="wrap qrms-wrap">
			<h1 class="qrms-title"><?php esc_html_e( 'Genel Ayarlar', 'qrms' ); ?></h1>

			<?php if ( is_array( $result ) && 'active' === $result['status'] ) : ?>
				<div class="qrms-alert qrms-alert-success">
					<p><?php esc_html_e( 'Lisansınız doğrulandı, modül listeniz güncellendi.', 'qrms' ); ?></p>
				</div>
			<?php endif; ?>

			<div class="qrms-card">
				<h2 class="qrms-card-title"><?php esc_html_e( 'Lisans Durumu', 'qrms' ); ?></h2>

				<ul class="qrms-detail-list">
					<li class="qrms-detail">
						<span class="qrms-detail-label"><?php esc_html_e( 'Durum', 'qrms' ); ?></span>
						<span class="qrms-detail-value qrms-status qrms-status-<?php echo esc_attr( '' !== $status ? $status : 'unknown' ); ?>">
							<?php echo esc_html( QRMS_Helpers::get_status_label( $status ) ); ?>
						</span>
					</li>
					<li class="qrms-detail">
						<span class="qrms-detail-label"><?php esc_html_e( 'Son senkronizasyon', 'qrms' ); ?></span>
						<span class="qrms-detail-value"><?php echo esc_html( QRMS_Helpers::format_datetime( QRMS_License_Client::get_last_sync() ) ); ?></span>
					</li>
					<li class="qrms-detail">
						<span class="qrms-detail-label"><?php esc_html_e( 'Sunucu adresi', 'qrms' ); ?></span>
						<span class="qrms-detail-value qrms-detail-break"><?php echo esc_html( QRMS_License_Client::get_server_url() ); ?></span>
					</li>
					<li class="qrms-detail">
						<span class="qrms-detail-label"><?php esc_html_e( 'Alan adı', 'qrms' ); ?></span>
						<span class="qrms-detail-value qrms-detail-break"><?php echo esc_html( QRMS_Helpers::get_site_domain() ); ?></span>
					</li>
				</ul>
			</div>

			<div class="qrms-card">
				<h2 class="qrms-card-title"><?php esc_html_e( 'Aktif Modüller', 'qrms' ); ?></h2>

				<?php if ( empty( $active ) ) : ?>
					<p class="qrms-muted"><?php esc_html_e( 'Aktif modül bulunmuyor.', 'qrms' ); ?></p>
				<?php else : ?>
					<ul class="qrms-module-list">
						<?php foreach ( $active as $slug ) : ?>
							<li class="qrms-module-list-item">
								<span class="qrms-check" aria-hidden="true">&#10003;</span>
								<?php echo esc_html( QRMS_Helpers::get_module_name( $slug ) ); ?>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</div>

			<details class="qrms-card qrms-details" <?php echo $is_open ? 'open' : ''; ?>>
				<summary class="qrms-summary"><?php esc_html_e( 'Lisansı Yeniden Doğrula', 'qrms' ); ?></summary>

				<div class="qrms-details-body">
					<p class="qrms-muted">
						<?php esc_html_e( 'Anahtarınızı veya sunucu adresinizi değiştirdiyseniz buradan yeniden doğrulayabilirsiniz.', 'qrms' ); ?>
					</p>
					<?php QRMS_Wizard::render_form( $result ); ?>
				</div>
			</details>
		</div>
		<?php
	}
}
