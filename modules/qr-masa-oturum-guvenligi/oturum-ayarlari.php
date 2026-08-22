<?php
/**
 * Yönetim sayfası: QR Menü → Güvenlik Ayarı → Oturum Limitleri
 *
 * @package QR_Menu_Official
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Eski snippet zaten kaydettiyse ikinci kez register_setting yapmayalım.
if ( ! defined( 'QR_MASA_ADMIN_INIT' ) ) {
	define( 'QR_MASA_ADMIN_INIT', true );

	add_action( 'admin_init', 'qmo_oturum_ayarlarini_kaydet' );

	/**
	 * Oturum ayarlarını kaydet.
	 */
	function qmo_oturum_ayarlarini_kaydet() {
		register_setting(
			'qr_masa_grup',
			QMO_Oturum::OPT,
			array(
				'type'              => 'array',
				'sanitize_callback' => 'qmo_oturum_ayar_temizle',
			)
		);

		// Sayfa kilidi AYRI bir option ve AYRI bir ayar grubudur: ekrandaki
		// ikinci form options.php'ye 'qmo_sayfa_grup' ile gönderiyor. Grup
		// kaydedilmediği sürece WordPress bu gönderimi "seçenekler sayfası
		// bulunamadı" diyerek reddeder; ayar hiç yazılmaz ve sayfa kilidi
		// sessizce hiç devreye girmez (qmo_korumali_sayfalar hep boş kalır).
		register_setting(
			'qmo_sayfa_grup',
			'qmo_korumali_sayfalar',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'qmo_korumali_sayfalar_temizle',
				'default'           => '',
			)
		);
	}
}

/**
 * Korunacak sayfa slug listesini temizler.
 *
 * Girdi virgülle ayrılmış slug listesidir ("menu, menu-tr"). Her parça
 * sanitize_title()'dan geçirilir — okuma tarafı (qmo_korumali_sluglar())
 * zaten aynı dönüşümü uyguluyor; burada da uygulanınca veritabanına
 * kaydedilen değer ile karşılaştırılan değer birebir aynı olur ve
 * yönetici girdiğinin ne olduğunu ekranda doğrudan görür.
 *
 * Saf fonksiyon; testlerde doğrudan doğrulanır.
 *
 * @param mixed $in Form girdisi.
 * @return string Normalleştirilmiş, virgülle ayrılmış slug listesi.
 */
if ( ! function_exists( 'qmo_korumali_sayfalar_temizle' ) ) {
	function qmo_korumali_sayfalar_temizle( $in ) {
		$sluglar = array_filter( array_map( 'sanitize_title', explode( ',', (string) $in ) ) );

		// Aynı slug iki kez girilmişse tek kez saklanır.
		return implode( ',', array_unique( $sluglar ) );
	}
}

/**
 * Oturum ayarı doğrulama.
 *
 * @param mixed $in Form girdisi.
 * @return array
 */
if ( ! function_exists( 'qmo_oturum_ayar_temizle' ) ) {
	function qmo_oturum_ayar_temizle( $in ) {
		$in = is_array( $in ) ? $in : array();
		return array(
			'hard_cap'   => max( 5, absint( $in['hard_cap'] ?? 90 ) ),
			'idle_cap'   => max( 1, absint( $in['idle_cap'] ?? 30 ) ),
			'chat_limit' => max( 1, absint( $in['chat_limit'] ?? 25 ) ),
		);
	}
}

/**
 * Oturum ayarları sayfası.
 */
if ( ! function_exists( 'qmo_oturum_ayar_sayfasi' ) ) {
	function qmo_oturum_ayar_sayfasi() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Bu sayfaya erişim yetkiniz yok.' );
		}

		$a   = QMO_Oturum::ayar();
		$opt = QMO_Oturum::OPT;
		?>
		<div class="wrap qmo-wrap">
			<h1 class="qmo-baslik">Oturum Limitleri</h1>

			<p class="qmo-aciklama">
				Masa QR'ı ile giren cihazlara süreli oturum verilir. Limitler dolunca chatbot,
				sepet ve çağrı butonları otomatik kilitlenir; müşteri devam etmek için QR'ı
				tekrar okutmak zorunda kalır.
			</p>

			<form method="post" action="options.php">
				<?php settings_fields( 'qr_masa_grup' ); ?>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="qmo_hard_cap">Maksimum oturum süresi (dk)</label></th>
						<td>
							<input type="number" id="qmo_hard_cap" min="5" class="small-text"
								name="<?php echo esc_attr( $opt ); ?>[hard_cap]"
								value="<?php echo esc_attr( $a['hard_cap'] ); ?>">
							<p class="description">QR okutulduktan sonra oturumun toplam ömrü. Örn: 90.</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="qmo_idle_cap">Hareketsizlik limiti (dk)</label></th>
						<td>
							<input type="number" id="qmo_idle_cap" min="1" class="small-text"
								name="<?php echo esc_attr( $opt ); ?>[idle_cap]"
								value="<?php echo esc_attr( $a['idle_cap'] ); ?>">
							<p class="description">
								Bu süre boyunca işlem yapılmazsa oturum kilitlenir. Örn: 30.
								Sayaç yalnızca AJAX isteklerinde değil, <strong>sayfa gezinmesinde de</strong> yenilenir.
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="qmo_chat_limit">Oturum başına chatbot mesajı</label></th>
						<td>
							<input type="number" id="qmo_chat_limit" min="1" class="small-text"
								name="<?php echo esc_attr( $opt ); ?>[chat_limit]"
								value="<?php echo esc_attr( $a['chat_limit'] ); ?>">
							<p class="description">Tek oturumda gönderilebilecek maksimum AI mesajı (Gemini maliyet koruması). Örn: 25.</p>
						</td>
					</tr>
				</table>

				<?php submit_button( 'Kaydet' ); ?>
			</form>

			<hr>

			<h2>Sayfa Kilidi (isteğe bağlı)</h2>
			<p class="qmo-aciklama">
				Menü ana sayfada yayınlanıyorsa buraya <strong>hiçbir şey yazmayın</strong> —
				ana sayfa her zaman herkese açık kalır. Yalnızca ayrı bir menü sayfası
				kullanıyorsanız o sayfanın slug'ını girin; oturumu olmayan ziyaretçiler
				kilit ekranı görür.
			</p>
			<form method="post" action="options.php">
				<?php settings_fields( 'qmo_sayfa_grup' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="qmo_korumali_sayfalar">Korunacak sayfa slug'ları</label></th>
						<td>
							<input type="text" id="qmo_korumali_sayfalar" name="qmo_korumali_sayfalar" class="regular-text"
								value="<?php echo esc_attr( get_option( 'qmo_korumali_sayfalar', '' ) ); ?>"
								placeholder="menu, menu-tr">
							<p class="description">Virgülle ayırın. Boş bırakılırsa sayfa kilidi kapalıdır (önerilen).</p>
						</td>
					</tr>
				</table>
				<?php submit_button( 'Kaydet' ); ?>
			</form>
		</div>
		<?php
	}
}

/* -------------------------------------------------------------------------
 * Geriye dönük uyumluluk
 * ---------------------------------------------------------------------- */

if ( ! function_exists( 'qr_masa_ayar_sayfasi' ) ) {
	/**
	 * @deprecated qmo_oturum_ayar_sayfasi() kullanın.
	 */
	function qr_masa_ayar_sayfasi() {
		qmo_oturum_ayar_sayfasi();
	}
}
