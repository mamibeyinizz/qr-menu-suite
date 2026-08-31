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

if ( ! defined( 'QMO_CHATBOT_ADMIN_INIT' ) ) {
	define( 'QMO_CHATBOT_ADMIN_INIT', true );

	add_action( 'admin_init', 'qmo_chatbot_ayarlarini_kaydet' );
	add_action( 'admin_post_qmo_chatbot_menu_guncelle', 'qmo_chatbot_menu_guncelle_handler' );
}

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
				update_option( 'gemini_api_key', $api_key );
			}
		}

		if ( isset( $_POST['qmo_gemini_model'] ) ) {
			update_option( 'qmo_gemini_model', sanitize_text_field( wp_unslash( $_POST['qmo_gemini_model'] ) ) );
		}
		if ( isset( $_POST['gemini_bot_name'] ) ) {
			update_option( 'gemini_bot_name', sanitize_text_field( wp_unslash( $_POST['gemini_bot_name'] ) ) );
			update_option( 'gemini_show_toggle_text', empty( $_POST['gemini_show_toggle_text'] ) ? 'no' : 'yes' );
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

		add_settings_error( 'qmo_chatbot', 'kaydedildi', 'Ayarlar kaydedildi.', 'updated' );
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
				'desc'   => __( 'Bot adı, karşılama mesajı, giriş alanı placeholder, toggle buton metni, bot ikonu', 'qrms' ),
				'icon'   => 'dashicons-id',
				'group'  => __( 'Bot', 'qrms' ),
			),
			'qrms-chatbot-appearance'   => array(
				'title'  => __( 'Görünüm & Renkler', 'qrms' ),
				'render' => 'qmo_chatbot_sayfa_gorunum',
				'desc'   => __( 'Sohbet penceresi renkleri, tipografi ve konum ayarları', 'qrms' ),
				'icon'   => 'dashicons-art',
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
				'title'  => __( 'Yapay Zeka Davranışı', 'qrms' ),
				'render' => 'qmo_chatbot_sayfa_davranis',
				'desc'   => __( 'Sistem talimatı ve asistanın davranış ayarları', 'qrms' ),
				'icon'   => 'dashicons-format-status',
				'group'  => __( 'Yapay Zeka', 'qrms' ),
			),
			'qrms-chatbot-firebase'     => array(
				'title'  => __( 'Firebase / Şube Bağlantısı', 'qrms' ),
				'render' => 'qmo_chatbot_sayfa_firebase',
				'desc'   => __( 'Şube kimliği (branchId) ve Service Account JSON', 'qrms' ),
				'icon'   => 'dashicons-cloud',
				'group'  => __( 'Entegrasyon', 'qrms' ),
			),
			'qrms-chatbot-ana-site'     => array(
				'title'  => __( 'Ana Site Ayarı', 'qrms' ),
				'render' => 'qmo_chatbot_sayfa_ana_site',
				'desc'   => __( '/wp-json/qrservis/v1/create-user ucunun açılıp kapatılması', 'qrms' ),
				'icon'   => 'dashicons-admin-site-alt3',
				'group'  => __( 'Entegrasyon', 'qrms' ),
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

		echo '<div class="rma-hub">';
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
			__( 'Bot Kimliği', 'qrms' ),
			__( 'Bot adı, karşılama mesajı, giriş alanı metni, toggle butonu ve ikon.', 'qrms' )
		);

		$bot_adi     = get_option( 'gemini_bot_name', 'Asistan' );
		$karsilama   = get_option( 'gemini_welcome_text', 'Merhaba! Size nasıl yardımcı olabilirim?' );
		$placeholder = get_option( 'gemini_placeholder_text', 'Bir şeyler sorun...' );
		$ikon_url    = get_option( 'gemini_bot_icon', '' );

		qmo_chatbot_form_ac();
		?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="gemini_bot_name">Bot Adı</label></th>
				<td>
					<input type="text" id="gemini_bot_name" name="gemini_bot_name" class="regular-text"
						value="<?php echo esc_attr( $bot_adi ); ?>">
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="gemini_welcome_text">Karşılama Mesajı</label></th>
				<td>
					<textarea id="gemini_welcome_text" name="gemini_welcome_text" rows="3" class="large-text"><?php echo esc_textarea( $karsilama ); ?></textarea>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="gemini_placeholder_text">Giriş Alanı Placeholder</label></th>
				<td>
					<input type="text" id="gemini_placeholder_text" name="gemini_placeholder_text" class="regular-text"
						value="<?php echo esc_attr( $placeholder ); ?>">
				</td>
			</tr>
			<tr>
				<th scope="row">Toggle Buton Metni</th>
				<td>
					<label>
						<input type="checkbox" name="gemini_show_toggle_text" value="yes"
							<?php checked( 'yes', get_option( 'gemini_show_toggle_text', 'yes' ) ); ?>>
						Bot adını toggle butonunda göster
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="gemini_bot_icon">Bot İkonu</label></th>
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

/* -----------------------------------------------------------------
   2) GÖRÜNÜM & RENKLER
----------------------------------------------------------------- */

if ( ! function_exists( 'qmo_chatbot_sayfa_gorunum' ) ) {
	function qmo_chatbot_sayfa_gorunum() {
		qmo_chatbot_sayfa_basligi(
			__( 'Görünüm & Renkler', 'qrms' ),
			__( 'Sohbet penceresi renkleri, boyutlar ve hazır şablonlar.', 'qrms' )
		);

		$renkler   = qmo_chatbot_renkleri_oku();
		$sablonlar = qmo_renk_sablonlari();
		$aktif     = (string) get_option( 'gemini_active_preset', '' );
		$bot_adi   = get_option( 'gemini_bot_name', 'Asistan' );
		$karsilama = get_option( 'gemini_welcome_text', 'Merhaba! Size nasıl yardımcı olabilirim?' );
		$placeholder = get_option( 'gemini_placeholder_text', 'Bir şeyler sorun...' );

		qmo_chatbot_form_ac();
		?>
		<input type="hidden" name="gemini_active_preset" id="gemini_active_preset" value="<?php echo esc_attr( $aktif ); ?>">

		<h2>Canlı Önizleme</h2>
		<div id="qmo-preview-wrap">
			<div id="qmo-preview-header">
				<div id="qmo-preview-header-left">
					<div id="qmo-preview-icon"></div>
					<span id="qmo-preview-title"><?php echo esc_html( $bot_adi ); ?></span>
				</div>
				<span id="qmo-preview-close" aria-hidden="true">&times;</span>
			</div>
			<div id="qmo-preview-log">
				<div id="qmo-preview-bot-bubble" class="qmo-preview-bubble"><?php echo esc_html( $karsilama ); ?></div>
				<div id="qmo-preview-user-bubble" class="qmo-preview-bubble">Örnek kullanıcı mesajı</div>
			</div>
			<div id="qmo-preview-input-area">
				<input type="text" id="qmo-preview-input" readonly value="<?php echo esc_attr( $placeholder ); ?>">
				<div id="qmo-preview-send">
					<svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor" aria-hidden="true"><path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/></svg>
				</div>
			</div>
		</div>

		<h2>Hazır Renk Şablonları</h2>
		<div class="qmo-preset-grid">
			<?php foreach ( $sablonlar as $slug => $sablon ) : ?>
				<div class="qmo-preset-card<?php echo $aktif === $slug ? ' is-active' : ''; ?>"
					data-preset="<?php echo esc_attr( $slug ); ?>">
					<?php if ( $aktif === $slug ) : ?>
						<span class="qmo-active-badge">Aktif</span>
					<?php endif; ?>
					<div class="qmo-preset-swatches">
						<?php foreach ( $sablon['preview'] as $swatch ) : ?>
							<span class="qmo-swatch" style="background:<?php echo esc_attr( $swatch ); ?>;"></span>
						<?php endforeach; ?>
					</div>
					<div class="qmo-preset-info">
						<strong><?php echo esc_html( $sablon['label'] ); ?></strong>
						<p><?php echo esc_html( $sablon['description'] ); ?></p>
					</div>
					<button type="button" class="button qmo-apply-preset" data-preset="<?php echo esc_attr( $slug ); ?>">
						Şablonu Uygula
					</button>
				</div>
			<?php endforeach; ?>
		</div>

		<h2>Boyutlar</h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="gemini_icon_size">İkon Boyutu (px)</label></th>
				<td>
					<input type="number" id="gemini_icon_size" name="gemini_icon_size" min="30" class="small-text"
						value="<?php echo esc_attr( get_option( 'gemini_icon_size', 24 ) ); ?>">
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="gemini_border_radius">Köşe Yuvarlaklığı (px)</label></th>
				<td>
					<input type="number" id="gemini_border_radius" name="gemini_border_radius" min="14" class="small-text"
						value="<?php echo esc_attr( get_option( 'gemini_border_radius', 16 ) ); ?>">
				</td>
			</tr>
		</table>

		<h2>Renk Ayarları</h2>
		<table class="form-table" role="presentation">
			<?php
			$etiketler = array(
				'gemini_toggle_bg_color'     => 'Toggle arkaplan',
				'gemini_toggle_text_color'   => 'Toggle metin',
				'gemini_header_bg_color'     => 'Başlık arkaplan',
				'gemini_header_text_color'   => 'Başlık metin',
				'gemini_header_icon_color'   => 'Başlık ikon',
				'gemini_chat_bg_color'       => 'Sohbet arkaplan',
				'gemini_text_color'          => 'Genel metin',
				'gemini_border_color'        => 'Kenarlık',
				'gemini_user_msg_color'      => 'Kullanıcı balon arkaplan',
				'gemini_user_msg_text_color' => 'Kullanıcı balon metin',
				'gemini_bot_msg_color'       => 'Bot balon arkaplan',
				'gemini_bot_msg_text_color'  => 'Bot balon metin',
				'gemini_input_bg_color'      => 'Giriş alanı arkaplan',
				'gemini_input_area_bg_color' => 'Giriş bölümü arkaplan',
				'gemini_send_btn_bg_color'   => 'Gönder butonu arkaplan',
				'gemini_send_btn_icon_color' => 'Gönder butonu ikon',
			);
			foreach ( $etiketler as $anahtar => $etiket ) :
				$deger = isset( $renkler[ $anahtar ] ) ? $renkler[ $anahtar ] : '';
				?>
				<tr>
					<th scope="row"><label for="<?php echo esc_attr( $anahtar ); ?>"><?php echo esc_html( $etiket ); ?></label></th>
					<td>
						<input type="color" id="<?php echo esc_attr( $anahtar ); ?>" name="<?php echo esc_attr( $anahtar ); ?>"
							class="qmo-color-input" data-color-key="<?php echo esc_attr( $anahtar ); ?>"
							value="<?php echo esc_attr( $deger ); ?>">
						<code><?php echo esc_html( $deger ); ?></code>
					</td>
				</tr>
			<?php endforeach; ?>
		</table>
		<?php
		qmo_chatbot_form_kapat();
		echo '<div id="qmo-toast"></div>';
		qmo_chatbot_sayfa_bitir();
	}
}

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
			__( 'Yapay Zeka Davranışı', 'qrms' ),
			__( 'Sistem talimatı ve asistanın menü sorularında kullanacağı veri.', 'qrms' )
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
