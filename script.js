/* --- 1. INISIALISASI HALAMAN --- */
window.onload = () => {
    initTimeMachine(); // Menjalankan hitungan umur/hari
    initParticles();   // Menjalankan efek gelembung romantis
};

// Memastikan partikel tetap muncul jika ada perubahan DOM
window.addEventListener('DOMContentLoaded', initParticles);


/* --- 2. SISTEM PASSCODE & NAVIGASI --- */
const unlockBtn = document.getElementById("unlockBtn");

if (unlockBtn) {
    unlockBtn.addEventListener('click', function() {
        const input = document.getElementById('passInput').value;
        const correctPass = "771149"; // Kode rahasia 6 angka Anda

        if (input === correctPass) {
            // Efek transisi kartu sebelum pindah halaman
            const card = document.querySelector(".glass-card");
            if (card) {
                card.style.opacity = "0";
                card.style.transform = "scale(0.95)";
                card.style.transition = "0.5s";
            }
            
            // Simpan status musik agar mulai otomatis di halaman berikutnya
            localStorage.setItem('playMusic', 'true');
            
            setTimeout(() => {
                window.location.href = "main.html";
            }, 500);
        } else {
            // Tampilkan pesan error menggunakan modal agar tetap estetik
            const modalBody = document.getElementById("modal-body");
            if (modalBody) {
                modalBody.innerHTML = "<h3>Salah sayang...</h3><p>Ayo ingat lagi, kodenya ada di petunjuk tanda tanya di atas! ❤️</p>";
                document.getElementById("hintModal").style.display = "flex";
            }
        }
    });
}


/* --- 3. SISTEM PETUNJUK (HINT) MODAL --- */
function showHint(step) {
    const modal = document.getElementById("hintModal");
    const modalBody = document.getElementById("modal-body");
    let message = "";
    
    switch(step) {
        case 1: message = "<h3>Hint #1</h3><p>Tanggal lahir orang tercantik yang lagi baca ini</p>"; break;
        case 2: message = "<h3>Hint #2</h3><p>Bulan pertama kali kita ketemu</p>"; break;
        case 3: message = "<h3>Hint #2</h3><p>Berapa kali aku transit kalau ke BINUS</p>"; break;
        case 4: message = "<h3>Hint #3</h3><p>Sudah di kepala?</p>"; break;
        case 5: message = "<h3>Hint #5</h3><p>3 Hari lalu tanggal berapa?</p>"; break;
        case 6: message = "<h3>Hint #6</h3><p>TJ Nomor?</p>"; break;
    }

    if (modal && modalBody) {
        modalBody.innerHTML = message;
        // Menggunakan 'flex' agar CSS justify-content & align-items bekerja untuk memusatkan modal
        modal.style.display = "flex"; 
    }
}

function closeModal() {
    const modal = document.getElementById("hintModal");
    if (modal) {
        modal.style.display = "none";
    }
}

// Menutup modal jika user klik di area luar kotak (overlay)
window.onclick = function(event) {
    const modal = document.getElementById("hintModal");
    if (event.target == modal) {
        closeModal();
    }
};


/* --- 4. PEMUTAR MUSIK SEAMLESS (ANTAR HALAMAN) --- */
document.addEventListener("DOMContentLoaded", function() {
    const bgMusic = document.getElementById('bgMusic');
    if (!bgMusic) return;

    const savedTime = localStorage.getItem('musicCurrentTime');
    if (savedTime) {
        bgMusic.currentTime = parseFloat(savedTime);
    }

    bgMusic.addEventListener('timeupdate', function() {
        localStorage.setItem('musicCurrentTime', bgMusic.currentTime);
    });

    const playMusic = () => {
        bgMusic.play().then(() => {
            document.removeEventListener('click', playMusic);
            window.removeEventListener('scroll', playMusic);
            document.removeEventListener('touchstart', playMusic);
        }).catch(error => {
            console.log("Menunggu interaksi user untuk memutar musik...");
        });
    };

    document.addEventListener('click', playMusic);
    window.addEventListener('scroll', playMusic);
    document.addEventListener('touchstart', playMusic);

    playMusic();
});


/* --- 5. MESIN WAKTU (UMUR & LIVE COUNTER) --- */
function initTimeMachine() {
    const ageEl = document.getElementById("age-number");
    const lifeEl = document.getElementById("life-counter");
    if (!ageEl) return;

    let count = 0;
    const target = 18;
    const interval = setInterval(() => {
        count++;
        ageEl.innerText = count;
        if (count === target) clearInterval(interval);
    }, 60);

    const birthDate = new Date("March 7, 2008 00:00:00").getTime();
    setInterval(() => {
        const now = new Date().getTime();
        const diff = now - birthDate;
        
        const days = Math.floor(diff / (1000 * 60 * 60 * 24));
        const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
        const mins = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
        const secs = Math.floor((diff % (1000 * 60)) / 1000);
        
        if (lifeEl) {
            lifeEl.innerHTML = `<b>${days.toLocaleString()}</b> days <br> <span>${hours}h : ${mins}m : ${secs}s</span>`;
        }
    }, 1000);
}


/* --- 6. EFEK PARTIKEL ROMANTIS --- */
function initParticles() {
    const bg = document.getElementById("bg-anim");
    if (!bg) return;

    bg.innerHTML = ''; 

    for (let i = 0; i < 30; i++) {
        const p = document.createElement("div");
        p.className = "heart-particle";
        
        const size = Math.random() * 10 + 5 + "px";
        p.style.width = size;
        p.style.height = size;
        p.style.left = Math.random() * 100 + "vw";
        p.style.animationDuration = Math.random() * 5 + 10 + "s";
        p.style.animationDelay = Math.random() * 10 + "s";
        
        bg.appendChild(p);
    }
}