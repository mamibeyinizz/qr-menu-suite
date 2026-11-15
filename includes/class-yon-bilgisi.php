<?php
/**
 * Modül yön bilgisi: her modülün ana sayfasında "Nereden başlamalıyım?" kutusu.
 *
 * Restoran sahibinin dilinde 3 adım; her adım ilgili alt sayfaya bağlanır.
 * Bağlantı yalnız sayfa bu kurulumda kayıtlıysa (lisans/paket) basılır, değilse
 * adım düz metin olarak kalır. Kutu kapatılırsa tarayıcıda kapalı hatırlanır.
 *
 * @package QR_Menu_Suite
 */

defined( 'ABSPATH' ) || exit;

/**
 * Yön bilgisi.
 */
class QRMS_Yon_Bilgisi {

	/**
	 * Modül slug'ı => [ 'ozet' => string, 'adimlar' => [ [metin, hedef] ] ].
	 * Hedef: admin sayfa slug'ı (admin.php?page=…) ya da wp-admin'e göreli adres.
	 *
	 * @return array
	 */
	public static function rehberler() {
		$r = array(
			'_genel'                   => array(
				'ozet'    => __( 'Menünüzü yayına almak için üç adım yeterli.', 'qrms' ),
				'adimlar' => array(
					array( __( 'Ürünlerinizi ve fiyatlarınızı kontrol edin.', 'qrms' ), 'edit.php?post_type=rma_menu_item' ),
					array( __( 'Masalarınızı oluşturup QR kodlarını yazdırın.', 'qrms' ), 'qrms-module-qr-masa' ),
					array( __( 'Menünüzü telefonunuzdan açıp müşteri gözüyle bakın.', 'qrms' ), '@menu' ),
				),
			),
			'restoran-menu'            => array(
				'ozet'    => __( 'Ürünlerinizi, fiyatlarınızı ve menünün görünümünü buradan yönetirsiniz.', 'qrms' ),
				'adimlar' => array(
					array( __( 'Önce kategorileri düzenleyin (Çorbalar, Ana Yemekler…).', 'qrms' ), 'edit-tags.php?taxonomy=rma_category&post_type=rma_menu_item' ),
					array( __( 'Ürün ekleyin: ad, fiyat ve fotoğraf yeterli.', 'qrms' ), 'post-new.php?post_type=rma_menu_item' ),
					array( __( 'Biten ürünleri Tükenen Ürünler’den tek dokunuşla kapatın.', 'qrms' ), 'qrms-rm-urunum-yok' ),
				),
			),
			'qr-masa'                  => array(
				'ozet'    => __( 'Her masaya özel QR kod üretir; hangi masadan sipariş ve çağrı geldiğini bilirsiniz.', 'qrms' ),
				'adimlar' => array(
					array( __( 'Masalarınızı ekleyin (tek tek ya da toplu).', 'qrms' ), '' ),
					array( __( 'QR kodları indirip yazdırın, her masaya kendi kodunu koyun.', 'qrms' ), '' ),
					array( __( 'Bir kodu telefonla okutup masanın doğru göründüğünü deneyin.', 'qrms' ), '' ),
				),
			),
			'qr-servis-paneli'         => array(
				'ozet'    => __( 'Garson çağrıları, hesap istekleri ve siparişler bu ekrana düşer.', 'qrms' ),
				'adimlar' => array(
					array( __( 'Bu sayfayı kasadaki ya da garsonun tabletinde açık tutun.', 'qrms' ), '' ),
					array( __( 'Gelen çağrıyı “Tamamlandı” ile kapatın; müşteri bilgilendirilir.', 'qrms' ), '' ),
					array( __( 'Bildirim sesi ve personel ayarlarını yapın.', 'qrms' ), 'qrms-sp-ayarlar' ),
				),
			),
			'qr-analiz'                => array(
				'ozet'    => __( 'Menünüze kaç kişinin baktığını, en çok neyin ilgi gördüğünü gösterir.', 'qrms' ),
				'adimlar' => array(
					array( __( 'Genel Bakış’ta günlük ziyaret ve okutma sayısına bakın.', 'qrms' ), 'qrms-an-genel' ),
					array( __( 'Ürünler’de en çok ve en az ilgi gören ürünleri görün.', 'qrms' ), 'qrms-an-urunler' ),
					array( __( 'Menüde bulunamayan aramalar, eklemeniz gereken ürünleri söyler.', 'qrms' ), 'qrms-an-urunler#qrms-an-arama-panel' ),
				),
			),
			'qr-menu-muhendisligi'     => array(
				'ozet'    => __( 'Hangi ürünün size gerçekten kazandırdığını gösterir.', 'qrms' ),
				'adimlar' => array(
					array( __( 'Malzeme fiyatlarını girin (kg/lt başına).', 'qrms' ), 'qrms-mm-malzeme' ),
					array( __( 'Ürün maliyetlerini tarif üzerinden hesaplayın.', 'qrms' ), 'qrms-mm-maliyet' ),
					array( __( 'Raporda kârlı ve zarar ettiren ürünleri görün.', 'qrms' ), 'qrms-mm-rapor' ),
				),
			),
			'yorum-feedback'           => array(
				'ozet'    => __( 'Müşteri yorumlarını toplar, memnun müşteriyi Google’a yönlendirir.', 'qrms' ),
				'adimlar' => array(
					array( __( 'Puanlama sorularını ve ayarları belirleyin.', 'qrms' ), 'qrms-yf-ayarlar' ),
					array( __( 'Google yorum bağlantınızı ve ödülü ekleyin.', 'qrms' ), 'qrms-yf-odul' ),
					array( __( 'Gelen yorumları okuyun, olumsuzlara hızlı dönün.', 'qrms' ), 'qrms-yf-yorumlar' ),
				),
			),
			'qr-ceviri'                => array(
				'ozet'    => __( 'Menünüzü yabancı misafirler için başka dillerde gösterir.', 'qrms' ),
				'adimlar' => array(
					array( __( 'Göstermek istediğiniz dilleri seçin.', 'qrms' ), 'qrms-cv-diller' ),
					array( __( 'Çevirileri CSV ile indirip doldurun.', 'qrms' ), 'qrms-cv-disa' ),
					array( __( 'Doldurduğunuz dosyayı geri yükleyin.', 'qrms' ), 'qrms-cv-ice' ),
				),
			),
			'qr-chatbot'               => array(
				'ozet'    => __( 'Misafirlerin menüyle ilgili sorularını sizin yerinize yanıtlar.', 'qrms' ),
				'adimlar' => array(
					array( __( 'Asistanın adını ve karşılama mesajını belirleyin.', 'qrms' ), 'qrms-chatbot-bot-identity' ),
					array( __( 'Sık sorulan hazır soruları ekleyin.', 'qrms' ), 'qrms-chatbot-quick-replies' ),
					array( __( 'Cevaplanamayan soruları düzenli kontrol edin.', 'qrms' ), 'qrms-chatbot-unanswered' ),
				),
			),
			'qr-galeri'                => array(
				'ozet'    => __( 'Mekânınızın ve yemeklerinizin fotoğraflarını sergiler.', 'qrms' ),
				'adimlar' => array(
					array( __( 'Bölüm oluşturun (Salon, Teras, Yemekler…).', 'qrms' ), 'qrmgm-sections' ),
					array( __( 'Fotoğrafları yükleyip bölümlere ekleyin.', 'qrms' ), 'qrmgm-images' ),
					array( __( 'Görünüm ayarlarını yapın.', 'qrms' ), 'qrmgm-settings' ),
				),
			),
			'qr-acilis-ekrani'         => array(
				'ozet'    => __( 'Menü açılmadan önce misafiri karşılayan ekran (Wi-Fi, sosyal medya, ödeme).', 'qrms' ),
				'adimlar' => array(
					array( __( 'Logo ve arka planı seçin.', 'qrms' ), 'qrms-ae-gorunum' ),
					array( __( 'Wi-Fi ve bağlantı butonlarını ekleyin.', 'qrms' ), 'qrms-ae-butonlar' ),
					array( __( 'Kabul ettiğiniz ödeme yöntemlerini işaretleyin.', 'qrms' ), 'qrms-ae-odeme' ),
				),
			),
			'qr-calisma-saatleri'      => array(
				'ozet'    => __( 'Açık/kapalı durumunu ve çalışma saatlerinizi menüde gösterir.', 'qrms' ),
				'adimlar' => array(
					array( __( 'Haftanın her günü için açılış ve kapanış saatini girin.', 'qrms' ), '' ),
					array( __( 'Tatil ve özel günleri ekleyin.', 'qrms' ), '' ),
					array( __( 'Kaydedip menüde doğru göründüğünü kontrol edin.', 'qrms' ), '@menu' ),
				),
			),
			'header-footer-builder'    => array(
				'ozet'    => __( 'Menünün üst ve alt kısmında görünen logo, bağlantılar ve iletişim bilgileri.', 'qrms' ),
				'adimlar' => array(
					array( __( 'Logonuzu ve üst menü bağlantılarını düzenleyin.', 'qrms' ), '' ),
					array( __( 'Alt kısma adres, telefon ve sosyal medyayı ekleyin.', 'qrms' ), '' ),
					array( __( 'Telefonda nasıl göründüğüne bakın.', 'qrms' ), '@menu' ),
				),
			),
			'qr-masa-oturum-guvenligi' => array(
				'ozet'    => __( 'Restoranda olmayan birinin masa adına sipariş vermesini engeller.', 'qrms' ),
				'adimlar' => array(
					array( __( 'Masa oturumunun ne kadar süre geçerli kalacağını seçin.', 'qrms' ), 'qrms-guvenlik-oturum' ),
					array( __( 'Varsayılanlar çoğu restoran için uygundur; emin değilseniz değiştirmeyin.', 'qrms' ), '' ),
				),
			),
		);

		/**
		 * Modül yön bilgileri.
		 *
		 * @param array $r Modül => rehber.
		 */
		return (array) apply_filters( 'qrms_yon_bilgisi_rehberleri', $r );
	}

	/**
	 * Hedefi adrese çevirir; sayfa kayıtlı değilse boş döner.
	 *
	 * @param string $hedef Hedef.
	 * @return string
	 */
	private static function adres( $hedef ) {
		global $_registered_pages;

		$hedef = (string) $hedef;
		if ( '' === $hedef ) {
			return '';
		}
		if ( '@menu' === $hedef ) {
			return home_url( '/' );
		}

		$capa = '';
		if ( false !== strpos( $hedef, '#' ) ) {
			list( $hedef, $capa ) = explode( '#', $hedef, 2 );
			$capa = '#' . $capa;
		}

		if ( false !== strpos( $hedef, '.php' ) ) {
			return admin_url( $hedef ) . $capa;
		}

		$kayitli = false;
		if ( is_array( $_registered_pages ) ) {
			foreach ( array_keys( $_registered_pages ) as $kanca ) {
				if ( substr( $kanca, -strlen( '_page_' . $hedef ) ) === '_page_' . $hedef ) {
					$kayitli = true;
					break;
				}
			}
		}

		return $kayitli ? admin_url( 'admin.php?page=' . rawurlencode( $hedef ) ) . $capa : '';
	}

	/**
	 * Kutuyu basar.
	 *
	 * @param string $anahtar Modül slug'ı ya da '_genel'.
	 * @return void
	 */
	public static function render( $anahtar ) {
		$hepsi = self::rehberler();
		if ( empty( $hepsi[ $anahtar ]['adimlar'] ) ) {
			return;
		}

		$r  = $hepsi[ $anahtar ];
		$id = 'qrms-yon-' . sanitize_html_class( $anahtar );
		?>
		<details class="qrms-yon" id="<?php echo esc_attr( $id ); ?>" data-yon="<?php echo esc_attr( $anahtar ); ?>" open>
			<summary class="qrms-yon__baslik">
				<span class="qrms-yon__ikon dashicons dashicons-location-alt" aria-hidden="true"></span>
				<span class="qrms-yon__soru"><?php esc_html_e( 'Nereden başlamalıyım?', 'qrms' ); ?></span>
				<span class="qrms-yon__ozet"><?php echo esc_html( $r['ozet'] ); ?></span>
			</summary>
			<ol class="qrms-yon__adimlar">
				<?php foreach ( $r['adimlar'] as $i => $adim ) : ?>
					<?php $url = self::adres( isset( $adim[1] ) ? $adim[1] : '' ); ?>
					<li class="qrms-yon__adim">
						<span class="qrms-yon__no" aria-hidden="true"><?php echo esc_html( (string) ( $i + 1 ) ); ?></span>
						<?php if ( '' !== $url ) : ?>
							<a class="qrms-yon__link" href="<?php echo esc_url( $url ); ?>"<?php echo '@menu' === $adim[1] ? ' target="_blank" rel="noopener"' : ''; ?>><?php echo esc_html( $adim[0] ); ?> <span aria-hidden="true">→</span></a>
						<?php else : ?>
							<span class="qrms-yon__metin"><?php echo esc_html( $adim[0] ); ?></span>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ol>
		</details>
		<script>
		(function(){var d=document.getElementById(<?php echo wp_json_encode( $id ); ?>);if(!d)return;var k='qrmsYonKapali:'+d.getAttribute('data-yon');
		var v=null;try{v=localStorage.getItem(k);}catch(e){}
		/* Telefonda ilk ekranın yarısını kaplamasın: kullanıcı açmadıysa kapalı başlar. */
		var dar=window.matchMedia&&window.matchMedia('(max-width: 782px)').matches;
		if(v==='1'||(dar&&v!=='0'))d.removeAttribute('open');
		d.addEventListener('toggle',function(){try{localStorage.setItem(k,d.open?'0':'1');}catch(e){}});})();
		</script>
		<?php
	}
}
