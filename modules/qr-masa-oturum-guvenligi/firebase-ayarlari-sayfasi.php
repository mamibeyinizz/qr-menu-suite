<?php
/**
 * Yönetim sayfası: QR Menü → Güvenlik Ayarı → Firebase & Şube Ayarları.
 *
 * qr-analiz'in iki REST ucu da (analytics, create-user) çağıranın kimliğini
 * QMO_Firestore üzerinden doğrular; sayfanın işi bu doğrulamanın dayandığı
 * yapılandırmayı (service account + şube kimliği + ana site bayrağı) toplamak
 * ve uçların durumunu göstermektir. Ekran v1.0'da qr-analiz modülünün
 * altındaydı: yapılandırdığı şey raporlama değil kimlik doğrulama ve şube
 * bağlantısı olduğu için güvenlik modülüne taşındı. Fonksiyon adı ve sayfa
 * adresi (qrms-analiz-ayarlar) korunuyor — canlı sitelerdeki bağlantılar
 * kırılmasın.
 *
 * Alanların kendisi _qmo-ortak/firebase-ayarlari.php içindeki ortak formdan
 * gelir: aynı üç option'ı qr-chatbot da kullanır ve modüller bağımsız
 * lisanslanır, bu yüzden form tek modülün altında duramaz.
 *
 * @package QR_Menu_Suite
 */

defined( 'ABSPATH' ) || exit;

/**
 * Firebase / şube ayar sayfası.
 *
 * @return void
 */
if ( ! function_exists( 'qmo_analiz_ayar_sayfasi' ) ) {
	function qmo_analiz_ayar_sayfasi() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Bu sayfaya erişim yetkiniz yok.' );
		}

		$hazir    = QMO_Firestore::hazir_mi();
		$ana_site = (bool) get_option( 'qmo_ana_site', false );

		// Menü analitiği AYRI bir modüldür (qr-analiz) ve bağımsız lisanslanır;
		// bağlantı yalnızca o modül aktifken basılır, yoksa kullanıcı erişemeyeceği
		// bir ekrana yönlendirilmiş olurdu.
		$analiz_aktif = in_array( 'qr-analiz', QRMS_License_Client::get_active_modules(), true );
		?>
		<div class="wrap qmo-wrap">
			<h1>Firebase &amp; Şube Ayarları</h1>

			<?php settings_errors(); ?>

			<p class="qmo-aciklama">
				Bu sayfa yalnızca uygulamanın (müdür/garson paneli) kullandığı REST uçlarını yapılandırır.
				<?php if ( $analiz_aktif ) : ?>
					Menüye kaç kişi baktığı, hangi ürünlerin tıklandığı ve hangi masadan geldiği
					<a href="<?php echo esc_url( QRMS_Admin::get_module_page_url( 'qr-analiz' ) ); ?>">QR Analiz</a>
					ekranındadır.
				<?php else : ?>
					Menüye kaç kişi baktığı, hangi ürünlerin tıklandığı ve hangi masadan geldiği
					QR Analiz modülünün ekranındadır.
				<?php endif; ?>
			</p>

			<h2 class="title">REST Uçları</h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">Analitik</th>
					<td>
						<code><?php echo esc_html( esc_url_raw( rest_url( 'qrservis/v1/analytics' ) ) ); ?></code>
						<p class="description">
							<?php if ( $hazir ) : ?>
								<span class="qmo-durum qmo-durum-ok">✓ Açık</span> — uygulama, müdür/admin Firebase ID token'ı ile buraya POST atar.
							<?php else : ?>
								<span class="qmo-durum qmo-durum-eksik">✗ Yapılandırma eksik</span> — aşağıdaki service account girilene kadar uç 500 döner.
							<?php endif; ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">Kullanıcı oluşturma</th>
					<td>
						<code><?php echo esc_html( esc_url_raw( rest_url( 'qrservis/v1/create-user' ) ) ); ?></code>
						<p class="description">
							<?php if ( $ana_site ) : ?>
								<span class="qmo-durum qmo-durum-ok">✓ Açık</span> — bu site ana site olarak işaretli.
							<?php else : ?>
								<span class="qmo-durum qmo-durum-eksik">✗ Kapalı</span> — şube sitelerinde uç hiç kaydedilmez.
								Merkezî yönetim sitesindeyseniz aşağıdaki "Bu site ana site mi?" kutusunu işaretleyin.
							<?php endif; ?>
						</p>
					</td>
				</tr>
			</table>

			<?php qmo_firebase_ayar_formu(); ?>
		</div>
		<?php
	}
}
