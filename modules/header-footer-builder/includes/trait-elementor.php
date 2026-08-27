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
