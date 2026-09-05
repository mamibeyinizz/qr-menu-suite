<?php
/**
 * QR Chatbot — ayar okuma, renk türetme ve görünürlük kararları.
 *
 * Eski gemini_* option adları korunur. Yeni anahtarlar qmo_chatbot_* önekini
 * kullanır; kayıtlı sitelerde veri kaybı olmasın diye hiçbiri silinmez.
 *
 * @package QR_Menu_Suite
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Asistanın sitede görünüp görünmeyeceği (hub anahtarı). */
if ( ! defined( 'QMO_CHATBOT_OPT_AKTIF' ) ) {
	define( 'QMO_CHATBOT_OPT_AKTIF', 'qmo_chatbot_aktif' );
}

/**
 * Asistan açık mı? Kayıt yoksa açık kabul edilir (eski siteler bozulmasın).
 *
 * @return bool
 */
function qmo_chatbot_aktif_mi() {
	return 'no' !== get_option( QMO_CHATBOT_OPT_AKTIF, 'yes' );
}

/**
 * Yeni ayarların varsayılanları.
 *
 * @return array<string,mixed>
 */
function qmo_chatbot_yeni_varsayilanlar() {
	return array(
		QMO_CHATBOT_OPT_AKTIF              => 'yes',
		'qmo_chatbot_icon_preset'          => 'bubble',
		'qmo_chatbot_icon_color'           => '#ffffff',
		'qmo_chatbot_icon_bg_color'        => '#8a2be2',
		'qmo_chatbot_icon_size_preset'     => 'medium',
		'qmo_chatbot_position'             => 'right',
		'qmo_chatbot_offset'               => 'mid',
		'qmo_chatbot_attention'            => 'none',
		'qmo_chatbot_badge'                => 'yes',
		'qmo_chatbot_advanced_colors'      => 'no',
		'qmo_chatbot_color_overrides'      => array(),
		'qmo_chatbot_radius_preset'        => 'soft',
		'qmo_chatbot_window_width'         => 'normal',
		'qmo_chatbot_welcome_screen'       => 'no',
		'qmo_chatbot_welcome_intro'        => 'Menü, öneriler ve sipariş için buradayım.',
		'qmo_chatbot_welcome_btn'          => 'Sohbete Başla',
		'qmo_chatbot_teaser'               => 'yes',
		'qmo_chatbot_teaser_text'          => 'Bir şey sormak ister misiniz?',
		'qmo_chatbot_teaser_delay'         => 4,
		'qmo_chatbot_quick_max'            => 5,
		'qmo_chatbot_auto_inject'          => 'yes',
		'qmo_chatbot_audience'             => 'session',
		'qmo_chatbot_devices'              => 'both',
		'qmo_chatbot_hide_after_hours'     => 'no',
		'qmo_chatbot_closed_behavior'      => 'hide',
		'qmo_chatbot_closed_message'       => 'Şu an kapalıyız, yakında görüşmek üzere.',
		'qmo_chatbot_daily_limit'          => 0,
		'qmo_chatbot_daily_limit_msg'      => 'Bugünkü soru hakkımız doldu. Lütfen biraz sonra tekrar deneyin.',
		'qmo_chatbot_rate_per_min'         => 8,
		'qmo_chatbot_banned_words'         => '',
		'qmo_chatbot_banned_msg'           => 'Bu konuda yardımcı olamam. Menü veya sipariş hakkında sorabilirsiniz.',
		'qmo_chatbot_retention_days'       => 30,
		'qmo_chatbot_eskalasyon'           => 'yes',
		'qmo_chatbot_eskalasyon_msg'       => 'Bu konuda emin olamadım. Garson çağırmamı ister misiniz?',
	);
}

/**
 * Tek bir yeni option'ı varsayılanıyla okur.
 *
 * @param string $anahtar Option adı.
 * @return mixed
 */
function qmo_chatbot_ayar( $anahtar ) {
	$d = qmo_chatbot_yeni_varsayilanlar();
	if ( ! isset( $d[ $anahtar ] ) ) {
		return get_option( $anahtar, '' );
	}
	return get_option( $anahtar, $d[ $anahtar ] );
}

/**
 * İkon boyutu hazır seçenek → piksel (gemini_icon_size ile senkron).
 *
 * @return array<string,int>
 */
function qmo_chatbot_boyut_haritasi() {
	return array(
		'small'  => 36,
		'medium' => 48,
		'large'  => 64,
	);
}

/**
 * Köşe yumuşaklığı hazır seçenek → piksel (gemini_border_radius ile senkron).
 *
 * @return array<string,int>
 */
function qmo_chatbot_kose_haritasi() {
	return array(
		'sharp' => 6,
		'soft'  => 16,
		'round' => 28,
	);
}

/**
 * Yerden yükseklik → alt boşluk (Garson/Hesap çubuğunun biraz üstü).
 *
 * @return array<string,int>
 */
function qmo_chatbot_yukseklik_haritasi() {
	return array(
		'low' => 24,
		'mid' => 108,
		'high' => 168,
	);
}

/**
 * Pencere genişliği → piksel.
 *
 * @return array<string,int>
 */
function qmo_chatbot_genislik_haritasi() {
	return array(
		'narrow' => 340,
		'normal' => 380,
		'wide'   => 440,
	);
}

/**
 * Hazır ikon galerisi — satır içi SVG.
 *
 * @return array<string,array{label:string,svg:string}>
 */
function qmo_chatbot_hazir_ikonlar() {
	$ortak = 'viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"';

	return array(
		'bubble'   => array(
			'label' => __( 'Sohbet balonu', 'qrms' ),
			'svg'   => '<svg ' . $ortak . '><path d="M7.5 4.5h9A2.5 2.5 0 0 1 19 7v7a2.5 2.5 0 0 1-2.5 2.5H11l-4.5 3v-3H7.5A2.5 2.5 0 0 1 5 14V7A2.5 2.5 0 0 1 7.5 4.5z"/><path d="M8.5 9h7M8.5 12.5h4.5"/></svg>',
		),
		'waiter'   => array(
			'label' => __( 'Garson', 'qrms' ),
			'svg'   => '<svg ' . $ortak . '><circle cx="12" cy="5.5" r="2.2"/><path d="M8 20.5v-1.4c0-2.2 1.8-4 4-4s4 1.8 4 4v1.4"/><path d="M4.5 11h15"/><path d="M6 11v2.1A1.6 1.6 0 0 0 7.6 14.7h8.8A1.6 1.6 0 0 0 18 13.1V11"/></svg>',
		),
		'chef'     => array(
			'label' => __( 'Şef şapkası', 'qrms' ),
			'svg'   => '<svg ' . $ortak . '><path d="M7.2 11c-1.3 0-2.4-1.1-2.4-2.5 0-1.3 1-2.4 2.3-2.5.5-1.6 2-2.7 3.7-2.7 1.4 0 2.6.7 3.3 1.8.5-.3 1.1-.5 1.8-.5 1.5 0 2.7 1.2 2.7 2.8 0 1.4-1 2.5-2.4 2.8"/><path d="M7 11h10v3.2H7z"/><path d="M8 14.2h8V19H8z"/></svg>',
		),
		'star'     => array(
			'label' => __( 'Yıldız', 'qrms' ),
			'svg'   => '<svg ' . $ortak . '><path d="M12 3.6 14.1 8l4.9.7-3.5 3.5.8 4.9L12 15.4 7.7 17.1l.8-4.9L5 8.7 9.9 8z"/></svg>',
		),
		'coffee'   => array(
			'label' => __( 'Kahve fincanı', 'qrms' ),
			'svg'   => '<svg ' . $ortak . '><path d="M5 8.5h11v5.2A3.8 3.8 0 0 1 12.2 17.5H8.8A3.8 3.8 0 0 1 5 13.7z"/><path d="M16 10h1.7A2.1 2.1 0 0 1 19.8 12.1 2.1 2.1 0 0 1 17.7 14.2H16"/><path d="M7.5 20h9"/><path d="M8.5 4.2c.3.5.3 1.1 0 1.6M11.5 3.8c.3.5.3 1.1 0 1.6M14.5 4.2c.3.5.3 1.1 0 1.6"/></svg>',
		),
		'fork'     => array(
			'label' => __( 'Çatal-bıçak', 'qrms' ),
			'svg'   => '<svg ' . $ortak . '><path d="M6 3.5v6M8.5 3.5v6M11 3.5v6M8.5 9.5v11.5M6 9.5h5"/><path d="M16 3.5c1.4 0 2.3 1.2 2.3 2.8V11H16V3.5z"/><path d="M16 11v10"/></svg>',
		),
		'question' => array(
			'label' => __( 'Soru işareti', 'qrms' ),
			'svg'   => '<svg ' . $ortak . '><circle cx="12" cy="12" r="8.25"/><path d="M9.6 9.5a2.5 2.5 0 1 1 3.6 2.2c-.7.5-1.2 1-1.2 1.9V14.4"/><circle cx="12" cy="17.1" r="0.85" fill="currentColor" stroke="none"/></svg>',
		),
		'bell'     => array(
			'label' => __( 'Zil', 'qrms' ),
			'svg'   => '<svg ' . $ortak . '><path d="M6.5 17h11"/><path d="M8 17V10a4 4 0 1 1 8 0v7"/><path d="M10.4 17a1.6 1.6 0 0 0 3.2 0"/><path d="M12 4.2V6"/></svg>',
		),
	);
}

/**
 * Seçili hazır ikonun SVG'si (yoksa varsayılan kıvılcım).
 *
 * @param string $slug Galeri anahtarı.
 * @return string
 */
function qmo_chatbot_ikon_svg( $slug ) {
	$liste = qmo_chatbot_hazir_ikonlar();
	if ( isset( $liste[ $slug ] ) ) {
		return $liste[ $slug ]['svg'];
	}
	return function_exists( 'qmo_varsayilan_ikon' ) ? qmo_varsayilan_ikon() : '';
}

/**
 * Satış odaklı varsayılan hazır sorular.
 *
 * @return array<int,array{id:string,label:string,question:string,enabled:int}>
 */
function qmo_chatbot_varsayilan_sorular() {
	$satirlar = array(
		array( 'Bugünün şef önerisi nedir?', 'Bugünün şef önerisi nedir?' ),
		array( 'En çok tercih edilen tatlı hangisi?', 'En çok tercih edilen tatlı hangisi?' ),
		array( 'Yanında ne iyi gider?', 'Yanında ne iyi gider?' ),
		array( 'Kampanyalı ürünler neler?', 'Kampanyalı ürünler neler?' ),
		array( 'Menüde ne var?', 'Menüde ne var?' ),
		array( 'Garson çağır', 'Garson çağırır mısınız?' ),
		array( 'Hesap iste', 'Hesap isterim.' ),
	);

	$liste = array();
	foreach ( $satirlar as $i => $satir ) {
		$liste[] = array(
			'id'       => 'd' . ( $i + 1 ),
			'label'    => $satir[0],
			'question' => $satir[1],
			'enabled'  => 1,
		);
	}

	return $liste;
}

/**
 * Kayıtlı hazır soruları okur; yoksa varsayılanları döner.
 *
 * @return array<int,array{id:string,label:string,question:string,enabled:int}>
 */
function qmo_chatbot_sorulari_oku() {
	$ham = get_option( 'qmo_chatbot_quick_replies', null );
	if ( ! is_array( $ham ) || empty( $ham ) ) {
		return qmo_chatbot_varsayilan_sorular();
	}

	$temiz = array();
	foreach ( $ham as $satir ) {
		if ( ! is_array( $satir ) ) {
			continue;
		}
		$etiket = isset( $satir['label'] ) ? sanitize_text_field( $satir['label'] ) : '';
		$soru   = isset( $satir['question'] ) ? sanitize_text_field( $satir['question'] ) : '';
		if ( '' === $etiket && '' === $soru ) {
			continue;
		}
		$temiz[] = array(
			'id'       => isset( $satir['id'] ) ? sanitize_key( $satir['id'] ) : uniqid( 'q', false ),
			'label'    => '' !== $etiket ? $etiket : $soru,
			'question' => '' !== $soru ? $soru : $etiket,
			'enabled'  => empty( $satir['enabled'] ) ? 0 : 1,
		);
	}

	return $temiz;
}

/**
 * Gösterilecek (açık) hazır sorular, azami adetle.
 *
 * @return array<int,array{id:string,label:string,question:string,enabled:int}>
 */
function qmo_chatbot_sorulari_aktif() {
	$azami = max( 1, min( 12, (int) qmo_chatbot_ayar( 'qmo_chatbot_quick_max' ) ) );
	$acik  = array();

	foreach ( qmo_chatbot_sorulari_oku() as $satir ) {
		if ( empty( $satir['enabled'] ) ) {
			continue;
		}
		$acik[] = $satir;
		if ( count( $acik ) >= $azami ) {
			break;
		}
	}

	return $acik;
}

/**
 * Hex rengi parçala.
 *
 * @param string $hex Renk.
 * @return array{0:int,1:int,2:int}
 */
function qmo_chatbot_hex_parcala( $hex ) {
	$temiz = sanitize_hex_color( $hex );
	if ( ! $temiz ) {
		$temiz = '#000000';
	}
	$temiz = ltrim( $temiz, '#' );
	if ( 3 === strlen( $temiz ) ) {
		$temiz = $temiz[0] . $temiz[0] . $temiz[1] . $temiz[1] . $temiz[2] . $temiz[2];
	}

	return array(
		hexdec( substr( $temiz, 0, 2 ) ),
		hexdec( substr( $temiz, 2, 2 ) ),
		hexdec( substr( $temiz, 4, 2 ) ),
	);
}

/**
 * RGB → hex.
 *
 * @param int $r Kırmızı.
 * @param int $g Yeşil.
 * @param int $b Mavi.
 * @return string
 */
function qmo_chatbot_rgb_hex( $r, $g, $b ) {
	return sprintf( '#%02x%02x%02x', max( 0, min( 255, $r ) ), max( 0, min( 255, $g ) ), max( 0, min( 255, $b ) ) );
}

/**
 * Göreli parlaklık (0–1).
 *
 * @param string $hex Renk.
 * @return float
 */
function qmo_chatbot_parlaklik( $hex ) {
	list( $r, $g, $b ) = qmo_chatbot_hex_parcala( $hex );
	return ( 0.2126 * $r + 0.7152 * $g + 0.0722 * $b ) / 255;
}

/**
 * İki rengi karıştır.
 *
 * @param string $hex   Kaynak.
 * @param string $hedef Hedef.
 * @param float  $oran  0 = kaynak, 1 = hedef.
 * @return string
 */
function qmo_chatbot_karistir( $hex, $hedef, $oran ) {
	$oran = max( 0, min( 1, (float) $oran ) );
	list( $r1, $g1, $b1 ) = qmo_chatbot_hex_parcala( $hex );
	list( $r2, $g2, $b2 ) = qmo_chatbot_hex_parcala( $hedef );

	return qmo_chatbot_rgb_hex(
		(int) round( $r1 + ( $r2 - $r1 ) * $oran ),
		(int) round( $g1 + ( $g2 - $g1 ) * $oran ),
		(int) round( $b1 + ( $b2 - $b1 ) * $oran )
	);
}

/**
 * Zemin üzerinde okunur yazı rengi.
 *
 * @param string $zemin Zemin rengi.
 * @return string
 */
function qmo_chatbot_kontrast_yazi( $zemin ) {
	return qmo_chatbot_parlaklik( $zemin ) > 0.55 ? '#1a1a1a' : '#ffffff';
}

/**
 * Yazı rengini zemin üzerinde okunur tut.
 *
 * @param string $yazi  İstenen yazı.
 * @param string $zemin Zemin.
 * @return string
 */
function qmo_chatbot_okunur_tut( $yazi, $zemin ) {
	$fark = abs( qmo_chatbot_parlaklik( $yazi ) - qmo_chatbot_parlaklik( $zemin ) );
	if ( $fark >= 0.35 ) {
		$temiz = sanitize_hex_color( $yazi );
		return $temiz ? $temiz : qmo_chatbot_kontrast_yazi( $zemin );
	}
	return qmo_chatbot_kontrast_yazi( $zemin );
}

/**
 * Üç ana renkten 14 alanı türetir.
 *
 * @param string $ana   Ana renk.
 * @param string $zemin Sohbet zemini.
 * @param string $yazi  Yazı rengi.
 * @return array<string,string>
 */
function qmo_chatbot_renkleri_turetilsin( $ana, $zemin, $yazi ) {
	$ana   = sanitize_hex_color( $ana ) ? sanitize_hex_color( $ana ) : '#8a2be2';
	$zemin = sanitize_hex_color( $zemin ) ? sanitize_hex_color( $zemin ) : '#f8fafc';
	$yazi  = sanitize_hex_color( $yazi ) ? sanitize_hex_color( $yazi ) : '#333333';

	$header_yazi = qmo_chatbot_kontrast_yazi( $ana );
	$koyu_zemin  = qmo_chatbot_parlaklik( $zemin ) < 0.35;

	if ( $koyu_zemin ) {
		$user_bg   = $ana;
		$bot_bg    = qmo_chatbot_karistir( $zemin, '#ffffff', 0.08 );
		$giris_bg  = qmo_chatbot_karistir( $zemin, '#ffffff', 0.06 );
		$alan_bg   = $zemin;
		$kenarlik  = qmo_chatbot_karistir( $zemin, '#ffffff', 0.14 );
	} else {
		$user_bg   = qmo_chatbot_karistir( $ana, '#ffffff', 0.78 );
		$bot_bg    = qmo_chatbot_karistir( $zemin, '#ffffff', 0.65 );
		$giris_bg  = qmo_chatbot_karistir( $zemin, '#ffffff', 0.35 );
		$alan_bg   = '#ffffff';
		$kenarlik  = qmo_chatbot_karistir( $zemin, $yazi, 0.16 );
	}

	$user_yazi = qmo_chatbot_okunur_tut( $koyu_zemin ? $header_yazi : $yazi, $user_bg );
	$bot_yazi  = qmo_chatbot_okunur_tut( $yazi, $bot_bg );
	$yazi      = qmo_chatbot_okunur_tut( $yazi, $zemin );

	return array(
		'gemini_main_color'          => $ana,
		'gemini_toggle_bg_color'     => $ana,
		'gemini_toggle_text_color'   => $header_yazi,
		'gemini_header_bg_color'     => $ana,
		'gemini_header_text_color'   => $header_yazi,
		'gemini_header_icon_color'   => $header_yazi,
		'gemini_text_color'          => $yazi,
		'gemini_border_color'        => $kenarlik,
		'gemini_bg_color'            => $zemin,
		'gemini_chat_bg_color'       => $zemin,
		'gemini_user_msg_color'      => $user_bg,
		'gemini_user_msg_text_color' => $user_yazi,
		'gemini_bot_msg_color'       => $bot_bg,
		'gemini_bot_msg_text_color'  => $bot_yazi,
		'gemini_input_bg_color'      => $giris_bg,
		'gemini_input_area_bg_color' => $alan_bg,
		'gemini_send_btn_bg_color'   => $ana,
		'gemini_send_btn_icon_color' => $header_yazi,
	);
}

/**
 * Elle değiştirilmiş renk anahtarları.
 *
 * @return string[]
 */
function qmo_chatbot_renk_elle_liste() {
	$ham = get_option( 'qmo_chatbot_color_overrides', array() );
	if ( ! is_array( $ham ) ) {
		return array();
	}
	return array_values( array_filter( array_map( 'sanitize_key', $ham ) ) );
}

/**
 * Türetilmiş + elle korunan renkleri birleştirip okur.
 *
 * @return array<string,string>
 */
function qmo_chatbot_renkleri_coz() {
	$kayit   = function_exists( 'qmo_chatbot_renkleri_oku' ) ? qmo_chatbot_renkleri_oku() : qmo_renk_varsayilanlari();
	$ana     = isset( $kayit['gemini_main_color'] ) ? $kayit['gemini_main_color'] : '#8a2be2';
	$zemin   = isset( $kayit['gemini_chat_bg_color'] ) ? $kayit['gemini_chat_bg_color'] : '#f8fafc';
	$yazi    = isset( $kayit['gemini_text_color'] ) ? $kayit['gemini_text_color'] : '#333333';
	$turetil = qmo_chatbot_renkleri_turetilsin( $ana, $zemin, $yazi );
	$elle    = qmo_chatbot_renk_elle_liste();

	foreach ( $elle as $anahtar ) {
		if ( isset( $kayit[ $anahtar ] ) ) {
			$turetil[ $anahtar ] = $kayit[ $anahtar ];
		}
	}

	$turetil['gemini_main_color']     = $ana;
	$turetil['gemini_chat_bg_color']  = $zemin;
	$turetil['gemini_text_color']     = $yazi;

	return $turetil;
}

/**
 * Çalışma saatleri dışında mıyız?
 *
 * @return bool
 */
function qmo_chatbot_mesai_disi_mi() {
	if ( 'yes' !== qmo_chatbot_ayar( 'qmo_chatbot_hide_after_hours' ) ) {
		return false;
	}
	if ( ! function_exists( 'qrms_cs_is_open_at' ) ) {
		return false;
	}
	return ! qrms_cs_is_open_at();
}

/**
 * Asistanın ön yüzde varlık yüklemesi / render etmesi gerekir mi?
 *
 * Kapalıysa veya mesai dışı + gizle ise false.
 *
 * @return bool
 */
function qmo_chatbot_onyuz_yuklensin_mi() {
	if ( ! qmo_chatbot_aktif_mi() ) {
		return false;
	}
	if ( qmo_chatbot_mesai_disi_mi() && 'hide' === qmo_chatbot_ayar( 'qmo_chatbot_closed_behavior' ) ) {
		return false;
	}
	return true;
}

/**
 * Chatbot wp_footer ile otomatik basılsın mı?
 *
 * Kapalıyken yalnızca [gemini_chatbot] kısa kodu çalışır — mevcut kurulumlar bozulmaz.
 *
 * @return bool
 */
function qmo_chatbot_otomatik_goster_mi() {
	return 'yes' === qmo_chatbot_ayar( 'qmo_chatbot_auto_inject' );
}

/**
 * Ön yüz HTML enjeksiyonu için uygun istek mi?
 *
 * Yönetim, AJAX, REST, feed, login, 404 ve Elementor önizlemesi dışarıda kalır.
 *
 * @return bool
 */
function qmo_chatbot_on_yuz_istegi_mi() {
	if ( is_admin() || wp_doing_ajax() || is_feed() || is_robots() ) {
		return false;
	}
	if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
		return false;
	}
	if ( defined( 'DOING_CRON' ) && DOING_CRON ) {
		return false;
	}
	if ( function_exists( 'is_login' ) && is_login() ) {
		return false;
	}
	if ( function_exists( 'is_404' ) && is_404() ) {
		return false;
	}
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( isset( $_GET['elementor-preview'] ) ) {
		return false;
	}
	if ( function_exists( 'is_customize_preview' ) && is_customize_preview() ) {
		return false;
	}
	return true;
}

/**
 * wp_footer otomatik enjeksiyonu bu istekte çalışmalı mı?
 *
 * Sıra performans içindir — her sayfa yüklemesinde wp_footer'da çalışır:
 * 1) modül aktif, 2) otomatik ayar, 3) istek türü, 4) çift basım bayrağı,
 * 5) mesai dışı gizle, 6) oturum (en pahalı).
 *
 * @return bool
 */
function qmo_chatbot_otomatik_basilmali_mi() {
	if ( ! qmo_chatbot_aktif_mi() ) {
		return false;
	}
	if ( ! qmo_chatbot_otomatik_goster_mi() ) {
		return false;
	}
	if ( ! qmo_chatbot_on_yuz_istegi_mi() ) {
		return false;
	}
	if ( function_exists( 'qmo_chatbot_istekte_basildi' ) && qmo_chatbot_istekte_basildi() ) {
		return false;
	}
	if ( qmo_chatbot_mesai_disi_mi() && 'hide' === qmo_chatbot_ayar( 'qmo_chatbot_closed_behavior' ) ) {
		return false;
	}
	// Oturum zorunlu modda tanıtım sayfalarına QR uyarısı basma — sessiz kal.
	if ( qmo_chatbot_oturum_zorunlu_mu() && ( ! function_exists( 'qmo_oturum' ) || ! qmo_oturum() ) ) {
		return false;
	}
	return true;
}

/**
 * Masa oturumu zorunlu mu?
 *
 * @return bool
 */
function qmo_chatbot_oturum_zorunlu_mu() {
	return 'all' !== qmo_chatbot_ayar( 'qmo_chatbot_audience' );
}

/**
 * Yasaklı kelime listesi.
 *
 * @return string[]
 */
function qmo_chatbot_yasakli_kelimeler() {
	$ham = (string) qmo_chatbot_ayar( 'qmo_chatbot_banned_words' );
	$ham = str_replace( array( "\r\n", "\r" ), "\n", $ham );
	$satirlar = preg_split( '/[\n,]+/', $ham );
	$liste    = array();
	foreach ( (array) $satirlar as $kelime ) {
		$kelime = trim( (string) $kelime );
		if ( '' !== $kelime ) {
			$liste[] = $kelime;
		}
	}
	return $liste;
}

/**
 * Metinde yasaklı kelime var mı?
 *
 * @param string $metin Metin.
 * @return bool
 */
function qmo_chatbot_yasakli_mi( $metin ) {
	$metin = function_exists( 'mb_strtolower' ) ? mb_strtolower( $metin ) : strtolower( $metin );
	foreach ( qmo_chatbot_yasakli_kelimeler() as $kelime ) {
		$ara = function_exists( 'mb_strtolower' ) ? mb_strtolower( $kelime ) : strtolower( $kelime );
		if ( '' !== $ara && false !== strpos( $metin, $ara ) ) {
			return true;
		}
	}
	return false;
}

/**
 * Ziyaretçi anahtarı — oturum varsa masa+issued, yoksa IP.
 *
 * @param array $sess Oturum dizisi.
 * @return string
 */
function qmo_chatbot_ziyaretci_anahtar( $sess = array() ) {
	if ( ! empty( $sess['masa'] ) ) {
		$issued = isset( $sess['issued'] ) ? (string) $sess['issued'] : '';
		return 's_' . md5( $sess['masa'] . '_' . $issued );
	}

	$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '0';
	return 'i_' . md5( $ip );
}

/**
 * Günlük ve dakikalık mesaj sınırını uygular. Aşılırsa JSON hata basıp çıkar.
 *
 * @param array $sess Oturum.
 * @return void
 */
function qmo_chatbot_sinir_kontrol( $sess ) {
	$dakika = (int) qmo_chatbot_ayar( 'qmo_chatbot_rate_per_min' );
	if ( $dakika > 0 ) {
		$k = 'qmo_cb_rpm_' . qmo_chatbot_ziyaretci_anahtar( $sess );
		$n = qmo_sayac_arttir( $k, MINUTE_IN_SECONDS );
		if ( $n > $dakika ) {
			wp_send_json_error(
				array(
					'kod'   => 'limit',
					'mesaj' => qmo_ceviri_chat( __( 'Çok hızlı soru gönderiyorsunuz. Lütfen biraz bekleyin.', 'qrms' ) ),
				),
				429
			);
		}
	}

	$gunluk = (int) qmo_chatbot_ayar( 'qmo_chatbot_daily_limit' );
	if ( $gunluk > 0 ) {
		$k = 'qmo_cb_day_' . gmdate( 'Ymd' );
		$n = qmo_sayac_arttir( $k, DAY_IN_SECONDS );
		if ( $n > $gunluk ) {
			wp_send_json_error( qmo_ceviri_chat( qmo_chatbot_ayar( 'qmo_chatbot_daily_limit_msg' ) ) );
		}
	}
}

/**
 * Bot cevabı "bilemedi" mi?
 *
 * @param string $cevap Cevap.
 * @return bool
 */
function qmo_chatbot_bilemedi_mi( $cevap ) {
	$cevap = (string) $cevap;
	if ( false !== strpos( $cevap, '[BILEMEDI]' ) ) {
		return true;
	}

	$kucuk = function_exists( 'mb_strtolower' ) ? mb_strtolower( $cevap, 'UTF-8' ) : strtolower( $cevap );
	$ipucu = array(
		'bilemiyorum',
		'bilmiyorum',
		'emin değilim',
		'emin degilim',
		'menüde böyle',
		'menude boyle',
		'bu konuda yardımcı olamam',
		'bu konuda yardimci olamam',
		'bulamadım',
		'bulamadim',
	);

	// Modelin bir "bilemedi" ifadesinden HEMEN SONRA bir alternatif/öneri
	// sunduğu durumlar yanlış pozitiftir (ör. "bulamadım ama benzerini
	// önerebilirim") — bu aslında müşteriye yardımcı olan normal bir
	// yanıttır, "Cevaplanamayan Sorular" kuyruğuna düşmemeli. İfadeden
	// sonraki kısa bir pencerede bağlaç/öneri kelimesi varsa bu ipucu
	// "bilemedi" SAYILMAZ; diğer ipuçları yine ayrı ayrı kontrol edilir.
	$kurtarma_penceresi = 60;
	$kurtarma_kelimeler = array( 'ama ', 'ancak ', 'fakat ', 'yerine ', 'öner', 'oner', 'ister misiniz', 'ister misin' );

	foreach ( $ipucu as $parca ) {
		$konum = mb_strpos( $kucuk, $parca, 0, 'UTF-8' );
		if ( false === $konum ) {
			continue;
		}

		$sonrasi    = mb_substr( $kucuk, $konum + mb_strlen( $parca, 'UTF-8' ), $kurtarma_penceresi, 'UTF-8' );
		$kurtarildi = false;
		foreach ( $kurtarma_kelimeler as $kk ) {
			if ( false !== mb_strpos( $sonrasi, $kk, 0, 'UTF-8' ) ) {
				$kurtarildi = true;
				break;
			}
		}

		if ( ! $kurtarildi ) {
			return true;
		}
	}

	return false;
}
