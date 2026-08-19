<?php
/**
 * Yönetim sayfası: QR Menü → Masalar
 *
 * GÜVENLİK: Eski qrkod.php'de masa ekleme ve silme işlemlerinde ne nonce
 * ne de yetki kontrolü vardı — giriş yapmış herhangi bir abone (hatta
 * hazırlanmış bir bağlantıya tıklayan bir yönetici) masa silebiliyordu.
 * Artık her iki işlem de current_user_can('manage_options') +
 * check_admin_referer() ile korunuyor.
 *
 * @package QR_Menu_Official
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Masalar sayfası.
 */
if ( ! function_exists( 'qmo_masalar_sayfasi' ) ) {
	function qmo_masalar_sayfasi() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Bu sayfaya erişim yetkiniz yok.' );
		}

		$bildirim = qmo_masalar_islem();

		if ( ! QMO_Masalar::tablo_var_mi() ) {
			QMO_Masalar::tablo_kur();
		}

		$masalar = QMO_Masalar::hepsi();
		?>
		<div class="wrap qmo-wrap">
			<h1 class="wp-heading-inline">QR Masa Yönetimi</h1>
			<hr class="wp-header-end">

			<?php if ( $bildirim ) : ?>
				<div class="notice notice-<?php echo esc_attr( $bildirim['tip'] ); ?> is-dismissible">
					<p><?php echo esc_html( $bildirim['mesaj'] ); ?></p>
				</div>
			<?php endif; ?>

			<div class="qmo-add-box">
				<h3>Yeni Masa Oluştur</h3>
				<form method="post" action="">
					<?php wp_nonce_field( 'qmo_masa_ekle', 'qmo_nonce' ); ?>
					<input type="text" name="table_name" placeholder="Örn: Masa 1" required class="regular-text">
					<button type="submit" name="qmo_masa_ekle" value="1" class="button button-primary">Oluştur</button>
				</form>
				<p class="description">
					Her masa için ana sayfanıza yönlenen <code>/?masa=masa-1</code> biçiminde
					parametreli özel bir QR kod üretilir. Masa adı bir <strong>metin</strong> slug'a
					dönüşür (<code>Masa 31</code> → <code>masa-31</code>, <code>VIP Salon</code> → <code>vip-salon</code>).
				</p>
			</div>

			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th style="width:50px;">ID</th>
						<th>Masa Adı</th>
						<th>URL Parametresi</th>
						<th style="width:100px;">QR Kod</th>
						<th style="width:280px;">İndirme & İşlemler</th>
					</tr>
				</thead>
				<tbody>
					<?php if ( $masalar ) : ?>
						<?php foreach ( $masalar as $t ) : ?>
							<?php
							$hedef   = QMO_Masalar::url( $t->table_slug );
							$sil_url = wp_nonce_url(
								admin_url( 'admin.php?page=' . QRMS_Admin::get_module_page_slug( 'qr-masa' ) . '&action=delete&id=' . (int) $t->id ),
								'qmo_masa_sil_' . (int) $t->id
							);
							?>
							<tr class="qmo-row"
								data-name="<?php echo esc_attr( $t->table_name ); ?>"
								data-url="<?php echo esc_url( $hedef ); ?>">
								<td><?php echo (int) $t->id; ?></td>
								<td><strong><?php echo esc_html( $t->table_name ); ?></strong></td>
								<td><code><?php echo esc_html( $hedef ); ?></code></td>
								<td><img class="qmo-qr-preview" src="" alt="QR"></td>
								<td>
									<button type="button" class="button qmo-dl-png qmo-action-btn">
										<span class="dashicons dashicons-format-image"></span> PNG
									</button>
									<button type="button" class="button qmo-dl-pdf qmo-action-btn">
										<span class="dashicons dashicons-media-document"></span> PDF
									</button>
									<a href="<?php echo esc_url( $sil_url ); ?>" class="button qmo-sil-btn"
										onclick="return confirm('Bu masayı silmek istediğinize emin misiniz? Masadaki açık oturumlar da kapanır.');">Sil</a>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php else : ?>
						<tr><td colspan="5">Henüz masa oluşturulmadı.</td></tr>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}
}

/**
 * Ekleme/silme işlemlerini yürüt.
 *
 * @return array{tip:string,mesaj:string}|null
 */
if ( ! function_exists( 'qmo_masalar_islem' ) ) {
	function qmo_masalar_islem() {

		// --- Masa ekle ---
		if ( isset( $_POST['qmo_masa_ekle'] ) ) {
			check_admin_referer( 'qmo_masa_ekle', 'qmo_nonce' );

			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( 'Yetkiniz yok.' );
			}

			$ad     = isset( $_POST['table_name'] ) ? sanitize_text_field( wp_unslash( $_POST['table_name'] ) ) : '';
			$sonuc  = QMO_Masalar::ekle( $ad );

			if ( is_wp_error( $sonuc ) ) {
				return array(
					'tip'   => 'error',
					'mesaj' => $sonuc->get_error_message(),
				);
			}
			return array(
				'tip'   => 'success',
				'mesaj' => 'Masa eklendi.',
			);
		}

		// --- Masa sil ---
		if ( isset( $_GET['action'], $_GET['id'] ) && 'delete' === $_GET['action'] ) {
			$id = (int) $_GET['id'];
			check_admin_referer( 'qmo_masa_sil_' . $id );

			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( 'Yetkiniz yok.' );
			}

			$sonuc = QMO_Masalar::sil( $id );
			if ( is_wp_error( $sonuc ) ) {
				return array(
					'tip'   => 'error',
					'mesaj' => $sonuc->get_error_message(),
				);
			}
			return array(
				'tip'   => 'success',
				'mesaj' => 'Masa silindi ve o masadaki açık oturumlar kapatıldı.',
			);
		}

		return null;
	}
}
