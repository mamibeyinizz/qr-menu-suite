<?php
/**
 * Menü mühendisliği hesabı (Kasavana–Smith matrisi).
 *
 * SAF sınıftır: veritabanına, option'a ve isteğe dokunmaz — kendisine verilen
 * satırları çevirir. Sorgu ve önbellek işi class-qrms-mm-rapor.php'dedir.
 * Böylece matrisin kendisi doğrudan test edilebilir.
 *
 * YÖNTEM
 * ------
 * Her ürün iki eksende ölçülür:
 *
 *   Popülerlik : ürünün satış adedinin grup içindeki payı (menü payı).
 *                Eşik = (1 / ürün sayısı) × katsayı. Klasik katsayı 0.70'tir:
 *                "eşit dağılımın %70'i" kadar satan ürün popüler sayılır.
 *   Kârlılık   : birim katkı payı (fiyat − maliyet). Eşik, grubun ADETLE
 *                AĞIRLIKLI ortalama katkı payıdır — düz ortalama alınsaydı
 *                hiç satmayan pahalı bir ürün eşiği yukarı çekerdi.
 *
 * Dört kutu: Yıldız (çok satan + kârlı), İş Atı (çok satan + az kârlı),
 * Bulmaca (az satan + kârlı), Köpek (ikisi de düşük).
 *
 * @package QR_Menu_Suite
 */

defined( 'ABSPATH' ) || exit;

/**
 * Matris hesabı.
 */
class QRMS_MM_Hesap {

	/**
	 * Satış verisinin "yeterli" sayıldığı en az toplam adet.
	 *
	 * Altında kalındığında popülerlik gerçek siparişlerden değil,
	 * görüntülenme + sepete ekleme vekil skorundan tahmin edilir ve rapor
	 * bunu ekranda açıkça yazar.
	 */
	const MIN_SATIS = 20;

	/**
	 * Sepete eklemenin vekil skordaki ağırlığı.
	 *
	 * Sepete ekleme, ürün detayına bakmaya göre satın alma niyetine çok daha
	 * yakındır; eşit ağırlık verilseydi yalnızca merak edilen ürünler
	 * popüler görünürdü.
	 */
	const VEKIL_SEPET_AGIRLIK = 3;

	/**
	 * Kutu anahtarları ve görünen adları.
	 *
	 * @return array<string,string>
	 */
	public static function kutular() {
		return array(
			'yildiz'  => __( 'Yıldız', 'qrms' ),
			'is_ati'  => __( 'İş Atı', 'qrms' ),
			'bulmaca' => __( 'Bulmaca', 'qrms' ),
			'kopek'   => __( 'Köpek', 'qrms' ),
		);
	}

	/**
	 * Bir kutunun rengi (rapor ekranı ve CSV rozetleri).
	 *
	 * @param string $kutu Kutu anahtarı.
	 * @return string
	 */
	public static function kutu_rengi( $kutu ) {
		$renkler = array(
			'yildiz'  => '#1f9d55',
			'is_ati'  => '#2b7cd3',
			'bulmaca' => '#e08a1e',
			'kopek'   => '#c0392b',
		);

		return isset( $renkler[ $kutu ] ) ? $renkler[ $kutu ] : '#646970';
	}

	/**
	 * Bir kutunun dashicon'u.
	 *
	 * @param string $kutu Kutu anahtarı.
	 * @return string
	 */
	public static function kutu_ikonu( $kutu ) {
		$ikonlar = array(
			'yildiz'  => 'dashicons-star-filled',
			'is_ati'  => 'dashicons-performance',
			'bulmaca' => 'dashicons-lightbulb',
			'kopek'   => 'dashicons-warning',
		);

		return isset( $ikonlar[ $kutu ] ) ? $ikonlar[ $kutu ] : 'dashicons-marker';
	}

	/**
	 * Kutunun tek cümlelik anlamı.
	 *
	 * @param string $kutu Kutu anahtarı.
	 * @return string
	 */
	public static function kutu_anlami( $kutu ) {
		$metinler = array(
			'yildiz'  => __( 'Çok satıyor ve çok kazandırıyor.', 'qrms' ),
			'is_ati'  => __( 'Çok satıyor ama az kazandırıyor.', 'qrms' ),
			'bulmaca' => __( 'Kârlı ama yeterince satmıyor.', 'qrms' ),
			'kopek'   => __( 'Ne satıyor ne kazandırıyor.', 'qrms' ),
		);

		return isset( $metinler[ $kutu ] ) ? $metinler[ $kutu ] : '';
	}

	/**
	 * Ürün için somut aksiyon cümlesi.
	 *
	 * Raporun asıl değeri budur: işletmeciye "şu ürün köpek" demek değil,
	 * ne yapacağını söylemek.
	 *
	 * @param string $kutu Kutu anahtarı.
	 * @return string
	 */
	public static function aksiyon( $kutu ) {
		$metinler = array(
			'yildiz'  => __( 'Koru. Fiyatını ve porsiyonunu değiştirme; menünün en üstünde ve vitrinde tut.', 'qrms' ),
			'is_ati'  => __( 'Maliyeti düşür ya da %5–10 zam dene. Talep güçlü, küçük bir zam satışı büyük ölçüde etkilemez.', 'qrms' ),
			'bulmaca' => __( 'Görünürlüğünü artır: vitrine al, chatbot önerilerine ekle, fotoğrafını ve adını güçlendir.', 'qrms' ),
			'kopek'   => __( 'Menüden çıkarmayı değerlendir. Kalacaksa fiyatı yükselt veya reçeteyi ucuzlat.', 'qrms' ),
		);

		return isset( $metinler[ $kutu ] ) ? $metinler[ $kutu ] : '';
	}

	/**
	 * Matrisi hesaplar.
	 *
	 * @param array $satirlar Ürün satırları. Her satır:
	 *                        item_id, item_name, category_name, satis, sepet,
	 *                        tik, fiyat, maliyet.
	 *                        `fiyat` ya da `maliyet` null ise ürün matrise
	 *                        girmez, "eksik" listesine düşer.
	 * @param array $ayar     populerlik_esigi (0.5–1.0).
	 * @return array{kaynak:string,urunler:array,eksik:array,ozet:array}
	 */
	public static function hesapla( array $satirlar, array $ayar = array() ) {
		$katsayi = isset( $ayar['populerlik_esigi'] ) ? (float) $ayar['populerlik_esigi'] : 0.70;
		$katsayi = max( 0.5, min( 1.0, $katsayi ) );

		// 1) Veri kaynağı: gerçek sipariş mi, vekil skor mu?
		$toplam_satis = 0;

		foreach ( $satirlar as $satir ) {
			$toplam_satis += isset( $satir['satis'] ) ? (int) $satir['satis'] : 0;
		}

		$kaynak = $toplam_satis >= self::MIN_SATIS ? 'siparis' : 'vekil';

		// 2) Eksik veriyi ayır, kalanı normalize et.
		$gecerli = array();
		$eksik   = array();

		foreach ( $satirlar as $satir ) {
			$fiyat   = isset( $satir['fiyat'] ) ? $satir['fiyat'] : null;
			$maliyet = isset( $satir['maliyet'] ) ? $satir['maliyet'] : null;

			$sebep = '';

			if ( null === $fiyat || '' === $fiyat || (float) $fiyat <= 0 ) {
				$sebep = __( 'Fiyat girilmemiş', 'qrms' );
			} elseif ( null === $maliyet || '' === $maliyet ) {
				$sebep = __( 'Maliyet girilmemiş', 'qrms' );
			}

			if ( '' !== $sebep ) {
				$eksik[] = array(
					'item_id'       => (int) $satir['item_id'],
					'item_name'     => (string) $satir['item_name'],
					'category_name' => isset( $satir['category_name'] ) ? (string) $satir['category_name'] : '',
					'sebep'         => $sebep,
				);
				continue;
			}

			$adet = 'siparis' === $kaynak
				? (int) $satir['satis']
				: (int) $satir['tik'] + ( (int) $satir['sepet'] * self::VEKIL_SEPET_AGIRLIK );

			$gecerli[] = array(
				'item_id'       => (int) $satir['item_id'],
				'item_name'     => (string) $satir['item_name'],
				'category_name' => isset( $satir['category_name'] ) ? (string) $satir['category_name'] : '',
				'fiyat'         => (float) $fiyat,
				'maliyet'       => (float) $maliyet,
				'katki'         => (float) $fiyat - (float) $maliyet,
				'adet'          => max( 0, $adet ),
				'satis'         => (int) $satir['satis'],
			);
		}

		if ( empty( $gecerli ) ) {
			return array(
				'kaynak'  => $kaynak,
				'urunler' => array(),
				'eksik'   => $eksik,
				'ozet'    => self::bos_ozet( $katsayi ),
			);
		}

		// 3) Eşikler.
		$adet_toplam  = 0;
		$katki_toplam = 0.0;
		$ciro_toplam  = 0.0;

		foreach ( $gecerli as $u ) {
			$adet_toplam  += $u['adet'];
			$katki_toplam += $u['katki'] * $u['adet'];
			$ciro_toplam  += $u['fiyat'] * $u['adet'];
		}

		$n = count( $gecerli );

		$esik_pay = ( 1 / $n ) * $katsayi;

		// Ağırlıklı ortalama katkı payı. Hiç satış yoksa (adet_toplam = 0)
		// ağırlık verilemez; düz ortalamaya düşülür ki eşik yine de anlamlı
		// bir yerde dursun.
		if ( $adet_toplam > 0 ) {
			$esik_katki = $katki_toplam / $adet_toplam;
		} else {
			$esik_katki = array_sum( wp_list_pluck( $gecerli, 'katki' ) ) / $n;
		}

		// 4) Kutulama.
		$urunler = array();
		$sayac   = array( 'yildiz' => 0, 'is_ati' => 0, 'bulmaca' => 0, 'kopek' => 0 );
		$kayip   = 0.0;

		foreach ( $gecerli as $u ) {
			$pay = $adet_toplam > 0 ? $u['adet'] / $adet_toplam : 0.0;

			// Sınırda eşitlik "yüksek" tarafa yazılır: eşiği tam tutturan bir
			// ürünü cezalandırmak için sebep yok.
			$populer = $pay >= $esik_pay;
			$karli   = $u['katki'] >= $esik_katki;

			if ( $populer && $karli ) {
				$kutu = 'yildiz';
			} elseif ( $populer ) {
				$kutu = 'is_ati';
			} elseif ( $karli ) {
				$kutu = 'bulmaca';
			} else {
				$kutu = 'kopek';
			}

			++$sayac[ $kutu ];

			// Kayıp fırsat: ortalamanın altında katkı üreten her satış,
			// aradaki fark kadar kaybettirir.
			$urun_kayip = 0.0;

			if ( $u['katki'] < $esik_katki ) {
				$urun_kayip = ( $esik_katki - $u['katki'] ) * $u['adet'];
				$kayip     += $urun_kayip;
			}

			$urunler[] = array(
				'item_id'       => $u['item_id'],
				'item_name'     => $u['item_name'],
				'category_name' => $u['category_name'],
				'fiyat'         => $u['fiyat'],
				'maliyet'       => $u['maliyet'],
				'katki'         => $u['katki'],
				'marj'          => $u['fiyat'] > 0 ? ( $u['katki'] / $u['fiyat'] ) * 100 : 0.0,
				'adet'          => $u['adet'],
				'satis'         => $u['satis'],
				'menu_payi'     => $pay * 100,
				'ciro'          => $u['fiyat'] * $u['adet'],
				'toplam_katki'  => $u['katki'] * $u['adet'],
				'kutu'          => $kutu,
				'aksiyon'       => self::aksiyon( $kutu ),
				'kayip'         => $urun_kayip,
			);
		}

		// Toplam katkıya göre azalan: ekranı açan kişi önce parayı kimin
		// getirdiğini görsün.
		usort(
			$urunler,
			static function ( $a, $b ) {
				if ( $a['toplam_katki'] === $b['toplam_katki'] ) {
					return 0;
				}

				return $a['toplam_katki'] < $b['toplam_katki'] ? 1 : -1;
			}
		);

		return array(
			'kaynak'  => $kaynak,
			'urunler' => $urunler,
			'eksik'   => $eksik,
			'ozet'    => array(
				'urun_sayisi'      => $n,
				'toplam_adet'      => $adet_toplam,
				'toplam_ciro'      => $ciro_toplam,
				'toplam_katki'     => $katki_toplam,
				'ort_marj'         => $ciro_toplam > 0 ? ( $katki_toplam / $ciro_toplam ) * 100 : 0.0,
				'esik_pay'         => $esik_pay * 100,
				'esik_katki'       => $esik_katki,
				'kutular'          => $sayac,
				'kayip_firsat'     => $kayip,
				'populerlik_esigi' => $katsayi,
			),
		);
	}

	/**
	 * Hiç geçerli ürün yokken dönen özet.
	 *
	 * @param float $katsayi Popülerlik katsayısı.
	 * @return array
	 */
	private static function bos_ozet( $katsayi ) {
		return array(
			'urun_sayisi'      => 0,
			'toplam_adet'      => 0,
			'toplam_ciro'      => 0.0,
			'toplam_katki'     => 0.0,
			'ort_marj'         => 0.0,
			'esik_pay'         => 0.0,
			'esik_katki'       => 0.0,
			'kutular'          => array( 'yildiz' => 0, 'is_ati' => 0, 'bulmaca' => 0, 'kopek' => 0 ),
			'kayip_firsat'     => 0.0,
			'populerlik_esigi' => $katsayi,
		);
	}
}
