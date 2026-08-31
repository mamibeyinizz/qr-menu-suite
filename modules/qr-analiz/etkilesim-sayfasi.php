<?php
/**
 * KATEGORİ: Müşteri Etkileşimi (qrms-an-etkilesim).
 *
 * Veri kaynağı Faz 8A'nın yazdığı olaylardır: chatbot_message, review_submit,
 * form_submit, reward_issued, reward_redeemed, lang_switch, gallery_view.
 * Bu sayfa yeni olay ÜRETMEZ.
 *
 * BÖLÜM = MODÜL. Chatbot mesajları qr-chatbot'a, yorum/form/ödül
 * yorum-feedback'e, dil dağılımı qr-ceviri'ye, galeri qr-galeri'ye bağlıdır.
 * Lisansta pasif bir modülün bölümü basılmaz. Hepsi pasifse hub kartı da
 * yoktur (bkz. qrms_module_qr_analiz_gecerli_sayfalar); sayfa yine kayıtlı
 * kalır ki doğrudan URL anlamlı bir mesaj göstersin.
 *
 * PERFORMANS. Tek GROUP BY (idx_td / idx_masa_td) + chatbot için bir zaman
 * kırılımı. N+1 yok. Sayaçlar istek içi önbelleklidir.
 *
 * @package QR_Menu_Suite
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'qrms_analitik_modul_aktif' ) ) {

	/**
	 * Modül bu kurulumda lisanslı mı?
	 *
	 * Yükleyici yoksa (stub test) evet sayılır.
	 *
	 * @param string $slug Modül slug'ı.
	 * @return bool
	 */
	function qrms_analitik_modul_aktif( $slug ) {
		if ( '' === $slug ) {
			return true;
		}

		if ( ! class_exists( 'QRMS_Module_Loader' ) ) {
			return true;
		}

		return QRMS_Module_Loader::is_module_active( $slug );
	}
}

if ( ! function_exists( 'qrms_analitik_etkilesim_bolumleri' ) ) {

	/**
	 * Sayfadaki bölümlerin lisans durumu.
	 *
	 * @return array{chatbot:bool,yorum:bool,ceviri:bool,galeri:bool}
	 */
	function qrms_analitik_etkilesim_bolumleri() {
		return array(
			'chatbot' => qrms_analitik_modul_aktif( 'qr-chatbot' ),
			'yorum'   => qrms_analitik_modul_aktif( 'yorum-feedback' ),
			'ceviri'  => qrms_analitik_modul_aktif( 'qr-ceviri' ),
			'galeri'  => qrms_analitik_modul_aktif( 'qr-galeri' ),
		);
	}
}

if ( ! function_exists( 'qrms_analitik_etkilesim_lisansli' ) ) {

	/**
	 * En az bir bölüm lisanslı mı?
	 *
	 * @return bool
	 */
	function qrms_analitik_etkilesim_lisansli() {
		foreach ( qrms_analitik_etkilesim_bolumleri() as $acik ) {
			if ( $acik ) {
				return true;
			}
		}

		return false;
	}
}

if ( ! function_exists( 'qrms_analitik_dil_etiketi' ) ) {

	/**
	 * Dil kodunun okunur adı.
	 *
	 * @param string $kod Dil kodu (tr, en, …).
	 * @return string
	 */
	function qrms_analitik_dil_etiketi( $kod ) {
		$kod = (string) $kod;

		if ( function_exists( 'qrmenu_get_langs' ) ) {
			$tumu = qrmenu_get_langs();

			if ( isset( $tumu[ $kod ]['name'] ) ) {
				return (string) $tumu[ $kod ]['name'];
			}
		}

		$yedek = array(
			'tr'    => 'Türkçe',
			'en'    => 'English',
			'ar'    => 'العربية',
			'de'    => 'Deutsch',
			'fr'    => 'Français',
			'ru'    => 'Русский',
			'es'    => 'Español',
			'it'    => 'Italiano',
			'zh-CN' => '中文',
		);

		return isset( $yedek[ $kod ] ) ? $yedek[ $kod ] : $kod;
	}
}

if ( ! function_exists( 'qrms_analitik_etkilesim_hesapla' ) ) {

	/**
	 * GROUP BY satırlarını özet / tablolara çevirir — saf fonksiyon.
	 *
	 * @param array $sayaclar QRMS_Analitik::olay_sayaclari() satırları.
	 * @return array<string,mixed>
	 */
	function qrms_analitik_etkilesim_hesapla( array $sayaclar ) {
		$ozet = array(
			'chatbot'         => 0,
			'review'          => 0,
			'form'            => 0,
			'reward_issued'   => 0,
			'reward_redeemed' => 0,
			'reward_oran'     => 0,
			'gallery'         => 0,
			'lang'            => 0,
		);

		$formlar = array();
		$diller  = array();

		foreach ( $sayaclar as $r ) {
			if ( ! is_array( $r ) ) {
				continue;
			}

			$tip  = isset( $r['event_type'] ) ? (string) $r['event_type'] : '';
			$adet = isset( $r['adet'] ) ? (int) $r['adet'] : 0;
			$ad   = isset( $r['item_name'] ) ? (string) $r['item_name'] : '';

			if ( $adet <= 0 || '' === $tip ) {
				continue;
			}

			if ( 'chatbot_message' === $tip ) {
				$ozet['chatbot'] += $adet;
			} elseif ( 'review_submit' === $tip ) {
				$ozet['review'] += $adet;
			} elseif ( 'form_submit' === $tip ) {
				$ozet['form'] += $adet;
				if ( ! isset( $formlar[ $ad ] ) ) {
					$formlar[ $ad ] = 0;
				}
				$formlar[ $ad ] += $adet;
			} elseif ( 'reward_issued' === $tip ) {
				$ozet['reward_issued'] += $adet;
			} elseif ( 'reward_redeemed' === $tip ) {
				$ozet['reward_redeemed'] += $adet;
			} elseif ( 'gallery_view' === $tip ) {
				$ozet['gallery'] += $adet;
			} elseif ( 'lang_switch' === $tip ) {
				$ozet['lang'] += $adet;
				if ( ! isset( $diller[ $ad ] ) ) {
					$diller[ $ad ] = 0;
				}
				$diller[ $ad ] += $adet;
			}
		}

		$ozet['reward_oran'] = $ozet['reward_issued'] > 0
			? (int) round( ( $ozet['reward_redeemed'] / $ozet['reward_issued'] ) * 100 )
			: 0;

		arsort( $formlar );
		arsort( $diller );

		$form_satir = array();

		foreach ( $formlar as $ad => $adet ) {
			$form_satir[] = array(
				'ad'   => '' !== $ad ? $ad : '—',
				'adet' => (int) $adet,
			);
		}

		$dil_satir = array();

		foreach ( $diller as $kod => $adet ) {
			$dil_satir[] = array(
				'kod'  => $kod,
				'ad'   => qrms_analitik_dil_etiketi( $kod ),
				'adet' => (int) $adet,
				'pay'  => $ozet['lang'] > 0 ? (int) round( ( $adet / $ozet['lang'] ) * 100 ) : 0,
			);
		}

		$bos = ( 0 === $ozet['chatbot']
			&& 0 === $ozet['review']
			&& 0 === $ozet['form']
			&& 0 === $ozet['reward_issued']
			&& 0 === $ozet['gallery']
			&& 0 === $ozet['lang'] );

		return array(
			'ozet'    => $ozet,
			'formlar' => $form_satir,
			'diller'  => $dil_satir,
			'bos'     => $bos,
		);
	}
}

if ( ! function_exists( 'qrms_analitik_etkilesim_verisi' ) ) {

	/**
	 * Sayfanın verisi — TEK yerde toplanır (ekran + CSV).
	 *
	 * @param array  $aralik QRMS_Analitik_Filtre::aralik() çıktısı.
	 * @param string $masa   Masa filtresi.
	 * @param string $kirilim Zaman kırılımı.
	 * @return array<string,mixed>
	 */
	function qrms_analitik_etkilesim_verisi( array $aralik, $masa = '', $kirilim = 'daily' ) {
		$kutu    = &qrms_analitik_onbellek_kutusu();
		$anahtar = 'etkilesim|' . $aralik['bas'] . '|' . $aralik['bit'] . '|' . (string) $masa . '|' . $kirilim;

		if ( isset( $kutu[ $anahtar ] ) ) {
			return $kutu[ $anahtar ];
		}

		$bolumler = qrms_analitik_etkilesim_bolumleri();
		$tipler   = array();

		if ( $bolumler['chatbot'] ) {
			$tipler[] = 'chatbot_message';
		}

		if ( $bolumler['yorum'] ) {
			$tipler[] = 'review_submit';
			$tipler[] = 'form_submit';
			$tipler[] = 'reward_issued';
			$tipler[] = 'reward_redeemed';
		}

		if ( $bolumler['ceviri'] ) {
			$tipler[] = 'lang_switch';
		}

		if ( $bolumler['galeri'] ) {
			$tipler[] = 'gallery_view';
		}

		$sayaclar = empty( $tipler )
			? array()
			: QRMS_Analitik::olay_sayaclari( $tipler, $aralik['bas'], $aralik['bit'], $masa );

		$hesap = qrms_analitik_etkilesim_hesapla( $sayaclar );

		$grafik = array();

		if ( $bolumler['chatbot'] ) {
			$grafik = QRMS_Analitik::olay_grafik(
				'chatbot_message',
				$kirilim,
				$aralik['bas'],
				$aralik['bit'],
				$masa
			);
		}

		$kutu[ $anahtar ] = array_merge(
			$hesap,
			array(
				'grafik'   => $grafik,
				'bolumler' => $bolumler,
				'kirilim'  => $kirilim,
			)
		);

		return $kutu[ $anahtar ];
	}
}

if ( ! function_exists( 'qrms_analitik_sayfa_etkilesim' ) ) {

	/**
	 * Müşteri Etkileşimi ekranı.
	 *
	 * @return void
	 */
	function qrms_analitik_sayfa_etkilesim() {
		if ( ! current_user_can( QRMS_Admin::CAPABILITY ) ) {
			wp_die( esc_html__( 'Bu sayfayı görüntüleme yetkiniz yok.', 'qrms' ) );
		}

		if ( ! qrms_analitik_etkilesim_lisansli() ) {
			?>
			<div class="wrap qrms-an qrms-an-etkilesim">
				<div class="qrms-an-header">
					<div class="qrms-an-header-text">
						<h1 class="qrms-an-title"><?php esc_html_e( 'Müşteri Etkileşimi', 'qrms' ); ?></h1>
					</div>
				</div>

				<div class="qrms-an-teshis qrms-an-teshis-uyari">
					<span class="qrms-an-teshis-icon dashicons dashicons-lock" aria-hidden="true"></span>
					<div class="qrms-an-teshis-body">
						<h2 class="qrms-an-teshis-title"><?php esc_html_e( 'Bu kategori bu lisansta kapalı', 'qrms' ); ?></h2>
						<p class="qrms-an-teshis-text">
							<?php esc_html_e( 'Chatbot, yorum, çeviri ve galeri modüllerinin hiçbiri lisansınızda aktif değil. Boş bir tablo, veri yokmuş gibi görünmesin diye bilinçli olarak basılmıyor.', 'qrms' ); ?>
						</p>
					</div>
				</div>
			</div>
			<?php
			return;
		}

		$bolumler = qrms_analitik_etkilesim_bolumleri();
		$csv_url  = add_query_arg(
			array(
				'action'   => 'qrms_analitik_csv',
				'kategori' => 'etkilesim',
				'donem'    => QRMS_Analitik_Filtre::donem(),
				'bas'      => QRMS_Analitik_Filtre::bas(),
				'bit'      => QRMS_Analitik_Filtre::bit(),
				'masa'     => QRMS_Analitik_Filtre::masa(),
				'security' => wp_create_nonce( QRMS_Analitik::NONCE_CSV ),
			),
			admin_url( 'admin-ajax.php' )
		);
		?>
		<div class="wrap qrms-an qrms-an-etkilesim">

			<div class="qrms-an-header">
				<div class="qrms-an-header-text">
					<h1 class="qrms-an-title"><?php esc_html_e( 'Müşteri Etkileşimi', 'qrms' ); ?></h1>
					<p class="qrms-an-subtitle">
						<?php esc_html_e( 'Chatbot mesajları, yorum ve form gönderimleri, ödül kodları, dil seçimi ve galeri.', 'qrms' ); ?>
					</p>
				</div>

				<div class="qrms-an-header-actions">
					<a class="qrms-an-btn" href="<?php echo esc_url( $csv_url ); ?>">
						<span class="dashicons dashicons-download" aria-hidden="true"></span>
						<?php esc_html_e( 'Bu sayfayı CSV indir', 'qrms' ); ?>
					</a>
				</div>
			</div>

			<?php qrms_analitik_filtre_cubugu( 'qrms-an-etkilesim' ); ?>

			<div id="qrms-an-etkilesim-bos" hidden></div>

			<?php if ( $bolumler['chatbot'] ) : ?>
				<div class="qrms-an-panel" id="qrms-an-etk-chatbot-panel">
					<div class="qrms-an-panel-header">
						<h2 class="qrms-an-panel-title">
							<span class="dashicons dashicons-format-chat" aria-hidden="true"></span>
							<?php esc_html_e( 'Chatbot mesajları', 'qrms' ); ?>
						</h2>
					</div>
					<p class="qrms-an-panel-note">
						<?php esc_html_e( 'Misafirin asistanla konuştuğu turlar. Mesaj metni saklanmaz; yalnızca sayı ve zaman dağılımı tutulur.', 'qrms' ); ?>
					</p>
					<div class="qrms-an-cards" id="qrms-an-etk-chatbot-cards" aria-live="polite">
						<div class="qrms-an-card qrms-an-skeleton"></div>
					</div>
					<div id="qrms-an-etk-chatbot-grafik">
						<div class="qrms-an-loading"><?php esc_html_e( 'Yükleniyor', 'qrms' ); ?></div>
					</div>
				</div>
			<?php endif; ?>

			<?php if ( $bolumler['yorum'] ) : ?>
				<div class="qrms-an-panel" id="qrms-an-etk-yorum-panel">
					<div class="qrms-an-panel-header">
						<h2 class="qrms-an-panel-title">
							<span class="dashicons dashicons-star-filled" aria-hidden="true"></span>
							<?php esc_html_e( 'Yorum ve form gönderimi', 'qrms' ); ?>
						</h2>
					</div>
					<p class="qrms-an-panel-note">
						<?php esc_html_e( 'Yorum ve form metinleri saklanmaz. Form kırılımı gönderim anındaki form adını taşır.', 'qrms' ); ?>
					</p>
					<div class="qrms-an-cards" id="qrms-an-etk-yorum-cards" aria-live="polite">
						<div class="qrms-an-card qrms-an-skeleton"></div>
						<div class="qrms-an-card qrms-an-skeleton"></div>
					</div>
					<div id="qrms-an-etk-formlar">
						<div class="qrms-an-loading"><?php esc_html_e( 'Yükleniyor', 'qrms' ); ?></div>
					</div>
				</div>

				<div class="qrms-an-panel" id="qrms-an-etk-odul-panel">
					<div class="qrms-an-panel-header">
						<h2 class="qrms-an-panel-title">
							<span class="dashicons dashicons-awards" aria-hidden="true"></span>
							<?php esc_html_e( 'Ödül kodları', 'qrms' ); ?>
						</h2>
					</div>
					<p class="qrms-an-panel-note">
						<?php esc_html_e( 'Kaynak ödül tablosudur; buradaki sayılar menü trafiğiyle aynı zaman ekseninde karşılaştırmak içindir. Dönüşüm, kullanılan / üretilen oranıdır.', 'qrms' ); ?>
					</p>
					<div class="qrms-an-cards" id="qrms-an-etk-odul-cards" aria-live="polite">
						<div class="qrms-an-card qrms-an-skeleton"></div>
						<div class="qrms-an-card qrms-an-skeleton"></div>
						<div class="qrms-an-card qrms-an-skeleton"></div>
					</div>
				</div>
			<?php endif; ?>

			<?php if ( $bolumler['ceviri'] ) : ?>
				<div class="qrms-an-panel" id="qrms-an-etk-dil-panel">
					<div class="qrms-an-panel-header">
						<h2 class="qrms-an-panel-title">
							<span class="dashicons dashicons-translation" aria-hidden="true"></span>
							<?php esc_html_e( 'Dil dağılımı', 'qrms' ); ?>
						</h2>
					</div>
					<p class="qrms-an-panel-note">
						<?php esc_html_e( 'Hangi dile geçildiği sayılır; hangi dilden gelindiği değil. Çeviri yatırımının nereye yapılacağını gösterir.', 'qrms' ); ?>
					</p>
					<div id="qrms-an-etk-diller">
						<div class="qrms-an-loading"><?php esc_html_e( 'Yükleniyor', 'qrms' ); ?></div>
					</div>
				</div>
			<?php endif; ?>

			<?php if ( $bolumler['galeri'] ) : ?>
				<div class="qrms-an-panel" id="qrms-an-etk-galeri-panel">
					<div class="qrms-an-panel-header">
						<h2 class="qrms-an-panel-title">
							<span class="dashicons dashicons-format-gallery" aria-hidden="true"></span>
							<?php esc_html_e( 'Galeri görüntüleme', 'qrms' ); ?>
						</h2>
					</div>
					<div class="qrms-an-cards" id="qrms-an-etk-galeri-cards" aria-live="polite">
						<div class="qrms-an-card qrms-an-skeleton"></div>
					</div>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}
}
