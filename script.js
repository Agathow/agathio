const words = [
    "Hello",
    "Halo",
    "こんにちは",
    "안녕하세요",
    "你好",
    "Bonjour",
    "Guten Tag",
    "Hola"
];

let wordIndex = 0;
let charIndex = 0;
let isDeleting = false;
const typingElement = document.getElementById("hello-typing");

function typeEffect() {
    const currentWord = words[wordIndex];
    const shownText = currentWord.substring(0, charIndex);

    typingElement.textContent = shownText;

    if (!isDeleting && charIndex < currentWord.length) {
        charIndex++;
        setTimeout(typeEffect, 200);  // lebih lambat pas ngetik
    } 
    else if (isDeleting && charIndex > 0) {
        charIndex--;
        setTimeout(typeEffect, 130);  // hapus lebih pelan
    } 
    else {
        if (!isDeleting) {
            setTimeout(() => isDeleting = true, 1400);  // delay sebelum delete
        } else {
            isDeleting = false;
            wordIndex = (wordIndex + 1) % words.length;
        }
        setTimeout(typeEffect, 180);
    }
}

window.onload = () => {
    const photo = document.getElementById("hero-photo");
    if (photo) {
        setTimeout(() => {
            photo.classList.add("show");
        }, 500);
    }
};

typeEffect();
