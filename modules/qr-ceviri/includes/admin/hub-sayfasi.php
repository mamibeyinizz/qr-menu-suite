<?php
/**
 * QR Çeviri HUB EKRANI ve alt sayfa tanımı.
 *
 * Modül tek uzun sayfaydı: Sistem Durumu, diller, metin toplama, kaynak
 * seçimi ve CSV uçları alt alta duruyordu. "Ayarları Kaydet" ile "CSV
 * Dışa Aktar" karışıyordu. Artık suite'in diğer modülleriyle AYNI deseni
 * kullanır: sol menüdeki satır bir hub'dır, her konu kendi alt sayfasındadır
 * (bkz. modules/qr-analiz).
 *
 * SOL MENÜ TEK SEVİYELİ KALIR. Alt sayfalar gerçek WordPress sayfaları
 * olarak kaydedilir ama menüye satır EKLEMEZ; QRMS_Admin::hide_module_subpages()
 * onları boyanmadan hemen önce düşürür.
 *
 * Adımlar SIRALI ZORUNLU DEĞİLDİR. Kullanıcı hub'dan veya adım şeridinden
 * istediğine atlar; sihirbaz kilidi yoktur.
 *
 * @package QR_Menu_Suite
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Eski (suite öncesi) yönetim sayfası slug'ı.
 *
 * Yalnızca yönlendirme olarak kayıtlı kalır: yer imleri ve dış bağlantılar
 * 404 yerine hub'ı açar. Aynı desen qr-analiz'deki QRMS_ANALITIK_SAYFA.
 */
const QRMS_CEVIRI_ESKI_SAYFA = 'qrmenu-translator';

/**
 * Klasik (tek sayfalık) görünümün slug'ı — ekran durur, hub'da kartı yoktur.
 *
 * Bölümler alt sayfalara taşındıktan sonra da doğrudan URL çalışır: eski
 * yer imi kırılmaz, "tümünü bir arada gör" isteyen yönetici kaybolmaz.
 */
const QRMS_CEVIRI_KLASIK_SAYFA = 'qrms-cv-klasik';

if ( ! function_exists( 'qrms_module_qr_ceviri_sayfalar' ) ) {

	/**
	 * Modülün alt sayfaları — TEK KAYNAK.
	 *
	 * Sayfa kaydı (add_submenu_page), hub kartları ve adım şeridi aynı
	 * listeden beslenir; dizideki sıra kart / adım sırasıdır. Sıra bir
	 * öneridir, kilit değildir.
	 *
	 *   title  : sayfa başlığı (sekme, breadcrumb, adım şeridi).
	 *   render : sayfayı basan çağrılabilir.
	 *   desc   : hub kartındaki tek cümlelik açıklama (durum satırı ayrı).
	 *   icon   : dashicon — EMOJİ DEĞİL (bkz. QRMS_Admin::render_hub).
	 *
	 * @return array<string,array{title:string,render:callable,desc:string,icon:string}>
	 */
	function qrms_module_qr_ceviri_sayfalar() {
		return array(
			'qrms-cv-diller'   => array(
				'title'  => 'Diller',
				'render' => 'qrms_module_qr_ceviri_sayfa_diller',
				'desc'   => 'Menüde gösterilecek diller ve dil seçici görünümü.',
				'icon'   => 'dashicons-translation',
			),
			'qrms-cv-kapsam'   => array(
				'title'  => 'Kapsam',
				'render' => 'qrms_module_qr_ceviri_sayfa_kapsam',
				'desc'   => 'Hangi ürün, kategori, alerjen ve sayfaların CSV\'ye çıkacağı.',
				'icon'   => 'dashicons-filter',
			),
			'qrms-cv-toplama'  => array(
				'title'  => 'Metin Toplama',
				'render' => 'qrms_module_qr_ceviri_sayfa_toplama',
				'desc'   => 'Sitede çevrilmeyen sabit metinleri bulma ve listeye ekleme.',
				'icon'   => 'dashicons-editor-ul',
			),
			'qrms-cv-disa'     => array(
				'title'  => 'CSV Dışa Aktar',
				'render' => 'qrms_module_qr_ceviri_sayfa_disa',
				'desc'   => 'Çeviri dosyasını indirin; yalnızca dil sütunlarını doldurun.',
				'icon'   => 'dashicons-download',
			),
			'qrms-cv-ice'      => array(
				'title'  => 'CSV İçe Aktar',
				'render' => 'qrms_module_qr_ceviri_sayfa_ice',
				'desc'   => 'Doldurduğunuz CSV\'yi yükleyin; ayraç otomatik algılanır.',
				'icon'   => 'dashicons-upload',
			),
			'qrms-cv-durum'    => array(
				'title'  => 'Sistem Durumu',
				'render' => 'qrms_module_qr_ceviri_sayfa_durum',
				'desc'   => 'Kaç çeviri yazıldı, hangileri eskimiş veya yetim.',
				'icon'   => 'dashicons-chart-bar',
			),
		);
	}
}

if ( ! function_exists( 'qrms_module_qr_ceviri_sayfa_url' ) ) {

	/**
	 * Bir alt sayfanın yönetim adresi.
	 *
	 * @param string $slug Alt sayfa slug'ı.
	 * @return string
	 */
	function qrms_module_qr_ceviri_sayfa_url( $slug ) {
		return admin_url( 'admin.php?page=' . $slug );
	}
}

if ( ! function_exists( 'qrms_module_qr_ceviri_zaman_metni' ) ) {

	/**
	 * Unix zaman damgasını yönetim tarihine çevirir.
	 *
	 * @param int $ts Unix zamanı; 0 = henüz yok.
	 * @return string
	 */
	function qrms_module_qr_ceviri_zaman_metni( $ts ) {
		$ts = (int) $ts;

		if ( $ts < 1 ) {
			return 'henüz yok';
		}

		$tarih = get_option( 'date_format', 'd.m.Y' );
		$saat  = get_option( 'time_format', 'H:i' );

		if ( ! is_string( $tarih ) || '' === $tarih ) {
			$tarih = 'd.m.Y';
		}
		if ( ! is_string( $saat ) || '' === $saat ) {
			$saat = 'H:i';
		}

		return date_i18n( $tarih . ' ' . $saat, $ts );
	}
}

if ( ! function_exists( 'qrms_module_qr_ceviri_hub_durumlari' ) ) {

	/**
	 * Hub kartlarının canlı durum satırları.
	 *
	 * @return array<string,string> slug => durum.
	 */
	function qrms_module_qr_ceviri_hub_durumlari() {
		$aktif = function_exists( 'rma_ceviri_hedef_diller' ) ? rma_ceviri_hedef_diller() : array();
		$dil_n = count( $aktif );

		$urun  = function_exists( 'rma_ceviri_urun_tipleri' ) ? count( rma_ceviri_urun_tipleri() ) : 0;
		$kat   = function_exists( 'rma_ceviri_taksonomiler' ) ? count( rma_ceviri_taksonomiler( 'category' ) ) : 0;
		$aler  = function_exists( 'rma_ceviri_taksonomiler' ) ? count( rma_ceviri_taksonomiler( 'allergen' ) ) : 0;
		$sayfa = function_exists( 'rma_ceviri_secili_elementor_sayfalari' )
			? count( rma_ceviri_secili_elementor_sayfalari() )
			: 0;
		$kapsam_n = $urun + $kat + $aler + $sayfa;

		$toplama_acik = function_exists( 'rma_ceviri_toplama_acik_mi' ) && rma_ceviri_toplama_acik_mi();
		$bulunan_n    = function_exists( 'rma_ceviri_bulunan_metinler' )
			? count( rma_ceviri_bulunan_metinler() )
			: 0;

		$disa = qrms_module_qr_ceviri_zaman_metni( (int) get_option( 'rma_ceviri_son_disa', 0 ) );
		$ice  = qrms_module_qr_ceviri_zaman_metni( (int) get_option( 'rma_ceviri_son_ice', 0 ) );

		$dil_sayilari = class_exists( 'RMA_Ceviri_Tablo' ) ? RMA_Ceviri_Tablo::dil_sayilari() : array();
		$toplam       = array_sum( $dil_sayilari );
		$eskimis      = function_exists( 'rma_ceviri_eskimis_sayilari' ) ? array_sum( rma_ceviri_eskimis_sayilari() ) : 0;
		$yetim        = function_exists( 'rma_ceviri_yetim_satir_sayisi' ) ? rma_ceviri_yetim_satir_sayisi() : 0;

		return array(
			'qrms-cv-diller'  => sprintf( '%d dil etkin', $dil_n ),
			'qrms-cv-kapsam'  => sprintf(
				'%d ürün tipi · %d taksonomi · %d sayfa',
				$urun,
				$kat + $aler,
				$sayfa
			),
			'qrms-cv-toplama' => $toplama_acik
				? sprintf( 'Açık · %d metin toplandı', $bulunan_n )
				: sprintf( 'Kapalı · %d metin bekliyor', $bulunan_n ),
			'qrms-cv-disa'    => 'Son dışa aktarma: ' . $disa,
			'qrms-cv-ice'     => 'Son içe aktarma: ' . $ice,
			'qrms-cv-durum'   => sprintf(
				'%d çeviri · %d eskimiş · %d yetim',
				$toplam,
				$eskimis,
				$yetim
			),
		);
	}
}

if ( ! function_exists( 'qrms_module_qr_ceviri_dikkatler' ) ) {

	/**
	 * Hub üstündeki "dikkat gerektirenler" maddeleri.
	 *
	 * @return array<int,array{metin:string,url:string,kritik:bool}>
	 */
	function qrms_module_qr_ceviri_dikkatler() {
		$maddeler = array();

		$hedef = function_exists( 'rma_ceviri_hedef_diller' ) ? rma_ceviri_hedef_diller() : array();
		if ( empty( $hedef ) ) {
			$maddeler[] = array(
				'metin'  => 'Hiç hedef dil seçili değil. Ziyaretçi menüyü başka dilde göremez.',
				'url'    => qrms_module_qr_ceviri_sayfa_url( 'qrms-cv-diller' ),
				'kritik' => true,
			);
		}

		$eskimis = function_exists( 'rma_ceviri_eskimis_sayilari' ) ? array_sum( rma_ceviri_eskimis_sayilari() ) : 0;
		if ( $eskimis > 0 ) {
			$maddeler[] = array(
				'metin'  => sprintf( '%d eskimiş çeviri var — yönetici metni değişmiş, ziyaretçi Türkçe görür.', $eskimis ),
				'url'    => qrms_module_qr_ceviri_sayfa_url( 'qrms-cv-durum' ),
				'kritik' => false,
			);
		}

		$yetim = function_exists( 'rma_ceviri_yetim_satir_sayisi' ) ? rma_ceviri_yetim_satir_sayisi() : 0;
		if ( $yetim > 0 ) {
			$maddeler[] = array(
				'metin'  => sprintf( '%d yetim satır var — silinmiş kaynağa ait çeviriler tabloda duruyor.', $yetim ),
				'url'    => qrms_module_qr_ceviri_sayfa_url( 'qrms-cv-durum' ),
				'kritik' => false,
			);
		}

		return $maddeler;
	}
}

if ( ! function_exists( 'qrms_module_qr_ceviri_dikkat_html' ) ) {

	/**
	 * Dikkat şeridinin HTML'i (sorun yoksa boş).
	 *
	 * @return string wp_kses_post ile basılabilir.
	 */
	function qrms_module_qr_ceviri_dikkat_html() {
		$maddeler = qrms_module_qr_ceviri_dikkatler();

		if ( empty( $maddeler ) ) {
			return '';
		}

		$kritik = false;
		foreach ( $maddeler as $madde ) {
			if ( ! empty( $madde['kritik'] ) ) {
				$kritik = true;
				break;
			}
		}

		ob_start();
		?>
		<div class="qrc-attention<?php echo $kritik ? ' is-critical' : ''; ?>">
			<p class="qrc-attention-title">
				<span class="dashicons dashicons-warning" aria-hidden="true"></span>
				Dikkat gerektirenler
			</p>
			<ul class="qrc-attention-list">
				<?php foreach ( $maddeler as $madde ) : ?>
					<li>
						<a href="<?php echo esc_url( $madde['url'] ); ?>"><?php echo esc_html( $madde['metin'] ); ?></a>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
		<?php

		return (string) ob_get_clean();
	}
}

if ( ! function_exists( 'qrms_module_qr_ceviri_hub_kartlari' ) ) {

	/**
	 * Hub kartları: her adım + canlı durum.
	 *
	 * @return array<int,array{url:string,title:string,desc:string,icon:string}>
	 */
	function qrms_module_qr_ceviri_hub_kartlari() {
		$durumlar = qrms_module_qr_ceviri_hub_durumlari();
		$kartlar  = array();

		foreach ( qrms_module_qr_ceviri_sayfalar() as $slug => $sayfa ) {
			$durum = isset( $durumlar[ $slug ] ) ? $durumlar[ $slug ] : '';
			$kartlar[] = array(
				'url'   => qrms_module_qr_ceviri_sayfa_url( $slug ),
				'title' => $sayfa['title'],
				'desc'  => '' !== $durum ? $durum : $sayfa['desc'],
				'icon'  => $sayfa['icon'],
			);
		}

		return $kartlar;
	}
}

if ( ! function_exists( 'qrms_module_qr_ceviri_hub' ) ) {

	/**
	 * Sol menüdeki "Dil / Çeviri Ayarları" satırının açtığı hub.
	 *
	 * @return void
	 */
	function qrms_module_qr_ceviri_hub() {
		if ( ! current_user_can( QRMS_Admin::CAPABILITY ) ) {
			wp_die( esc_html__( 'Bu sayfayı görüntüleme yetkiniz yok.', 'qrms' ) );
		}

		QRMS_Admin::render_hub(
			array(
				'title'  => QRMS_Helpers::get_module_name( 'qr-ceviri' ),
				'intro'  => 'Çeviri işleri adımlara ayrıldı. Sıra zorunlu değil — istediğiniz karta dokunun.',
				'accent' => '#2271b1',
				'notice' => qrms_module_qr_ceviri_dikkat_html(),
				'cards'  => qrms_module_qr_ceviri_hub_kartlari(),
			)
		);
	}
}

if ( ! function_exists( 'qrms_module_qr_ceviri_adim_seridi' ) ) {

	/**
	 * Alt sayfanın ilerleme göstergesi — kilitli sihirbaz değil, atlanabilir.
	 *
	 * @param string $aktif_slug Bu sayfanın slug'ı.
	 * @return void
	 */
	function qrms_module_qr_ceviri_adim_seridi( $aktif_slug ) {
		$sayfalar = qrms_module_qr_ceviri_sayfalar();
		$sluglar  = array_keys( $sayfalar );
		$toplam   = count( $sluglar );
		$sira     = array_search( $aktif_slug, $sluglar, true );
		$sira     = false === $sira ? 0 : $sira + 1;
		$baslik   = isset( $sayfalar[ $aktif_slug ] ) ? $sayfalar[ $aktif_slug ]['title'] : '';
		?>
		<nav class="qrc-steps" aria-label="Çeviri adımları">
			<p class="qrc-steps-meta">
				<?php
				printf(
					'Adım %1$d / %2$d%3$s — istediğiniz adıma geçebilirsiniz.',
					(int) $sira,
					(int) $toplam,
					'' !== $baslik ? ' · ' . $baslik : ''
				);
				?>
			</p>
			<ol class="qrc-steps-list">
				<?php
				$i = 0;
				foreach ( $sayfalar as $slug => $sayfa ) :
					++$i;
					$current = ( $slug === $aktif_slug );
					?>
					<li class="<?php echo $current ? 'is-current' : ''; ?>">
						<?php if ( $current ) : ?>
							<span class="qrc-steps-link" aria-current="page">
								<span class="qrc-steps-num"><?php echo (int) $i; ?></span>
								<?php echo esc_html( $sayfa['title'] ); ?>
							</span>
						<?php else : ?>
							<a class="qrc-steps-link" href="<?php echo esc_url( qrms_module_qr_ceviri_sayfa_url( $slug ) ); ?>">
								<span class="qrc-steps-num"><?php echo (int) $i; ?></span>
								<?php echo esc_html( $sayfa['title'] ); ?>
							</a>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ol>
		</nav>
		<?php
	}
}

if ( ! function_exists( 'qrms_module_qr_ceviri_sayfa_ac' ) ) {

	/**
	 * Alt sayfa iskeletini açar (yetki + wrap + adım şeridi).
	 *
	 * @param string $slug Aktif adım.
	 * @return void
	 */
	function qrms_module_qr_ceviri_sayfa_ac( $slug ) {
		if ( ! current_user_can( QRMS_Admin::CAPABILITY ) ) {
			wp_die( 'Yetkiniz yok.' );
		}

		echo '<div class="wrap qrc-wrap">';
		qrms_module_qr_ceviri_adim_seridi( $slug );
	}
}

if ( ! function_exists( 'qrms_module_qr_ceviri_sayfa_kapat' ) ) {

	/**
	 * Alt sayfa iskeletini kapatır.
	 *
	 * @return void
	 */
	function qrms_module_qr_ceviri_sayfa_kapat() {
		echo '</div>';
	}
}

if ( ! function_exists( 'qrms_module_qr_ceviri_baslik' ) ) {

	/**
	 * Dashicon'lu bölüm başlığı — emoji yok.
	 *
	 * @param string $ikon   dashicons-* sınıfı.
	 * @param string $baslik Görünen metin.
	 * @param string $seviye h2|h1|h3.
	 * @return void
	 */
	function qrms_module_qr_ceviri_baslik( $ikon, $baslik, $seviye = 'h2' ) {
		$seviye = in_array( $seviye, array( 'h1', 'h2', 'h3' ), true ) ? $seviye : 'h2';
		printf(
			'<%1$s class="title qrc-heading"><span class="dashicons %2$s" aria-hidden="true"></span> %3$s</%1$s>',
			esc_attr( $seviye ),
			esc_attr( $ikon ),
			esc_html( $baslik )
		);
	}
}

if ( ! function_exists( 'qrms_module_qr_ceviri_admin_menu' ) ) {

	/**
	 * Alt sayfaları ve eski adresi kaydeder.
	 *
	 * @return void
	 */
	function qrms_module_qr_ceviri_admin_menu() {
		global $submenu;

		if ( empty( $submenu[ QRMS_Admin::MENU_SLUG ] ) ) {
			return;
		}

		foreach ( qrms_module_qr_ceviri_sayfalar() as $slug => $sayfa ) {
			add_submenu_page(
				QRMS_Admin::MENU_SLUG,
				$sayfa['title'],
				$sayfa['title'],
				QRMS_Admin::CAPABILITY,
				$slug,
				QRMS_Admin::register_module_subpage( 'qr-ceviri', $slug, $sayfa['render'] )
			);
		}

		add_submenu_page(
			QRMS_Admin::MENU_SLUG,
			'QR Çeviri (klasik görünüm)',
			'QR Çeviri (klasik görünüm)',
			QRMS_Admin::CAPABILITY,
			QRMS_CEVIRI_KLASIK_SAYFA,
			QRMS_Admin::register_module_subpage( 'qr-ceviri', QRMS_CEVIRI_KLASIK_SAYFA, 'qrmenu_trans_page' )
		);

		add_submenu_page(
			'',
			'QR Çeviri',
			'QR Çeviri',
			QRMS_Admin::CAPABILITY,
			QRMS_CEVIRI_ESKI_SAYFA,
			'qrms_module_qr_ceviri_eski_adresi_yonlendir'
		);
	}
}

if ( ! function_exists( 'qrms_module_qr_ceviri_eski_adresi_yonlendir' ) ) {

	/**
	 * Eski slug'dan hub'a yönlendirir.
	 *
	 * @return void
	 */
	function qrms_module_qr_ceviri_eski_adresi_yonlendir() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Yönlendirme; durum değişmez.
		$slug = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';

		if ( class_exists( 'QRMS_Helpers' ) && method_exists( 'QRMS_Helpers', 'legacy_slug_hit' ) ) {
			QRMS_Helpers::legacy_slug_hit( $slug );
		}

		wp_safe_redirect( QRMS_Admin::get_module_page_url( 'qr-ceviri' ) );
		exit;
	}
}

if ( ! function_exists( 'qrms_module_qr_ceviri_ekran_mi' ) ) {

	/**
	 * Bu istek çeviri yönetim ekranlarından birinde mi?
	 *
	 * @param string $page page= slug'ı.
	 * @return bool
	 */
	function qrms_module_qr_ceviri_ekran_mi( $page ) {
		if ( class_exists( 'QRMS_Admin' ) && QRMS_Admin::get_module_page_slug( 'qr-ceviri' ) === $page ) {
			return true;
		}

		if ( 0 === strpos( $page, 'qrms-cv-' ) ) {
			return true;
		}

		return QRMS_CEVIRI_ESKI_SAYFA === $page;
	}
}
