<?php
/**
 * Kısa kod kayıt defteri ve "Kısa Kodlar" rehber ekranı.
 *
 * @package QR_Menu_Suite
 */

defined( 'ABSPATH' ) || exit;

/**
 * Modüllerin kısa kodlarını tek yerde toplar ve rehber sayfasını basar.
 *
 * Suite genelinde dokuz dosyada dağınık `add_shortcode()` çağrısı var; hiçbiri
 * kullanıcıya ne işe yaradığını söylemiyordu. Burası o bilginin TEK kaydıdır:
 * her modül kendi kısa kodlarını `register()` ile bildirir, rehber sayfası da
 * yalnızca bu defteri basar. Liste statik değildir — lisansta aktif olmayan
 * bir modülün kısa kodları hiç görünmez, çünkü modülün init'i hiç çalışmaz.
 *
 * Kayıt `plugins_loaded` (öncelik 20) sırasında yapılır; `admin_menu` ve sayfa
 * render'ı bundan sonra çalıştığı için zamanlama doğrudur — modül sayfası
 * kaydındaki (QRMS_Admin::register_module_page) desenin aynısı.
 */
class QRMS_Shortcodes {

	/**
	 * Modül slug'ı -> kısa kod tanımları.
	 *
	 * @var array<string,array<int,array<string,mixed>>>
	 */
	private static $kayit = array();

	/**
	 * Bir modülün kısa kodlarını kaydeder.
	 *
	 * Her tanım:
	 *   tag    (zorunlu) Kısa kodun adı, köşeli parantezsiz.
	 *   title  (zorunlu) Kullanıcıya görünen kısa başlık.
	 *   desc   (zorunlu) Ne işe yaradığı — restoran sahibinin anlayacağı dille.
	 *   usage  Örnek kullanım; verilmezse "[tag]" olur.
	 *   note   Ek koşul (ör. "geçerli masa oturumu gerektirir").
	 *   attrs  Parametreler: name, default, desc.
	 *
	 * @param string $modul_slug Modül slug'ı.
	 * @param array  $kodlar     Kısa kod tanımları.
	 * @return void
	 */
	public static function register( $modul_slug, array $kodlar ) {
		if ( ! QRMS_Helpers::is_valid_module( $modul_slug ) ) {
			return;
		}

		foreach ( $kodlar as $kod ) {
			$kod = self::normalize( $kod );

			if ( null === $kod ) {
				continue;
			}

			self::$kayit[ $modul_slug ][] = $kod;
		}
	}

	/**
	 * Kayıt defterini boşaltır.
	 *
	 * Defter süreç ömrü boyunca yaşayan statik bir durumdur; testler her
	 * senaryoya temiz bir defterle başlayabilsin diye sıfırlanabilir.
	 *
	 * @return void
	 */
	public static function reset() {
		self::$kayit = array();
	}

	/**
	 * Tek bir tanımı eksiksiz biçime getirir (geçersizse null).
	 *
	 * Saf fonksiyon; testlerde doğrudan doğrulanır.
	 *
	 * @param mixed $kod Ham tanım.
	 * @return array<string,mixed>|null
	 */
	public static function normalize( $kod ) {
		if ( ! is_array( $kod ) || empty( $kod['tag'] ) || empty( $kod['title'] ) ) {
			return null;
		}

		$tag = trim( (string) $kod['tag'], "[] \t\n\r" );

		if ( '' === $tag ) {
			return null;
		}

		$attrs = array();

		foreach ( ( isset( $kod['attrs'] ) ? (array) $kod['attrs'] : array() ) as $attr ) {
			if ( ! is_array( $attr ) || empty( $attr['name'] ) ) {
				continue;
			}

			$attrs[] = array(
				'name'    => (string) $attr['name'],
				'default' => isset( $attr['default'] ) ? (string) $attr['default'] : '',
				'desc'    => isset( $attr['desc'] ) ? (string) $attr['desc'] : '',
			);
		}

		return array(
			'tag'   => $tag,
			'title' => (string) $kod['title'],
			'desc'  => isset( $kod['desc'] ) ? (string) $kod['desc'] : '',
			'usage' => ! empty( $kod['usage'] ) ? (string) $kod['usage'] : '[' . $tag . ']',
			'note'  => isset( $kod['note'] ) ? (string) $kod['note'] : '',
			'attrs' => $attrs,
		);
	}

	/**
	 * Kayıtlı kısa kodlar, modül slug'ına göre gruplu ve sabit sırada.
	 *
	 * @return array<string,array<int,array<string,mixed>>>
	 */
	public static function all() {
		$sirali = array();

		foreach ( QRMS_Helpers::MODULE_SLUGS as $slug ) {
			if ( ! empty( self::$kayit[ $slug ] ) ) {
				$sirali[ $slug ] = self::$kayit[ $slug ];
			}
		}

		/**
		 * Rehberde listelenecek kısa kodlar.
		 *
		 * @param array $sirali Modül slug'ı => kısa kod tanımları.
		 */
		return apply_filters( 'qrms_shortcodes', $sirali );
	}

	/**
	 * Listelenecek en az bir kısa kod var mı?
	 *
	 * Menü satırı ve beyaz liste aynı yanıtı kullanır; hiç kısa kod yoksa
	 * (aktif modül yoksa) boş bir sayfa menüde yer kaplamaz.
	 *
	 * @return bool
	 */
	public static function has_any() {
		return ! empty( self::all() );
	}

	/**
	 * "Kısa Kodlar" rehber ekranı.
	 *
	 * @return void
	 */
	public static function render_page() {
		if ( ! current_user_can( QRMS_Admin::CAPABILITY ) ) {
			wp_die( esc_html__( 'Bu sayfayı görüntüleme yetkiniz yok.', 'qrms' ) );
		}

		$gruplar = self::all();
		?>
		<div class="wrap qrms-hub qrms-sc">
			<h1 class="qrms-hub-heading"><?php esc_html_e( 'Kısa Kodlar', 'qrms' ); ?></h1>
			<p class="qrms-hub-intro">
				<?php esc_html_e( 'Aktif modüllerinizin sayfalarınıza ekleyebileceğiniz kısa kodları. Kodu kopyalayın, sayfa içeriğine ya da Elementor\'daki "Kısa Kod" widget\'ına yapıştırın.', 'qrms' ); ?>
			</p>

			<?php if ( empty( $gruplar ) ) : ?>
				<div class="qrms-card">
					<p class="qrms-muted"><?php esc_html_e( 'Aktif modülünüz olmadığı için listelenecek kısa kod yok. Lisansınızı doğruladığınızda modüllerinizin kısa kodları burada görünür.', 'qrms' ); ?></p>
				</div>
			<?php endif; ?>

			<?php foreach ( $gruplar as $modul => $kodlar ) : ?>
				<section class="qrms-sc-group">
					<h2 class="qrms-sc-group-title">
						<?php echo esc_html( QRMS_Helpers::get_module_name( $modul ) ); ?>
						<span class="qrms-sc-group-count"><?php echo (int) count( $kodlar ); ?></span>
					</h2>

					<div class="qrms-sc-grid">
						<?php foreach ( $kodlar as $kod ) : ?>
							<article class="qrms-sc-card">
								<div class="qrms-sc-head">
									<code class="qrms-sc-tag"><?php echo esc_html( $kod['usage'] ); ?></code>
									<button type="button" class="qrms-sc-copy" data-qrms-copy="<?php echo esc_attr( $kod['usage'] ); ?>">
										<span class="dashicons dashicons-clipboard" aria-hidden="true"></span>
										<span class="qrms-sc-copy-text"><?php esc_html_e( 'Kopyala', 'qrms' ); ?></span>
									</button>
								</div>

								<h3 class="qrms-sc-title"><?php echo esc_html( $kod['title'] ); ?></h3>

								<?php if ( '' !== $kod['desc'] ) : ?>
									<p class="qrms-sc-desc"><?php echo esc_html( $kod['desc'] ); ?></p>
								<?php endif; ?>

								<?php if ( '' !== $kod['note'] ) : ?>
									<p class="qrms-sc-note">
										<span class="dashicons dashicons-info-outline" aria-hidden="true"></span>
										<?php echo esc_html( $kod['note'] ); ?>
									</p>
								<?php endif; ?>

								<?php if ( ! empty( $kod['attrs'] ) ) : ?>
									<div class="qrms-sc-attrs">
										<span class="qrms-sc-attrs-title"><?php esc_html_e( 'Parametreler', 'qrms' ); ?></span>
										<ul class="qrms-sc-attr-list">
											<?php foreach ( $kod['attrs'] as $attr ) : ?>
												<li class="qrms-sc-attr">
													<code><?php echo esc_html( $attr['name'] ); ?></code>
													<?php if ( '' !== $attr['default'] ) : ?>
														<span class="qrms-sc-attr-default"><?php
															/* translators: %s: parametrenin varsayılan değeri. */
															printf( esc_html__( 'varsayılan: %s', 'qrms' ), esc_html( $attr['default'] ) );
														?></span>
													<?php endif; ?>
													<span class="qrms-sc-attr-desc"><?php echo esc_html( $attr['desc'] ); ?></span>
												</li>
											<?php endforeach; ?>
										</ul>
									</div>
								<?php endif; ?>
							</article>
						<?php endforeach; ?>
					</div>
				</section>
			<?php endforeach; ?>
		</div>
		<?php
	}
}
