<?php
/**
 * Kısa kod: [qmo_sepet] — menüden direkt sipariş.
 *
 * OTURUM KOŞULU: Sepet yalnızca geçerli bir masa oturumu varken render
 * edilir (HMAC imzalı `qr_masa_token` çerezi). Cookie yalnızca kayıtlı
 * bir `?masa=` QR'ı okutulduğunda yazılır; doğrudan siteye giren
 * ziyaretçide boş string döner. Yöneticiler (manage_options) masa-dogrulama.php
 * ile aynı gerekçeyle muaf tutulur — test/geliştirme kolaylığı.
 *
 * Sipariş `POST /wp-json/qrservis/v1/order` ucuna gider (rest-order.php).
 *
 * @package QR_Menu_Official
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// WPCode / Code Snippets üzerindeki eski "QMO Sepet — Menüden Direkt
// Sipariş" snippet'i hâlâ [qmo_sepet] kaydediyorsa WordPress son
// add_shortcode çağrısını tutar — hangisinin kazandığı yükleme sırasına
// bağlıdır. Eski snippet'i kapatın; aksi halde bu kayıt ezilebilir.
add_shortcode( 'qmo_sepet', 'qmo_sepet_shortcode' );

/**
 * Sepetin gösterilip gösterilmeyeceğine karar verir.
 *
 * qmo_oturum() HMAC çerezini doğrular; çerez yalnızca geçerli bir masa
 * QR'ı okutulunca yazılır. Sayfa kilidi (qmo_korumali_sayfa_mi) bundan
 * bağımsızdır ve varsayılan kapalıdır — o yüzden genel kilit "oturum
 * açık" saymaz.
 *
 * @return bool
 */
if ( ! function_exists( 'qmo_sepet_izinli_mi' ) ) {
	function qmo_sepet_izinli_mi() {
		// masa-dogrulama.php: yöneticiler kilitlenmez.
		if ( function_exists( 'is_user_logged_in' ) && is_user_logged_in() && current_user_can( 'manage_options' ) ) {
			return true;
		}

		return function_exists( 'qmo_oturum' ) && (bool) qmo_oturum();
	}
}

/**
 * 1 TL'nin USD/EUR karşılığı. Yaklaşık fiyat satırı için.
 *
 * @return array{USD:float,EUR:float}
 */
if ( ! function_exists( 'qmo_sepet_kur' ) ) {
	function qmo_sepet_kur() {
		$saklanan = get_transient( 'qmo_sepet_kur' );
		if ( is_array( $saklanan ) && isset( $saklanan['USD'], $saklanan['EUR'] ) ) {
			return array(
				'USD' => (float) $saklanan['USD'],
				'EUR' => (float) $saklanan['EUR'],
			);
		}

		$kur = array(
			'USD' => 0.0207,
			'EUR' => 0.0179,
		);

		if ( function_exists( 'wp_remote_get' ) ) {
			$yanit = wp_remote_get( 'https://open.er-api.com/v6/latest/TRY', array( 'timeout' => 3 ) );
			if ( ! is_wp_error( $yanit ) ) {
				$data = json_decode( wp_remote_retrieve_body( $yanit ), true );
				if ( ! empty( $data['rates']['USD'] ) && ! empty( $data['rates']['EUR'] ) ) {
					$kur['USD'] = (float) $data['rates']['USD'];
					$kur['EUR'] = (float) $data['rates']['EUR'];
				}
			}
		}

		set_transient( 'qmo_sepet_kur', $kur, 12 * HOUR_IN_SECONDS );

		return $kur;
	}
}

/**
 * Sipariş REST adresi.
 *
 * @return string
 */
if ( ! function_exists( 'qmo_sepet_order_url' ) ) {
	function qmo_sepet_order_url() {
		if ( function_exists( 'rest_url' ) ) {
			return esc_url_raw( rest_url( 'qrservis/v1/order' ) );
		}

		return esc_url_raw( home_url( '/wp-json/qrservis/v1/order' ) );
	}
}

/**
 * "Sepet ile Sipariş" anahtarı açık mı?
 *
 * Option yoksa (hiç kaydedilmemiş) kapalı kabul edilir — opt-in.
 *
 * @return bool
 */
if ( ! function_exists( 'qmo_sepet_aktif_mi' ) ) {
	function qmo_sepet_aktif_mi() {
		return (int) get_option( 'qmo_sepet_aktif', 0 ) === 1;
	}
}

/**
 * Menü HTML'inin sonuna sepeti ekler — yalnızca ayar açıksa.
 *
 * restoran-menu `shortcode_menu()` burayı function_exists ile çağırır;
 * qr-chatbot yüklü değilse menü çıktısı değişmez.
 *
 * @param string $cikti Menü shortcode çıktısı.
 * @return string
 */
if ( ! function_exists( 'qmo_sepet_menuye_ekle' ) ) {
	function qmo_sepet_menuye_ekle( $cikti ) {
		if ( ! qmo_sepet_aktif_mi() ) {
			return (string) $cikti;
		}

		return (string) $cikti . qmo_sepet_shortcode();
	}
}

/**
 * Bu istekte sepet HTML'i basıldı mı?
 *
 * Aynı sayfada otomatik enjeksiyon + elle [qmo_sepet] çakışmasın diye
 * shortcode bir kez çıktı verdikten sonra bayrak kalkar. $ata verilirse
 * bayrağı yazar (testler false geçerek sıfırlar); null yalnızca okur.
 *
 * @param bool|null $ata true/false atar, null okur.
 * @return bool
 */
if ( ! function_exists( 'qmo_sepet_istekte_basildi' ) ) {
	function qmo_sepet_istekte_basildi( $ata = null ) {
		static $basildi = false;
		if ( null !== $ata ) {
			$basildi = (bool) $ata;
		}
		return $basildi;
	}
}

/**
 * Sepet çıktısı.
 *
 * Aynı istekte ikinci kez çağrılırsa boş döner: otomatik menü enjeksiyonu
 * ile elle yazılmış [qmo_sepet] aynı sayfada id="qmo-sepet-root" çakışması
 * üretmesin. Bayrak yalnızca HTML basıldığında kalkar; izin yokken boş
 * dönüş sonraki denemeyi engellemez.
 *
 * @return string
 */
if ( ! function_exists( 'qmo_sepet_shortcode' ) ) {
	function qmo_sepet_shortcode() {
		if ( qmo_sepet_istekte_basildi() ) {
			return '';
		}

		// Sadece geçerli masa oturumu olan müşterilere (ve yöneticilere) göster.
		if ( ! qmo_sepet_izinli_mi() ) {
			return '';
		}

		qmo_sepet_istekte_basildi( true );

		if ( function_exists( 'qmo_asset_enqueue' ) ) {
			qmo_asset_enqueue( 'qmo-sepet' );
		}

		wp_localize_script(
			'qmo-sepet',
			'qmoSepet',
			array(
				'endpoint'  => qmo_sepet_order_url(),
				'restNonce' => wp_create_nonce( 'wp_rest' ),
				'kur'       => qmo_sepet_kur(),
				'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
				'nonce'     => wp_create_nonce( QMO_NONCE_ACTION ),
				'analitik'  => class_exists( 'QRMS_Analitik' ),
			)
		);

		ob_start();
		?>
		<div class="qmo-sepet-root" id="qmo-sepet-root">

			<div class="qmo-bar" id="qmo-bar" role="button" tabindex="0" aria-label="Sepeti aç">
				<div class="qmo-bar-l">
					<span class="qmo-bar-badge" id="qmo-badge">0</span>
					<span id="qmo-bar-txt">Sepet</span>
				</div>
				<div class="qmo-bar-r" id="qmo-bar-tot">₺0</div>
			</div>

			<div class="qmo-ov" id="qmo-ov"></div>

			<div class="qmo-dr" id="qmo-dr" role="dialog" aria-label="Sepet">
				<div class="qmo-dr-h">
					<h3 id="qmo-dr-title">Sepetiniz</h3>
					<button type="button" class="qmo-x" id="qmo-x" aria-label="Kapat">×</button>
				</div>

				<div class="qmo-list" id="qmo-list"></div>

				<div class="qmo-ft">
					<div class="qmo-tot">
						<b id="qmo-t-top">Toplam</b>
						<span id="qmo-tot">₺0</span>
					</div>
					<div class="qmo-yak" id="qmo-yak"></div>
					<div class="qmo-tl" id="qmo-tl-not"></div>
					<button type="button" class="qmo-send" id="qmo-send">Siparişi Gönder</button>
				</div>
			</div>

			<div class="qmo-toast" id="qmo-toast"></div>
		</div>
		<?php
		return ob_get_clean();
	}
}
