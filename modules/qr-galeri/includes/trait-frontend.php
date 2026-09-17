<?php
/**
 * Galeri ön yüz kısa kodu.
 *
 * @package QR_Menu_Suite
 */

defined( 'ABSPATH' ) || exit;

trait QRMGM_Frontend_Trait {

	public function maybe_frontend_assets(): void {
		wp_register_style( 'qrmgm-front', false );
		wp_register_script( 'qrmgm-front', false, [], QRMGM_VERSION, true );

		// Kısa kod bu istekte kesin olarak tespit edilebiliyorsa (normal
		// sayfa içeriği veya Elementor'un Shortcode widget'ı) CSS/JS'i
		// burada, wp_head'den önce enqueue et — böylece <link> ilk paint'ten
		// önce basılır. Tespit edilemezse (ör. Elementor Theme Builder
		// şablonu, arşiv, dinamik render) render_shortcode() içindeki
		// ensure_frontend_assets() çağrısı geriye dönük uyumlu yedek olarak
		// kalır.
		if ( $this->should_load_gallery_assets() ) {
			$this->ensure_frontend_assets();
		}
	}

	/**
	 * Bu istekte `[qrmenu_gallery]` render edilecek mi?
	 *
	 * Yalnızca geçerli tekil yazının kendi `post_content`'ine ve (Elementor
	 * yüklüyse) `_elementor_data`'sına bakar; bu iki kaynağın dışında bir
	 * yerde (ör. Theme Builder şablonu) kullanılan galeri hâlâ
	 * ensure_frontend_assets()'in render anındaki yedek çağrısıyla
	 * çalışmaya devam eder.
	 *
	 * @return bool
	 */
	private function should_load_gallery_assets(): bool {
		$post = get_post();

		if ( ! $post instanceof WP_Post ) {
			return false;
		}

		if ( has_shortcode( $post->post_content, 'qrmenu_gallery' ) ) {
			return true;
		}

		if ( did_action( 'elementor/loaded' ) || class_exists( '\Elementor\Plugin' ) ) {
			$data = get_post_meta( $post->ID, '_elementor_data', true );
			if ( $this->elementor_data_contains( 'qrmenu_gallery', $data ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Bir `_elementor_data` JSON'unda etiket doğrudan mı yoksa Elementor'un
	 * "Global Widget" / "Şablon Ekle" (Insert Template) referansı üzerinden
	 * mi geçiyor arar.
	 *
	 * `[qrmenu_gallery]` sayfanın kendi verisinde DEĞİL, bir Global
	 * Widget/Bölüm veya "Şablon Ekle" widget'ı referansının işaret ettiği
	 * AYRI bir Elementor Library post'unun verisinde olabilir. Bu metod o
	 * referansı bulup hedef post'un verisini de tarar.
	 *
	 * VARSAYIM (gerçek Elementor Pro ortamında doğrulanmadı): referanslar
	 * JSON'da "template_id"/"templateID"/"templateId" anahtarlarından
	 * biriyle sayısal bir ID olarak durur. Eşleşme bulunamazsa metod
	 * sessizce `false` döner; davranış önceki düz metin taramasına geriler.
	 *
	 * Güvenlik: `$stack` ile döngü koruması, `$depth` ile 3 seviyelik sabit
	 * sınır, `static $cache` ile aynı şablon ID'sinin verisi istek boyunca
	 * yalnızca bir kez okunur. Yalnızca referans verilen ID'ler çözülür;
	 * `elementor_library` toplu taranmaz.
	 *
	 * @param string     $tag   Aranan kısa kod etiketi.
	 * @param string     $data  Taranacak ham `_elementor_data` JSON metni.
	 * @param array<int> $stack Geçerli çağrı zincirindeki şablon ID'leri.
	 * @param int        $depth Geçerli derinlik.
	 * @return bool
	 */
	private function elementor_data_contains( $tag, $data, array $stack = array(), $depth = 0 ): bool {
		static $cache = array();

		if ( ! is_string( $data ) || '' === $data ) {
			return false;
		}

		if ( false !== strpos( $data, $tag ) ) {
			return true;
		}

		if ( $depth >= 3 ) {
			return false;
		}

		if ( ! preg_match_all( '/"template(?:_id|ID|Id)"\s*:\s*"?(\d+)"?/', $data, $matches ) ) {
			return false;
		}

		foreach ( array_unique( $matches[1] ) as $template_id ) {
			$template_id = (int) $template_id;

			if ( $template_id <= 0 || in_array( $template_id, $stack, true ) ) {
				continue;
			}

			if ( ! array_key_exists( $template_id, $cache ) ) {
				$raw = get_post_meta( $template_id, '_elementor_data', true );
				$cache[ $template_id ] = ( is_string( $raw ) && '' !== $raw ) ? $raw : false;
			}

			$referenced_data = $cache[ $template_id ];

			if ( false === $referenced_data ) {
				continue;
			}

			if ( $this->elementor_data_contains( $tag, $referenced_data, array_merge( $stack, array( $template_id ) ), $depth + 1 ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Kısa kod render edilirken CSS/JS'i bir kez enqueue eder.
	 *
	 * Elementor gibi oluşturucularda kısa kod post_content'te görünmez;
	 * bu yüzden varlıklar yalnızca has_shortcode ile değil, gerekirse
	 * render sırasında da (yedek olarak) basılır — bkz.
	 * should_load_gallery_assets() ve maybe_frontend_assets().
	 */
	private function ensure_frontend_assets(): void {
		static $done = false;
		if ( $done ) {
			return;
		}
		$done = true;

		$s = $this->get_settings();

		wp_register_style( 'qrmgm-front', false );
		wp_register_script( 'qrmgm-front', false, [], QRMGM_VERSION, true );

		wp_enqueue_style( 'qrmgm-front' );
		wp_add_inline_style( 'qrmgm-front', $this->frontend_css( $s ) );

		wp_enqueue_script( 'qrmgm-front' );
		wp_add_inline_script( 'qrmgm-front', $this->frontend_js( $s ) );
	}

	public function render_shortcode( $atts ): string {
		$this->ensure_frontend_assets();

		$atts = shortcode_atts(
			[
				'section'     => '',
				'columns'     => '',
				'ratio'       => '',
				'limit'       => 0,
				'filter'      => '',
				'show_titles' => 'yes',
			],
			$atts,
			'qrmenu_gallery'
		);

		$columns = '';
		if ( '' !== $atts['columns'] ) {
			$columns = (string) min( 6, max( 1, absint( $atts['columns'] ) ) );
		}

		$ratio     = '';
		$ratio_raw = sanitize_text_field( $atts['ratio'] );
		if ( preg_match( '/^\d{1,2}\s*\/\s*\d{1,2}$/', $ratio_raw ) ) {
			$ratio = preg_replace( '/\s+/', '', $ratio_raw );
		}

		$limit  = absint( $atts['limit'] );
		$filter = sanitize_key( $atts['filter'] );
		if ( 'yes' !== $filter && 'no' !== $filter ) {
			$filter = '';
		}

		$show_titles = sanitize_key( $atts['show_titles'] );
		if ( 'no' !== $show_titles ) {
			$show_titles = 'yes';
		}

		$s = $this->get_settings();

		$dil       = function_exists( 'rma_get_current_lang' ) ? rma_get_current_lang() : 'tr';
		$surum     = function_exists( 'rma_ceviri_onbellek_surumu' ) ? rma_ceviri_onbellek_surumu() : 0;
		$cache_key = 'qrmgm_gallery_' . md5( implode( '|', [
			'v7',
			$atts['section'],
			$columns,
			$ratio,
			(string) $limit,
			$filter,
			$show_titles,
			$dil,
			(string) $surum,
			(string) $this->cache_version(),
		] ) );
		$cached = get_transient( $cache_key );
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

		$show_filter = (bool) $s['filter_bar'];
		if ( 'no' === $filter ) {
			$show_filter = false;
		} elseif ( 'yes' === $filter ) {
			$show_filter = true;
		}

		$css_vars = [];
		if ( '' !== $columns ) {
			$css_vars[] = '--qrmgm-cols-desktop:' . $columns;
		}
		if ( '' !== $ratio ) {
			$css_vars[] = '--qrmgm-ratio:' . $ratio;
		}

		$hover = in_array( $s['hover_effect'], [ 'none', 'zoom', 'glass', 'lift' ], true ) ? $s['hover_effect'] : 'glass';
		$anim  = empty( $s['animations'] ) ? '0' : '1';

		// PERFORMANS: bölüm başına ayrı get_posts() yerine tüm görseller tek
		// sorguda çekilip post_parent'a göre gruplanır (N+1 sorgu yerine 2).
		$section_ids       = wp_list_pluck( $sections, 'ID' );
		$images_by_section = [];
		if ( ! empty( $section_ids ) ) {
			$all_images = get_posts( [
				'post_type'       => self::CPT_IMAGE,
				'post_parent__in' => $section_ids,
				'post_status'     => 'publish',
				'orderby'         => 'menu_order',
				'order'           => 'ASC',
				'posts_per_page'  => -1,
			] );
			foreach ( $all_images as $img ) {
				$images_by_section[ $img->post_parent ][] = $img;
			}
		}

		ob_start();
		?>
		<div class="qrmgm-gallery" data-lightbox="<?php echo esc_attr( $s['lightbox'] ); ?>" data-hover="<?php echo esc_attr( $hover ); ?>" data-anim="<?php echo esc_attr( $anim ); ?>" data-nav-sticky="<?php echo empty( $s['nav_sticky'] ) ? '0' : '1'; ?>"<?php if ( $css_vars ) : ?> style="<?php echo esc_attr( implode( ';', $css_vars ) ); ?>"<?php endif; ?>>
			<?php if ( $show_filter && count( $sections ) > 1 ) : ?>
				<div class="qrmgm-filter-wrap">
					<div class="qrmgm-filter-bar" role="group" aria-label="<?php echo esc_attr( function_exists( 'rma_ceviri_modul' ) ? rma_ceviri_modul( 'gallery', 'Galeri filtresi' ) : 'Galeri filtresi' ); ?>">
						<button type="button" class="qrmgm-filter-btn is-active" data-filter="all" aria-pressed="true"><?php
							$tumu = function_exists( 'rma_ceviri_modul' ) ? rma_ceviri_modul( 'gallery', 'Tümü' ) : 'Tümü';
							echo esc_html( $tumu );
						?></button>
						<?php foreach ( $sections as $sec ) : ?>
							<button type="button" class="qrmgm-filter-btn" data-filter="<?php echo esc_attr( $sec->post_name ); ?>" aria-pressed="false"><?php echo esc_html( $sec->post_title ); ?></button>
						<?php endforeach; ?>
					</div>
				</div>
			<?php endif; ?>

			<?php
			$counter        = 0;
			$is_first_image = true;
			foreach ( $sections as $sec ) :
				if ( $limit > 0 && $counter >= $limit ) {
					break;
				}
				$images = $images_by_section[ $sec->ID ] ?? [];
				if ( empty( $images ) ) {
					continue;
				}

				$sec_title = $sec->post_title;
				$sec_desc  = trim( (string) $sec->post_excerpt );
				if ( '' === $sec_desc ) {
					$sec_desc = trim( (string) get_post_meta( $sec->ID, '_qrmgm_desc', true ) );
				}
				$sec_icon  = (string) get_post_meta( $sec->ID, '_qrmgm_icon', true );
				if ( function_exists( 'rma_ceviri_modul' ) ) {
					$sec_title = rma_ceviri_modul( 'gallery', $sec_title );
					if ( '' !== $sec_desc ) {
						$sec_desc = rma_ceviri_modul( 'gallery', $sec_desc );
					}
				}
				$heading_id = 'qrmgm-sec-title-' . $sec->ID;
				?>
				<section class="qrmgm-section" data-section="<?php echo esc_attr( $sec->post_name ); ?>"<?php if ( 'yes' === $show_titles ) : ?> aria-labelledby="<?php echo esc_attr( $heading_id ); ?>"<?php endif; ?>>
					<?php if ( 'yes' === $show_titles ) : ?>
						<header class="qrmgm-section-head">
							<h2 class="qrmgm-section-title" id="<?php echo esc_attr( $heading_id ); ?>">
								<?php if ( $sec_icon ) : ?><span class="dashicons <?php echo esc_attr( $sec_icon ); ?>" aria-hidden="true"></span><?php endif; ?>
								<?php echo esc_html( $sec_title ); ?>
							</h2>
							<?php if ( ! empty( $s['divider_show'] ) ) : ?>
								<span class="qrmgm-section-divider" aria-hidden="true"></span>
							<?php endif; ?>
							<?php if ( '' !== trim( $sec_desc ) ) : ?>
								<p class="qrmgm-section-desc"><?php echo esc_html( $sec_desc ); ?></p>
							<?php endif; ?>
						</header>
					<?php endif; ?>

					<div class="qrmgm-grid">
						<?php
						foreach ( $images as $img ) :
							if ( $limit > 0 && $counter >= $limit ) {
								break;
							}
							$att_id = (int) get_post_meta( $img->ID, '_qrmgm_attachment_id', true );
							if ( ! $att_id ) {
								continue;
							}
							$src      = wp_get_attachment_image_src( $att_id, 'large' );
							$full     = wp_get_attachment_image_src( $att_id, 'full' );
							$webp_url = get_post_meta( $att_id, '_qrmgm_webp_url', true );
							$alt      = (string) get_post_meta( $img->ID, '_qrmgm_alt', true );
							$desc     = (string) get_post_meta( $img->ID, '_qrmgm_desc', true );
							$featured = (bool) get_post_meta( $img->ID, '_qrmgm_featured', true );
							if ( ! $src ) {
								continue;
							}
							$caption = trim( $desc );
							if ( '' === $caption ) {
								$caption = trim( $alt );
							}
							// ERİŞİLEBİLİRLİK: anlamlı bir alt her zaman üretilir —
							// özel alt yoksa medya kitaplığı alt'ına, o da yoksa
							// açıklama/altyazıya, o da yoksa bölüm başlığına düşer.
							$img_alt = trim( $alt );
							if ( '' === $img_alt ) {
								$img_alt = (string) get_post_meta( $att_id, '_wp_attachment_image_alt', true );
							}
							if ( '' === trim( $img_alt ) ) {
								$img_alt = '' !== $caption ? $caption : $sec_title;
							}
							$counter++;
							[ $url, $width, $height ] = $src;
							$srcset = wp_get_attachment_image_srcset( $att_id, 'large' );
							$sizes  = $srcset ? wp_get_attachment_image_sizes( $att_id, 'large' ) : '';

							// PERFORMANS: yalnızca ilk kart LCP adayıdır ve hemen
							// yüklenir; sonraki tüm görseller (ayar açıksa) lazy-load.
							if ( $is_first_image ) {
								$loading_attr = 'fetchpriority="high"';
								$is_first_image = false;
							} elseif ( $s['lazy_load'] ) {
								$loading_attr = 'loading="lazy"';
							} else {
								$loading_attr = '';
							}
							?>
							<figure class="qrmgm-item<?php echo $featured ? ' qrmgm-item--featured' : ''; ?>" data-section="<?php echo esc_attr( $sec->post_name ); ?>" data-index="<?php echo esc_attr( $counter ); ?>">
								<a href="<?php echo esc_url( $full[0] ?? $url ); ?>"
								   class="qrmgm-lightbox-trigger"
								   <?php if ( '' !== $caption ) : ?>data-caption="<?php echo esc_attr( $caption ); ?>"<?php endif; ?>
								   data-download="<?php echo esc_url( $full[0] ?? $url ); ?>"
								   aria-label="<?php echo esc_attr( '' !== $caption ? $caption : $img_alt ); ?>">
									<?php if ( $webp_url ) : ?>
										<picture>
											<source srcset="<?php echo esc_url( $webp_url ); ?>" type="image/webp" />
											<img src="<?php echo esc_url( $url ); ?>" <?php if ( $srcset ) : ?>srcset="<?php echo esc_attr( $srcset ); ?>" sizes="<?php echo esc_attr( $sizes ); ?>"<?php endif; ?> alt="<?php echo esc_attr( $img_alt ); ?>" width="<?php echo esc_attr( $width ); ?>" height="<?php echo esc_attr( $height ); ?>" <?php echo $loading_attr; ?> />
										</picture>
									<?php else : ?>
										<img src="<?php echo esc_url( $url ); ?>" <?php if ( $srcset ) : ?>srcset="<?php echo esc_attr( $srcset ); ?>" sizes="<?php echo esc_attr( $sizes ); ?>"<?php endif; ?> alt="<?php echo esc_attr( $img_alt ); ?>" width="<?php echo esc_attr( $width ); ?>" height="<?php echo esc_attr( $height ); ?>" <?php echo $loading_attr; ?> />
									<?php endif; ?>
									<?php if ( '' !== $caption ) : ?>
										<figcaption><?php echo esc_html( $caption ); ?></figcaption>
									<?php endif; ?>
								</a>
							</figure>
							<?php
						endforeach;
						?>
					</div>
				</section>
				<?php
			endforeach;
			?>
		</div>
		<?php

		$html = ob_get_clean();
		set_transient( $cache_key, $html, HOUR_IN_SECONDS );
		return $html;
	}
}
