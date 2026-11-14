<?php
/**
 * Panel özellik araması: başlıktaki arama kutusunun dizini.
 *
 * Dizin elle tutulan bir liste DEĞİLDİR: kenar çubuğu, modüllerin sekme şeridi
 * (QRMS_Admin::get_module_nav_items), genel bakış kartlarındaki alt bağlantılar
 * (QRMS_Admin::get_module_overview_links) ve ayar sekmelerinden türer; yeni bir
 * alt sayfa kaydedildiğinde aramada kendiliğinden görünür. Elle eklenenler yalnızca
 * sayfa İÇİ bölümler (çapa bağlantıları) ve eşanlamlı kelimelerdir.
 *
 * Yalnızca lisansta etkin modüller ve kullanıcının görebildiği sayfalar listelenir.
 *
 * @package QR_Menu_Suite
 */

defined( 'ABSPATH' ) || exit;

/**
 * Özellik arama dizini.
 */
class QRMS_Ozellik_Arama {

	/**
	 * İstek başına önbellek.
	 *
	 * @var array|null
	 */
	private static $dizin = null;

	/**
	 * Modül slug'ı => eşanlamlı kelimeler (müşterinin değil, restoran sahibinin dili).
	 *
	 * @return array<string,string>
	 */
	public static function modul_esanlamlilari() {
		return array(
			'restoran-menu'            => 'ürün urun yemek fiyat kategori menü ekle düzenle ekstra rozet porsiyon kampanya indirim tükendi stok banner csv toplu',
			'qr-masa'                  => 'masa qr karekod kod yazdır toplu masa ekle oturum',
			'qr-servis-paneli'         => 'sipariş siparis garson hesap çağrı servis mutfak personel bildirim',
			'qr-analiz'                => 'istatistik rapor analiz ziyaretçi okutma görüntülenme grafik veri',
			'qr-menu-muhendisligi'     => 'menü mühendisliği maliyet reçete malzeme kâr kar kârlılık performans',
			'yorum-feedback'           => 'yorum değerlendirme puan geri bildirim form google ödül şikayet anket',
			'qr-ceviri'                => 'çeviri dil ingilizce translate yabancı csv',
			'qr-chatbot'               => 'asistan chatbot yapay zeka ai gemini sohbet bot',
			'qr-galeri'                => 'galeri fotoğraf foto resim görsel',
			'qr-acilis-ekrani'         => 'karşılama açılış splash wifi şifre sosyal medya ödeme yöntemi',
			'qr-calisma-saatleri'      => 'çalışma saatleri saat açık kapalı tatil mesai',
			'header-footer-builder'    => 'header footer üst alt bilgi logo menü hamburger',
			'qr-masa-oturum-guvenligi' => 'güvenlik oturum süre limit firebase şube',
		);
	}

	/**
	 * Arama dizini.
	 *
	 * @return array<int,array{b:string,g:string,a:string,k:string,u:string}>
	 */
	public static function dizin() {
		if ( null !== self::$dizin ) {
			return self::$dizin;
		}

		self::$dizin = array();

		if ( ! current_user_can( QRMS_Admin::CAPABILITY ) ) {
			return self::$dizin;
		}

		// Yalnız servis rolündeki personel tek ekran görür; aramaya gerek yok.
		if ( class_exists( 'QRMS_SP_Rol' ) && QRMS_SP_Rol::yalniz_servis_mi() ) {
			return self::$dizin;
		}

		$kayit = array();
		$ekle  = function ( $baslik, $grup, $url, $aciklama = '', $anahtar = '' ) use ( &$kayit ) {
			$baslik = trim( wp_strip_all_tags( (string) $baslik ) );
			$url    = (string) $url;

			if ( '' === $baslik || '' === $url ) {
				return;
			}

			// Aynı adres iki kaynaktan gelebilir (kenar çubuğu + kart): ilki kalır, kelimeler birleşir.
			if ( isset( $kayit[ $url ] ) ) {
				$kayit[ $url ]['k'] = trim( $kayit[ $url ]['k'] . ' ' . $anahtar );
				return;
			}

			$kayit[ $url ] = array(
				'b' => $baslik,
				'g' => trim( wp_strip_all_tags( (string) $grup ) ),
				'a' => trim( wp_strip_all_tags( (string) $aciklama ) ),
				'k' => trim( (string) $anahtar ),
				'u' => $url,
			);
		};

		$esanlam = self::modul_esanlamlilari();
		$aktif   = QRMS_License_Client::get_active_modules();
		$sayfa   = function ( $slug ) {
			return admin_url( 'admin.php?page=' . rawurlencode( $slug ) );
		};

		$ekle( __( 'Genel Bakış', 'qrms' ), __( 'Panel', 'qrms' ), $sayfa( QRMS_Admin::MENU_SLUG ), __( 'Bugünün özeti ve tüm özellikler.', 'qrms' ), 'ana sayfa özet dashboard kontrol merkezi başlangıç' );

		// 1) Kenar çubuğu (grup başlıklarıyla).
		$grup = '';
		foreach ( QRMS_Admin_Shell::arama_nav_satirlari() as $satir ) {
			if ( isset( $satir['type'] ) && 'group' === $satir['type'] ) {
				$grup = isset( $satir['title'] ) ? (string) $satir['title'] : '';
				continue;
			}

			if ( empty( $satir['url'] ) || empty( $satir['label'] ) ) {
				continue;
			}

			$modul = self::moduldan_url( (string) $satir['url'] );
			$ekle(
				$satir['label'],
				$grup,
				$satir['url'],
				'' !== $modul ? QRMS_Helpers::get_module_description( $modul ) : '',
				'' !== $modul && isset( $esanlam[ $modul ] ) ? $esanlam[ $modul ] : ''
			);
		}

		// 2) Modüllerin alt sayfaları (sekme şeridi) ve genel bakış alt bağlantıları.
		foreach ( $aktif as $modul ) {
			if ( ! QRMS_Helpers::is_valid_module( $modul ) ) {
				continue;
			}

			$ad = QRMS_Helpers::get_module_name( $modul );

			// Alt sayfalar yalnız kendi başlığı ve modül adıyla bulunur. Modülün eşanlamlıları
			// yalnız ana girişte durur: her alt sayfaya eklenince "kampanya" araması
			// "Alerjenler"i de getiriyordu.
			foreach ( QRMS_Admin::get_module_nav_items( $modul ) as $oge ) {
				if ( ! empty( $oge['url'] ) && ! empty( $oge['title'] ) ) {
					$ekle( $oge['title'], $ad, $oge['url'] );
				}
			}

			foreach ( QRMS_Admin::get_module_overview_links( $modul ) as $oge ) {
				if ( ! empty( $oge['url'] ) && ! empty( $oge['title'] ) ) {
					$ekle( $oge['title'], $ad, $oge['url'] );
				}
			}
		}

		// 3) Sistem ayarları sekmeleri ve çekirdek ekranlar.
		$ayar_k = array(
			'genel'  => 'lisans anahtar durum modül doğrula paket',
			'giris'  => 'giriş ekranı login şifre parola sayfa görünüm arka plan qrm adres',
			'marka'  => 'logo marka renk restoran adı kimlik',
			'tasima' => 'taşıma yedek yedekle dışa aktar içe aktar export import başka site kopyala',
		);
		foreach ( QRMS_Admin::get_settings_tabs() as $slug => $sekme ) {
			$ekle(
				$sekme['label'],
				__( 'Sistem Ayarları', 'qrms' ),
				add_query_arg( 'tab', $slug, $sayfa( QRMS_Admin::SETTINGS_SLUG ) ),
				'',
				isset( $ayar_k[ $slug ] ) ? $ayar_k[ $slug ] : ''
			);
		}

		if ( class_exists( 'QRMS_Acil_Durum' ) && defined( 'QRMS_Acil_Durum::SLUG' ) ) {
			$ekle( __( 'Acil Durum', 'qrms' ), __( 'Sistem', 'qrms' ), $sayfa( QRMS_Acil_Durum::SLUG ), __( 'Modülleri tek tuşla kapatın ya da bakım moduna alın.', 'qrms' ), 'acil bakım modu kapat durdur pin menüyü kapat' );
		}

		// 4) Sayfa içi bölümler (çapa ile doğrudan bölüme gider).
		if ( in_array( 'qr-analiz', $aktif, true ) ) {
			$urunler = $sayfa( 'qrms-an-urunler' );
			$grup_an = QRMS_Helpers::get_module_name( 'qr-analiz' ) . ' › ' . __( 'Ürünler', 'qrms' );

			$ekle( __( 'Menüde Bulunamayan Aramalar', 'qrms' ), $grup_an, $urunler . '#qrms-an-arama-panel', __( 'Müşterilerin aradığı ama menünüzde olmayan ürünler.', 'qrms' ), 'keyword keywords anahtar kelime arama kelimeleri aranan aramalar bulunamayan olmayan ürün eksik ürün search sorgu müşteri ne aradı' );
			$ekle( __( 'En Çok Tıklanan Ürünler', 'qrms' ), $grup_an, $urunler . '#qrms-an-products', '', 'popüler en çok ilgi gören favori' );
			$ekle( __( 'En Az Tıklanan Ürünler', 'qrms' ), $grup_an, $urunler . '#qrms-an-least', '', 'ilgi görmeyen satmayan ölü ürün' );
			$ekle( __( 'Kategori Dağılımı', 'qrms' ), $grup_an, $urunler . '#qrms-an-cats-dist', '', 'kategori pay oran' );
			$ekle( __( 'Filtre Kullanımı', 'qrms' ), $grup_an, $urunler . '#qrms-an-filters', '', 'filtre alerjen diyet vegan glutensiz' );
		}

		/**
		 * Özellik arama dizini (son süzme).
		 *
		 * @param array $dizin Kayıtlar: b (başlık), g (grup), a (açıklama), k (kelimeler), u (adres).
		 */
		self::$dizin = array_values( (array) apply_filters( 'qrms_ozellik_arama_dizini', array_values( $kayit ) ) );

		return self::$dizin;
	}

	/**
	 * Modül ana sayfası adresinden modül slug'ı.
	 *
	 * @param string $url Adres.
	 * @return string
	 */
	private static function moduldan_url( $url ) {
		$sorgu = (string) wp_parse_url( $url, PHP_URL_QUERY );
		parse_str( $sorgu, $p );
		$page = isset( $p['page'] ) ? (string) $p['page'] : '';

		if ( 0 !== strpos( $page, QRMS_Admin::MODULE_PAGE_PREFIX ) ) {
			return '';
		}

		$modul = substr( $page, strlen( QRMS_Admin::MODULE_PAGE_PREFIX ) );

		return QRMS_Helpers::is_valid_module( $modul ) ? $modul : '';
	}
}
