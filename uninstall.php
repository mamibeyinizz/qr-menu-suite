<?php
/**
 * Eklenti kaldırılırken çalışır (WordPress "Sil" dediğinde).
 *
 * VARSAYILAN DAVRANIŞ: hiçbir müşteri verisi silinmez. Bir restoranın yorum
 * arşivini, masa kayıtlarını ve analitiğini eklenti kaldırıldı diye haber
 * vermeden yok etmek geri alınamaz bir kayıptır; ayrıca eklentiyi geçici
 * olarak kaldırıp yeniden kuran işletmeler vardır.
 *
 * Verilerin de silinmesi isteniyorsa işletme bunu açıkça seçer:
 *
 *   Ayarlar ekranındaki "eklenti silinince tüm verileri de sil" kutusu
 *   (`qrms_uninstall_veri_sil` option'ı), ya da wp-config.php içinde
 *   `define( 'QRMS_UNINSTALL_VERI_SIL', true );`
 *
 * Seçim yapılmışsa aşağıdaki tablolar, option'lar ve cron kayıtları temizlenir.
 * KVKK açısından kişisel veri barındıran tablolar bu listenin ilk sırasındadır.
 *
 * @package QR_Menu_Suite
 */

// Doğrudan çağrılamaz: yalnızca WordPress kaldırma akışı bu sabiti tanımlar.
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$qrms_veri_sil = ( defined( 'QRMS_UNINSTALL_VERI_SIL' ) && QRMS_UNINSTALL_VERI_SIL )
	|| get_option( 'qrms_uninstall_veri_sil' );

if ( ! $qrms_veri_sil ) {
	return;
}

global $wpdb;

/* -------------------------------------------------------------------------
 * 1) TABLOLAR
 * ---------------------------------------------------------------------- */

$qrms_tablolar = array(
	// Kişisel veri barındıranlar.
	'qrm_reviews',
	'qrm_review_media',
	'qrm_reward_codes',
	'qrm_cf_submissions',
	'qmo_chatbot_mesajlar',
	'qrms_analitik',
	// Yapılandırma / işletme verisi.
	'qrm_form_fields',
	'qrm_cf_forms',
	'qrm_cf_fields',
	'qrm_tables',
	'rma_price_campaign_snapshot',
	'rma_ceviri',
);

foreach ( $qrms_tablolar as $qrms_tablo ) {
	$qrms_ad = $wpdb->prefix . $qrms_tablo;

	// Tablo adı sabit listeden gelir, kullanıcı girdisi değildir.
	$wpdb->query( "DROP TABLE IF EXISTS `{$qrms_ad}`" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
}

/* -------------------------------------------------------------------------
 * 2) CRON KAYITLARI
 * ---------------------------------------------------------------------- */

$qrms_kancalar = array(
	'qrms_analitik_temizlik',
	'qrms_lisans_kontrol',
	'qrms_mm_recete_yenile',
	'qmo_uy_supurge',
	'qmo_uy_tekil_aktive',
	'qmo_chatbot_gecmis_temizle',
	'qrm_privacy_saklama_temizligi',
	'qrm_reward_expire_cron',
);

foreach ( $qrms_kancalar as $qrms_kanca ) {
	wp_clear_scheduled_hook( $qrms_kanca );
}

/* -------------------------------------------------------------------------
 * 3) OPTION'LAR
 *
 * Sabit adlar tek tek, modül ön ekli olanlar tek sorguyla silinir.
 * ---------------------------------------------------------------------- */

$qrms_optionlar = array(
	'qrms_active_modules',
	'qrms_license_key',
	'qrms_license_status',
	'qrms_server_url',
	'qrms_db_version',
	'qrms_uninstall_veri_sil',
	'qmo_firebase_sa',
	'qmo_branch_id',
	'qmo_ana_site',
	'qmo_korumali_sayfalar',
	'qmo_oturum_anahtar',
	'qmo_sir_autoload_gocu',
	'gemini_api_key',
	'qrm_settings',
	'qrm_db_version',
	'qrm_cf_db_version',
	'qrm_saklama_gun',
	'qrm_media_visibility_migrated',
	'qrm_reward_templates',
);

foreach ( $qrms_optionlar as $qrms_option ) {
	delete_option( $qrms_option );
}

$qrms_onekler = array( 'qrms\_%', 'qmo\_%', 'rma\_%', 'qrm\_%' );

foreach ( $qrms_onekler as $qrms_onek ) {
	$wpdb->query(
		$wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $qrms_onek )
	);
}

/* -------------------------------------------------------------------------
 * 4) POST META / TERİM VERİSİ
 *
 * Menü ürünleri (rma_menu_item) ve ekleri WordPress'in kendi kaldırma akışında
 * silinmez; işletme onları elle silmediyse burada da bırakılır. Yalnızca
 * eklentinin ürettiği meta anahtarları temizlenir.
 * ---------------------------------------------------------------------- */

foreach ( array( '\_rma\_%', 'rma\_%', '\_qrms\_%', '\_qmo\_%' ) as $qrms_meta_onek ) {
	$wpdb->query(
		$wpdb->prepare( "DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE %s", $qrms_meta_onek )
	);
}

// Nesne önbelleği eski option'ları tutmasın.
wp_cache_flush();
