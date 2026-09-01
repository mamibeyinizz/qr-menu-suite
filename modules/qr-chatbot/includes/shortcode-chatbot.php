<?php
/**
 * Kısa kod: [gemini_chatbot] — tam ekran AI asistan.
 *
 * OTURUM KOŞULU: Chatbot yalnızca geçerli bir masa oturumu varken render
 * edilir. Oturum yoksa kısa kod "masadaki QR'ı okutun" bilgi kutusu basılır;
 * wp_footer otomatik enjeksiyonu ise sessiz kalır (tanıtım sayfalarına uyarı basmaz).
 * Koşul SAYFA bazlı değil OTURUM bazlıdır — böylece menü hangi sayfada
 * olursa olsun aynı kural işler.
 *
 * @package QR_Menu_Official
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_shortcode( 'gemini_chatbot', 'qmo_chatbot_shortcode' );

/**
 * Bu istekte chatbot HTML'i basıldı mı?
 *
 * Kısa kod + wp_footer otomatik enjeksiyonu aynı sayfada çakışmasın diye
 * shortcode bir kez çıktı verdikten sonra bayrak kalkar. $ata verilirse
 * bayrağı yazar; null yalnızca okur.
 *
 * @param bool|null $ata true/false atar, null okur.
 * @return bool
 */
if ( ! function_exists( 'qmo_chatbot_istekte_basildi' ) ) {
	function qmo_chatbot_istekte_basildi( $ata = null ) {
		static $basildi = false;
		if ( null !== $ata ) {
			$basildi = (bool) $ata;
		}
		return $basildi;
	}
}

/**
 * Chatbot çıktısı.
 *
 * @return string
 */
if ( ! function_exists( 'qmo_chatbot_shortcode' ) ) {
	function qmo_chatbot_shortcode() {
		if ( qmo_chatbot_istekte_basildi() ) {
			return '';
		}

		$html = qmo_chatbot_html_uret( 'shortcode' );
		if ( '' !== $html ) {
			qmo_chatbot_istekte_basildi( true );
		}
		return $html;
	}
}

/**
 * Chatbot HTML'ini üretir.
 *
 * @param string $baglam 'shortcode' | 'footer' — otomatik enjeksiyonda oturum
 *                       yokken QR uyarısı basılmaz (sessiz kalır).
 * @return string
 */
if ( ! function_exists( 'qmo_chatbot_html_uret' ) ) {
	function qmo_chatbot_html_uret( $baglam = 'shortcode' ) {
		if ( function_exists( 'qmo_chatbot_onyuz_yuklensin_mi' ) && ! qmo_chatbot_onyuz_yuklensin_mi() ) {
			return '';
		}

		$oturum_zorunlu = ! function_exists( 'qmo_chatbot_oturum_zorunlu_mu' ) || qmo_chatbot_oturum_zorunlu_mu();
		if ( $oturum_zorunlu && ! qmo_oturum() ) {
			if ( 'footer' === $baglam ) {
				return '';
			}
			$uyari = __( 'Asistanı kullanmak için masanızdaki QR kodu okutun.', 'qrms' );
			return qmo_oturum_uyari_kutusu(
				function_exists( 'qmo_ceviri_chat' ) ? qmo_ceviri_chat( $uyari ) : $uyari
			);
		}

		qmo_asset_enqueue( 'qmo-chatbot' );

		$bot_adi     = get_option( 'gemini_bot_name', 'Asistan' );
		$placeholder = get_option( 'gemini_placeholder_text', 'Bir şeyler sorun...' );
		$karsilama   = get_option( 'gemini_welcome_text', 'Merhaba! Size nasıl yardımcı olabilirim?' );
		if ( '' === trim( (string) $bot_adi ) ) {
			$bot_adi = 'Asistan';
		}
		if ( '' === trim( (string) $placeholder ) ) {
			$placeholder = 'Bir şeyler sorun...';
		}
		if ( '' === trim( (string) $karsilama ) ) {
			$karsilama = 'Merhaba! Size nasıl yardımcı olabilirim?';
		}
		if ( function_exists( 'rma_ceviri_option' ) ) {
			$bot_adi     = rma_ceviri_option( 'gemini_bot_name', $bot_adi );
			$placeholder = rma_ceviri_option( 'gemini_placeholder_text', $placeholder );
			$karsilama   = rma_ceviri_option( 'gemini_welcome_text', $karsilama );
		}
		$metin_goster = 'yes' === get_option( 'gemini_show_toggle_text', 'yes' );
		$ikon_url     = get_option( 'gemini_bot_icon', '' );
		$cihaz        = function_exists( 'qmo_chatbot_ayar' ) ? qmo_chatbot_ayar( 'qmo_chatbot_devices' ) : 'both';
		$mesai_disi   = function_exists( 'qmo_chatbot_mesai_disi_mi' ) && qmo_chatbot_mesai_disi_mi();
		$kapali_dav   = function_exists( 'qmo_chatbot_ayar' ) ? qmo_chatbot_ayar( 'qmo_chatbot_closed_behavior' ) : 'hide';
		$kapali_msg   = function_exists( 'qmo_chatbot_ayar' ) ? qmo_chatbot_ayar( 'qmo_chatbot_closed_message' ) : '';
		if ( function_exists( 'rma_ceviri_option' ) && '' !== (string) $kapali_msg ) {
			$kapali_msg = rma_ceviri_option( 'qmo_chatbot_closed_message', $kapali_msg );
		}
		$rozet        = function_exists( 'qmo_chatbot_ayar' ) && 'yes' === qmo_chatbot_ayar( 'qmo_chatbot_badge' );
		$teaser       = function_exists( 'qmo_chatbot_ayar' ) && 'yes' === qmo_chatbot_ayar( 'qmo_chatbot_teaser' );
		$ekran        = function_exists( 'qmo_chatbot_ayar' ) && 'yes' === qmo_chatbot_ayar( 'qmo_chatbot_welcome_screen' );
		$sorular      = function_exists( 'qmo_chatbot_sorulari_aktif' ) ? qmo_chatbot_sorulari_aktif() : array();
		$konum        = function_exists( 'qmo_chatbot_ayar' ) ? qmo_chatbot_ayar( 'qmo_chatbot_position' ) : 'right';
		$hareket      = function_exists( 'qmo_chatbot_ayar' ) ? qmo_chatbot_ayar( 'qmo_chatbot_attention' ) : 'none';
		$cihaz_sinif  = 'gm-device-' . sanitize_html_class( $cihaz );
		$konum_sinif  = 'left' === $konum ? 'gm-pos-left' : 'gm-pos-right';
		$attn_map     = array(
			'pulse' => 'gm-attn-pulse',
			'shake' => 'gm-attn-shake',
			'float' => 'gm-attn-float',
		);
		$attn_sinif   = isset( $attn_map[ $hareket ] ) ? $attn_map[ $hareket ] : '';
		$siniflar     = array( 'gemini-shortcode-container', $cihaz_sinif, $konum_sinif );

		ob_start();
		?>
		<div class="<?php echo esc_attr( implode( ' ', $siniflar ) ); ?>"
			data-closed="<?php echo $mesai_disi && 'message' === $kapali_dav ? '1' : '0'; ?>"
			data-closed-msg="<?php echo esc_attr( $kapali_msg ); ?>"
			data-badge="<?php echo $rozet ? '1' : '0'; ?>"
			data-teaser="<?php echo $teaser ? '1' : '0'; ?>"
			data-teaser-delay="<?php echo esc_attr( function_exists( 'qmo_chatbot_ayar' ) ? (int) qmo_chatbot_ayar( 'qmo_chatbot_teaser_delay' ) : 4 ); ?>"
			data-welcome="<?php echo $ekran ? '1' : '0'; ?>">
			<?php if ( $teaser ) : ?>
				<div class="gemini-teaser" hidden>
					<button type="button" class="gemini-teaser-kapat" aria-label="<?php echo esc_attr( qmo_ceviri_chat( __( 'Kapat', 'qrms' ) ) ); ?>">&times;</button>
					<span><?php
						$teaser_metin = qmo_chatbot_ayar( 'qmo_chatbot_teaser_text' );
						if ( function_exists( 'rma_ceviri_option' ) ) {
							$teaser_metin = rma_ceviri_option( 'qmo_chatbot_teaser_text', $teaser_metin );
						}
						echo esc_html( $teaser_metin );
					?></span>
				</div>
			<?php endif; ?>

			<div class="gemini-chat-toggle-btn<?php echo $attn_sinif ? ' ' . esc_attr( $attn_sinif ) : ''; ?>" role="button" tabindex="0" aria-label="<?php echo esc_attr( $bot_adi ); ?>">
				<span class="gm-attn-core">
					<span class="gm-attn-ring" aria-hidden="true"></span>
					<div class="gemini-icon-wrapper">
						<?php qmo_chatbot_ikon( $ikon_url ); ?>
					</div>
				</span>
				<?php if ( $rozet ) : ?>
					<span class="gemini-unread-badge" hidden>1</span>
				<?php endif; ?>
				<?php if ( $metin_goster ) : ?>
					<span><?php echo esc_html( $bot_adi ); ?></span>
				<?php endif; ?>
			</div>

			<div class="gemini-chat-overlay <?php echo esc_attr( $cihaz_sinif . ' ' . $konum_sinif ); ?>">
				<div class="gemini-chat-header">
					<div class="gemini-chat-header-left">
						<div class="gemini-icon-wrapper">
							<?php qmo_chatbot_ikon( $ikon_url ); ?>
						</div>
						<div class="gemini-header-textblock">
							<span><?php echo esc_html( $bot_adi ); ?></span>
							<span class="gemini-header-status"><?php echo esc_html( qmo_ceviri_chat( __( 'Çevrimiçi', 'qrms' ) ) ); ?></span>
						</div>
					</div>
					<button type="button" class="gemini-chat-close" aria-label="<?php echo esc_attr( qmo_ceviri_chat( __( 'Kapat', 'qrms' ) ) ); ?>">&times;</button>
				</div>

				<?php if ( $ekran ) : ?>
					<div class="gemini-welcome-screen">
						<div class="gemini-icon-wrapper"><?php qmo_chatbot_ikon( $ikon_url ); ?></div>
						<strong><?php echo esc_html( $bot_adi ); ?></strong>
						<p><?php
							$welcome_intro = qmo_chatbot_ayar( 'qmo_chatbot_welcome_intro' );
							if ( function_exists( 'rma_ceviri_option' ) ) {
								$welcome_intro = rma_ceviri_option( 'qmo_chatbot_welcome_intro', $welcome_intro );
							}
							echo esc_html( $welcome_intro );
						?></p>
						<button type="button" class="gemini-welcome-start"><?php
							$welcome_btn = qmo_chatbot_ayar( 'qmo_chatbot_welcome_btn' );
							if ( function_exists( 'rma_ceviri_option' ) ) {
								$welcome_btn = rma_ceviri_option( 'qmo_chatbot_welcome_btn', $welcome_btn );
							}
							echo esc_html( $welcome_btn );
						?></button>
					</div>
				<?php endif; ?>

				<div class="gemini-chat-log">
					<div class="gemini-msg-bubble gemini-msg-bot"><?php echo esc_html( $karsilama ); ?></div>
				</div>

				<?php if ( $sorular ) : ?>
					<div class="gemini-quick-replies">
						<?php foreach ( $sorular as $soru ) : ?>
							<?php
							$soru_etiket = $soru['label'];
							$soru_metin  = $soru['question'];
							if ( function_exists( 'rma_ceviri_option' ) && ! empty( $soru['id'] ) ) {
								$soru_etiket = rma_ceviri_option( 'qmo_chatbot_qr.' . $soru['id'] . '.label', $soru_etiket );
								$soru_metin  = rma_ceviri_option( 'qmo_chatbot_qr.' . $soru['id'] . '.question', $soru_metin );
							}
							?>
							<button type="button" class="gemini-quick-reply" data-question="<?php echo esc_attr( $soru_metin ); ?>">
								<?php echo esc_html( $soru_etiket ); ?>
							</button>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>

				<div class="gemini-chat-input-area">
					<input type="text" class="gemini-chat-input" maxlength="1000"
						placeholder="<?php echo esc_attr( $placeholder ); ?>"
						aria-label="<?php echo esc_attr( $placeholder ); ?>" />
					<button type="button" class="gemini-chat-send" aria-label="<?php echo esc_attr( qmo_ceviri_chat( __( 'Gönder', 'qrms' ) ) ); ?>">
						<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/></svg>
					</button>
				</div>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}
}

/**
 * wp_footer: otomatik chatbot enjeksiyonu.
 *
 * @return void
 */
if ( ! function_exists( 'qmo_chatbot_footer_bas' ) ) {
	function qmo_chatbot_footer_bas() {
		if ( ! function_exists( 'qmo_chatbot_otomatik_basilmali_mi' ) || ! qmo_chatbot_otomatik_basilmali_mi() ) {
			return;
		}

		$html = qmo_chatbot_html_uret( 'footer' );
		if ( '' === $html ) {
			return;
		}

		qmo_chatbot_istekte_basildi( true );
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- şablon esc_* ile basılır.
		echo $html;
	}
}

/**
 * Chatbot ikonu (yüklenmiş logo veya varsayılan SVG).
 *
 * @param string $ikon_url Logo adresi.
 */
if ( ! function_exists( 'qmo_chatbot_ikon' ) ) {
	function qmo_chatbot_ikon( $ikon_url ) {
		$preset = function_exists( 'qmo_chatbot_ayar' ) ? qmo_chatbot_ayar( 'qmo_chatbot_icon_preset' ) : 'bubble';
		if ( 'custom' === $preset && $ikon_url ) {
			printf( '<img src="%s" alt="" />', esc_url( $ikon_url ) );
			return;
		}
		if ( $ikon_url && ( ! $preset || 'custom' === $preset ) ) {
			printf( '<img src="%s" alt="" />', esc_url( $ikon_url ) );
			return;
		}
		if ( function_exists( 'qmo_chatbot_ikon_svg' ) && $preset && 'custom' !== $preset ) {
			echo wp_kses( qmo_chatbot_ikon_svg( $preset ), qmo_svg_kses() );
			return;
		}
		if ( $ikon_url ) {
			printf( '<img src="%s" alt="" />', esc_url( $ikon_url ) );
			return;
		}
		echo wp_kses( qmo_varsayilan_ikon(), qmo_svg_kses() );
	}
}

/**
 * Ayarlardan gelen renk/boyut değerlerini CSS değişkenine çevirir.
 *
 * Statik chatbot.css yalnızca var(--gm-*) okur; değerler burada üretilir.
 *
 * @return string
 */
if ( ! function_exists( 'qmo_chatbot_degiskenleri' ) ) {
	function qmo_chatbot_degiskenleri() {
		$d = qmo_renk_varsayilanlari();

		/**
		 * Renk option'ını güvenli biçimde oku.
		 *
		 * @param string $key Option adı.
		 * @return string
		 */
		$renk = function ( $key ) use ( $d ) {
			$deger = get_option( $key, $d[ $key ] );
			// Yalnızca hex renk kabul et — CSS enjeksiyonu olmasın.
			$temiz = sanitize_hex_color( $deger );
			return $temiz ? $temiz : $d[ $key ];
		};

		$coz = function_exists( 'qmo_chatbot_renkleri_coz' ) ? qmo_chatbot_renkleri_coz() : array();
		$renkler = function ( $key ) use ( $d, $renk, $coz ) {
			if ( isset( $coz[ $key ] ) ) {
				$temiz = sanitize_hex_color( $coz[ $key ] );
				return $temiz ? $temiz : $renk( $key );
			}
			return $renk( $key );
		};

		$ikon_boyut = max( 30, (int) get_option( 'gemini_icon_size', 24 ) );
		$radius     = max( 4, (int) get_option( 'gemini_border_radius', 16 ) );
		$toggle_pad = ( 'yes' === get_option( 'gemini_show_toggle_text', 'yes' ) ) ? '13px 26px 13px 14px' : '14px';
		$konum      = function_exists( 'qmo_chatbot_ayar' ) ? qmo_chatbot_ayar( 'qmo_chatbot_position' ) : 'right';
		$yuk        = function_exists( 'qmo_chatbot_ayar' ) ? qmo_chatbot_ayar( 'qmo_chatbot_offset' ) : 'mid';
		$yuk_map    = function_exists( 'qmo_chatbot_yukseklik_haritasi' ) ? qmo_chatbot_yukseklik_haritasi() : array( 'mid' => 108 );
		$gen        = function_exists( 'qmo_chatbot_ayar' ) ? qmo_chatbot_ayar( 'qmo_chatbot_window_width' ) : 'normal';
		$gen_map    = function_exists( 'qmo_chatbot_genislik_haritasi' ) ? qmo_chatbot_genislik_haritasi() : array( 'normal' => 380 );
		$ikon_r     = function_exists( 'qmo_chatbot_ayar' ) ? sanitize_hex_color( qmo_chatbot_ayar( 'qmo_chatbot_icon_color' ) ) : '#ffffff';
		$ikon_bg    = function_exists( 'qmo_chatbot_ayar' ) ? sanitize_hex_color( qmo_chatbot_ayar( 'qmo_chatbot_icon_bg_color' ) ) : '';

		$degiskenler = array(
			'--gm-main'          => $renkler( 'gemini_main_color' ),
			'--gm-toggle-bg'     => $ikon_bg ? $ikon_bg : $renkler( 'gemini_toggle_bg_color' ),
			'--gm-toggle-text'   => $ikon_r ? $ikon_r : $renkler( 'gemini_toggle_text_color' ),
			'--gm-header-bg'     => $renkler( 'gemini_header_bg_color' ),
			'--gm-header-text'   => $renkler( 'gemini_header_text_color' ),
			'--gm-header-icon'   => $renkler( 'gemini_header_icon_color' ),
			'--gm-text'          => $renkler( 'gemini_text_color' ),
			'--gm-border'        => $renkler( 'gemini_border_color' ),
			'--gm-chat-bg'       => $renkler( 'gemini_chat_bg_color' ),
			'--gm-user-bg'       => $renkler( 'gemini_user_msg_color' ),
			'--gm-user-text'     => $renkler( 'gemini_user_msg_text_color' ),
			'--gm-bot-bg'        => $renkler( 'gemini_bot_msg_color' ),
			'--gm-bot-text'      => $renkler( 'gemini_bot_msg_text_color' ),
			'--gm-input-bg'      => $renkler( 'gemini_input_bg_color' ),
			'--gm-input-area-bg' => $renkler( 'gemini_input_area_bg_color' ),
			'--gm-send-bg'       => $renkler( 'gemini_send_btn_bg_color' ),
			'--gm-send-icon'     => $renkler( 'gemini_send_btn_icon_color' ),
			'--gm-radius'        => $radius . 'px',
			'--gm-icon-size'     => $ikon_boyut . 'px',
			'--gm-toggle-pad'    => $toggle_pad,
			'--gm-bottom'        => ( isset( $yuk_map[ $yuk ] ) ? (int) $yuk_map[ $yuk ] : 108 ) . 'px',
			'--gm-side'          => '20px',
			'--gm-window'        => ( isset( $gen_map[ $gen ] ) ? (int) $gen_map[ $gen ] : 380 ) . 'px',
			'--gm-z'             => '2147482800',
		);

		$satirlar = '';
		foreach ( $degiskenler as $ad => $deger ) {
			$satirlar .= $ad . ':' . $deger . ';';
		}

		return '.gemini-shortcode-container,.gemini-chat-overlay{' . $satirlar . '}';
	}
}

/**
 * Kısa kod çıktısında izin verilen SVG etiketleri.
 *
 * @return array
 */
if ( ! function_exists( 'qmo_svg_kses' ) ) {
	function qmo_svg_kses() {
		return array(
			'svg'  => array(
				'viewbox'     => true,
				'width'       => true,
				'height'      => true,
				'fill'        => true,
				'stroke'      => true,
				'style'       => true,
				'class'       => true,
				'aria-hidden' => true,
				'xmlns'       => true,
				'stroke-width'      => true,
				'stroke-linecap'    => true,
				'stroke-linejoin'   => true,
			),
			'path'   => array(
				'd'                 => true,
				'fill'              => true,
				'stroke'            => true,
				'stroke-width'      => true,
				'stroke-linecap'    => true,
				'stroke-linejoin'   => true,
			),
			'circle' => array(
				'cx'           => true,
				'cy'           => true,
				'r'            => true,
				'fill'         => true,
				'stroke'       => true,
				'stroke-width' => true,
			),
			'line'   => array(
				'x1'    => true,
				'y1'    => true,
				'x2'    => true,
				'y2'    => true,
				'stroke' => true,
			),
		);
	}
}
