<?php
/**
 * Header Footer Builder — Elementor uyumluluğu.
 *
 * Üç ayrı çakışma noktası var, üçü de burada toplandı:
 *
 * 1. Elementor Pro'nun Theme Builder'ı "header"/"footer" konumu için etkin
 *    bir şablon taşıyorsa, sayfada ayrıca bu modülün kısa kodu da varsa
 *    ekranda İKİ header çıkar. `theme_location_has_template()` bunu görür
 *    ve modül sessizce çekilir.
 * 2. Elementor editör/önizleme çerçevesinde widget'lar AJAX ile yeniden
 *    basılır; DOMContentLoaded bir daha çalışmaz. Frontend JS bu yüzden
 *    idempotent init + `elementor/frontend/init` kancasıyla çalışır
 *    (bkz. assets/js/frontend.js) ve editörde gövde kaydırma kilidi
 *    kurulmaz.
 * 3. Yönetim varlıkları yalnızca modülün kendi ayar sayfasında yüklenir;
 *    Elementor editörü de bir admin ekranı olduğu için bu ayrım şart.
 *
 * @package QR_Menu_Suite
 */

defined( 'ABSPATH' ) || exit;

trait QRMS_HFB_Elementor {

	/**
	 * Elementor yüklü mü?
	 *
	 * @return bool
	 */
	public function elementor_loaded() {
		return did_action( 'elementor/loaded' ) || class_exists( '\Elementor\Plugin' );
	}

	/**
	 * Elementor editör veya önizleme modunda mıyız?
	 *
	 * Editörde modül render EDİLİR (kullanıcı ne düzenlediğini görsün) ama
	 * scroll kilidi gibi sayfa çapında yan etkiler devre dışı kalır.
	 *
	 * @return bool
	 */
	public function elementor_is_edit_mode() {
		if ( ! $this->elementor_loaded() || ! class_exists( '\Elementor\Plugin' ) ) {
			return false;
		}

		try {
			$plugin = \Elementor\Plugin::$instance;

			if ( ! $plugin ) {
				return false;
			}

			if ( isset( $plugin->editor ) && method_exists( $plugin->editor, 'is_edit_mode' ) && $plugin->editor->is_edit_mode() ) {
				return true;
			}

			if ( isset( $plugin->preview ) && method_exists( $plugin->preview, 'is_preview_mode' ) && $plugin->preview->is_preview_mode() ) {
				return true;
			}
		} catch ( \Exception $e ) {
			return false;
		} catch ( \Error $e ) {
			return false;
		}

		return false;
	}

	/**
	 * Elementor Theme Builder'da bu konum için etkin şablon var mı?
	 *
	 * @param string $location 'header' veya 'footer'.
	 * @return bool
	 */
	public function theme_location_has_template( $location ) {
		if ( ! $this->elementor_loaded() ) {
			return false;
		}

		if ( ! class_exists( '\ElementorPro\Modules\ThemeBuilder\Module' ) ) {
			return false;
		}

		try {
			$module = \ElementorPro\Modules\ThemeBuilder\Module::instance();

			if ( ! $module || ! method_exists( $module, 'get_conditions_manager' ) ) {
				return false;
			}

			$manager = $module->get_conditions_manager();

			if ( ! $manager || ! method_exists( $manager, 'get_documents_for_location' ) ) {
				return false;
			}

			return ! empty( $manager->get_documents_for_location( $location ) );
		} catch ( \Exception $e ) {
			return false;
		} catch ( \Error $e ) {
			return false;
		}
	}

	/**
	 * Elementor Pro Theme Builder şablonlarından biri (header/footer/tekil/
	 * arşiv/arama/404) verilen kısa kod etiketini içeriyor mu?
	 *
	 * `[hfb_header]` / `[hfb_footer]` çoğunlukla sitenin TÜM sayfalarında
	 * görünür ve genelde geçerli sayfanın kendi `post_content`'inde değil,
	 * ayrı bir Theme Builder şablonunda (ya da tema dosyasına gömülü
	 * `do_shortcode()` çağrısında) bulunur. Arşiv/ana sayfa gibi
	 * `is_singular()` olmayan isteklerde tespit edilebilecek tek yol budur.
	 * Elementor Pro API'sine class_exists()/method_exists() ile temkinli
	 * erişilir; herhangi bir katman eksikse sessizce false döner —
	 * `maybe_enqueue_frontend_assets()` yine yalnızca 'yedek' enqueue'ya
	 * (shortcode callback) güvenmeye devam eder.
	 *
	 * @param string $tag Kısa kod etiketi.
	 * @return bool
	 */
	public function theme_builder_documents_contain( $tag ) {
		if ( ! $this->elementor_loaded() ) {
			return false;
		}

		if ( ! class_exists( '\ElementorPro\Modules\ThemeBuilder\Module' ) ) {
			return false;
		}

		try {
			$module = \ElementorPro\Modules\ThemeBuilder\Module::instance();

			if ( ! $module || ! method_exists( $module, 'get_conditions_manager' ) ) {
				return false;
			}

			$manager = $module->get_conditions_manager();

			if ( ! $manager || ! method_exists( $manager, 'get_documents_for_location' ) ) {
				return false;
			}

			foreach ( array( 'header', 'footer', 'single', 'archive', 'search-results', '404' ) as $location ) {
				$documents = $manager->get_documents_for_location( $location );

				if ( empty( $documents ) ) {
					continue;
				}

				foreach ( (array) $documents as $document ) {
					$doc_id = ( is_object( $document ) && method_exists( $document, 'get_main_id' ) )
						? (int) $document->get_main_id()
						: ( is_numeric( $document ) ? (int) $document : 0 );

					if ( ! $doc_id ) {
						continue;
					}

					$data = get_post_meta( $doc_id, '_elementor_data', true );
					if ( is_string( $data ) && false !== strpos( $data, $tag ) ) {
						return true;
					}
				}
			}
		} catch ( \Exception $e ) {
			return false;
		} catch ( \Error $e ) {
			return false;
		}

		return false;
	}

	/**
	 * Bu bölüm bu istekte render edilmeli mi?
	 *
	 * İki fren: (a) aynı istekte ikinci kez çağrı, (b) Elementor Theme
	 * Builder'ın aynı konumu zaten dolduruyor olması. Site sahibi ikisini
	 * bilerek üst üste kullanmak isterse `qrms_hfb_should_render` filtresi
	 * son sözü söyler.
	 *
	 * @param string $section 'header' veya 'footer'.
	 * @return bool
	 */
	public function should_render( $section ) {
		$allowed = ! isset( $this->rendered[ $section ] ) || ! $this->rendered[ $section ];

		if ( $allowed && $this->theme_location_has_template( $section ) ) {
			$allowed = false;
		}

		/**
		 * Header/footer çıktısını son anda açıp kapatır.
		 *
		 * @param bool   $allowed Render edilsin mi?
		 * @param string $section 'header' veya 'footer'.
		 */
		return (bool) apply_filters( 'qrms_hfb_should_render', $allowed, $section );
	}

	/**
	 * Bölümü render edildi olarak işaretler.
	 *
	 * @param string $section 'header' veya 'footer'.
	 * @return void
	 */
	public function mark_rendered( $section ) {
		$this->rendered[ $section ] = true;
	}
}
