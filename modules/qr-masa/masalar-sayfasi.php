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
 * QR Masa ekranının bölümleri — TEK KAYNAK.
 *
 * Genel Bakış kartındaki alt liste buradan türer; adresler sayfa içi
 * çapalardır (ayrı WordPress sayfası yoktur).
 *
 * @return array<string,array{title:string,url:string}>
 */
if ( ! function_exists( 'qrms_module_qr_masa_sayfalar' ) ) {
	function qrms_module_qr_masa_sayfalar() {
		$base = class_exists( 'QRMS_Admin' )
			? QRMS_Admin::get_module_page_url( 'qr-masa' )
			: admin_url( 'admin.php?page=qrms-module-qr-masa' );

		return array(
			'masalar' => array(
				'title' => __( 'Masalar', 'qrms' ),
				'url'   => $base . '#qmo-masa-liste',
			),
			'toplu'   => array(
				'title' => __( 'Toplu Oluştur', 'qrms' ),
				'url'   => $base . '#qmo-toplu',
			),
			'aktif'   => array(
				'title' => __( 'Aktif Masa Bilgisi', 'qrms' ),
				'url'   => $base . '#qmo-aktif-masa',
			),
		);
	}
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

			<div class="qmo-add-grid">
				<div class="qmo-add-box">
					<h3>Tek Masa Oluştur</h3>
					<form method="post" action="" class="qmo-form">
						<?php wp_nonce_field( 'qmo_masa_ekle', 'qmo_nonce' ); ?>
						<div class="qmo-field">
							<label for="qmo-table-name">Masa adı</label>
							<input type="text" id="qmo-table-name" name="table_name" placeholder="Örn: Masa 1" required class="regular-text">
						</div>
						<button type="submit" name="qmo_masa_ekle" value="1" class="button button-primary">Oluştur</button>
					</form>
					<p class="description">
						Her masa için ana sayfanıza yönlenen <code>/?masa=masa-1</code> biçiminde
						parametreli özel bir QR kod üretilir. Masa adı bir <strong>metin</strong> slug'a
						dönüşür (<code>Masa 31</code> → <code>masa-31</code>, <code>VIP Salon</code> → <code>vip-salon</code>).
					</p>
				</div>

				<div class="qmo-add-box" id="qmo-toplu">
					<h3>Toplu Oluştur</h3>
					<form method="post" action="" class="qmo-form qmo-toplu-form">
						<?php wp_nonce_field( 'qmo_masa_toplu_ekle', 'qmo_toplu_nonce' ); ?>

						<div class="qmo-field">
							<label for="qmo-onek">Slug öneki</label>
							<input type="text" id="qmo-onek" name="onek" value="" placeholder="Örn: ic-masa" required class="regular-text">
						</div>

						<div class="qmo-field-row">
							<div class="qmo-field">
								<label for="qmo-bas">Başlangıç</label>
								<input type="number" id="qmo-bas" name="bas" value="1" min="1" max="999" required class="small-text">
							</div>
							<div class="qmo-field">
								<label for="qmo-bit">Bitiş</label>
								<input type="number" id="qmo-bit" name="bit" value="10" min="1" max="999" required class="small-text">
							</div>
						</div>

						<p class="qmo-onizleme" id="qmo-onizleme" aria-live="polite"></p>

						<button type="submit" name="qmo_masa_toplu_ekle" value="1" class="button button-primary">Masaları Oluştur</button>
					</form>
					<p class="description">
						Numaralı masaları tek seferde açar: <code>ic-masa</code> öneki ve 1–10 aralığı
						<code>ic-masa-1</code> … <code>ic-masa-10</code> masalarını oluşturur. Zaten var olan
						masalar atlanır, tek seferde en fazla <?php echo (int) QMO_Masalar::TOPLU_AZAMI; ?> masa açılır.
					</p>
				</div>
			</div>

			<?php
			$gruplar = qmo_masalar_gruplari( $masalar );

			// Tek grup varsa filtrenin anlamı yok; çip listesi hiç basılmaz.
			if ( count( $gruplar ) > 1 ) :
				?>
				<div class="qmo-filtre" role="group" aria-label="Masa grubuna göre filtrele">
					<button type="button" class="qmo-chip is-active" data-grup="">
						Tümü <span class="qmo-chip-sayi"><?php echo (int) count( $masalar ); ?></span>
					</button>
					<?php foreach ( $gruplar as $grup => $adet ) : ?>
						<button type="button" class="qmo-chip" data-grup="<?php echo esc_attr( $grup ); ?>">
							<?php echo esc_html( $grup ); ?> <span class="qmo-chip-sayi"><?php echo (int) $adet; ?></span>
						</button>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<table class="wp-list-table widefat fixed striped qmo-masa-tablo" id="qmo-masa-liste">
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
								data-grup="<?php echo esc_attr( QMO_Masalar::grup_adi( $t->table_slug ) ); ?>"
								data-url="<?php echo esc_url( $hedef ); ?>">
								<td data-label="ID"><?php echo (int) $t->id; ?></td>
								<td data-label="Masa Adı"><strong><?php echo esc_html( $t->table_name ); ?></strong></td>
								<td data-label="URL Parametresi"><code><?php echo esc_html( $hedef ); ?></code></td>
								<td data-label="QR Kod"><img class="qmo-qr-preview" src="" alt="QR"></td>
								<td data-label="İşlemler">
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
						<tr class="qmo-bos-filtre" hidden>
							<td colspan="5">Bu grupta masa yok.</td>
						</tr>
					<?php else : ?>
						<tr><td colspan="5">Henüz masa oluşturulmadı.</td></tr>
					<?php endif; ?>
				</tbody>
			</table>

			<div class="qmo-add-box" id="qmo-aktif-masa" style="margin-top:16px;">
				<h3><?php esc_html_e( 'Aktif Masa Bilgisi', 'qrms' ); ?></h3>
				<p class="description">
					<?php esc_html_e( 'Müşteriye hangi masada olduğunu göstermek için kısa kod:', 'qrms' ); ?>
					<code>[qr_aktif_masa]</code>.
					<?php esc_html_e( 'Masa bilgisi doğrulanmış oturumdan okunur, adresten değil. Yalnızca müşteri masadaki QR kodu okuttuysa görünür.', 'qrms' ); ?>
				</p>
			</div>
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

		// --- Toplu masa ekle ---
		if ( isset( $_POST['qmo_masa_toplu_ekle'] ) ) {
			check_admin_referer( 'qmo_masa_toplu_ekle', 'qmo_toplu_nonce' );

			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( 'Yetkiniz yok.' );
			}

			$onek = isset( $_POST['onek'] ) ? sanitize_text_field( wp_unslash( $_POST['onek'] ) ) : '';
			$bas  = isset( $_POST['bas'] ) ? (int) $_POST['bas'] : 0;
			$bit  = isset( $_POST['bit'] ) ? (int) $_POST['bit'] : 0;

			$sonuc = QMO_Masalar::toplu_ekle( $onek, $bas, $bit );

			if ( is_wp_error( $sonuc ) ) {
				return array(
					'tip'   => 'error',
					'mesaj' => $sonuc->get_error_message(),
				);
			}

			return array(
				'tip'   => $sonuc['eklenen'] > 0 ? 'success' : 'warning',
				'mesaj' => qmo_toplu_sonuc_mesaji( $sonuc ),
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

/**
 * Toplu oluşturma sonucunu tek cümlelik bir bildirime çevirir.
 *
 * Atlananlar ve hatalar AYRI AYRI söylenir: "10 istedim 8 oldu" durumunda
 * kullanıcı hangi masaların neden açılmadığını görmelidir. Liste uzarsa ilk
 * beşi yazılıp gerisi sayıyla özetlenir.
 *
 * Saf fonksiyon; testlerde doğrudan doğrulanır.
 *
 * @param array{eklenen:int,atlanan:string[],hata:string[]} $sonuc QMO_Masalar::toplu_ekle() çıktısı.
 * @return string
 */
if ( ! function_exists( 'qmo_toplu_sonuc_mesaji' ) ) {
	function qmo_toplu_sonuc_mesaji( array $sonuc ) {
		$eklenen = isset( $sonuc['eklenen'] ) ? (int) $sonuc['eklenen'] : 0;
		$atlanan = isset( $sonuc['atlanan'] ) ? (array) $sonuc['atlanan'] : array();
		$hata    = isset( $sonuc['hata'] ) ? (array) $sonuc['hata'] : array();

		$parca = array( 0 === $eklenen ? 'Hiç yeni masa oluşturulmadı.' : sprintf( '%d masa oluşturuldu.', $eklenen ) );

		if ( $atlanan ) {
			$parca[] = sprintf(
				'%d tanesi zaten vardı: %s',
				count( $atlanan ),
				qmo_slug_listesi( $atlanan )
			);
		}

		if ( $hata ) {
			$parca[] = sprintf(
				'%d tanesi kaydedilemedi: %s',
				count( $hata ),
				qmo_slug_listesi( $hata )
			);
		}

		return implode( ' ', $parca );
	}
}

/**
 * Slug listesini kısaltılmış, okunabilir bir metne çevirir.
 *
 * @param string[] $sluglar Slug listesi.
 * @param int      $azami   Yazılacak azami slug sayısı.
 * @return string
 */
if ( ! function_exists( 'qmo_slug_listesi' ) ) {
	function qmo_slug_listesi( array $sluglar, $azami = 5 ) {
		$azami = max( 1, (int) $azami );

		if ( count( $sluglar ) <= $azami ) {
			return implode( ', ', $sluglar );
		}

		return implode( ', ', array_slice( $sluglar, 0, $azami ) )
			. sprintf( ' ve %d tane daha', count( $sluglar ) - $azami );
	}
}

/**
 * Masa listesinden filtre gruplarını çıkarır: grup => masa sayısı.
 *
 * Sıra doğaldır (ic-masa-2, ic-masa-10 beklenen sırada), böylece çipler
 * tabloyla aynı mantıkta dizilir.
 *
 * Saf fonksiyon; testlerde doğrudan doğrulanır.
 *
 * @param array $masalar QMO_Masalar::hepsi() çıktısı.
 * @return array<string,int>
 */
if ( ! function_exists( 'qmo_masalar_gruplari' ) ) {
	function qmo_masalar_gruplari( $masalar ) {
		$gruplar = array();

		foreach ( (array) $masalar as $t ) {
			if ( ! isset( $t->table_slug ) ) {
				continue;
			}

			$grup = QMO_Masalar::grup_adi( (string) $t->table_slug );

			if ( '' === $grup ) {
				continue;
			}

			$gruplar[ $grup ] = isset( $gruplar[ $grup ] ) ? $gruplar[ $grup ] + 1 : 1;
		}

		uksort( $gruplar, 'strnatcasecmp' );

		return $gruplar;
	}
}
