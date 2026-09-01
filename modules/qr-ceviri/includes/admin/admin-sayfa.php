<?php
/**
 * "QR Çeviri" yönetim sayfası.
 *
 * v1.7'deki renk ayarları ve aktif dil kutuları AYNEN korunuyor (aynı sayfa
 * slug'ı, aynı nonce, aynı seçenek adları). Altına CSV dışa/içe aktarma,
 * kaynak ayarları ve Elementor sayfa seçimi eklendi.
 *
 * @package QRMenu_Ceviri
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Ayar formunu kaydet.
 */
if ( ! function_exists( 'rma_ceviri_ayarlari_kaydet' ) ) {
	function rma_ceviri_ayarlari_kaydet() {
		check_admin_referer( 'qrmenu_save_action', 'qrmenu_nonce' );

		$langs         = isset( $_POST['qrmenu_langs'] ) ? array_map( 'sanitize_text_field', (array) $_POST['qrmenu_langs'] ) : array();
		$bg_color_text = isset( $_POST['qrmenu_bg_color_text'] ) ? sanitize_hex_color( $_POST['qrmenu_bg_color_text'] ) : '#111111';
		$bg_color_only = isset( $_POST['qrmenu_bg_color_only'] ) ? sanitize_hex_color( $_POST['qrmenu_bg_color_only'] ) : '#111111';

		update_option( 'qrmenu_active_langs', $langs );
		update_option( 'qrmenu_bg_color_text', $bg_color_text );
		update_option( 'qrmenu_bg_color_only', $bg_color_only );

		/* Kaynak ayarları — yalnızca uygun slug'lar, kategori/alerjen ayrık */
		$gonderilen = function ( $ad ) {
			return isset( $_POST[ $ad ] ) ? array_map( 'sanitize_key', (array) $_POST[ $ad ] ) : array();
		};

		update_option(
			'rma_ceviri_urun_tipleri',
			array_values( array_intersect( $gonderilen( 'rma_urun_tipleri' ), array_keys( rma_ceviri_uygun_urun_tipleri() ) ) ),
			false
		);

		$uygun_taks = array_keys( rma_ceviri_uygun_taksonomiler() );

		list( $kategori_taks, $alerjen_taks ) = rma_ceviri_taks_ayir(
			array_intersect( $gonderilen( 'rma_kategori_taks' ), $uygun_taks ),
			array_intersect( $gonderilen( 'rma_alerjen_taks' ), $uygun_taks )
		);

		update_option( 'rma_ceviri_kategori_taks', $kategori_taks, false );
		update_option( 'rma_ceviri_alerjen_taks', $alerjen_taks, false );

		update_option(
			'rma_ceviri_ek_metinler',
			isset( $_POST['rma_ek_metinler'] ) ? sanitize_textarea_field( wp_unslash( $_POST['rma_ek_metinler'] ) ) : '',
			false
		);

		update_option( 'rma_ceviri_url_yonlendir', empty( $_POST['rma_url_yonlendir'] ) ? 0 : 1, false );
		update_option( 'rma_ceviri_tampon_acik', empty( $_POST['rma_tampon_acik'] ) ? 0 : 1, false );
		update_option( 'rma_ceviri_toplama_acik', empty( $_POST['rma_toplama_acik'] ) ? 0 : 1, false );

		/* Bulunan metinler: seçilenler ek metinlere taşınır (ek metinler
		   yukarıda kaydedildi, aktarım onun üstüne yazar). */
		$aktarilan = 0;
		if ( ! empty( $_POST['rma_bulunan'] ) ) {
			$secilenler = array_map(
				function ( $m ) {
					return sanitize_text_field( wp_unslash( $m ) );
				},
				(array) $_POST['rma_bulunan']
			);
			$aktarilan = rma_ceviri_bulunanlari_aktar( $secilenler );
		}

		if ( ! empty( $_POST['rma_bulunanlari_temizle'] ) ) {
			rma_ceviri_bulunanlari_yaz( array() );
		}

		// Sabit metinler ve diller değişmiş olabilir.
		rma_ceviri_onbellek_temizle();

		$mesaj = 'Ayarlar kaydedildi.';
		if ( $aktarilan > 0 ) {
			$mesaj .= sprintf( ' %d metin listeye eklendi — bir sonraki CSV dışa aktarımında çıkacak.', $aktarilan );
		}

		echo '<div class="updated"><p>' . esc_html( $mesaj ) . '</p></div>';
	}
}

/**
 * İçe aktarma bildirimleri ve son rapor.
 */
if ( ! function_exists( 'rma_ceviri_import_bildirimleri' ) ) {
	function rma_ceviri_import_bildirimleri() {
		$durum = isset( $_GET['ice'] ) ? sanitize_key( wp_unslash( $_GET['ice'] ) ) : '';

		if ( 'hata' === $durum ) {
			$kod     = isset( $_GET['msg'] ) ? sanitize_key( wp_unslash( $_GET['msg'] ) ) : '';
			$hatalar = array(
				'nofile'     => 'Dosya seçilmedi veya yüklenirken hata oluştu.',
				'type'       => 'Sadece .csv dosyaları yükleyebilirsiniz.',
				'size'       => 'Dosya boyutu 20 MB sınırını aşıyor.',
				'invalidcsv' => 'CSV okunamadı (başlık satırı bulunamadı).',
				'sutun'      => 'Başlık satırında item_type/field sütunları ya da hiçbir dil sütunu bulunamadı.',
				'memory'     => 'Dosya bu sunucunun belleğine sığmadı. Dosyayı bölerek yükleyin.',
			);
			$metin   = isset( $hatalar[ $kod ] ) ? $hatalar[ $kod ] : 'Bilinmeyen bir hata oluştu.';

			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( $metin ) . '</p></div>';
			return;
		}

		if ( 'yetim' === $durum ) {
			$silinen = isset( $_GET['silinen'] ) ? (int) $_GET['silinen'] : 0;
			echo '<div class="notice notice-success is-dismissible"><p>';
			printf( esc_html( '%d yetim çeviri satırı silindi.' ), $silinen );
			echo '</p></div>';
			return;
		}

		if ( 'ok' !== $durum ) {
			return;
		}

		$rapor = get_transient( 'rma_ceviri_rapor_' . get_current_user_id() );
		delete_transient( 'rma_ceviri_rapor_' . get_current_user_id() );

		if ( ! is_array( $rapor ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>İçe aktarma tamamlandı.</p></div>';
			return;
		}

		echo '<div class="notice notice-success"><p><strong>İçe aktarma tamamlandı.</strong> ';
		printf(
			esc_html( '%1$d satır okundu · %2$d çeviri yazıldı · %3$d satır atlandı' ),
			(int) $rapor['toplam'],
			(int) $rapor['guncellendi'],
			(int) $rapor['atlandi']
		);
		if ( ! empty( $rapor['temizle'] ) ) {
			printf( esc_html( ' · %d çeviri silindi' ), (int) $rapor['silindi'] );
		}
		echo '</p></div>';

		if ( ! empty( $rapor['atlanan'] ) ) {
			echo '<div class="notice notice-warning"><p><strong>Atlanan satırlar</strong> (ilk ' . count( $rapor['atlanan'] ) . '):</p><ul style="margin-left:18px;list-style:disc;">';
			foreach ( $rapor['atlanan'] as $satir ) {
				printf(
					'<li>Satır %1$d — %2$s</li>',
					(int) $satir['satir'],
					esc_html( $satir['neden'] )
				);
			}
			echo '</ul></div>';
		}

		if ( ! empty( $rapor['bellek_kesildi'] ) ) {
			echo '<div class="notice notice-warning is-dismissible"><p>';
			printf(
				esc_html( 'İşlem bellek sınırına yaklaştığı için durdu. %d satır okundu. Kalanı ayrı bir dosyayla yükleyin.' ),
				(int) $rapor['toplam']
			);
			echo '</p></div>';
		}

		if ( ! empty( $rapor['bayat'] ) ) {
			echo '<div class="notice notice-warning"><p><strong>Orijinal metni değişmiş satırlar</strong> — çeviriler bayat olabilir, bu satırları yeniden çevirmeyi düşünün:</p><ul style="margin-left:18px;list-style:disc;">';
			foreach ( $rapor['bayat'] as $satir ) {
				printf(
					'<li>Satır %1$d — %2$s #%3$d / %4$s → güncel metin: “%5$s”</li>',
					(int) $satir['satir'],
					esc_html( $satir['tip'] ),
					(int) $satir['id'],
					esc_html( $satir['field'] ),
					esc_html( $satir['orijinal'] )
				);
			}
			echo '</ul></div>';
		}
	}
}

/**
 * Sistem durumu — "içe aktardım ama çevrilmiyor" için ilk bakılacak yer.
 *
 * Menü metinleri QR MENÜ eklentisinin İÇİNDEKİ sarmalayıcılarla çevriliyor;
 * o eklenti güncel değilse çeviri tablosu dolu olsa bile kartlar Türkçe
 * kalır. Bu panel iki soruyu tek bakışta yanıtlar: satırlar yazıldı mı,
 * menü entegrasyonu devrede mi?
 */
if ( ! function_exists( 'rma_ceviri_durum_paneli' ) ) {
	function rma_ceviri_durum_paneli() {
		$entegre  = rma_ceviri_rma_entegre_mi();
		$rma_var  = class_exists( 'Restaurant_Menu_Automation' );
		$sayilar  = RMA_Ceviri_Tablo::tip_dil_sayilari();
		$hedefler = rma_ceviri_hedef_diller();
		$tipler   = rma_ceviri_gecerli_tipler();
		$katalog  = qrmenu_get_langs();
		$eskimis  = function_exists( 'rma_ceviri_eskimis_sayilari' ) ? rma_ceviri_eskimis_sayilari() : array();
		$yetim    = function_exists( 'rma_ceviri_yetim_haritasi' ) ? rma_ceviri_yetim_haritasi() : array();
		$yetim_n  = function_exists( 'rma_ceviri_yetim_satir_sayisi' ) ? rma_ceviri_yetim_satir_sayisi( $yetim ) : 0;
		$kaynaklar = function_exists( 'rma_ceviri_tip_kaynak_adetleri' ) ? rma_ceviri_tip_kaynak_adetleri() : array();
		?>
		<h2 class="title qrc-heading"><span class="dashicons dashicons-chart-bar" aria-hidden="true"></span> Sistem Durumu</h2>

		<?php if ( ! $rma_var ) : ?>
			<div class="notice notice-warning inline"><p>
				<strong>QR MENÜ eklentisi bulunamadı.</strong> Menü çevirisi için o eklentinin
				etkin olması gerekiyor. Elementor/tema metinleri çıktı tamponuyla çevrilmeye
				devam eder.
			</p></div>
		<?php elseif ( ! $entegre ) : ?>
			<div class="notice notice-error inline"><p>
				<strong>QR MENÜ eklentisi çeviri entegrasyonu içermiyor.</strong>
				Sitedeki sürüm güncellenmemiş görünüyor (<code>t_field()</code> sarmalayıcısı yok).
				Menü kartları çıktı tamponuyla çevrilmeye devam eder, ama en doğru sonuç için
				<code>qr-menu/</code> eklentisini de bu depodaki sürümle güncelleyin.
			</p></div>
		<?php else : ?>
			<div class="notice notice-success inline"><p>
				<strong>QR MENÜ entegrasyonu etkin.</strong> Kart, kategori ve modal metinleri
				doğrudan çeviri tablosundan basılıyor.
			</p></div>
		<?php endif; ?>

		<?php
		/*
		 * Dar ekranda bu tablo KART görünümüne döner (bkz. assets/css/admin.css):
		 * her içerik satırı kendi kartı, her dil hücresi "Dil: adet" satırı olur.
		 * Sütun sayısı aktif hedef dile bağlı olduğu ve 30'a kadar çıkabildiği
		 * için yatay kaydırma değil kart tercih edildi. Hücrelerdeki data-label
		 * kart görünümünde başlık yerine geçer — thead orada gizlenir.
		 */
		?>
		<table class="widefat striped qrc-stats qrc-cards">
			<thead>
				<tr>
					<th scope="col">İçerik</th>
					<?php foreach ( $hedefler as $dil ) : ?>
						<th scope="col" class="qrc-stats-num"><?php echo esc_html( $dil ); ?></th>
					<?php endforeach; ?>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $tipler as $tip ) : ?>
					<tr>
						<th scope="row" class="qrc-stats-row-head">
							<?php echo esc_html( rma_ceviri_tip_etiketi( $tip ) ); ?>
							<?php if ( isset( $eskimis[ $tip ] ) ) : ?>
								<span class="qrc-stale">Eskimiş: <?php echo (int) $eskimis[ $tip ]; ?></span>
							<?php endif; ?>
						</th>
						<?php foreach ( $hedefler as $dil ) : ?>
							<?php
							$adet   = isset( $sayilar[ $tip ][ $dil ] ) ? (int) $sayilar[ $tip ][ $dil ] : 0;
							$kaynak = isset( $kaynaklar[ $tip ] ) ? (int) $kaynaklar[ $tip ] : -1;
							$hucre  = rma_ceviri_hucre_durumu( $adet, $kaynak );
							// Sütun başlığı dar kalsın diye kod; kart görünümündeki
							// etiket ise bayrak + dil adı (orada yer var, "en" yerine
							// "İngilizce" okunur).
							$etiket = isset( $katalog[ $dil ] )
								? $katalog[ $dil ]['flag'] . ' ' . $katalog[ $dil ]['name']
								: $dil;
							?>
							<td class="qrc-stats-num<?php echo '' !== $hucre['sinif'] ? ' ' . esc_attr( $hucre['sinif'] ) : ''; ?>" data-label="<?php echo esc_attr( $etiket ); ?>">
								<?php echo esc_html( $hucre['metin'] ); ?>
							</td>
						<?php endforeach; ?>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<details class="qrc-limit qrc-details">
			<summary style="cursor:pointer;color:#2271b1;">ⓘ Bu tablo ne anlatıyor?</summary>
			<p class="description" style="margin-top:8px;">
				Her hücre, o içeriğin o dilde kaç çevirisinin kayıtlı olduğunu gösterir.
				<strong>çeviri yok</strong> = kaynak duruyor ama bu dile hiç yazılmamış
				(CSV'yi dışa aktarıp ilgili sütunu doldurun). <strong>kaynak yok</strong> =
				bu tipte çevrilecek metin bulunamadı (ürün seçilmemiş, form alanı yok…).
				Satırlar hâlâ boşsa CSV'deki <code>item_id</code>, <code>item_type</code>
				ve <code>field</code> sütunlarına dokunulmadığından emin olun.
			</p>
			<p class="description">
				Kaynaklar — ürün: <code><?php echo esc_html( implode( ', ', rma_ceviri_urun_tipleri() ) ?: '—' ); ?></code> ·
				kategori: <code><?php echo esc_html( implode( ', ', rma_ceviri_taksonomiler( 'category' ) ) ?: '—' ); ?></code> ·
				alerjen: <code><?php echo esc_html( implode( ', ', rma_ceviri_taksonomiler( 'allergen' ) ) ?: '—' ); ?></code>
			</p>
			<p class="description">
				Yönetici ayarları ve form alanları CSV'ye otomatik çıkar
				(<code>item_type=option</code> / <code>form_field</code> / <code>cf_field</code> / <code>cf_form</code>).
				<code>field</code> sütunu hangi ayar olduğunu gösterir; <code>original_text</code> o anki yönetici metnidir.
				Metin değişince çeviri satırı kalır ama ön yüzde basılmaz (Eskimiş).
			</p>
		</details>

		<p class="description">
			<strong>Yetim satır: <?php echo (int) $yetim_n; ?></strong>
			<?php if ( $yetim_n > 0 ) : ?>
				— silinmiş ürün, form alanı veya özel forma ait çeviriler tabloda duruyor; sitede kullanılmaz.
			<?php else : ?>
				— silinmiş kaynağa bağlı çeviri yok.
			<?php endif; ?>
		</p>
		<?php if ( $yetim_n > 0 ) : ?>
			<form method="POST" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="qrc-yetim-form"
				onsubmit="return confirm('Yetim çeviri satırları kalıcı olarak silinecek. Bu işlem geri alınamaz. Devam edilsin mi?');">
				<input type="hidden" name="action" value="rma_ceviri_yetim_temizle">
				<?php wp_nonce_field( 'rma_ceviri_yetim_temizle', 'rma_ceviri_yetim_nonce' ); ?>
				<label>
					<input type="checkbox" name="rma_ceviri_yetim_onay" value="1" required>
					Evet, yetim satırları silmek istiyorum (geri alınamaz)
				</label>
				<button type="submit" class="button">Yetim satırları temizle</button>
			</form>
		<?php endif; ?>
		<?php
	}
}

/**
 * Sistem Durumu hücresi: sayı / "çeviri yok" / "kaynak yok".
 *
 * WordPress'e bağımsız — test edilebilir. $kaynak_adet < 0 = bilinmiyor;
 * o durumda "çeviri yok" gösterilir (kaynak yok iddiası yanlış olur).
 *
 * @param int $ceviri_adet O tip+dil satır sayısı.
 * @param int $kaynak_adet O tipte kaynak adedi.
 * @return array{metin:string,sinif:string}
 */
if ( ! function_exists( 'rma_ceviri_hucre_durumu' ) ) {
	function rma_ceviri_hucre_durumu( $ceviri_adet, $kaynak_adet ) {
		$ceviri_adet = (int) $ceviri_adet;
		$kaynak_adet = (int) $kaynak_adet;

		if ( $ceviri_adet > 0 ) {
			return array(
				'metin' => (string) $ceviri_adet,
				'sinif' => '',
			);
		}

		if ( 0 === $kaynak_adet ) {
			return array(
				'metin' => 'kaynak yok',
				'sinif' => 'is-empty is-no-source',
			);
		}

		return array(
			'metin' => 'çeviri yok',
			'sinif' => 'is-empty is-no-trans',
		);
	}
}

/**
 * Tip başına kaynak adedi (hücre ayrımı için).
 *
 * Katalog tipleri kesin sayılır. WordPress nesneleri sayılamazsa -1
 * (bilinmiyor) döner; hücre "çeviri yok" gösterir.
 *
 * @return array<string,int>
 */
if ( ! function_exists( 'rma_ceviri_tip_kaynak_adetleri' ) ) {
	function rma_ceviri_tip_kaynak_adetleri() {
		$adet = array();

		foreach ( rma_ceviri_gecerli_tipler() as $tip ) {
			$adet[ $tip ] = -1;
		}

		if ( function_exists( 'rma_ceviri_modul_kaynak_metinleri' ) ) {
			foreach ( rma_ceviri_modul_kaynak_metinleri() as $tip => $metinler ) {
				$adet[ $tip ] = count( $metinler );
			}
		}

		if ( function_exists( 'rma_ceviri_ui_stringleri' ) ) {
			$adet['ui_string'] = count( rma_ceviri_ui_stringleri() );
		}

		$ek = get_option( 'rma_ceviri_ek_metinler', '' );
		if ( is_string( $ek ) && '' !== trim( $ek ) ) {
			$adet['ui_string'] = ( isset( $adet['ui_string'] ) ? max( 0, (int) $adet['ui_string'] ) : 0 )
				+ count( array_filter( array_map( 'trim', explode( "\n", $ek ) ) ) );
		}

		if ( function_exists( 'rma_ceviri_secili_elementor_sayfalari' ) ) {
			$adet['elementor'] = count( rma_ceviri_secili_elementor_sayfalari() );
		}

		if ( function_exists( 'wp_count_posts' ) && function_exists( 'rma_ceviri_urun_tipleri' ) ) {
			$n = 0;
			foreach ( rma_ceviri_urun_tipleri() as $pt ) {
				$c = wp_count_posts( $pt );
				$n += ( is_object( $c ) && isset( $c->publish ) ) ? (int) $c->publish : 0;
			}
			$adet['product'] = $n;
		}

		if ( function_exists( 'rma_ceviri_option_satirlari' ) ) {
			$adet['option'] = iterator_count( rma_ceviri_option_satirlari() );
		}

		return $adet;
	}
}

/**
 * Öğe tipinin kullanıcıya gösterilecek adı.
 *
 * @param string $tip Öğe tipi.
 * @return string
 */
if ( ! function_exists( 'rma_ceviri_tip_etiketi' ) ) {
	function rma_ceviri_tip_etiketi( $tip ) {
		$etiketler = array(
			'product'   => 'Menü ürünleri',
			'category'  => 'Kategoriler',
			'allergen'  => 'Alerjenler',
			'nav_menu'  => 'Menü linkleri (header/footer)',
			'ui_string' => 'Sabit metinler (buton, etiket)',
			'elementor' => 'Sayfa içerikleri (Elementor)',
		);

		if ( function_exists( 'rma_ceviri_modul_tipleri' ) ) {
			$etiketler = array_merge( $etiketler, rma_ceviri_modul_tipleri() );
		}
		if ( function_exists( 'rma_ceviri_veri_tipleri' ) ) {
			$etiketler = array_merge( $etiketler, rma_ceviri_veri_tipleri() );
		}

		return isset( $etiketler[ $tip ] ) ? $etiketler[ $tip ] : $tip;
	}
}

/**
 * Post type / taxonomy seçim kutuları.
 *
 * @param string   $ad      input adı.
 * @param array    $nesneler Seçenek nesneleri (slug => etiket).
 * @param string[] $secili   Seçili slug'lar.
 */
if ( ! function_exists( 'rma_ceviri_secim_kutulari' ) ) {
	function rma_ceviri_secim_kutulari( $ad, $nesneler, $secili ) {
		if ( empty( $nesneler ) ) {
			echo '<em>Uygun kayıt bulunamadı.</em>';
			return;
		}

		// Izgara sabit üç sütun DEĞİLDİR: geniş ekranda sığdığı kadar sütun,
		// dokunmatik/dar ekranda tek sütun ve 48px dokunma yüksekliği.
		echo '<div class="qrc-check-grid">';
		foreach ( $nesneler as $slug => $etiket ) {
			printf(
				'<label class="qrc-check"><input type="checkbox" name="%1$s[]" value="%2$s" %3$s> <span>%4$s <code>%2$s</code></span></label>',
				esc_attr( $ad ),
				esc_attr( $slug ),
				checked( in_array( $slug, $secili, true ), true, false ),
				esc_html( $etiket )
			);
		}
		echo '</div>';
	}
}

/**
 * Ayar sayfası.
 */
if ( ! function_exists( 'qrmenu_trans_page' ) ) {
	function qrmenu_trans_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Yetkiniz yok.' );
		}

		if ( isset( $_POST['qrmenu_trans_save'] ) ) {
			rma_ceviri_ayarlari_kaydet();
		}

		rma_ceviri_import_bildirimleri();
		?>
		<div class="wrap qrc-wrap">
			<h1 class="qrc-heading"><span class="dashicons dashicons-translation" aria-hidden="true"></span> QR Çeviri</h1>
			<p class="description qrc-limit">
				Klasik (tek sayfa) görünüm. Günlük iş için sol menüdeki kart ızgarasını
				kullanın — her adım kendi ayarını kaydeder.
			</p>

			<?php if ( ! RMA_Ceviri_Tablo::tablo_var_mi() ) : ?>
				<div class="notice notice-error"><p>
					Çeviri tablosu bulunamadı. Eklentiyi devre dışı bırakıp yeniden etkinleştirin.
				</p></div>
			<?php endif; ?>

			<?php rma_ceviri_durum_paneli(); ?>

			<form method="POST">
				<?php wp_nonce_field( 'qrmenu_save_action', 'qrmenu_nonce' ); ?>

				<?php qrms_module_qr_ceviri_baslik( 'dashicons-translation', 'Diller' ); ?>
				<?php rma_ceviri_diller_alanlari(); ?>

				<?php qrms_module_qr_ceviri_baslik( 'dashicons-editor-ul', 'Çevrilmeyen metinler' ); ?>
				<?php rma_ceviri_toplama_alanlari(); ?>

				<details class="qrc-details" style="margin:24px 0;">
					<summary class="qrc-heading">
						<span class="dashicons dashicons-admin-generic" aria-hidden="true"></span>
						Gelişmiş Ayarlar
					</summary>
					<?php rma_ceviri_gorunum_alanlari(); ?>
					<?php rma_ceviri_kapsam_alanlari(); ?>
				</details>

				<p class="submit">
					<input type="submit" name="qrmenu_trans_save" class="button button-primary button-large" value="Ayarları Kaydet">
					<span class="description">Klasik görünüm: bu buton yukarıdaki dil, toplama ve kapsam ayarlarını birlikte kaydeder.</span>
				</p>
			</form>

			<hr>
			<?php qrms_module_qr_ceviri_baslik( 'dashicons-download', 'Çeviri CSV\'sini Dışa Aktar' ); ?>
			<?php rma_ceviri_csv_disa_formu(); ?>

			<hr>
			<?php qrms_module_qr_ceviri_baslik( 'dashicons-upload', 'Çeviri CSV\'sini İçe Aktar' ); ?>
			<?php rma_ceviri_csv_ice_formu(); ?>
		</div>
		<?php
	}
}

add_action( 'admin_post_rma_ceviri_yetim_temizle', 'rma_ceviri_yetim_temizle_istek' );

/**
 * Yetim çeviri satırlarını sil (onaylı, geri alınamaz).
 */
if ( ! function_exists( 'rma_ceviri_yetim_temizle_istek' ) ) {
	function rma_ceviri_yetim_temizle_istek() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Yetkiniz yok.' );
		}
		check_admin_referer( 'rma_ceviri_yetim_temizle', 'rma_ceviri_yetim_nonce' );

		$silinen = 0;
		if ( ! empty( $_POST['rma_ceviri_yetim_onay'] ) && function_exists( 'rma_ceviri_yetimleri_sil' ) ) {
			$silinen = rma_ceviri_yetimleri_sil();
		}

		$args = array(
			'page'    => 'qrms-cv-durum',
			'ice'     => 'yetim',
			'silinen' => $silinen,
		);
		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
		exit;
	}
}
