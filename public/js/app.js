/**
 * Biliardovna.sk - Public JavaScript
 */

// Auto-detect and cache language preference
(function() {
    const supportedLanguages = ['sk', 'en', 'ru', 'uk', 'de'];
    const currentPath = window.location.pathname;
    const pathLang = currentPath.split('/')[1];
    
    // Determine current page language
    let currentLang = 'sk';
    if (supportedLanguages.includes(pathLang)) {
        currentLang = pathLang;
    }
    
    // Get saved language
    const savedLang = localStorage.getItem('preferred_language');
    
    // First visit - no saved language
    if (!savedLang) {
        const browserLang = (navigator.language || navigator.languages[0]).split('-')[0];
        const detectedLang = supportedLanguages.includes(browserLang) ? browserLang : 'sk';
        
        localStorage.setItem('preferred_language', detectedLang);
        
        if (detectedLang !== currentLang) {
            if (detectedLang === 'sk') {
                window.location.href = '/';
            } else {
                window.location.href = '/' + detectedLang + currentPath;
            }
        }
        return;
    }
    
    // User has visited before - save current choice
    localStorage.setItem('preferred_language', currentLang);
    
})();

// Language Switcher
document.addEventListener('DOMContentLoaded', function() {
    const langSwitcher = document.getElementById('languageSwitcher');
    const langToggle = document.getElementById('langToggle');
    
    if (langToggle && langSwitcher) {
        langToggle.addEventListener('click', function(e) {
            e.stopPropagation();
            langSwitcher.classList.toggle('active');
        });
        
        document.addEventListener('click', function(e) {
            if (!langSwitcher.contains(e.target)) {
                langSwitcher.classList.remove('active');
            }
        });
    }
});

// Mobile Menu
document.addEventListener('DOMContentLoaded', function() {
    const menuToggle = document.getElementById('mobileMenuToggle');
    const mainNav = document.querySelector('.main-nav');
    
    if (!menuToggle || !mainNav) return;
    
    const overlay = document.createElement('div');
    overlay.className = 'mobile-overlay';
    document.body.appendChild(overlay);
    
    function openMenu() {
        menuToggle.classList.add('active');
        mainNav.classList.add('active');
        overlay.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
    
    function closeMenu() {
        menuToggle.classList.remove('active');
        mainNav.classList.remove('active');
        overlay.classList.remove('active');
        document.body.style.overflow = '';
    }
    
    menuToggle.addEventListener('click', function() {
        if (mainNav.classList.contains('active')) {
            closeMenu();
        } else {
            openMenu();
        }
    });
    
    overlay.addEventListener('click', closeMenu);
    
    mainNav.querySelectorAll('a').forEach(link => {
        link.addEventListener('click', closeMenu);
    });
});

// Scroll Animations

document.addEventListener('DOMContentLoaded', function() {
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    };
    
    const observer = new IntersectionObserver(function(entries) {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('animate-in');
                observer.unobserve(entry.target);
            }
        });
    }, observerOptions);
    
    document.querySelectorAll('.service-item, .quality-grid, .gallery-item, .event-card').forEach(el => {
        el.classList.add('animate-element');
        observer.observe(el);
    });
});

// Smooth Scroll
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function(e) {
            const href = this.getAttribute('href');
            if (href === '#') return;
            
            e.preventDefault();
            const target = document.querySelector(href);
            if (target) {
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });
});

// Gallery Lightbox
document.addEventListener('DOMContentLoaded', function() {
    const galleryItems = document.querySelectorAll('.gallery-item');
    
    if (galleryItems.length === 0) return;
    
    const lightbox = document.createElement('div');
    lightbox.className = 'lightbox';
    lightbox.innerHTML = `
        <div class="lightbox-content">
            <span class="lightbox-close">&times;</span>
            <span class="lightbox-prev">&#10094;</span>
            <span class="lightbox-next">&#10095;</span>
            <img src="" alt="Gallery Image">
            <div class="lightbox-counter"></div>
        </div>
    `;
    document.body.appendChild(lightbox);
    
    const lightboxImg = lightbox.querySelector('img');
    const closeBtn = lightbox.querySelector('.lightbox-close');
    const prevBtn = lightbox.querySelector('.lightbox-prev');
    const nextBtn = lightbox.querySelector('.lightbox-next');
    const counter = lightbox.querySelector('.lightbox-counter');
    
    let currentIndex = 0;
    const images = Array.from(galleryItems).map(item => item.querySelector('img').src);
    
    function openLightbox(index) {
        currentIndex = index;
        lightboxImg.src = images[currentIndex];
        counter.textContent = `${currentIndex + 1} / ${images.length}`;
        lightbox.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
    
    function closeLightbox() {
        lightbox.classList.remove('active');
        document.body.style.overflow = '';
    }
    
    function showNext() {
        currentIndex = (currentIndex + 1) % images.length;
        lightboxImg.src = images[currentIndex];
        counter.textContent = `${currentIndex + 1} / ${images.length}`;
    }
    
    function showPrev() {
        currentIndex = (currentIndex - 1 + images.length) % images.length;
        lightboxImg.src = images[currentIndex];
        counter.textContent = `${currentIndex + 1} / ${images.length}`;
    }
    
    galleryItems.forEach((item, index) => {
        item.addEventListener('click', () => openLightbox(index));
    });
    
    closeBtn.addEventListener('click', closeLightbox);
    nextBtn.addEventListener('click', showNext);
    prevBtn.addEventListener('click', showPrev);
    
    lightbox.addEventListener('click', function(e) {
        if (e.target === lightbox) closeLightbox();
    });
    
    document.addEventListener('keydown', function(e) {
        if (!lightbox.classList.contains('active')) return;
        
        if (e.key === 'Escape') closeLightbox();
        if (e.key === 'ArrowRight') showNext();
        if (e.key === 'ArrowLeft') showPrev();
    });
});

// Services Mobile Carousel (CSS-only approach)
document.addEventListener('DOMContentLoaded', function() {
    if (window.innerWidth <= 768) {
        const servicesList = document.querySelector('.services-list');
        if (servicesList) {
            const services = Array.from(servicesList.querySelectorAll('.service-item'));
            if (services.length > 0) {
                servicesList.innerHTML = '';
                
                const wrapper = document.createElement('div');
                wrapper.className = 'services-carousel-wrapper';
                
                const track = document.createElement('div');
                track.className = 'services-carousel-track';
                
                services.forEach(service => track.appendChild(service));
                wrapper.appendChild(track);
                servicesList.appendChild(wrapper);
                
                const dotsContainer = document.createElement('div');
                dotsContainer.className = 'services-carousel-dots';
                
                for (let i = 0; i < services.length; i++) {
                    const dot = document.createElement('button');
                    dot.className = 'carousel-dot' + (i === 0 ? ' active' : '');
                    dot.addEventListener('click', () => goToSlide(i));
                    dotsContainer.appendChild(dot);
                }
                servicesList.appendChild(dotsContainer);
                
                let currentSlide = 0;
                const dots = dotsContainer.querySelectorAll('.carousel-dot');
                
                function goToSlide(index) {
                    currentSlide = index;
                    track.style.transform = `translateX(-${index * 300}px)`;
                    dots.forEach((d, i) => d.classList.toggle('active', i === index));
                }
                
                let touchStartX = 0;
                let touchStartY = 0;
                
                track.addEventListener('touchstart', (e) => {
                    touchStartX = e.touches[0].clientX;
                    touchStartY = e.touches[0].clientY;
                }, { passive: true });
                
                track.addEventListener('touchend', (e) => {
                    const touchEndX = e.changedTouches[0].clientX;
                    const touchEndY = e.changedTouches[0].clientY;
                    const diffX = touchStartX - touchEndX;
                    const diffY = Math.abs(touchStartY - touchEndY);
                    
                    if (Math.abs(diffX) > 50 && diffY < 50) {
                        if (diffX > 0 && currentSlide < services.length - 1) {
                            goToSlide(currentSlide + 1);
                        } else if (diffX < 0 && currentSlide > 0) {
                            goToSlide(currentSlide - 1);
                        }
                    }
                }, { passive: true });
            }
        }
    }
});

// Gallery Mobile Progress
document.addEventListener('DOMContentLoaded', function() {
    if (window.innerWidth <= 768) {
        const galleryGrid = document.querySelector('.gallery-grid');
        if (!galleryGrid) return;
        
        const gallerySection = document.querySelector('.gallery-section .container');
        
        const progressContainer = document.createElement('div');
        progressContainer.className = 'gallery-progress';
        const progressBar = document.createElement('div');
        progressBar.className = 'gallery-progress-bar';
        progressContainer.appendChild(progressBar);
        gallerySection.appendChild(progressContainer);
        
        let scrollTimeout;
        function updateProgress() {
            const scrollLeft = galleryGrid.scrollLeft;
            const scrollWidth = galleryGrid.scrollWidth - galleryGrid.clientWidth;
            const progress = scrollWidth > 0 ? (scrollLeft / scrollWidth) * 100 : 0;
            progressBar.style.width = `${Math.min(progress, 100)}%`;
        }
        
        galleryGrid.addEventListener('scroll', function() {
            clearTimeout(scrollTimeout);
            scrollTimeout = setTimeout(updateProgress, 10);
        }, { passive: true });
        
        updateProgress();
    }
});

document.addEventListener('DOMContentLoaded', () => {
  const header = document.querySelector('header');
  if (!header) return;

  const mq = window.matchMedia('(max-width: 768px)');
  let enabled = false;
  let lastY = window.pageYOffset || 0;

  function applyHeaderHeight() {
    const h = Math.round(header.getBoundingClientRect().height || 64);
    document.documentElement.style.setProperty('--mobile-header-h', h + 'px');
    document.body.classList.add('with-fixed-header');
  }

  function onScroll() {
    const y = window.pageYOffset || document.documentElement.scrollTop || 0;
    const dy = y - lastY;
    lastY = y;

    if (y <= 0) { header.classList.remove('header--hidden'); return; }
    if (Math.abs(dy) < 6) return;
    if (dy > 0) header.classList.add('header--hidden');  // вниз — прячем
    else header.classList.remove('header--hidden');      // вверх — показываем
  }

  function enable() {
    if (enabled) return;
    enabled = true;
    applyHeaderHeight();
    window.addEventListener('resize', applyHeaderHeight);
    window.addEventListener('orientationchange', applyHeaderHeight);
    window.addEventListener('scroll', onScroll, { passive: true });
  }

  function disable() {
    if (!enabled) return;
    enabled = false;
    header.classList.remove('header--hidden');
    document.body.classList.remove('with-fixed-header');
    document.documentElement.style.removeProperty('--mobile-header-h');
    window.removeEventListener('resize', applyHeaderHeight);
    window.removeEventListener('orientationchange', applyHeaderHeight);
    window.removeEventListener('scroll', onScroll);
  }

  if (mq.matches) enable();
  (mq.addEventListener ? mq.addEventListener('change', e => e.matches ? enable() : disable())
                       : mq.addListener(e => e.matches ? enable() : disable()));

  // При открытом мобильном меню не прячем хедер
  const menuToggle = document.getElementById('mobileMenuToggle');
  const mainNav = document.querySelector('.main-nav');
  if (menuToggle && mainNav) {
    const obs = new MutationObserver(() => {
      if (mainNav.classList.contains('active') || menuToggle.classList.contains('active')) {
        header.classList.remove('header--hidden');
      }
    });
    obs.observe(mainNav, { attributes: true, attributeFilter: ['class'] });
  }
});

console.log('Biliardovna.sk loaded');