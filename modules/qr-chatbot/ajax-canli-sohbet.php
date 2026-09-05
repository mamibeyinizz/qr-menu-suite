<?php
/**
 * AJAX: canlı sohbet devralma.
 *
 * Personel tarafı yönetim uçları (liste/yazışma/mesaj gönder/kapat) ve
 * müşteri tarafı personel mesajı yoklama ucu.
 *
 * GÜVENLİK:
 *   - Yönetim uçları current_user_can( 'manage_options' ) + nonce ister
 *     (diğer ajax-admin.php uçlarıyla aynı desen).
 *   - Müşteri tarafı yoklama ucu qmo_oturum_zorla() ile aynı masa oturumu
 *     doğrulamasından geçer; oturum_id İSTEMCİDEN ALINMAZ, sunucuda
 *     doğrulanmış oturumdan türetilir — aksi hâlde bir ziyaretçi başka bir
 *     masanın personel mesajlarını okuyabilirdi.
 *
 * @package QR_Menu_Suite
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'wp_ajax_qmo_chatbot_canli_liste', 'qmo_chatbot_ajax_canli_liste' );
add_action( 'wp_ajax_qmo_chatbot_canli_yazisma', 'qmo_chatbot_ajax_canli_yazisma' );
add_action( 'wp_ajax_qmo_chatbot_canli_mesaj_gonder', 'qmo_chatbot_ajax_canli_mesaj_gonder' );
add_action( 'wp_ajax_qmo_chatbot_canli_kapat', 'qmo_chatbot_ajax_canli_kapat' );

add_action( 'wp_ajax_qmo_chatbot_personel_yokla', 'qmo_ajax_personel_yokla' );
add_action( 'wp_ajax_nopriv_qmo_chatbot_personel_yokla', 'qmo_ajax_personel_yokla' );

/**
 * Kapatılmamış (eskalasyon almış) canlı sohbet listesi.
 *
 * @return void
 */
if ( ! function_exists( 'qmo_chatbot_ajax_canli_liste' ) ) {
	function qmo_chatbot_ajax_canli_liste() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'mesaj' => 'Yetkiniz yok.' ), 403 );
		}
		check_ajax_referer( 'qmo_chatbot_canli', 'nonce' );

		$cikti = array();
		foreach ( QMO_Chatbot_DB::canli_liste() as $satir ) {
			$cikti[] = array(
				'oturum_id'         => $satir->oturum_id,
				'masa_no'           => $satir->masa_no,
				'son_musteri_mesaj' => $satir->son_musteri_mesaj,
				'durum'             => $satir->durum,
				'son_aktivite'      => $satir->son_aktivite,
			);
		}

		wp_send_json_success( array( 'satirlar' => $cikti ) );
	}
}

/**
 * Bir oturumun tam yazışması — müşteri turları + personel mesajları,
 * tarihe göre birleştirilmiş.
 *
 * @return void
 */
if ( ! function_exists( 'qmo_chatbot_ajax_canli_yazisma' ) ) {
	function qmo_chatbot_ajax_canli_yazisma() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'mesaj' => 'Yetkiniz yok.' ), 403 );
		}
		check_ajax_referer( 'qmo_chatbot_canli', 'nonce' );

		$oturum = isset( $_POST['oturum_id'] ) ? sanitize_text_field( wp_unslash( $_POST['oturum_id'] ) ) : '';
		if ( '' === $oturum ) {
			wp_send_json_error( array( 'mesaj' => 'Oturum bulunamadı.' ) );
		}

		$olaylar = array();
		foreach ( QMO_Chatbot_DB::oturum_yazismasi( $oturum ) as $satir ) {
			$olaylar[] = array(
				'tur'        => 'musteri',
				'soru'       => $satir->soru,
				'cevap'      => $satir->cevap,
				'created_at' => $satir->created_at,
			);
		}
		foreach ( QMO_Chatbot_DB::personel_mesajlari_al( $oturum ) as $satir ) {
			$olaylar[] = array(
				'tur'        => 'personel',
				'mesaj'      => $satir->mesaj,
				'created_at' => $satir->created_at,
			);
		}

		usort(
			$olaylar,
			function ( $a, $b ) {
				return strcmp( $a['created_at'], $b['created_at'] );
			}
		);

		wp_send_json_success( array( 'olaylar' => $olaylar ) );
	}
}

/**
 * Personel mesajı gönderir.
 *
 * @return void
 */
if ( ! function_exists( 'qmo_chatbot_ajax_canli_mesaj_gonder' ) ) {
	function qmo_chatbot_ajax_canli_mesaj_gonder() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'mesaj' => 'Yetkiniz yok.' ), 403 );
		}
		check_ajax_referer( 'qmo_chatbot_canli', 'nonce' );

		$oturum = isset( $_POST['oturum_id'] ) ? sanitize_text_field( wp_unslash( $_POST['oturum_id'] ) ) : '';
		$mesaj  = isset( $_POST['mesaj'] ) ? sanitize_textarea_field( wp_unslash( $_POST['mesaj'] ) ) : '';
		$mesaj  = mb_substr( $mesaj, 0, 500 );

		if ( '' === $oturum || '' === $mesaj ) {
			wp_send_json_error( array( 'mesaj' => 'Oturum veya mesaj boş.' ) );
		}

		$id = QMO_Chatbot_DB::personel_mesaj_yaz( $oturum, $mesaj );
		if ( $id < 1 ) {
			wp_send_json_error( array( 'mesaj' => 'Mesaj gönderilemedi.' ) );
		}

		wp_send_json_success(
			array(
				'mesaj' => 'Gönderildi.',
				'id'    => $id,
			)
		);
	}
}

/**
 * Canlı sohbeti kapatır (devralma bitti).
 *
 * @return void
 */
if ( ! function_exists( 'qmo_chatbot_ajax_canli_kapat' ) ) {
	function qmo_chatbot_ajax_canli_kapat() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'mesaj' => 'Yetkiniz yok.' ), 403 );
		}
		check_ajax_referer( 'qmo_chatbot_canli', 'nonce' );

		$oturum = isset( $_POST['oturum_id'] ) ? sanitize_text_field( wp_unslash( $_POST['oturum_id'] ) ) : '';
		if ( '' === $oturum ) {
			wp_send_json_error( array( 'mesaj' => 'Oturum bulunamadı.' ) );
		}

		QMO_Chatbot_DB::canli_kapat( $oturum );
		wp_send_json_success( array( 'mesaj' => 'Kapatıldı.' ) );
	}
}

/**
 * Müşteri tarafı: bu oturuma personelden yeni mesaj geldi mi?
 *
 * oturum_id istemciden ALINMAZ — qmo_oturum_zorla() ile doğrulanan masa
 * oturumundan sunucuda türetilir; aksi hâlde bir ziyaretçi oturum_id
 * tahmin ederek başka bir masanın personel mesajlarını okuyabilirdi.
 *
 * @return void
 */
if ( ! function_exists( 'qmo_ajax_personel_yokla' ) ) {
	function qmo_ajax_personel_yokla() {
		$sess = qmo_oturum_zorla();

		$oturum_id = function_exists( 'qmo_chatbot_ziyaretci_anahtar' )
			? qmo_chatbot_ziyaretci_anahtar( $sess )
			: '';
		if ( '' === $oturum_id ) {
			wp_send_json_success( array( 'mesajlar' => array() ) );
		}

		$sonrasi  = isset( $_POST['sonrasi_id'] ) ? absint( $_POST['sonrasi_id'] ) : 0;
		$satirlar = QMO_Chatbot_DB::personel_mesajlari_al( $oturum_id, $sonrasi );

		$cikti = array();
		foreach ( $satirlar as $satir ) {
			$cikti[] = array(
				'id'    => (int) $satir->id,
				'mesaj' => $satir->mesaj,
			);
		}

		wp_send_json_success( array( 'mesajlar' => $cikti ) );
	}
}
