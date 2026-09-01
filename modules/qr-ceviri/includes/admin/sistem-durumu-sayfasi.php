<?php
/**
 * Alt sayfa: Sistem Durumu (qrms-cv-durum).
 *
 * Mevcut tablo + P1 grupları (Yönetici ayarları, Form alanları),
 * eskimiş / yetim yönetimi. Hücreler "çeviri yok" ile "kaynak yok"u ayırır.
 *
 * @package QR_Menu_Suite
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'qrms_module_qr_ceviri_sayfa_durum' ) ) {

	/**
	 * Sistem Durumu ekranı.
	 *
	 * @return void
	 */
	function qrms_module_qr_ceviri_sayfa_durum() {
		rma_ceviri_import_bildirimleri();

		qrms_module_qr_ceviri_sayfa_ac( 'qrms-cv-durum' );
		qrms_module_qr_ceviri_baslik( 'dashicons-chart-bar', 'Sistem Durumu', 'h1' );
		rma_ceviri_durum_paneli();
		qrms_module_qr_ceviri_sayfa_kapat();
	}
}
