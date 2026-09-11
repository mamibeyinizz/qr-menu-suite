<?php
/**
 * Yönetim sayfası: QR Menü → QR Chatbot
 *
 * Hub ekranı modülün kendi kart bileşenini kullanır (qmo-cb-* ad alanı).
 * Form alanları kendi alt sayfalarındadır; option key'leri ve name
 * attribute'ları değişmez.
 *
 * @package QR_Menu_Suite
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once dirname( __DIR__ ) . '/class-ayarlar.php';
require_once dirname( __DIR__ ) . '/class-db.php';

if ( ! defined( 'QMO_CHATBOT_ADMIN_INIT' ) ) {
	define( 'QMO_CHATBOT_ADMIN_INIT', true );

	add_action( 'admin_init', 'qmo_chatbot_ayarlarini_kaydet' );
	add_action( 'admin_post_qmo_chatbot_menu_guncelle', 'qmo_chatbot_menu_guncelle_handler' );
	add_action( 'admin_init', array( 'QMO_Chatbot_DB', 'sema_kontrol' ) );
	add_action( 'qmo_chatbot_gecmis_temizle', 'qmo_chatbot_eski_kayitlari_sil' );
}

require_once __DIR__ . '/sayfa-gorunum.php';
require_once __DIR__ . '/sayfa-sorular.php';
require_once __DIR__ . '/sayfa-gorunurluk.php';
require_once __DIR__ . '/sayfa-gecmis.php';
require_once __DIR__ . '/sayfa-cevaplanamayan.php';
require_once __DIR__ . '/sayfa-oneri.php';
require_once __DIR__ . '/sayfa-oneri-rapor.php';
require_once __DIR__ . '/sayfa-canli-sohbet.php';

/**
 * Kayıtlı renk option'larını güvenli biçimde okur.
 *
 * @return array<string,string>
 */
if ( ! function_exists( 'qmo_chatbot_renkleri_oku' ) ) {
	function qmo_chatbot_renkleri_oku() {
		$d       = qmo_renk_varsayilanlari();
		$renkler = array();

		foreach ( array_keys( $d ) as $anahtar ) {
			$deger                  = get_option( $anahtar, $d[ $anahtar ] );
			$temiz                  = sanitize_hex_color( $deger );
			$renkler[ $anahtar ]    = $temiz ? $temiz : $d[ $anahtar ];
		}

		return $renkler;
	}
}

/**
 * Form gönderimini işler.
 *
 * Her alt sayfa kendi formunu basar; yalnızca POST'ta bulunan alanlar
 * yazılır — diğer sayfaların option'ları dokunulmadan kalır.
 *
 * @return void
 */
if ( ! function_exists( 'qmo_chatbot_ayarlarini_kaydet' ) ) {
	function qmo_chatbot_ayarlarini_kaydet() {
		if ( ! isset( $_POST['qmo_chatbot_kaydet'] ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		check_admin_referer( 'qmo_chatbot_ayar', 'qmo_chatbot_nonce' );

		if ( isset( $_POST['gemini_api_key'] ) ) {
			$api_key = sanitize_text_field( wp_unslash( $_POST['gemini_api_key'] ) );
			if ( '' !== $api_key ) {
				// autoload=false: anahtar her istekte alloptions'a yüklenmez.
				update_option( 'gemini_api_key', $api_key, false );
			}
		}

		if ( isset( $_POST['qmo_gemini_model'] ) ) {
			update_option( 'qmo_gemini_model', sanitize_text_field( wp_unslash( $_POST['qmo_gemini_model'] ) ) );
		}
		if ( isset( $_POST['gemini_bot_name'] ) ) {
			update_option( 'gemini_bot_name', sanitize_text_field( wp_unslash( $_POST['gemini_bot_name'] ) ) );
		}
		if ( isset( $_POST['gemini_show_toggle_text'] ) ) {
			$toggle_metin = wp_unslash( $_POST['gemini_show_toggle_text'] );
			if ( is_array( $toggle_metin ) ) {
				$toggle_metin = end( $toggle_metin );
			}
			update_option( 'gemini_show_toggle_text', 'yes' === sanitize_key( $toggle_metin ) ? 'yes' : 'no' );
		}
		if ( isset( $_POST['gemini_welcome_text'] ) ) {
			update_option( 'gemini_welcome_text', sanitize_textarea_field( wp_unslash( $_POST['gemini_welcome_text'] ) ) );
		}
		if ( isset( $_POST['gemini_placeholder_text'] ) ) {
			update_option( 'gemini_placeholder_text', sanitize_text_field( wp_unslash( $_POST['gemini_placeholder_text'] ) ) );
		}
		if ( isset( $_POST['gemini_bot_icon'] ) ) {
			update_option( 'gemini_bot_icon', esc_url_raw( wp_unslash( $_POST['gemini_bot_icon'] ) ) );
		}
		if ( isset( $_POST['gemini_icon_size'] ) ) {
			update_option( 'gemini_icon_size', max( 30, absint( $_POST['gemini_icon_size'] ) ) );
		}
		if ( isset( $_POST['gemini_border_radius'] ) ) {
			update_option( 'gemini_border_radius', max( 14, absint( $_POST['gemini_border_radius'] ) ) );
		}
		if ( isset( $_POST['gemini_system_prompt'] ) ) {
			update_option( 'gemini_system_prompt', sanitize_textarea_field( wp_unslash( $_POST['gemini_system_prompt'] ) ) );
		}
		if ( isset( $_POST['gemini_menu_json_data'] ) ) {
			update_option( 'gemini_menu_json_data', wp_unslash( $_POST['gemini_menu_json_data'] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON ham metin olarak saklanır.
		}

		if ( isset( $_POST['gemini_active_preset'] ) ) {
			update_option( 'gemini_active_preset', sanitize_key( wp_unslash( $_POST['gemini_active_preset'] ) ) );
		}

		$varsayilan = qmo_renk_varsayilanlari();
		foreach ( array_keys( $varsayilan ) as $anahtar ) {
			if ( ! isset( $_POST[ $anahtar ] ) ) {
				continue;
			}
			$temiz = sanitize_hex_color( wp_unslash( $_POST[ $anahtar ] ) );
			if ( $temiz ) {
				update_option( $anahtar, $temiz );
			}
		}

		qmo_chatbot_yeni_alanlari_kaydet();

		add_settings_error( 'qmo_chatbot', 'kaydedildi', 'Ayarlar kaydedildi.', 'updated' );
	}
}

/**
 * Yeni option alanlarını kaydeder — eski gemini_* anahtarlarına dokunmaz.
 *
 * @return void
 */
function qmo_chatbot_yeni_alanlari_kaydet() {
	$metin = array(
		'qmo_chatbot_icon_preset'      => 'sanitize_key',
		'qmo_chatbot_icon_size_preset' => 'sanitize_key',
		'qmo_chatbot_position'         => 'sanitize_key',
		'qmo_chatbot_offset'           => 'sanitize_key',
		'qmo_chatbot_attention'        => 'sanitize_key',
		'qmo_chatbot_badge'            => 'sanitize_key',
		'qmo_chatbot_advanced_colors'  => 'sanitize_key',
		'qmo_chatbot_radius_preset'    => 'sanitize_key',
		'qmo_chatbot_window_width'     => 'sanitize_key',
		'qmo_chatbot_welcome_screen'   => 'sanitize_key',
		'qmo_chatbot_welcome_btn'      => 'sanitize_text_field',
		'qmo_chatbot_teaser'           => 'sanitize_key',
		'qmo_chatbot_teaser_text'      => 'sanitize_text_field',
		'qmo_chatbot_auto_inject'    => 'sanitize_key',
		'qmo_chatbot_audience'         => 'sanitize_key',
		'qmo_chatbot_devices'          => 'sanitize_key',
		'qmo_chatbot_hide_after_hours' => 'sanitize_key',
		'qmo_chatbot_closed_behavior'  => 'sanitize_key',
		'qmo_chatbot_closed_message'   => 'sanitize_text_field',
		'qmo_chatbot_daily_limit_msg'  => 'sanitize_text_field',
		'qmo_chatbot_banned_msg'       => 'sanitize_text_field',
		'qmo_chatbot_eskalasyon'       => 'sanitize_key',
		'qmo_chatbot_eskalasyon_msg'   => 'sanitize_text_field',
	);

	foreach ( $metin as $anahtar => $temizleyici ) {
		if ( ! isset( $_POST[ $anahtar ] ) ) {
			continue;
		}
		$deger = wp_unslash( $_POST[ $anahtar ] );
		update_option( $anahtar, $temizleyici( $deger ) );
	}

	if ( isset( $_POST['qmo_chatbot_welcome_intro'] ) ) {
		update_option( 'qmo_chatbot_welcome_intro', sanitize_textarea_field( wp_unslash( $_POST['qmo_chatbot_welcome_intro'] ) ) );
	}
	if ( isset( $_POST['qmo_chatbot_banned_words'] ) ) {
		update_option( 'qmo_chatbot_banned_words', sanitize_textarea_field( wp_unslash( $_POST['qmo_chatbot_banned_words'] ) ) );
	}
	if ( isset( $_POST['qmo_chatbot_eskalasyon_msg'] ) ) {
		update_option( 'qmo_chatbot_eskalasyon_msg', sanitize_text_field( wp_unslash( $_POST['qmo_chatbot_eskalasyon_msg'] ) ) );
	}
	if ( isset( $_POST['qmo_chatbot_icon_color'] ) ) {
		$r = sanitize_hex_color( wp_unslash( $_POST['qmo_chatbot_icon_color'] ) );
		if ( $r ) {
			update_option( 'qmo_chatbot_icon_color', $r );
		}
	}
	if ( isset( $_POST['qmo_chatbot_icon_bg_color'] ) ) {
		$r = sanitize_hex_color( wp_unslash( $_POST['qmo_chatbot_icon_bg_color'] ) );
		if ( $r ) {
			update_option( 'qmo_chatbot_icon_bg_color', $r );
		}
	}
	if ( isset( $_POST['qmo_chatbot_teaser_delay'] ) ) {
		update_option( 'qmo_chatbot_teaser_delay', max( 1, min( 30, absint( $_POST['qmo_chatbot_teaser_delay'] ) ) ) );
	}
	if ( isset( $_POST['qmo_chatbot_quick_max'] ) ) {
		update_option( 'qmo_chatbot_quick_max', max( 1, min( 12, absint( $_POST['qmo_chatbot_quick_max'] ) ) ) );
	}
	if ( isset( $_POST['qmo_chatbot_daily_limit'] ) ) {
		update_option( 'qmo_chatbot_daily_limit', max( 0, absint( $_POST['qmo_chatbot_daily_limit'] ) ) );
	}
	if ( isset( $_POST['qmo_chatbot_rate_per_min'] ) ) {
		update_option( 'qmo_chatbot_rate_per_min', max( 0, absint( $_POST['qmo_chatbot_rate_per_min'] ) ) );
	}
	if ( isset( $_POST['qmo_chatbot_retention_days'] ) ) {
		update_option( 'qmo_chatbot_retention_days', max( 0, min( 365, absint( $_POST['qmo_chatbot_retention_days'] ) ) ) );
	}

	if ( isset( $_POST['qmo_chatbot_color_overrides'] ) ) {
		$ham = json_decode( sanitize_text_field( wp_unslash( $_POST['qmo_chatbot_color_overrides'] ) ), true );
		$liste = array();
		if ( is_array( $ham ) ) {
			foreach ( $ham as $k ) {
				$k = sanitize_key( $k );
				if ( '' !== $k ) {
					$liste[] = $k;
				}
			}
		}
		update_option( 'qmo_chatbot_color_overrides', $liste );
	}

	if ( isset( $_POST['qmo_chatbot_quick_replies'] ) && is_array( $_POST['qmo_chatbot_quick_replies'] ) ) {
		$ham   = wp_unslash( $_POST['qmo_chatbot_quick_replies'] );
		$liste = array();
		foreach ( $ham as $satir ) {
			if ( ! is_array( $satir ) ) {
				continue;
			}
			$etiket = isset( $satir['label'] ) ? sanitize_text_field( $satir['label'] ) : '';
			$soru   = isset( $satir['question'] ) ? sanitize_text_field( $satir['question'] ) : '';
			if ( '' === $etiket && '' === $soru ) {
				continue;
			}
			$liste[] = array(
				'id'       => isset( $satir['id'] ) ? sanitize_key( $satir['id'] ) : uniqid( 'q', false ),
				'label'    => '' !== $etiket ? $etiket : $soru,
				'question' => '' !== $soru ? $soru : $etiket,
				'enabled'  => empty( $satir['enabled'] ) ? 0 : 1,
			);
		}
		update_option( 'qmo_chatbot_quick_replies', $liste );
	}

	if ( isset( $_POST['qmo_chatbot_icon_size_preset'] ) ) {
		$harita = qmo_chatbot_boyut_haritasi();
		$p      = sanitize_key( wp_unslash( $_POST['qmo_chatbot_icon_size_preset'] ) );
		if ( isset( $harita[ $p ] ) ) {
			update_option( 'gemini_icon_size', $harita[ $p ] );
		}
	}
	if ( isset( $_POST['qmo_chatbot_radius_preset'] ) ) {
		$harita = qmo_chatbot_kose_haritasi();
		$p      = sanitize_key( wp_unslash( $_POST['qmo_chatbot_radius_preset'] ) );
		if ( isset( $harita[ $p ] ) ) {
			update_option( 'gemini_border_radius', $harita[ $p ] );
		}
	}
}

/**
 * Saklama süresi dolduysa eski sohbetleri siler.
 *
 * @return void
 */
function qmo_chatbot_eski_kayitlari_sil() {
	$gun = (int) qmo_chatbot_ayar( 'qmo_chatbot_retention_days' );
	if ( $gun > 0 ) {
		QMO_Chatbot_DB::eski_sil( $gun );
	}
}

/**
 * Restoran menü CPT'sinden chatbot menü JSON'ı üret.
 *
 * @return string
 */
if ( ! function_exists( 'qmo_chatbot_menu_json_uret' ) ) {
	function qmo_chatbot_menu_json_uret() {
		if ( ! post_type_exists( 'rma_menu_item' ) ) {
			return '';
		}

		$posts = get_posts(
			array(
				'post_type'      => 'rma_menu_item',
				'post_status'    => 'publish',
				'posts_per_page' => 500,
				'orderby'        => 'menu_order',
				'order'          => 'ASC',
			)
		);

		$urunler = array();
		foreach ( $posts as $post ) {
			// Restoran Menü'de aktif bir fiyat kampanyası varsa (toplu zam /
			// indirim) yapay zekâya kampanyalı fiyat gider; aksi hâlde bot
			// müşteriye menüde görünmeyen eski fiyatı söylerdi. Köprü modül
			// yoksa ham meta'ya düşer.
			$fiyat = function_exists( 'rma_get_effective_price' )
				? rma_get_effective_price( $post->ID )
				: get_post_meta( $post->ID, 'rma_price', true );
			$kat   = wp_get_post_terms( $post->ID, 'rma_category', array( 'fields' => 'names' ) );
			$urunler[] = array(
				'kategori' => is_array( $kat ) && $kat ? $kat[0] : '',
				'urunAdi'  => $post->post_title,
				'aciklama' => wp_strip_all_tags( $post->post_excerpt ?: $post->post_content ),
				'fiyat'    => is_numeric( $fiyat ) ? (string) $fiyat : (string) $fiyat,
				'tukendi'  => ( function_exists( 'rma_urun_tukendi' ) && rma_urun_tukendi( $post->ID ) ) ? 1 : 0,
			);
		}

		return wp_json_encode( $urunler, JSON_UNESCAPED_UNICODE );
	}
}

/**
 * Yapay Zeka Davranışı sayfasının slug'ı — menü JSON güncellemesi buraya döner.
 */
if ( ! defined( 'QMO_CHATBOT_AI_SAYFA' ) ) {
	define( 'QMO_CHATBOT_AI_SAYFA', 'qrms-chatbot-ai-behavior' );
}

/**
 * Menü JSON'unu CPT'den güncelle.
 */
if ( ! function_exists( 'qmo_chatbot_menu_guncelle_handler' ) ) {
	function qmo_chatbot_menu_guncelle_handler() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Bu sayfaya erişim yetkiniz yok.' );
		}

		check_admin_referer( 'qmo_chatbot_menu_guncelle' );

		$json = qmo_chatbot_menu_json_uret();
		if ( '' !== $json ) {
			update_option( 'gemini_menu_json_data', $json );
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => QMO_CHATBOT_AI_SAYFA,
					'menu_ok' => 1,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}
}

if ( ! function_exists( 'qmo_chatbot_sayfalar' ) ) {
	/**
	 * Chatbot alt sayfaları — TEK KAYNAK.
	 *
	 * Sayfa kaydı (add_submenu_page), hub kartları ve Genel Bakış alt
	 * listesi aynı diziden türer. Sol menüde görünmezler; kartlardan açılırlar.
	 *
	 * @return array<string,array{title:string,render:string,desc:string,icon:string,group:string}>
	 */
	function qmo_chatbot_sayfalar() {
		return array(
			'qrms-chatbot-bot-identity' => array(
				'title'  => __( 'Asistan Profili', 'qrms' ),
				'render' => 'qmo_chatbot_sayfa_bot_kimligi',
				'desc'   => __( 'Asistanın adını, karşılama mesajını ve müşterilere nasıl hitap edeceğini belirleyin.', 'qrms' ),
				'icon'   => 'dashicons-id',
				'group'  => __( 'Asistan Deneyimi', 'qrms' ),
			),
			'qrms-chatbot-appearance'   => array(
				'title'  => __( 'Görünüm ve Karşılama', 'qrms' ),
				'render' => 'qmo_chatbot_sayfa_gorunum',
				'desc'   => __( 'Asistanın simgesini, renklerini ve müşteriye gösterilecek karşılama deneyimini özelleştirin.', 'qrms' ),
				'icon'   => 'dashicons-art',
				'group'  => __( 'Asistan Deneyimi', 'qrms' ),
			),
			'qrms-chatbot-quick-replies' => array(
				'title'  => __( 'Hazır Sorular', 'qrms' ),
				'render' => 'qmo_chatbot_sayfa_sorular',
				'desc'   => __( 'Müşterilerin tek dokunuşla sorabileceği popüler soruları belirleyin.', 'qrms' ),
				'icon'   => 'dashicons-format-chat',
				'group'  => __( 'Asistan Deneyimi', 'qrms' ),
			),
			'qrms-chatbot-visibility'   => array(
				'title'  => __( 'Ne Zaman ve Kimlere Gösterilsin?', 'qrms' ),
				'render' => 'qmo_chatbot_sayfa_gorunurluk',
				'desc'   => __( 'Asistanın hangi müşterilere, hangi cihazlarda ve hangi saatlerde gösterileceğini belirleyin.', 'qrms' ),
				'icon'   => 'dashicons-visibility',
				'group'  => __( 'Asistan Deneyimi', 'qrms' ),
			),
			'qrms-chatbot-gemini'       => array(
				'title'  => __( 'AI Motoru', 'qrms' ),
				'render' => 'qmo_chatbot_sayfa_gemini',
				'desc'   => __( 'Yapay zekâ sağlayıcınızı, bağlantınızı ve kullanılacak modeli yönetin.', 'qrms' ),
				'icon'   => 'dashicons-admin-network',
				'group'  => __( 'Asistanın Zekâsı', 'qrms' ),
			),
			QMO_CHATBOT_AI_SAYFA        => array(
				'title'  => __( 'Asistan Davranışı', 'qrms' ),
				'render' => 'qmo_chatbot_sayfa_davranis',
				'desc'   => __( 'Asistanın nasıl cevap vereceğini, hangi menü bilgilerini kullanacağını ve cevap sınırlarını belirleyin.', 'qrms' ),
				'icon'   => 'dashicons-format-status',
				'group'  => __( 'Asistanın Zekâsı', 'qrms' ),
			),
			'qrms-chatbot-firebase'     => array(
				'title'  => __( 'Restoran Verisi', 'qrms' ),
				'render' => 'qmo_chatbot_sayfa_firebase',
				'desc'   => __( 'Sipariş, garson çağrısı ve hesap isteklerinin restoranınıza ulaşması için gereken bağlantı.', 'qrms' ),
				'icon'   => 'dashicons-store',
				'group'  => __( 'Entegrasyon', 'qrms' ),
			),
			'qrms-chatbot-ana-site'     => array(
				'title'  => __( 'Site Entegrasyonu', 'qrms' ),
				'render' => 'qmo_chatbot_sayfa_ana_site',
				'desc'   => __( 'Asistanın sitenizde nasıl yayınlanacağı ve merkez site ayarı.', 'qrms' ),
				'icon'   => 'dashicons-admin-site-alt3',
				'group'  => __( 'Entegrasyon', 'qrms' ),
			),
			'qrms-chatbot-history'      => array(
				'title'  => __( 'Sohbet Geçmişi', 'qrms' ),
				'render' => 'qmo_chatbot_sayfa_gecmis',
				'desc'   => __( 'Müşterilerin asistanla yaptığı görüşmeleri inceleyin.', 'qrms' ),
				'icon'   => 'dashicons-backup',
				'group'  => __( 'Müşteri İçgörüleri', 'qrms' ),
			),
			'qrms-chatbot-canli'        => array(
				'title'  => __( 'Canlı Sohbetler', 'qrms' ),
				'render' => 'qmo_chatbot_sayfa_canli',
				'desc'   => __( 'İnsan desteği gereken görüşmeleri yönetin ve müşterilerle doğrudan iletişim kurun.', 'qrms' ),
				'icon'   => 'dashicons-format-chat',
				'group'  => __( 'Müşteri İçgörüleri', 'qrms' ),
			),
			'qrms-chatbot-unanswered'   => array(
				'title'  => __( 'Cevaplanamayan Sorular', 'qrms' ),
				'render' => 'qmo_chatbot_sayfa_cevaplanamayan',
				'desc'   => __( 'Asistanın yanıtlayamadığı soruları keşfedin ve bilgi eksiklerini giderin.', 'qrms' ),
				'icon'   => 'dashicons-flag',
				'group'  => __( 'Müşteri İçgörüleri', 'qrms' ),
			),
			'qrms-chatbot-oneri'        => array(
				'title'  => __( 'Ürün Önerileri', 'qrms' ),
				'render' => 'qmo_chatbot_sayfa_oneri',
				'desc'   => __( 'Müşterilere önerilecek ürünleri ve öneri kurallarını yönetin.', 'qrms' ),
				'icon'   => 'dashicons-megaphone',
				'group'  => __( 'Müşteri İçgörüleri', 'qrms' ),
			),
			'qrms-chatbot-oneri-rapor'  => array(
				'title'  => __( 'Öneri Raporu', 'qrms' ),
				'render' => 'qmo_chatbot_sayfa_oneri_rapor',
				'desc'   => __( 'Ürün önerilerinin sepete ve siparişe dönüşüm performansını inceleyin.', 'qrms' ),
				'icon'   => 'dashicons-chart-bar',
				'group'  => __( 'Müşteri İçgörüleri', 'qrms' ),
			),
		);
	}
}

if ( ! function_exists( 'qmo_chatbot_admin_pages' ) ) {
	/**
	 * Genel Bakış kartındaki alt bağlantılar — hub kartlarıyla AYNI kaynak.
	 *
	 * @return array<int,array{url:string,title:string}>
	 */
	function qmo_chatbot_admin_pages() {
		$liste = array();

		foreach ( qmo_chatbot_sayfalar() as $slug => $page ) {
			$liste[] = array(
				'url'   => admin_url( 'admin.php?page=' . $slug ),
				'title' => $page['title'],
			);
		}

		return $liste;
	}
}

if ( ! function_exists( 'qmo_chatbot_bolum_aciklamasi' ) ) {
	/**
	 * Hub bölüm başlıklarının altındaki tek cümlelik açıklama.
	 *
	 * @param string $grup Bölüm adı (qmo_chatbot_sayfalar() içindeki `group`).
	 * @return string
	 */
	function qmo_chatbot_bolum_aciklamasi( $grup ) {
		$harita = array(
			__( 'Asistan Deneyimi', 'qrms' )   => __( 'Müşterilerin gördüğü ve kullandığı deneyimi yönetin.', 'qrms' ),
			__( 'Asistanın Zekâsı', 'qrms' )   => __( 'Asistanın neye göre cevap verdiğini ve hangi sınırlar içinde konuştuğunu belirleyin.', 'qrms' ),
			__( 'Entegrasyon', 'qrms' )        => __( 'Asistanın restoran verinizle ve sitenizle bağlantısını yönetin.', 'qrms' ),
			__( 'Müşteri İçgörüleri', 'qrms' ) => __( 'Müşterilerin asistana ne sorduğunu görün, eksikleri kapatın.', 'qrms' ),
		);

		return isset( $harita[ $grup ] ) ? $harita[ $grup ] : '';
	}
}

if ( ! function_exists( 'qmo_chatbot_ai_hazir_mi' ) ) {
	/**
	 * Yapay zekâ bağlantısı için anahtar girilmiş mi?
	 *
	 * @return bool
	 */
	function qmo_chatbot_ai_hazir_mi() {
		if ( defined( 'GEMINI_API_KEY' ) && GEMINI_API_KEY ) {
			return true;
		}

		return '' !== trim( (string) get_option( 'gemini_api_key', '' ) );
	}
}

if ( ! function_exists( 'qmo_chatbot_restoran_verisi_hazir_mi' ) ) {
	/**
	 * Sipariş/çağrı yazımı için şube bağlantısı hazır mı?
	 *
	 * @return bool
	 */
	function qmo_chatbot_restoran_verisi_hazir_mi() {
		return class_exists( 'QMO_Firestore' ) && QMO_Firestore::hazir_mi();
	}
}

if ( ! function_exists( 'qmo_chatbot_kurulum_adimlari' ) ) {
	/**
	 * Kurulum durumu listesi — YALNIZCA kayıtlı ayarlardan türetilir.
	 *
	 * Her adım: etiket, tamam mı, ilgili sayfa adresi.
	 *
	 * @return array<int,array{label:string,done:bool,url:string}>
	 */
	function qmo_chatbot_kurulum_adimlari() {
		$sorular = function_exists( 'qmo_chatbot_sorulari_oku' ) ? qmo_chatbot_sorulari_oku() : array();
		$aktif   = 0;
		foreach ( $sorular as $soru ) {
			if ( ! empty( $soru['enabled'] ) ) {
				++$aktif;
			}
		}

		return array(
			array(
				'label' => __( 'Asistan profili', 'qrms' ),
				'done'  => '' !== trim( (string) get_option( 'gemini_bot_name', '' ) )
					&& '' !== trim( (string) get_option( 'gemini_welcome_text', '' ) ),
				'url'   => admin_url( 'admin.php?page=qrms-chatbot-bot-identity' ),
			),
			array(
				'label' => __( 'Görünüm ve karşılama', 'qrms' ),
				'done'  => null !== get_option( 'qmo_chatbot_icon_preset', null )
					|| null !== get_option( 'gemini_main_color', null ),
				'url'   => admin_url( 'admin.php?page=qrms-chatbot-appearance' ),
			),
			array(
				'label' => __( 'Hazır sorular', 'qrms' ),
				'done'  => $aktif > 0,
				'url'   => admin_url( 'admin.php?page=qrms-chatbot-quick-replies' ),
			),
			array(
				'label' => __( 'Menü bilgisi', 'qrms' ),
				'done'  => '' !== trim( (string) get_option( 'gemini_menu_json_data', '' ) ),
				'url'   => admin_url( 'admin.php?page=' . QMO_CHATBOT_AI_SAYFA ),
			),
			array(
				'label' => __( 'AI bağlantısı', 'qrms' ),
				'done'  => qmo_chatbot_ai_hazir_mi(),
				'url'   => admin_url( 'admin.php?page=qrms-chatbot-gemini' ),
			),
		);
	}
}

if ( ! function_exists( 'qmo_chatbot_hub_kartlari' ) ) {
	/**
	 * Hub ekranındaki kart grupları — tek kaynak qmo_chatbot_sayfalar().
	 *
	 * Rozetler yalnızca gerçekten okunabilen durumlardan üretilir.
	 *
	 * @return array<int,array{title:string,desc:string,cards:array<int,array<string,string>>}>
	 */
	function qmo_chatbot_hub_kartlari() {
		$gruplar = array();

		foreach ( qmo_chatbot_sayfalar() as $slug => $page ) {
			$grup = $page['group'];

			if ( ! isset( $gruplar[ $grup ] ) ) {
				$gruplar[ $grup ] = array(
					'title' => $grup,
					'desc'  => qmo_chatbot_bolum_aciklamasi( $grup ),
					'cards' => array(),
				);
			}

			$kart = array(
				'url'   => admin_url( 'admin.php?page=' . $slug ),
				'title' => $page['title'],
				'desc'  => $page['desc'],
				'icon'  => $page['icon'],
			);

			if ( 'qrms-chatbot-firebase' === $slug ) {
				$hazir          = qmo_chatbot_restoran_verisi_hazir_mi();
				$kart['badge']  = $hazir ? __( 'Bağlı', 'qrms' ) : __( 'Kurulum gerekiyor', 'qrms' );
				$kart['durum']  = $hazir ? 'ok' : 'uyari';
			}

			if ( 'qrms-chatbot-gemini' === $slug ) {
				$hazir         = qmo_chatbot_ai_hazir_mi();
				$kart['badge'] = $hazir ? __( 'Bağlı', 'qrms' ) : __( 'Kurulum gerekiyor', 'qrms' );
				$kart['durum'] = $hazir ? 'ok' : 'uyari';
			}

			$gruplar[ $grup ]['cards'][] = $kart;
		}

		return array_values( $gruplar );
	}
}

if ( ! function_exists( 'qmo_chatbot_hub_kart_bas' ) ) {
	/**
	 * Tek hub kartı — kartın tamamı gerçek bir bağlantıdır.
	 *
	 * @param array $kart Kart verisi.
	 * @return void
	 */
	function qmo_chatbot_hub_kart_bas( array $kart ) {
		$durum = isset( $kart['durum'] ) ? $kart['durum'] : '';

		echo '<a class="qmo-cb-card" href="' . esc_url( $kart['url'] ) . '">';
		echo '<span class="qmo-cb-card-icon dashicons ' . esc_attr( $kart['icon'] ) . '" aria-hidden="true"></span>';
		echo '<span class="qmo-cb-card-body">';
		echo '<span class="qmo-cb-card-title">' . esc_html( $kart['title'] ) . '</span>';
		if ( ! empty( $kart['desc'] ) ) {
			echo '<span class="qmo-cb-card-desc">' . esc_html( $kart['desc'] ) . '</span>';
		}
		if ( ! empty( $kart['badge'] ) ) {
			echo '<span class="qmo-cb-badge qmo-cb-badge-' . esc_attr( $durum ? $durum : 'notr' ) . '">';
			echo '<span class="qmo-cb-badge-mark" aria-hidden="true">' . ( 'ok' === $durum ? '&#10003;' : '!' ) . '</span>';
			echo esc_html( $kart['badge'] );
			echo '</span>';
		}
		echo '</span>';
		echo '<span class="qmo-cb-card-chevron dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span>';
		echo '</a>';
	}
}

/**
 * Hub adresi (geri bağlantısı ve eski sekme yönlendirmesi).
 *
 * @return string
 */
if ( ! function_exists( 'qmo_chatbot_hub_url' ) ) {
	function qmo_chatbot_hub_url() {
		if ( class_exists( 'QRMS_Admin' ) ) {
			return QRMS_Admin::get_module_page_url( 'qr-chatbot' );
		}
		return admin_url( 'admin.php?page=qrms-module-qr-chatbot' );
	}
}

/**
 * Eski ?tab= sekmeleri -> yeni alt sayfa slug'ları.
 *
 * @return array<string,string>
 */
if ( ! function_exists( 'qmo_chatbot_eski_sekme_haritasi' ) ) {
	function qmo_chatbot_eski_sekme_haritasi() {
		return array(
			'gorunum'   => 'qrms-chatbot-appearance',
			'yapayzeka' => QMO_CHATBOT_AI_SAYFA,
		);
	}
}

/**
 * Chatbot ayarları — artık hub ekranı. Form yok.
 *
 * Eski yer imlerindeki ?tab= parametresi ilgili alt sayfaya yönlendirilir.
 *
 * @return void
 */
if ( ! function_exists( 'qmo_chatbot_ayar_sayfasi' ) ) {
	function qmo_chatbot_ayar_sayfasi() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Bu sayfaya erişim yetkiniz yok.' );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$sekme = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '';
		$harita = qmo_chatbot_eski_sekme_haritasi();
		if ( isset( $harita[ $sekme ] ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=' . $harita[ $sekme ] ) );
			exit;
		}

		$acik      = qmo_chatbot_aktif_mi();
		$ai_hazir  = qmo_chatbot_ai_hazir_mi();
		$adimlar   = qmo_chatbot_kurulum_adimlari();
		$tamam     = 0;
		foreach ( $adimlar as $adim ) {
			if ( $adim['done'] ) {
				++$tamam;
			}
		}

		if ( ! $acik ) {
			$durum_sinif = 'kapali';
			$durum_metin = __( 'Kapalı', 'qrms' );
			$durum_not   = __( 'Asistan şu an kapalı, sitede görünmüyor.', 'qrms' );
		} elseif ( ! $ai_hazir ) {
			$durum_sinif = 'uyari';
			$durum_metin = __( 'Yapılandırma gerekiyor', 'qrms' );
			$durum_not   = __( 'Asistanın cevap verebilmesi için AI bağlantısını tamamlayın.', 'qrms' );
		} else {
			$durum_sinif = 'ok';
			$durum_metin = __( 'Aktif', 'qrms' );
			$durum_not   = __( 'Misafirleriniz menünüz hakkında soru sorabilir.', 'qrms' );
		}

		$wrap = 'qmo-cb-hub';
		if ( ! $acik ) {
			$wrap .= ' qmo-cb-hub-kapali';
		}

		echo '<div class="wrap ' . esc_attr( $wrap ) . '">';

		/* Hero: kimlik + durum + ana aksiyonlar. */
		echo '<section class="qmo-cb-hero">';
		echo '<div class="qmo-cb-hero-main">';
		echo '<h1 class="qmo-cb-hero-title">' . esc_html__( 'AI Menü Asistanı', 'qrms' ) . '</h1>';
		echo '<p class="qmo-cb-hero-desc">' . esc_html__( 'Misafirlerinizin menünüz hakkında sorularını yapay zekâ ile yanıtlayın, ürünleri keşfetmelerini ve siparişe daha hızlı ulaşmalarını sağlayın.', 'qrms' ) . '</p>';
		$acik_sinif = $ai_hazir ? 'ok' : 'uyari';
		$acik_metin = $ai_hazir ? __( 'Aktif', 'qrms' ) : __( 'Yapılandırma gerekiyor', 'qrms' );
		$acik_not   = $ai_hazir
			? __( 'Misafirleriniz menünüz hakkında soru sorabilir.', 'qrms' )
			: __( 'Asistanın cevap verebilmesi için AI bağlantısını tamamlayın.', 'qrms' );

		echo '<p class="qmo-cb-status qmo-cb-status-' . esc_attr( $durum_sinif ) . '"';
		echo ' data-acik-sinif="' . esc_attr( $acik_sinif ) . '"';
		echo ' data-acik-metin="' . esc_attr( $acik_metin ) . '"';
		echo ' data-acik-not="' . esc_attr( $acik_not ) . '"';
		echo ' data-kapali-metin="' . esc_attr__( 'Kapalı', 'qrms' ) . '"';
		echo ' data-kapali-not="' . esc_attr__( 'Asistan şu an kapalı, sitede görünmüyor.', 'qrms' ) . '">';
		echo '<span class="qmo-cb-status-dot" aria-hidden="true"></span>';
		echo '<span class="qmo-cb-status-text">' . esc_html( $durum_metin ) . '</span>';
		echo '<span class="qmo-cb-status-note">' . esc_html( $durum_not ) . '</span>';
		echo '</p>';
		echo '</div>';

		echo '<div class="qmo-cb-hero-side">';
		echo '<button type="button" class="qmo-cb-switch" id="qmo-cb-hub-switch"';
		echo ' aria-pressed="' . ( $acik ? 'true' : 'false' ) . '"';
		echo ' data-nonce="' . esc_attr( wp_create_nonce( 'qmo_chatbot_toggle' ) ) . '">';
		echo '<span class="qmo-cb-switch-track" aria-hidden="true"><span class="qmo-cb-switch-thumb"></span></span>';
		echo '<span class="qmo-cb-switch-label">' . esc_html__( 'Sohbet Asistanı', 'qrms' ) . ' — ';
		echo '<strong class="qmo-cb-switch-state">' . ( $acik ? esc_html__( 'Açık', 'qrms' ) : esc_html__( 'Kapalı', 'qrms' ) ) . '</strong>';
		echo '</span></button>';
		echo '<div class="qmo-cb-hero-actions">';
		echo '<a class="qmo-cb-btn qmo-cb-btn-primary" href="' . esc_url( admin_url( 'admin.php?page=qrms-chatbot-appearance' ) ) . '">';
		echo esc_html__( 'Asistanı Test Et', 'qrms' ) . '</a>';
		echo '<a class="qmo-cb-btn" href="' . esc_url( admin_url( 'admin.php?page=qrms-chatbot-bot-identity' ) ) . '">';
		echo esc_html__( 'Ayarları Düzenle', 'qrms' ) . '</a>';
		echo '</div>';
		echo '<p class="qmo-cb-master-note"' . ( $acik ? ' hidden' : '' ) . '>';
		echo esc_html__( 'Asistan şu an kapalı, sitede görünmüyor.', 'qrms' );
		echo '</p>';
		echo '</div>';
		echo '</section>';

		/* Kurulum durumu — yalnızca kayıtlı ayarlardan okunur. */
		echo '<section class="qmo-cb-setup">';
		echo '<div class="qmo-cb-setup-head">';
		echo '<h2 class="qmo-cb-setup-title">' . esc_html__( 'Kurulum Durumu', 'qrms' ) . '</h2>';
		echo '<p class="qmo-cb-setup-count">';
		printf(
			/* translators: 1: tamamlanan adım sayısı, 2: toplam adım sayısı. */
			esc_html__( '%1$d / %2$d tamamlandı', 'qrms' ),
			(int) $tamam,
			count( $adimlar )
		);
		echo '</p>';
		echo '</div>';
		echo '<ul class="qmo-cb-setup-list">';
		foreach ( $adimlar as $adim ) {
			$sinif = $adim['done'] ? 'ok' : 'uyari';
			echo '<li class="qmo-cb-setup-item qmo-cb-setup-item-' . esc_attr( $sinif ) . '">';
			echo '<a href="' . esc_url( $adim['url'] ) . '">';
			echo '<span class="qmo-cb-setup-mark" aria-hidden="true">' . ( $adim['done'] ? '&#10003;' : '!' ) . '</span>';
			echo '<span class="qmo-cb-setup-label">' . esc_html( $adim['label'] ) . '</span>';
			echo '<span class="screen-reader-text">';
			echo $adim['done'] ? esc_html__( 'tamamlandı', 'qrms' ) : esc_html__( 'eksik', 'qrms' );
			echo '</span>';
			echo '</a></li>';
		}
		echo '</ul>';
		echo '</section>';

		foreach ( qmo_chatbot_hub_kartlari() as $grup ) {
			echo '<section class="qmo-cb-section">';
			echo '<div class="qmo-cb-section-head">';
			echo '<h2 class="qmo-cb-section-title">' . esc_html( $grup['title'] ) . '</h2>';
			if ( ! empty( $grup['desc'] ) ) {
				echo '<p class="qmo-cb-section-desc">' . esc_html( $grup['desc'] ) . '</p>';
			}
			echo '</div>';
			echo '<div class="qmo-cb-grid">';
			foreach ( $grup['cards'] as $kart ) {
				qmo_chatbot_hub_kart_bas( $kart );
			}
			echo '</div>';
			echo '</section>';
		}

		echo '</div>';
	}
}

/* -----------------------------------------------------------------
   ORTAK ALT SAYFA İSKELETİ
----------------------------------------------------------------- */

/**
 * Alt sayfa başlığı: geri bağlantısı + başlık + (varsa) açıklama.
 *
 * Geri bağlantısı suite'in ortak `.qrms-back-link` işaretlemesini kullanır;
 * metin hub başlığıyla aynıdır ("QR Chatbot").
 *
 * @param string $title Sayfa başlığı.
 * @param string $intro Kısa açıklama (düz metin).
 * @return void
 */
if ( ! function_exists( 'qmo_chatbot_sayfa_basligi' ) ) {
	function qmo_chatbot_sayfa_basligi( $title, $intro = '' ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Bu sayfaya erişim yetkiniz yok.' );
		}

		echo '<div class="qrms-subpage-nav">';
		echo '<a class="qrms-back-link" href="' . esc_url( qmo_chatbot_hub_url() ) . '">';
		echo '<span class="dashicons dashicons-arrow-left-alt2" aria-hidden="true"></span>';
		echo esc_html__( 'AI Menü Asistanı', 'qrms' );
		echo '</a></div>';
		echo '<div class="wrap qmo-wrap">';
		echo '<h1 class="qmo-baslik">' . esc_html( $title ) . '</h1>';
		if ( '' !== $intro ) {
			echo '<p class="qmo-aciklama">' . esc_html( $intro ) . '</p>';
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['menu_ok'] ) ) {
			add_settings_error( 'qmo_chatbot', 'menu_ok', 'Menü verisi ürünlerden güncellendi.', 'updated' );
		}

		settings_errors( 'qmo_chatbot' );
	}
}

/**
 * Alt sayfa kapanışı.
 *
 * @return void
 */
if ( ! function_exists( 'qmo_chatbot_sayfa_bitir' ) ) {
	function qmo_chatbot_sayfa_bitir() {
		echo '</div>';
	}
}

/**
 * Chatbot option formunu açar (nonce + mevcut kaydet düğmesi adı).
 *
 * @return void
 */
if ( ! function_exists( 'qmo_chatbot_form_ac' ) ) {
	function qmo_chatbot_form_ac() {
		echo '<form method="post" action="" id="qmo-chatbot-form">';
		wp_nonce_field( 'qmo_chatbot_ayar', 'qmo_chatbot_nonce' );
	}
}

/**
 * Chatbot option formunu Kaydet düğmesiyle kapatır.
 *
 * @return void
 */
if ( ! function_exists( 'qmo_chatbot_form_kapat' ) ) {
	function qmo_chatbot_form_kapat() {
		submit_button( 'Kaydet', 'primary', 'qmo_chatbot_kaydet' );
		echo '</form>';
	}
}

/* -----------------------------------------------------------------
   1) BOT KİMLİĞİ
----------------------------------------------------------------- */

if ( ! function_exists( 'qmo_chatbot_sayfa_bot_kimligi' ) ) {
	function qmo_chatbot_sayfa_bot_kimligi() {
		qmo_chatbot_sayfa_basligi(
			__( 'Asistan Profili', 'qrms' ),
			__( 'Asistanın adını, karşılama mesajını ve müşterilere nasıl hitap edeceğini belirleyin.', 'qrms' )
		);

		$bot_adi     = get_option( 'gemini_bot_name', 'Asistan' );
		$karsilama   = get_option( 'gemini_welcome_text', 'Merhaba! Size nasıl yardımcı olabilirim?' );
		$placeholder = get_option( 'gemini_placeholder_text', 'Bir şeyler sorun...' );
		$ikon_url    = get_option( 'gemini_bot_icon', '' );

		qmo_chatbot_form_ac();
		?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="gemini_bot_name">Asistan Adı</label></th>
				<td>
					<input type="text" id="gemini_bot_name" name="gemini_bot_name" class="regular-text"
						value="<?php echo esc_attr( $bot_adi ); ?>">
					<?php
					if ( function_exists( 'rma_ceviri_bayat_uyari_html' ) ) {
						echo rma_ceviri_bayat_uyari_html( rma_ceviri_bayat_uyari_metni( rma_ceviri_veri_dil_sayisi( 'option', 0, 'gemini_bot_name' ) ) );
					}
					?>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="gemini_welcome_text">Karşılama Mesajı</label></th>
				<td>
					<textarea id="gemini_welcome_text" name="gemini_welcome_text" rows="3" class="large-text"><?php echo esc_textarea( $karsilama ); ?></textarea>
					<?php
					if ( function_exists( 'rma_ceviri_bayat_uyari_html' ) ) {
						echo rma_ceviri_bayat_uyari_html( rma_ceviri_bayat_uyari_metni( rma_ceviri_veri_dil_sayisi( 'option', 0, 'gemini_welcome_text' ) ) );
					}
					?>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="gemini_placeholder_text">Kutu içi ipucu metni</label></th>
				<td>
					<input type="text" id="gemini_placeholder_text" name="gemini_placeholder_text" class="regular-text"
						value="<?php echo esc_attr( $placeholder ); ?>">
					<?php
					if ( function_exists( 'rma_ceviri_bayat_uyari_html' ) ) {
						echo rma_ceviri_bayat_uyari_html( rma_ceviri_bayat_uyari_metni( rma_ceviri_veri_dil_sayisi( 'option', 0, 'gemini_placeholder_text' ) ) );
					}
					?>
				</td>
			</tr>
			<tr>
				<th scope="row">Açma butonu yazısı</th>
				<td>
					<?php qmo_chatbot_option_ac_kapa( 'gemini_show_toggle_text', __( 'Asistan adını açma butonunda göster', 'qrms' ) ); ?>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="gemini_bot_icon">Asistan Simgesi</label></th>
				<td>
					<input type="url" id="gemini_bot_icon" name="gemini_bot_icon" class="regular-text"
						value="<?php echo esc_url( $ikon_url ); ?>" placeholder="https://...">
					<button type="button" class="button" id="qmo-icon-upload">Medya Kütüphanesinden Seç</button>
					<?php if ( $ikon_url ) : ?>
						<p><img src="<?php echo esc_url( $ikon_url ); ?>" alt="" style="max-width:48px;border-radius:50%;margin-top:8px;"></p>
					<?php endif; ?>
				</td>
			</tr>
		</table>
		<?php
		qmo_chatbot_form_kapat();
		qmo_chatbot_sayfa_bitir();
	}
}


/* Görünüm sihirbazı: includes/admin/sayfa-gorunum.php */

/* -----------------------------------------------------------------
   3) GEMINI BAĞLANTISI
----------------------------------------------------------------- */

if ( ! function_exists( 'qmo_chatbot_sayfa_gemini' ) ) {
	function qmo_chatbot_sayfa_gemini() {
		qmo_chatbot_sayfa_basligi(
			__( 'AI Motoru', 'qrms' ),
			__( 'Yapay zekâ sağlayıcınızı, bağlantınızı ve kullanılacak modeli yönetin.', 'qrms' )
		);

		$ai_hazir = qmo_chatbot_ai_hazir_mi();
		echo '<div class="qmo-cb-panel-status qmo-cb-panel-status-' . ( $ai_hazir ? 'ok' : 'uyari' ) . '">';
		echo '<span class="qmo-cb-panel-status-mark" aria-hidden="true">' . ( $ai_hazir ? '&#10003;' : '!' ) . '</span>';
		echo '<span class="qmo-cb-panel-status-body"><strong>' . esc_html__( 'Google Gemini', 'qrms' ) . '</strong> ';
		echo esc_html( $ai_hazir ? __( '— Bağlı', 'qrms' ) : __( '— Kurulum gerekiyor', 'qrms' ) );
		echo '</span></div>';

		qmo_chatbot_form_ac();
		?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="gemini_api_key">Bağlantı anahtarı</label></th>
				<td>
					<input type="password" id="gemini_api_key" name="gemini_api_key" class="regular-text"
						placeholder="<?php echo get_option( 'gemini_api_key' ) ? '•••••••• (değiştirmek için yazın)' : 'API anahtarınızı girin'; ?>"
						autocomplete="off">
					<p class="description">
						<a href="https://aistudio.google.com/apikey" target="_blank" rel="noopener noreferrer">Google AI Studio</a>
						üzerinden alınır. Boş bırakırsanız mevcut anahtar korunur.
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="qmo_gemini_model">Kullanılacak model</label></th>
				<td>
					<input type="text" id="qmo_gemini_model" name="qmo_gemini_model" class="regular-text"
						value="<?php echo esc_attr( get_option( 'qmo_gemini_model', '' ) ); ?>"
						placeholder="gemini-3-flash-preview">
					<p class="description">Boş bırakılırsa <code>gemini-3-flash-preview</code> kullanılır.</p>
				</td>
			</tr>
		</table>
		<?php
		qmo_chatbot_form_kapat();
		qmo_chatbot_sayfa_bitir();
	}
}

/* -----------------------------------------------------------------
   4) YAPAY ZEKA DAVRANIŞI
----------------------------------------------------------------- */

if ( ! function_exists( 'qmo_chatbot_sayfa_davranis' ) ) {
	function qmo_chatbot_sayfa_davranis() {
		qmo_chatbot_sayfa_basligi(
			__( 'Asistan Davranışı', 'qrms' ),
			__( 'Asistanın nasıl cevap vereceğini, hangi menü bilgilerini kullanacağını ve cevap sınırlarını belirleyin.', 'qrms' )
		);

		qmo_chatbot_form_ac();
		?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="gemini_system_prompt">Asistanın konuşma kuralları</label></th>
				<td>
					<textarea id="gemini_system_prompt" name="gemini_system_prompt" rows="10" class="large-text code"><?php echo esc_textarea( get_option( 'gemini_system_prompt', '' ) ); ?></textarea>
					<p class="description">
						Asistanın genel davranışını tanımlar. Sipariş, garson ve menü kuralları
						otomatik olarak eklenir.
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="gemini_menu_json_data">Asistanın kullandığı menü bilgisi</label></th>
				<td>
					<textarea id="gemini_menu_json_data" name="gemini_menu_json_data" rows="12" class="large-text code"><?php echo esc_textarea( get_option( 'gemini_menu_json_data', '' ) ); ?></textarea>
					<p class="description">
						Asistanın ürün ve fiyat sorularında kullanacağı menü verisi.
						<?php if ( post_type_exists( 'rma_menu_item' ) ) : ?>
							<a class="button button-secondary" style="margin-top:6px;"
								href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=qmo_chatbot_menu_guncelle' ), 'qmo_chatbot_menu_guncelle' ) ); ?>">
								Restoran menü ürünlerinden güncelle
							</a>
						<?php else : ?>
							Restoran Menü modülünden dışa aktarılan JSON buraya yapıştırılabilir.
						<?php endif; ?>
					</p>
				</td>
			</tr>
		</table>

		<h2><?php esc_html_e( 'Kullanım Sınırı ve Güvenlik', 'qrms' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="qmo_chatbot_daily_limit"><?php esc_html_e( 'Günlük mesaj sınırı', 'qrms' ); ?></label></th>
				<td>
					<input type="number" id="qmo_chatbot_daily_limit" name="qmo_chatbot_daily_limit" class="small-text" min="0"
						value="<?php echo esc_attr( (int) qmo_chatbot_ayar( 'qmo_chatbot_daily_limit' ) ); ?>">
					<p class="description"><?php esc_html_e( 'Tüm ziyaretçiler için günlük üst sınır. 0 = sınırsız.', 'qrms' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="qmo_chatbot_daily_limit_msg"><?php esc_html_e( 'Sınır dolunca gösterilecek mesaj', 'qrms' ); ?></label></th>
				<td>
					<input type="text" id="qmo_chatbot_daily_limit_msg" name="qmo_chatbot_daily_limit_msg" class="large-text"
						value="<?php echo esc_attr( qmo_chatbot_ayar( 'qmo_chatbot_daily_limit_msg' ) ); ?>">
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="qmo_chatbot_rate_per_min"><?php esc_html_e( 'Ziyaretçi başına dakikalık sınır', 'qrms' ); ?></label></th>
				<td>
					<input type="number" id="qmo_chatbot_rate_per_min" name="qmo_chatbot_rate_per_min" class="small-text" min="0"
						value="<?php echo esc_attr( (int) qmo_chatbot_ayar( 'qmo_chatbot_rate_per_min' ) ); ?>">
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="qmo_chatbot_banned_words"><?php esc_html_e( 'Yasaklı kelime / konu listesi', 'qrms' ); ?></label></th>
				<td>
					<textarea id="qmo_chatbot_banned_words" name="qmo_chatbot_banned_words" rows="4" class="large-text"><?php echo esc_textarea( qmo_chatbot_ayar( 'qmo_chatbot_banned_words' ) ); ?></textarea>
					<p class="description"><?php esc_html_e( 'Her satıra bir kelime veya konu. Eşleşirse asistan cevap vermez.', 'qrms' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="qmo_chatbot_banned_msg"><?php esc_html_e( 'Yasaklı konuda gösterilecek uyarı', 'qrms' ); ?></label></th>
				<td>
					<input type="text" id="qmo_chatbot_banned_msg" name="qmo_chatbot_banned_msg" class="large-text"
						value="<?php echo esc_attr( qmo_chatbot_ayar( 'qmo_chatbot_banned_msg' ) ); ?>">
				</td>
			</tr>
		</table>

		<h2><?php esc_html_e( 'Cevapsız soru eskalasyonu', 'qrms' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Cevapsız soruda garson öner', 'qrms' ); ?></th>
				<td>
					<?php qmo_chatbot_ac_kapa( 'qmo_chatbot_eskalasyon', qmo_chatbot_ayar( 'qmo_chatbot_eskalasyon' ), __( 'Asistan cevaplayamadığında garson çağırma butonu göstersin.', 'qrms' ) ); ?>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="qmo_chatbot_eskalasyon_msg"><?php esc_html_e( 'Eskalasyon mesajı', 'qrms' ); ?></label></th>
				<td>
					<input type="text" id="qmo_chatbot_eskalasyon_msg" name="qmo_chatbot_eskalasyon_msg" class="large-text"
						value="<?php echo esc_attr( qmo_chatbot_ayar( 'qmo_chatbot_eskalasyon_msg' ) ); ?>">
					<p class="description"><?php esc_html_e( 'Garson çağır butonunun üstünde gösterilir.', 'qrms' ); ?></p>
				</td>
			</tr>
		</table>
		<?php
		qmo_chatbot_form_kapat();
		qmo_chatbot_sayfa_bitir();
	}
}

/* -----------------------------------------------------------------
   5) FIREBASE / ŞUBE BAĞLANTISI
   Ortak option'lar (qmo_branch_id, qmo_firebase_sa). options.php
   grubun TÜM option'larını yazar; formda olmayan qmo_ana_site
   gizli alanla korunur.
----------------------------------------------------------------- */

if ( ! function_exists( 'qmo_chatbot_sayfa_firebase' ) ) {
	function qmo_chatbot_sayfa_firebase() {
		qmo_chatbot_sayfa_basligi(
			__( 'Restoran Verisi', 'qrms' ),
			__( 'Sipariş, garson çağrısı ve hesap isteklerinin restoranınıza ulaşabilmesi için gereken bağlantı.', 'qrms' )
		);

		settings_errors();

		$grup = defined( 'QMO_FIREBASE_AYAR_GRUBU' ) ? QMO_FIREBASE_AYAR_GRUBU : 'qmo_firebase_grup';
		?>
		<form method="post" action="options.php" id="qmo-firebase-form">
			<?php settings_fields( $grup ); ?>

			<?php if ( get_option( 'qmo_ana_site' ) ) : ?>
				<input type="hidden" name="qmo_ana_site" value="1">
			<?php endif; ?>

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="qmo_branch_id">Şube kimliği</label></th>
					<td>
						<input type="text" id="qmo_branch_id" name="qmo_branch_id"
							value="<?php echo esc_attr( get_option( 'qmo_branch_id', '' ) ); ?>" class="regular-text" />
						<p class="description">Garson/hesap çağrıları, siparişler ve analitik sorguları bu şube kimliğiyle eşleşir.</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="qmo_firebase_sa">Bağlantı anahtarı</label></th>
					<td>
						<?php if ( class_exists( 'QMO_Firestore' ) && QMO_Firestore::hazir_mi() ) : ?>
							<p class="qmo-durum qmo-durum-ok">
								✓ Yapılandırılmış — proje: <code><?php echo esc_html( QMO_Firestore::project_id() ); ?></code>
								<?php if ( defined( 'QMO_FIREBASE_SA_JSON' ) ) : ?>
									(wp-config.php sabitinden okunuyor)
								<?php endif; ?>
							</p>
						<?php else : ?>
							<p class="qmo-durum qmo-durum-eksik">✗ Henüz yapılandırılmadı — sipariş, çağrı ve analitik özellikleri çalışmaz.</p>
						<?php endif; ?>

						<textarea id="qmo_firebase_sa" name="qmo_firebase_sa" rows="6" cols="70"
							placeholder="Değiştirmek için yeni JSON'ı buraya yapıştırın. Boş bırakırsanız mevcut kayıt korunur."></textarea>

						<p class="description">
							<strong>Güvenlik:</strong> Bu JSON Firebase projenize tam yetki verir. En güvenli yöntem,
							dosyayı buraya değil <code>wp-config.php</code> içine koymaktır:<br>
							<code>define( 'QMO_FIREBASE_SA_JSON', '{ ... }' );</code><br>
							Sabit tanımlıysa buradaki alan yok sayılır. Anahtar hiçbir zaman ekranda geri gösterilmez.
						</p>
					</td>
				</tr>
			</table>

			<?php submit_button( 'Kaydet' ); ?>
		</form>
		<?php
		qmo_chatbot_sayfa_bitir();
	}
}

/* -----------------------------------------------------------------
   6) ANA SİTE AYARI
   qmo_ana_site — /create-user ucunun bu sitede açılıp açılmayacağı.
   options.php qmo_branch_id'yi silmesin diye gizli alanla korunur;
   boş qmo_firebase_sa mevcut kaydı korur (qmo_sa_json_temizle).
----------------------------------------------------------------- */

if ( ! function_exists( 'qmo_chatbot_sayfa_ana_site' ) ) {
	function qmo_chatbot_sayfa_ana_site() {
		qmo_chatbot_sayfa_basligi(
			__( 'Site Entegrasyonu', 'qrms' ),
			__( 'Asistanın sitenizde nasıl yayınlanacağı ve bu sitenin merkez site olup olmadığı.', 'qrms' )
		);

		echo '<div class="qmo-cb-kod-kutu">';
		echo '<h2 class="qmo-cb-kod-baslik">' . esc_html__( 'Asistanı sayfaya elle ekleme', 'qrms' ) . '</h2>';
		echo '<p class="qmo-cb-kod-not">' . esc_html__( 'Asistan varsayılan olarak tüm sayfalarda otomatik gösterilir. Yalnızca belirli bir sayfada göstermek isterseniz aşağıdaki kısa kodu o sayfaya ekleyin.', 'qrms' ) . '</p>';
		echo '<div class="qmo-cb-kod-satir">';
		echo '<input type="text" class="qmo-cb-kod-input" id="qmo-cb-kisa-kod" readonly value="[gemini_chatbot]">';
		echo '<button type="button" class="qmo-cb-btn qmo-cb-kopyala" data-kopyala="#qmo-cb-kisa-kod">' . esc_html__( 'Kopyala', 'qrms' ) . '</button>';
		echo '</div></div>';

		settings_errors();

		$grup = defined( 'QMO_FIREBASE_AYAR_GRUBU' ) ? QMO_FIREBASE_AYAR_GRUBU : 'qmo_firebase_grup';
		?>
		<form method="post" action="options.php" id="qmo-firebase-form">
			<?php settings_fields( $grup ); ?>

			<input type="hidden" name="qmo_branch_id" value="<?php echo esc_attr( get_option( 'qmo_branch_id', '' ) ); ?>">

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">Bu site merkez site mi?</th>
					<td>
						<label>
							<input type="checkbox" name="qmo_ana_site" value="1"
								<?php checked( (bool) get_option( 'qmo_ana_site', false ) ); ?> />
							Evet — <code>/wp-json/qrservis/v1/create-user</code> ucunu bu sitede aç
						</label>
						<p class="description">
							Yalnızca merkezi yönetim sitesinde işaretleyin. Şube sitelerinde kapalı kalmalı;
							kapalıyken kullanıcı oluşturma ucu hiç kaydedilmez. (Uç <strong>QR Analiz</strong>
							modülüyle gelir.)
						</p>
					</td>
				</tr>
			</table>

			<?php submit_button( 'Kaydet' ); ?>
		</form>
		<?php
		qmo_chatbot_sayfa_bitir();
	}
}
