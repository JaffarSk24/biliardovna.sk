// Events Carousel
document.addEventListener('DOMContentLoaded', function() {
    const carousel = document.querySelector('.events-carousel');
    if (!carousel) return;

    const grid = carousel.querySelector('.events-grid');
    const prevBtn = carousel.querySelector('.carousel-prev');
    const nextBtn = carousel.querySelector('.carousel-next');
    const cards = grid.querySelectorAll('.event-card');
    
    if (cards.length <= 3) {
        if (prevBtn) prevBtn.style.display = 'none';
        if (nextBtn) nextBtn.style.display = 'none';
        return;
    }

    let currentIndex = 0;
    const cardsToShow = 3;
    const maxIndex = cards.length - cardsToShow;

    function updateCarousel() {
        const cardWidth = cards[0].offsetWidth;
        const gap = 30;
        const offset = currentIndex * (cardWidth + gap);
        grid.style.transform = `translateX(-${offset}px)`;
    }

    prevBtn.addEventListener('click', () => {
        if (currentIndex === 0) {
            currentIndex = maxIndex;
        } else {
            currentIndex--;
        }
        updateCarousel();
    });

    nextBtn.addEventListener('click', () => {
        if (currentIndex >= maxIndex) {
            currentIndex = 0;
        } else {
            currentIndex++;
        }
        updateCarousel();
    });

    window.addEventListener('resize', updateCarousel);
    updateCarousel();
});