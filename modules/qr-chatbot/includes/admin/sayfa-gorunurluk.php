<?php
/**
 * Görünürlük alt sayfası — Asistanın Görünürlüğü.
 *
 * @package QR_Menu_Suite
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Kimlere / hangi cihazda / çalışma saatleri / kapalıyken davranış.
 *
 * @return void
 */
function qmo_chatbot_sayfa_gorunurluk() {
	qmo_chatbot_sayfa_basligi(
		__( 'Ne Zaman ve Kimlere Gösterilsin?', 'qrms' ),
		__( 'AI Menü Asistanınızın nerede, ne zaman ve kimlere gösterileceğini belirleyin.', 'qrms' )
	);

	$saatler_var     = function_exists( 'qrms_cs_is_open_at' );
	$oto             = 'yes' === qmo_chatbot_ayar( 'qmo_chatbot_auto_inject' );
	$audience        = (string) qmo_chatbot_ayar( 'qmo_chatbot_audience' );
	$devices         = (string) qmo_chatbot_ayar( 'qmo_chatbot_devices' );
	$hide_hours      = 'yes' === qmo_chatbot_ayar( 'qmo_chatbot_hide_after_hours' );
	$closed_behavior = (string) qmo_chatbot_ayar( 'qmo_chatbot_closed_behavior' );
	$closed_message  = (string) qmo_chatbot_ayar( 'qmo_chatbot_closed_message' );

	qmo_chatbot_form_ac();
	?>
	<div class="qmo-cb-wizard">
		<div class="qmo-cb-wizard-main">

			<section class="qmo-cb-card" id="qmo-cb-auto-card">
				<h2 class="qmo-cb-card-title"><?php esc_html_e( 'Asistanı Göster', 'qrms' ); ?></h2>
				<label class="qmo-cb-switch-row">
					<input type="hidden" name="qmo_chatbot_auto_inject" value="no">
					<input type="checkbox" id="qmo-cb-auto-inject" name="qmo_chatbot_auto_inject" value="yes" <?php checked( $oto ); ?>>
					<span class="qmo-cb-switch-ui" aria-hidden="true"></span>
					<span class="qmo-cb-switch-text"><?php esc_html_e( 'Asistanı otomatik göster', 'qrms' ); ?></span>
				</label>
				<p class="qmo-cb-field-desc"><?php esc_html_e( 'AI Menü Asistanını sitenizde otomatik olarak müşterilerinize gösterin.', 'qrms' ); ?></p>

				<div class="qmo-cb-shortcode-note">
					<p class="qmo-cb-field-desc"><?php esc_html_e( 'Asistan yalnızca eklediğiniz kısa kodla gösterilir.', 'qrms' ); ?></p>
					<div class="qmo-cb-shortcode-row">
						<code id="qmo-cb-shortcode-value">[gemini_chatbot]</code>
						<button type="button" class="button" id="qmo-cb-shortcode-copy"><?php esc_html_e( 'Kısa Kodu Kopyala', 'qrms' ); ?></button>
					</div>
				</div>
			</section>

			<section class="qmo-cb-card">
				<h2 class="qmo-cb-card-title"><?php esc_html_e( 'Müşteri Erişimi', 'qrms' ); ?></h2>

				<div class="qmo-cb-field">
					<span class="qmo-cb-field-label-static"><?php esc_html_e( 'Kimler görebilsin?', 'qrms' ); ?></span>
					<div class="qmo-cb-choice-cards" role="radiogroup" aria-label="<?php esc_attr_e( 'Kimler görebilsin?', 'qrms' ); ?>">
						<label class="qmo-cb-choice-card<?php echo 'all' === $audience ? ' is-selected' : ''; ?>">
							<input type="radio" name="qmo_chatbot_audience" value="all" <?php checked( $audience, 'all' ); ?>>
							<span class="qmo-cb-choice-title"><?php esc_html_e( 'Tüm ziyaretçiler', 'qrms' ); ?></span>
							<span class="qmo-cb-choice-desc"><?php esc_html_e( 'AI Menü Asistanını sitenizi ziyaret eden herkes kullanabilir.', 'qrms' ); ?></span>
						</label>
						<label class="qmo-cb-choice-card<?php echo 'session' === $audience ? ' is-selected' : ''; ?>">
							<input type="radio" name="qmo_chatbot_audience" value="session" <?php checked( $audience, 'session' ); ?>>
							<span class="qmo-cb-choice-title"><?php esc_html_e( 'QR menüden gelen müşteriler', 'qrms' ); ?></span>
							<span class="qmo-cb-choice-desc"><?php esc_html_e( 'Masadaki müşteriler QR menü üzerinden AI Menü Asistanını kullanabilir.', 'qrms' ); ?></span>
						</label>
					</div>
				</div>

				<div class="qmo-cb-field">
					<span class="qmo-cb-field-label-static"><?php esc_html_e( 'Hangi cihazlarda?', 'qrms' ); ?></span>
					<?php
					qmo_chatbot_secenek_grup(
						'qmo_chatbot_devices',
						$devices,
						array(
							'phone'   => __( 'Telefon', 'qrms' ),
							'desktop' => __( 'Masaüstü', 'qrms' ),
							'both'    => __( 'Telefon ve Masaüstü', 'qrms' ),
						)
					);
					?>
				</div>
			</section>

			<section class="qmo-cb-card">
				<h2 class="qmo-cb-card-title"><?php esc_html_e( 'Çalışma Saatleri', 'qrms' ); ?></h2>
				<?php if ( $saatler_var ) : ?>
					<label class="qmo-cb-inline-check">
						<input type="hidden" name="qmo_chatbot_hide_after_hours" value="no">
						<input type="checkbox" name="qmo_chatbot_hide_after_hours" value="yes" <?php checked( $hide_hours ); ?>>
						<?php esc_html_e( 'Restoranınız kapalıyken asistanı gizle', 'qrms' ); ?>
					</label>
					<p class="qmo-cb-field-desc">
						<?php esc_html_e( 'Çalışma saatleri, Çalışma Saatleri modülünüzdeki ayarlardan alınır.', 'qrms' ); ?>
						<?php if ( class_exists( 'QRMS_Admin' ) ) : ?>
							<a href="<?php echo esc_url( QRMS_Admin::get_module_page_url( 'qr-calisma-saatleri' ) ); ?>"><?php esc_html_e( 'Çalışma Saatlerini Yönet', 'qrms' ); ?></a>
						<?php endif; ?>
					</p>
				<?php else : ?>
					<p class="qmo-cb-field-desc"><?php esc_html_e( 'Bu seçenek için Çalışma Saatleri modülünün açık olması gerekir.', 'qrms' ); ?></p>
					<input type="hidden" name="qmo_chatbot_hide_after_hours" value="<?php echo esc_attr( $hide_hours ? 'yes' : 'no' ); ?>">
				<?php endif; ?>
			</section>

			<section class="qmo-cb-card">
				<h2 class="qmo-cb-card-title"><?php esc_html_e( 'Restoran Kapalıyken', 'qrms' ); ?></h2>

				<div class="qmo-cb-choice-cards" role="radiogroup" aria-label="<?php esc_attr_e( 'Restoran kapalıyken', 'qrms' ); ?>">
					<label class="qmo-cb-choice-card<?php echo 'hide' === $closed_behavior ? ' is-selected' : ''; ?>">
						<input type="radio" name="qmo_chatbot_closed_behavior" value="hide" <?php checked( $closed_behavior, 'hide' ); ?>>
						<span class="qmo-cb-choice-title"><?php esc_html_e( 'Asistanı tamamen gizle', 'qrms' ); ?></span>
						<span class="qmo-cb-choice-desc"><?php esc_html_e( 'Müşteriler asistanı görmez.', 'qrms' ); ?></span>
					</label>
					<label class="qmo-cb-choice-card<?php echo 'message' === $closed_behavior ? ' is-selected' : ''; ?>">
						<input type="radio" name="qmo_chatbot_closed_behavior" value="message" <?php checked( $closed_behavior, 'message' ); ?>>
						<span class="qmo-cb-choice-title"><?php esc_html_e( 'Asistanı göster, mesajlaşmayı kapat', 'qrms' ); ?></span>
						<span class="qmo-cb-choice-desc"><?php esc_html_e( 'Müşteriler asistanı görebilir ancak restoranınızın kapalı olduğunu belirten mesajı görür.', 'qrms' ); ?></span>
					</label>
				</div>

				<div class="qmo-cb-field">
					<label for="qmo_chatbot_closed_message"><?php esc_html_e( 'Kapalıyken gösterilecek mesaj', 'qrms' ); ?></label>
					<?php qmo_chatbot_i18n_wrap_ac( $closed_message ); ?>
					<input type="text" id="qmo_chatbot_closed_message" name="qmo_chatbot_closed_message" class="large-text"
						placeholder="<?php esc_attr_e( 'Şu an kapalıyız. Yakında tekrar hizmetinizdeyiz. 👋', 'qrms' ); ?>"
						value="<?php echo esc_attr( $closed_message ); ?>">
					<?php qmo_chatbot_i18n_wrap_kapat( 'qmo_chatbot_closed_message' ); ?>
				</div>
			</section>
		</div>

		<aside class="qmo-cb-preview-col" aria-label="<?php esc_attr_e( 'Görünürlük özeti', 'qrms' ); ?>">
			<div class="qmo-cb-summary-card" id="qmo-cb-visibility-summary">
				<p class="qmo-cb-preview-title"><?php esc_html_e( 'Görünürlük Özeti', 'qrms' ); ?></p>
				<ul class="qmo-cb-summary-list">
					<li data-summary="auto">
						<?php echo $oto ? '✓' : '—'; ?>
						<?php echo $oto ? esc_html__( 'Otomatik gösterim açık', 'qrms' ) : esc_html__( 'Yalnızca kısa kodla gösterim', 'qrms' ); ?>
					</li>
					<li data-summary="audience">
						✓ <?php echo 'all' === $audience ? esc_html__( 'Tüm ziyaretçiler', 'qrms' ) : esc_html__( 'QR menü müşterileri', 'qrms' ); ?>
					</li>
					<li data-summary="devices">
						✓
						<?php
						if ( 'both' === $devices ) {
							esc_html_e( 'Telefon + Masaüstü', 'qrms' );
						} elseif ( 'phone' === $devices ) {
							esc_html_e( 'Yalnızca telefon', 'qrms' );
						} else {
							esc_html_e( 'Yalnızca masaüstü', 'qrms' );
						}
						?>
					</li>
					<li data-summary="hours">
						<?php echo ( $saatler_var && $hide_hours ) ? '✓' : '—'; ?>
						<?php esc_html_e( 'Çalışma saatlerine uyum', 'qrms' ); ?>
					</li>
					<li data-summary="closed">
						✓
						<?php
						if ( 'hide' === $closed_behavior ) {
							esc_html_e( 'Kapalıyken tamamen gizlenir', 'qrms' );
						} else {
							esc_html_e( 'Kapalıyken mesaj gösterilir', 'qrms' );
						}
						?>
					</li>
				</ul>
			</div>
		</aside>
	</div>
	<?php
	qmo_chatbot_form_kapat();
	qmo_chatbot_gorunurluk_script();
	qmo_chatbot_sayfa_bitir();
}

/**
 * Kısa kod kopyalama + otomatik gösterim notu görünürlüğü için küçük script.
 * :has() destekleyen tarayıcılarda CSS zaten notu gizler; bu script yalnızca
 * kopyalama davranışını ekler (CSS geri dönüşü olarak not her durumda okunur).
 *
 * @return void
 */
function qmo_chatbot_gorunurluk_script() {
	?>
	<script>
	( function () {
		var btn = document.getElementById( 'qmo-cb-shortcode-copy' );
		var kod = document.getElementById( 'qmo-cb-shortcode-value' );
		if ( ! btn || ! kod ) {
			return;
		}
		btn.addEventListener( 'click', function () {
			var metin = kod.textContent || '';
			var basarili = function () {
				var eski = btn.textContent;
				btn.textContent = '<?php echo esc_js( __( 'Kopyalandı ✓', 'qrms' ) ); ?>';
				window.setTimeout( function () {
					btn.textContent = eski;
				}, 2000 );
			};
			if ( navigator.clipboard && navigator.clipboard.writeText ) {
				navigator.clipboard.writeText( metin ).then( basarili );
				return;
			}
			var alan = document.createElement( 'textarea' );
			alan.value = metin;
			alan.style.position = 'fixed';
			alan.style.opacity = '0';
			document.body.appendChild( alan );
			alan.select();
			try {
				document.execCommand( 'copy' );
				basarili();
			} catch ( e ) {
				// Sessiz geç — kopyalama desteklenmiyor.
			}
			document.body.removeChild( alan );
		} );
	}() );

	// Seçim kartlarının vurgusu CSS :has() ile de çalışır; bu yalnızca
	// eski tarayıcılarda kayıt öncesi seçim değişince görsel geri bildirim
	// versin diye eklenen küçük bir geri dönüştür.
	( function () {
		document.querySelectorAll( '.qmo-cb-choice-cards' ).forEach( function ( grup ) {
			grup.addEventListener( 'change', function ( e ) {
				if ( 'radio' !== e.target.type ) {
					return;
				}
				grup.querySelectorAll( '.qmo-cb-choice-card' ).forEach( function ( kart ) {
					var girdi = kart.querySelector( 'input' );
					kart.classList.toggle( 'is-selected', !!( girdi && girdi.checked ) );
				} );
			} );
		} );
	}() );
	</script>
	<?php
}
