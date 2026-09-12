<?php
/**
 * Yönetim sayfası: QR Menü → QR Chatbot
 *
 * Hub ekranı Restoran Menü ile AYNI kart bileşenini kullanır
 * (QRMS_Admin::render_hub + .rma-hub). Form alanları kendi alt
 * sayfalarındadır; option key'leri ve name attribute'ları değişmez.
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

		add_settings_error( 'qmo_chatbot', 'kaydedildi', __( '✓ Değişiklikler kaydedildi.', 'qrms' ), 'updated' );
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
				'title'  => __( 'Bot Kimliği', 'qrms' ),
				'render' => 'qmo_chatbot_sayfa_bot_kimligi',
				'desc'   => __( 'Bot adı, karşılama mesajı, kutu içi ipucu ve açma butonu yazısı.', 'qrms' ),
				'icon'   => 'dashicons-id',
				'group'  => __( 'Bot', 'qrms' ),
			),
			'qrms-chatbot-appearance'   => array(
				'title'  => __( 'Görünüm', 'qrms' ),
				'render' => 'qmo_chatbot_sayfa_gorunum',
				'desc'   => __( 'İkon, renk, şekil ve karşılama ekranı.', 'qrms' ),
				'icon'   => 'dashicons-art',
				'group'  => __( 'Bot', 'qrms' ),
			),
			'qrms-chatbot-quick-replies' => array(
				'title'  => __( 'Hazır Sorular', 'qrms' ),
				'render' => 'qmo_chatbot_sayfa_sorular',
				'desc'   => __( 'Sohbet açılınca çıkan tıklanabilir soru butonları.', 'qrms' ),
				'icon'   => 'dashicons-format-chat',
				'group'  => __( 'Bot', 'qrms' ),
			),
			'qrms-chatbot-visibility'   => array(
				'title'  => __( 'Görünürlük', 'qrms' ),
				'render' => 'qmo_chatbot_sayfa_gorunurluk',
				'desc'   => __( 'Kimlere, hangi cihazda ve çalışma saatleri dışında nasıl görünsün.', 'qrms' ),
				'icon'   => 'dashicons-visibility',
				'group'  => __( 'Bot', 'qrms' ),
			),
			'qrms-chatbot-gemini'       => array(
				'title'  => __( 'Gemini Bağlantısı', 'qrms' ),
				'render' => 'qmo_chatbot_sayfa_gemini',
				'desc'   => __( 'API anahtarı ve model seçimi', 'qrms' ),
				'icon'   => 'dashicons-admin-network',
				'group'  => __( 'Yapay Zeka', 'qrms' ),
			),
			QMO_CHATBOT_AI_SAYFA        => array(
				'title'  => __( 'Yapay Zeka', 'qrms' ),
				'render' => 'qmo_chatbot_sayfa_davranis',
				'desc'   => __( 'Sistem talimatı, menü verisi, kullanım sınırı ve güvenlik.', 'qrms' ),
				'icon'   => 'dashicons-format-status',
				'group'  => __( 'Yapay Zeka', 'qrms' ),
			),
			'qrms-chatbot-firebase'     => array(
				'title'  => __( 'Firebase / Şube Bağlantısı', 'qrms' ),
				'render' => 'qmo_chatbot_sayfa_firebase',
				'desc'   => __( 'Şube kimliği ve servis hesabı.', 'qrms' ),
				'icon'   => 'dashicons-cloud',
				'group'  => __( 'Entegrasyon', 'qrms' ),
			),
			'qrms-chatbot-ana-site'     => array(
				'title'  => __( 'Ana Site Ayarı', 'qrms' ),
				'render' => 'qmo_chatbot_sayfa_ana_site',
				'desc'   => __( 'Kullanıcı oluşturma ucunun bu sitede açılıp kapatılması.', 'qrms' ),
				'icon'   => 'dashicons-admin-site-alt3',
				'group'  => __( 'Entegrasyon', 'qrms' ),
			),
			'qrms-chatbot-history'      => array(
				'title'  => __( 'Sohbet Geçmişi', 'qrms' ),
				'render' => 'qmo_chatbot_sayfa_gecmis',
				'desc'   => __( 'Tarih, masa, soru ve cevap kayıtları.', 'qrms' ),
				'icon'   => 'dashicons-backup',
				'group'  => __( 'Yönetim', 'qrms' ),
			),
			'qrms-chatbot-canli'        => array(
				'title'  => __( 'Canlı Sohbetler', 'qrms' ),
				'render' => 'qmo_chatbot_sayfa_canli',
				'desc'   => __( 'Eskalasyon alan sohbetleri devral, müşteriye doğrudan yaz.', 'qrms' ),
				'icon'   => 'dashicons-format-chat',
				'group'  => __( 'Yönetim', 'qrms' ),
			),
			'qrms-chatbot-unanswered'   => array(
				'title'  => __( 'Cevaplanamayan Sorular', 'qrms' ),
				'render' => 'qmo_chatbot_sayfa_cevaplanamayan',
				'desc'   => __( 'Asistanın bilemediği sorular, tekrar sayısıyla.', 'qrms' ),
				'icon'   => 'dashicons-flag',
				'group'  => __( 'Yönetim', 'qrms' ),
			),
			'qrms-chatbot-oneri'        => array(
				'title'  => __( 'Öneri Yönetimi', 'qrms' ),
				'render' => 'qmo_chatbot_sayfa_oneri',
				'desc'   => __( 'Önerilecek ürünler ve birlikte öneri kuralları.', 'qrms' ),
				'icon'   => 'dashicons-megaphone',
				'group'  => __( 'Yönetim', 'qrms' ),
			),
			'qrms-chatbot-oneri-rapor'  => array(
				'title'  => __( 'Öneri Raporu', 'qrms' ),
				'render' => 'qmo_chatbot_sayfa_oneri_rapor',
				'desc'   => __( 'Öneri, sepet ve sipariş dönüşüm istatistikleri.', 'qrms' ),
				'icon'   => 'dashicons-chart-bar',
				'group'  => __( 'Yönetim', 'qrms' ),
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

if ( ! function_exists( 'qmo_chatbot_hub_kartlari' ) ) {
	/**
	 * Hub ekranındaki kart grupları — Restoran Menü get_hub_cards() deseni.
	 *
	 * @return array<int,array{title:string,cards:array<int,array<string,string>>}>
	 */
	function qmo_chatbot_hub_kartlari() {
		$gruplar = array();

		foreach ( qmo_chatbot_sayfalar() as $slug => $page ) {
			$grup = $page['group'];

			if ( ! isset( $gruplar[ $grup ] ) ) {
				$gruplar[ $grup ] = array(
					'title' => $grup,
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
				$hazir = class_exists( 'QMO_Firestore' ) && QMO_Firestore::hazir_mi();
				if ( $hazir ) {
					$kart['badge'] = '✓ Yapılandırılmış';
				} else {
					$kart['badge'] = '✗ Henüz yapılandırılmadı';
				}
			}

			$gruplar[ $grup ]['cards'][] = $kart;
		}

		return array_values( $gruplar );
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

		$acik = qmo_chatbot_aktif_mi();
		$wrap = $acik ? 'rma-hub' : 'rma-hub qmo-cb-hub-kapali';

		echo '<div class="' . esc_attr( $wrap ) . '">';
		echo '<div class="qmo-cb-master">';
		echo '<button type="button" class="qmo-cb-switch" id="qmo-cb-hub-switch"';
		echo ' aria-pressed="' . ( $acik ? 'true' : 'false' ) . '"';
		echo ' data-nonce="' . esc_attr( wp_create_nonce( 'qmo_chatbot_toggle' ) ) . '">';
		echo '<span class="qmo-cb-switch-track" aria-hidden="true"><span class="qmo-cb-switch-thumb"></span></span>';
		echo '<span class="qmo-cb-switch-label">' . esc_html__( 'Sohbet Asistanı', 'qrms' ) . ' — ';
		echo '<strong class="qmo-cb-switch-state">' . ( $acik ? esc_html__( 'Açık', 'qrms' ) : esc_html__( 'Kapalı', 'qrms' ) ) . '</strong>';
		echo '</span></button>';
		echo '<p class="qmo-cb-master-note"' . ( $acik ? ' hidden' : '' ) . '>';
		echo esc_html__( 'Asistan şu an kapalı, sitede görünmüyor.', 'qrms' );
		echo '</p></div>';

		QRMS_Admin::render_hub(
			array(
				'title'       => 'QR Chatbot',
				'intro'       => 'Gemini destekli masa asistanı. Kısa kod: [gemini_chatbot]',
				'accent'      => '#c9a84c',
				'card_groups' => qmo_chatbot_hub_kartlari(),
			)
		);
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
		echo 'QR Chatbot';
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
		echo '<div class="qmo-cb-save-bar">';
		submit_button( __( 'Değişiklikleri Kaydet', 'qrms' ), 'primary qmo-cb-save-btn', 'qmo_chatbot_kaydet', false );
		echo '<span class="qmo-cb-save-state" aria-live="polite"></span>';
		echo '</div>';
		echo '</form>';
		qmo_chatbot_kaydet_ux_script();
	}
}

/**
 * "Kaydediliyor…" geçiş durumu — form gönderilirken düğmeyi kilitler.
 * Tam sayfa POST olduğu için yalnızca geçiş anını kaplar; sonuç mesajı
 * zaten mevcut settings_errors() akışından gelir.
 *
 * @return void
 */
if ( ! function_exists( 'qmo_chatbot_kaydet_ux_script' ) ) {
	function qmo_chatbot_kaydet_ux_script() {
		static $basildi = false;
		if ( $basildi ) {
			return;
		}
		$basildi = true;
		?>
		<script>
		( function () {
			document.querySelectorAll( '.qmo-cb-save-bar' ).forEach( function ( bar ) {
				var form  = bar.closest( 'form' );
				var btn   = bar.querySelector( '.qmo-cb-save-btn' );
				var state = bar.querySelector( '.qmo-cb-save-state' );
				if ( ! form || ! btn || ! state ) {
					return;
				}
				form.addEventListener( 'submit', function () {
					btn.setAttribute( 'disabled', 'disabled' );
					state.textContent = '<?php echo esc_js( __( 'Kaydediliyor…', 'qrms' ) ); ?>';
				} );
			} );

			// Kompakt çeviri rozeti: alan orijinal değerinden farklılaştığında
			// "mevcut" rozetini "değişti" uyarısına çevirir (bkz.
			// qmo_chatbot_i18n_wrap_*()). Bu betik her alt sayfada ortak
			// basıldığı için sonradan eklenen alanlar da (ör. yeni hazır soru
			// kartları) olay delegasyonuyla kapsanır.
			document.addEventListener( 'input', function ( e ) {
				var wrap = e.target.closest( '.qmo-cb-i18n-wrap' );
				if ( ! wrap ) {
					return;
				}
				var orijinal = wrap.getAttribute( 'data-i18n-original' ) || '';
				wrap.classList.toggle( 'is-dirty', e.target.value !== orijinal );
			} );
		}() );
		</script>
		<?php
	}
}

/* -----------------------------------------------------------------
   KOMPAKT ÇEVİRİ DURUMU — büyük sarı notice yerine (fonksiyonalite aynı,
   yalnızca ekran görünümü). Alttaki .rma-ceviri-* çeviri altyapısına
   dokunmaz; yalnızca rma_ceviri_veri_dil_sayisi() ile SAYIYI okur.
----------------------------------------------------------------- */

/**
 * Kompakt çeviri rozeti HTML'i — dil sayısı 0 ise boş döner.
 *
 * @param string $field rma_ceviri_veri_dil_sayisi() alan anahtarı.
 * @return string
 */
if ( ! function_exists( 'qmo_chatbot_ceviri_rozet' ) ) {
	function qmo_chatbot_ceviri_rozet( $field ) {
		if ( ! function_exists( 'rma_ceviri_veri_dil_sayisi' ) ) {
			return '';
		}
		$adet = (int) rma_ceviri_veri_dil_sayisi( 'option', 0, $field );
		if ( $adet < 1 ) {
			return '';
		}

		$fresh = sprintf(
			/* translators: %d: çevirili dil sayısı. */
			__( '🌐 Çeviri mevcut (%d dil)', 'qrms' ),
			$adet
		);
		$stale = __( '⚠ Bu metin değiştirildi. Mevcut çevirilerin güncellenmesi gerekebilir.', 'qrms' );

		return '<p class="qmo-cb-i18n-badge">'
			. '<span class="qmo-cb-i18n-fresh">' . esc_html( $fresh ) . '</span>'
			. '<span class="qmo-cb-i18n-stale">' . esc_html( $stale ) . '</span>'
			. '</p>';
	}
}

/**
 * Çeviri rozetli alan sarmalayıcısını açar. JS bu wrapper'daki
 * data-i18n-original ile alanın güncel değerini karşılaştırıp
 * "mevcut" / "değişti" rozetleri arasında geçiş yapar.
 *
 * @param string $orijinal_deger Alanın kayıtlı (sayfa yüklenirkenki) değeri.
 * @return void
 */
if ( ! function_exists( 'qmo_chatbot_i18n_wrap_ac' ) ) {
	function qmo_chatbot_i18n_wrap_ac( $orijinal_deger ) {
		echo '<div class="qmo-cb-i18n-wrap" data-i18n-original="' . esc_attr( $orijinal_deger ) . '">';
	}
}

/**
 * Çeviri rozetli alan sarmalayıcısını rozetle birlikte kapatır.
 *
 * @param string $field rma_ceviri_veri_dil_sayisi() alan anahtarı.
 * @return void
 */
if ( ! function_exists( 'qmo_chatbot_i18n_wrap_kapat' ) ) {
	function qmo_chatbot_i18n_wrap_kapat( $field ) {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- qmo_chatbot_ceviri_rozet() zaten esc_html ile üretir.
		echo qmo_chatbot_ceviri_rozet( $field );
		echo '</div>';
	}
}

/* -----------------------------------------------------------------
   PAYLAŞILAN CANLI ÖNİZLEME — Asistan Profili, Görünüm ve Hazır Sorular
   sayfaları aynı işaretlemeyi kullanır (assets/js/admin-chatbot.js bu
   DOM'u okuyup --gm-* değişkenlerini yazar). chatbot.css'teki gemini-*
   sınıflarına bağımlıdır; sınıf adları ön yüzle birebir aynı kalmalı.
----------------------------------------------------------------- */

/**
 * Canlı önizleme aracı çubuğu + sahte chatbot penceresi.
 *
 * @return void
 */
if ( ! function_exists( 'qmo_chatbot_onizleme_blogu' ) ) {
	function qmo_chatbot_onizleme_blogu() {
		$bot_adi      = get_option( 'gemini_bot_name', 'Asistan' );
		$karsilama    = get_option( 'gemini_welcome_text', 'Merhaba! Size nasıl yardımcı olabilirim?' );
		$ipucu        = get_option( 'gemini_placeholder_text', 'Bir şeyler sorun...' );
		$ikon_url     = get_option( 'gemini_bot_icon', '' );
		$preset       = (string) qmo_chatbot_ayar( 'qmo_chatbot_icon_preset' );
		$konum        = (string) qmo_chatbot_ayar( 'qmo_chatbot_position' );
		$hareket      = (string) qmo_chatbot_ayar( 'qmo_chatbot_attention' );
		$rozet_on     = 'yes' === qmo_chatbot_ayar( 'qmo_chatbot_badge' );
		$teaser_on    = 'yes' === qmo_chatbot_ayar( 'qmo_chatbot_teaser' );
		$teaser_metin = (string) qmo_chatbot_ayar( 'qmo_chatbot_teaser_text' );
		$ekran_on     = 'yes' === qmo_chatbot_ayar( 'qmo_chatbot_welcome_screen' );
		$giris_metin  = (string) qmo_chatbot_ayar( 'qmo_chatbot_welcome_intro' );
		$basla_metin  = (string) qmo_chatbot_ayar( 'qmo_chatbot_welcome_btn' );
		$metin_goster = 'yes' === get_option( 'gemini_show_toggle_text', 'no' );
		$konum_sinif  = 'left' === $konum ? 'gm-pos-left' : 'gm-pos-right';
		$attn_map     = array(
			'pulse' => 'gm-attn-pulse',
			'shake' => 'gm-attn-shake',
			'float' => 'gm-attn-float',
		);
		$attn_sinif   = isset( $attn_map[ $hareket ] ) ? $attn_map[ $hareket ] : '';
		$sorular      = function_exists( 'qmo_chatbot_sorulari_aktif' ) ? qmo_chatbot_sorulari_aktif() : array();

		if ( 'custom' === $preset && $ikon_url ) {
			$onizleme_ikon = '<img src="' . esc_url( $ikon_url ) . '" alt="" />';
		} else {
			$onizleme_ikon = wp_kses( qmo_chatbot_ikon_svg( $preset ), qmo_svg_kses() );
		}
		?>
		<aside class="qmo-cb-preview-col" aria-label="<?php esc_attr_e( 'Canlı önizleme', 'qrms' ); ?>">
			<p class="qmo-cb-preview-title"><?php esc_html_e( 'Canlı Önizleme', 'qrms' ); ?></p>
			<div class="qmo-cb-preview-toolbar">
				<div class="qmo-cb-seg" role="tablist" aria-label="<?php esc_attr_e( 'Cihaz', 'qrms' ); ?>">
					<button type="button" class="is-active" data-preview-device="phone" aria-pressed="true"><?php esc_html_e( 'Telefon', 'qrms' ); ?></button>
					<button type="button" data-preview-device="desktop" aria-pressed="false"><?php esc_html_e( 'Masaüstü', 'qrms' ); ?></button>
				</div>
				<div class="qmo-cb-seg" role="tablist" aria-label="<?php esc_attr_e( 'Durum', 'qrms' ); ?>">
					<button type="button" class="is-active" data-preview-state="closed" aria-pressed="true"><?php esc_html_e( 'Kapalı', 'qrms' ); ?></button>
					<button type="button" data-preview-state="open" aria-pressed="false"><?php esc_html_e( 'Açık', 'qrms' ); ?></button>
				</div>
			</div>
			<div id="qmo-cb-live" class="qmo-cb-live is-phone is-closed">
				<div class="qmo-cb-live-stage">
					<div id="qmo-cb-preview-root" class="gemini-shortcode-container <?php echo esc_attr( $konum_sinif ); ?>">
						<div class="gemini-teaser" <?php echo $teaser_on ? '' : 'hidden'; ?>>
							<button type="button" class="gemini-teaser-kapat" aria-label="<?php esc_attr_e( 'Kapat', 'qrms' ); ?>">&times;</button>
							<span data-preview-teaser-text><?php echo esc_html( $teaser_metin ); ?></span>
						</div>

						<div class="gemini-chat-toggle-btn<?php echo $attn_sinif ? ' ' . esc_attr( $attn_sinif ) : ''; ?>" role="button" tabindex="0" aria-label="<?php echo esc_attr( $bot_adi ); ?>">
							<span class="gm-attn-core">
								<span class="gm-attn-ring" aria-hidden="true"></span>
								<div class="gemini-icon-wrapper" data-preview-icon><?php echo $onizleme_ikon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG kses / esc_url. ?></div>
							</span>
							<span class="gemini-unread-badge" <?php echo $rozet_on ? '' : 'hidden'; ?>>1</span>
							<span class="gemini-toggle-label" data-preview-toggle-label <?php echo $metin_goster ? '' : 'hidden'; ?>><?php echo esc_html( $bot_adi ); ?></span>
						</div>

						<div class="gemini-chat-overlay <?php echo esc_attr( $konum_sinif ); ?>">
							<div class="gemini-chat-header">
								<div class="gemini-chat-header-left">
									<div class="gemini-icon-wrapper" data-preview-icon><?php echo $onizleme_ikon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG kses / esc_url. ?></div>
									<div class="gemini-header-textblock">
										<span data-preview-bot-name><?php echo esc_html( $bot_adi ); ?></span>
										<span class="gemini-header-status"><?php esc_html_e( 'Çevrimiçi', 'qrms' ); ?></span>
									</div>
								</div>
								<button type="button" class="gemini-chat-close" aria-label="<?php esc_attr_e( 'Kapat', 'qrms' ); ?>">&times;</button>
							</div>

							<div class="gemini-welcome-screen" <?php echo $ekran_on ? '' : 'hidden'; ?>>
								<div class="gemini-icon-wrapper" data-preview-icon><?php echo $onizleme_ikon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG kses / esc_url. ?></div>
								<strong data-preview-bot-name><?php echo esc_html( $bot_adi ); ?></strong>
								<p data-preview-welcome-text><?php echo esc_html( $giris_metin ); ?></p>
								<button type="button" class="gemini-welcome-start" data-preview-welcome-btn><?php echo esc_html( $basla_metin ); ?></button>
							</div>

							<div class="gemini-chat-log" <?php echo $ekran_on ? 'hidden' : ''; ?>>
								<div class="gemini-msg-bubble gemini-msg-bot" data-preview-welcome><?php echo esc_html( $karsilama ); ?></div>
								<div class="gemini-msg-bubble gemini-msg-user"><?php esc_html_e( 'Örnek kullanıcı mesajı', 'qrms' ); ?></div>
							</div>

							<div class="gemini-quick-replies" id="qmo-cb-preview-quick-replies" <?php echo $ekran_on ? 'hidden' : ''; ?>>
								<?php foreach ( $sorular as $soru ) : ?>
									<button type="button" class="gemini-quick-reply"><?php echo esc_html( $soru['label'] ); ?></button>
								<?php endforeach; ?>
							</div>

							<div class="gemini-chat-input-area" <?php echo $ekran_on ? 'hidden' : ''; ?>>
								<input type="text" class="gemini-chat-input" readonly value="<?php echo esc_attr( $ipucu ); ?>" data-preview-placeholder>
								<button type="button" class="gemini-chat-send" aria-label="<?php esc_attr_e( 'Gönder', 'qrms' ); ?>">
									<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/></svg>
								</button>
							</div>
						</div>
					</div>
				</div>
			</div>
		</aside>
		<?php
	}
}

/* -----------------------------------------------------------------
   1) BOT KİMLİĞİ → ASİSTAN PROFİLİ
----------------------------------------------------------------- */

if ( ! function_exists( 'qmo_chatbot_sayfa_bot_kimligi' ) ) {
	function qmo_chatbot_sayfa_bot_kimligi() {
		qmo_chatbot_sayfa_basligi(
			__( 'Asistan Profili', 'qrms' ),
			__( 'Müşterilerinizin karşısında görünecek asistanın adını ve iletişim dilini belirleyin.', 'qrms' )
		);

		$bot_adi     = get_option( 'gemini_bot_name', 'Asistan' );
		$karsilama   = get_option( 'gemini_welcome_text', 'Merhaba! Size nasıl yardımcı olabilirim?' );
		$placeholder = get_option( 'gemini_placeholder_text', 'Bir şeyler sorun...' );
		$ikon_url    = get_option( 'gemini_bot_icon', '' );
		$preset      = (string) qmo_chatbot_ayar( 'qmo_chatbot_icon_preset' );
		$ikon_ozel   = 'custom' === $preset;

		qmo_chatbot_form_ac();
		?>
		<input type="hidden" name="qmo_chatbot_icon_preset" id="qmo_chatbot_icon_preset" value="<?php echo esc_attr( $preset ); ?>">

		<div class="qmo-cb-wizard">
			<div class="qmo-cb-wizard-main">
				<section class="qmo-cb-card">
					<h2 class="qmo-cb-card-title"><?php esc_html_e( 'Asistan Kimliği', 'qrms' ); ?></h2>

					<div class="qmo-cb-field">
						<label for="gemini_bot_name"><?php esc_html_e( 'Asistan Adı', 'qrms' ); ?></label>
						<p class="qmo-cb-field-desc"><?php esc_html_e( 'Müşterileriniz sohbet ekranında bu adı görecek.', 'qrms' ); ?></p>
						<?php qmo_chatbot_i18n_wrap_ac( $bot_adi ); ?>
						<input type="text" id="gemini_bot_name" name="gemini_bot_name" class="regular-text"
							placeholder="<?php esc_attr_e( 'Örn. Mekanım Asistanı', 'qrms' ); ?>"
							value="<?php echo esc_attr( $bot_adi ); ?>">
						<?php qmo_chatbot_i18n_wrap_kapat( 'gemini_bot_name' ); ?>
					</div>

					<div class="qmo-cb-field">
						<label for="gemini_welcome_text"><?php esc_html_e( 'Karşılama Mesajı', 'qrms' ); ?></label>
						<p class="qmo-cb-field-desc"><?php esc_html_e( 'Müşteriler sohbeti açtığında göreceği ilk mesajı yazın.', 'qrms' ); ?></p>
						<?php qmo_chatbot_i18n_wrap_ac( $karsilama ); ?>
						<textarea id="gemini_welcome_text" name="gemini_welcome_text" rows="3" class="large-text"
							placeholder="<?php esc_attr_e( 'Merhaba! 👋 Menümüz hakkında size nasıl yardımcı olabilirim?', 'qrms' ); ?>"><?php echo esc_textarea( $karsilama ); ?></textarea>
						<?php qmo_chatbot_i18n_wrap_kapat( 'gemini_welcome_text' ); ?>
					</div>

					<div class="qmo-cb-field">
						<label for="gemini_placeholder_text"><?php esc_html_e( 'Mesaj Kutusu İpucu', 'qrms' ); ?></label>
						<p class="qmo-cb-field-desc"><?php esc_html_e( 'Müşteri henüz mesaj yazmadığında giriş alanında gösterilecek kısa metin.', 'qrms' ); ?></p>
						<?php qmo_chatbot_i18n_wrap_ac( $placeholder ); ?>
						<input type="text" id="gemini_placeholder_text" name="gemini_placeholder_text" class="regular-text"
							placeholder="<?php esc_attr_e( 'Menü hakkında bir şey sorun...', 'qrms' ); ?>"
							value="<?php echo esc_attr( $placeholder ); ?>">
						<?php qmo_chatbot_i18n_wrap_kapat( 'gemini_placeholder_text' ); ?>
					</div>

					<div class="qmo-cb-field">
						<span class="qmo-cb-field-label-static"><?php esc_html_e( 'Açma Butonu', 'qrms' ); ?></span>
						<?php qmo_chatbot_option_ac_kapa( 'gemini_show_toggle_text', __( 'Asistan adını butonda göster', 'qrms' ) ); ?>
					</div>
				</section>

				<section class="qmo-cb-card">
					<h2 class="qmo-cb-card-title"><?php esc_html_e( 'Asistan İkonu', 'qrms' ); ?></h2>
					<p class="qmo-cb-field-desc"><?php esc_html_e( 'Müşterilerinize göstermek istediğiniz görseli seçin.', 'qrms' ); ?></p>

					<div class="qmo-cb-icon-picker">
						<div class="qmo-cb-icon-picker-preview" data-preview-icon-thumb>
							<?php if ( $ikon_ozel && $ikon_url ) : ?>
								<img src="<?php echo esc_url( $ikon_url ); ?>" alt="">
							<?php else : ?>
								<?php echo wp_kses( qmo_chatbot_ikon_svg( $preset ), qmo_svg_kses() ); ?>
							<?php endif; ?>
						</div>
						<div class="qmo-cb-icon-picker-actions">
							<button type="button" class="button button-primary" id="qmo-icon-upload"><?php esc_html_e( 'Medya Kütüphanesinden Seç', 'qrms' ); ?></button>
							<p class="qmo-cb-field-desc">
								<?php
								printf(
									/* translators: %s: Asistan Görünümü sayfası linki. */
									esc_html__( 'Hazır ikon galerisinden seçmek isterseniz %s sayfasını kullanın.', 'qrms' ),
									'<a href="' . esc_url( admin_url( 'admin.php?page=qrms-chatbot-appearance' ) ) . '">' . esc_html__( 'Asistan Görünümü', 'qrms' ) . '</a>'
								);
								?>
							</p>
						</div>
					</div>

					<details class="qmo-cb-advanced">
						<summary><?php esc_html_e( 'Gelişmiş: görsel adresini elle girin', 'qrms' ); ?></summary>
						<div class="qmo-cb-field">
							<label for="gemini_bot_icon"><?php esc_html_e( 'Görsel adresi', 'qrms' ); ?></label>
							<input type="url" id="gemini_bot_icon" name="gemini_bot_icon" class="regular-text"
								value="<?php echo esc_url( $ikon_url ); ?>" placeholder="https://...">
						</div>
					</details>
				</section>
			</div>

			<?php qmo_chatbot_onizleme_blogu(); ?>
		</div>
		<?php
		qmo_chatbot_form_kapat();
		echo '<div id="qmo-toast" aria-live="polite"></div>';
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
			__( 'Gemini Bağlantısı', 'qrms' ),
			__( 'API anahtarı ve model seçimi.', 'qrms' )
		);

		qmo_chatbot_form_ac();
		?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="gemini_api_key">Gemini API Anahtarı</label></th>
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
				<th scope="row"><label for="qmo_gemini_model">Gemini Modeli</label></th>
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
			__( 'Yapay Zeka', 'qrms' ),
			__( 'Sistem talimatı, menü verisi, kullanım sınırı ve güvenlik.', 'qrms' )
		);

		qmo_chatbot_form_ac();
		?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="gemini_system_prompt">Sistem Talimatı</label></th>
				<td>
					<textarea id="gemini_system_prompt" name="gemini_system_prompt" rows="10" class="large-text code"><?php echo esc_textarea( get_option( 'gemini_system_prompt', '' ) ); ?></textarea>
					<p class="description">
						Asistanın genel davranışını tanımlar. Sipariş, garson ve menü kuralları
						otomatik olarak eklenir.
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="gemini_menu_json_data">Menü Verisi (JSON)</label></th>
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
			__( 'Firebase / Şube Bağlantısı', 'qrms' ),
			__( 'Şube kimliği ve Service Account JSON — garson/hesap çağrısı ile sipariş yazımı buna bağlıdır.', 'qrms' )
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
					<th scope="row"><label for="qmo_branch_id">Şube Kimliği (branchId)</label></th>
					<td>
						<input type="text" id="qmo_branch_id" name="qmo_branch_id"
							value="<?php echo esc_attr( get_option( 'qmo_branch_id', '' ) ); ?>" class="regular-text" />
						<p class="description">Garson/hesap çağrıları, siparişler ve analitik sorguları bu şube kimliğiyle eşleşir.</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="qmo_firebase_sa">Service Account JSON</label></th>
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
			__( 'Ana Site Ayarı', 'qrms' ),
			__( 'Kullanıcı oluşturma REST ucunun bu sitede açılıp açılmayacağı.', 'qrms' )
		);

		settings_errors();

		$grup = defined( 'QMO_FIREBASE_AYAR_GRUBU' ) ? QMO_FIREBASE_AYAR_GRUBU : 'qmo_firebase_grup';
		?>
		<form method="post" action="options.php" id="qmo-firebase-form">
			<?php settings_fields( $grup ); ?>

			<input type="hidden" name="qmo_branch_id" value="<?php echo esc_attr( get_option( 'qmo_branch_id', '' ) ); ?>">

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">Bu site ana site mi?</th>
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
