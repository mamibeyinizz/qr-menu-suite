<?php
/**
 * KATEGORİ: Masalar (qrms-an-masalar).
 *
 * Klasik paneldeki "Masalara Göre" kesiti buraya taşındı ve iki eksiği
 * kapatıldı: hiç okutulmamış masalar ile masa gruplarının toplamları.
 *
 * HİÇ OKUTULMAMIŞ MASA EN DEĞERLİ SATIRDIR. Kesit eskiden yalnızca analitik
 * tablosundan besleniyordu; oysa QR'ı hiç okutulmamış bir masanın orada
 * SATIRI YOKTUR ve listeden sessizce düşerdi. Restoran sahibi için bu satır
 * doğrudan aksiyondur: QR basılmamış, masaya yapıştırılmamış ya da yıpranmış
 * olabilir. Bu yüzden liste kayıtlı masalardan (qr-masa modülü) başlar,
 * sayaçlar onun üzerine yazılır.
 *
 * İKİ FARKLI GÖRÜNÜM. Sayfanın kendisi zaten masa kırılımıdır, bu yüzden
 * paylaşılan filtredeki masa seçimi burada "kırılımı daralt" değil "tek
 * masaya odaklan" anlamına gelir:
 *   - Masa seçili DEĞİLKEN : bütün masaların karşılaştırmalı listesi.
 *   - Masa seçiliyken      : o masanın karnesi (sayılar, sıralamadaki yeri,
 *                            grubuyla karşılaştırması) + grubun diğer masaları.
 *
 * VERİ UYUMSUZLUĞU. Analitik tablosundaki masa_no ile qr-masa'daki table_slug
 * aynı üreticiden gelir (sanitize_title), ama iki sütunun genişliği farklıdır:
 * masa_no varchar(64), table_slug varchar(100). 64 karakterden uzun bir slug
 * analitiğe kırpılarak yazılır ve birebir eşleşmezdi; eşleştirme bu yüzden
 * kırpılmış anahtar üzerinden yapılır. Silinmiş masaların kayıtları da durur:
 * onlar listeden DÜŞÜRÜLMEZ, "kayıtlı olmayan masa" olarak ayrıca işaretlenir
 * — geçmiş ciro bilgisi kaybolmasın.
 *
 * PERFORMANS: iki sorgu (masalar + tek GROUP BY sayaç), eşleştirme PHP'de.
 * Masa sayısı ürün sayısından küçüktür ama N+1 yine de açılmaz.
 *
 * @package QR_Menu_Suite
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'qrms_analitik_masa_anahtari' ) ) {

	/**
	 * Masa slug'ının analitik tablosundaki karşılığı.
	 *
	 * masa_no sütunu 64 karakterle sınırlıdır (QRMS_Analitik::MASA_UZUNLUK);
	 * kayıt yazılırken slug oraya kırpılır. Eşleştirme de aynı kırpmayı
	 * uygulamazsa uzun adlı masalar "hiç okutulmamış" görünürdü.
	 *
	 * @param string $slug Masa slug'ı.
	 * @return string
	 */
	function qrms_analitik_masa_anahtari( $slug ) {
		return substr( (string) $slug, 0, QRMS_Analitik::MASA_UZUNLUK );
	}
}

if ( ! function_exists( 'qrms_analitik_kayitli_masalar' ) ) {

	/**
	 * Kayıtlı masalar — istek içi önbellekli, TEK sorgu.
	 *
	 * @return array<int,array{slug:string,ad:string,grup:string}>
	 */
	function qrms_analitik_kayitli_masalar() {
		$kutu = &qrms_analitik_onbellek_kutusu();

		if ( isset( $kutu['masalar'] ) ) {
			return $kutu['masalar'];
		}

		$masalar = array();

		if ( class_exists( 'QMO_Masalar' ) && QMO_Masalar::tablo_var_mi() ) {
			foreach ( (array) QMO_Masalar::hepsi() as $masa ) {
				$slug = (string) $masa->table_slug;

				$masalar[] = array(
					'slug' => $slug,
					'ad'   => (string) $masa->table_name,
					// Grup tanımı qr-masa modülüyle ORTAKTIR: sondaki "-<sayı>"
					// eki atılmış slug ("ic-masa-12" -> "ic-masa"). Aynı kural
					// masalar yönetim ekranındaki çipleri de üretir.
					'grup' => QMO_Masalar::grup_adi( $slug ),
				);
			}
		}

		$kutu['masalar'] = $masalar;

		return $masalar;
	}
}

if ( ! function_exists( 'qrms_analitik_masa_karnesi' ) ) {

	/**
	 * Masa listesi + grup toplamları.
	 *
	 * Üç tür satır üretir ve üçü de listede kalır:
	 *   kayitli       — qr-masa'da tanımlı masa (okutulmamışsa sayılar 0).
	 *   kayitsiz      — analitikte kaydı olan ama artık tanımlı olmayan masa
	 *                   (silinmiş masa); geçmişi görünür kalsın diye durur.
	 *   masasiz       — QR okutmadan, doğrudan gelen hareketler ('' anahtarı).
	 *
	 * Saf birleştirme fonksiyonudur (veritabanına kendisi gitmez, iki kaynağı
	 * argüman alır), bu yüzden doğrudan test edilir.
	 *
	 * @param array $masalar  qrms_analitik_kayitli_masalar() çıktısı.
	 * @param array $sayaclar QRMS_Analitik::masa_sayaclari() çıktısı.
	 * @return array{satirlar:array<int,array<string,mixed>>,gruplar:array<int,array<string,mixed>>,ozet:array<string,int>}
	 */
	function qrms_analitik_masa_karnesi( array $masalar, array $sayaclar ) {
		$bos = array(
			'mv'  => 0,
			'pc'  => 0,
			'uv'  => 0,
			'son' => '',
		);

		$satirlar = array();
		$gruplar  = array();
		$eslesen  = array();
		$hic      = 0;

		foreach ( $masalar as $masa ) {
			$anahtar = qrms_analitik_masa_anahtari( $masa['slug'] );
			$sayac   = isset( $sayaclar[ $anahtar ] ) ? $sayaclar[ $anahtar ] : $bos;

			$eslesen[ $anahtar ] = true;

			if ( 0 === $sayac['mv'] + $sayac['pc'] ) {
				++$hic;
			}

			$satirlar[] = array(
				'masa'    => $masa['slug'],
				'label'   => '' !== $masa['ad'] ? $masa['ad'] : $masa['slug'],
				'grup'    => $masa['grup'],
				'durum'   => 'kayitli',
				'mv'      => $sayac['mv'],
				'pc'      => $sayac['pc'],
				'uv'      => $sayac['uv'],
				'son'     => $sayac['son'],
			);

			if ( ! isset( $gruplar[ $masa['grup'] ] ) ) {
				$gruplar[ $masa['grup'] ] = array(
					'grup'  => $masa['grup'],
					'masa'  => 0,
					'sessiz' => 0,
					'mv'    => 0,
					'pc'    => 0,
					'uv'    => 0,
				);
			}

			++$gruplar[ $masa['grup'] ]['masa'];
			$gruplar[ $masa['grup'] ]['mv'] += $sayac['mv'];
			$gruplar[ $masa['grup'] ]['pc'] += $sayac['pc'];
			$gruplar[ $masa['grup'] ]['uv'] += $sayac['uv'];

			if ( 0 === $sayac['mv'] + $sayac['pc'] ) {
				++$gruplar[ $masa['grup'] ]['sessiz'];
			}
		}

		// Kayıtlı masalarla eşleşmeyen sayaçlar: silinmiş masalar ve masasız
		// hareketler. İkisi de listede kalır ama farklı anlatılır.
		$kayitsiz = 0;
		$masasiz  = null;

		foreach ( $sayaclar as $anahtar => $sayac ) {
			if ( isset( $eslesen[ $anahtar ] ) ) {
				continue;
			}

			if ( '' === $anahtar ) {
				$masasiz = array(
					'masa'  => '',
					'label' => __( 'Masasız (doğrudan erişim)', 'qrms' ),
					'grup'  => '',
					'durum' => 'masasiz',
					'mv'    => $sayac['mv'],
					'pc'    => $sayac['pc'],
					'uv'    => $sayac['uv'],
					'son'   => $sayac['son'],
				);

				continue;
			}

			++$kayitsiz;

			$satirlar[] = array(
				'masa'  => $anahtar,
				'label' => $anahtar,
				'grup'  => '',
				'durum' => 'kayitsiz',
				'mv'    => $sayac['mv'],
				'pc'    => $sayac['pc'],
				'uv'    => $sayac['uv'],
				'son'   => $sayac['son'],
			);
		}

		// Hareketi çok olan üstte; hiç okutulmamışlar en altta ama listede.
		usort(
			$satirlar,
			static function ( $a, $b ) {
				$fark = ( $b['mv'] + $b['pc'] ) - ( $a['mv'] + $a['pc'] );

				return 0 !== $fark ? $fark : strcmp( $a['label'], $b['label'] );
			}
		);

		// Masasız satır her zaman en sonda: bir masa değil, bir artık kovadır.
		if ( null !== $masasiz ) {
			$satirlar[] = $masasiz;
		}

		usort(
			$gruplar,
			static function ( $a, $b ) {
				$fark = ( $b['mv'] + $b['pc'] ) - ( $a['mv'] + $a['pc'] );

				return 0 !== $fark ? $fark : strcmp( $a['grup'], $b['grup'] );
			}
		);

		return array(
			'satirlar' => $satirlar,
			'gruplar'  => $gruplar,
			'ozet'     => array(
				'kayitli'  => count( $masalar ),
				'sessiz'   => $hic,
				'kayitsiz' => $kayitsiz,
			),
		);
	}
}

if ( ! function_exists( 'qrms_analitik_masa_verisi' ) ) {

	/**
	 * Sayfanın verisi — ekran ve CSV aynı yerden beslenir.
	 *
	 * @param array  $aralik QRMS_Analitik_Filtre::aralik() çıktısı.
	 * @param string $masa   Masa filtresi ('' = tüm masalar).
	 * @return array<string,mixed>
	 */
	function qrms_analitik_masa_verisi( array $aralik, $masa = '' ) {
		// Sayaçlar HER ZAMAN filtresiz çekilir: tek masaya odaklanıldığında
		// bile o masanın sıralamadaki yerini ve grubuyla karşılaştırmasını
		// göstermek için diğerlerinin sayıları gerekir. Sorgu yine tektir.
		$sayaclar = QRMS_Analitik::masa_sayaclari( $aralik['bas'], $aralik['bit'] );
		$karne    = qrms_analitik_masa_karnesi( qrms_analitik_kayitli_masalar(), $sayaclar );

		$karne['odak'] = '';

		if ( '' !== $masa ) {
			$karne['odak'] = $masa;

			foreach ( $karne['satirlar'] as $sira => $satir ) {
				if ( $satir['masa'] !== $masa ) {
					continue;
				}

				$karne['odakSatir'] = array_merge(
					$satir,
					array(
						'sira'   => $sira + 1,
						'toplam' => count( $karne['satirlar'] ),
					)
				);

				// Grubun diğer masaları: "bu masa mı sessiz, yoksa bütün
				// bahçe mi?" sorusunun cevabı.
				$karne['odakGrup'] = array();

				foreach ( $karne['satirlar'] as $komsu ) {
					if ( '' !== $satir['grup'] && $komsu['grup'] === $satir['grup'] ) {
						$karne['odakGrup'][] = $komsu;
					}
				}

				break;
			}
		}

		/*
		 * Garson çağırma / hesap isteme sayaçları: waiter_call ve
		 * bill_request olayları qmo_cagri_gonder içinde yazılıyor. Masa
		 * başına iki SUM(...) eklemek yeni sorgu gerektirmez; bölüm
		 * rapor tarafında ayrı bir iş olarak bağlanır.
		 */

		return $karne;
	}
}

if ( ! function_exists( 'qrms_analitik_sayfa_masalar' ) ) {

	/**
	 * Masalar ekranı.
	 *
	 * @return void
	 */
	function qrms_analitik_sayfa_masalar() {
		if ( ! current_user_can( QRMS_Admin::CAPABILITY ) ) {
			wp_die( esc_html__( 'Bu sayfayı görüntüleme yetkiniz yok.', 'qrms' ) );
		}

		$masa    = QRMS_Analitik_Filtre::masa();
		$csv_url = add_query_arg(
			array(
				'action'   => 'qrms_analitik_csv',
				'kategori' => 'masalar',
				'donem'    => QRMS_Analitik_Filtre::donem(),
				'bas'      => QRMS_Analitik_Filtre::bas(),
				'bit'      => QRMS_Analitik_Filtre::bit(),
				'masa'     => $masa,
				'security' => wp_create_nonce( QRMS_Analitik::NONCE_CSV ),
			),
			admin_url( 'admin-ajax.php' )
		);
		?>
		<div class="wrap qrms-an qrms-an-masalar">

			<div class="qrms-an-header">
				<div class="qrms-an-header-text">
					<h1 class="qrms-an-title"><?php esc_html_e( 'Masalar', 'qrms' ); ?></h1>
					<p class="qrms-an-subtitle">
						<?php esc_html_e( 'Hangi masadan kaç hareket geldi ve hangi masaların QR kodu hiç okutulmadı.', 'qrms' ); ?>
					</p>
				</div>

				<div class="qrms-an-header-actions">
					<a class="qrms-an-btn" href="<?php echo esc_url( $csv_url ); ?>">
						<span class="dashicons dashicons-download" aria-hidden="true"></span>
						<?php esc_html_e( 'Bu sayfayı CSV indir', 'qrms' ); ?>
					</a>
				</div>
			</div>

			<?php qrms_analitik_filtre_cubugu( 'qrms-an-masalar' ); ?>

			<?php
			// Tek masaya odaklanıldığında karne en üstte durur; masa seçili
			// değilken bu bölüm hiç basılmaz.
			?>
			<div id="qrms-an-masa-odak"></div>

			<div class="qrms-an-panel">
				<div class="qrms-an-panel-header">
					<h2 class="qrms-an-panel-title">
						<span class="dashicons dashicons-editor-table" aria-hidden="true"></span>
						<?php esc_html_e( 'Masalara Göre', 'qrms' ); ?>
					</h2>
				</div>

				<div id="qrms-an-masa-liste">
					<div class="qrms-an-loading"><?php esc_html_e( 'Yükleniyor', 'qrms' ); ?></div>
				</div>
			</div>

			<div class="qrms-an-panel">
				<div class="qrms-an-panel-header">
					<h2 class="qrms-an-panel-title">
						<span class="dashicons dashicons-screenoptions" aria-hidden="true"></span>
						<?php esc_html_e( 'Masa Grupları', 'qrms' ); ?>
					</h2>
				</div>

				<p class="qrms-an-panel-note">
					<?php esc_html_e( 'Gruplar masa adlarından türetilir: sondaki numara atılır ("Bahçe 3" ve "Bahçe 4" aynı gruptur).', 'qrms' ); ?>
				</p>

				<div id="qrms-an-masa-gruplar"></div>
			</div>
		</div>
		<?php
	}
}
