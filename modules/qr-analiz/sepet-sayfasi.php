<?php
/**
 * KATEGORİ: Sepet & Sipariş (qrms-an-sepet).
 *
 * Veri kaynağı Faz 6'nın yazdığı olaylardır: cart_add, cart_remove,
 * order_sent, order_failed, order_blocked. Bu sayfa yeni olay ÜRETMEZ;
 * biriken satırları okur.
 *
 * YAZIM STRATEJİSİ (Faz 6, sorgular buna göre)
 *   cart_add / cart_remove : istemci 3 sn debounce + toplu gönderim. Her
 *     ekleme/çıkarma TIKLAMASI bir satırdır; oturum sonu toplu yazım
 *     seçilmedi (terk edilen sepeti kaybettirirdi).
 *   order_sent / order_failed : sipariş kalemi başına BİR satır. Adet kadar
 *     satır yazılmaz — şemada adet sütunu yoktur. "Kaç porsiyon" buradan
 *     çıkmaz; "kaç siparişte bu ürün vardı" çıkar.
 *
 * OTURUM
 * Tabloda oturum sütunu yoktur. Yaklaşık kimlik: ip_hash + masa_no +
 * 2 saatlik zaman penceresi. Aynı masada öğle/akşam ayrı düşer; restoran
 * Wi-Fi'sinde aynı IP'nin 2 saat içindeki farklı müşterileri birleşebilir.
 * Arayüz bunu "yaklaşık" diye söyler.
 *
 * LİSANS. Kategori chatbot'a bağlıdır. Hub kartı lisansta pasifse hiç
 * basılmaz (bkz. qrms_module_qr_analiz_gecerli_sayfalar); sayfa yine de
 * kayıtlı kalır ki doğrudan URL boş tablo değil anlamlı bir mesaj göstersin.
 *
 * PERFORMANS. Tek GROUP BY (idx_td / idx_masa_td ile sınırlı aralık),
 * oturum eşlemesi PHP'de. N+1 yok. Sayaçlar istek içi önbelleklidir.
 *
 * @package QR_Menu_Suite
 */

defined( 'ABSPATH' ) || exit;

/**
 * Ekrandaki ürün tablolarının satır tavanı (CSV tavan uygulanmaz).
 */
const QRMS_ANALITIK_SEPET_LIMIT = 50;

if ( ! function_exists( 'qrms_analitik_sepet_lisansli' ) ) {

	/**
	 * Sepet & Sipariş kategorisi bu kurulumda lisanslı mı?
	 *
	 * Modül yükleyicisi yoksa (stub test) evet sayılır: lisans süzme kendi
	 * testinde, hesaplama testleri lisanssız da çalışabilsin.
	 *
	 * @return bool
	 */
	function qrms_analitik_sepet_lisansli() {
		if ( ! class_exists( 'QRMS_Module_Loader' ) ) {
			return true;
		}

		return QRMS_Module_Loader::is_module_active( 'qr-chatbot' );
	}
}

if ( ! function_exists( 'qrms_analitik_sepet_oturum_anahtari' ) ) {

	/**
	 * Yaklaşık oturum kimliği.
	 *
	 * @param string $ip      ip_hash.
	 * @param string $masa    masa_no.
	 * @param string $pencere SQL'in ürettiği pencere etiketi.
	 * @return string
	 */
	function qrms_analitik_sepet_oturum_anahtari( $ip, $masa, $pencere ) {
		return (string) $ip . '|' . (string) $masa . '|' . (string) $pencere;
	}
}

if ( ! function_exists( 'qrms_analitik_sepet_hesapla' ) ) {

	/**
	 * GROUP BY satırlarını özet / tablolara çevirir — saf fonksiyon.
	 *
	 * Veritabanına gitmez; tek sorgunun çıktısını PHP'de oturumlara fold eder.
	 * Bu yüzden doğrudan test edilir ve N+1 üretemez.
	 *
	 * Girdi satırı: ip_hash, masa_no, pencere, event_type, item_id,
	 * item_name, category_name, adet [, oturum].
	 *
	 * @param array $gruplar QRMS_Analitik::sepet_olay_gruplari() satırları.
	 * @param int   $gun     Aralığın gün sayısı (hata dağılımının kırılımı).
	 * @param int   $limit   Tablo satır tavanı (0 = hepsi; CSV bunu kullanır).
	 * @return array<string,mixed>
	 */
	function qrms_analitik_sepet_hesapla( array $gruplar, $gun = 1, $limit = QRMS_ANALITIK_SEPET_LIMIT ) {
		$gun   = max( 1, (int) $gun );
		$limit = max( 0, (int) $limit );

		$oturumlar = array();
		$hata_oturum = array();
		$add_global  = array();
		$rm_global   = array();

		$cart_add_olay = 0;
		$cart_add_urun = array();
		$blocked_olay  = 0;
		$failed_olay   = 0;

		foreach ( $gruplar as $r ) {
			if ( ! is_array( $r ) ) {
				continue;
			}

			$tip = isset( $r['event_type'] ) ? (string) $r['event_type'] : '';
			$adet = isset( $r['adet'] ) ? (int) $r['adet'] : 0;

			if ( $adet <= 0 || '' === $tip ) {
				continue;
			}

			$pencere = isset( $r['pencere'] ) ? (string) $r['pencere'] : '';
			$oturum  = isset( $r['oturum'] ) && '' !== $r['oturum']
				? (string) $r['oturum']
				: qrms_analitik_sepet_oturum_anahtari(
					isset( $r['ip_hash'] ) ? $r['ip_hash'] : '',
					isset( $r['masa_no'] ) ? $r['masa_no'] : '',
					$pencere
				);

			$id  = isset( $r['item_id'] ) ? (int) $r['item_id'] : 0;
			$ad  = isset( $r['item_name'] ) ? (string) $r['item_name'] : '';
			$kat = isset( $r['category_name'] ) ? (string) $r['category_name'] : '';

			if ( ! isset( $oturumlar[ $oturum ] ) ) {
				$oturumlar[ $oturum ] = array(
					'pencere'  => $pencere,
					'add'      => false,
					'sent'     => false,
					'failed'   => false,
					'urun_add' => array(),
					'urun_sent' => array(),
					'urun_blk' => array(),
				);
			}

			$o = &$oturumlar[ $oturum ];

			if ( 'cart_add' === $tip ) {
				$o['add']         = true;
				$cart_add_olay   += $adet;
				$cart_add_urun[ $id ] = true;
				if ( ! isset( $add_global[ $id ] ) ) {
					$add_global[ $id ] = 0;
				}
				$add_global[ $id ] += $adet;
				if ( ! isset( $o['urun_add'][ $id ] ) ) {
					$o['urun_add'][ $id ] = array(
						'adet'     => 0,
						'ad'       => $ad,
						'kategori' => $kat,
					);
				}
				$o['urun_add'][ $id ]['adet'] += $adet;
				if ( '' !== $ad ) {
					$o['urun_add'][ $id ]['ad'] = $ad;
				}
			} elseif ( 'cart_remove' === $tip ) {
				if ( ! isset( $rm_global[ $id ] ) ) {
					$rm_global[ $id ] = array(
						'adet'     => 0,
						'ad'       => $ad,
						'kategori' => $kat,
					);
				}
				$rm_global[ $id ]['adet'] += $adet;
				if ( '' !== $ad ) {
					$rm_global[ $id ]['ad'] = $ad;
				}
			} elseif ( 'order_sent' === $tip ) {
				$o['sent']            = true;
				$o['urun_sent'][ $id ] = true;
			} elseif ( 'order_failed' === $tip ) {
				$o['failed']    = true;
				$failed_olay  += $adet;
				$kova          = ( $gun > 1 && strlen( $pencere ) >= 10 ) ? substr( $pencere, 0, 10 ) : $pencere;
				if ( '' === $kova ) {
					$kova = '—';
				}
				if ( ! isset( $hata_oturum[ $kova ] ) ) {
					$hata_oturum[ $kova ] = array();
				}
				$hata_oturum[ $kova ][ $oturum ] = true;
			} elseif ( 'order_blocked' === $tip ) {
				$blocked_olay    += $adet;
				if ( ! isset( $o['urun_blk'][ $id ] ) ) {
					$o['urun_blk'][ $id ] = array(
						'adet'     => 0,
						'ad'       => $ad,
						'kategori' => $kat,
					);
				}
				$o['urun_blk'][ $id ]['adet'] += $adet;
				if ( '' !== $ad ) {
					$o['urun_blk'][ $id ]['ad'] = $ad;
				}
			}

			unset( $o );
		}

		$oturum_add  = 0;
		$oturum_sent = 0;
		$oturum_terk = 0;
		$oturum_fail = 0;

		$terk_urun  = array();
		$engellenen = array();

		foreach ( $oturumlar as $o ) {
			if ( $o['add'] ) {
				++$oturum_add;
			}
			if ( $o['sent'] ) {
				++$oturum_sent;
			}
			if ( $o['add'] && ! $o['sent'] ) {
				++$oturum_terk;
			}
			if ( $o['failed'] ) {
				++$oturum_fail;
			}

			foreach ( $o['urun_add'] as $id => $u ) {
				if ( isset( $o['urun_sent'][ $id ] ) ) {
					continue;
				}

				if ( ! isset( $terk_urun[ $id ] ) ) {
					$terk_urun[ $id ] = array(
						'id'       => $id,
						'ad'       => $u['ad'],
						'kategori' => $u['kategori'],
						'terk'     => 0,
						'ekleme'   => 0,
					);
				}
				++$terk_urun[ $id ]['terk'];
				$terk_urun[ $id ]['ekleme'] += $u['adet'];
				if ( '' !== $u['ad'] ) {
					$terk_urun[ $id ]['ad'] = $u['ad'];
				}
			}

			foreach ( $o['urun_blk'] as $id => $u ) {
				if ( ! isset( $engellenen[ $id ] ) ) {
					$engellenen[ $id ] = array(
						'id'       => $id,
						'ad'       => $u['ad'],
						'kategori' => $u['kategori'],
						'siparis'  => 0,
					);
				}
				$engellenen[ $id ]['siparis'] += $u['adet'];
				if ( '' !== $u['ad'] ) {
					$engellenen[ $id ]['ad'] = $u['ad'];
				}
			}
		}

		$terk_oran = $oturum_add > 0 ? (int) round( ( $oturum_terk / $oturum_add ) * 100 ) : 0;

		$cikarilan = array();

		foreach ( $rm_global as $id => $u ) {
			$cikarilan[] = array(
				'id'       => $id,
				'ad'       => $u['ad'],
				'kategori' => $u['kategori'],
				'cikarma'  => $u['adet'],
				'ekleme'   => isset( $add_global[ $id ] ) ? (int) $add_global[ $id ] : 0,
			);
		}

		$terk_urun = array_values( $terk_urun );
		usort(
			$terk_urun,
			static function ( $a, $b ) {
				if ( $a['terk'] === $b['terk'] ) {
					if ( $a['ekleme'] === $b['ekleme'] ) {
						return strcmp( $a['ad'], $b['ad'] );
					}

					return $a['ekleme'] > $b['ekleme'] ? -1 : 1;
				}

				return $a['terk'] > $b['terk'] ? -1 : 1;
			}
		);

		$cikarilan = array_values( $cikarilan );
		usort(
			$cikarilan,
			static function ( $a, $b ) {
				if ( $a['cikarma'] === $b['cikarma'] ) {
					return strcmp( $a['ad'], $b['ad'] );
				}

				return $a['cikarma'] > $b['cikarma'] ? -1 : 1;
			}
		);

		foreach ( $cikarilan as $i => $satir ) {
			$cikarilan[ $i ]['oran'] = $satir['cikarma'] > 0
				? round( $satir['ekleme'] / $satir['cikarma'], 2 )
				: null;
		}

		$engellenen = array_values( $engellenen );
		usort(
			$engellenen,
			static function ( $a, $b ) {
				if ( $a['siparis'] === $b['siparis'] ) {
					return strcmp( $a['ad'], $b['ad'] );
				}

				return $a['siparis'] > $b['siparis'] ? -1 : 1;
			}
		);

		ksort( $hata_oturum );
		$hatalar = array();
		foreach ( $hata_oturum as $etiket => $oturum_kume ) {
			$hatalar[] = array(
				'label' => $etiket,
				'sayi'  => count( $oturum_kume ),
			);
		}

		$bos = ( 0 === $cart_add_olay && 0 === $oturum_sent && 0 === $blocked_olay && 0 === $failed_olay && empty( $cikarilan ) );

		$kes = static function ( array $liste ) use ( $limit ) {
			return $limit > 0 ? array_slice( $liste, 0, $limit ) : $liste;
		};

		return array(
			'ozet'       => array(
				'cart_add'      => $cart_add_olay,
				'cart_add_urun' => count( $cart_add_urun ),
				'order_sent'    => $oturum_sent,
				'terk'          => $oturum_terk,
				'terk_oran'     => $terk_oran,
				'oturum_add'    => $oturum_add,
				'blocked'       => $blocked_olay,
				'failed'        => $oturum_fail,
				'failed_olay'   => $failed_olay,
			),
			'terk_urun'  => $kes( $terk_urun ),
			'cikarilan'  => $kes( $cikarilan ),
			'engellenen' => $kes( $engellenen ),
			'hatalar'    => $hatalar,
			'bos'        => $bos,
			'tavan'      => $limit,
		);
	}
}

if ( ! function_exists( 'qrms_analitik_sepet_verisi' ) ) {

	/**
	 * Sayfanın bütün bölümlerinin verisi — TEK yerde toplanır.
	 *
	 * Ekran (AJAX) ve CSV aynı fonksiyondan beslenir. İstek içi önbellek:
	 * aynı aralık+masa için ikinci çağrı sorgu açmaz.
	 *
	 * @param array  $aralik QRMS_Analitik_Filtre::aralik() çıktısı.
	 * @param string $masa   Masa filtresi.
	 * @param int    $limit  Tablo satır tavanı (0 = hepsi).
	 * @return array<string,mixed>
	 */
	function qrms_analitik_sepet_verisi( array $aralik, $masa = '', $limit = QRMS_ANALITIK_SEPET_LIMIT ) {
		$kutu = &qrms_analitik_onbellek_kutusu();
		$anahtar = 'sepet|' . $aralik['bas'] . '|' . $aralik['bit'] . '|' . (string) $masa . '|' . (int) $limit;

		if ( isset( $kutu[ $anahtar ] ) ) {
			return $kutu[ $anahtar ];
		}

		$gruplar = QRMS_Analitik::sepet_olay_gruplari( $aralik['bas'], $aralik['bit'], $masa );
		$gun     = isset( $aralik['gun'] ) ? (int) $aralik['gun'] : 1;

		$kutu[ $anahtar ] = qrms_analitik_sepet_hesapla( $gruplar, $gun, $limit );

		return $kutu[ $anahtar ];
	}
}

if ( ! function_exists( 'qrms_analitik_sepet_urunum_yok_url' ) ) {

	/**
	 * Ürünüm Yok ekranının, aktif analitik filtresini taşıyan adresi.
	 *
	 * @return string
	 */
	function qrms_analitik_sepet_urunum_yok_url() {
		return add_query_arg(
			array_merge(
				array( 'page' => 'qrms-rm-urunum-yok' ),
				QRMS_Analitik_Filtre::args()
			),
			admin_url( 'admin.php' )
		);
	}
}

if ( ! function_exists( 'qrms_analitik_sepet_firebase_url' ) ) {

	/**
	 * Firebase & Şube Ayarları ekranı (Güvenlik Ayarı modülü).
	 *
	 * @return string
	 */
	function qrms_analitik_sepet_firebase_url() {
		$slug = defined( 'QRMS_GUVENLIK_FIREBASE_SAYFA' ) ? QRMS_GUVENLIK_FIREBASE_SAYFA : 'qrms-analiz-ayarlar';

		return admin_url( 'admin.php?page=' . $slug );
	}
}

if ( ! function_exists( 'qrms_analitik_sayfa_sepet' ) ) {

	/**
	 * Sepet & Sipariş ekranı.
	 *
	 * @return void
	 */
	function qrms_analitik_sayfa_sepet() {
		if ( ! current_user_can( QRMS_Admin::CAPABILITY ) ) {
			wp_die( esc_html__( 'Bu sayfayı görüntüleme yetkiniz yok.', 'qrms' ) );
		}

		if ( ! qrms_analitik_sepet_lisansli() ) {
			?>
			<div class="wrap qrms-an qrms-an-sepet">
				<div class="qrms-an-header">
					<div class="qrms-an-header-text">
						<h1 class="qrms-an-title"><?php esc_html_e( 'Sepet & Sipariş', 'qrms' ); ?></h1>
					</div>
				</div>

				<div class="qrms-an-teshis qrms-an-teshis-uyari">
					<span class="qrms-an-teshis-icon dashicons dashicons-lock" aria-hidden="true"></span>
					<div class="qrms-an-teshis-body">
						<h2 class="qrms-an-teshis-title"><?php esc_html_e( 'Chatbot Asistan bu lisansta kapalı', 'qrms' ); ?></h2>
						<p class="qrms-an-teshis-text">
							<?php esc_html_e( 'Sepet ve sipariş sayıları Chatbot Asistan modülünden gelir. Bu kategori lisansınızda aktif olmadığı için burada tablo yok — boş bir ekran, veri yokmuş gibi görünmesin diye bilinçli olarak basılmıyor.', 'qrms' ); ?>
						</p>
					</div>
				</div>
			</div>
			<?php
			return;
		}

		$csv_url = add_query_arg(
			array(
				'action'   => 'qrms_analitik_csv',
				'kategori' => 'sepet',
				'donem'    => QRMS_Analitik_Filtre::donem(),
				'bas'      => QRMS_Analitik_Filtre::bas(),
				'bit'      => QRMS_Analitik_Filtre::bit(),
				'masa'     => QRMS_Analitik_Filtre::masa(),
				'security' => wp_create_nonce( QRMS_Analitik::NONCE_CSV ),
			),
			admin_url( 'admin-ajax.php' )
		);
		?>
		<div class="wrap qrms-an qrms-an-sepet">

			<div class="qrms-an-header">
				<div class="qrms-an-header-text">
					<h1 class="qrms-an-title"><?php esc_html_e( 'Sepet & Sipariş', 'qrms' ); ?></h1>
					<p class="qrms-an-subtitle">
						<?php esc_html_e( 'Sepete eklenen, gönderilen, terk edilen ve tükendi diye engellenen siparişler.', 'qrms' ); ?>
					</p>
				</div>

				<div class="qrms-an-header-actions">
					<a class="qrms-an-btn" href="<?php echo esc_url( $csv_url ); ?>">
						<span class="dashicons dashicons-download" aria-hidden="true"></span>
						<?php esc_html_e( 'Bu sayfayı CSV indir', 'qrms' ); ?>
					</a>
				</div>
			</div>

			<?php qrms_analitik_filtre_cubugu( 'qrms-an-sepet' ); ?>

			<p class="qrms-an-panel-note" id="qrms-an-sepet-oturum-notu">
				<?php
				printf(
					/* translators: %d: oturum penceresi (saat). */
					esc_html__( 'Terk oranı yaklaşık bir oturuma göredir: aynı IP + aynı masa + %d saatlik pencere. Kesin bir oturum kimliği yoktur; öğle ile akşam ayrılır, restoran Wi-Fi’sinde yakın saatlerdeki farklı müşteriler birleşebilir.', 'qrms' ),
					(int) QRMS_Analitik::OTURUM_SAAT
				);
				?>
			</p>

			<div id="qrms-an-sepet-bos" hidden></div>

			<div class="qrms-an-cards" id="qrms-an-cards" aria-live="polite">
				<div class="qrms-an-card qrms-an-skeleton"></div>
				<div class="qrms-an-card qrms-an-skeleton"></div>
				<div class="qrms-an-card qrms-an-skeleton"></div>
				<div class="qrms-an-card qrms-an-skeleton"></div>
				<div class="qrms-an-card qrms-an-skeleton"></div>
			</div>

			<div class="qrms-an-panel">
				<div class="qrms-an-panel-header">
					<h2 class="qrms-an-panel-title">
						<span class="dashicons dashicons-dismiss" aria-hidden="true"></span>
						<?php esc_html_e( 'En çok sepete eklenip gönderilmeyen ürünler', 'qrms' ); ?>
					</h2>
				</div>
				<p class="qrms-an-panel-note">
					<?php esc_html_e( 'Sepete konup aynı oturumda siparişe dönüşmeyen ürünler. Fiyat direnci veya tereddüt göstergesidir; sıralama terk edilen oturum sayısına göredir.', 'qrms' ); ?>
				</p>
				<div id="qrms-an-sepet-terk">
					<div class="qrms-an-loading"><?php esc_html_e( 'Yükleniyor', 'qrms' ); ?></div>
				</div>
			</div>

			<div class="qrms-an-panel">
				<div class="qrms-an-panel-header">
					<h2 class="qrms-an-panel-title">
						<span class="dashicons dashicons-undo" aria-hidden="true"></span>
						<?php esc_html_e( 'Sepetten çıkarılan ürünler', 'qrms' ); ?>
					</h2>
				</div>
				<p class="qrms-an-panel-note">
					<?php esc_html_e( 'Sürekli eklenip çıkarılan ürün ayrı bir sinyaldir: ekleme / çıkarma oranı 1’e yaklaştıkça tereddüt yükselir.', 'qrms' ); ?>
				</p>
				<div id="qrms-an-sepet-cikar">
					<div class="qrms-an-loading"><?php esc_html_e( 'Yükleniyor', 'qrms' ); ?></div>
				</div>
			</div>

			<div class="qrms-an-panel">
				<div class="qrms-an-panel-header">
					<h2 class="qrms-an-panel-title">
						<span class="dashicons dashicons-hidden" aria-hidden="true"></span>
						<?php esc_html_e( 'Engellenen siparişler', 'qrms' ); ?>
					</h2>
					<a class="qrms-an-btn qrms-an-btn-small" href="<?php echo esc_url( qrms_analitik_sepet_urunum_yok_url() ); ?>">
						<span class="dashicons dashicons-external" aria-hidden="true"></span>
						<?php esc_html_e( 'Ürünüm Yok', 'qrms' ); ?>
					</a>
				</div>
				<p class="qrms-an-panel-note">
					<?php esc_html_e( 'Tükendi işaretli olduğu için siparişi kesilen ürünler. Bu tablo Ürünüm Yok ekranının değerini doğrudan gösterir.', 'qrms' ); ?>
				</p>
				<div id="qrms-an-sepet-engel">
					<div class="qrms-an-loading"><?php esc_html_e( 'Yükleniyor', 'qrms' ); ?></div>
				</div>
			</div>

			<div class="qrms-an-panel" id="qrms-an-sepet-hata-panel" hidden>
				<div class="qrms-an-panel-header">
					<h2 class="qrms-an-panel-title">
						<span class="dashicons dashicons-warning" aria-hidden="true"></span>
						<?php esc_html_e( 'Sipariş hataları', 'qrms' ); ?>
					</h2>
				</div>
				<div id="qrms-an-sepet-hata-uyari"></div>
				<div id="qrms-an-sepet-hata"></div>
			</div>
		</div>
		<?php
	}
}
