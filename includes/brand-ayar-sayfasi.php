<?php
/**
 * Sistem Ayarları — Restoran Markası sekmesi.
 *
 * @package QR_Menu_Suite
 */

defined( 'ABSPATH' ) || exit;

/**
 * Restoran Markası ayar formu.
 *
 * @return void
 */
function qrms_marka_ayar_sayfasi() {
	$s        = QRMS_Brand_Identity::get_settings();
	$logo_url = QRMS_Brand_Identity::get_logo_url( 'medium' );

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$kaydedildi = isset( $_GET['kaydedildi'] ) && '1' === (string) wp_unslash( $_GET['kaydedildi'] );
	?>
	<?php if ( $kaydedildi ) : ?>
		<div class="qrms-alert qrms-alert-success">
			<p><?php esc_html_e( 'Restoran markası kaydedildi.', 'qrms' ); ?></p>
		</div>
	<?php endif; ?>

	<form class="qrms-marka-ayar" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( QRMS_Brand_Identity::NONCE ); ?>
		<input type="hidden" name="action" value="<?php echo esc_attr( QRMS_Brand_Identity::ACTION ); ?>" />

		<div class="qrms-marka-grid">
			<div class="qrms-marka-col">
				<div class="qrms-card">
					<h2 class="qrms-card-title"><?php esc_html_e( 'Restoran Markası', 'qrms' ); ?></h2>
					<p class="qrms-muted">
						<?php esc_html_e( 'Logo ve marka adı burada merkezi olarak saklanır. Header, giriş ekranı ve diğer modüller Phase 2 ile bu kaynağa bağlanacaktır.', 'qrms' ); ?>
					</p>

					<div class="qrms-field qrms-medya" data-medya="logo">
						<label class="qrms-label"><?php esc_html_e( 'Logo', 'qrms' ); ?></label>
						<div class="qrms-medya-onizleme qrms-marka-logo-onizleme">
							<?php if ( '' !== $logo_url ) : ?>
								<img src="<?php echo esc_url( $logo_url ); ?>" alt="">
							<?php endif; ?>
						</div>
						<input type="hidden" name="qrms_marka[logo]" value="<?php echo esc_attr( (string) $s['logo'] ); ?>" />
						<button type="button" class="button qrms-medya-sec"><?php esc_html_e( 'Logo seç', 'qrms' ); ?></button>
						<button type="button" class="button-link qrms-medya-sil"><?php esc_html_e( 'Kaldır', 'qrms' ); ?></button>
					</div>

					<div class="qrms-field">
						<label class="qrms-label" for="qrms-marka-ad"><?php esc_html_e( 'Marka Adı', 'qrms' ); ?></label>
						<input
							type="text"
							class="qrms-input"
							id="qrms-marka-ad"
							name="qrms_marka[ad]"
							value="<?php echo esc_attr( $s['ad'] ); ?>"
							placeholder="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>"
							data-onizleme-metin=".qrms-marka-onizleme-ad"
						/>
					</div>

					<div class="qrms-field">
						<label class="qrms-label" for="qrms-marka-alt-ad"><?php esc_html_e( 'Kısa marka / alt satır (isteğe bağlı)', 'qrms' ); ?></label>
						<input
							type="text"
							class="qrms-input"
							id="qrms-marka-alt-ad"
							name="qrms_marka[alt_ad]"
							value="<?php echo esc_attr( $s['alt_ad'] ); ?>"
							data-onizleme-metin=".qrms-marka-onizleme-alt"
						/>
					</div>
				</div>

				<p class="qrms-marka-kaydet">
					<button type="submit" class="button button-primary button-hero"><?php esc_html_e( 'Kaydet', 'qrms' ); ?></button>
				</p>
			</div>

			<div class="qrms-marka-col qrms-marka-col-onizleme">
				<div class="qrms-card qrms-marka-onizleme-kart">
					<h2 class="qrms-card-title"><?php esc_html_e( 'Önizleme', 'qrms' ); ?></h2>
					<div class="qrms-marka-onizleme-shell" id="qrms-marka-onizleme">
						<div class="qrms-marka-onizleme-marka" data-onizleme-kok>
							<div class="qrms-marka-onizleme-logo" data-onizleme-logo <?php echo '' === $logo_url ? 'hidden' : ''; ?>>
								<?php if ( '' !== $logo_url ) : ?>
									<img src="<?php echo esc_url( $logo_url ); ?>" alt="">
								<?php endif; ?>
							</div>
							<span class="qrms-marka-onizleme-yedek" data-onizleme-yedek <?php echo '' !== $logo_url ? 'hidden' : ''; ?> aria-hidden="true"></span>
							<span class="qrms-marka-onizleme-metin">
								<span class="qrms-marka-onizleme-ad"><?php echo esc_html( '' !== $s['ad'] ? $s['ad'] : QRMS_Brand_Identity::get_fallback_name() ); ?></span>
								<span class="qrms-marka-onizleme-alt"><?php echo esc_html( '' !== $s['alt_ad'] ? $s['alt_ad'] : QRMS_Brand_Identity::get_fallback_sub_name() ); ?></span>
							</span>
						</div>
					</div>
				</div>
			</div>
		</div>
	</form>
	<?php
}
