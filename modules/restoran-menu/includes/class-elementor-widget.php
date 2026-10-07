<?php
/**
 * QR Restoran Menü — native Elementor widget.
 *
 * Yalnızca Elementor yüklüyse kayıt edilir; çıktı mevcut [restaurant_menu]
 * shortcode altyapısını kullanır.
 *
 * @package QR_Menu_Suite
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Elementor hazır olduğunda widget kaydını planlar.
 */
function rma_bootstrap_elementor_menu_widget() {
	if ( did_action( 'elementor/loaded' ) ) {
		rma_register_elementor_menu_widget_integration();
		return;
	}

	add_action( 'elementor/loaded', 'rma_register_elementor_menu_widget_integration' );
}
add_action( 'plugins_loaded', 'rma_bootstrap_elementor_menu_widget', 20 );

/**
 * Widget sınıfı, kategori ve widgets/register kancalarını bağlar.
 */
function rma_register_elementor_menu_widget_integration() {
	if ( ! class_exists( '\Elementor\Widget_Base' ) ) {
		return;
	}

	if ( ! class_exists( 'RMA_Elementor_Menu_Widget', false ) ) {
		/**
		 * Native Elementor widget — menü çıktısı [restaurant_menu] ile aynı.
		 */
		class RMA_Elementor_Menu_Widget extends \Elementor\Widget_Base {

			public function get_name() {
				return 'rma_menu_widget';
			}

			public function get_title() {
				return __( 'QR Restoran Menüsü', 'qrms' );
			}

			public function get_description() {
				return __( 'Müşteri menüsünü (kategori, ürün, arama, filtre ve ürün detayı) sayfaya ekler. Shortcode ile aynı render altyapısını kullanır.', 'qrms' );
			}

			public function get_icon() {
				return 'eicon-editor-list-ul';
			}

			public function get_categories() {
				return array( 'qrms-restoran-menu' );
			}

			public function get_keywords() {
				return array( 'qr', 'menu', 'restoran', 'restaurant', 'menü', 'qrms' );
			}

			protected function register_controls() {
				$this->start_controls_section(
					'content_section',
					array(
						'label' => __( 'Menü', 'qrms' ),
						'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
					)
				);

				$this->add_control(
					'show_search',
					array(
						'label'   => __( 'Arama çubuğu', 'qrms' ),
						'type'    => \Elementor\Controls_Manager::SWITCHER,
						'default' => 'yes',
					)
				);

				$this->end_controls_section();
			}

			protected function render() {
				$settings    = $this->get_settings_for_display();
				$show_search = ( ( $settings['show_search'] ?? 'yes' ) === 'yes' ) ? 'yes' : 'no';

				echo do_shortcode( '[restaurant_menu show_search="' . esc_attr( $show_search ) . '"]' );
			}
		}
	}

	add_action( 'elementor/elements/categories_registered', 'rma_register_elementor_menu_widget_category' );
	add_action( 'elementor/widgets/register', 'rma_register_elementor_menu_widget_instance' );
	// Elementor < 3.5 uyumluluğu.
	add_action( 'elementor/widgets/widgets_registered', 'rma_register_elementor_menu_widget_instance' );
}

/**
 * Elementor panelinde QR Restoran Menü kategorisi.
 *
 * @param \Elementor\Elements_Manager $elements_manager Yönetici.
 */
function rma_register_elementor_menu_widget_category( $elements_manager ) {
	$elements_manager->add_category(
		'qrms-restoran-menu',
		array(
			'title' => __( 'QR Restoran Menü', 'qrms' ),
			'icon'  => 'eicon-menu-bar',
		)
	);
}

/**
 * Widget örneğini Elementor'a kaydeder.
 *
 * @param \Elementor\Widgets_Manager $widgets_manager Widget yöneticisi.
 */
function rma_register_elementor_menu_widget_instance( $widgets_manager ) {
	static $registered = false;

	if ( $registered || ! class_exists( 'RMA_Elementor_Menu_Widget', false ) ) {
		return;
	}

	if ( method_exists( $widgets_manager, 'register' ) ) {
		$widgets_manager->register( new RMA_Elementor_Menu_Widget() );
	} elseif ( method_exists( $widgets_manager, 'register_widget_type' ) ) {
		$widgets_manager->register_widget_type( new RMA_Elementor_Menu_Widget() );
	}

	$registered = true;
}
