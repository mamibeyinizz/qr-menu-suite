<?php
/**
 * Kısa kodlar: [garson_butonu], [hesap_iste_butonu], [ikili_buton], [qr_garson_hesap]
 *
 * OTURUM KOŞULU: Butonlar yalnızca geçerli bir masa oturumu varken render
 * edilir. Tıklama, ortak nonce ile AJAX action'ına gider.
 *
 * Tıklama, ajax-waiter-bill.php içindeki garson_cagir / hesap_iste /
 * qrservis_call uçlarına gider; uçlar QMO_Firestore üzerinden yazar.
 *
 * @package QR_Menu_Official
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_shortcode( 'garson_butonu', 'qmo_garson_butonu_shortcode' );
add_shortcode( 'hesap_iste_butonu', 'qmo_hesap_butonu_shortcode' );
add_shortcode( 'ikili_buton', 'qmo_ikili_buton_shortcode' );
add_shortcode( 'qr_garson_hesap', 'qmo_ikili_buton_shortcode' );

/**
 * Tekil garson çağrı butonu.
 *
 * @return string
 */
if ( ! function_exists( 'qmo_garson_butonu_shortcode' ) ) {
	function qmo_garson_butonu_shortcode() {
		return qmo_cagri_butonlari_html( 'garson' );
	}
}

/**
 * Tekil hesap isteme butonu.
 *
 * @return string
 */
if ( ! function_exists( 'qmo_hesap_butonu_shortcode' ) ) {
	function qmo_hesap_butonu_shortcode() {
		return qmo_cagri_butonlari_html( 'hesap' );
	}
}

/**
 * Garson + hesap yan yana.
 *
 * @return string
 */
if ( ! function_exists( 'qmo_ikili_buton_shortcode' ) ) {
	function qmo_ikili_buton_shortcode() {
		return qmo_cagri_butonlari_html( 'ikili' );
	}
}

/**
 * Çağrı butonu HTML'i.
 *
 * @param string $tip 'garson' | 'hesap' | 'ikili'.
 * @return string
 */
if ( ! function_exists( 'qmo_cagri_butonlari_html' ) ) {
	function qmo_cagri_butonlari_html( $tip ) {
		if ( ! qmo_oturum() ) {
			return qmo_oturum_uyari_kutusu(
				qmo_ceviri_chat( __( 'Çağrı butonlarını kullanmak için masanızdaki QR kodu okutun.', 'qrms' ) )
			);
		}

		qmo_asset_enqueue( 'ikili' === $tip ? 'qmo-garson-hesap' : 'qmo-buttons' );

		ob_start();
		?>
		<div class="qmo-cagri-bar qmo-cagri-bar--<?php echo esc_attr( $tip ); ?>">
			<?php if ( 'hesap' !== $tip ) : ?>
				<button type="button" class="qmo-cagri-btn qmo-cagri-btn--garson" data-qmo-cagri="garson">
					<span class="qmo-cagri-ikon" aria-hidden="true">🛎️</span>
					<span><?php echo esc_html( qmo_ceviri_chat( __( 'Garson Çağır', 'qrms' ) ) ); ?></span>
				</button>
			<?php endif; ?>
			<?php if ( 'garson' !== $tip ) : ?>
				<button type="button" class="qmo-cagri-btn qmo-cagri-btn--hesap" data-qmo-cagri="hesap">
					<span class="qmo-cagri-ikon" aria-hidden="true">🧾</span>
					<span><?php echo esc_html( qmo_ceviri_chat( __( 'Hesap İste', 'qrms' ) ) ); ?></span>
				</button>
			<?php endif; ?>
			<p class="qmo-cagri-durum" hidden></p>
		</div>
		<?php
		return ob_get_clean();
	}
}
