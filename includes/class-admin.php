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
	 * Sol menü ve Genel Bakış'ın ORTAK taksonomisi.
	 *
	 * Grup adları, sırası, rengi ve üyelik tek yerde durur. Sol menü
	 * (get_menu_groups) sayfa slug'ına, Genel Bakış (get_overview_groups)
	 * modül slug'ına / çekirdek anahtarına çevirir; ikisi de buradan türer.
	 *
	 * Menü tek seviyeli kalır (alt menü YOKTUR); başlıklar sayfa değildir:
	 * `admin_head`'de satırlara yazılan grup sınıfını okuyup ilgili başlığı
	 * (ve aç/kapa düğmesini) DOM'a assets/js/admin-menu.js ekler.
	 *
	 * PALET. Renk yalnızca ikonda ve satırın sol kenar şeridindedir; satır
	 * ARKA PLANI koyu temanın kendi rengi olarak kalır. Üst menüdeki "QR Menü"
	 * rozeti mavi–mor gradyandır; onunla yarışmasın diye Menü Yönetimi mavisi
	 * daha açık bir gök mavisine, Görünüm & Erişim ise mordan uzaklaşıp pembeye
	 * çekildi.
	 *
	 * Kalemler ya bir modül slug'ıdır ('restoran-menu') ya da çekirdek sayfa
	 * anahtarı (MENU_SLUG / OVERVIEW_CORE_*). Listede olmayan bir satır
	 * gruplanmaz; sırasını koruyarak en alta düşer.
	 *
	 * @return array<int,array{key:string,title:string,accent:string,icon:string,items:string[]}>
	 */
	public static function get_nav_groups() {
		return array(
			array(
				'key'    => 'menu-yonetimi',
				'title'  => __( 'Menü Yönetimi', 'qrms' ),
				'accent' => '#5cb0f0',
				'icon'   => 'dashicons-food',
				'items'  => array( 'restoran-menu', 'yorum-feedback' ),
			),
			array(
				'key'    => 'araclar',
				'title'  => __( 'Araçlar', 'qrms' ),
				'accent' => '#35d1b4',
				'icon'   => 'dashicons-admin-tools',
				'items'  => array(
					'qr-masa',
					'qr-masa-oturum-guvenligi',
					'qr-analiz',
					'qr-galeri',
					'qr-ceviri',
					'qr-chatbot',
					'qr-calisma-saatleri',
				),
			),
			array(
				'key'    => 'gorunum',
				'title'  => __( 'Görünüm & Erişim', 'qrms' ),
				'accent' => '#f27cb8',
				'icon'   => 'dashicons-visibility',
				'items'  => array( 'qr-acilis-ekrani', 'header-footer-builder' ),
			),
			array(
				'key'    => 'genel',
				'title'  => __( 'Genel', 'qrms' ),
				'accent' => '#9ba7b4',
				'icon'   => 'dashicons-dashboard',
				'items'  => array( self::MENU_SLUG, self::OVERVIEW_CORE_SHORTCODES, self::OVERVIEW_CORE_SETTINGS ),
			),
		);
	}

	/**
	 * Sol menünün grupları — get_nav_groups() çıktısını sayfa slug'ına çevirir.
	 *
	 * @return array<int,array{key:string,title:string,accent:string,items:string[]}>
	 */
	public static function get_menu_groups() {
		$groups = array();

		foreach ( self::get_nav_groups() as $group ) {
			$items = array();

			foreach ( (array) $group['items'] as $item ) {
				$slug = self::nav_item_menu_slug( $item );

				if ( '' !== $slug ) {
					$items[] = $slug;
				}
			}

			$groups[] = array(
				'key'    => $group['key'],
				'title'  => $group['title'],
				'accent' => $group['accent'],
				'items'  => $items,
			);
		}

		return $groups;
	}

	/**
	 * Taksonomi kalemini sol menü satır slug'ına çevirir.
	 *
	 * @param string $item Modül slug'ı, çekirdek anahtarı veya sayfa slug'ı.
	 * @return string
	 */
	private static function nav_item_menu_slug( $item ) {
		if ( QRMS_Helpers::is_valid_module( $item ) ) {
			return self::get_module_page_slug( $item );
		}

		if ( self::OVERVIEW_CORE_SHORTCODES === $item ) {
			return self::SHORTCODES_SLUG;
		}

		if ( self::OVERVIEW_CORE_SETTINGS === $item ) {
			return self::SETTINGS_SLUG;
		}

		return (string) $item;
	}

	/**
	 * Bir menü satırının dashicon'u.
	 *
	 * Modül satırlarında ikon zaten Genel Bakış kartlarıyla ortaktır
	 * (QRMS_Helpers::get_module_meta); çekirdek sayfaların ikonu da oradaki
	 * kartlarla aynı seçilmiştir ki iki ekran aynı şeyi aynı simgeyle anlatsın.
	 *
	 * @param string $slug Sayfa slug'ı.
	 * @return string Bilinmeyen satırda boş metin (ikon basılmaz).
	 */
	public static function get_menu_row_icon( $slug ) {
		$core = array(
			self::MENU_SLUG       => 'dashicons-dashboard',
			self::SETTINGS_SLUG   => 'dashicons-admin-settings',
			self::SHORTCODES_SLUG => 'dashicons-editor-code',
		);

		if ( isset( $core[ $slug ] ) ) {
			return $core[ $slug ];
		}

		$prefix = self::MODULE_PAGE_PREFIX;

		if ( 0 === strpos( (string) $slug, $prefix ) ) {
			$module = substr( (string) $slug, strlen( $prefix ) );

			return QRMS_Helpers::is_valid_module( $module ) ? QRMS_Helpers::get_module_icon( $module ) : '';
		}

		return '';
	}

	/**
	 * Menü satırlarını gruplara göre sıralar, ikon ve grup sınıfı ekler.
	 *
	 * Saf dizi fonksiyonu (WordPress'e bağımlılığı yok), bu yüzden doğrudan
	 * test edilir. Üç şey yapar:
	 *
	 *   1. Satırları get_menu_groups() sırasına sokar; grupta olmayan satırlar
	 *      birbirlerine göre sıralarını koruyarak sona düşer.
	 *   2. Etiketin başına dashicon ekler (etiket HTML taşıyabilir; modülün
	 *      rozeti bozulmadan içeride kalır).
	 *   3. Satıra `qrms-menu-item qrms-mg-<grup>` sınıflarını yazar. WordPress
	 *      alt menü dizisinin 4. dizinini `<li>` ve `<a>`'nın class'ına
	 *      geçirir (bkz. wp-admin/menu-header.php); renk şeridi ile başlıkları
	 *      kuran JavaScript bu sınıfı okur.
	 *
	 * @param array $rows   $submenu[MENU_SLUG] satırları.
	 * @param array $groups get_menu_groups() çıktısı.
	 * @return array Yeniden sıralanmış satırlar.
	 */
	public static function build_menu_rows( array $rows, array $groups ) {
		$order   = array();
		$classes = array();
		$sira    = 0;

		foreach ( $groups as $group ) {
			if ( empty( $group['key'] ) || empty( $group['items'] ) ) {
				continue;
			}

			foreach ( (array) $group['items'] as $slug ) {
				$order[ $slug ]   = $sira++;
				$classes[ $slug ] = 'qrms-menu-item qrms-mg-' . sanitize_key( $group['key'] );
			}
		}

		$gruplu = array();
		$kalan  = array();

		foreach ( $rows as $row ) {
			$slug = isset( $row[2] ) ? $row[2] : '';

			if ( '' === $slug || ! isset( $order[ $slug ] ) ) {
				$kalan[] = $row;
				continue;
			}

			$row[0] = self::decorate_menu_title( isset( $row[0] ) ? $row[0] : '', $slug );
			$row[4] = trim( ( isset( $row[4] ) ? $row[4] . ' ' : '' ) . $classes[ $slug ] );

			// Aynı slug birden çok satırda geçerse ikisi de kalsın; sıralama
			// anahtarı satırı EZMEMELİ.
			$gruplu[ $order[ $slug ] ][] = $row;
		}

		ksort( $gruplu );

		$sirali = array();

		foreach ( $gruplu as $satirlar ) {
			foreach ( $satirlar as $row ) {
				$sirali[] = $row;
			}
		}

		return array_merge( $sirali, $kalan );
	}

	/**
	 * Menü etiketinin başına kategori renginde bir dashicon koyar.
	 *
	 * Etiket HTML taşıyabildiği için (qrms_module_menu_label rozeti) mevcut
	 * değer olduğu gibi bir sarmalayıcının içine alınır.
	 *
	 * @param string $title Mevcut etiket.
	 * @param string $slug  Sayfa slug'ı.
	 * @return string
	 */
	private static function decorate_menu_title( $title, $slug ) {
		$icon = self::get_menu_row_icon( $slug );

		if ( '' === $icon || false !== strpos( (string) $title, 'qrms-menu-icon' ) ) {
			return $title;
		}

		return '<span class="qrms-menu-icon dashicons ' . esc_attr( $icon ) . '" aria-hidden="true"></span>'
			. '<span class="qrms-menu-label">' . $title . '</span>';
	}

	/**
	 * Sol menü satırlarını gruplandırır.
	 *
	 * Zamanlaması hide_module_subpages() ile aynı gerekçeye dayanır (bkz. o
	 * metodun başlığı): route ve başlık çözüldükten sonra, menü boyanmadan
	 * önce. Gizleme 10, gruplama 11 önceliğindedir — önce menüden düşecek
	 * satırlar düşer, sonra kalanlar sıraya girer.
	 *
	 * @return void
	 */
	public static function group_menu_rows() {
		global $submenu;

		if ( empty( $submenu[ self::MENU_SLUG ] ) || ! is_array( $submenu[ self::MENU_SLUG ] ) ) {
			return;
		}

		/**
		 * Sol menünün grupları (sıra, başlık, renk).
		 *
		 * @param array $groups Varsayılan gruplama.
		 */
		$groups = apply_filters( 'qrms_menu_groups', self::get_menu_groups() );

		$submenu[ self::MENU_SLUG ] = self::build_menu_rows( $submenu[ self::MENU_SLUG ], (array) $groups );
	}

	/**
	 * Grup renklerini CSS değişkenine çeviren satır içi stil.
	 *
	 * Renkler tek yerde (get_menu_groups) dursun diye stil dosyasına
	 * yazılmaz; dosya yalnızca `var(--qrms-menu-accent)` kullanır.
	 *
	 * @param array $groups get_menu_groups() çıktısı.
	 * @return string
	 */
	public static function build_menu_accent_css( array $groups ) {
		$css = '';

		foreach ( $groups as $group ) {
			if ( empty( $group['key'] ) || empty( $group['accent'] ) ) {
				continue;
			}

			// Filtreden geçen bir değer stil dosyasına sızmasın: yalnızca
			// düz bir hex renk kabul edilir.
			if ( ! preg_match( '/^#(?:[0-9a-f]{3}|[0-9a-f]{6}|[0-9a-f]{8})$/i', (string) $group['accent'] ) ) {
				continue;
			}

			$css .= '#adminmenu .qrms-mg-' . sanitize_key( $group['key'] )
				. '{--qrms-menu-accent:' . $group['accent'] . ';}';
		}

		return $css;
	}

	/**
	 * Sol menünün stil ve betiği — HER admin ekranında.
	 *
	 * Menü her sayfada görünür; enqueue_assets() gibi yalnızca eklentinin
	 * ekranlarında yüklenirse gruplar diğer sayfalarda dağılırdı. Dosyalar
	 * bunun için küçük ve bağımsız tutulur (yönetim ekranlarının stili
	 * assets/css/admin.css'te kalır).
	 *
	 * @return void
	 */
	public static function enqueue_menu_assets() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return;
		}

		wp_enqueue_style(
			'qrms-admin-menu',
			QRMS_PLUGIN_URL . 'assets/css/admin-menu.css',
			array(),
			QRMS_Helpers::asset_version( 'assets/css/admin-menu.css' )
		);

		/** This filter is documented in includes/class-admin.php */
		$groups = (array) apply_filters( 'qrms_menu_groups', self::get_menu_groups() );

		wp_add_inline_style( 'qrms-admin-menu', self::build_menu_accent_css( $groups ) );

		wp_enqueue_script(
			'qrms-admin-menu',
			QRMS_PLUGIN_URL . 'assets/js/admin-menu.js',
			array(),
			QRMS_Helpers::asset_version( 'assets/js/admin-menu.js' ),
			true
		);

		$basliklar = array();

		foreach ( $groups as $group ) {
			if ( empty( $group['key'] ) ) {
				continue;
			}

			$basliklar[] = array(
				'key'   => sanitize_key( $group['key'] ),
				'title' => isset( $group['title'] ) ? (string) $group['title'] : '',
			);
		}

		wp_localize_script(
			'qrms-admin-menu',
			'qrmsMenu',
			array(
				'groups'      => $basliklar,
				'collapse'    => __( 'Bölümü aç/kapat', 'qrms' ),
				// Üst "QR Menü" ile "Genel Bakış" aynı sayfadır; gruplar açık gelsin.
				'openAll'     => self::MENU_SLUG === self::get_current_page(),
				// WordPress üst menünün href'ini ilk alt satırdan üretir; gruplama
				// sonrası o satır Restoran Menü olur. Betik href'i Genel Bakış'a çeker.
				'overviewUrl' => admin_url( 'admin.php?page=' . self::MENU_SLUG ),
			)
		);
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

		/**
		 * Alt sayfadaki "geri" bağlantısının hedefi.
		 *
		 * Varsayılan hedef modülün hub ekranıdır. Alt sayfaları arasında
		 * DURUM taşıyan modüller (ör. İstatistikler'in ortak zaman aralığı ve
		 * masa filtresi) adrese kendi query arg'larını ekleyebilsin diye
		 * filtrelenir; aksi hâlde hub'a dönmek seçimi sıfırlardı.
		 *
		 * @param string $url         Modülün hub adresi.
		 * @param string $module_slug Alt sayfanın sahibi modül.
		 */
		$url = (string) apply_filters( 'qrms_subpage_back_url', self::get_module_page_url( $module_slug ), $module_slug );

		if ( '' === $current && isset( $GLOBALS['title'] ) ) {
			$current = (string) $GLOBALS['title'];
		}

		if ( $current === $module_name ) {
			$current = '';
		}
		?>
		<div class="qrms-subpage-nav">
			<a class="qrms-back-link" href="<?php echo esc_url( $url ); ?>">
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
	 *     @type string $title       Sayfa başlığı.
	 *     @type string $intro       Tek cümlelik açıklama (opsiyonel).
	 *     @type string $accent      Vurgu rengi (opsiyonel, modülün markası).
	 *     @type string $class       Wrap'e eklenecek ek sınıf (ör. qrms-overview).
	 *     @type string $notice      Kartların üstünde gösterilecek uyarı HTML'i.
	 *     @type array  $stats       Üstteki özet kutuları: düz liste
	 *                               ({label,value,url,accent,class}) ya da satırlı
	 *                               ({title,items:array}).
	 *     @type array  $cards       Kartlar (tek ızgara, başlıksız): url, title, desc, icon, badge.
	 *     @type array  $card_groups Kartlar başlıklı bölümler hâlinde: her öge
	 *                               {title:string, cards:array} — verilirse
	 *                               $cards yok sayılır, her grup kendi
	 *                               `<h2>` başlığı + ayrı bir ızgarayla basılır.
	 * }
	 * @return void
	 */
	public static function render_hub( array $args ) {
		$args = array_merge(
			array(
				'title'       => '',
				'intro'       => '',
				'accent'      => '',
				'class'       => '',
				'notice'      => '',
				'stats'       => array(),
				'cards'       => array(),
				'card_groups' => array(),
			),
			$args
		);

		$style = '' !== $args['accent'] ? 'style="--qrms-hub-accent:' . esc_attr( $args['accent'] ) . '"' : '';
		$wrap  = 'wrap qrms-hub';
		if ( '' !== $args['class'] ) {
			$wrap .= ' ' . $args['class'];
		}
		?>
		<div class="<?php echo esc_attr( $wrap ); ?>" <?php echo $style; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
			<h1 class="qrms-hub-heading"><?php echo esc_html( $args['title'] ); ?></h1>

			<?php if ( '' !== $args['intro'] ) : ?>
				<p class="qrms-hub-intro"><?php echo esc_html( $args['intro'] ); ?></p>
			<?php endif; ?>

			<?php if ( '' !== $args['notice'] ) : ?>
				<?php echo wp_kses_post( $args['notice'] ); ?>
			<?php endif; ?>

			<?php self::render_hub_stats( (array) $args['stats'] ); ?>

			<?php if ( ! empty( $args['card_groups'] ) ) : ?>
				<?php foreach ( $args['card_groups'] as $group ) : ?>
					<?php self::render_hub_group( $group ); ?>
				<?php endforeach; ?>
			<?php else : ?>
				<div class="qrms-hub-grid">
					<?php foreach ( $args['cards'] as $card ) : ?>
						<?php self::render_hub_card( $card ); ?>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Hub üstündeki özet kutuları — düz liste veya başlıklı satırlar.
	 *
	 * Düz liste (modül hub'ları): [{label,value,url,accent}, ...].
	 * Satırlı (Genel Bakış): [{title,items:array}, ...] — boş satır basılmaz.
	 *
	 * @param array $stats Özet kutuları.
	 * @return void
	 */
	private static function render_hub_stats( array $stats ) {
		if ( empty( $stats ) ) {
			return;
		}

		$grouped = isset( $stats[0]['items'] ) && is_array( $stats[0]['items'] );

		if ( ! $grouped ) {
			echo '<div class="qrms-hub-stats">';
			foreach ( $stats as $stat ) {
				self::render_hub_stat( $stat );
			}
			echo '</div>';
			return;
		}

		$rows = array();
		foreach ( $stats as $row ) {
			if ( empty( $row['items'] ) || ! is_array( $row['items'] ) ) {
				continue;
			}
			$rows[] = $row;
		}

		if ( empty( $rows ) ) {
			return;
		}

		echo '<div class="qrms-hub-stats qrms-hub-stats-stacked">';
		foreach ( $rows as $row ) {
			$row_class = 'qrms-hub-stats-row';
			if ( ! empty( $row['class'] ) ) {
				$row_class .= ' ' . $row['class'];
			}
			echo '<div class="' . esc_attr( $row_class ) . '">';
			if ( ! empty( $row['title'] ) ) {
				echo '<h3 class="qrms-hub-stats-row-title">' . esc_html( $row['title'] ) . '</h3>';
			}
			echo '<div class="qrms-hub-stats-row-items">';
			foreach ( $row['items'] as $stat ) {
				self::render_hub_stat( $stat );
			}
			echo '</div></div>';
		}
		echo '</div>';
	}

	/**
	 * Tek bir özet kutusu. Adresi varsa kutunun tamamı tıklanabilir.
	 *
	 * `class` isteğe bağlıdır: bekleyen iş bildiren kutular kendilerini
	 * vurgulamak için ek bir sınıf geçer (ör. `qrms-hub-stat-alert`).
	 *
	 * @param array $stat { @type string $label, $value, $url, $accent, $class }
	 * @return void
	 */
	private static function render_hub_stat( array $stat ) {
		$accent  = isset( $stat['accent'] ) ? $stat['accent'] : '#c3c4c7';
		$url     = isset( $stat['url'] ) ? $stat['url'] : '';
		$tag     = '' !== $url ? 'a' : 'div';
		$href    = '' !== $url ? ' href="' . esc_url( $url ) . '"' : '';
		$classes = 'qrms-hub-stat';

		if ( ! empty( $stat['class'] ) ) {
			$classes .= ' ' . $stat['class'];
		}
		?>
		<<?php echo $tag; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> class="<?php echo esc_attr( $classes ); ?>"<?php echo $href; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> style="border-left-color:<?php echo esc_attr( $accent ); ?>">
			<div class="qrms-hub-stat-label"><?php echo esc_html( $stat['label'] ); ?></div>
			<div class="qrms-hub-stat-value">
				<span class="qrms-stat-value"><?php echo esc_html( $stat['value'] ); ?></span>
			</div>
		</<?php echo $tag; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
		<?php
	}

	/**
	 * Kart grubu: başlık + ızgara. Genel Bakış grupları ikon, sayaç ve
	 * vurgu rengi taşır; modül hub'ları yalnızca başlık + dekoratif ayırıcı.
	 *
	 * @param array $group { @type string $title, $icon, $accent, $count, $cards }
	 * @return void
	 */
	private static function render_hub_group( array $group ) {
		$is_overview = ! empty( $group['icon'] ) || isset( $group['total'] );
		$accent      = isset( $group['accent'] ) ? $group['accent'] : '';
		$style       = '' !== $accent ? ' style="--qrms-overview-group-accent:' . esc_attr( $accent ) . '"' : '';

		if ( $is_overview ) {
			echo '<section class="qrms-overview-group"' . $style . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo '<h2 class="qrms-overview-group-title">';
			if ( ! empty( $group['icon'] ) ) {
				echo '<span class="qrms-overview-group-icon dashicons ' . esc_attr( $group['icon'] ) . '" aria-hidden="true"></span>';
			}
			echo '<span class="qrms-overview-group-name">' . esc_html( $group['title'] ) . '</span>';
			if ( ! empty( $group['total'] ) ) {
				echo '<span class="qrms-overview-group-count">';
				printf(
					/* translators: 1: aktif modül sayısı, 2: kategorideki toplam modül sayısı. */
					esc_html__( '%1$d/%2$d aktif', 'qrms' ),
					(int) $group['active'],
					(int) $group['total']
				);
				echo '</span>';
			}
			echo '</h2>';
		} else {
			echo '<h2 class="qrms-hub-group-title">';
			echo esc_html( $group['title'] );
			echo '<span class="qrms-hub-group-divider" aria-hidden="true">&#9670;</span>';
			echo '</h2>';
		}

		echo '<div class="qrms-hub-grid">';
		foreach ( $group['cards'] as $card ) {
			self::render_hub_card( $card );
		}
		echo '</div>';

		if ( $is_overview ) {
			echo '</section>';
		}
	}

	/**
	 * Tek bir hub kartını basar — hem başlıksız (`cards`) hem gruplu
	 * (`card_groups`) ızgaralarda aynı kart işaretlemesi kullanılsın diye
	 * render_hub()'tan ayrılmıştır.
	 *
	 * Kart gövdesi BLOK elemanlardan kurulur (div/h3/p), span'lerden değil.
	 * Alt bağlantı listesi varsa kart bir <div> olur (iç içe <a> geçersiz);
	 * aksi hâlde kartın tamamı tek bir bağlantıdır.
	 *
	 * @param array $card { @type string $url, $title, $desc, $icon, $badge, $state, $links, $more }
	 * @return void
	 */
	private static function render_hub_card( array $card ) {
		$state      = isset( $card['state'] ) ? $card['state'] : '';
		$is_passive = ( 'passive' === $state );
		$has_links  = ! empty( $card['links'] ) && is_array( $card['links'] );
		$classes    = 'qrms-hub-card';

		if ( '' !== $state ) {
			$classes .= ' qrms-overview-card qrms-overview-card-' . $state;
		}
		if ( $has_links ) {
			$classes .= ' qrms-hub-card-has-links';
		}

		$url  = isset( $card['url'] ) ? $card['url'] : '';
		$icon = isset( $card['icon'] ) ? $card['icon'] : 'dashicons-admin-generic';

		// Alt liste varsa kart <div> olur (iç içe <a> geçersiz). Pasif kart
		// bağlantı değildir. Diğer her durumda kartın tamamı tek <a>'dır.
		if ( $has_links ) {
			echo '<div class="' . esc_attr( $classes ) . '">';
			if ( ! $is_passive && '' !== $url ) {
				echo '<a class="qrms-hub-card-main" href="' . esc_url( $url ) . '">';
			} else {
				echo '<div class="qrms-hub-card-main">';
			}
		} elseif ( $is_passive ) {
			echo '<div class="' . esc_attr( $classes ) . '">';
		} else {
			echo '<a class="' . esc_attr( $classes ) . '" href="' . esc_url( $url ) . '">';
		}

		self::render_hub_card_body( $card, $icon, $state, $is_passive );

		if ( $has_links ) {
			echo ( ! $is_passive && '' !== $url ) ? '</a>' : '</div>';
			echo '<ul class="qrms-hub-links">';
			foreach ( $card['links'] as $link ) {
				echo '<li><a href="' . esc_url( $link['url'] ) . '">' . esc_html( $link['title'] ) . '</a></li>';
			}
			if ( ! empty( $card['more']['url'] ) && ! empty( $card['more']['label'] ) ) {
				echo '<li><a class="qrms-hub-links-more" href="' . esc_url( $card['more']['url'] ) . '">' . esc_html( $card['more']['label'] ) . '</a></li>';
			}
			echo '</ul></div>';
		} elseif ( $is_passive ) {
			echo '</div>';
		} else {
			echo '</a>';
		}
	}

	/**
	 * Kartın ikon + başlık + açıklama + durum gövdesi.
	 *
	 * @param array  $card       Kart.
	 * @param string $icon       Dashicon sınıfı.
	 * @param string $state      active|passive|core|''.
	 * @param bool   $is_passive Pasif mi?
	 * @return void
	 */
	private static function render_hub_card_body( array $card, $icon, $state, $is_passive ) {
		?>
			<span class="qrms-hub-icon dashicons <?php echo esc_attr( $icon ); ?>" aria-hidden="true"></span>
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
			<?php if ( 'active' === $state ) : ?>
				<span class="qrms-overview-state qrms-overview-state-active">
					<span class="qrms-check" aria-hidden="true">&#10003;</span>
					<span class="screen-reader-text"><?php esc_html_e( 'Aktif', 'qrms' ); ?></span>
				</span>
			<?php elseif ( $is_passive ) : ?>
				<span class="qrms-overview-state qrms-overview-state-passive"><?php esc_html_e( 'Pasif', 'qrms' ); ?></span>
			<?php endif; ?>
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
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_menu_assets' ) );

		// Modül alt sayfaları menüden yalnızca boyanmadan hemen önce düşürülür;
		// gerekçe için hide_module_subpages() başlığına bakın. Gruplama aynı
		// kancada, gizlemeden SONRA çalışır.
		add_action( 'admin_head', array( __CLASS__, 'hide_module_subpages' ), 10 );
		add_action( 'admin_head', array( __CLASS__, 'group_menu_rows' ), 11 );
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
	 * Genel Bakış'ın kategorileri — get_nav_groups() ile aynı taksonomi.
	 *
	 * Kalem ya bir modül slug'ıdır ('restoran-menu') ya da çekirdek sayfa
	 * anahtarı (self::OVERVIEW_CORE_* sabitleri). Gruplama tek yerde,
	 * get_nav_groups() içindedir; kartların sunumu build_overview_groups() ve
	 * render_overview() içindedir.
	 *
	 * Bir modül birden çok kategoriye konmamalı ve hiçbir kategoride
	 * unutulmamalıdır; ikisi de testle korunur. Yine de unutulan bir modül
	 * ekrandan DÜŞMEZ: build_overview_groups() onu sondaki "Diğer Modüller"
	 * kategorisine alır.
	 *
	 * @return array<int,array{key:string,title:string,icon:string,accent:string,items:string[]}>
	 */
	public static function get_overview_groups() {
		$groups = array();

		foreach ( self::get_nav_groups() as $group ) {
			$groups[] = array(
				'key'    => $group['key'],
				'title'  => $group['title'],
				'icon'   => $group['icon'],
				'accent' => $group['accent'],
				'items'  => $group['items'],
			);
		}

		return $groups;
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
	 * @return array<int,array{title:string,icon:string,accent:string,cards:array,total:int,active:int}>
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
				// Genel Bakış kartı kendini göstermez — zaten bu ekrandayız.
				if ( self::MENU_SLUG === $item ) {
					continue;
				}

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

				$card = array(
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

				if ( $is_active ) {
					$card = self::attach_overview_links( $card, $item );
				}

				$cards[] = $card;
			}

			if ( empty( $cards ) ) {
				continue;
			}

			$groups[] = array(
				'title'  => $group['title'],
				'icon'   => $group['icon'],
				'accent' => isset( $group['accent'] ) ? $group['accent'] : '',
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

				$card = array(
					'url'   => $is_active ? admin_url( 'admin.php?page=' . self::get_module_page_slug( $slug ) ) : '',
					'title' => QRMS_Helpers::get_module_name( $slug ),
					'desc'  => QRMS_Helpers::get_module_description( $slug ),
					'icon'  => QRMS_Helpers::get_module_icon( $slug ),
					'state' => $is_active ? 'active' : 'passive',
				);

				if ( $is_active ) {
					$card = self::attach_overview_links( $card, $slug );
				}

				$cards[] = $card;
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
	 * Aktif modül kartına alt ekran listesini ekler.
	 *
	 * Liste modülün kendi get_subpages()/admin_pages() kaydından türer;
	 * 5'ten uzunsa ilk 5 gösterilir, kalanı "+N daha" ile hub'a bağlanır.
	 *
	 * @param array  $card        Kart dizisi.
	 * @param string $module_slug Modül slug'ı.
	 * @return array
	 */
	private static function attach_overview_links( array $card, $module_slug ) {
		$links = self::get_module_overview_links( $module_slug );

		if ( empty( $links ) ) {
			return $card;
		}

		$limit = 5;
		$total = count( $links );

		$card['links'] = array_slice( $links, 0, $limit );

		if ( $total > $limit ) {
			$card['more'] = array(
				'url'   => $card['url'],
				'label' => sprintf(
					/* translators: %d: gizlenen alt ekran sayısı. */
					__( '+%d daha', 'qrms' ),
					$total - $limit
				),
			);
		}

		return $card;
	}

	/**
	 * Bir modülün Genel Bakış kartındaki alt bağlantılar.
	 *
	 * Adresler elle yazılmaz: her modülün kendi sayfa kaydından (get_subpages,
	 * admin_pages, qrm_pro_admin_pages, …) türetilir. Yeni alt ekran o kayda
	 * eklenince burada da görünür.
	 *
	 * @param string $slug Modül slug'ı.
	 * @return array<int,array{url:string,title:string}>
	 */
	public static function get_module_overview_links( $slug ) {
		$links = array();

		switch ( $slug ) {
			case 'restoran-menu':
				if ( class_exists( 'Restaurant_Menu_Automation' ) ) {
					$rma = Restaurant_Menu_Automation::get_instance();
					if ( method_exists( $rma, 'get_overview_links' ) ) {
						$links = $rma->get_overview_links();
					}
				}
				break;

			case 'yorum-feedback':
				if ( function_exists( 'qrm_pro_admin_pages' ) && function_exists( 'qrm_pro_admin_url' ) ) {
					foreach ( qrm_pro_admin_pages() as $page_slug => $page ) {
						$links[] = array(
							'url'   => qrm_pro_admin_url( $page_slug ),
							'title' => isset( $page['menu_title'] ) ? $page['menu_title'] : $page['title'],
						);
					}
				}
				break;

			case 'qr-galeri':
				if ( class_exists( 'QRMenu_Gallery_Manager' ) ) {
					foreach ( QRMenu_Gallery_Manager::instance()->admin_pages() as $page_slug => $page ) {
						$links[] = array(
							'url'   => admin_url( 'admin.php?page=' . $page_slug ),
							'title' => $page['title'],
						);
					}
				}
				break;

			case 'qr-acilis-ekrani':
				if ( class_exists( 'QRMS_Acilis_Ekrani' ) && QRMS_Acilis_Ekrani::instance() ) {
					$ae = QRMS_Acilis_Ekrani::instance();
					foreach ( $ae->admin_pages() as $page_slug => $page ) {
						$links[] = array(
							'url'   => $ae->admin_url_for( $page_slug ),
							'title' => $page['title'],
						);
					}
				}
				break;

			case 'qr-masa-oturum-guvenligi':
				if ( function_exists( 'qrms_module_qr_masa_oturum_guvenligi_sayfalar' ) ) {
					foreach ( qrms_module_qr_masa_oturum_guvenligi_sayfalar() as $page_slug => $page ) {
						$links[] = array(
							'url'   => admin_url( 'admin.php?page=' . $page_slug ),
							'title' => $page['title'],
						);
					}
				}
				break;

			case 'qr-chatbot':
				if ( function_exists( 'qmo_chatbot_admin_pages' ) ) {
					$links = qmo_chatbot_admin_pages();
				}
				break;

			case 'qr-masa':
				if ( function_exists( 'qrms_module_qr_masa_sayfalar' ) ) {
					foreach ( qrms_module_qr_masa_sayfalar() as $page ) {
						$links[] = array(
							'url'   => $page['url'],
							'title' => $page['title'],
						);
					}
				}
				break;
		}

		/**
		 * Genel Bakış kartındaki alt bağlantılar.
		 *
		 * @param array  $links {url, title} listesi.
		 * @param string $slug  Modül slug'ı.
		 */
		return apply_filters( 'qrms_module_overview_links', $links, $slug );
	}

	/**
	 * Genel Bakış'ın üst analiz şeridi.
	 *
	 * Sayaçlar modülün kendi merkezi fonksiyonlarından gelir (sol menü rozeti
	 * ve hub kartıyla aynı kaynak). Lisansı pasif modülün kutusu basılmaz;
	 * "Dikkat gerektirenler" satırında değeri 0 olan kutu basılmaz.
	 *
	 * @param string[] $active Aktif modül slug'ları.
	 * @return array<int,array{title:string,class?:string,items:array}>
	 */
	public static function get_overview_stats( array $active ) {
		$attention = array();
		$status    = array();
		$is        = static function ( $slug ) use ( $active ) {
			return in_array( $slug, $active, true );
		};

		if ( $is( 'restoran-menu' ) && function_exists( 'qmo_tukendi_urun_sayisi' ) ) {
			$tukendi = (int) qmo_tukendi_urun_sayisi();
			if ( $tukendi > 0 ) {
				$attention[] = array(
					'label'  => __( 'Tükendi Ürün', 'qrms' ),
					'value'  => $tukendi,
					'url'    => admin_url( 'edit.php?post_type=rma_menu_item&rma_tukendi=1' ),
					'accent' => '#d63638',
				);
			}
		}

		if ( $is( 'restoran-menu' ) && function_exists( 'qmo_urunum_yok_eksik_ozet' ) ) {
			$ozet = qmo_urunum_yok_eksik_ozet();
			$mal  = isset( $ozet['malzemeler'] ) ? count( (array) $ozet['malzemeler'] ) : 0;
			if ( $mal > 0 ) {
				$attention[] = array(
					'label'  => __( 'Tükenen Malzeme', 'qrms' ),
					'value'  => $mal,
					'url'    => admin_url( 'admin.php?page=qrms-rm-urunum-yok' ),
					'accent' => '#d63638',
				);
			}
		}

		if ( $is( 'yorum-feedback' ) && function_exists( 'qrm_cf_unread_total' ) ) {
			$unread = (int) qrm_cf_unread_total();
			if ( $unread > 0 ) {
				$url = function_exists( 'qrm_pro_admin_url' )
					? qrm_pro_admin_url( 'qrms-yf-formlar', array( 'tab' => 'submissions' ) )
					: admin_url( 'admin.php?page=qrms-yf-formlar&tab=submissions' );

				$attention[] = array(
					'label'  => __( 'Okunmamış Form', 'qrms' ),
					'value'  => $unread,
					'url'    => $url,
					'accent' => '#dba617',
				);
			}
		}

		if ( $is( 'yorum-feedback' ) && function_exists( 'qrm_reward_setup_status' ) ) {
			$setup   = qrm_reward_setup_status();
			$eksik   = isset( $setup['missing'] ) ? count( (array) $setup['missing'] ) : 0;
			if ( $eksik > 0 ) {
				$url = function_exists( 'qrm_pro_admin_url' )
					? qrm_pro_admin_url( 'qrms-yf-odul' )
					: admin_url( 'admin.php?page=qrms-yf-odul' );

				$attention[] = array(
					'label'  => __( 'Eksik Ödül Kurulumu', 'qrms' ),
					'value'  => $eksik,
					'url'    => $url,
					'accent' => '#dba617',
				);
			}
		}

		if ( $is( 'restoran-menu' ) && function_exists( 'qmo_yayinlanan_urun_sayisi' ) ) {
			$status[] = array(
				'label'  => __( 'Toplam Ürün', 'qrms' ),
				'value'  => (int) qmo_yayinlanan_urun_sayisi(),
				'url'    => admin_url( 'edit.php?post_type=rma_menu_item' ),
				'accent' => '#8c8f94',
			);
		}

		if ( $is( 'qr-masa' ) && class_exists( 'QMO_Masalar' ) ) {
			if ( method_exists( 'QMO_Masalar', 'sayisi' ) ) {
				$masa_sayisi = (int) QMO_Masalar::sayisi();
			} else {
				$masalar     = method_exists( 'QMO_Masalar', 'hepsi' ) ? QMO_Masalar::hepsi() : array();
				$masa_sayisi = is_array( $masalar ) ? count( $masalar ) : 0;
			}

			$status[] = array(
				'label'  => __( 'Kayıtlı Masa', 'qrms' ),
				'value'  => $masa_sayisi,
				'url'    => self::get_module_page_url( 'qr-masa' ),
				'accent' => '#8c8f94',
			);
		}

		if ( $is( 'qr-analiz' ) && class_exists( 'QRMS_Analitik' ) && method_exists( 'QRMS_Analitik', 'genel_bakis' ) ) {
			$ozet = ( class_exists( 'QRMS_Analitik' ) && method_exists( 'QRMS_Analitik', 'tablo_var_mi' ) && ! QRMS_Analitik::tablo_var_mi() )
				? array( 'mv_bugun' => 0, 'masa_gun' => 0 )
				: QRMS_Analitik::genel_bakis();

			$analiz_url = self::get_module_page_url( 'qr-analiz' );

			$status[] = array(
				'label'  => __( 'Bugün Menü Okutma', 'qrms' ),
				'value'  => isset( $ozet['mv_bugun'] ) ? (int) $ozet['mv_bugun'] : 0,
				'url'    => $analiz_url,
				'accent' => '#8c8f94',
			);
			$status[] = array(
				'label'  => __( 'Bugün Aktif Masa', 'qrms' ),
				'value'  => isset( $ozet['masa_gun'] ) ? (int) $ozet['masa_gun'] : 0,
				'url'    => $analiz_url,
				'accent' => '#8c8f94',
			);
		}

		if ( $is( 'yorum-feedback' ) && function_exists( 'qrm_reward_active_code_count' ) ) {
			$url = function_exists( 'qrm_pro_admin_url' )
				? qrm_pro_admin_url( 'qrms-yf-odul', array( 'tab' => 'codes', 'status' => 'active' ) )
				: admin_url( 'admin.php?page=qrms-yf-odul&tab=codes&status=active' );

			$status[] = array(
				'label'  => __( 'Aktif İndirim Kodu', 'qrms' ),
				'value'  => (int) qrm_reward_active_code_count(),
				'url'    => $url,
				'accent' => '#8c8f94',
			);
		}

		$rows = array();

		if ( ! empty( $attention ) ) {
			$rows[] = array(
				'title' => __( 'Dikkat gerektirenler', 'qrms' ),
				'class' => 'is-attention',
				'items' => $attention,
			);
		}

		if ( ! empty( $status ) ) {
			$rows[] = array(
				'title' => __( 'Durum', 'qrms' ),
				'class' => 'is-status',
				'items' => $status,
			);
		}

		return $rows;
	}

	/**
	 * Genel Bakış ekranı: modüller kategorilere ayrılmış kart ızgarasında.
	 *
	 * Kart görseli modül hub'larıyla ORTAKTIR (.qrms-hub-card): aynı ikon +
	 * başlık + açıklama dizilimi, aynı kırılım noktaları. Genel Bakış'a özgü
	 * tek fark kartın lisans durumudur — aktif kart bağlantı, pasif kart
	 * tıklanamaz bir kutudur. Üstteki analiz şeridi render_hub()'ın stats
	 * desteğini kullanır.
	 *
	 * @return void
	 */
	public static function render_overview() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'Bu sayfayı görüntüleme yetkiniz yok.', 'qrms' ) );
		}

		$active = QRMS_License_Client::get_active_modules();
		$groups = self::build_overview_groups( $active, QRMS_Shortcodes::has_any() );

		$notice = '';
		if ( empty( $active ) ) {
			$notice  = '<div class="qrms-alert qrms-overview-alert">';
			$notice .= '<p>' . esc_html__( 'Henüz aktif modül yok. Lisansınızı doğruladığınızda modülleriniz burada açılır.', 'qrms' ) . '</p>';
			$notice .= '<a class="qrms-button qrms-button-primary" href="' . esc_url( admin_url( 'admin.php?page=' . self::SETTINGS_SLUG ) ) . '">';
			$notice .= esc_html__( 'Lisansı Doğrula', 'qrms' );
			$notice .= '</a></div>';
		}

		self::render_hub(
			array(
				'title'       => __( 'QR Menü — Genel Bakış', 'qrms' ),
				'intro'       => __( 'Modülleriniz konularına göre gruplandı. Ne yapmak istiyorsanız kartına dokunun.', 'qrms' ),
				'class'       => 'qrms-overview',
				'notice'      => $notice,
				'stats'       => self::get_overview_stats( $active ),
				'card_groups' => $groups,
			)
		);
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
