document.addEventListener('DOMContentLoaded', () => {
    const filterButtons = document.querySelectorAll('.filter-btn');
    const projectCards = document.querySelectorAll('.project-card');

    

    filterButtons.forEach(button => {
        button.addEventListener('click', () => {
            // 1. Ubah tampilan tombol yang aktif
            filterButtons.forEach(btn => btn.classList.remove('active'));
            button.classList.add('active');

            // 2. Ambil nilai kategori dari atribut data-filter
            const filterValue = button.getAttribute('data-filter');


            // 3. Filter kartu proyek
            projectCards.forEach(card => {
                const projectCategory = card.getAttribute('data-category');
                
                if (filterValue === 'all' || filterValue === projectCategory) {
                    card.style.display = 'flex'; // Tampilkan jika cocok atau 'all'
                    card.style.animation = 'fadeIn 0.5s ease forwards';
                } else {
                    card.style.display = 'none'; // Sembunyikan jika tidak cocok
                }
            });
        });
    });
});

// Fungsi ini tetap sama, hanya memastikan id terpanggil dengan benar
function animateNumber(id, start, end, duration) {
    const obj = document.getElementById(id);
    if(!obj) return; // Guard clause
    
    let startTimestamp = null;
    const step = (timestamp) => {
        if (!startTimestamp) startTimestamp = timestamp;
        const progress = Math.min((timestamp - startTimestamp) / duration, 1);
        obj.innerHTML = Math.floor(progress * (end - start) + start) + "+";
        if (progress < 1) {
            window.requestAnimationFrame(step);
        }
    };
    window.requestAnimationFrame(step);
}

document.addEventListener('DOMContentLoaded', () => {
    animateNumber("count-number", 0, 3, 2000); 
});