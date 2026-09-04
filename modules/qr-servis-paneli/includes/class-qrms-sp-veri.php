<?php
/**
 * Servis paneli veri katmanı — Firestore sarmalayıcı.
 *
 * @package QR_Menu_Suite
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'QRMS_SP_Veri' ) ) {

	/**
	 * Firestore çağrı okuma/yazma ve normalizasyon.
	 */
	class QRMS_SP_Veri {

		const OPT_AYARLAR = 'qrms_sp_ayarlar';
		const CACHE_KEY   = 'qrms_sp_liste_cache';

		/**
		 * Varsayılan ayarlar.
		 *
		 * @return array
		 */
		public static function varsayilan_ayarlar() {
			return array(
				'ses_acik'           => 1,
				'esik_sari'          => 3,
				'esik_kirmizi'       => 7,
				'otomatik_tamam'     => 120,
				'tipler'             => array( 'siparis', 'garson', 'hesap' ),
				'yenileme_araligi'   => 5,
			);
		}

		/**
		 * Ayarları okur.
		 *
		 * @return array
		 */
		public static function ayarlar() {
			$opt = get_option( self::OPT_AYARLAR, array() );
			if ( ! is_array( $opt ) ) {
				$opt = array();
			}
			return array_merge( self::varsayilan_ayarlar(), $opt );
		}

		/**
		 * Ayarları kaydeder.
		 *
		 * @param array $ayarlar Ayar dizisi.
		 * @return void
		 */
		public static function ayarlari_kaydet( array $ayarlar ) {
			$mevcut = self::ayarlar();
			$yeni   = array_merge( $mevcut, $ayarlar );
			$yeni['esik_sari']        = max( 1, absint( $yeni['esik_sari'] ?? 3 ) );
			$yeni['esik_kirmizi']     = max( $yeni['esik_sari'], absint( $yeni['esik_kirmizi'] ?? 7 ) );
			$yeni['otomatik_tamam']   = max( 30, absint( $yeni['otomatik_tamam'] ?? 120 ) );
			$yeni['yenileme_araligi'] = max( 3, min( 60, absint( $yeni['yenileme_araligi'] ?? 5 ) ) );
			$yeni['ses_acik']         = ! empty( $yeni['ses_acik'] ) ? 1 : 0;
			if ( isset( $ayarlar['tipler'] ) && is_array( $ayarlar['tipler'] ) ) {
				$yeni['tipler'] = array_values( array_intersect( $ayarlar['tipler'], array( 'siparis', 'garson', 'hesap' ) ) );
			}
			update_option( self::OPT_AYARLAR, $yeni, false );
		}

		/**
		 * Firestore yanıtını normalize eder (test edilebilir).
		 *
		 * @param array $doc Ham Firestore belgesi.
		 * @return array
		 */
		public static function normalize_doc( array $doc ) {
			$name   = isset( $doc['name'] ) ? (string) $doc['name'] : '';
			$parts  = explode( '/', $name );
			$id     = end( $parts );
			$fields = isset( $doc['fields'] ) && is_array( $doc['fields'] ) ? $doc['fields'] : array();

			$items = array();
			if ( ! empty( $fields['items']['arrayValue']['values'] ) && is_array( $fields['items']['arrayValue']['values'] ) ) {
				foreach ( $fields['items']['arrayValue']['values'] as $val ) {
					if ( ! empty( $val['mapValue']['fields'] ) ) {
						$item = array();
						foreach ( $val['mapValue']['fields'] as $ik => $iv ) {
							$item[ $ik ] = QMO_Firestore::deger_coz( $iv );
						}
						$items[] = $item;
					}
				}
			}

			return array(
				'id'          => $id ?: '',
				'masaNo'      => (string) QMO_Firestore::deger_coz( $fields['masaNo'] ?? array( 'stringValue' => '' ) ),
				'tip'         => (string) QMO_Firestore::deger_coz( $fields['tip'] ?? array( 'stringValue' => 'siparis' ) ),
				'durum'       => (string) QMO_Firestore::deger_coz( $fields['durum'] ?? array( 'stringValue' => 'bekliyor' ) ),
				'items'       => $items,
				'notDili'     => (string) QMO_Firestore::deger_coz( $fields['notDili'] ?? array( 'stringValue' => '' ) ),
				'onaylayanAd' => (string) QMO_Firestore::deger_coz( $fields['onaylayanAd'] ?? array( 'stringValue' => '' ) ),
				'createdAt'   => (string) QMO_Firestore::deger_coz( $fields['createdAt'] ?? array( 'stringValue' => '' ) ),
				'guncellendi' => (string) QMO_Firestore::deger_coz( $fields['guncellendi'] ?? array( 'stringValue' => '' ) ),
			);
		}

		/**
		 * Masa slug'ından görünen adı çözer.
		 *
		 * @param string $slug Masa slug'ı.
		 * @return string
		 */
		public static function masa_adi( $slug ) {
			if ( class_exists( 'QMO_Masalar' ) ) {
				$masa = QMO_Masalar::bul( $slug );
				if ( $masa && ! empty( $masa->table_name ) ) {
					return (string) $masa->table_name;
				}
			}
			return (string) $slug;
		}

		/**
		 * Kayıtları listeler (kısa önbellekli).
		 *
		 * @param string $since ISO timestamp.
		 * @param int    $limit Limit.
		 * @return array|WP_Error
		 */
		public static function liste( $since = '', $limit = 100 ) {
			if ( ! class_exists( 'QMO_Firestore' ) || ! QMO_Firestore::hazir_mi() ) {
				return new WP_Error( 'firebase', __( 'Firebase yapılandırılmamış.', 'qrms' ) );
			}

			if ( '' === $since ) {
				$since = gmdate( 'Y-m-d\TH:i:s\Z', strtotime( '-24 hours' ) );
			}

			$cache_key = self::CACHE_KEY . '_' . md5( $since . '_' . $limit );
			$cached    = get_transient( $cache_key );
			if ( is_array( $cached ) ) {
				return $cached;
			}

			$res = QMO_Firestore::call_listele(
				array(
					'since' => $since,
					'limit' => $limit,
				)
			);

			if ( is_wp_error( $res ) ) {
				return $res;
			}

			foreach ( $res as &$row ) {
				$row['masaAdi'] = self::masa_adi( $row['masaNo'] );
			}
			unset( $row );

			set_transient( $cache_key, $res, 3 );
			return $res;
		}

		/**
		 * Geçerli durum geçişleri.
		 *
		 * @return array<string,string[]>
		 */
		public static function gecisler() {
			return array(
				'bekliyor'    => array( 'hazirlaniyor', 'iptal' ),
				'hazirlaniyor' => array( 'serviste', 'bekliyor', 'iptal' ),
				'serviste'    => array( 'tamamlandi', 'hazirlaniyor', 'iptal' ),
				'tamamlandi'  => array(),
				'iptal'       => array(),
			);
		}

		/**
		 * Durum geçişi geçerli mi?
		 *
		 * @param string $mevcut Mevcut durum.
		 * @param string $yeni   Hedef durum.
		 * @return bool
		 */
		public static function gecis_gecerli_mi( $mevcut, $yeni ) {
			$mevcut = sanitize_key( (string) $mevcut );
			$yeni   = sanitize_key( (string) $yeni );
			$map    = self::gecisler();
			return isset( $map[ $mevcut ] ) && in_array( $yeni, $map[ $mevcut ], true );
		}

		/**
		 * Durumu günceller.
		 *
		 * @param string $doc_id Belge kimliği.
		 * @param string $durum  Yeni durum.
		 * @param string $mevcut Mevcut durum (doğrulama için).
		 * @return true|WP_Error
		 */
		public static function durum_guncelle( $doc_id, $durum, $mevcut = '' ) {
			$durum = sanitize_key( (string) $durum );
			if ( '' !== $mevcut && ! self::gecis_gecerli_mi( $mevcut, $durum ) ) {
				return new WP_Error( 'gecis', __( 'Geçersiz durum geçişi.', 'qrms' ) );
			}

			$user = wp_get_current_user();
			$fields = array(
				'durum'        => array( 'stringValue' => $durum ),
				'onaylayanUid' => array( 'stringValue' => (string) $user->ID ),
				'onaylayanAd'  => array( 'stringValue' => $user->display_name ? $user->display_name : $user->user_login ),
				'guncellendi'  => array( 'timestampValue' => gmdate( 'Y-m-d\TH:i:s\Z' ) ),
			);

			$res = QMO_Firestore::call_guncelle(
				$doc_id,
				$fields,
				array( 'durum', 'onaylayanUid', 'onaylayanAd', 'guncellendi' )
			);

			if ( is_wp_error( $res ) ) {
				return $res;
			}

			delete_transient( self::CACHE_KEY . '_' . md5( '' ) );
			return true;
		}
	}
}
