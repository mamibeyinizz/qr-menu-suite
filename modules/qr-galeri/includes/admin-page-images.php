<?php
/**
 * Tüm görseller yönetim ekranı şablonu.
 *
 * @package QR_Menu_Suite
 * @var WP_Post[] $sections
 * @var int       $current_id
 * @var QRMenu_Gallery_Manager $this
 */

defined( 'ABSPATH' ) || exit;

$sections_page = 'qrmgm-sections';
?>
<div class="wrap qrmgm-wrap">
	<h1 class="qrmgm-title">Tüm Görseller</h1>

	<div class="qrmgm-toolbar">
		<label>Bölüm:
			<select id="qrmgm-section-select">
				<?php foreach ( $sections as $s ) : ?>
					<option value="<?php echo esc_attr( $s->ID ); ?>" <?php selected( $current_id, $s->ID ); ?>><?php echo esc_html( $s->post_title ); ?></option>
				<?php endforeach; ?>
			</select>
		</label>
	</div>

	<?php if ( empty( $sections ) ) : ?>
		<p>Önce bir <a href="<?php echo esc_url( admin_url( 'admin.php?page=' . $sections_page ) ); ?>">Galeri Bölümü</a> oluşturun.</p>
		<?php return; ?>
	<?php endif; ?>

	<div id="qrmgm-dropzone" class="qrmgm-dropzone" data-section="<?php echo esc_attr( $current_id ); ?>">
		<p>Görselleri buraya sürükleyin veya <button type="button" class="button" id="qrmgm-browse-btn">Bilgisayardan Seç</button></p>
		<input type="file" id="qrmgm-file-input" accept="image/jpeg,image/jpg,image/png,image/webp" multiple style="display:none;" />
		<div id="qrmgm-upload-progress"></div>
	</div>

	<div id="qrmgm-images-grid" class="qrmgm-images-grid" data-section="<?php echo esc_attr( $current_id ); ?>">
		<?php $this->render_admin_image_cards( $current_id ); ?>
	</div>
</div>

<div id="qrmgm-image-modal" class="qrmgm-modal" style="display:none;">
	<div class="qrmgm-modal-box">
		<h2>Görseli Düzenle</h2>
		<input type="hidden" id="qrmgm-i-id" value="0" />
		<p><label>Başlık<br /><input type="text" id="qrmgm-i-title" class="widefat" /></label></p>
		<p><label>Alt Text<br /><input type="text" id="qrmgm-i-alt" class="widefat" /></label></p>
		<p><label>Açıklama<br /><textarea id="qrmgm-i-desc" class="widefat" rows="2"></textarea></label></p>
		<p><label>Etiket<br /><input type="text" id="qrmgm-i-tag" class="widefat" /></label></p>
		<p class="qrmgm-modal-actions">
			<button type="button" class="button button-primary" id="qrmgm-i-save">Kaydet</button>
			<button type="button" class="button" id="qrmgm-i-cancel">Vazgeç</button>
		</p>
	</div>
</div>
