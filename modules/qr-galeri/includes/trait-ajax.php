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

	/**
	 * Bir ID'nin gerçekten beklenen CPT'ye ait olduğunu doğrular.
	 *
	 * GÜVENLİK: AJAX uçlarına gelen ID'ler doğrudan istemciden gelir; bu
	 * kontrol olmadan bir uç, galeri dışındaki herhangi bir post'u (sayfa,
	 * ürün, vb.) değiştirebilir veya silebilir.
	 */
	private function is_post_type( int $id, string $type ): bool {
		return $id > 0 && $type === get_post_type( $id );
	}

	/**
	 * Önbellek sürüm sayacı. Anahtar bu sürümü içerdiği için sürümü artırmak
	 * yeni bir önbellek anahtarı üretir ve eskisini fiilen geçersiz kılar —
	 * kalıcı nesne önbelleğinde (Redis/Memcached) transient'lar wp_options
	 * tablosuna hiç yazılmadığı için bu, DELETE sorgusunun işe yaramadığı
	 * durumlarda da güvenilir şekilde çalışır.
	 */
	public function cache_version(): int {
		return (int) get_option( 'qrmgm_cache_version', 1 );
	}

	private function bump_cache_version(): void {
		update_option( 'qrmgm_cache_version', $this->cache_version() + 1, false );
	}

	private function clear_cache(): void {
		global $wpdb;
		$this->bump_cache_version();
		// Standart dosya tabanlı önbellekte eski satırları hemen temizler;
		// nesne önbelleğinde no-op'tur ama zararsızdır (sürüm artışı asıl
		// geçersiz kılma mekanizmasıdır).
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_qrmgm_gallery_%' OR option_name LIKE '_transient_timeout_qrmgm_gallery_%'" );
	}

	/**
	 * Bir attachment'ın, silinmekte olan galeri görselleri HARİÇ başka bir
	 * qrmgm_image tarafından hâlâ kullanılıp kullanılmadığını kontrol eder.
	 *
	 * BUG: Çoğaltılmış (duplicate) görseller aynı attachment'ı paylaşır; bu
	 * kontrol olmadan biri silindiğinde attachment diskten kalıcı olarak
	 * silinir ve paylaşan kardeş kayıt kırık görsele düşer.
	 *
	 * @param int   $attachment_id Kontrol edilecek attachment ID.
	 * @param int[] $exclude_ids   Bu silme işleminde silinmekte olan qrmgm_image ID'leri.
	 */
	private function attachment_referenced_elsewhere( int $attachment_id, array $exclude_ids ): bool {
		if ( ! $attachment_id ) {
			return false;
		}
		$others = get_posts( [
			'post_type'      => self::CPT_IMAGE,
			'post_status'    => [ 'publish', 'draft' ],
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'post__not_in'   => array_map( 'absint', $exclude_ids ),
			'meta_key'       => '_qrmgm_attachment_id',
			'meta_value'     => $attachment_id,
		] );
		return ! empty( $others );
	}

	/**
	 * Attachment'ı yalnızca başka hiçbir galeri görseli tarafından
	 * paylaşılmıyorsa siler; paylaşılıyorsa kardeş kaydı korumak için
	 * attachment'a dokunmaz (bkz. attachment_referenced_elsewhere).
	 */
	private function delete_image_attachment_if_unshared( int $attachment_id, array $exclude_ids ): void {
		if ( $attachment_id && ! $this->attachment_referenced_elsewhere( $attachment_id, $exclude_ids ) ) {
			wp_delete_attachment( $attachment_id, true );
		}
	}

	/**
	 * Modülün ürettiği WebP dosyasını, attachment (medya kitaplığından
	 * doğrudan da olsa) silindiğinde temizler. `delete_attachment` çekirdek
	 * kancasına bağlanır; böylece galerinin kendi silme uçlarından bağımsız
	 * olarak da orphan .webp dosyası kalmaz.
	 */
	public function cleanup_webp_for_attachment( int $attachment_id ): void {
		$webp_path = get_post_meta( $attachment_id, '_qrmgm_webp_path', true );
		if ( ! $webp_path || ! is_string( $webp_path ) ) {
			return;
		}
		$normalized = wp_normalize_path( $webp_path );
		// GÜVENLİK: yalnızca uploads dizini içindeki .webp uzantılı dosya
		// silinir; meta manipüle edilmiş olsa da dizin dışına çıkılamaz.
		if ( 'webp' !== strtolower( pathinfo( $normalized, PATHINFO_EXTENSION ) ) ) {
			return;
		}
		$uploads = wp_upload_dir();
		if ( empty( $uploads['basedir'] ) || 0 !== strpos( $normalized, wp_normalize_path( $uploads['basedir'] ) ) ) {
			return;
		}
		if ( file_exists( $normalized ) ) {
			@unlink( $normalized );
		}
	}

	public function ajax_save_section(): void {
		$this->verify_ajax();

		$id = absint( $_POST['id'] ?? 0 );
		// GÜVENLİK: ID>0 ise bu bir düzenlemedir; tip kontrolü olmadan bu uç
		// başka bir post'un başlığını/slug'ını/durumunu ezebilirdi.
		if ( $id && ! $this->is_post_type( $id, self::CPT_SECTION ) ) {
			wp_send_json_error( [ 'message' => 'Geçersiz bölüm.' ] );
		}
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
		// GÜVENLİK: ID doğrudan POST'tan geliyor; tip kontrolü olmadan bu uç
		// galeri bölümü dışındaki HERHANGİ bir post'u (sayfa, ürün, vb.)
		// kalıcı olarak silmek için kullanılabilirdi.
		if ( ! $this->is_post_type( $id, self::CPT_SECTION ) ) {
			wp_send_json_error();
		}
		$images = get_posts( [ 'post_type' => self::CPT_IMAGE, 'post_parent' => $id, 'posts_per_page' => -1, 'post_status' => [ 'publish', 'draft' ], 'fields' => 'ids' ] );
		foreach ( $images as $img_id ) {
			$att_id = (int) get_post_meta( $img_id, '_qrmgm_attachment_id', true );
			// $images (bu bölümün tüm görselleri) dışlanarak kontrol edilir;
			// böylece aynı bölümdeki bir çift (duplicate) da doğru ele alınır.
			$this->delete_image_attachment_if_unshared( $att_id, $images );
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
		// GÜVENLİK: tip kontrolü olmadan bu uç herhangi bir post'un durumunu
		// (ör. yayınlanmış bir sayfayı taslağa) değiştirebilirdi.
		if ( ! $this->is_post_type( $id, self::CPT_SECTION ) ) {
			wp_send_json_error();
		}
		wp_update_post( [ 'ID' => $id, 'post_status' => $active ? 'publish' : 'draft' ] );
		$this->clear_cache();
		wp_send_json_success();
	}

	public function ajax_reorder_sections(): void {
		$this->verify_ajax();
		$order = isset( $_POST['order'] ) ? array_map( 'absint', (array) $_POST['order'] ) : [];
		foreach ( $order as $index => $id ) {
			// GÜVENLİK: yalnızca galeri bölümlerinin sırası değiştirilebilir.
			if ( ! $this->is_post_type( $id, self::CPT_SECTION ) ) {
				continue;
			}
			wp_update_post( [ 'ID' => $id, 'menu_order' => $index ] );
		}
		$this->clear_cache();
		wp_send_json_success();
	}

	public function ajax_get_section_images(): void {
		$this->verify_ajax();
		$section_id = absint( $_POST['section_id'] ?? 0 );
		// GÜVENLİK: geçersiz/başka türden bir ID ile keyfi post'un
		// alt kayıtlarının sızdırılmasını önler.
		if ( ! $this->is_post_type( $section_id, self::CPT_SECTION ) ) {
			wp_send_json_error();
		}
		ob_start();
		$this->render_admin_image_cards( $section_id );
		$html = ob_get_clean();
		wp_send_json_success( [ 'html' => $html ] );
	}

	public function ajax_upload_image(): void {
		$this->verify_ajax();

		$section_id = absint( $_POST['section_id'] ?? 0 );
		if ( ! $this->is_post_type( $section_id, self::CPT_SECTION ) ) {
			wp_send_json_error( [ 'message' => 'Geçersiz bölüm.' ] );
		}

		if ( empty( $_FILES['file'] ) || ! isset( $_FILES['file']['tmp_name'] ) ) {
			wp_send_json_error( [ 'message' => 'Dosya bulunamadı.' ] );
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		// WordPress'in kendi dosya-türü doğrulaması: gerçek dosya içeriğini
		// uzantıyla karşılaştırır (mime_content_type()'a göre daha güvenli —
		// ext-fileinfo'ya sabit bağımlılık da yaratmaz).
		$allowed  = [ 'image/jpeg', 'image/png', 'image/webp' ];
		$filetype = wp_check_filetype_and_ext( $_FILES['file']['tmp_name'], $_FILES['file']['name'] ?? '' );
		if ( empty( $filetype['type'] ) || ! in_array( $filetype['type'], $allowed, true ) ) {
			wp_send_json_error( [ 'message' => 'Desteklenmeyen dosya türü.' ] );
		}

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
		if ( ! $this->is_post_type( $id, self::CPT_IMAGE ) ) {
			wp_send_json_error();
		}
		$att_id = (int) get_post_meta( $id, '_qrmgm_attachment_id', true );
		// Bir çift (duplicate) tarafından hâlâ kullanılıyorsa attachment'a
		// dokunulmaz (bkz. attachment_referenced_elsewhere).
		$this->delete_image_attachment_if_unshared( $att_id, [ $id ] );
		wp_delete_post( $id, true );
		$this->clear_cache();
		wp_send_json_success();
	}

	public function ajax_toggle_image_status(): void {
		$this->verify_ajax();
		$id     = absint( $_POST['id'] ?? 0 );
		$active = ! empty( $_POST['active'] );
		// GÜVENLİK: tip kontrolü olmadan bu uç herhangi bir post'un durumunu
		// değiştirebilirdi.
		if ( ! $this->is_post_type( $id, self::CPT_IMAGE ) ) {
			wp_send_json_error();
		}
		wp_update_post( [ 'ID' => $id, 'post_status' => $active ? 'publish' : 'draft' ] );
		$this->clear_cache();
		wp_send_json_success();
	}

	public function ajax_toggle_image_featured(): void {
		$this->verify_ajax();
		$id       = absint( $_POST['id'] ?? 0 );
		$featured = ! empty( $_POST['featured'] );
		// GÜVENLİK: tip kontrolü olmadan bu uç herhangi bir post'a meta
		// yazabilirdi.
		if ( ! $this->is_post_type( $id, self::CPT_IMAGE ) ) {
			wp_send_json_error();
		}
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
			// GÜVENLİK: yalnızca galeri görsellerinin sırası değiştirilebilir
			// (ör. bir sayfanın menu_order'ını bozmasın).
			if ( ! $this->is_post_type( $id, self::CPT_IMAGE ) ) {
				continue;
			}
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
