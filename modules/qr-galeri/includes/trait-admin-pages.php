<?php
/**
 * Galeri yönetim sayfaları.
 *
 * @package QR_Menu_Suite
 */

defined( 'ABSPATH' ) || exit;

trait QRMGM_Admin_Pages_Trait {

	public function page_sections(): void {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( 'Yetkiniz yok.' );
		}

		$sections = get_posts( [
			'post_type'      => self::CPT_SECTION,
			'post_status'    => [ 'publish', 'draft' ],
			'orderby'        => 'menu_order',
			'order'          => 'ASC',
			'posts_per_page' => -1,
		] );

		include __DIR__ . '/admin-page-sections.php';
	}

	public function page_images(): void {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( 'Yetkiniz yok.' );
		}

		$sections   = get_posts( [ 'post_type' => self::CPT_SECTION, 'post_status' => [ 'publish', 'draft' ], 'orderby' => 'menu_order', 'order' => 'ASC', 'posts_per_page' => -1 ] );
		$current_id = isset( $_GET['section'] ) ? absint( $_GET['section'] ) : ( $sections[0]->ID ?? 0 );

		include __DIR__ . '/admin-page-images.php';
	}

	public function render_admin_image_cards( int $section_id ): void {
		$images = get_posts( [
			'post_type'      => self::CPT_IMAGE,
			'post_parent'    => $section_id,
			'post_status'    => [ 'publish', 'draft' ],
			'orderby'        => 'menu_order',
			'order'          => 'ASC',
			'posts_per_page' => -1,
		] );

		if ( empty( $images ) ) {
			echo '<p class="qrmgm-empty">Bu bölümde henüz görsel yok.</p>';
			return;
		}

		foreach ( $images as $img ) {
			$att_id   = (int) get_post_meta( $img->ID, '_qrmgm_attachment_id', true );
			$thumb    = wp_get_attachment_image_url( $att_id, 'medium' );
			$alt      = get_post_meta( $img->ID, '_qrmgm_alt', true );
			$desc     = get_post_meta( $img->ID, '_qrmgm_desc', true );
			$tag      = get_post_meta( $img->ID, '_qrmgm_tag', true );
			$featured = (bool) get_post_meta( $img->ID, '_qrmgm_featured', true );
			$active   = 'publish' === $img->post_status;
			?>
			<div class="qrmgm-image-card" data-id="<?php echo esc_attr( $img->ID ); ?>">
				<div class="qrmgm-drag-handle qrmgm-card-drag">⠿</div>
				<img src="<?php echo esc_url( $thumb ?: '' ); ?>" alt="<?php echo esc_attr( $alt ); ?>" loading="lazy" />
				<div class="qrmgm-card-body">
					<strong><?php echo esc_html( $img->post_title ); ?></strong>
					<?php if ( $tag ) : ?><span class="qrmgm-tag-pill"><?php echo esc_html( $tag ); ?></span><?php endif; ?>
					<?php if ( $desc ) : ?><p class="qrmgm-card-desc"><?php echo esc_html( wp_trim_words( $desc, 10 ) ); ?></p><?php endif; ?>
					<div class="qrmgm-card-actions">
						<label class="qrmgm-mini-switch" title="Öne Çıkan">
							<input type="checkbox" class="qrmgm-toggle-featured" data-id="<?php echo esc_attr( $img->ID ); ?>" <?php checked( $featured ); ?> /> ★
						</label>
						<label class="qrmgm-mini-switch" title="Aktif/Pasif">
							<input type="checkbox" class="qrmgm-toggle-image" data-id="<?php echo esc_attr( $img->ID ); ?>" <?php checked( $active ); ?> /> Aktif
						</label>
						<button class="button button-small qrmgm-edit-image"
							data-id="<?php echo esc_attr( $img->ID ); ?>"
							data-title="<?php echo esc_attr( $img->post_title ); ?>"
							data-alt="<?php echo esc_attr( $alt ); ?>"
							data-desc="<?php echo esc_attr( $desc ); ?>"
							data-tag="<?php echo esc_attr( $tag ); ?>">Düzenle</button>
						<button class="button button-small qrmgm-duplicate-image" data-id="<?php echo esc_attr( $img->ID ); ?>">Çoğalt</button>
						<a class="button button-small" href="<?php echo esc_url( wp_get_attachment_url( $att_id ) ?: '#' ); ?>" download>İndir</a>
						<button class="button button-small qrmgm-delete-image" data-id="<?php echo esc_attr( $img->ID ); ?>">Sil</button>
					</div>
				</div>
			</div>
			<?php
		}
	}

	public function page_settings(): void {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( 'Yetkiniz yok.' );
		}

		if ( isset( $_POST['qrmgm_settings_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['qrmgm_settings_nonce'] ) ), 'qrmgm_save_settings_action' ) ) {
			$this->save_settings_from_request( $_POST );
			echo '<div class="notice notice-success is-dismissible"><p>Ayarlar kaydedildi.</p></div>';
		}

		$s = $this->get_settings();
		include __DIR__ . '/admin-page-settings.php';
	}

	private function save_settings_from_request( array $data ): void {
		$defaults = $this->default_settings();
		$out      = [
			'radius'          => absint( $data['radius'] ?? $defaults['radius'] ),
			'shadow'          => sanitize_key( $data['shadow'] ?? $defaults['shadow'] ),
			'gap'             => absint( $data['gap'] ?? $defaults['gap'] ),
			'columns_desktop' => max( 1, min( 6, absint( $data['columns_desktop'] ?? 4 ) ) ),
			'columns_tablet'  => max( 1, min( 6, absint( $data['columns_tablet'] ?? 3 ) ) ),
			'columns_mobile'  => max( 1, min( 6, absint( $data['columns_mobile'] ?? 2 ) ) ),
			'hover_effect'    => sanitize_key( $data['hover_effect'] ?? $defaults['hover_effect'] ),
			'animations'      => empty( $data['animations'] ) ? 0 : 1,
			'lightbox'        => empty( $data['lightbox'] ) ? 0 : 1,
			'filter_bar'      => empty( $data['filter_bar'] ) ? 0 : 1,
			'lazy_load'       => empty( $data['lazy_load'] ) ? 0 : 1,
			'webp'            => empty( $data['webp'] ) ? 0 : 1,
			'color_dark'      => sanitize_hex_color( $data['color_dark'] ?? $defaults['color_dark'] ) ?: $defaults['color_dark'],
			'color_gold'      => sanitize_hex_color( $data['color_gold'] ?? $defaults['color_gold'] ) ?: $defaults['color_gold'],
			'color_light'     => sanitize_hex_color( $data['color_light'] ?? $defaults['color_light'] ) ?: $defaults['color_light'],
			'color_white'     => sanitize_hex_color( $data['color_white'] ?? $defaults['color_white'] ) ?: $defaults['color_white'],
			'font'            => in_array( $data['font'] ?? '', [ 'Poppins', 'Inter' ], true ) ? $data['font'] : $defaults['font'],
			'overlay_opacity' => max( 0, min( 100, absint( $data['overlay_opacity'] ?? $defaults['overlay_opacity'] ) ) ),
		];
		update_option( self::OPTION_SETTINGS, $out );
		$this->clear_cache();
	}
}
