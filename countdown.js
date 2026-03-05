document.addEventListener("DOMContentLoaded", function() {
    const bgMusic = document.getElementById('bgMusic');
    const timerElem = document.getElementById("countdown-timer");
    const btn = document.getElementById("btn-unlock");
    
    // Target: 7 Maret 2026 pukul 00:00:00
    const targetDate = new Date("March 7, 2026 00:00:00").getTime();
    let serverTimeOffset = 0;

    // --- LOGIKA MUSIK KHUSUS INDEX ---
    const playIndexMusic = () => {
        if (bgMusic) {
            // Set volume sedang agar tidak mengagetkan
            bgMusic.volume = 0.5; 
            bgMusic.play().then(() => {
                // Hapus pemicu jika sudah jalan
                ["click", "scroll", "touchstart"].forEach(ev => 
                    document.removeEventListener(ev, playIndexMusic)
                );
            }).catch(err => console.log("Menunggu interaksi..."));
        }
    };

    // Pemicu musik saat user pertama kali menyentuh layar
    ["click", "scroll", "touchstart"].forEach(ev => 
        document.addEventListener(ev, playIndexMusic)
    );

    // PENTING: Hapus memori waktu lagu utama agar saat pindah halaman
    // lagu "SONG.mp3" nanti benar-benar mulai dari awal (00:00)
    localStorage.removeItem('musicCurrentTime');

    // --- LOGIKA HITUNG MUNDUR & ANTI-CURANG ---
    async function syncTime() {
        try {
            const response = await fetch('https://worldtimeapi.org/api/timezone/Etc/UTC');
            const data = await response.json();
            const serverNow = new Date(data.utc_datetime).getTime();
            const localNow = new Date().getTime();
            serverTimeOffset = serverNow - localNow;
        } catch (error) {
            console.error("Gagal sinkron server, menggunakan waktu lokal.");
        }
    }

    function updateTimer() {
        const now = new Date().getTime() + serverTimeOffset;
        const distance = targetDate - now;

        if (!timerElem) return;

        if (distance > 0) {
            const days = Math.floor(distance / (1000 * 60 * 60 * 24));
            const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
            const seconds = Math.floor((distance % (1000 * 60)) / 1000);

            const d = days.toString().padStart(2, '0');
            const h = hours.toString().padStart(2, '0');
            const m = minutes.toString().padStart(2, '0');
            const s = seconds.toString().padStart(2, '0');

            timerElem.innerHTML = `${d}d : ${h}h : ${m}m : ${s}s`;
        } else {
            timerElem.innerHTML = "IT'S TIME! ✨";
            if (btn) {
                btn.classList.remove("disabled");
                btn.innerText = "Unlock Memories →";
                btn.style.pointerEvents = "auto";
                btn.style.opacity = "1";
            }
        }
    }

    syncTime().then(() => {
        setInterval(updateTimer, 1000);
        updateTimer();
    });
});