// deals.js
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.btn-deal').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();

            const triggerStr = btn.dataset.dealTrigger;
            if (!triggerStr) return;

            let params = {};
            try {
                params = JSON.parse(triggerStr);
            } catch (err) {
                console.error('Invalid data-deal-trigger JSON:', err);
                params = {};
            }

            const popupEl = document.getElementById('bookingPopup') || document.querySelector('.booking-popup');
            if (!popupEl) return;

            // Service (support both hidden input and optional select)
            if (params.service != null) {
                const serviceId = String(params.service);

                const serviceIdInput = document.getElementById('popup-service-id');
                if (serviceIdInput) {
                    serviceIdInput.value = serviceId;
                }

                const serviceSelect = document.getElementById('popup-service');
                if (serviceSelect) {
                    serviceSelect.value = serviceId;
                    serviceSelect.disabled = true;
                    serviceSelect.dispatchEvent(new Event('change'));
                }

                const iconEl = document.getElementById('popup-service-icon');
                if (iconEl) iconEl.setAttribute('data-service', serviceId);
            }

            // Coupon
            if (params.coupon) {
                const couponInput = document.getElementById('popup-coupon');
                if (couponInput) {
                    couponInput.value = params.coupon;
                    couponInput.readOnly = true;
                }
            }

            // Restrictions (also send via event detail)
            window.dealTimeRestriction = params.timeRestriction || null;
            window.dealDateRestriction = params.dateRestriction || null;

            // Optional service name
            if (params.serviceName) {
                const serviceNameEl = document.getElementById('popup-service-name');
                if (serviceNameEl) serviceNameEl.textContent = params.serviceName;
            }

            // Open popup
            popupEl.classList.add('active');
            document.body.style.overflow = 'hidden';

            // Notify booking module to reset/init
            window.dispatchEvent(new CustomEvent('booking:open', {
                detail: { source: 'deal', params }
            }));
        });
    });
});