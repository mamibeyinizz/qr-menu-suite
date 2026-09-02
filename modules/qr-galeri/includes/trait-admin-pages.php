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

	/**
	 * Ayar ekranındaki font seçenekleri — kaydetme ve form aynı listeden beslenir.
	 *
	 * @return string[]
	 */
	private function gallery_font_choices(): array {
		return [ 'Poppins', 'Inter' ];
	}

	/**
	 * İzin listesinden değer seçer; yoksa varsayılana döner.
	 *
	 * @param mixed $value   Gelen değer.
	 * @param array $allowed İzin verilenler.
	 * @param mixed $default Varsayılan.
	 * @return mixed
	 */
	private function pick_setting( $value, array $allowed, $default ) {
		return in_array( $value, $allowed, true ) ? $value : $default;
	}

	private function save_settings_from_request( array $data ): void {
		$defaults = $this->default_settings();
		$fonts    = $this->gallery_font_choices();
		$weights  = [ 400, 500, 600, 700, 800, 900 ];
		$aligns   = [ 'left', 'center', 'right' ];
		$hex      = static function ( $raw, $fallback ) {
			$color = sanitize_hex_color( is_string( $raw ) ? $raw : '' );
			return $color ? $color : $fallback;
		};

		$out = [
			'radius'            => max( 0, min( 60, absint( $data['radius'] ?? $defaults['radius'] ) ) ),
			'shadow'            => $this->pick_setting( sanitize_key( $data['shadow'] ?? '' ), [ 'none', 'soft', 'medium', 'strong' ], $defaults['shadow'] ),
			'gap'               => max( 0, min( 60, absint( $data['gap'] ?? $defaults['gap'] ) ) ),
			'columns_desktop'   => max( 1, min( 6, absint( $data['columns_desktop'] ?? 4 ) ) ),
			'columns_tablet'    => max( 1, min( 6, absint( $data['columns_tablet'] ?? 3 ) ) ),
			'columns_mobile'    => max( 1, min( 6, absint( $data['columns_mobile'] ?? 2 ) ) ),
			'hover_effect'      => $this->pick_setting( sanitize_key( $data['hover_effect'] ?? '' ), [ 'none', 'zoom', 'glass', 'lift' ], $defaults['hover_effect'] ),
			'animations'        => empty( $data['animations'] ) ? 0 : 1,
			'lightbox'          => empty( $data['lightbox'] ) ? 0 : 1,
			'filter_bar'        => empty( $data['filter_bar'] ) ? 0 : 1,
			'lazy_load'         => empty( $data['lazy_load'] ) ? 0 : 1,
			'webp'              => empty( $data['webp'] ) ? 0 : 1,
			'color_dark'        => $hex( $data['color_dark'] ?? '', $defaults['color_dark'] ),
			'color_gold'        => $hex( $data['color_gold'] ?? '', $defaults['color_gold'] ),
			'color_light'       => $hex( $data['color_light'] ?? '', $defaults['color_light'] ),
			'color_white'       => $hex( $data['color_white'] ?? '', $defaults['color_white'] ),
			'font'              => $this->pick_setting( $data['font'] ?? '', $fonts, $defaults['font'] ),
			'overlay_opacity'   => max( 0, min( 100, absint( $data['overlay_opacity'] ?? $defaults['overlay_opacity'] ) ) ),
			'title_font'        => $this->pick_setting( $data['title_font'] ?? '', $fonts, $defaults['title_font'] ),
			'title_size'        => max( 12, min( 72, absint( $data['title_size'] ?? $defaults['title_size'] ) ) ),
			'title_color'       => $hex( $data['title_color'] ?? '', $defaults['title_color'] ),
			'title_weight'      => (int) $this->pick_setting( absint( $data['title_weight'] ?? 0 ), $weights, $defaults['title_weight'] ),
			'title_align'       => $this->pick_setting( sanitize_key( $data['title_align'] ?? '' ), $aligns, $defaults['title_align'] ),
			'title_transform'   => $this->pick_setting( sanitize_key( $data['title_transform'] ?? '' ), [ 'none', 'uppercase', 'capitalize' ], $defaults['title_transform'] ),
			'divider_show'      => empty( $data['divider_show'] ) ? 0 : 1,
			'divider_align'     => $this->pick_setting( sanitize_key( $data['divider_align'] ?? '' ), $aligns, $defaults['divider_align'] ),
			'divider_color'     => $hex( $data['divider_color'] ?? '', $defaults['divider_color'] ),
			'divider_width'     => max( 0, min( 400, absint( $data['divider_width'] ?? $defaults['divider_width'] ) ) ),
			'divider_thickness' => max( 1, min( 12, absint( $data['divider_thickness'] ?? $defaults['divider_thickness'] ) ) ),
			'divider_radius'    => max( 0, min( 20, absint( $data['divider_radius'] ?? $defaults['divider_radius'] ) ) ),
			'desc_font'         => $this->pick_setting( $data['desc_font'] ?? '', $fonts, $defaults['desc_font'] ),
			'desc_size'         => max( 10, min( 36, absint( $data['desc_size'] ?? $defaults['desc_size'] ) ) ),
			'desc_color'        => $hex( $data['desc_color'] ?? '', $defaults['desc_color'] ),
			'desc_weight'       => (int) $this->pick_setting( absint( $data['desc_weight'] ?? 0 ), $weights, $defaults['desc_weight'] ),
			'desc_align'        => $this->pick_setting( sanitize_key( $data['desc_align'] ?? '' ), $aligns, $defaults['desc_align'] ),
			'desc_max_width'    => max( 0, min( 200, absint( $data['desc_max_width'] ?? $defaults['desc_max_width'] ) ) ),
		];
		update_option( self::OPTION_SETTINGS, $out );
		$this->clear_cache();
	}
}
