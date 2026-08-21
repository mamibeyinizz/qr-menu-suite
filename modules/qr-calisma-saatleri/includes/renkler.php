<?php
/**
 * QR Çalışma Saatleri — görünüm ayarları (renkler + yazı tipi).
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
 * Aynı kural yazı tipi için de geçerlidir — seçilmemişse tema fontu kalır.
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
		'bg'       => array(
			'label'    => __( 'Arka plan', 'qrms' ),
			'desc'     => __( 'Listenin zemini. Boş bırakılırsa zemin olmaz, sayfanın rengi görünür.', 'qrms' ),
			'fallback' => 'transparent',
			'var'      => '--qrms-cs-bg',
		),
		'border'   => array(
			'label'    => __( 'Kenar rengi', 'qrms' ),
			'desc'     => __( 'Listenin çevresindeki çerçeve. Boş bırakılırsa çerçeve hiç çizilmez.', 'qrms' ),
			'fallback' => '',
			'var'      => '--qrms-cs-border',
		),
		'text'     => array(
			'label'    => __( 'Yazı rengi', 'qrms' ),
			'desc'     => __( 'Listenin genel yazı rengi. Gün adı ve saat için ayrı renk seçmezseniz onlar da bunu kullanır.', 'qrms' ),
			'fallback' => '',
			'var'      => '--qrms-cs-text',
		),
		'today'    => array(
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
		'day'      => array(
			'label'    => __( 'Gün adı', 'qrms' ),
			'desc'     => __( 'Boş bırakılırsa yazı rengi (yoksa temanınki) kullanılır.', 'qrms' ),
			'fallback' => '',
			'var'      => '--qrms-cs-day',
		),
		'hours'    => array(
			'label'    => __( 'Saat metni', 'qrms' ),
			'desc'     => __( 'Boş bırakılırsa yazı rengi (yoksa temanınki) kullanılır.', 'qrms' ),
			'fallback' => '',
			'var'      => '--qrms-cs-hours',
		),
		'closed'   => array(
			'label'    => __( 'Kapalı gün', 'qrms' ),
			'desc'     => __( '"Kapalı" yazan satırların rengi.', 'qrms' ),
			'fallback' => '',
			'var'      => '--qrms-cs-closed',
		),
		'divider'  => array(
			'label'    => __( 'Satır ayracı', 'qrms' ),
			'desc'     => __( 'Günleri ayıran ince çizgi.', 'qrms' ),
			'fallback' => 'rgba(0, 0, 0, 0.08)',
			'var'      => '--qrms-cs-divider',
		),
	);
}

/**
 * Yazı tipinin CSS değişkeni.
 */
if ( ! defined( 'QRMS_CS_FONT_VAR' ) ) {
	define( 'QRMS_CS_FONT_VAR', '--qrms-cs-font' );
}

/**
 * Seçilebilir yazı tipleri.
 *
 * Liste, Restoran Menü'nün Görünüm sayfasındaki seçicinin listesiyle BİREBİR
 * aynıdır (bkz. RMA_Admin_Pages_Trait::get_font_options): restoran sahibi iki
 * ekranda farklı fontlar görüp hangisini seçtiğini karıştırmasın. Liste orada
 * private bir metottadır ve iki modül birbirinden bağımsız lisanslanır, bu
 * yüzden paylaşılamaz — kopya bilinçlidir.
 *
 * Boş anahtar "temadan devral" seçeneğidir ve varsayılandır.
 *
 * @return string[]
 */
function qrms_cs_font_options() {
	return array(
		'Plus Jakarta Sans',
		'Nunito',
		'Inter',
		'Nunito Sans',
		'Cormorant Garamond',
		'DM Sans',
		'Playfair Display',
		'Lato',
		'Montserrat',
		'Raleway',
		'Open Sans',
		'Roboto',
		'Merriweather',
		'Poppins',
		'Georgia',
		'serif',
		'sans-serif',
	);
}

/**
 * Google Fonts'tan yüklenmesi gereken aileler.
 *
 * Listede olmayan seçenekler (Georgia, serif, sans-serif) sistem fontudur;
 * dış istek yapılmaz. Aynı eşleşme restoran-menu modülünde de vardır.
 *
 * @return array<string,string> Font adı => css2 `family=` parametresi.
 */
function qrms_cs_google_font_map() {
	return array(
		'Plus Jakarta Sans'  => 'Plus+Jakarta+Sans:wght@400;600;700',
		'Nunito'             => 'Nunito:wght@400;600;700',
		'Nunito Sans'        => 'Nunito+Sans:wght@400;600;700',
		'Inter'              => 'Inter:wght@400;600;700',
		'Cormorant Garamond' => 'Cormorant+Garamond:wght@400;600;700',
		'DM Sans'            => 'DM+Sans:wght@400;500;600',
		'Playfair Display'   => 'Playfair+Display:wght@400;600;700',
		'Lato'               => 'Lato:wght@400;700',
		'Montserrat'         => 'Montserrat:wght@400;600;700',
		'Raleway'            => 'Raleway:wght@400;600;700',
		'Open Sans'          => 'Open+Sans:wght@400;600;700',
		'Roboto'             => 'Roboto:wght@400;500;700',
		'Merriweather'       => 'Merriweather:wght@400;700',
		'Poppins'            => 'Poppins:wght@400;500;600;700',
	);
}

/**
 * Bir font seçiminin CSS `font-family` değeri.
 *
 * Jenerik aileler (serif/sans-serif) tırnaklanmaz; adı olanlar tırnaklanır ve
 * arkalarına sistem geri düşüşü eklenir — font yüklenmezse yazı kaybolmaz.
 *
 * @param string $font Font adı.
 * @return string Boş seçimde boş string.
 */
function qrms_cs_font_family( $font ) {
	$font = (string) $font;

	if ( '' === $font ) {
		return '';
	}

	if ( 'serif' === $font || 'sans-serif' === $font ) {
		return $font;
	}

	return "'" . $font . "', system-ui, sans-serif";
}

/**
 * Google Fonts stil adresi (gerekmiyorsa boş).
 *
 * @param string $font Font adı.
 * @return string
 */
function qrms_cs_google_font_url( $font ) {
	$map = qrms_cs_google_font_map();

	if ( '' === (string) $font || ! isset( $map[ $font ] ) ) {
		return '';
	}

	return 'https://fonts.googleapis.com/css2?family=' . $map[ $font ] . '&display=swap';
}

/**
 * Ham girdiyi geçerli hex renklere ve bilinen bir yazı tipine indirger.
 *
 * Geçersiz değer boşa düşer — yani "temadan devral"a. Böylece bozuk bir
 * girdi görünümü kırmak yerine ayarı yok saymış olur. Font beyaz listeyle
 * doğrulanır: değer doğrudan CSS'e indiği için serbest metin kabul edilmez.
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

	$font = isset( $input['font'] ) ? sanitize_text_field( (string) $input['font'] ) : '';

	$out['font'] = in_array( $font, qrms_cs_font_options(), true ) ? $font : '';

	return $out;
}

/**
 * Kayıtlı görünüm ayarları (eksik anahtarlar boş).
 *
 * @return array<string,string>
 */
function qrms_cs_get_colors() {
	/**
	 * Çalışma saatleri renklerini filtreler.
	 *
	 * @param array $colors Sanitize edilmiş renk dizisi (+ 'font' anahtarı).
	 */
	return apply_filters( 'qrms_cs_colors', qrms_cs_sanitize_colors( get_option( QRMS_CS_COLORS_OPTION, array() ) ) );
}

/**
 * Kayıtlı yazı tipi (seçilmemişse boş).
 *
 * @return string
 */
function qrms_cs_get_font() {
	$colors = qrms_cs_get_colors();

	return isset( $colors['font'] ) ? $colors['font'] : '';
}

/**
 * Seçilmiş renklerden ve fonttan CSS özel değişkeni bildirimleri.
 *
 * Seçilmemiş değer hiç basılmaz; stylesheet'teki geri düşüş devrede kalır.
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

	$family = qrms_cs_font_family( isset( $colors['font'] ) ? $colors['font'] : '' );

	if ( '' !== $family ) {
		$out .= QRMS_CS_FONT_VAR . ': ' . $family . '; ';
	}

	$out .= qrms_cs_box_declarations( $colors );

	return trim( $out );
}

/**
 * Kutu ölçüleri — YALNIZCA görünür bir kutu istendiğinde.
 *
 * Zemin ya da çerçeve seçilmemişken liste bugünkü gibi çıplak durur: çerçeve
 * kalınlığı 0, iç boşluk 0. "1px solid transparent" bile satırları kaydırır
 * ve modül güncellemesi kimsenin sitesini oynatmamalıdır — bu yüzden ölçüler
 * de renklerle birlikte basılır.
 *
 * @param array $colors Sanitize edilmiş ayarlar.
 * @return string
 */
function qrms_cs_box_declarations( array $colors ) {
	$border = ! empty( $colors['border'] );
	$bg     = ! empty( $colors['bg'] );

	if ( ! $border && ! $bg ) {
		return '';
	}

	$out = '';

	if ( $border ) {
		$out .= '--qrms-cs-border-width: 1px; ';
	}

	// Zemin ya da çerçeve varken yazı kenara yapışmasın.
	$out .= '--qrms-cs-pad: 12px 16px; --qrms-cs-radius: 10px; ';

	return $out;
}

/**
 * Kısa kodun kapsayıcısına verilecek satır içi stil.
 *
 * Kurallar stylesheet'te kalır; buraya yalnızca siteye özel DEĞERLER iner.
 *
 * @return string style="..." için hazır dize (hiçbir ayar seçilmemişse boş).
 */
function qrms_cs_inline_style_attr() {
	$decl = qrms_cs_color_declarations();

	return '' === $decl ? '' : ' style="' . esc_attr( $decl ) . '"';
}
