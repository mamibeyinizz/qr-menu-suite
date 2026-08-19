<?php
/**
 * Galeri AJAX uçları.
 *
 * @package QR_Menu_Suite
 */

defined( 'ABSPATH' ) || exit;

trait QRMGM_Ajax_Trait {

	private function verify_ajax(): void {
		check_ajax_referer( self::NONCE, 'nonce' );
		if ( ! current_user_can( self::CAP ) ) {
			wp_send_json_error( [ 'message' => 'Yetkiniz yok.' ], 403 );
		}
	}

	private function clear_cache(): void {
		global $wpdb;
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_qrmgm_gallery_%' OR option_name LIKE '_transient_timeout_qrmgm_gallery_%'" );
	}

	public function ajax_save_section(): void {
		$this->verify_ajax();

		$id    = absint( $_POST['id'] ?? 0 );
		$title = sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) );
		if ( '' === $title ) {
			wp_send_json_error( [ 'message' => 'Başlık zorunludur.' ] );
		}
		$slug  = sanitize_title( wp_unslash( $_POST['slug'] ?? '' ) ?: $title );
		$desc  = sanitize_textarea_field( wp_unslash( $_POST['desc'] ?? '' ) );
		$icon  = sanitize_html_class( wp_unslash( $_POST['icon'] ?? '' ) );
		$bg    = sanitize_hex_color( wp_unslash( $_POST['bg'] ?? '' ) ) ?: '#0F172A';
		$fg    = sanitize_hex_color( wp_unslash( $_POST['fg'] ?? '' ) ) ?: '#FFFFFF';
		$cover = absint( $_POST['cover'] ?? 0 );

		$post_arr = [
			'post_type'    => self::CPT_SECTION,
			'post_title'   => $title,
			'post_name'    => $slug,
			'post_excerpt' => $desc,
			'post_status'  => 'publish',
		];

		if ( $id > 0 ) {
			$post_arr['ID'] = $id;
			$new_id         = wp_update_post( wp_slash( $post_arr ), true );
		} else {
			$max                    = get_posts( [ 'post_type' => self::CPT_SECTION, 'orderby' => 'menu_order', 'order' => 'DESC', 'posts_per_page' => 1, 'post_status' => [ 'publish', 'draft' ] ] );
			$post_arr['menu_order'] = ! empty( $max ) ? ( (int) $max[0]->menu_order + 1 ) : 0;
			$new_id                 = wp_insert_post( wp_slash( $post_arr ), true );
		}

		if ( is_wp_error( $new_id ) ) {
			wp_send_json_error( [ 'message' => $new_id->get_error_message() ] );
		}

		update_post_meta( $new_id, '_qrmgm_icon', $icon );
		update_post_meta( $new_id, '_qrmgm_bg_color', $bg );
		update_post_meta( $new_id, '_qrmgm_text_color', $fg );
		update_post_meta( $new_id, '_qrmgm_cover_id', $cover );

		$this->clear_cache();
		wp_send_json_success( [ 'id' => $new_id, 'reload' => true ] );
	}

	public function ajax_delete_section(): void {
		$this->verify_ajax();
		$id = absint( $_POST['id'] ?? 0 );
		if ( ! $id ) {
			wp_send_json_error();
		}
		$images = get_posts( [ 'post_type' => self::CPT_IMAGE, 'post_parent' => $id, 'posts_per_page' => -1, 'post_status' => [ 'publish', 'draft' ], 'fields' => 'ids' ] );
		foreach ( $images as $img_id ) {
			$att_id = (int) get_post_meta( $img_id, '_qrmgm_attachment_id', true );
			if ( $att_id ) {
				wp_delete_attachment( $att_id, true );
			}
			wp_delete_post( $img_id, true );
		}
		wp_delete_post( $id, true );
		$this->clear_cache();
		wp_send_json_success();
	}

	public function ajax_toggle_section_status(): void {
		$this->verify_ajax();
		$id     = absint( $_POST['id'] ?? 0 );
		$active = ! empty( $_POST['active'] );
		wp_update_post( [ 'ID' => $id, 'post_status' => $active ? 'publish' : 'draft' ] );
		$this->clear_cache();
		wp_send_json_success();
	}

	public function ajax_reorder_sections(): void {
		$this->verify_ajax();
		$order = isset( $_POST['order'] ) ? array_map( 'absint', (array) $_POST['order'] ) : [];
		foreach ( $order as $index => $id ) {
			wp_update_post( [ 'ID' => $id, 'menu_order' => $index ] );
		}
		$this->clear_cache();
		wp_send_json_success();
	}

	public function ajax_get_section_images(): void {
		$this->verify_ajax();
		$section_id = absint( $_POST['section_id'] ?? 0 );
		ob_start();
		$this->render_admin_image_cards( $section_id );
		$html = ob_get_clean();
		wp_send_json_success( [ 'html' => $html ] );
	}

	public function ajax_upload_image(): void {
		$this->verify_ajax();

		$section_id = absint( $_POST['section_id'] ?? 0 );
		if ( ! $section_id || 'qrmgm_section' !== get_post_type( $section_id ) ) {
			wp_send_json_error( [ 'message' => 'Geçersiz bölüm.' ] );
		}

		if ( empty( $_FILES['file'] ) || ! isset( $_FILES['file']['tmp_name'] ) ) {
			wp_send_json_error( [ 'message' => 'Dosya bulunamadı.' ] );
		}

		$allowed = [ 'image/jpeg', 'image/png', 'image/webp' ];
		$type    = mime_content_type( $_FILES['file']['tmp_name'] );
		if ( ! in_array( $type, $allowed, true ) ) {
			wp_send_json_error( [ 'message' => 'Desteklenmeyen dosya türü.' ] );
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$att_id = media_handle_upload( 'file', 0 );
		if ( is_wp_error( $att_id ) ) {
			wp_send_json_error( [ 'message' => $att_id->get_error_message() ] );
		}

		if ( $this->get_settings()['webp'] ) {
			$this->generate_webp( $att_id );
		}

		$max        = get_posts( [ 'post_type' => self::CPT_IMAGE, 'post_parent' => $section_id, 'orderby' => 'menu_order', 'order' => 'DESC', 'posts_per_page' => 1, 'post_status' => [ 'publish', 'draft' ] ] );
		$menu_order = ! empty( $max ) ? ( (int) $max[0]->menu_order + 1 ) : 0;
		$title      = get_the_title( $att_id ) ?: 'Görsel';

		$image_id = wp_insert_post( wp_slash( [
			'post_type'   => self::CPT_IMAGE,
			'post_parent' => $section_id,
			'post_title'  => $title,
			'post_status' => 'publish',
			'menu_order'  => $menu_order,
		] ), true );

		if ( is_wp_error( $image_id ) ) {
			wp_send_json_error( [ 'message' => $image_id->get_error_message() ] );
		}

		update_post_meta( $image_id, '_qrmgm_attachment_id', $att_id );
		update_post_meta( $image_id, '_qrmgm_alt', get_post_meta( $att_id, '_wp_attachment_image_alt', true ) );
		update_post_meta( $image_id, '_qrmgm_featured', 0 );

		$this->clear_cache();
		wp_send_json_success( [ 'id' => $image_id ] );
	}

	private function generate_webp( int $attachment_id ): void {
		$file = get_attached_file( $attachment_id );
		if ( ! $file || ! file_exists( $file ) ) {
			return;
		}
		$path_info = pathinfo( $file );
		if ( strtolower( $path_info['extension'] ?? '' ) === 'webp' ) {
			return;
		}
		$webp_path = $path_info['dirname'] . '/' . $path_info['filename'] . '.webp';
		if ( file_exists( $webp_path ) ) {
			return;
		}
		$editor = wp_get_image_editor( $file );
		if ( is_wp_error( $editor ) ) {
			return;
		}
		if ( ! method_exists( $editor, 'save' ) ) {
			return;
		}
		$result = $editor->save( $webp_path, 'image/webp' );
		if ( ! is_wp_error( $result ) ) {
			update_post_meta( $attachment_id, '_qrmgm_webp_path', $result['path'] );
			update_post_meta( $attachment_id, '_qrmgm_webp_url', str_replace( basename( $file ), basename( $webp_path ), wp_get_attachment_url( $attachment_id ) ) );
		}
	}

	public function ajax_save_image(): void {
		$this->verify_ajax();
		$id = absint( $_POST['id'] ?? 0 );
		if ( ! $id || self::CPT_IMAGE !== get_post_type( $id ) ) {
			wp_send_json_error();
		}
		$title = sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) );
		$alt   = sanitize_text_field( wp_unslash( $_POST['alt'] ?? '' ) );
		$desc  = sanitize_textarea_field( wp_unslash( $_POST['desc'] ?? '' ) );
		$tag   = sanitize_text_field( wp_unslash( $_POST['tag'] ?? '' ) );

		wp_update_post( wp_slash( [ 'ID' => $id, 'post_title' => $title ] ) );
		update_post_meta( $id, '_qrmgm_alt', $alt );
		update_post_meta( $id, '_qrmgm_desc', $desc );
		update_post_meta( $id, '_qrmgm_tag', $tag );

		$att_id = (int) get_post_meta( $id, '_qrmgm_attachment_id', true );
		if ( $att_id ) {
			update_post_meta( $att_id, '_wp_attachment_image_alt', $alt );
		}

		$this->clear_cache();
		wp_send_json_success();
	}

	public function ajax_delete_image(): void {
		$this->verify_ajax();
		$id = absint( $_POST['id'] ?? 0 );
		if ( ! $id ) {
			wp_send_json_error();
		}
		$att_id = (int) get_post_meta( $id, '_qrmgm_attachment_id', true );
		if ( $att_id ) {
			wp_delete_attachment( $att_id, true );
		}
		wp_delete_post( $id, true );
		$this->clear_cache();
		wp_send_json_success();
	}

	public function ajax_toggle_image_status(): void {
		$this->verify_ajax();
		$id     = absint( $_POST['id'] ?? 0 );
		$active = ! empty( $_POST['active'] );
		wp_update_post( [ 'ID' => $id, 'post_status' => $active ? 'publish' : 'draft' ] );
		$this->clear_cache();
		wp_send_json_success();
	}

	public function ajax_toggle_image_featured(): void {
		$this->verify_ajax();
		$id       = absint( $_POST['id'] ?? 0 );
		$featured = ! empty( $_POST['featured'] );
		update_post_meta( $id, '_qrmgm_featured', $featured ? 1 : 0 );
		$this->clear_cache();
		wp_send_json_success();
	}

	public function ajax_duplicate_image(): void {
		$this->verify_ajax();
		$id = absint( $_POST['id'] ?? 0 );
		$original = get_post( $id );
		if ( ! $original || self::CPT_IMAGE !== $original->post_type ) {
			wp_send_json_error();
		}
		$new_id = wp_insert_post( wp_slash( [
			'post_type'   => self::CPT_IMAGE,
			'post_parent' => $original->post_parent,
			'post_title'  => $original->post_title . ' (Kopya)',
			'post_status' => $original->post_status,
			'menu_order'  => (int) $original->menu_order + 1,
		] ), true );
		if ( is_wp_error( $new_id ) ) {
			wp_send_json_error();
		}
		foreach ( [ '_qrmgm_attachment_id', '_qrmgm_alt', '_qrmgm_desc', '_qrmgm_tag', '_qrmgm_featured' ] as $meta_key ) {
			update_post_meta( $new_id, $meta_key, get_post_meta( $id, $meta_key, true ) );
		}
		$this->clear_cache();
		wp_send_json_success( [ 'reload' => true ] );
	}

	public function ajax_reorder_images(): void {
		$this->verify_ajax();
		$order = isset( $_POST['order'] ) ? array_map( 'absint', (array) $_POST['order'] ) : [];
		foreach ( $order as $index => $id ) {
			wp_update_post( [ 'ID' => $id, 'menu_order' => $index ] );
		}
		$this->clear_cache();
		wp_send_json_success();
	}

	public function ajax_save_settings(): void {
		$this->verify_ajax();
		$this->save_settings_from_request( $_POST );
		wp_send_json_success();
	}
}
