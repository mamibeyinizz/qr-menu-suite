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
	$ortak = 'viewBox="0 0 24 24" width="100%" height="100%" fill="currentColor" aria-hidden="true"';

	return array(
		'bubble'   => array(
			'label' => __( 'Sohbet balonu', 'qrms' ),
			'svg'   => '<svg ' . $ortak . '><path d="M4 4h16a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H8l-4 4V6a2 2 0 0 1 2-2zm3 5h10v2H7V9zm0 4h7v2H7v-2z"/></svg>',
		),
		'waiter'   => array(
			'label' => __( 'Garson', 'qrms' ),
			'svg'   => '<svg ' . $ortak . '><path d="M12 12a4 4 0 1 0-4-4 4 4 0 0 0 4 4zm0 2c-3.3 0-8 1.7-8 5v1h16v-1c0-3.3-4.7-5-8-5zm7.5-7.2.9-1.6 1.7.9-.9 1.6A7 7 0 0 1 19 12h-2a5 5 0 0 0 2.5-5.2zM4.5 4.8 3.6 3.2 1.9 4.1l.9 1.6A7 7 0 0 0 5 12H7A5 5 0 0 1 4.5 6.8z"/></svg>',
		),
		'chef'     => array(
			'label' => __( 'Şef şapkası', 'qrms' ),
			'svg'   => '<svg ' . $ortak . '><path d="M8 10a4 4 0 0 1 8 0h1a3 3 0 0 1 0 6H7a3 3 0 0 1 0-6h1zm-1 8h10v3H7v-3z"/></svg>',
		),
		'star'     => array(
			'label' => __( 'Yıldız', 'qrms' ),
			'svg'   => '<svg ' . $ortak . '><path d="M12 2.5 14.8 9l6.7.6-5.1 4.4 1.6 6.5L12 16.8 6 20.5l1.6-6.5L2.5 9.6 9.2 9z"/></svg>',
		),
		'coffee'   => array(
			'label' => __( 'Kahve fincanı', 'qrms' ),
			'svg'   => '<svg ' . $ortak . '><path d="M4 5h12v7a5 5 0 0 1-5 5H9a5 5 0 0 1-5-5V5zm12 2h2.5A2.5 2.5 0 0 1 21 9.5 2.5 2.5 0 0 1 18.5 12H16V7zM5 20h12v2H5v-2z"/></svg>',
		),
		'fork'     => array(
			'label' => __( 'Çatal-bıçak', 'qrms' ),
			'svg'   => '<svg ' . $ortak . '><path d="M5 2h2v6H5V2zm3 0h2v6H8V2zm-3 7h5v2H9v11H7V11H5V9zm10-7c2 0 3 1.5 3 3.5V11h-2V4.5c0-.8-.4-1.5-1-1.5s-1 .7-1 1.5V22h-2V2.5C12 2.2 13.2 2 15 2z"/></svg>',
		),
		'question' => array(
			'label' => __( 'Soru işareti', 'qrms' ),
			'svg'   => '<svg ' . $ortak . '><path d="M12 2a8 8 0 0 1 8 8c0 3.2-2 5.2-4.2 6.4-.9.5-1.3 1-1.3 1.6v.5h-5v-.7c0-2 1.5-3.3 3.1-4.2C14.4 12.8 15 11.8 15 10a3 3 0 1 0-6 0H6a6 6 0 0 1 6-8zm-2 16h4v4h-4v-4z"/></svg>',
		),
		'bell'     => array(
			'label' => __( 'Zil', 'qrms' ),
			'svg'   => '<svg ' . $ortak . '><path d="M12 2a6 6 0 0 1 6 6v5l2 3H4l2-3V8a6 6 0 0 1 6-6zm-2 16a2 2 0 0 0 4 0H10z"/></svg>',
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
		$n = (int) get_transient( $k );
		if ( $n >= $dakika ) {
			wp_send_json_error(
				array(
					'kod'   => 'limit',
					'mesaj' => __( 'Çok hızlı soru gönderiyorsunuz. Lütfen biraz bekleyin.', 'qrms' ),
				),
				429
			);
		}
		set_transient( $k, $n + 1, MINUTE_IN_SECONDS );
	}

	$gunluk = (int) qmo_chatbot_ayar( 'qmo_chatbot_daily_limit' );
	if ( $gunluk > 0 ) {
		$k = 'qmo_cb_day_' . gmdate( 'Ymd' );
		$n = (int) get_transient( $k );
		if ( $n >= $gunluk ) {
			wp_send_json_error( qmo_chatbot_ayar( 'qmo_chatbot_daily_limit_msg' ) );
		}
		set_transient( $k, $n + 1, DAY_IN_SECONDS );
	}
}

/**
 * Bot cevabı "bilemedi" mi?
 *
 * @param string $cevap Cevap.
 * @return bool
 */
function qmo_chatbot_bilemedi_mi( $cevap ) {
	if ( false !== strpos( $cevap, '[BILEMEDI]' ) ) {
		return true;
	}
	$cevap = function_exists( 'mb_strtolower' ) ? mb_strtolower( $cevap ) : strtolower( $cevap );
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
	foreach ( $ipucu as $parca ) {
		if ( false !== strpos( $cevap, $parca ) ) {
			return true;
		}
	}
	return false;
}
