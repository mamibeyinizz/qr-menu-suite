<?php
/**
 * Genel Ayarlar → Giriş Ekranı sekmesi.
 *
 * Solda ayar kartları, sağda canlı önizleme. Önizleme giriş ekranının
 * GERÇEK stylesheet'ini (`assets/css/login.css`) kullanır; oradaki her kural
 * hem `body.qrms-login …` hem `.qrms-lp …` seçicisiyle yazıldığı için iki
 * görünüm zamanla birbirinden ayrı düşemez.
 *
 * @package QR_Menu_Suite
 */

defined( 'ABSPATH' ) || exit;

/**
 * Sekmeyi basar.
 *
 * @return void
 */
function qrms_login_ayar_sayfasi() {
	$s          = QRMS_Login::get_settings();
	$sabit      = QRMS_Login::is_disabled_by_constant();
	$coklu_site = function_exists( 'is_multisite' ) && is_multisite();

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$slug_hata = isset( $_GET['slug_hata'] ) ? sanitize_text_field( wp_unslash( $_GET['slug_hata'] ) ) : '';

	$arkaplan_url = QRMS_Login::attachment_url( $s['arkaplan_gorsel'] );
	$logo_url     = QRMS_Login::attachment_url( $s['logo'] );
	?>
	<div class="qrms-login-ayar">

		<?php if ( $sabit ) : ?>
			<div class="qrms-alert qrms-alert-warning">
				<p>
					<strong><?php esc_html_e( 'Özellik wp-config.php üzerinden kapatılmış.', 'qrms' ); ?></strong>
					<?php esc_html_e( 'Dosyada QRMS_LOGIN_DISABLE sabiti tanımlı olduğu sürece ne özel giriş adresi ne de özel görünüm devreye girer. Aşağıdaki ayarlar kaydedilir ama uygulanmaz.', 'qrms' ); ?>
				</p>
			</div>
		<?php endif; ?>

		<?php if ( $coklu_site ) : ?>
			<div class="qrms-alert qrms-alert-warning">
				<p><?php esc_html_e( 'Çok siteli (multisite) kurulumlarda özel giriş adresi devreye girmez; giriş ekranı görünümü çalışmaya devam eder.', 'qrms' ); ?></p>
			</div>
		<?php endif; ?>

		<?php if ( '' !== $slug_hata ) : ?>
			<div class="qrms-alert qrms-alert-error">
				<p><strong><?php esc_html_e( 'Giriş adresi değiştirilmedi:', 'qrms' ); ?></strong> <?php echo esc_html( $slug_hata ); ?></p>
			</div>
		<?php endif; ?>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="qrms-login-form">
			<input type="hidden" name="action" value="<?php echo esc_attr( QRMS_Login::ACTION ); ?>">
			<?php wp_nonce_field( QRMS_Login::NONCE ); ?>

			<div class="qrms-login-grid">

				<div class="qrms-login-col">

					<!-- ---------------------------------------------------- -->
					<div class="qrms-card">
						<h2 class="qrms-card-title"><?php esc_html_e( 'Giriş Adresi', 'qrms' ); ?></h2>

						<label class="qrms-switch">
							<input type="checkbox" name="qrms_login[yol_aktif]" value="1" <?php checked( $s['yol_aktif'], 1 ); ?>>
							<span><?php esc_html_e( 'Özel giriş adresini kullan', 'qrms' ); ?></span>
						</label>

						<p class="qrms-muted">
							<?php esc_html_e( 'Açtığınızda yönetim paneline yalnızca aşağıdaki adresten girilir. wp-login.php ve oturum açılmadan istenen wp-admin adresleri 404 döner.', 'qrms' ); ?>
						</p>

						<div class="qrms-field">
							<label for="qrms-login-slug"><?php esc_html_e( 'Adres', 'qrms' ); ?></label>
							<div class="qrms-url-input">
								<span class="qrms-url-prefix"><?php echo esc_html( trailingslashit( home_url() ) ); ?></span>
								<input type="text" id="qrms-login-slug" name="qrms_login[slug]" value="<?php echo esc_attr( $s['slug'] ); ?>" autocomplete="off" spellcheck="false">
							</div>
							<p class="qrms-muted"><?php esc_html_e( 'Yalnızca harf, rakam ve tire. Sitenizde aynı adrese sahip bir sayfa varsa kabul edilmez.', 'qrms' ); ?></p>
						</div>

						<?php if ( QRMS_Login::is_active() ) : ?>
							<div class="qrms-login-url-kutu">
								<span class="qrms-login-url-etiket"><?php esc_html_e( 'Yürürlükteki giriş adresi', 'qrms' ); ?></span>
								<code id="qrms-login-url"><?php echo esc_html( QRMS_Login::login_url() ); ?></code>
								<button type="button" class="button qrms-kopyala" data-hedef="qrms-login-url"><?php esc_html_e( 'Kopyala', 'qrms' ); ?></button>
							</div>
						<?php endif; ?>

						<label class="qrms-switch">
							<input type="checkbox" name="qrms_login[wp_admin_koru]" value="1" <?php checked( $s['wp_admin_koru'], 1 ); ?>>
							<span><?php esc_html_e( 'Oturum açılmadan istenen wp-admin adreslerini 404 döndür', 'qrms' ); ?></span>
						</label>

						<div class="qrms-alert qrms-alert-info qrms-login-kurtarma">
							<p>
								<strong><?php esc_html_e( 'Adresi unutursanız:', 'qrms' ); ?></strong>
								<?php esc_html_e( 'Sunucudaki wp-config.php dosyasına aşağıdaki satırı ekleyin; özellik kapanır ve wp-login.php yeniden çalışır.', 'qrms' ); ?>
							</p>
							<code>define( 'QRMS_LOGIN_DISABLE', true );</code>
							<p class="qrms-muted"><?php esc_html_e( 'Adres her değiştiğinde site yöneticisinin e-posta adresine de gönderilir.', 'qrms' ); ?></p>
						</div>
					</div>

					<!-- ---------------------------------------------------- -->
					<div class="qrms-card">
						<h2 class="qrms-card-title"><?php esc_html_e( 'Giriş Ekranı Görünümü', 'qrms' ); ?></h2>

						<label class="qrms-switch">
							<input type="checkbox" name="qrms_login[gorunum_aktif]" value="1" <?php checked( $s['gorunum_aktif'], 1 ); ?> data-onizleme="gorunum_aktif">
							<span><?php esc_html_e( 'WordPress varsayılan giriş ekranı yerine bu tasarımı kullan', 'qrms' ); ?></span>
						</label>

						<div class="qrms-field">
							<span class="qrms-field-label"><?php esc_html_e( 'Düzen', 'qrms' ); ?></span>
							<div class="qrms-secim">
								<?php
								$duzenler = array(
									'bolunmus' => __( 'Bölünmüş (marka paneli + form)', 'qrms' ),
									'merkez'   => __( 'Ortalanmış kart', 'qrms' ),
								);
								foreach ( $duzenler as $deger => $etiket ) :
									?>
									<label class="qrms-secim-secenek">
										<input type="radio" name="qrms_login[duzen]" value="<?php echo esc_attr( $deger ); ?>" <?php checked( $s['duzen'], $deger ); ?> data-onizleme="duzen">
										<span><?php echo esc_html( $etiket ); ?></span>
									</label>
								<?php endforeach; ?>
							</div>
						</div>

						<div class="qrms-field">
							<span class="qrms-field-label"><?php esc_html_e( 'Tema', 'qrms' ); ?></span>
							<div class="qrms-secim">
								<?php
								$temalar = array(
									'koyu'     => __( 'Koyu', 'qrms' ),
									'acik'     => __( 'Açık', 'qrms' ),
									'otomatik' => __( 'Cihaza göre', 'qrms' ),
								);
								foreach ( $temalar as $deger => $etiket ) :
									?>
									<label class="qrms-secim-secenek">
										<input type="radio" name="qrms_login[tema]" value="<?php echo esc_attr( $deger ); ?>" <?php checked( $s['tema'], $deger ); ?> data-onizleme="tema">
										<span><?php echo esc_html( $etiket ); ?></span>
									</label>
								<?php endforeach; ?>
							</div>
						</div>

						<div class="qrms-field qrms-field-ikili">
							<div>
								<label for="qrms-login-vurgu"><?php esc_html_e( 'Vurgu rengi', 'qrms' ); ?></label>
								<input type="text" id="qrms-login-vurgu" class="qrms-renk" name="qrms_login[vurgu]" value="<?php echo esc_attr( $s['vurgu'] ); ?>" data-onizleme-var="--qrms-lg-vurgu">
							</div>
							<div>
								<label for="qrms-login-vurgu2"><?php esc_html_e( 'İkinci vurgu (buton gradyanı)', 'qrms' ); ?></label>
								<input type="text" id="qrms-login-vurgu2" class="qrms-renk" name="qrms_login[vurgu2]" value="<?php echo esc_attr( $s['vurgu2'] ); ?>" data-onizleme-var="--qrms-lg-vurgu2">
							</div>
						</div>
					</div>

					<!-- ---------------------------------------------------- -->
					<div class="qrms-card">
						<h2 class="qrms-card-title"><?php esc_html_e( 'Arka Plan', 'qrms' ); ?></h2>

						<div class="qrms-field">
							<div class="qrms-secim">
								<?php
								$tipler = array(
									'gradyan' => __( 'Gradyan', 'qrms' ),
									'renk'    => __( 'Düz renk', 'qrms' ),
									'gorsel'  => __( 'Görsel', 'qrms' ),
								);
								foreach ( $tipler as $deger => $etiket ) :
									?>
									<label class="qrms-secim-secenek">
										<input type="radio" name="qrms_login[arkaplan_tip]" value="<?php echo esc_attr( $deger ); ?>" <?php checked( $s['arkaplan_tip'], $deger ); ?> data-onizleme="arkaplan_tip">
										<span><?php echo esc_html( $etiket ); ?></span>
									</label>
								<?php endforeach; ?>
							</div>
						</div>

						<div class="qrms-field qrms-field-ikili">
							<div>
								<label for="qrms-login-bg1"><?php esc_html_e( 'Renk', 'qrms' ); ?></label>
								<input type="text" id="qrms-login-bg1" class="qrms-renk" name="qrms_login[arkaplan_renk]" value="<?php echo esc_attr( $s['arkaplan_renk'] ); ?>" data-onizleme-var="--qrms-lg-bg1">
							</div>
							<div>
								<label for="qrms-login-bg2"><?php esc_html_e( 'İkinci renk (gradyan)', 'qrms' ); ?></label>
								<input type="text" id="qrms-login-bg2" class="qrms-renk" name="qrms_login[arkaplan_renk2]" value="<?php echo esc_attr( $s['arkaplan_renk2'] ); ?>" data-onizleme-var="--qrms-lg-bg2">
							</div>
						</div>

						<div class="qrms-field qrms-medya" data-medya="arkaplan_gorsel">
							<span class="qrms-field-label"><?php esc_html_e( 'Arka plan görseli', 'qrms' ); ?></span>
							<div class="qrms-medya-onizleme">
								<?php if ( '' !== $arkaplan_url ) : ?>
									<img src="<?php echo esc_url( $arkaplan_url ); ?>" alt="">
								<?php endif; ?>
							</div>
							<input type="hidden" name="qrms_login[arkaplan_gorsel]" value="<?php echo esc_attr( $s['arkaplan_gorsel'] ); ?>" data-onizleme-var="--qrms-lg-bg-image">
							<button type="button" class="button qrms-medya-sec"><?php esc_html_e( 'Görsel seç', 'qrms' ); ?></button>
							<button type="button" class="button-link qrms-medya-sil"><?php esc_html_e( 'Kaldır', 'qrms' ); ?></button>
						</div>

						<div class="qrms-field qrms-field-ikili">
							<div>
								<label for="qrms-login-karartma"><?php esc_html_e( 'Karartma', 'qrms' ); ?> <span class="qrms-deger" data-icin="qrms-login-karartma"><?php echo esc_html( $s['arkaplan_karartma'] ); ?>%</span></label>
								<input type="range" id="qrms-login-karartma" name="qrms_login[arkaplan_karartma]" min="0" max="90" step="5" value="<?php echo esc_attr( $s['arkaplan_karartma'] ); ?>" data-onizleme-var="--qrms-lg-karartma" data-birim="oran">
							</div>
							<div>
								<label for="qrms-login-bulanik"><?php esc_html_e( 'Bulanıklık', 'qrms' ); ?> <span class="qrms-deger" data-icin="qrms-login-bulanik"><?php echo esc_html( $s['arkaplan_bulanik'] ); ?>px</span></label>
								<input type="range" id="qrms-login-bulanik" name="qrms_login[arkaplan_bulanik]" min="0" max="20" step="1" value="<?php echo esc_attr( $s['arkaplan_bulanik'] ); ?>" data-onizleme-var="--qrms-lg-bulanik" data-birim="px">
							</div>
						</div>
					</div>

					<!-- ---------------------------------------------------- -->
					<div class="qrms-card">
						<h2 class="qrms-card-title"><?php esc_html_e( 'Marka', 'qrms' ); ?></h2>

						<div class="qrms-field qrms-medya" data-medya="logo">
							<span class="qrms-field-label"><?php esc_html_e( 'Logo', 'qrms' ); ?></span>
							<div class="qrms-medya-onizleme">
								<?php if ( '' !== $logo_url ) : ?>
									<img src="<?php echo esc_url( $logo_url ); ?>" alt="">
								<?php endif; ?>
							</div>
							<input type="hidden" name="qrms_login[logo]" value="<?php echo esc_attr( $s['logo'] ); ?>" data-onizleme-var="--qrms-lg-logo">
							<button type="button" class="button qrms-medya-sec"><?php esc_html_e( 'Logo seç', 'qrms' ); ?></button>
							<button type="button" class="button-link qrms-medya-sil"><?php esc_html_e( 'Kaldır', 'qrms' ); ?></button>
							<p class="qrms-muted"><?php esc_html_e( 'Boş bırakırsanız site adı yazıyla görünür.', 'qrms' ); ?></p>
						</div>

						<div class="qrms-field">
							<label for="qrms-login-logo-h"><?php esc_html_e( 'Logo yüksekliği', 'qrms' ); ?> <span class="qrms-deger" data-icin="qrms-login-logo-h"><?php echo esc_html( $s['logo_yukseklik'] ); ?>px</span></label>
							<input type="range" id="qrms-login-logo-h" name="qrms_login[logo_yukseklik]" min="24" max="160" step="2" value="<?php echo esc_attr( $s['logo_yukseklik'] ); ?>" data-onizleme-var="--qrms-lg-logo-h" data-birim="px">
						</div>

						<div class="qrms-field">
							<label for="qrms-login-baslik"><?php esc_html_e( 'Başlık', 'qrms' ); ?></label>
							<input type="text" id="qrms-login-baslik" name="qrms_login[baslik]" value="<?php echo esc_attr( $s['baslik'] ); ?>" placeholder="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>" data-onizleme-metin=".qrms-lp-brand-title">
						</div>

						<div class="qrms-field">
							<label for="qrms-login-alt"><?php esc_html_e( 'Alt metin', 'qrms' ); ?></label>
							<textarea id="qrms-login-alt" name="qrms_login[alt_metin]" rows="2" data-onizleme-metin=".qrms-lp-brand-text"><?php echo esc_textarea( $s['alt_metin'] ); ?></textarea>
						</div>

						<div class="qrms-field">
							<label for="qrms-login-footer"><?php esc_html_e( 'Alt bilgi', 'qrms' ); ?></label>
							<textarea id="qrms-login-footer" name="qrms_login[footer_metin]" rows="2"><?php echo esc_textarea( $s['footer_metin'] ); ?></textarea>
							<p class="qrms-muted"><?php esc_html_e( 'Formun altında küçük punto ile görünür. Bağlantı ve kalın yazı kullanılabilir.', 'qrms' ); ?></p>
						</div>
					</div>

					<!-- ---------------------------------------------------- -->
					<div class="qrms-card">
						<h2 class="qrms-card-title"><?php esc_html_e( 'Kart ve Bileşenler', 'qrms' ); ?></h2>

						<div class="qrms-field">
							<label for="qrms-login-radius"><?php esc_html_e( 'Köşe yuvarlaklığı', 'qrms' ); ?> <span class="qrms-deger" data-icin="qrms-login-radius"><?php echo esc_html( $s['kart_yaricap'] ); ?>px</span></label>
							<input type="range" id="qrms-login-radius" name="qrms_login[kart_yaricap]" min="0" max="40" step="1" value="<?php echo esc_attr( $s['kart_yaricap'] ); ?>" data-onizleme-var="--qrms-lg-radius" data-birim="px">
						</div>

						<label class="qrms-switch">
							<input type="checkbox" name="qrms_login[kart_golge]" value="1" <?php checked( $s['kart_golge'], 1 ); ?> data-onizleme-sinif="qrms-login-golge">
							<span><?php esc_html_e( 'Kart gölgesi', 'qrms' ); ?></span>
						</label>

						<label class="qrms-switch">
							<input type="checkbox" name="qrms_login[kart_cam]" value="1" <?php checked( $s['kart_cam'], 1 ); ?> data-onizleme-sinif="qrms-login-cam">
							<span><?php esc_html_e( 'Cam efekti (arka planı bulanıklaştırır)', 'qrms' ); ?></span>
						</label>

						<hr class="qrms-ayrac">

						<label class="qrms-switch">
							<input type="checkbox" name="qrms_login[beni_hatirla]" value="1" <?php checked( $s['beni_hatirla'], 1 ); ?> data-onizleme-sinif="qrms-login-hatirla-gizli" data-ters="1">
							<span><?php esc_html_e( '"Beni hatırla" seçeneğini göster', 'qrms' ); ?></span>
						</label>

						<label class="qrms-switch">
							<input type="checkbox" name="qrms_login[sifremi_unuttum]" value="1" <?php checked( $s['sifremi_unuttum'], 1 ); ?> data-onizleme-sinif="qrms-login-nav-gizli" data-ters="1">
							<span><?php esc_html_e( '"Şifremi unuttum" bağlantısını göster', 'qrms' ); ?></span>
						</label>

						<label class="qrms-switch">
							<input type="checkbox" name="qrms_login[siteye_don]" value="1" <?php checked( $s['siteye_don'], 1 ); ?> data-onizleme-sinif="qrms-login-geri-gizli" data-ters="1">
							<span><?php esc_html_e( '"Siteye dön" bağlantısını göster', 'qrms' ); ?></span>
						</label>

						<label class="qrms-switch">
							<input type="checkbox" name="qrms_login[dil_secici]" value="1" <?php checked( $s['dil_secici'], 1 ); ?>>
							<span><?php esc_html_e( 'Dil seçicisini göster', 'qrms' ); ?></span>
						</label>
					</div>

					<p class="qrms-login-kaydet">
						<button type="submit" class="button button-primary button-hero"><?php esc_html_e( 'Ayarları Kaydet', 'qrms' ); ?></button>
					</p>
				</div>

				<!-- ======================= ÖNİZLEME ======================= -->
				<div class="qrms-login-col qrms-login-col-onizleme">
					<div class="qrms-onizleme-sarmal">
						<div class="qrms-onizleme-baslik">
							<span><?php esc_html_e( 'Canlı Önizleme', 'qrms' ); ?></span>
							<div class="qrms-onizleme-cihaz">
								<button type="button" class="qrms-cihaz aktif" data-cihaz="masaustu"><?php esc_html_e( 'Masaüstü', 'qrms' ); ?></button>
								<button type="button" class="qrms-cihaz" data-cihaz="mobil"><?php esc_html_e( 'Mobil', 'qrms' ); ?></button>
							</div>
						</div>

						<div class="qrms-onizleme-cerceve" data-cihaz="masaustu">
							<div id="qrms-lp" class="qrms-lp <?php echo esc_attr( implode( ' ', QRMS_Login::skin_classes( $s ) ) ); ?>"
								style="<?php echo esc_attr( QRMS_Login::css_variables( $s, $arkaplan_url, $logo_url ) ); ?>">
								<div class="qrms-lp-brand">
									<h2 class="qrms-lp-brand-title"><?php echo esc_html( '' !== $s['baslik'] ? $s['baslik'] : get_bloginfo( 'name' ) ); ?></h2>
									<p class="qrms-lp-brand-text"><?php echo esc_html( $s['alt_metin'] ); ?></p>
								</div>

								<div class="qrms-lp-box">
									<div class="qrms-lp-logo"><span><?php echo esc_html( get_bloginfo( 'name' ) ); ?></span></div>

									<div class="qrms-lp-form">
										<p>
											<label><?php esc_html_e( 'Kullanıcı adı veya e-posta', 'qrms' ); ?></label>
											<input type="text" value="restoran" readonly>
										</p>
										<p>
											<label><?php esc_html_e( 'Şifre', 'qrms' ); ?></label>
											<input type="password" value="123456" readonly>
										</p>
										<p class="qrms-lp-hatirla">
											<label><input type="checkbox" checked disabled> <?php esc_html_e( 'Beni hatırla', 'qrms' ); ?></label>
										</p>
										<p class="qrms-lp-submit">
											<span class="qrms-lp-button"><?php esc_html_e( 'Giriş Yap', 'qrms' ); ?></span>
										</p>
									</div>

									<p class="qrms-lp-nav"><span><?php esc_html_e( 'Şifremi unuttum', 'qrms' ); ?></span></p>
								</div>

								<p class="qrms-lp-geri"><span><?php esc_html_e( '← Siteye dön', 'qrms' ); ?></span></p>
							</div>
						</div>

						<p class="qrms-muted qrms-onizleme-not">
							<?php esc_html_e( 'Önizleme giriş ekranının gerçek stil dosyasını kullanır; kaydettiğinizde görünen sonuç budur.', 'qrms' ); ?>
						</p>
					</div>
				</div>

			</div>
		</form>
	</div>
	<?php
}
