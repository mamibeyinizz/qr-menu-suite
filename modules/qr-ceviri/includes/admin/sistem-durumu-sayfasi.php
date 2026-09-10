<?php
/**
 * Alt sayfa: Sistem Durumu (qrms-cv-durum).
 *
 * Mevcut tablo + P1 grupları (Yönetici ayarları, Form alanları),
 * eskimiş / yetim yönetimi. Hücreler "çeviri yok" ile "kaynak yok"u ayırır.
 *
 * @package QR_Menu_Suite
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'rma_ceviri_dil_doluluk_verileri' ) ) {

	/**
	 * Aktif diller için çeviri doluluk yüzdeleri.
	 *
	 * Tek GROUP BY sorgusu + bir kez sayılan kaynak toplamı (N+1 yok).
	 *
	 * @return array<string,array{adet:int,toplam:int,yuzde:int}>
	 */
	function rma_ceviri_dil_doluluk_verileri() {
		static $onbellek = null;

		if ( null !== $onbellek ) {
			return $onbellek;
		}

		$hedefler = rma_ceviri_hedef_diller();
		$toplam   = 0;

		if ( function_exists( 'rma_ceviri_kaynak_satirlari' ) ) {
			foreach ( rma_ceviri_kaynak_satirlari() as $satir ) {
				++$toplam;
			}
		}

		$dil_adetleri = RMA_Ceviri_Tablo::dil_sayilari();
		$sonuc        = array();

		foreach ( $hedefler as $dil ) {
			$adet  = isset( $dil_adetleri[ $dil ] ) ? (int) $dil_adetleri[ $dil ] : 0;
			$yuzde = ( $toplam > 0 ) ? (int) round( 100 * $adet / $toplam ) : 0;

			$sonuc[ $dil ] = array(
				'adet'  => $adet,
				'toplam' => $toplam,
				'yuzde' => $yuzde,
			);
		}

		$onbellek = $sonuc;
		return $sonuc;
	}
}

if ( ! function_exists( 'rma_ceviri_dil_doluluk_tablosu' ) ) {

	/**
	 * Dil bazlı çeviri doluluk tablosu.
	 *
	 * @return void
	 */
	function rma_ceviri_dil_doluluk_tablosu() {
		$veriler = rma_ceviri_dil_doluluk_verileri();
		$katalog = qrmenu_get_langs();

		if ( empty( $veriler ) ) {
			return;
		}

		$ilk = reset( $veriler );
		?>
		<h2 class="title qrc-heading" style="margin-top:32px;">
			<span class="dashicons dashicons-translation" aria-hidden="true"></span>
			Dil Doluluk Oranları
		</h2>
		<p class="description">
			Her aktif dil için: o dilde çevirisi olan alan sayısı / toplam çevrilebilir alan sayısı
			(<?php echo (int) $ilk['toplam']; ?> alan).
		</p>
		<table class="widefat striped qrc-stats" style="max-width:640px;">
			<thead>
				<tr>
					<th scope="col">Dil</th>
					<th scope="col" class="qrc-stats-num">Çeviri</th>
					<th scope="col" class="qrc-stats-num">Doluluk</th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $veriler as $dil => $v ) : ?>
					<?php
					$etiket = isset( $katalog[ $dil ] )
						? $katalog[ $dil ]['flag'] . ' ' . $katalog[ $dil ]['name'] . ' (' . $dil . ')'
						: $dil;
					$sinif  = ( 0 === $v['adet'] ) ? 'is-empty is-no-trans' : '';
					if ( $v['yuzde'] > 0 && $v['yuzde'] < 50 ) {
						$sinif = 'is-empty is-no-trans';
					}
					?>
					<tr>
						<th scope="row"><?php echo esc_html( $etiket ); ?></th>
						<td class="qrc-stats-num"><?php echo (int) $v['adet']; ?> / <?php echo (int) $v['toplam']; ?></td>
						<td class="qrc-stats-num<?php echo '' !== $sinif ? ' ' . esc_attr( $sinif ) : ''; ?>">
							<?php echo (int) $v['yuzde']; ?>%
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}
}

if ( ! function_exists( 'qrms_module_qr_ceviri_sayfa_durum' ) ) {

	/**
	 * Sistem Durumu ekranı.
	 *
	 * @return void
	 */
	function qrms_module_qr_ceviri_sayfa_durum() {
		rma_ceviri_import_bildirimleri();

		qrms_module_qr_ceviri_sayfa_ac( 'qrms-cv-durum' );
		qrms_module_qr_ceviri_baslik( 'dashicons-chart-bar', 'Sistem Durumu', 'h1' );
		rma_ceviri_durum_paneli();
		rma_ceviri_dil_doluluk_tablosu();
		qrms_module_qr_ceviri_sayfa_kapat();
	}
}
