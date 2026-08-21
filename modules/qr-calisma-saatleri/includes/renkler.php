<?php
/**
 * QR Çalışma Saatleri — renk ayarları.
 *
 * AYRI BİR OPTION'DA TUTULUR. Saatler `qrms_calisma_saatleri` içindedir ve
 * `qrms_cs_sanitize()` o diziyi gün anahtarlarına indirger — gün anahtarı
 * olmayan her şeyi düşürür. Renkleri aynı option'a koymak ya sessizce
 * silinmelerine ya da çalışan saat şemasını yeniden yazmaya mal olurdu;
 * ikisi de gereksiz risk.
 *
 * BOŞ DEĞER = "temadan devral". Renk seçici boş bırakıldığında CSS değişkeni
 * hiç basılmaz ve stylesheet'teki geri düşüş (fallback) devreye girer. Bu
 * sayede modül güncellendiğinde kimsenin sitesinin görünümü değişmez: hiçbir
 * renk seçilmemişken çıktı, renk ayarı eklenmeden önceki çıktının aynısıdır.
 *
 * @package QR_Menu_Suite
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'QRMS_CS_COLORS_OPTION' ) ) {
	define( 'QRMS_CS_COLORS_OPTION', 'qrms_calisma_saatleri_renkler' );
}

/**
 * Ayarlanabilir renkler.
 *
 * `fallback` yalnızca yönetim ekranındaki seçicinin "varsayılana dön"
 * düğmesini besler ve stylesheet'teki geri düşüşle AYNI değeri gösterir;
 * option'a yazılmaz.
 *
 * @return array<string,array{label:string,desc:string,fallback:string,var:string}>
 */
function qrms_cs_color_fields() {
	return array(
		'today'   => array(
			'label'    => __( 'Bugünün vurgusu', 'qrms' ),
			'desc'     => __( 'Listede içinde bulunulan günün adı bu renkte yazılır.', 'qrms' ),
			'fallback' => '#c9a84c',
			'var'      => '--qrms-cs-today',
		),
		'today_bg' => array(
			'label'    => __( 'Bugünün satır zemini', 'qrms' ),
			'desc'     => __( 'Bugünün satırına hafif bir zemin verir. Boş bırakılırsa zemin olmaz.', 'qrms' ),
			'fallback' => 'transparent',
			'var'      => '--qrms-cs-today-bg',
		),
		'day'     => array(
			'label'    => __( 'Gün adı', 'qrms' ),
			'desc'     => __( 'Boş bırakılırsa temanın yazı rengi kullanılır.', 'qrms' ),
			'fallback' => '',
			'var'      => '--qrms-cs-day',
		),
		'hours'   => array(
			'label'    => __( 'Saat metni', 'qrms' ),
			'desc'     => __( 'Boş bırakılırsa temanın yazı rengi kullanılır.', 'qrms' ),
			'fallback' => '',
			'var'      => '--qrms-cs-hours',
		),
		'closed'  => array(
			'label'    => __( 'Kapalı gün', 'qrms' ),
			'desc'     => __( '"Kapalı" yazan satırların rengi.', 'qrms' ),
			'fallback' => '',
			'var'      => '--qrms-cs-closed',
		),
		'divider' => array(
			'label'    => __( 'Satır ayracı', 'qrms' ),
			'desc'     => __( 'Günleri ayıran ince çizgi.', 'qrms' ),
			'fallback' => 'rgba(0, 0, 0, 0.08)',
			'var'      => '--qrms-cs-divider',
		),
	);
}

/**
 * Ham girdiyi geçerli hex renklere indirger.
 *
 * Geçersiz değer boşa düşer — yani "temadan devral"a. Böylece bozuk bir
 * girdi görünümü kırmak yerine ayarı yok saymış olur.
 *
 * @param mixed $input Ham dizi.
 * @return array<string,string>
 */
function qrms_cs_sanitize_colors( $input ) {
	$input = is_array( $input ) ? $input : array();
	$out   = array();

	foreach ( array_keys( qrms_cs_color_fields() ) as $key ) {
		$raw = isset( $input[ $key ] ) ? (string) $input[ $key ] : '';
		$hex = sanitize_hex_color( $raw );

		$out[ $key ] = $hex ? $hex : '';
	}

	return $out;
}

/**
 * Kayıtlı renkler (eksik anahtarlar boş).
 *
 * @return array<string,string>
 */
function qrms_cs_get_colors() {
	/**
	 * Çalışma saatleri renklerini filtreler.
	 *
	 * @param array $colors Sanitize edilmiş renk dizisi.
	 */
	return apply_filters( 'qrms_cs_colors', qrms_cs_sanitize_colors( get_option( QRMS_CS_COLORS_OPTION, array() ) ) );
}

/**
 * Seçilmiş renklerden CSS özel değişkeni bildirimleri.
 *
 * Seçilmemiş renk hiç basılmaz; stylesheet'teki geri düşüş devrede kalır.
 *
 * @param array|null $colors Renk dizisi; null ise kayıtlı olanlar.
 * @return string "--ad: değer; …" biçiminde, boşsa boş string.
 */
function qrms_cs_color_declarations( $colors = null ) {
	if ( ! is_array( $colors ) ) {
		$colors = qrms_cs_get_colors();
	}

	$fields = qrms_cs_color_fields();
	$out    = '';

	foreach ( $fields as $key => $field ) {
		if ( empty( $colors[ $key ] ) ) {
			continue;
		}

		$out .= $field['var'] . ': ' . $colors[ $key ] . '; ';
	}

	return trim( $out );
}

/**
 * Kısa kodun kapsayıcısına verilecek satır içi stil.
 *
 * Kurallar stylesheet'te kalır; buraya yalnızca siteye özel DEĞERLER iner.
 *
 * @return string style="..." için hazır dize (renk seçilmemişse boş).
 */
function qrms_cs_inline_style_attr() {
	$decl = qrms_cs_color_declarations();

	return '' === $decl ? '' : ' style="' . esc_attr( $decl ) . '"';
}
