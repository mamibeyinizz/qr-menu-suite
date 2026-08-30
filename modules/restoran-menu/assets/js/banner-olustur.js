/**
 * Kampanya Banner — "Toplu Kampanya Görseli Oluştur" aracı (3. adım).
 *
 * NEDEN TARAYICI TARAFI: kod tabanında hiç GD/Imagick çizimi yok; sunucuda
 * metin basmak paketlenmiş bir TTF + FreeType desteği isterdi ve paylaşımlı
 * hostinglerde bu garanti değil. Canvas tarayıcının kendi font motorunu
 * kullanır, kullanıcı sonucu birebir görür; sunucunun tek işi PNG'yi
 * doğrulayıp medya kütüphanesine yazmak (bkz. ajax_banner_gorsel_olustur).
 *
 * Renkler ve oranlar PHP'den data-* olarak gelir — burada sabit renk yoktur,
 * şablon eklemek için yalnızca banner_sablonlari() güncellenir.
 */
(function () {
    'use strict';

    var kok = document.getElementById('qmo-banner-olustur');
    if (!kok) return;

    var canvas = document.getElementById('qmo-banner-uret-canvas');
    if (!canvas || !canvas.getContext) return;

    var ctx     = canvas.getContext('2d');
    var $baslik = document.getElementById('qmo-banner-uret-baslik');
    var $alt    = document.getElementById('qmo-banner-uret-alt');
    var $oran   = document.getElementById('qmo-banner-uret-oran');
    var $btn    = document.getElementById('qmo-banner-uret-btn');
    var $sonuc  = document.getElementById('qmo-banner-uret-sonuc');

    var BASLIK_FONT = "'Playfair Display', Georgia, serif";
    var ALT_FONT    = "'Manrope', system-ui, sans-serif";

    function sablon() {
        var secili = kok.querySelector('input[name="qmo_banner_sablon"]:checked');
        if (!secili) return null;
        return {
            bgBas:  secili.getAttribute('data-bg-bas'),
            bgSon:  secili.getAttribute('data-bg-son'),
            baslik: secili.getAttribute('data-baslik-renk'),
            alt:    secili.getAttribute('data-alt-renk'),
            cizgi:  secili.getAttribute('data-cizgi-renk')
        };
    }

    function boyut() {
        var secili = $oran && $oran.options[$oran.selectedIndex];
        var g = secili ? parseInt(secili.getAttribute('data-genislik'), 10) : 0;
        var y = secili ? parseInt(secili.getAttribute('data-yukseklik'), 10) : 0;
        return { g: g || 1600, y: y || 900 };
    }

    /**
     * Metni verilen genişliğe sığana kadar puntoyu küçültür.
     * Tek satır çizildiği için sarma (wrap) yoktur — sığdırma yeterli.
     */
    function sigdir(metin, font, punto, enFazlaGenislik, enAzPunto) {
        var p = punto;
        while (p > enAzPunto) {
            ctx.font = '600 ' + p + 'px ' + font;
            if (ctx.measureText(metin).width <= enFazlaGenislik) break;
            p -= 2;
        }
        ctx.font = '600 ' + p + 'px ' + font;
        return p;
    }

    function ciz() {
        var s = sablon();
        if (!s) return;

        var b = boyut();
        canvas.width  = b.g;
        canvas.height = b.y;

        var baslikMetni = ($baslik ? $baslik.value : '').trim();
        var altMetni    = ($alt ? $alt.value : '').trim();

        // Arka plan: köşegen degrade.
        var grad = ctx.createLinearGradient(0, 0, b.g, b.y);
        grad.addColorStop(0, s.bgBas);
        grad.addColorStop(1, s.bgSon);
        ctx.fillStyle = grad;
        ctx.fillRect(0, 0, b.g, b.y);

        // Sağ üstte yumuşak bir ışık — düz zemini kırar.
        var isik = ctx.createRadialGradient(b.g * 0.78, b.y * 0.18, 0, b.g * 0.78, b.y * 0.18, b.g * 0.55);
        isik.addColorStop(0, 'rgba(255,255,255,0.13)');
        isik.addColorStop(1, 'rgba(255,255,255,0)');
        ctx.fillStyle = isik;
        ctx.fillRect(0, 0, b.g, b.y);

        // İnce çerçeve.
        ctx.strokeStyle = s.cizgi;
        ctx.globalAlpha = 0.35;
        ctx.lineWidth = Math.max(2, Math.round(b.y * 0.006));
        var pay = Math.round(b.y * 0.05);
        ctx.strokeRect(pay, pay, b.g - pay * 2, b.y - pay * 2);
        ctx.globalAlpha = 1;

        var merkezX = b.g / 2;
        var enFazla = b.g * 0.76;

        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';

        // Başlığın üstündeki vurgu çizgisi.
        var cizgiY = altMetni ? b.y * 0.32 : b.y * 0.36;
        ctx.fillStyle = s.cizgi;
        ctx.fillRect(merkezX - b.g * 0.05, cizgiY, b.g * 0.1, Math.max(2, Math.round(b.y * 0.008)));

        if (baslikMetni) {
            ctx.fillStyle = s.baslik;
            sigdir(baslikMetni, BASLIK_FONT, Math.round(b.y * 0.17), enFazla, 18);
            ctx.fillText(baslikMetni, merkezX, altMetni ? b.y * 0.47 : b.y * 0.52);
        }

        if (altMetni) {
            ctx.fillStyle = s.alt;
            sigdir(altMetni, ALT_FONT, Math.round(b.y * 0.072), enFazla, 12);
            ctx.fillText(altMetni, merkezX, b.y * 0.66);
        }
    }

    /** Fontlar inince yeniden çiz — aksi hâlde ilk çizim yedek fontla olur. */
    function cizVeBekle() {
        ciz();
        if (document.fonts && document.fonts.ready && document.fonts.ready.then) {
            document.fonts.ready.then(ciz);
        }
    }

    function mesaj(tur, metin, ekHtml) {
        if (!$sonuc) return;
        $sonuc.innerHTML = '';
        var kutu = document.createElement('div');
        kutu.className = 'notice notice-' + tur + ' inline';
        var p = document.createElement('p');
        p.textContent = metin;
        kutu.appendChild(p);
        if (ekHtml) {
            var ek = document.createElement('p');
            ek.innerHTML = ekHtml;
            kutu.appendChild(ek);
        }
        $sonuc.appendChild(kutu);
    }

    function olustur() {
        var baslikMetni = ($baslik ? $baslik.value : '').trim();

        if (!baslikMetni) {
            mesaj('error', 'Önce bir başlık yazın.');
            if ($baslik) $baslik.focus();
            return;
        }

        ciz();

        var veri;
        try {
            veri = canvas.toDataURL('image/png');
        } catch (e) {
            mesaj('error', 'Görsel üretilemedi. Tarayıcınız canvas dışa aktarmayı engelliyor olabilir.');
            return;
        }

        var gonder = new FormData();
        gonder.append('action', kok.getAttribute('data-ajax-action'));
        gonder.append('nonce', kok.getAttribute('data-nonce'));
        gonder.append('baslik', baslikMetni);
        gonder.append('gorsel', veri);

        $btn.disabled = true;
        var eskiMetin = $btn.textContent;
        $btn.textContent = 'Oluşturuluyor…';
        mesaj('info', 'Görsel yükleniyor…');

        window.fetch(kok.getAttribute('data-ajax-url'), {
            method: 'POST',
            credentials: 'same-origin',
            body: gonder
        }).then(function (yanit) {
            return yanit.json();
        }).then(function (json) {
            if (json && json.success) {
                var ek = '';
                if (json.data.duzenle) {
                    ek += '<a class="button" href="' + json.data.duzenle + '">Kampanyayı Düzenle</a> ';
                }
                if (json.data.liste) {
                    ek += '<a class="button" href="' + json.data.liste + '">Kampanya Listesine Dön</a>';
                }
                mesaj('success', json.data.message, ek);
            } else {
                mesaj('error', (json && json.data && json.data.message) ? json.data.message : 'Kampanya oluşturulamadı.');
            }
        }).catch(function () {
            mesaj('error', 'Sunucuya ulaşılamadı. Lütfen tekrar deneyin.');
        }).then(function () {
            $btn.disabled = false;
            $btn.textContent = eskiMetin;
        });
    }

    ['input', 'change'].forEach(function (olay) {
        kok.addEventListener(olay, function (e) {
            if (e.target.closest && e.target.closest('#qmo-banner-olustur')) ciz();
        });
    });

    if ($btn) $btn.addEventListener('click', olustur);

    cizVeBekle();
})();
