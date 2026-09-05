<?php
/**
 * AJAX: qmo_sepet_olay — sepete ekleme / çıkarma analitik olayları.
 *
 * Sepet localStorage'da yaşar; bu uç yalnızca analitik yazımı içindir.
 * qr-analiz lisansta pasifse class_exists başarısız olur ve yanıt yine
 * success döner — sepet akışı analitiğe bağımlı değildir.
 *
 * Masa istemciden ALINMAZ: doğrulanmış oturum çerezinden okunur.
 *
 * @package QR_Menu_Official
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'wp_ajax_qmo_sepet_olay', 'qmo_ajax_sepet_olay' );
add_action( 'wp_ajax_nopriv_qmo_sepet_olay', 'qmo_ajax_sepet_olay' );

/**
 * Toplu sepet olaylarını doğrula ve kaydet.
 *
 * Yanıt her zaman success'tir (akışı kesmemek için). Yazılamayan olay
 * atlanır; kullanıcıya hata gösterilmez.
 *
 * @return void
 */
if ( ! function_exists( 'qmo_ajax_sepet_olay' ) ) {
	function qmo_ajax_sepet_olay() {
		qmo_nonce_dogrula();

		$ham = isset( $_POST['olaylar'] ) ? wp_unslash( $_POST['olaylar'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- nonce yukarıda; JSON aşağıda çözülür.

		if ( is_string( $ham ) && '' !== $ham ) {
			$olaylar = json_decode( $ham, true );
		} else {
			$olaylar = array();
		}

		if ( is_array( $olaylar ) && function_exists( 'qmo_chatbot_oneri_durum_sessiz' ) ) {
			$oturum    = function_exists( 'qmo_oturum' ) ? qmo_oturum() : array();
			$oturum_id = function_exists( 'qmo_chatbot_ziyaretci_anahtar' )
				? qmo_chatbot_ziyaretci_anahtar( is_array( $oturum ) ? $oturum : array() )
				: '';

			if ( '' !== $oturum_id ) {
				foreach ( array_slice( $olaylar, 0, 40 ) as $o ) {
					if ( ! is_array( $o ) ) {
						continue;
					}
					$tip = isset( $o['tip'] ) ? sanitize_key( (string) $o['tip'] ) : '';
					$id  = isset( $o['item_id'] ) ? absint( $o['item_id'] ) : 0;
					if ( 'cart_add' === $tip && $id > 0 ) {
						qmo_chatbot_oneri_durum_sessiz( $oturum_id, $id, 'sepete' );
					}
				}
			}
		}

		if ( ! class_exists( 'QRMS_Analitik' ) ) {
			wp_send_json_success();
		}

		$oturum = qmo_oturum();

		if ( ! is_array( $oturum ) || empty( $oturum['masa'] ) ) {
			wp_send_json_success();
		}

		$masa = (string) $oturum['masa'];

		// Toplu istek zaten 3 sn debounce ile gelir; 2 sn'lik hız sınırı
		// aynı masa+IP'den flood'u keser. Takılırsa yazım ATLANIR (429 yok:
		// sepet kullanıcısı bir analitik reddini görmesin).
		if ( ! qmo_hiz_siniri( 'sepet_olay', $masa, 2 ) ) {
			wp_send_json_success();
		}

		if ( ! is_array( $olaylar ) ) {
			wp_send_json_success();
		}

		$olaylar = array_slice( $olaylar, 0, 40 );
		$izinli  = array( 'cart_add', 'cart_remove' );

		foreach ( $olaylar as $o ) {
			if ( ! is_array( $o ) ) {
				continue;
			}

			$tip = isset( $o['tip'] ) ? sanitize_key( (string) $o['tip'] ) : '';

			if ( ! in_array( $tip, $izinli, true ) ) {
				continue;
			}

			$id   = isset( $o['item_id'] ) ? absint( $o['item_id'] ) : 0;
			$alan = qmo_analitik_urun_alani( $id );

			if ( empty( $alan ) ) {
				continue;
			}

			qmo_analitik_yaz(
				array(
					'event_type'    => $tip,
					'item_id'       => $alan['item_id'],
					'item_name'     => $alan['item_name'],
					'category_name' => $alan['category_name'],
					'price'         => isset( $alan['price'] ) ? (float) $alan['price'] : 0.0,
					'masa_no'       => $masa,
				)
			);
		}

		wp_send_json_success();
	}
}
