<?php

if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'plugins_loaded', 'rma_register_elementor_addon' );
function rma_register_elementor_addon() {
    if ( ! class_exists( '\Elementor\Widget_Base' ) ) return;

    class RMA_Elementor_Menu_Widget extends \Elementor\Widget_Base {
        public function get_name()       { return 'rma_menu_widget'; }
        public function get_title()      { return 'Restoran Menüsü'; }
        public function get_icon()       { return 'eicon-editor-list-ul'; }
        public function get_categories() { return [ 'general' ]; }

        protected function register_controls() {
            $this->start_controls_section( 'content_section', [
                'label' => 'Ayarlar',
                'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
            ] );
            $this->add_control( 'show_search', [
                'label'   => 'Arama Çubuğu',
                'type'    => \Elementor\Controls_Manager::SWITCHER,
                'default' => 'yes',
            ] );
            $this->end_controls_section();
        }

        protected function render() {
            $settings    = $this->get_settings_for_display();
            $show_search = ( ( $settings['show_search'] ?? 'yes' ) === 'yes' ) ? 'yes' : 'no';
            echo do_shortcode( '[restaurant_menu show_search="' . $show_search . '"]' );
        }
    }

    add_action( 'elementor/widgets/register', function ( $wm ) {
        $wm->register( new RMA_Elementor_Menu_Widget() );
    } );
}