<?php
/**
 * PAYLAŞILAN FİLTRE ÇUBUĞU — bütün İstatistik ekranlarının ortak başlığı.
 *
 * Bileşen TEK YERDE tanımlıdır; her kategori sayfası onu çağırır. Filtrenin
 * kendisi (okuma, doğrulama, URL üretimi) QRMS_Analitik_Filtre'dedir — burada
 * yalnızca o bağlamın arayüzü vardır.
 *
 * NEDEN SAYFA YENİLEME? Aralık ve masa seçimi query arg olarak taşınır, yani
 * her seçim adres çubuğuna yazılır ve sayfa o adresle yeniden yüklenir.
 * Böylece seçim paylaşılabilir, yer imine alınabilir, tarayıcının geri/ileri
 * düğmeleri doğru çalışır ve en önemlisi kategoriler arası geçişte KAYBOLMAZ.
 * (Grafiğin kendi kırılım seçicisi bunun dışındadır: o yalnızca aynı sayfadaki
 * gruplamayı değiştirir, taşınmaz ve sayfayı yenilemez.)
 *
 * Hazır aralıklar `<a>` etiketidir, form değil: her seçeneğin adresi doğrudan
 * görünür, yeni sekmede açılabilir ve tarayıcı geçmişine doğru düşer. Masa
 * seçimi ve özel aralık GET formudur — JS seçim değişince formu kendiliğinden
 * gönderir, göndermezse "Uygula" düğmesi aynı işi yapar.
 *
 * @package QR_Menu_Suite
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'qrms_analitik_donem_secenekleri' ) ) {

	/**
	 * Zaman aralığı düğmelerinin etiketleri.
	 *
	 * @return array<string,string>
	 */
	function qrms_analitik_donem_secenekleri() {
		return array(
			'bugun' => __( 'Bugün', 'qrms' ),
			'hafta' => __( 'Son 7 gün', 'qrms' ),
			'ay'    => __( 'Bu ay', 'qrms' ),
			'ozel'  => __( 'Özel aralık', 'qrms' ),
		);
	}
}

if ( ! function_exists( 'qrms_analitik_filtre_cubugu' ) ) {

	/**
	 * Filtre çubuğunu basar.
	 *
	 * @param string $sayfa Bulunulan sayfanın slug'ı (bağlantılar buraya döner).
	 * @return void
	 */
	function qrms_analitik_filtre_cubugu( $sayfa ) {
		$aktif   = QRMS_Analitik_Filtre::donem();
		$masa    = QRMS_Analitik_Filtre::masa();
		$aralik  = QRMS_Analitik_Filtre::aralik();
		$masalar = QRMS_Analitik::masa_secenekleri();

		// "Özel aralık" düğmesi bir tarih seçmeden anlamlı bir adres üretemez;
		// tıklandığında formu açar. Form zaten açıksa (dönem "ozel") kapanmaz.
		$ozel_acik = ( 'ozel' === $aktif );
		?>
		<div class="qrms-an-filtre" data-donem="<?php echo esc_attr( $aktif ); ?>">

			<div class="qrms-an-filtre-satir">
				<span class="qrms-an-filtre-label" id="qrms-an-donem-label">
					<span class="dashicons dashicons-calendar-alt" aria-hidden="true"></span>
					<?php esc_html_e( 'Zaman aralığı', 'qrms' ); ?>
				</span>

				<div class="qrms-an-donemler" role="group" aria-labelledby="qrms-an-donem-label">
					<?php foreach ( qrms_analitik_donem_secenekleri() as $anahtar => $etiket ) : ?>
						<?php
						$secili = ( $anahtar === $aktif );

						if ( 'ozel' === $anahtar ) {
							// Tarihler seçilene kadar gidilecek bir adres yok.
							?>
							<button type="button"
								class="qrms-an-donem qrms-an-ozel-ac<?php echo $secili ? ' is-active' : ''; ?>"
								aria-expanded="<?php echo $ozel_acik ? 'true' : 'false'; ?>"
								aria-controls="qrms-an-ozel-form">
								<?php echo esc_html( $etiket ); ?>
							</button>
							<?php
							continue;
						}

						// Dönem değişince özel aralığın tarihleri düşer; args()
						// bunu kendisi halleder (donem !== ozel iken bas/bit yazılmaz).
						$url = QRMS_Analitik_Filtre::url(
							$sayfa,
							array(
								QRMS_Analitik_Filtre::ARG_DONEM => $anahtar,
								QRMS_Analitik_Filtre::ARG_BAS   => false,
								QRMS_Analitik_Filtre::ARG_BIT   => false,
							)
						);
						?>
						<a class="qrms-an-donem<?php echo $secili ? ' is-active' : ''; ?>"
							href="<?php echo esc_url( $url ); ?>"
							<?php echo $secili ? 'aria-current="true"' : ''; ?>>
							<?php echo esc_html( $etiket ); ?>
						</a>
					<?php endforeach; ?>
				</div>

				<span class="qrms-an-filtre-ozet">
					<?php echo esc_html( substr( $aralik['bas'], 0, 10 ) ); ?>
					<?php if ( substr( $aralik['bas'], 0, 10 ) !== substr( $aralik['bit'], 0, 10 ) ) : ?>
						&ndash; <?php echo esc_html( substr( $aralik['bit'], 0, 10 ) ); ?>
					<?php endif; ?>
				</span>
			</div>

			<form class="qrms-an-ozel-form" id="qrms-an-ozel-form" method="get"
				action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>"
				<?php echo $ozel_acik ? '' : 'hidden'; ?>>

				<?php
				// GET formu adresi kendi alanlarından kurar; sayfa ve taşınan
				// masa filtresi gizli alanlarla korunur.
				?>
				<input type="hidden" name="page" value="<?php echo esc_attr( $sayfa ); ?>">
				<input type="hidden" name="<?php echo esc_attr( QRMS_Analitik_Filtre::ARG_DONEM ); ?>" value="ozel">
				<input type="hidden" name="<?php echo esc_attr( QRMS_Analitik_Filtre::ARG_MASA ); ?>" value="<?php echo esc_attr( $masa ); ?>">

				<label class="qrms-an-filtre-label" for="qrms-an-bas"><?php esc_html_e( 'Başlangıç', 'qrms' ); ?></label>
				<input type="date" id="qrms-an-bas" class="qrms-an-date"
					name="<?php echo esc_attr( QRMS_Analitik_Filtre::ARG_BAS ); ?>"
					value="<?php echo esc_attr( '' !== QRMS_Analitik_Filtre::bas() ? QRMS_Analitik_Filtre::bas() : substr( $aralik['bas'], 0, 10 ) ); ?>"
					required>

				<label class="qrms-an-filtre-label" for="qrms-an-bit"><?php esc_html_e( 'Bitiş', 'qrms' ); ?></label>
				<input type="date" id="qrms-an-bit" class="qrms-an-date"
					name="<?php echo esc_attr( QRMS_Analitik_Filtre::ARG_BIT ); ?>"
					value="<?php echo esc_attr( '' !== QRMS_Analitik_Filtre::bit() ? QRMS_Analitik_Filtre::bit() : substr( $aralik['bit'], 0, 10 ) ); ?>"
					required>

				<button type="submit" class="qrms-an-btn qrms-an-btn-small"><?php esc_html_e( 'Uygula', 'qrms' ); ?></button>
			</form>

			<div class="qrms-an-filtre-satir">
				<label class="qrms-an-filtre-label" for="qrms-an-masa">
					<span class="dashicons dashicons-editor-table" aria-hidden="true"></span>
					<?php esc_html_e( 'Masa filtresi', 'qrms' ); ?>
				</label>

				<?php
				// Seçim değiştiğinde JS adrese gider; JS yoksa "Uygula" düğmesi
				// aynı işi yapar (form GET olduğu için adres yine kurulur).
				?>
				<form class="qrms-an-masa-form" method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
					<input type="hidden" name="page" value="<?php echo esc_attr( $sayfa ); ?>">
					<input type="hidden" name="<?php echo esc_attr( QRMS_Analitik_Filtre::ARG_DONEM ); ?>" value="<?php echo esc_attr( $aktif ); ?>">
					<?php if ( 'ozel' === $aktif ) : ?>
						<input type="hidden" name="<?php echo esc_attr( QRMS_Analitik_Filtre::ARG_BAS ); ?>" value="<?php echo esc_attr( QRMS_Analitik_Filtre::bas() ); ?>">
						<input type="hidden" name="<?php echo esc_attr( QRMS_Analitik_Filtre::ARG_BIT ); ?>" value="<?php echo esc_attr( QRMS_Analitik_Filtre::bit() ); ?>">
					<?php endif; ?>

					<select id="qrms-an-masa" class="qrms-an-select" name="<?php echo esc_attr( QRMS_Analitik_Filtre::ARG_MASA ); ?>">
						<option value=""><?php esc_html_e( 'Tüm masalar', 'qrms' ); ?></option>
						<?php foreach ( $masalar as $secenek ) : ?>
							<option value="<?php echo esc_attr( $secenek['slug'] ); ?>" <?php selected( $masa, $secenek['slug'] ); ?>>
								<?php echo esc_html( $secenek['label'] ); ?>
							</option>
						<?php endforeach; ?>
					</select>

					<button type="submit" class="qrms-an-btn qrms-an-btn-small qrms-an-masa-uygula"><?php esc_html_e( 'Uygula', 'qrms' ); ?></button>
				</form>

				<?php if ( '' !== $masa ) : ?>
					<a class="qrms-an-btn qrms-an-btn-small"
						href="<?php echo esc_url( QRMS_Analitik_Filtre::url( $sayfa, array( QRMS_Analitik_Filtre::ARG_MASA => false ) ) ); ?>">
						<?php esc_html_e( 'Filtreyi kaldır', 'qrms' ); ?>
					</a>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}
}
