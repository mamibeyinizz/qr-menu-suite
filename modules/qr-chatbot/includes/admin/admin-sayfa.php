<?php
/**
 * Yönetim sayfası: QR Menü → QR Chatbot
 *
 * Gemini API, bot metinleri, renk şablonları ve canlı önizleme.
 * Firebase / Firestore alanları chatbot formunun DIŞINDA, ortak
 * `qmo_firebase_ayar_formu()` ile basılır (qr-analiz ile aynı option'lar).
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
		$d      = qmo_renk_varsayilanlari();
		$renkler = array();

		foreach ( array_keys( $d ) as $anahtar ) {
			$deger = get_option( $anahtar, $d[ $anahtar ] );
			$temiz = sanitize_hex_color( $deger );
			$renkler[ $anahtar ] = $temiz ? $temiz : $d[ $anahtar ];
		}

		return $renkler;
	}
}

/**
 * Form gönderimini işler.
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
					'page'    => QRMS_Admin::get_module_page_slug( 'qr-chatbot' ),
					'tab'     => 'yapayzeka',
					'menu_ok' => 1,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}
}

if ( ! function_exists( 'qmo_chatbot_admin_pages' ) ) {
	/**
	 * Chatbot ayar sekmeleri — TEK KAYNAK.
	 *
	 * Hub/Genel Bakış alt listesi ve sayfa üstündeki sekmeler aynı listeden
	 * türer; yeni sekme eklenince ikisi de güncellenir.
	 *
	 * @return array<int,array{url:string,title:string,tab:string}>
	 */
	function qmo_chatbot_admin_pages() {
		$sayfa = class_exists( 'QRMS_Admin' )
			? QRMS_Admin::get_module_page_slug( 'qr-chatbot' )
			: 'qrms-module-qr-chatbot';

		$sekmeler = array(
			'genel'     => __( 'Genel', 'qrms' ),
			'gorunum'   => __( 'Görünüm & Renkler', 'qrms' ),
			'yapayzeka' => __( 'Yapay Zeka', 'qrms' ),
		);

		$liste = array();
		foreach ( $sekmeler as $tab => $title ) {
			$liste[] = array(
				'tab'   => $tab,
				'title' => $title,
				'url'   => admin_url( 'admin.php?page=' . $sayfa . '&tab=' . $tab ),
			);
		}

		return $liste;
	}
}

/**
 * Chatbot ayarları sayfası.
 *
 * @return void
 */
if ( ! function_exists( 'qmo_chatbot_ayar_sayfasi' ) ) {
	function qmo_chatbot_ayar_sayfasi() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Bu sayfaya erişim yetkiniz yok.' );
		}

		$sayfalar = qmo_chatbot_admin_pages();
		$izinli   = array();
		foreach ( $sayfalar as $sayfa ) {
			$izinli[] = $sayfa['tab'];
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$sekme = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'genel';
		if ( ! in_array( $sekme, $izinli, true ) ) {
			$sekme = 'genel';
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['menu_ok'] ) ) {
			add_settings_error( 'qmo_chatbot', 'menu_ok', 'Menü verisi ürünlerden güncellendi.', 'updated' );
		}

		$renkler    = qmo_chatbot_renkleri_oku();
		$sablonlar  = qmo_renk_sablonlari();
		$aktif      = (string) get_option( 'gemini_active_preset', '' );

		$bot_adi     = get_option( 'gemini_bot_name', 'Asistan' );
		$karsilama   = get_option( 'gemini_welcome_text', 'Merhaba! Size nasıl yardımcı olabilirim?' );
		$placeholder = get_option( 'gemini_placeholder_text', 'Bir şeyler sorun...' );
		$ikon_url    = get_option( 'gemini_bot_icon', '' );
		?>
		<div class="wrap qmo-wrap">
			<h1 class="qmo-baslik">QR Chatbot</h1>

			<p class="qmo-aciklama">
				Gemini destekli masa asistanı. Kısa kod: <code>[gemini_chatbot]</code>.
				Asistan yalnızca geçerli masa oturumu olan ziyaretçilere açılır.
			</p>

			<?php settings_errors( 'qmo_chatbot' ); ?>

			<nav class="nav-tab-wrapper wp-clearfix" style="margin-bottom:20px;">
				<?php foreach ( $sayfalar as $sayfa ) : ?>
					<a href="<?php echo esc_url( $sayfa['url'] ); ?>"
						class="nav-tab <?php echo $sayfa['tab'] === $sekme ? 'nav-tab-active' : ''; ?>"><?php echo esc_html( $sayfa['title'] ); ?></a>
				<?php endforeach; ?>
			</nav>

			<form method="post" action="" id="qmo-chatbot-form">
				<?php wp_nonce_field( 'qmo_chatbot_ayar', 'qmo_chatbot_nonce' ); ?>
				<input type="hidden" name="gemini_active_preset" id="gemini_active_preset" value="<?php echo esc_attr( $aktif ); ?>">

				<?php if ( 'genel' === $sekme ) : ?>
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
						<tr>
							<th scope="row"><label for="gemini_bot_name">Bot Adı</label></th>
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
							<th scope="row"><label for="gemini_placeholder_text">Giriş Alanı Placeholder</label></th>
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

				<?php elseif ( 'gorunum' === $sekme ) : ?>
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
							'gemini_toggle_bg_color'       => 'Toggle arkaplan',
							'gemini_toggle_text_color'     => 'Toggle metin',
							'gemini_header_bg_color'       => 'Başlık arkaplan',
							'gemini_header_text_color'     => 'Başlık metin',
							'gemini_header_icon_color'     => 'Başlık ikon',
							'gemini_chat_bg_color'         => 'Sohbet arkaplan',
							'gemini_text_color'            => 'Genel metin',
							'gemini_border_color'          => 'Kenarlık',
							'gemini_user_msg_color'        => 'Kullanıcı balon arkaplan',
							'gemini_user_msg_text_color'   => 'Kullanıcı balon metin',
							'gemini_bot_msg_color'         => 'Bot balon arkaplan',
							'gemini_bot_msg_text_color'    => 'Bot balon metin',
							'gemini_input_bg_color'        => 'Giriş alanı arkaplan',
							'gemini_input_area_bg_color'   => 'Giriş bölümü arkaplan',
							'gemini_send_btn_bg_color'     => 'Gönder butonu arkaplan',
							'gemini_send_btn_icon_color'   => 'Gönder butonu ikon',
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

				<?php else : ?>
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
				<?php endif; ?>

				<?php submit_button( 'Kaydet', 'primary', 'qmo_chatbot_kaydet' ); ?>
			</form>

			<?php
			// Firebase/şube bağlantısı: ortak bölüm, kendi ayar grubu ve kendi
			// formu. Chatbot'un garson/hesap çağrısı ve sipariş yazımı buna
			// bağlıdır (QMO_Firestore bu option'ları okur).
			if ( function_exists( 'qmo_firebase_ayar_formu' ) ) {
				qmo_firebase_ayar_formu();
			}
			?>

			<div id="qmo-toast"></div>
		</div>
		<?php
	}
}
