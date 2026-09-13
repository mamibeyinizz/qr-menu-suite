<?php
/**
 * Kirli çeviri kayıtlarını siler: en (it ile aynı), ar (ru ile aynı).
 * it ve ru kayıtlarına dokunulmaz.
 *
 * Kullanım:
 *   wp eval-file scripts/qr-ceviri-bakim/04-temizle-sirali-kopya.php          (dry-run)
 *   wp eval-file scripts/qr-ceviri-bakim/04-temizle-sirali-kopya.php -- --apply
 *
 * @package QRMenu_Ceviri_Bakim
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit( "WordPress yüklü değil.\n" );
}

require __DIR__ . '/_ortak.php';

if ( ! class_exists( 'RMA_Ceviri_Tablo' ) || ! RMA_Ceviri_Tablo::tablo_var_mi() ) {
	exit( "rma_translations tablosu bulunamadı.\n" );
}

$uygula = rma_bakim_uygula_mi();
global $wpdb;
$tablo = RMA_Ceviri_Tablo::tablo();

/**
 * Silinecek dil → referans dil eşlemeleri.
 */
$ciftler = array(
	'en' => 'it',
	'ar' => 'ru',
);

rma_bakim_log( $uygula ? '=== UYGULA modu ===' : '=== DRY-RUN modu (--apply ile silinir) ===' );

$toplam_silinen = 0;

foreach ( $ciftler as $hedef_dil => $kaynak_dil ) {
	$kayitlar = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT
				t1.item_id,
				t1.item_type,
				t1.field,
				t1.original_text,
				t1.translated_text
			FROM {$tablo} t1
			INNER JOIN {$tablo} t2
				ON t1.item_id = t2.item_id
				AND t1.item_type = t2.item_type
				AND t1.field = t2.field
				AND t2.lang_code = %s
			WHERE t1.lang_code = %s
				AND t1.translated_text = t2.translated_text
				AND t1.translated_text <> ''
			ORDER BY t1.item_type, t1.item_id, t1.field",
			$kaynak_dil,
			$hedef_dil
		),
		ARRAY_A
	);

	rma_bakim_log( '' );
	rma_bakim_log( "--- {$hedef_dil} ({$kaynak_dil} ile aynı): " . count( $kayitlar ) . ' kayıt ---' );

	foreach ( $kayitlar as $k ) {
		rma_bakim_log(
			sprintf(
				'%s item_id=%s field=%s orijinal="%s" %s="%s"',
				$k['item_type'],
				$k['item_id'],
				$k['field'],
				mb_substr( $k['original_text'], 0, 50 ),
				$hedef_dil,
				mb_substr( $k['translated_text'], 0, 50 )
			)
		);

		if ( $uygula ) {
			RMA_Ceviri_Tablo::sil(
				(int) $k['item_id'],
				$k['item_type'],
				$k['field'],
				$hedef_dil
			);
		}
	}

	$toplam_silinen += count( $kayitlar );
}

if ( $uygula && $toplam_silinen > 0 && function_exists( 'rma_ceviri_onbellek_temizle' ) ) {
	rma_ceviri_onbellek_temizle();
}

rma_bakim_basarili(
	sprintf(
		'%d kayıt %s.',
		$toplam_silinen,
		$uygula ? 'silindi' : 'dry-run ile listelendi'
	)
);
