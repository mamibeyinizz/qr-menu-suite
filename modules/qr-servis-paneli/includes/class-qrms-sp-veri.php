<?php
/**
 * Servis Paneli veri katmanı.
 *
 * Müşteri siparişleri ve garson/hesap çağrıları Firestore'daki `calls`
 * koleksiyonuna yazılır (bkz. qr-chatbot modülü). Bu sınıf o kayıtları okur,
 * panelin beklediği biçime çevirir ve durum değişikliklerini geri yazar.
 *
 * Firestore anahtarı TARAYICIYA ÇIKMAZ: panel kendi sunucusundaki AJAX ucunu
 * çağırır, Firestore'a yalnızca PHP tarafı service account ile gider.
 *
 * @package QR_Menu_Suite
 */

defined( 'ABSPATH' ) || exit;

/**
 * Panel verisi.
 */
class QRMS_SP_Veri {

	/** Ayarlar option'ı. */
	const OPTION = 'qrms_sp_ayarlar';

	/** Sunucu tarafı kısa önbellek (saniye). */
	const ONBELLEK = 3;

	/** Önbellek anahtarı. */
	const ONBELLEK_ANAHTAR = 'qrms_sp_liste';

	/**
	 * Durum akışı: hangi durumdan hangilerine geçilebilir?
	 *
	 * Geçerli olmayan sıçramalar (ör. "bekliyor" → "tamamlandi") reddedilir;
	 * aksi hâlde eş zamanlı iki panelden gelen tıklamalar siparişi
	 * hazırlanmadan tamamlanmış gösterebilirdi.
	 *
	 * @return array<string,string[]>
	 */
	public static function akis() {
		return array(
			'bekliyor'    => array( 'hazirlaniyor', 'iptal' ),
			'hazirlaniyor' => array( 'serviste', 'bekliyor', 'iptal' ),
			'serviste'    => array( 'tamamlandi', 'hazirlaniyor', 'iptal' ),
			'tamamlandi'  => array(),
			'iptal'       => array(),
		);
	}

	/**
	 * Durumların görünen adları ve sütun sırası.
	 *
	 * @return array<string,string>
	 */
	public static function durumlar() {
		return array(
			'bekliyor'     => __( 'Bekliyor', 'qrms' ),
			'hazirlaniyor' => __( 'Hazırlanıyor', 'qrms' ),
			'serviste'     => __( 'Serviste', 'qrms' ),
			'tamamlandi'   => __( 'Tamamlandı', 'qrms' ),
		);
	}

	/**
	 * Kayıt tipleri.
	 *
	 * @return array<string,string>
	 */
	public static function tipler() {
		return array(
			'siparis' => __( 'Sipariş', 'qrms' ),
			'garson'  => __( 'Garson', 'qrms' ),
			'hesap'   => __( 'Hesap', 'qrms' ),
		);
	}

	/**
	 * Geçiş geçerli mi?
	 *
	 * @param string $eski Mevcut durum.
	 * @param string $yeni Hedef durum.
	 * @return bool
	 */
	public static function gecis_gecerli( $eski, $yeni ) {
		$akis = self::akis();

		if ( ! isset( $akis[ $eski ] ) ) {
			return false;
		}

		return in_array( $yeni, $akis[ $eski ], true );
	}

	/* -----------------------------------------------------------------
	   AYARLAR
	----------------------------------------------------------------- */

	/**
	 * Varsayılan ayarlar.
	 *
	 * @return array
	 */
	public static function ayar_varsayilan() {
		return array(
			'ses'          => 1,
			'esik_sari'    => 180,
			'esik_kirmizi' => 420,
			'yenileme'     => 5,
			'tamam_penceresi' => 2,
			'tipler'       => array( 'siparis', 'garson', 'hesap' ),
		);
	}

	/**
	 * Kayıtlı ayarlar.
	 *
	 * @return array
	 */
	public static function ayarlar() {
		$kayitli = get_option( self::OPTION, array() );

		if ( ! is_array( $kayitli ) ) {
			$kayitli = array();
		}

		return array_merge( self::ayar_varsayilan(), $kayitli );
	}

	/**
	 * Ayarları temizler.
	 *
	 * @param array $raw Ham veri.
	 * @return array
	 */
	public static function ayar_temizle( $raw ) {
		$v   = self::ayar_varsayilan();
		$raw = is_array( $raw ) ? $raw : array();

		$v['ses'] = empty( $raw['ses'] ) ? 0 : 1;

		$v['esik_sari']    = max( 30, min( 3600, absint( isset( $raw['esik_sari'] ) ? $raw['esik_sari'] : 180 ) ) );
		$v['esik_kirmizi'] = max( 60, min( 7200, absint( isset( $raw['esik_kirmizi'] ) ? $raw['esik_kirmizi'] : 420 ) ) );

		// Kırmızı eşik sarıdan küçük olamaz; olursa kart doğrudan kırmızıya
		// atlar ve sarı uyarı hiç görünmez.
		if ( $v['esik_kirmizi'] <= $v['esik_sari'] ) {
			$v['esik_kirmizi'] = $v['esik_sari'] + 60;
		}

		$yenileme       = absint( isset( $raw['yenileme'] ) ? $raw['yenileme'] : 5 );
		$v['yenileme']  = max( 3, min( 60, $yenileme ) );

		$v['tamam_penceresi'] = max( 1, min( 24, absint( isset( $raw['tamam_penceresi'] ) ? $raw['tamam_penceresi'] : 2 ) ) );

		$tipler = array();

		foreach ( (array) ( isset( $raw['tipler'] ) ? $raw['tipler'] : array() ) as $tip ) {
			$tip = sanitize_key( $tip );

			if ( isset( self::tipler()[ $tip ] ) ) {
				$tipler[] = $tip;
			}
		}

		// Hiç tip seçilmemişse panel boş kalırdı; hepsine dönülür.
		$v['tipler'] = empty( $tipler ) ? array_keys( self::tipler() ) : $tipler;

		return $v;
	}

	/* -----------------------------------------------------------------
	   OKUMA
	----------------------------------------------------------------- */

	/**
	 * Firestore yapılandırılmış mı?
	 *
	 * @return bool
	 */
	public static function hazir_mi() {
		return class_exists( 'QMO_Firestore' ) && QMO_Firestore::hazir_mi();
	}

	/**
	 * Panelin göstereceği kayıtlar.
	 *
	 * @return array|WP_Error
	 */
	public static function kayitlar() {
		if ( ! self::hazir_mi() ) {
			return new WP_Error( 'firebase', __( 'Firebase yapılandırılmamış.', 'qrms' ) );
		}

		$onbellek = get_transient( self::ONBELLEK_ANAHTAR );

		if ( is_array( $onbellek ) ) {
			return $onbellek;
		}

		$ayar = self::ayarlar();

		// Aralık: tamamlanmışların görüneceği pencere kadar geriye bakılır.
		$saat  = max( 2, (int) $ayar['tamam_penceresi'] );
		$since = gmdate( 'Y-m-d\TH:i:s\Z', time() - ( $saat * HOUR_IN_SECONDS ) );

		$ham = QMO_Firestore::call_listele(
			array(
				'since' => $since,
				'limit' => 200,
			)
		);

		if ( is_wp_error( $ham ) ) {
			return $ham;
		}

		$masalar = self::masa_adlari();
		$kayit   = array();

		foreach ( $ham as $doc ) {
			$normal = self::normalize( $doc, $masalar );

			if ( null === $normal ) {
				continue;
			}

			if ( ! in_array( $normal['tip'], $ayar['tipler'], true ) ) {
				continue;
			}

			$kayit[] = $normal;
		}

		// Aynı anda birden çok panel açıkken Firestore kotası boşuna
		// tükenmesin: üç saniyelik kısa önbellek beş ekranı tek isteğe indirir.
		set_transient( self::ONBELLEK_ANAHTAR, $kayit, self::ONBELLEK );

		return $kayit;
	}

	/**
	 * Firestore belgesini panel kaydına çevirir.
	 *
	 * SAF fonksiyondur; testler doğrudan çağırır.
	 *
	 * @param array $doc     Çözülmüş Firestore belgesi.
	 * @param array $masalar Masa slug => görünen ad.
	 * @return array|null Geçersiz belgede null.
	 */
	public static function normalize( array $doc, array $masalar = array() ) {
		if ( empty( $doc['id'] ) ) {
			return null;
		}

		$tip = isset( $doc['tip'] ) ? sanitize_key( (string) $doc['tip'] ) : '';

		if ( ! isset( self::tipler()[ $tip ] ) ) {
			return null;
		}

		$durum = isset( $doc['durum'] ) ? sanitize_key( (string) $doc['durum'] ) : 'bekliyor';

		if ( ! isset( self::akis()[ $durum ] ) ) {
			$durum = 'bekliyor';
		}

		$masa_slug = isset( $doc['masaNo'] ) ? (string) $doc['masaNo'] : '';

		$kalemler = array();

		if ( isset( $doc['items'] ) && is_array( $doc['items'] ) ) {
			foreach ( $doc['items'] as $kalem ) {
				if ( ! is_array( $kalem ) ) {
					continue;
				}

				$kalemler[] = array(
					'ad'    => isset( $kalem['urunAdi'] ) ? (string) $kalem['urunAdi'] : '',
					'adet'  => isset( $kalem['adet'] ) ? max( 1, (int) $kalem['adet'] ) : 1,
					// Müşteri notu kendi dilinde yazılır; qr-chatbot modülü
					// Türkçe çevirisini notTr alanına yazar. İkisi de gösterilir.
					'not'   => isset( $kalem['notOrijinal'] ) ? (string) $kalem['notOrijinal'] : '',
					'notTr' => isset( $kalem['notTr'] ) ? (string) $kalem['notTr'] : '',
				);
			}
		}

		$olusma = isset( $doc['createdAt'] ) ? (string) $doc['createdAt'] : '';
		$zaman  = '' !== $olusma ? strtotime( $olusma ) : 0;

		return array(
			'id'         => (string) $doc['id'],
			'tip'        => $tip,
			'durum'      => $durum,
			'masa'       => $masa_slug,
			'masaAd'     => isset( $masalar[ $masa_slug ] ) ? $masalar[ $masa_slug ] : $masa_slug,
			'kalemler'   => $kalemler,
			'notDili'    => isset( $doc['notDili'] ) ? (string) $doc['notDili'] : '',
			'personel'   => isset( $doc['onaylayanAd'] ) ? (string) $doc['onaylayanAd'] : '',
			'olusma'     => $olusma,
			'olusmaTs'   => $zaman ? $zaman : 0,
		);
	}

	/**
	 * Masa slug'ı => görünen ad.
	 *
	 * Masaların TEK KAYNAĞI qr-masa modülüdür; modül pasifse slug olduğu gibi
	 * gösterilir (panel yine çalışır, yalnızca ad yerine slug görünür).
	 *
	 * @return array<string,string>
	 */
	public static function masa_adlari() {
		if ( ! class_exists( 'QMO_Masalar' ) || ! QMO_Masalar::tablo_var_mi() ) {
			return array();
		}

		$adlar = array();

		foreach ( (array) QMO_Masalar::hepsi() as $masa ) {
			if ( isset( $masa->table_slug ) ) {
				$adlar[ (string) $masa->table_slug ] = (string) $masa->table_name;
			}
		}

		return $adlar;
	}

	/* -----------------------------------------------------------------
	   YAZMA
	----------------------------------------------------------------- */

	/**
	 * Bir kaydın durumunu değiştirir.
	 *
	 * @param string $id    Belge kimliği.
	 * @param string $eski  İstemcinin gördüğü durum.
	 * @param string $yeni  Hedef durum.
	 * @return true|WP_Error
	 */
	public static function durum_degistir( $id, $eski, $yeni ) {
		$eski = sanitize_key( $eski );
		$yeni = sanitize_key( $yeni );

		if ( ! self::gecis_gecerli( $eski, $yeni ) ) {
			return new WP_Error( 'gecis', __( 'Bu durum değişikliği yapılamaz.', 'qrms' ) );
		}

		if ( ! self::hazir_mi() ) {
			return new WP_Error( 'firebase', __( 'Firebase yapılandırılmamış.', 'qrms' ) );
		}

		$kullanici = wp_get_current_user();

		$sonuc = QMO_Firestore::call_guncelle(
			$id,
			array(
				'durum'        => array( 'stringValue' => $yeni ),
				'onaylayanUid' => array( 'stringValue' => (string) get_current_user_id() ),
				'onaylayanAd'  => array( 'stringValue' => $kullanici ? (string) $kullanici->display_name : '' ),
				'guncellendi'  => array( 'timestampValue' => gmdate( 'Y-m-d\TH:i:s\Z' ) ),
			)
		);

		if ( is_wp_error( $sonuc ) ) {
			return $sonuc;
		}

		// Panel bir sonraki yoklamada yeni durumu görsün.
		delete_transient( self::ONBELLEK_ANAHTAR );

		return true;
	}
}
