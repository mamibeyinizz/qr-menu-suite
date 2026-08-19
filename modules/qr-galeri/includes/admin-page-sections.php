<?php
/**
 * Galeri bölümleri yönetim ekranı şablonu.
 *
 * @package QR_Menu_Suite
 * @var WP_Post[] $sections
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap qrmgm-wrap">
	<h1 class="qrmgm-title">
		Galeri Bölümleri
		<button type="button" class="button button-primary" id="qrmgm-new-section">+ Yeni Bölüm Ekle</button>
	</h1>

	<table class="widefat striped qrmgm-table" id="qrmgm-sections-table">
		<thead>
			<tr>
				<th style="width:24px;"></th>
				<th>Kapak</th>
				<th>Başlık</th>
				<th>Slug</th>
				<th>Görsel Sayısı</th>
				<th>Durum</th>
				<th style="width:220px;">İşlem</th>
			</tr>
		</thead>
		<tbody id="qrmgm-sections-sortable">
		<?php foreach ( $sections as $section ) :
			$cover_id  = (int) get_post_meta( $section->ID, '_qrmgm_cover_id', true );
			$cover_url = $cover_id ? wp_get_attachment_image_url( $cover_id, 'thumbnail' ) : '';
			$icon      = get_post_meta( $section->ID, '_qrmgm_icon', true );
			$bg        = get_post_meta( $section->ID, '_qrmgm_bg_color', true ) ?: '#0F172A';
			$fg        = get_post_meta( $section->ID, '_qrmgm_text_color', true ) ?: '#FFFFFF';
			$count     = count( get_posts( [ 'post_type' => QRMenu_Gallery_Manager::CPT_IMAGE, 'post_parent' => $section->ID, 'post_status' => [ 'publish', 'draft' ], 'posts_per_page' => -1, 'fields' => 'ids' ] ) );
			$active    = 'publish' === $section->post_status;
			?>
			<tr data-id="<?php echo esc_attr( $section->ID ); ?>">
				<td class="qrmgm-drag-handle">⠿</td>
				<td>
					<?php if ( $cover_url ) : ?>
						<img src="<?php echo esc_url( $cover_url ); ?>" style="width:48px;height:48px;object-fit:cover;border-radius:8px;" alt="" />
					<?php else : ?>
						<span class="dashicons <?php echo esc_attr( $icon ?: 'dashicons-format-image' ); ?>" style="font-size:32px;color:<?php echo esc_attr( $bg ); ?>"></span>
					<?php endif; ?>
				</td>
				<td><strong><?php echo esc_html( $section->post_title ); ?></strong></td>
				<td><code><?php echo esc_html( $section->post_name ); ?></code></td>
				<td><?php echo (int) $count; ?></td>
				<td>
					<label class="qrmgm-switch">
						<input type="checkbox" class="qrmgm-toggle-section" data-id="<?php echo esc_attr( $section->ID ); ?>" <?php checked( $active ); ?> />
						<span class="qrmgm-slider"></span>
					</label>
				</td>
				<td>
					<button class="button qrmgm-edit-section"
						data-id="<?php echo esc_attr( $section->ID ); ?>"
						data-title="<?php echo esc_attr( $section->post_title ); ?>"
						data-slug="<?php echo esc_attr( $section->post_name ); ?>"
						data-desc="<?php echo esc_attr( $section->post_excerpt ); ?>"
						data-icon="<?php echo esc_attr( $icon ); ?>"
						data-bg="<?php echo esc_attr( $bg ); ?>"
						data-fg="<?php echo esc_attr( $fg ); ?>"
						data-cover="<?php echo esc_attr( $cover_id ); ?>"
						data-cover-url="<?php echo esc_url( $cover_url ); ?>">Düzenle</button>
					<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=qrmgm-images&section=' . $section->ID ) ); ?>">Galeriyi Yönet</a>
					<button class="button qrmgm-delete-section" data-id="<?php echo esc_attr( $section->ID ); ?>">Sil</button>
				</td>
			</tr>
		<?php endforeach; ?>
		<?php if ( empty( $sections ) ) : ?>
			<tr><td colspan="7">Henüz bölüm eklenmedi.</td></tr>
		<?php endif; ?>
		</tbody>
	</table>

	<p><code>[qrmenu_gallery]</code> — tüm bölümler. <code>[qrmenu_gallery section="slug"]</code> — sadece ilgili bölüm.</p>
</div>

<div id="qrmgm-section-modal" class="qrmgm-modal" style="display:none;">
	<div class="qrmgm-modal-box">
		<h2 id="qrmgm-modal-title">Yeni Bölüm</h2>
		<input type="hidden" id="qrmgm-s-id" value="0" />
		<p><label>Başlık<br /><input type="text" id="qrmgm-s-title" class="widefat" /></label></p>
		<p><label>Slug (boş bırakılırsa otomatik oluşturulur)<br /><input type="text" id="qrmgm-s-slug" class="widefat" /></label></p>
		<p><label>Kısa Açıklama<br /><textarea id="qrmgm-s-desc" class="widefat" rows="2"></textarea></label></p>
		<p><label>İkon (dashicon class, örn: dashicons-food)<br /><input type="text" id="qrmgm-s-icon" class="widefat" placeholder="dashicons-food" /></label></p>
		<p>
			<label>Arka Plan Rengi <input type="text" id="qrmgm-s-bg" class="qrmgm-color" value="#0F172A" /></label>
			<label style="margin-left:16px;">Yazı Rengi <input type="text" id="qrmgm-s-fg" class="qrmgm-color" value="#FFFFFF" /></label>
		</p>
		<p>
			<button type="button" class="button" id="qrmgm-s-cover-btn">Kapak Görseli Seç</button>
			<span id="qrmgm-s-cover-preview"></span>
			<input type="hidden" id="qrmgm-s-cover" value="0" />
		</p>
		<p class="qrmgm-modal-actions">
			<button type="button" class="button button-primary" id="qrmgm-s-save">Kaydet</button>
			<button type="button" class="button" id="qrmgm-s-cancel">Vazgeç</button>
		</p>
	</div>
</div>
