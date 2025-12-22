// booking-popup.js
document.addEventListener('DOMContentLoaded', function() {

    const i18nEl = document.getElementById('booking-i18n');
    const tr = (key, fallback = '') => {
      if (!i18nEl) return fallback || key;
      const kebab = key.replace(/_/g, '-');
      const camel = kebab.replace(/-([a-z])/g, (_, c) => c.toUpperCase());
      const ds = i18nEl.dataset ? i18nEl.dataset[camel] : null;
      if (ds !== undefined && ds !== null && ds !== '') return ds;
      const attr = i18nEl.getAttribute('data-' + kebab);
      if (attr !== null && attr !== '') return attr;
      return fallback || key;
    };

    const popup = document.getElementById('bookingPopup') || document.querySelector('.booking-popup');
    const form = document.getElementById('bookingPopupForm');
    if (!popup || !form) return;

    const popupClose = document.getElementById('popupClose');
    const bookButtons = document.querySelectorAll('.btn-book-service');

    // Coupon
    const couponInput = document.getElementById('popup-coupon');
    const couponCheckBtn = document.getElementById('coupon-check-btn');
    const couponMessage = document.getElementById('coupon-message');
    let appliedCoupon = null;

    // Hidden inputs
    const dateInput = document.getElementById('popup-date');
    const serviceIdInput = document.getElementById('popup-service-id');
    const resourceIdInput = document.getElementById('popup-resource-id');
    const startTimeInput = document.getElementById('popup-start-time');
    const endTimeInput = document.getElementById('popup-end-time');
    const slotCountInput = document.getElementById('popup-slot-count');

    // Sections and controls
    const resourcesSection = document.getElementById('resources-section');
    const detailsSection = document.getElementById('details-section');
    const resourcesGrid = document.getElementById('resources-grid');
    const submitBtn = form.querySelector('button[type="submit"]');
    const actionsContainer = form.querySelector('.popup-actions');

    // Success popup
    const successPopup = document.getElementById('successPopup');
    const successClose = document.getElementById('successClose');
    const bookingNumberSpan = document.getElementById('bookingNumber');
    const successWaitMessageEl = document.getElementById('successWaitMessage');

    let currentServiceId = null;
    let selectedResource = null;
    let selectedSlot = null;
    let selectedSlots = [];
    let multiSlotHintShown = false;

    const ICON_IMG_PATH = '/public/images/piramide-billard.png';
    const iconMap = { 1: 'icon-billiard', 2: 'icon-billiard', 3: 'icon-darts', 4: 'icon-football' };

    function t(key, fallback = '') {
        return (window.bookingTranslations && window.bookingTranslations[key]) || fallback;
    }

    // Helpers: eligibility by count (supports {min/max} and {start/end})
    
    // Allow only eligible dates (e.g., weekdays-only deals)
    function isDateAllowed(dateStr) {
      if (!dateStr) return false;
      if (window.dealDateRestriction === 'weekdays') {
        const [y, m, d] = dateStr.split('-').map(Number); // local date (no TZ pitfalls)
        const dt = new Date(y, m - 1, d);
        const dow = dt.getDay(); // 0 Sun, 6 Sat
        return dow !== 0 && dow !== 6;
      }
      return true;
    }

    function isSlotCountEligible(slots, guard) {
        if (!guard) return true;
        const min = (guard.min != null) ? parseInt(guard.min, 10)
                  : (guard.start != null) ? parseInt(guard.start, 10) : 0;
        const max = (guard.max != null) ? parseInt(guard.max, 10)
                  : (guard.end != null) ? parseInt(guard.end, 10) : null;
        const cnt = slots.length;
        return (min === 0 || cnt >= min) && (max == null || cnt <= max);
    }

    function updateSlotCountHidden() {
        if (slotCountInput) slotCountInput.value = String(selectedSlots.length);
    }

    function updateVisibilityForSlots() {
        const guard = window.dealSlotsRestriction || null;
        const eligible = isSlotCountEligible(selectedSlots, guard);
        // details block
        if (detailsSection) detailsSection.style.display = eligible && selectedSlots.length > 0 ? 'block' : 'none';
        // actions container + submit button
        if (actionsContainer) actionsContainer.style.display = eligible && selectedSlots.length > 0 ? 'flex' : 'none';
        if (submitBtn) submitBtn.style.display = eligible && selectedSlots.length > 0 ? 'block' : 'none';
    }

    // Localize date heading/label (fallback if twig missed keys)
    if (dateInput) {
        const dateHeadingEl = document.querySelector('.popup-date-block h2');
        if (dateHeadingEl) dateHeadingEl.textContent = tr('date_label', dateHeadingEl.textContent || 'Dátum návštevy');
        const dateLabelEl = document.querySelector('label[for="popup-date"]');
        if (dateLabelEl) {
            const hasStar = /\*$/.test(dateLabelEl.textContent.trim());
            dateLabelEl.textContent = tr('date_label', dateLabelEl.textContent.replace('*', '').trim() || 'Dátum návštevy');
            if (hasStar) dateLabelEl.textContent += '*';
        }
        // inline fit
        Object.assign(dateInput.style, {
            maxWidth: '260px', width: '100%', display: 'block', margin: '0 auto',
            textAlign: 'center', padding: '10px 12px', fontSize: '1rem'
        });
    }

    // "Book" buttons on pages
    bookButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const serviceItem = this.closest('.service-item');

            if (!serviceIdInput) return;
            if (!serviceItem) {
                currentServiceId = this.dataset.serviceId || '1';
            } else {
                currentServiceId = serviceItem.dataset.serviceId || '1';
                const serviceName = serviceItem.querySelector('h3')?.textContent || '';
                const serviceNameElement = document.getElementById('popup-service-name');
                if (serviceNameElement) serviceNameElement.textContent = serviceName;
            }

            serviceIdInput.value = currentServiceId;
            document.getElementById('popup-service-icon')?.setAttribute('data-service', String(currentServiceId));

            popup.classList.add('active');
            document.body.style.overflow = 'hidden';
            resetForm();
        });
    });

    // optional PNG/SVG icon renderer (kept)
    function renderServiceIcon(serviceId) {
        const iconContainer = document.getElementById('popup-service-icon');
        if (!iconContainer) return;

        const renderPng = () => {
            iconContainer.innerHTML = `
                <div class="popup-service-icon-container left" aria-hidden="true">
                    <img src="${ICON_IMG_PATH}" alt="" loading="lazy">
                </div>
                <div class="popup-service-icon-container right" aria-hidden="true">
                    <img src="${ICON_IMG_PATH}" alt="" loading="lazy">
                </div>
            `;
        };

        const renderFallbackSvg = () => {
            const id = iconMap[serviceId] || 'icon-billiard';
            iconContainer.innerHTML = `
                <div class="popup-service-icon-container left" aria-hidden="true">
                    <svg class="popup-service-icon-svg"><use href="#${id}"></use></svg>
                </div>
                <div class="popup-service-icon-container right" aria-hidden="true">
                    <svg class="popup-service-icon-svg"><use href="#${id}"></use></svg>
                </div>
            `;
        };

        const probe = new Image();
        probe.onload = renderPng;
        probe.onerror = renderFallbackSvg;
        probe.src = ICON_IMG_PATH;
    }

    function resetForm() {
        form.reset();
        if (resourcesSection) resourcesSection.style.display = 'none';
        if (detailsSection) detailsSection.style.display = 'none';
        if (resourcesGrid) resourcesGrid.innerHTML = '';
        selectedResource = null;
        selectedSlot = null;
        selectedSlots = [];
        multiSlotHintShown = false;

        if (actionsContainer) actionsContainer.style.display = 'none';
        if (submitBtn) submitBtn.style.display = 'none';
        updateSlotCountHidden();

        appliedCoupon = null;
        if (couponInput) { couponInput.value = ''; couponInput.disabled = false; couponInput.readOnly = false; }
        if (couponCheckBtn) { couponCheckBtn.textContent = '→'; couponCheckBtn.style.background = ''; couponCheckBtn.disabled = false; }
        if (couponMessage) { couponMessage.textContent = ''; couponMessage.className = 'coupon-message'; }
    }

    function closePopup() {
        popup.classList.remove('active');
        document.body.style.overflow = '';
        window.dealTimeRestriction = null;
        window.dealDateRestriction = null;
        window.dealSlotsRestriction = null;

        appliedCoupon = null;
        if (couponInput) { couponInput.value = ''; couponInput.disabled = false; couponInput.readOnly = false; }
        if (couponCheckBtn) { couponCheckBtn.textContent = '→'; couponCheckBtn.style.background = ''; couponCheckBtn.disabled = false; }
        if (couponMessage) { couponMessage.textContent = ''; couponMessage.className = 'coupon-message'; }
    }

    if (popupClose) popupClose.addEventListener('click', closePopup);
    popup.addEventListener('click', (e) => { if (e.target === popup) closePopup(); });
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && popup.classList.contains('active')) closePopup(); });

    // Date bounds and change
    if (dateInput) {
        const today = new Date();
        const maxDate = new Date(); maxDate.setDate(today.getDate() + 14);
        dateInput.setAttribute('min', today.toISOString().split('T')[0]);
        dateInput.setAttribute('max', maxDate.toISOString().split('T')[0]);

        dateInput.addEventListener('change', function() {
          const date = this.value;

          if (!isDateAllowed(date)) {
            alert(tr('deal_weekdays_only', 'Эта акция действует только в будние дни'));
            // hard reset UI so no slots remain visible
            this.value = '';
            selectedSlots = [];
            updateSlotCountHidden();
            if (resourcesGrid) resourcesGrid.innerHTML = '';
            if (resourcesSection) resourcesSection.style.display = 'none';
            if (detailsSection) detailsSection.style.display = 'none';
            if (actionsContainer) actionsContainer.style.display = 'none';
            if (submitBtn) submitBtn.style.display = 'none';
            return;
          }

          if (currentServiceId && date) {
            selectedSlots = [];
            updateSlotCountHidden();
            updateVisibilityForSlots();
            loadResourcesAndSlots(currentServiceId, date);
          }
        });

        dateInput.addEventListener('click', function(e) {
            if (e.target === this) {
                try { if (this.showPicker) this.showPicker(); } catch { this.focus(); }
            }
        });
    }

    function loadResourcesAndSlots(serviceId, date) {
      if (!resourcesGrid || !resourcesSection) return;

      // hard guard: do not show slots for disallowed dates (e.g., weekends)
      if (!isDateAllowed(date)) {
        if (resourcesGrid) resourcesGrid.innerHTML = '';
        resourcesSection.style.display = 'none';
        if (detailsSection) detailsSection.style.display = 'none';
        if (actionsContainer) actionsContainer.style.display = 'none';
        if (submitBtn) submitBtn.style.display = 'none';
        return;
      }

      resourcesGrid.innerHTML = `<p style="color: var(--text-color); text-align: center;">${tr('loading', 'Загрузка...')}</p>`;
      resourcesSection.style.display = 'block';
      if (detailsSection) detailsSection.style.display = 'none';
      if (actionsContainer) actionsContainer.style.display = 'none';
      if (submitBtn) submitBtn.style.display = 'none';

        const lang = document.documentElement.lang || 'sk';
        fetch(`/api/resources-availability?service_id=${serviceId}&date=${date}&lang=${lang}&_=${Date.now()}`)
        .then(r => r.json())
        .then(data => {
          if (data.resources && data.resources.length > 0) {
            renderResources(data.resources, serviceId);
          } else {
            resourcesGrid.innerHTML = `<p style="color: var(--text-color); text-align: center;">${tr('no_tables', 'Нет доступных столов')}</p>`;
          }
        })
        .catch(err => {
          console.error('Error loading resources:', err);
          resourcesGrid.innerHTML = `<p style="color: var(--text-color); text-align: center;">${tr('loading_error', 'Ошибка загрузки')}</p>`;
        });
    }

    function renderResources(resources, serviceId) {
      if (!resourcesGrid) return;

      resourcesGrid.innerHTML = '';
      const iconId = iconMap[serviceId] || 'icon-billiard';
      let shown = 0;

      resources.forEach(resource => {
        let slots = Array.isArray(resource.slots) ? resource.slots.slice() : [];

        // apply optional time restriction from deals
        if (window.dealTimeRestriction) {
          const r = window.dealTimeRestriction;
          slots = slots.filter(slot => {
            const slotHour = parseInt(String(slot.start_time).split(':')[0], 10);
            return slotHour >= r.start && slotHour < r.end;
          });
        }

        // skip resource if no available slots remain
        const hasAvailable = slots.some(s => !!s.available);
        if (!hasAvailable) return;

        const card = document.createElement('div');
        card.className = 'resource-card';

        const slotsHtml = slots.length
          ? slots.map(slot => `
              <div class="time-slot ${slot.available ? '' : 'disabled'}"
                   data-resource-id="${resource.id}"
                   data-start="${slot.start_time}"
                   data-end="${slot.end_time}"
                   data-price="${slot.price}">
                <span class="time-slot-time">${String(slot.start_time).substring(0,5)} - ${String(slot.end_time).substring(0,5)}</span>
                <span class="time-slot-price">${slot.price} €</span>
              </div>
            `).join('')
          : `<p style="color: var(--color-muted); font-size: 0.9rem; text-align: center;">${tr('no_slots', 'Нет слотов')}</p>`;

        card.innerHTML = `
          <div class="resource-header">
            <svg class="resource-icon"><use href="#${iconId}"></use></svg>
            <div class="resource-name">${resource.name}</div>
          </div>
          <div class="time-slots">${slotsHtml}</div>
        `;

        resourcesGrid.appendChild(card);
        shown++;
      });

      if (shown === 0) {
        resourcesGrid.innerHTML = `<p style="color: var(--text-color); text-align: center;">${tr('no_tables', 'Нет доступных столов')}</p>`;
        return;
      }

      // bind clicks only for available slots
      resourcesGrid.querySelectorAll('.time-slot:not(.disabled)').forEach(slot => {
        slot.addEventListener('click', function () { selectSlot(this); });
      });
    }

    function selectSlot(slotElement) {
        const resourceId = slotElement.dataset.resourceId;
        const startTime = slotElement.dataset.start;
        const endTime = slotElement.dataset.end;
        const price = parseFloat(slotElement.dataset.price);

        if (selectedSlots.length === 0 || selectedSlots[0].resourceId !== resourceId) {
            document.querySelectorAll('.time-slot').forEach(s => s.classList.remove('selected'));
            document.querySelectorAll('.resource-card').forEach(c => c.classList.remove('selected'));
            selectedSlots = [];
            showMultiSlotHint();
        }

        if (slotElement.classList.contains('selected')) {
            slotElement.classList.remove('selected');
            selectedSlots = selectedSlots.filter(s => s.start !== startTime);
        } else {
            slotElement.classList.add('selected');
            selectedSlots.push({ resourceId, start: startTime, end: endTime, price });
        }

        updateSlotCountHidden();

        if (selectedSlots.length > 0) {
            slotElement.closest('.resource-card')?.classList.add('selected');
            selectedSlots.sort((a, b) => a.start.localeCompare(b.start));

            if (!areConsecutive(selectedSlots)) {
                alert(tr('select_consecutive', 'Пожалуйста, выбирайте слоты подряд'));
                slotElement.classList.remove('selected');
                selectedSlots = selectedSlots.filter(s => s.start !== startTime);
                updateSlotCountHidden();
                updateVisibilityForSlots();
                return;
            }

            if (resourceIdInput) resourceIdInput.value = resourceId;
            if (startTimeInput) startTimeInput.value = selectedSlots[0].start;
            if (endTimeInput) endTimeInput.value = selectedSlots[selectedSlots.length - 1].end;

            const totalPrice = selectedSlots.reduce((sum, s) => sum + s.price, 0);
            displayTotalPrice(totalPrice);

            // visibility controlled here (supports 3+1 guard)
            updateVisibilityForSlots();

            setTimeout(() => resourcesSection?.scrollIntoView({ behavior: 'smooth', block: 'start' }), 100);
        } else {
            slotElement.closest('.resource-card')?.classList.remove('selected');
            if (detailsSection) detailsSection.style.display = 'none';
            if (actionsContainer) actionsContainer.style.display = 'none';
            if (submitBtn) submitBtn.style.display = 'none';
        }
    }

    function areConsecutive(slots) {
        for (let i = 1; i < slots.length; i++) {
            if (slots[i].start !== slots[i - 1].end) return false;
        }
        return true;
    }

    function showMultiSlotHint() {
        if (multiSlotHintShown) return;
        multiSlotHintShown = true;

        const modal = document.createElement('div');
        modal.className = 'multi-slot-hint-modal';
        modal.innerHTML = `
            <div class="multi-slot-hint-content">
                <div class="multi-slot-hint-image">
                    <img src="/public/images/many-slots.webp" alt="Multiple slots">
                </div>
                <div class="multi-slot-hint-text">
                    <p>${tr('multi_slot_hint', 'Вы можете выбрать несколько часов подряд')}</p>
                </div>
                <button class="multi-slot-hint-btn">${tr('ok', 'OK')}</button>
            </div>
        `;
        document.body.appendChild(modal);
        setTimeout(() => modal.classList.add('show'), 10);

        const closeModal = () => { modal.classList.remove('show'); setTimeout(() => modal.remove(), 300); };
        modal.querySelector('.multi-slot-hint-btn').addEventListener('click', closeModal);
        modal.addEventListener('click', (e) => { if (e.target === modal) closeModal(); });
    }

    function displayTotalPrice(price) {
        if (!detailsSection) return;
        const priceDisplay = document.createElement('div');
        priceDisplay.className = 'popup-price-display';

        if (appliedCoupon) {
            const discountedPrice = price * (1 - appliedCoupon.discount / 100);
            priceDisplay.innerHTML = `
                <strong>${tr('total', 'Итого')}:</strong>
                <span style="text-decoration: line-through; color: var(--color-muted); margin-right: 10px;">${price.toFixed(2)} €</span>
                <span style="color: var(--accent-color); font-size: 1.2em;">${discountedPrice.toFixed(2)} €</span>
                <span style="color: #4caf50; margin-left: 10px;">(-${appliedCoupon.discount}%)</span>
            `;
        } else {
            priceDisplay.innerHTML = `<strong>${tr('total', 'Итого')}:</strong> ${price.toFixed(2)} €`;
        }

        const existingPrice = detailsSection.querySelector('.popup-price-display');
        if (existingPrice) existingPrice.replaceWith(priceDisplay);
        else detailsSection.insertBefore(priceDisplay, detailsSection.firstChild);
    }

    function showSuccessPopup(bookingNumber) {
        if (bookingNumberSpan) bookingNumberSpan.textContent = bookingNumber;
        if (successWaitMessageEl) successWaitMessageEl.textContent = tr('success_wait_confirmation', '');
        closePopup();
        if (successPopup) {
            successPopup.classList.add('active');
            document.body.style.overflow = 'hidden';
        }
    }

    function closeSuccessPopup() {
        if (successPopup) successPopup.classList.remove('active');
        document.body.style.overflow = '';
    }

    if (successClose) successClose.addEventListener('click', closeSuccessPopup);
    if (successPopup) successPopup.addEventListener('click', (e) => { if (e.target === successPopup) closeSuccessPopup(); });

    // Coupons
    if (couponCheckBtn && couponInput && couponMessage) {
      const { msgValid, msgInvalid, msgError } = couponMessage.dataset;

      couponCheckBtn.addEventListener('click', async function () {
        const code = couponInput.value.trim();
        if (!code) {
          couponMessage.textContent = '';
          couponMessage.className = 'coupon-message';
          return;
        }

        couponCheckBtn.disabled = true;
        couponMessage.textContent = '...';
        couponMessage.className = 'coupon-message';

        try {
          const response = await fetch(`/api/coupon/validate?code=${encodeURIComponent(code)}`);
          const data = await response.json();

          if (data.valid) {
            appliedCoupon = { code, discount: data.discount_percent };
            couponMessage.textContent = msgValid || 'Код действителен! Скидка применена';
            couponMessage.className = 'coupon-message valid';
            couponInput.disabled = true;
            couponCheckBtn.textContent = '✓';
            couponCheckBtn.style.background = '#4caf50';

            if (selectedSlots.length > 0) {
              const totalPrice = selectedSlots.reduce((sum, s) => sum + s.price, 0);
              displayTotalPrice(totalPrice);
            }
          } else {
            appliedCoupon = null;
            couponMessage.textContent = msgInvalid || 'Неверный или уже использованный код';
            couponMessage.className = 'coupon-message invalid';
            couponCheckBtn.disabled = false;
          }
        } catch (error) {
          appliedCoupon = null;
          couponMessage.textContent = msgError || 'Ошибка при проверке кода';
          couponMessage.className = 'coupon-message invalid';
          couponCheckBtn.disabled = false;
        }
      });

      couponInput.addEventListener('input', function () {
        if (appliedCoupon) {
          appliedCoupon = null;
          couponInput.disabled = false;
          couponCheckBtn.textContent = '→'; // текст кнопки оставляем как и было
          couponCheckBtn.style.background = '';
          couponMessage.textContent = '';
          couponMessage.className = 'coupon-message';
        }
      });
    }

    // Handle opening from deals.js
    window.addEventListener('booking:open', (e) => {
        resetForm();
        const params = e.detail?.params || {};

        if (params.service && serviceIdInput) {
            const serviceIdStr = String(params.service);
            serviceIdInput.value = serviceIdStr;
            document.getElementById('popup-service-icon')?.setAttribute('data-service', serviceIdStr);
            const serviceSelect = document.getElementById('popup-service');
            if (serviceSelect) {
                serviceSelect.value = serviceIdStr;
                serviceSelect.disabled = true;
                serviceSelect.dispatchEvent(new Event('change'));
            }
            currentServiceId = serviceIdStr;
        }

        if (params.serviceName) {
            const serviceNameEl = document.getElementById('popup-service-name');
            if (serviceNameEl) serviceNameEl.textContent = params.serviceName;
        }

        if (params.coupon && couponInput) { couponInput.value = params.coupon; couponInput.readOnly = true; }
        if (params.timeRestriction) window.dealTimeRestriction = params.timeRestriction;
        if (params.dateRestriction) window.dealDateRestriction = params.dateRestriction;
        window.dealSlotsRestriction = params.slotsRestriction || null;

        updateVisibilityForSlots();
    });

    // Submit
    form.addEventListener('submit', function(e) {
        e.preventDefault();

        if (selectedSlots.length === 0) {
            alert(tr('select_table_time', 'Пожалуйста, выберите стол и время'));
            return;
        }

        // Enforce guard before submit
        if (!isSlotCountEligible(selectedSlots, window.dealSlotsRestriction || null)) {
            alert(tr('selection_hint', 'Выберите нужное количество слотов подряд'));
            return;
        }

        const formData = new FormData(form);
        const data = Object.fromEntries(formData);
        data.slot_count = selectedSlots.length;

        if (appliedCoupon) {
            data.coupon_code = appliedCoupon.code;
            data.discount_percent = appliedCoupon.discount;
            const currentTotal = selectedSlots.reduce((sum, s) => sum + s.price, 0);
            data.original_price = currentTotal;
            data.final_price = currentTotal * (1 - appliedCoupon.discount / 100);
        }

        fetch('/api/booking/create', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        })
        .then(r => r.json())
        .then(result => {
            if (result.success && result.booking_id) {
                form.reset();

                appliedCoupon = null;
                if (couponInput) { couponInput.value = ''; couponInput.disabled = false; couponInput.readOnly = false; }
                if (couponCheckBtn) { couponCheckBtn.textContent = '→'; couponCheckBtn.style.background = ''; couponCheckBtn.disabled = false; }
                if (couponMessage) { couponMessage.textContent = ''; couponMessage.className = 'coupon-message'; }

                showSuccessPopup(result.booking_id);

                selectedSlots = [];
                updateSlotCountHidden();
                if (currentServiceId && dateInput && dateInput.value) {
                    loadResourcesAndSlots(currentServiceId, dateInput.value);
                }
            } else {
                alert(result.message || tr('booking_error', 'Ошибка при отправке заявки'));
            }
        })
        .catch(err => {
            console.error('Error:', err);
            alert(tr('booking_error', 'Ошибка при отправке заявки'));
        });
    });
});