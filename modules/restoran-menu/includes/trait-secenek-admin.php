<?php
/**
 * Yönetim arayüzü: porsiyon, ekstra, servis saati ve özel rozetler.
 *
 * İKİ EKRAN VAR:
 *  - Ürün düzenleme sayfasındaki "Seçenekler" meta kutusu (ürüne özel).
 *  - "Seçenek & Rozet" ayar sayfası: yeniden kullanılan ekstra listeleri ve
 *    özel rozet tanımları (tüm ürünlerin ortak kaynağı).
 *
 * @package QR_Menu_Suite
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait RMA_Secenek_Admin_Trait {

	/**
	 * Seçenek arayüzünün varlıkları.
	 *
	 * İki ayrı kuyruk noktasından çağrılır (modülün kendi admin_scripts'i ve
	 * suite'in module.php kuyruğu), bu yüzden temel adres ve sürüm çözücü
	 * dışarıdan verilir.
	 *
	 * @param string   $url    Modül kök adresi (sonunda / ile).
	 * @param callable $surum  Göreli yol alıp sürüm döndüren fonksiyon.
	 * @return void
	 */
	public function enqueue_secenek_assets( $url, $surum ) {
		wp_enqueue_style(
			'rma-urun-secenekler',
			$url . 'assets/css/urun-secenekler.css',
			array(),
			call_user_func( $surum, 'assets/css/urun-secenekler.css' )
		);

		wp_enqueue_script(
			'rma-urun-secenekler',
			$url . 'assets/js/urun-secenekler.js',
			array(),
			call_user_func( $surum, 'assets/js/urun-secenekler.js' ),
			true
		);
	}

	/* =============================================================
	   ÜRÜN META KUTUSU
	============================================================= */

	/**
	 * "Seçenekler" meta kutusunu kaydeder.
	 *
	 * @return void
	 */
	public function add_secenek_meta_box() {
		add_meta_box(
			'rma_secenekler',
			'Porsiyon, Ekstra ve Servis Saati',
			array( $this, 'render_secenek_meta_box' ),
			'rma_menu_item',
			'normal',
			'default'
		);
	}

	/**
	 * Meta kutusu içeriği.
	 *
	 * @param WP_Post $post Ürün.
	 * @return void
	 */
	public function render_secenek_meta_box( $post ) {
		wp_nonce_field( 'rma_save_secenek', 'rma_secenek_nonce' );

		$porsiyonlar = RMA_Porsiyon::oku( $post->ID );
		$manuel      = RMA_Ekstra::manuel( $post->ID );
		$listeler    = RMA_Ekstra::listeler();
		$secili      = RMA_Ekstra::liste_idleri( $post->ID );
		$rozetler    = RMA_Ozel_Rozet::tanimlar();
		$secili_roz  = RMA_Ozel_Rozet::urun_sluglari( $post->ID );
		$mod         = (string) get_post_meta( $post->ID, RMA_Servis_Saati::META_MOD, true );
		$mod         = in_array( $mod, array( 'kapali', 'ozel' ), true ) ? $mod : 'devral';
		$gunler      = RMA_Servis_Saati::gunleri_temizle( get_post_meta( $post->ID, RMA_Servis_Saati::META_GUNLER, true ) );
		$bas         = RMA_Servis_Saati::saati_temizle( get_post_meta( $post->ID, RMA_Servis_Saati::META_BAS, true ) );
		$bit         = RMA_Servis_Saati::saati_temizle( get_post_meta( $post->ID, RMA_Servis_Saati::META_BIT, true ) );
		$ayar_url    = $this->admin_page_url( 'qrms-rm-secenekler' );
		?>
		<div class="rma-secenek-kutu">

			<h4 class="rma-secenek-h">Porsiyon / Varyasyon</h4>
			<p class="rma-secenek-not">
				Fiyat, ürünün <strong>taban fiyatına eklenen fark</strong> olarak yazılır: "Büyük" için <code>40</code>,
				"Küçük" için <code>-20</code>. Fark yazmazsanız o porsiyon taban fiyattan satılır.
				Porsiyon eklemezseniz müşteri seçim ekranı görmez.
			</p>

			<table class="widefat rma-tekrar" data-rma-tekrar="porsiyon" data-azami="<?php echo (int) RMA_Porsiyon::AZAMI; ?>">
				<thead>
					<tr>
						<th style="width:60%;">Porsiyon adı</th>
						<th style="width:30%;">Fiyat farkı (₺)</th>
						<th style="width:10%;"></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $porsiyonlar as $i => $satir ) : ?>
					<tr class="rma-tekrar-satir">
						<td><input type="text" name="rma_porsiyon[<?php echo (int) $i; ?>][ad]" value="<?php echo esc_attr( $satir['ad'] ); ?>" placeholder="Büyük" class="widefat"></td>
						<td><input type="text" name="rma_porsiyon[<?php echo (int) $i; ?>][fark]" value="<?php echo esc_attr( $satir['fark'] ); ?>" placeholder="0" class="widefat"></td>
						<td><button type="button" class="button-link rma-tekrar-sil" aria-label="Satırı sil">✕</button></td>
					</tr>
					<?php endforeach; ?>
				</tbody>
				<?php
				$this->render_tekrar_sablonu(
					array(
						array( 'ad', 'text', 'Büyük' ),
						array( 'fark', 'text', '0' ),
					),
					'rma_porsiyon'
				);
				?>
			</table>
			<p><button type="button" class="button rma-tekrar-ekle" data-hedef="porsiyon">+ Porsiyon ekle</button></p>

			<hr>

			<h4 class="rma-secenek-h">Yan Ürünler / Ekstralar</h4>
			<p class="rma-secenek-not">
				Müşteri ürün kartını açtığında en altta "Ekstra ekle" bölümü çıkar. Hiçbir şey seçmezseniz bu bölüm görünmez.
				Hazır listeler <a href="<?php echo esc_url( $ayar_url ); ?>">Ekstralar ve Rozetler</a> ekranından yönetilir.
			</p>

			<?php if ( ! empty( $listeler ) ) : ?>
			<div class="rma-secenek-listeler">
				<strong>Hazır listeler</strong>
				<div class="rma-secenek-kutucuklar">
					<?php foreach ( $listeler as $liste ) : ?>
					<label>
						<input type="checkbox" name="rma_ekstra_listeler[]" value="<?php echo esc_attr( $liste['id'] ); ?>"
							<?php checked( in_array( $liste['id'], $secili, true ) ); ?>>
						<?php echo esc_html( $liste['ad'] ); ?>
						<span class="rma-secenek-sayi"><?php echo (int) count( $liste['urunler'] ); ?></span>
					</label>
					<?php endforeach; ?>
				</div>
			</div>
			<?php else : ?>
			<p class="rma-secenek-not">Henüz hazır liste yok — <a href="<?php echo esc_url( $ayar_url ); ?>">Ekstralar ve Rozetler</a> ekranından oluşturabilirsiniz.</p>
			<?php endif; ?>

			<p><strong>Yalnızca bu ürüne özel ekstralar</strong></p>
			<table class="widefat rma-tekrar" data-rma-tekrar="ekstra" data-azami="30">
				<thead>
					<tr>
						<th style="width:60%;">Ekstra adı</th>
						<th style="width:30%;">Fiyat (₺)</th>
						<th style="width:10%;"></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $manuel as $i => $satir ) : ?>
					<tr class="rma-tekrar-satir">
						<td><input type="text" name="rma_ekstra_manuel[<?php echo (int) $i; ?>][ad]" value="<?php echo esc_attr( $satir['ad'] ); ?>" placeholder="Sos" class="widefat"></td>
						<td><input type="text" name="rma_ekstra_manuel[<?php echo (int) $i; ?>][fiyat]" value="<?php echo esc_attr( $satir['fiyat'] ); ?>" placeholder="10" class="widefat"></td>
						<td><button type="button" class="button-link rma-tekrar-sil" aria-label="Satırı sil">✕</button></td>
					</tr>
					<?php endforeach; ?>
				</tbody>
				<?php
				$this->render_tekrar_sablonu(
					array(
						array( 'ad', 'text', 'Sos' ),
						array( 'fiyat', 'text', '10' ),
					),
					'rma_ekstra_manuel'
				);
				?>
			</table>
			<p><button type="button" class="button rma-tekrar-ekle" data-hedef="ekstra">+ Ekstra ekle</button></p>

			<hr>

			<h4 class="rma-secenek-h">Servis Saati</h4>
			<p class="rma-secenek-not">
				Saat dışında ürün menüde kalır, üzerine "Servis dışı" etiketi basılır ve sepete eklenemez.
				Varsayılan olarak ürün, kategorisine tanımlanmış saati devralır (Kategoriler ekranından).
			</p>

			<p>
				<label><input type="radio" name="rma_servis_mod" value="devral" <?php checked( 'devral', $mod ); ?>> Kategoriden devral</label><br>
				<label><input type="radio" name="rma_servis_mod" value="kapali" <?php checked( 'kapali', $mod ); ?>> Kısıt yok (her zaman servis edilir)</label><br>
				<label><input type="radio" name="rma_servis_mod" value="ozel" <?php checked( 'ozel', $mod ); ?>> Bu ürüne özel saat</label>
			</p>

			<div class="rma-servis-alan">
				<?php $this->render_servis_saat_alanlari( 'rma_servis', $gunler, $bas, $bit ); ?>
			</div>

			<?php if ( ! empty( $rozetler ) ) : ?>
			<hr>
			<h4 class="rma-secenek-h">Özel Rozetler</h4>
			<div class="rma-secenek-kutucuklar">
				<?php foreach ( $rozetler as $rozet ) : ?>
				<label>
					<input type="checkbox" name="rma_ozel_rozetler[]" value="<?php echo esc_attr( $rozet['slug'] ); ?>"
						<?php checked( in_array( $rozet['slug'], $secili_roz, true ) ); ?>>
					<span class="rma-rozet-onizleme" style="background:<?php echo esc_attr( $rozet['renk'] ); ?>">
						<?php echo esc_html( trim( $rozet['ikon'] . ' ' . $rozet['ad'] ) ); ?>
					</span>
				</label>
				<?php endforeach; ?>
			</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Tekrarlı tablolar için satır şablonu.
	 *
	 * JS satırı klonlamak yerine bu şablonu kullanır: tablo boşken de
	 * satır eklenebilir ve alan adları tek yerde tanımlı kalır.
	 * `__i__` yer tutucusu istemcide sıradaki indisle değiştirilir.
	 *
	 * @param array  $sutunlar [ [anahtar, tip, placeholder], … ].
	 * @param string $onek     Alan adı öneki (rma_porsiyon gibi).
	 * @return void
	 */
	private function render_tekrar_sablonu( array $sutunlar, $onek ) {
		echo '<template class="rma-tekrar-sablon"><tr class="rma-tekrar-satir">';

		foreach ( $sutunlar as $sutun ) {
			list( $anahtar, $tip, $varsayilan ) = array_pad( $sutun, 3, '' );

			$ad = $onek . '[__i__][' . $anahtar . ']';

			printf(
				'<td><input type="%s" class="widefat" name="%s" %s="%s"></td>',
				esc_attr( $tip ),
				esc_attr( $ad ),
				'color' === $tip ? 'value' : 'placeholder',
				esc_attr( $varsayilan )
			);
		}

		echo '<td><button type="button" class="button-link rma-tekrar-sil" aria-label="Satırı sil">✕</button></td>';
		echo '</tr></template>';
	}

	/**
	 * Gün kutucukları + başlangıç/bitiş saati alanları.
	 *
	 * Ürün meta kutusu ve kategori formu aynı biçimi kullanır.
	 *
	 * @param string $onek   Alan adı öneki ('rma_servis' | 'rma_cat_servis').
	 * @param int[]  $gunler Seçili günler.
	 * @param string $bas    Başlangıç saati.
	 * @param string $bit    Bitiş saati.
	 * @return void
	 */
	private function render_servis_saat_alanlari( $onek, array $gunler, $bas, $bit ) {
		?>
		<div class="rma-servis-gunler">
			<?php foreach ( RMA_Servis_Saati::gunler() as $no => $ad ) : ?>
			<label>
				<input type="checkbox" name="<?php echo esc_attr( $onek ); ?>_gunler[]" value="<?php echo (int) $no; ?>"
					<?php checked( in_array( $no, $gunler, true ) ); ?>>
				<?php echo esc_html( $ad ); ?>
			</label>
			<?php endforeach; ?>
		</div>
		<p class="rma-servis-saatler">
			<label>Başlangıç <input type="time" name="<?php echo esc_attr( $onek ); ?>_bas" value="<?php echo esc_attr( $bas ); ?>"></label>
			<label>Bitiş <input type="time" name="<?php echo esc_attr( $onek ); ?>_bit" value="<?php echo esc_attr( $bit ); ?>"></label>
		</p>
		<?php
	}

	/**
	 * Meta kutusu kaydı.
	 *
	 * Ürün Detayları kutusundan AYRI bir nonce kullanır: hızlı düzenleme
	 * (quick edit) bu alanları hiç göndermez, o yüzden nonce yoksa mevcut
	 * değerlere dokunulmaz.
	 *
	 * @param int $post_id Ürün ID.
	 * @return void
	 */
	public function save_secenek_meta( $post_id ) {
		if ( ! isset( $_POST['rma_secenek_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['rma_secenek_nonce'] ) ), 'rma_save_secenek' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		RMA_Porsiyon::kaydet( $post_id, wp_unslash( $_POST['rma_porsiyon'] ?? array() ) );

		RMA_Ekstra::urun_kaydet(
			$post_id,
			wp_unslash( $_POST['rma_ekstra_manuel'] ?? array() ),
			wp_unslash( $_POST['rma_ekstra_listeler'] ?? array() )
		);

		RMA_Ozel_Rozet::urun_kaydet( $post_id, wp_unslash( $_POST['rma_ozel_rozetler'] ?? array() ) );

		$mod = sanitize_key( (string) ( $_POST['rma_servis_mod'] ?? 'devral' ) );
		$mod = in_array( $mod, array( 'kapali', 'ozel' ), true ) ? $mod : 'devral';

		if ( 'devral' === $mod ) {
			delete_post_meta( $post_id, RMA_Servis_Saati::META_MOD );
		} else {
			update_post_meta( $post_id, RMA_Servis_Saati::META_MOD, $mod );
		}

		update_post_meta( $post_id, RMA_Servis_Saati::META_GUNLER, RMA_Servis_Saati::gunleri_temizle( wp_unslash( $_POST['rma_servis_gunler'] ?? array() ) ) );
		update_post_meta( $post_id, RMA_Servis_Saati::META_BAS, RMA_Servis_Saati::saati_temizle( wp_unslash( $_POST['rma_servis_bas'] ?? '' ) ) );
		update_post_meta( $post_id, RMA_Servis_Saati::META_BIT, RMA_Servis_Saati::saati_temizle( wp_unslash( $_POST['rma_servis_bit'] ?? '' ) ) );
	}

	/* =============================================================
	   KATEGORİ ALANLARI (servis saati)
	============================================================= */

	/**
	 * Kategori DÜZENLEME formundaki servis saati alanları.
	 *
	 * @param WP_Term $term Kategori.
	 * @return void
	 */
	public function edit_category_servis_fields( $term ) {
		$aktif  = '1' === (string) get_term_meta( $term->term_id, RMA_Servis_Saati::TERIM_AKTIF, true );
		$gunler = RMA_Servis_Saati::gunleri_temizle( get_term_meta( $term->term_id, RMA_Servis_Saati::TERIM_GUNLER, true ) );
		$bas    = RMA_Servis_Saati::saati_temizle( get_term_meta( $term->term_id, RMA_Servis_Saati::TERIM_BAS, true ) );
		$bit    = RMA_Servis_Saati::saati_temizle( get_term_meta( $term->term_id, RMA_Servis_Saati::TERIM_BIT, true ) );
		?>
		<tr class="form-field">
			<th><label>Servis saati</label></th>
			<td>
				<label>
					<input type="checkbox" name="rma_cat_servis_aktif" value="1" <?php checked( $aktif ); ?>>
					Bu kategori yalnızca belirli gün ve saatlerde servis edilsin
				</label>
				<div class="rma-servis-alan" style="margin-top:10px;">
					<?php $this->render_servis_saat_alanlari( 'rma_cat_servis', $gunler, $bas, $bit ); ?>
				</div>
				<p class="description">
					Örnek: kahvaltı için Pazartesi–Cuma, 07:00–11:00. Saat dışında ürünler menüde kalır,
					"Servis dışı" etiketiyle gösterilir ve sepete eklenemez.
				</p>
			</td>
		</tr>
		<?php
	}

	/**
	 * Kategori servis saati kaydı.
	 *
	 * @param int $term_id Kategori ID.
	 * @return void
	 */
	public function save_category_servis_fields( $term_id ) {
		// Alanları basmayan formlardan (hızlı ekleme) gelen kayıtlarda
		// mevcut değere dokunulmaz.
		if ( ! isset( $_POST['rma_cat_servis_bas'] ) && ! isset( $_POST['rma_cat_servis_aktif'] ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_categories' ) ) {
			return;
		}

		update_term_meta( $term_id, RMA_Servis_Saati::TERIM_AKTIF, isset( $_POST['rma_cat_servis_aktif'] ) ? '1' : '0' );
		update_term_meta( $term_id, RMA_Servis_Saati::TERIM_GUNLER, RMA_Servis_Saati::gunleri_temizle( wp_unslash( $_POST['rma_cat_servis_gunler'] ?? array() ) ) );
		update_term_meta( $term_id, RMA_Servis_Saati::TERIM_BAS, RMA_Servis_Saati::saati_temizle( wp_unslash( $_POST['rma_cat_servis_bas'] ?? '' ) ) );
		update_term_meta( $term_id, RMA_Servis_Saati::TERIM_BIT, RMA_Servis_Saati::saati_temizle( wp_unslash( $_POST['rma_cat_servis_bit'] ?? '' ) ) );

		$this->bump_cache_version();
	}

	/* =============================================================
	   AYAR SAYFASI — ekstra listeleri + özel rozetler
	============================================================= */

	/**
	 * "Ekstralar ve Rozetler" sayfası.
	 *
	 * @return void
	 */
	public function render_secenekler_page() {
		$sayfa = $this->get_subpages()['qrms-rm-secenekler'];

		$this->page_header(
			$sayfa['title'],
			'Birden çok üründe kullanacağınız ekstra gruplarını ve ürün rozetlerinizi burada tanımlayın.'
		);

		$aktif_sekme = isset( $_GET['sekme'] ) ? sanitize_key( wp_unslash( $_GET['sekme'] ) ) : 'ekstra';
		if ( ! in_array( $aktif_sekme, array( 'ekstra', 'rozet' ), true ) ) {
			$aktif_sekme = 'ekstra';
		}

		if ( isset( $_GET['secenek_msg'] ) ) {
			$msg = sanitize_key( wp_unslash( $_GET['secenek_msg'] ) );

			if ( preg_match( '/^ekstra_(\d+)$/', $msg, $eslesme ) ) {
				echo '<div class="notice notice-success is-dismissible"><p>'
					. esc_html( sprintf( '%d ekstra listesi kaydedildi.', (int) $eslesme[1] ) )
					. '</p></div>';
			} elseif ( preg_match( '/^rozet_(\d+)$/', $msg, $eslesme ) ) {
				echo '<div class="notice notice-success is-dismissible"><p>'
					. esc_html( sprintf( '%d rozet kaydedildi.', (int) $eslesme[1] ) )
					. '</p></div>';
			}
		}

		$listeler         = RMA_Ekstra::listeler();
		$rozetler         = RMA_Ozel_Rozet::tanimlar();
		$ekstra_kullanim  = RMA_Ekstra::kullanim_sayilari();
		$rozet_kullanim   = RMA_Ozel_Rozet::kullanim_sayilari();
		$sekme_url        = $this->admin_page_url( 'qrms-rm-secenekler' );
		?>
		<div class="rma-secenek-sayfa" data-rma-secenek-sayfa data-aktif-sekme="<?php echo esc_attr( $aktif_sekme ); ?>">

			<div class="rma-secenek-sekmeler" role="tablist" aria-label="Ekstra ve rozet bölümleri">
				<a href="<?php echo esc_url( add_query_arg( 'sekme', 'ekstra', $sekme_url ) ); ?>#rma-ekstra-listeleri"
					class="rma-secenek-sekme<?php echo 'ekstra' === $aktif_sekme ? ' is-active' : ''; ?>"
					id="rma-sekme-ekstra"
					role="tab"
					aria-selected="<?php echo 'ekstra' === $aktif_sekme ? 'true' : 'false'; ?>"
					aria-controls="rma-panel-ekstra"
					tabindex="<?php echo 'ekstra' === $aktif_sekme ? '0' : '-1'; ?>">
					Ekstra Ürünler
				</a>
				<a href="<?php echo esc_url( add_query_arg( 'sekme', 'rozet', $sekme_url ) ); ?>#rma-ozel-rozetler"
					class="rma-secenek-sekme<?php echo 'rozet' === $aktif_sekme ? ' is-active' : ''; ?>"
					id="rma-sekme-rozet"
					role="tab"
					aria-selected="<?php echo 'rozet' === $aktif_sekme ? 'true' : 'false'; ?>"
					aria-controls="rma-panel-rozet"
					tabindex="<?php echo 'rozet' === $aktif_sekme ? '0' : '-1'; ?>">
					Ürün Rozetleri
				</a>
			</div>

			<div class="rma-secenek-panel<?php echo 'ekstra' === $aktif_sekme ? ' is-active' : ''; ?>"
				id="rma-panel-ekstra"
				role="tabpanel"
				aria-labelledby="rma-sekme-ekstra"
				<?php echo 'ekstra' !== $aktif_sekme ? ' hidden' : ''; ?>>

				<div class="rma-card" id="rma-ekstra-listeleri">
					<h2 class="rma-card-title">Ekstra Ürünler</h2>
					<p class="rma-card-desc">
						Ürünün yanında satılan sos, içecek, ek malzeme gruplarını bir kez tanımlayın; ürün ekranından işaretleyin.
						Fiyatı buradan değiştirdiğinizde bu listeyi kullanan tüm ürünlerde güncellenir.
					</p>

					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-rma-ekstra-form>
						<input type="hidden" name="action" value="rma_ekstra_listeleri_kaydet">
						<?php wp_nonce_field( 'rma_ekstra_listeleri' ); ?>

						<div class="rma-liste-kaplar" data-rma-listeler>
							<?php foreach ( $listeler as $li => $liste ) :
								$kullanim = isset( $ekstra_kullanim[ $liste['id'] ] ) ? (int) $ekstra_kullanim[ $liste['id'] ] : 0;
								?>
							<div class="rma-liste-kap" data-rma-siralanabilir data-kullanim="<?php echo (int) $kullanim; ?>" draggable="true">
								<div class="rma-sira-arac">
									<button type="button" class="rma-sira-tutamac" aria-label="Sürükleyerek sırala" tabindex="-1">⠿</button>
									<button type="button" class="rma-sira-yukari" aria-label="Yukarı taşı">↑</button>
									<button type="button" class="rma-sira-asagi" aria-label="Aşağı taşı">↓</button>
								</div>
								<input type="hidden" name="rma_ekstra_listeleri[<?php echo (int) $li; ?>][id]" value="<?php echo esc_attr( $liste['id'] ); ?>">
								<p class="rma-liste-baslik">
									<input type="text" class="regular-text" name="rma_ekstra_listeleri[<?php echo (int) $li; ?>][ad]"
										value="<?php echo esc_attr( $liste['ad'] ); ?>" placeholder="Liste adı (Soslar)">
									<span class="rma-kullanim-sayaci"><?php echo esc_html( sprintf( '%d üründe kullanılıyor', $kullanim ) ); ?></span>
									<button type="button" class="button-link rma-liste-sil">Listeyi sil</button>
								</p>
								<table class="widefat rma-tekrar rma-secenek-tablo" data-rma-tekrar="liste-<?php echo (int) $li; ?>" data-azami="30">
									<thead><tr><th style="width:60%;">Ürün</th><th style="width:30%;">Fiyat (₺)</th><th></th></tr></thead>
									<tbody>
										<?php foreach ( $liste['urunler'] as $ui => $urun ) : ?>
										<tr class="rma-tekrar-satir">
											<td data-label="Ürün"><input type="text" class="widefat" name="rma_ekstra_listeleri[<?php echo (int) $li; ?>][urunler][<?php echo (int) $ui; ?>][ad]" value="<?php echo esc_attr( $urun['ad'] ); ?>"></td>
											<td data-label="Fiyat (₺)"><input type="text" class="widefat" name="rma_ekstra_listeleri[<?php echo (int) $li; ?>][urunler][<?php echo (int) $ui; ?>][fiyat]" value="<?php echo esc_attr( $urun['fiyat'] ); ?>"></td>
											<td><button type="button" class="button-link rma-tekrar-sil" aria-label="Satırı sil">✕</button></td>
										</tr>
										<?php endforeach; ?>
									</tbody>
									<?php
									$this->render_tekrar_sablonu(
										array(
											array( 'ad', 'text', 'Ketçap' ),
											array( 'fiyat', 'text', '10' ),
										),
										'rma_ekstra_listeleri[' . (int) $li . '][urunler]'
									);
									?>
								</table>
								<p><button type="button" class="button rma-tekrar-ekle" data-hedef="liste-<?php echo (int) $li; ?>">+ Ürün ekle</button></p>
							</div>
							<?php endforeach; ?>
						</div>

						<div class="rma-bos-durum" data-rma-ekstra-bos<?php echo empty( $listeler ) ? '' : ' hidden'; ?>>
							<p class="rma-empty">Henüz liste yok</p>
							<button type="button" class="button" data-rma-sablon-ekstra>Örnek şablon ekle (Soslar, İçecekler, Ekstra Malzeme)</button>
						</div>

						<template id="rma-liste-sablon">
							<div class="rma-liste-kap" data-rma-siralanabilir data-kullanim="0" draggable="true">
								<div class="rma-sira-arac">
									<button type="button" class="rma-sira-tutamac" aria-label="Sürükleyerek sırala" tabindex="-1">⠿</button>
									<button type="button" class="rma-sira-yukari" aria-label="Yukarı taşı">↑</button>
									<button type="button" class="rma-sira-asagi" aria-label="Aşağı taşı">↓</button>
								</div>
								<p class="rma-liste-baslik">
									<input type="text" class="regular-text" name="rma_ekstra_listeleri[__li__][ad]" placeholder="Liste adı (Soslar)">
									<span class="rma-kullanim-sayaci">0 üründe kullanılıyor</span>
									<button type="button" class="button-link rma-liste-sil">Listeyi sil</button>
								</p>
								<table class="widefat rma-tekrar rma-secenek-tablo" data-rma-tekrar="liste-__li__" data-azami="30">
									<thead><tr><th style="width:60%;">Ürün</th><th style="width:30%;">Fiyat (₺)</th><th></th></tr></thead>
									<tbody>
										<tr class="rma-tekrar-satir">
											<td data-label="Ürün"><input type="text" class="widefat" name="rma_ekstra_listeleri[__li__][urunler][0][ad]" placeholder="Ketçap"></td>
											<td data-label="Fiyat (₺)"><input type="text" class="widefat" name="rma_ekstra_listeleri[__li__][urunler][0][fiyat]" placeholder="10"></td>
											<td><button type="button" class="button-link rma-tekrar-sil" aria-label="Satırı sil">✕</button></td>
										</tr>
									</tbody>
									<template class="rma-tekrar-sablon">
										<tr class="rma-tekrar-satir">
											<td data-label="Ürün"><input type="text" class="widefat" name="rma_ekstra_listeleri[__li__][urunler][__i__][ad]" placeholder="Ketçap"></td>
											<td data-label="Fiyat (₺)"><input type="text" class="widefat" name="rma_ekstra_listeleri[__li__][urunler][__i__][fiyat]" placeholder="10"></td>
											<td><button type="button" class="button-link rma-tekrar-sil" aria-label="Satırı sil">✕</button></td>
										</tr>
									</template>
								</table>
								<p><button type="button" class="button rma-tekrar-ekle" data-hedef="liste-__li__">+ Ürün ekle</button></p>
							</div>
						</template>

						<p class="rma-secenek-aksiyonlar">
							<button type="button" class="button" data-rma-liste-ekle>+ Yeni liste</button>
							<button type="submit" class="button button-primary">Listeleri Kaydet</button>
						</p>
					</form>
				</div>
			</div>

			<div class="rma-secenek-panel<?php echo 'rozet' === $aktif_sekme ? ' is-active' : ''; ?>"
				id="rma-panel-rozet"
				role="tabpanel"
				aria-labelledby="rma-sekme-rozet"
				<?php echo 'rozet' !== $aktif_sekme ? ' hidden' : ''; ?>>

				<div class="rma-card" id="rma-ozel-rozetler">
					<h2 class="rma-card-title">Ürün Rozetleri</h2>
					<p class="rma-card-desc">
						Ürün kartında ve detay penceresinde görünecek kendi etiketleriniz: Hızlı Servis, Acı, Yüksek Protein…
					</p>

					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-rma-rozet-form>
						<input type="hidden" name="action" value="rma_ozel_rozet_kaydet">
						<?php wp_nonce_field( 'rma_ozel_rozet' ); ?>

						<table class="widefat rma-tekrar rma-secenek-tablo rma-rozet-tablo" data-rma-tekrar="rozet" data-azami="<?php echo (int) RMA_Ozel_Rozet::AZAMI; ?>" data-rma-rozet-tablosu>
							<thead>
								<tr>
									<th class="rma-sira-sutun" aria-hidden="true"></th>
									<th style="width:30%;">Rozet adı</th>
									<th style="width:12%;">İkon</th>
									<th style="width:22%;">Renk</th>
									<th style="width:18%;">Önizleme</th>
									<th></th>
								</tr>
							</thead>
							<tbody data-rma-siralanabilir-tbody>
								<?php foreach ( $rozetler as $i => $rozet ) :
									$kullanim = isset( $rozet_kullanim[ $rozet['slug'] ] ) ? (int) $rozet_kullanim[ $rozet['slug'] ] : 0;
									?>
								<tr class="rma-tekrar-satir rma-rozet-satir" data-kullanim="<?php echo (int) $kullanim; ?>" draggable="true">
									<td class="rma-sira-sutun" data-label="">
										<div class="rma-sira-arac">
											<button type="button" class="rma-sira-tutamac" aria-label="Sürükleyerek sırala" tabindex="-1">⠿</button>
											<button type="button" class="rma-sira-yukari" aria-label="Yukarı taşı">↑</button>
											<button type="button" class="rma-sira-asagi" aria-label="Aşağı taşı">↓</button>
										</div>
									</td>
									<td data-label="Rozet adı">
										<input type="hidden" name="rma_ozel_rozetler[<?php echo (int) $i; ?>][slug]" value="<?php echo esc_attr( $rozet['slug'] ); ?>">
										<input type="text" class="widefat rma-rozet-ad" name="rma_ozel_rozetler[<?php echo (int) $i; ?>][ad]" value="<?php echo esc_attr( $rozet['ad'] ); ?>">
										<span class="rma-kullanim-sayaci"><?php echo esc_html( sprintf( '%d üründe kullanılıyor', $kullanim ) ); ?></span>
									</td>
									<td data-label="İkon">
										<div class="rma-ikon-secici" data-rma-ikon-secici>
											<button type="button" class="rma-ikon-tetik" aria-haspopup="listbox" aria-expanded="false">
												<span class="rma-ikon-goster"><?php echo '' !== $rozet['ikon'] ? esc_html( $rozet['ikon'] ) : '—'; ?></span>
											</button>
											<input type="hidden" class="rma-rozet-ikon" name="rma_ozel_rozetler[<?php echo (int) $i; ?>][ikon]" value="<?php echo esc_attr( $rozet['ikon'] ); ?>" maxlength="4">
										</div>
									</td>
									<td data-label="Renk">
										<div class="rma-renk-alan" data-rma-renk-alan>
											<input type="text" class="rma-rozet-renk widefat" name="rma_ozel_rozetler[<?php echo (int) $i; ?>][renk]"
												value="<?php echo esc_attr( $rozet['renk'] ); ?>"
												data-default-color="<?php echo esc_attr( RMA_Ozel_Rozet::RENK ); ?>">
										</div>
									</td>
									<td data-label="Önizleme">
										<span class="rma-badge rma-badge-ozel rma-rozet-onizleme-kutu"
											style="--rma-rozet-renk:<?php echo esc_attr( $rozet['renk'] ); ?>">
											<?php echo '' !== $rozet['ikon'] ? esc_html( $rozet['ikon'] ) . ' ' : ''; ?><?php echo esc_html( $rozet['ad'] ); ?>
										</span>
									</td>
									<td><button type="button" class="button-link rma-tekrar-sil" aria-label="Satırı sil">✕</button></td>
								</tr>
								<?php endforeach; ?>
							</tbody>
							<?php
							// slug sütunu şablonda yok: yeni rozetin slug'ı kaydederken
							// addan türetilir (bkz. RMA_Ozel_Rozet::temizle).
							$this->render_rozet_sablonu();
							?>
						</table>

						<div class="rma-bos-durum" data-rma-rozet-bos<?php echo empty( $rozetler ) ? '' : ' hidden'; ?>>
							<p class="rma-empty">Henüz rozet yok</p>
							<button type="button" class="button" data-rma-sablon-rozet>Örnek rozetler ekle (Hızlı Servis, Acı, Şefin Önerisi)</button>
						</div>

						<p class="rma-secenek-aksiyonlar">
							<button type="button" class="button rma-tekrar-ekle" data-hedef="rozet">+ Rozet ekle</button>
							<button type="submit" class="button button-primary">Rozetleri Kaydet</button>
						</p>
					</form>
				</div>
			</div>
		</div>
		<?php
		$this->page_footer();
	}

	/**
	 * Rozet tablosu için satır şablonu (ikon seçici + renk alanı dahil).
	 *
	 * @return void
	 */
	private function render_rozet_sablonu() {
		$renk = esc_attr( RMA_Ozel_Rozet::RENK );
		?>
		<template class="rma-tekrar-sablon">
			<tr class="rma-tekrar-satir rma-rozet-satir" data-kullanim="0" draggable="true">
				<td class="rma-sira-sutun" data-label="">
					<div class="rma-sira-arac">
						<button type="button" class="rma-sira-tutamac" aria-label="Sürükleyerek sırala" tabindex="-1">⠿</button>
						<button type="button" class="rma-sira-yukari" aria-label="Yukarı taşı">↑</button>
						<button type="button" class="rma-sira-asagi" aria-label="Aşağı taşı">↓</button>
					</div>
				</td>
				<td data-label="Rozet adı">
					<input type="text" class="widefat rma-rozet-ad" name="rma_ozel_rozetler[__i__][ad]" placeholder="Hızlı Servis">
					<span class="rma-kullanim-sayaci">0 üründe kullanılıyor</span>
				</td>
				<td data-label="İkon">
					<div class="rma-ikon-secici" data-rma-ikon-secici>
						<button type="button" class="rma-ikon-tetik" aria-haspopup="listbox" aria-expanded="false">
							<span class="rma-ikon-goster">—</span>
						</button>
						<input type="hidden" class="rma-rozet-ikon" name="rma_ozel_rozetler[__i__][ikon]" value="" maxlength="4">
					</div>
				</td>
				<td data-label="Renk">
					<div class="rma-renk-alan" data-rma-renk-alan>
						<input type="text" class="rma-rozet-renk widefat" name="rma_ozel_rozetler[__i__][renk]"
							value="<?php echo $renk; ?>"
							data-default-color="<?php echo $renk; ?>">
					</div>
				</td>
				<td data-label="Önizleme">
					<span class="rma-badge rma-badge-ozel rma-rozet-onizleme-kutu" style="--rma-rozet-renk:<?php echo $renk; ?>">Hızlı Servis</span>
				</td>
				<td><button type="button" class="button-link rma-tekrar-sil" aria-label="Satırı sil">✕</button></td>
			</tr>
		</template>
		<?php
	}

	/**
	 * Ekstra listelerini kaydeder (admin-post).
	 *
	 * @return void
	 */
	public function handle_ekstra_listeleri_save() {
		$yetki = class_exists( 'QRMS_Admin' ) ? QRMS_Admin::CAPABILITY : 'manage_options';
		if ( ! current_user_can( $yetki ) ) {
			wp_die( 'Yetkiniz yok.', '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'rma_ekstra_listeleri' );

		$temiz = RMA_Ekstra::listeleri_kaydet( wp_unslash( $_POST['rma_ekstra_listeleri'] ?? array() ) );
		$this->bump_cache_version();

		wp_safe_redirect(
			$this->admin_page_url(
				'qrms-rm-secenekler',
				array(
					'sekme'       => 'ekstra',
					'secenek_msg' => 'ekstra_' . count( $temiz ),
				),
				'rma-ekstra-listeleri'
			)
		);
		exit;
	}

	/**
	 * Özel rozet tanımlarını kaydeder (admin-post).
	 *
	 * @return void
	 */
	public function handle_ozel_rozet_save() {
		$yetki = class_exists( 'QRMS_Admin' ) ? QRMS_Admin::CAPABILITY : 'manage_options';
		if ( ! current_user_can( $yetki ) ) {
			wp_die( 'Yetkiniz yok.', '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'rma_ozel_rozet' );

		$temiz = RMA_Ozel_Rozet::kaydet( wp_unslash( $_POST['rma_ozel_rozetler'] ?? array() ) );
		$this->bump_cache_version();

		wp_safe_redirect(
			$this->admin_page_url(
				'qrms-rm-secenekler',
				array(
					'sekme'       => 'rozet',
					'secenek_msg' => 'rozet_' . count( $temiz ),
				),
				'rma-ozel-rozetler'
			)
		);
		exit;
	}
}
