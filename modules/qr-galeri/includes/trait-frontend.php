<?php
/**
 * Galeri ön yüz kısa kodu.
 *
 * @package QR_Menu_Suite
 */

defined( 'ABSPATH' ) || exit;

trait QRMGM_Frontend_Trait {

	public function maybe_frontend_assets(): void {
		global $post;
		$has_shortcode = is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'qrmenu_gallery' );
		if ( ! $has_shortcode ) {
			return;
		}

		$s = $this->get_settings();

		wp_register_style( 'qrmgm-front', false );
		wp_enqueue_style( 'qrmgm-front' );
		wp_add_inline_style( 'qrmgm-front', $this->frontend_css( $s ) );

		wp_register_script( 'qrmgm-front', false, [], QRMGM_VERSION, true );
		wp_enqueue_script( 'qrmgm-front' );
		wp_add_inline_script( 'qrmgm-front', $this->frontend_js( $s ) );
	}

	public function render_shortcode( $atts ): string {
		$atts = shortcode_atts( [ 'section' => '' ], $atts, 'qrmenu_gallery' );
		$s    = $this->get_settings();

		$dil    = function_exists( 'rma_get_current_lang' ) ? rma_get_current_lang() : 'tr';
		$surum  = function_exists( 'rma_ceviri_onbellek_surumu' ) ? rma_ceviri_onbellek_surumu() : 0;
		$cache_key = 'qrmgm_gallery_' . md5( $atts['section'] . '|' . $dil . '|' . $surum );
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			return $cached;
		}

		$section_query_args = [
			'post_type'      => self::CPT_SECTION,
			'post_status'    => 'publish',
			'orderby'        => 'menu_order',
			'order'          => 'ASC',
			'posts_per_page' => -1,
		];
		if ( $atts['section'] ) {
			$section_query_args['name'] = sanitize_title( $atts['section'] );
		}
		$sections = get_posts( $section_query_args );

		if ( empty( $sections ) ) {
			$bos = __( 'Galeri bulunamadı.', 'qrmenu-gallery-manager' );
			if ( function_exists( 'rma_ceviri_modul' ) ) {
				$bos = rma_ceviri_modul( 'gallery', $bos );
			}
			return '<p>' . esc_html( $bos ) . '</p>';
		}

		ob_start();
		?>
		<div class="qrmgm-gallery" data-lightbox="<?php echo esc_attr( $s['lightbox'] ); ?>">
			<?php if ( $s['filter_bar'] && count( $sections ) > 1 ) : ?>
				<div class="qrmgm-filter-bar">
					<button type="button" class="qrmgm-filter-btn is-active" data-filter="all"><?php
						$tumu = function_exists( 'rma_ceviri_modul' ) ? rma_ceviri_modul( 'gallery', 'Tümü' ) : 'Tümü';
						echo esc_html( $tumu );
					?></button>
					<?php foreach ( $sections as $sec ) : ?>
						<button type="button" class="qrmgm-filter-btn" data-filter="<?php echo esc_attr( $sec->post_name ); ?>"><?php echo esc_html( $sec->post_title ); ?></button>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<div class="qrmgm-masonry-grid" style="column-count:var(--qrmgm-cols-desktop);">
				<?php
				$counter = 0;
				foreach ( $sections as $sec ) :
					$images = get_posts( [
						'post_type'      => self::CPT_IMAGE,
						'post_parent'    => $sec->ID,
						'post_status'    => 'publish',
						'orderby'        => 'menu_order',
						'order'          => 'ASC',
						'posts_per_page' => -1,
					] );
					foreach ( $images as $img ) :
						$att_id = (int) get_post_meta( $img->ID, '_qrmgm_attachment_id', true );
						if ( ! $att_id ) {
							continue;
						}
						$src        = wp_get_attachment_image_src( $att_id, 'large' );
						$full       = wp_get_attachment_image_src( $att_id, 'full' );
						$webp_url   = get_post_meta( $att_id, '_qrmgm_webp_url', true );
						$alt        = get_post_meta( $img->ID, '_qrmgm_alt', true ) ?: $img->post_title;
						$desc       = get_post_meta( $img->ID, '_qrmgm_desc', true );
						$icon       = get_post_meta( $sec->ID, '_qrmgm_icon', true );
						$counter++;
						if ( ! $src ) {
							continue;
						}
						[ $url, $width, $height ] = $src;
						?>
						<figure class="qrmgm-item" data-section="<?php echo esc_attr( $sec->post_name ); ?>" data-index="<?php echo esc_attr( $counter ); ?>">
							<a href="<?php echo esc_url( $full[0] ?? $url ); ?>"
							   class="qrmgm-lightbox-trigger"
							   data-caption="<?php echo esc_attr( $img->post_title . ( $desc ? ' — ' . $desc : '' ) ); ?>"
							   data-download="<?php echo esc_url( $full[0] ?? $url ); ?>">
								<?php if ( $webp_url ) : ?>
									<picture>
										<source srcset="<?php echo esc_url( $webp_url ); ?>" type="image/webp" />
										<img src="<?php echo esc_url( $url ); ?>" alt="<?php echo esc_attr( $alt ); ?>" width="<?php echo esc_attr( $width ); ?>" height="<?php echo esc_attr( $height ); ?>" <?php echo $s['lazy_load'] ? 'loading="lazy"' : ''; ?> />
									</picture>
								<?php else : ?>
									<img src="<?php echo esc_url( $url ); ?>" alt="<?php echo esc_attr( $alt ); ?>" width="<?php echo esc_attr( $width ); ?>" height="<?php echo esc_attr( $height ); ?>" <?php echo $s['lazy_load'] ? 'loading="lazy"' : ''; ?> />
								<?php endif; ?>
								<figcaption>
									<?php if ( $icon ) : ?><span class="dashicons <?php echo esc_attr( $icon ); ?>"></span><?php endif; ?>
									<span><?php echo esc_html( $img->post_title ); ?></span>
								</figcaption>
							</a>
						</figure>
						<?php
					endforeach;
				endforeach;
				?>
			</div>
		</div>
		<?php
		$html = ob_get_clean();
		set_transient( $cache_key, $html, HOUR_IN_SECONDS );
		return $html;
	}
}
