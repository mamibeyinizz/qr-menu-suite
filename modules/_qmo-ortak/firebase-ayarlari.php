<?php
/**
 * Firebase / şube bağlantısı ayarları — modüller arası ortak bölüm.
 *
 * QMO_Firestore'un okuduğu üç option burada kaydedilir ve tek bir yeniden
 * kullanılabilir form bölümü olarak basılır:
 *
 *   - qmo_branch_id   : Bu sitenin şube kimliği (çağrı/sipariş kayıtlarına yazılır).
 *   - qmo_firebase_sa : Service account JSON (wp-config sabiti yoksa).
 *   - qmo_ana_site    : /create-user ucunun bu sitede açılıp açılmayacağı.
 *
 * Sınıfın kendisi gibi bu bölüm de _qmo-ortak altındadır: aynı yapılandırmayı
 * qr-analiz (REST analitiği + kullanıcı oluşturma) ve qr-chatbot (garson/hesap
 * çağrısı + sipariş) kullanır, ama modüller birbirinden bağımsız lisanslanır.
 * Bölüm tek modülün altında dursaydı, o modül lisanslı değilken diğerinin
 * Firebase yapılandırmasını girecek ekranı kalmazdı.
 *
 * AYRI AYAR GRUBU (qmo_firebase_grup) — options.php gönderilen grubun TÜM
 * option'larını yeniden yazar (formda olmayanları null'lar). Eski eklentide bu
 * alanlar chatbot ile aynı gruptaydı; suite'te bölüm birden fazla modül
 * sayfasında görünebildiği için kendi grubunda ve kendi <form>'unda durur,
 * yoksa chatbot sayfasından kaydetmek analiz sayfasının alanlarını silerdi.
 *
 * Option ADLARI değişmedi — canlı sitelerdeki kayıtlı değerler korunur.
 *
 * @package QR_Menu_Suite
 */

defined( 'ABSPATH' ) || exit;

/** Firebase alanlarının ayar grubu. */
if ( ! defined( 'QMO_FIREBASE_AYAR_GRUBU' ) ) {
	define( 'QMO_FIREBASE_AYAR_GRUBU', 'qmo_firebase_grup' );
}

add_action( 'admin_init', 'qmo_firebase_ayarlarini_kaydet' );

/**
 * Firebase option'larını register_setting ile kaydeder.
 *
 * @return void
 */
if ( ! function_exists( 'qmo_firebase_ayarlarini_kaydet' ) ) {
	function qmo_firebase_ayarlarini_kaydet() {
		$grup = QMO_FIREBASE_AYAR_GRUBU;

		register_setting(
			$grup,
			'qmo_branch_id',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			)
		);

		register_setting(
			$grup,
			'qmo_firebase_sa',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'qmo_sa_json_temizle',
			)
		);

		register_setting(
			$grup,
			'qmo_ana_site',
			array(
				'type'              => 'boolean',
				'sanitize_callback' => 'qmo_bool_temizle',
			)
		);
	}
}

/**
 * Onay kutusu değerini bool'a çevirir.
 *
 * @param mixed $in Girdi.
 * @return bool
 */
if ( ! function_exists( 'qmo_bool_temizle' ) ) {
	function qmo_bool_temizle( $in ) {
		return ! empty( $in );
	}
}

/**
 * Service account JSON'ını doğrular.
 *
 * Boş gönderilirse mevcut kayıt korunur (form anahtarı hiçbir zaman ekranda
 * geri göstermez).
 *
 * @param string $in Girdi.
 * @return string
 */
if ( ! function_exists( 'qmo_sa_json_temizle' ) ) {
	function qmo_sa_json_temizle( $in ) {
		$in = trim( (string) $in );
		if ( '' === $in ) {
			return (string) get_option( 'qmo_firebase_sa', '' );
		}

		$d = json_decode( $in, true );
		if ( ! is_array( $d ) || empty( $d['client_email'] ) || empty( $d['private_key'] ) ) {
			add_settings_error(
				'qmo_firebase_sa',
				'qmo_sa_gecersiz',
				'Service account JSON geçersiz görünüyor (client_email / private_key bulunamadı). Kayıt yapılmadı.',
				'error'
			);
			return (string) get_option( 'qmo_firebase_sa', '' );
		}

		// Anahtar değişti — önbellekteki access token'ları düşür.
		delete_transient( 'qmo_gcp_token_' . substr( md5( QMO_Firestore::SCOPE_DATASTORE ), 0, 12 ) );
		delete_transient( 'qmo_gcp_token_' . substr( md5( QMO_Firestore::SCOPE_CLOUD ), 0, 12 ) );

		return wp_json_encode( $d );
	}
}

/**
 * Firebase / şube bağlantısı formunu basar.
 *
 * Kendi <form>'unu açar; modül ayar sayfaları bunu kendi formlarının DIŞINDA
 * çağırmalıdır (iç içe form geçersiz HTML'dir).
 *
 * @param string $baslik Bölüm başlığı.
 * @return void
 */
if ( ! function_exists( 'qmo_firebase_ayar_formu' ) ) {
	function qmo_firebase_ayar_formu( $baslik = 'Firebase / Şube Bağlantısı' ) {
		?>
		<form method="post" action="options.php" id="qmo-firebase-form">
			<?php settings_fields( QMO_FIREBASE_AYAR_GRUBU ); ?>

			<h2 class="title"><?php echo esc_html( $baslik ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="qmo_branch_id">Şube Kimliği (branchId)</label></th>
					<td>
						<input type="text" id="qmo_branch_id" name="qmo_branch_id"
							value="<?php echo esc_attr( get_option( 'qmo_branch_id', '' ) ); ?>" class="regular-text" />
						<p class="description">Garson/hesap çağrıları, siparişler ve analitik sorguları bu şube kimliğiyle eşleşir.</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="qmo_firebase_sa">Service Account JSON</label></th>
					<td>
						<?php if ( QMO_Firestore::hazir_mi() ) : ?>
							<p class="qmo-durum qmo-durum-ok">
								✓ Yapılandırılmış — proje: <code><?php echo esc_html( QMO_Firestore::project_id() ); ?></code>
								<?php if ( defined( 'QMO_FIREBASE_SA_JSON' ) ) : ?>
									(wp-config.php sabitinden okunuyor)
								<?php endif; ?>
							</p>
						<?php else : ?>
							<p class="qmo-durum qmo-durum-eksik">✗ Henüz yapılandırılmadı — sipariş, çağrı ve analitik özellikleri çalışmaz.</p>
						<?php endif; ?>

						<textarea id="qmo_firebase_sa" name="qmo_firebase_sa" rows="6" cols="70"
							placeholder="Değiştirmek için yeni JSON'ı buraya yapıştırın. Boş bırakırsanız mevcut kayıt korunur."></textarea>

						<p class="description">
							<strong>Güvenlik:</strong> Bu JSON Firebase projenize tam yetki verir. En güvenli yöntem,
							dosyayı buraya değil <code>wp-config.php</code> içine koymaktır:<br>
							<code>define( 'QMO_FIREBASE_SA_JSON', '{ ... }' );</code><br>
							Sabit tanımlıysa buradaki alan yok sayılır. Anahtar hiçbir zaman ekranda geri gösterilmez.
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">Bu site ana site mi?</th>
					<td>
						<label>
							<input type="checkbox" name="qmo_ana_site" value="1"
								<?php checked( (bool) get_option( 'qmo_ana_site', false ) ); ?> />
							Evet — <code>/wp-json/qrservis/v1/create-user</code> ucunu bu sitede aç
						</label>
						<p class="description">
							Yalnızca merkezi yönetim sitesinde işaretleyin. Şube sitelerinde kapalı kalmalı;
							kapalıyken kullanıcı oluşturma ucu hiç kaydedilmez. (Uç <strong>QR Analiz</strong>
							modülüyle gelir.)
						</p>
					</td>
				</tr>
			</table>

			<?php submit_button( 'Firebase Ayarlarını Kaydet' ); ?>
		</form>
		<?php
	}
}
