/**
 * Biliardovna.sk - Booking Form Logic
 */

class BookingForm {
    constructor() {
        this.form = document.getElementById('booking-form');
        this.serviceInputs = document.querySelectorAll('input[name="service_id"]');
        this.dateInput = document.getElementById('booking_date');
        this.timeSelect = document.getElementById('start_time');
        this.durationSelect = document.getElementById('duration_minutes');
        this.priceDisplay = document.getElementById('total-price');
        this.submitButton = this.form?.querySelector('button[type="submit"]');
        
        this.init();
    }
    
    init() {
        if (!this.form) return;
        
        // Event listeners for service radio buttons
        this.serviceInputs.forEach(input => {
            input.addEventListener('change', () => this.onServiceChange());
        });
        
        // Core inputs listeners
        if (this.dateInput) {
            this.dateInput.addEventListener('change', () => this.onDateChange());
        }
        if (this.timeSelect) {
            this.timeSelect.addEventListener('change', () => this.onTimeChange());
        }
        if (this.durationSelect) {
            this.durationSelect.addEventListener('change', () => this.onDurationChange());
        }

        this.form.addEventListener('submit', (e) => this.onSubmit(e));
        
        // Make date input clickable (open native picker where supported)
        if (this.dateInput) {
            this.dateInput.style.cursor = 'pointer';
            this.dateInput.addEventListener('click', function() {
                if (typeof this.showPicker === 'function') {
                    this.showPicker();
                } else {
                    this.focus();
                }
            });
        }
    }
    
    getSelectedService() {
        const selected = document.querySelector('input[name="service_id"]:checked');
        return selected ? selected.value : null;
    }
    
    async onServiceChange() {
        this.resetTimeSlots();
        this.resetPrice();
        
        const serviceId = this.getSelectedService();
        if (serviceId && this.dateInput.value) {
            await this.loadAvailableSlots();
        }
    }
    
    async onDateChange() {
        this.resetTimeSlots();
        this.resetPrice();
        
        const serviceId = this.getSelectedService();
        if (serviceId && this.dateInput.value) {
            await this.loadAvailableSlots();
        }
    }
    
    async onTimeChange() {
        if (this.isFormValid()) {
            await this.calculatePrice();
        }
    }
    
    async onDurationChange() {
        if (this.isFormValid()) {
            await this.calculatePrice();
        }
    }
    
    async loadAvailableSlots() {
        const serviceId = this.getSelectedService();
        const date = this.dateInput.value;
        
        if (!serviceId || !date) return;
        
        try {
            this.setLoading(true);
            
            const response = await fetch(`/api/slots?service_id=${serviceId}&date=${date}`);
            const data = await response.json();
            
            if (data.error) {
                this.showError(data.error);
                return;
            }
            
            this.populateTimeSlots(data.slots);
        } catch (error) {
            console.error('Error loading slots:', error);
            this.showError(this.getTranslation('error_loading_slots'));
        } finally {
            this.setLoading(false);
        }
    }
    
    populateTimeSlots(slots) {
        // Clear existing slots
        const container = document.querySelector('.time-slots-grid') || this.createTimeSlotsContainer();
        container.innerHTML = '';
        
        if (!slots || slots.length === 0) {
            container.innerHTML = `<p class="no-slots">${this.getTranslation('no_slots_available')}</p>`;
            this.timeSelect.value = '';
            return;
        }
        
        slots.forEach(slot => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'time-slot-btn';
            button.dataset.time = slot.time;
            
            // Format time (remove seconds if present)
            const timeFormatted = slot.time.substring(0, 5); // "16:00:00" -> "16:00"
            
            // Get price (handle undefined)
            const price = slot.price || slot.price_per_hour || '0';
            
            button.innerHTML = `
                <span class="time">${timeFormatted}</span>
                <span class="price">${price}€/h</span>
            `;
            
            if (!slot.available) {
                button.disabled = true;
                button.classList.add('occupied');
                button.innerHTML += `<span class="status">${this.getTranslation('occupied')}</span>`;
            }
            
            button.addEventListener('click', () => this.selectTimeSlot(slot.time));
            container.appendChild(button);
        });
    }

    createTimeSlotsContainer() {
        const container = document.createElement('div');
        container.className = 'time-slots-grid';
        
        // Insert after time select
        const section = this.timeSelect.closest('.form-section');
        const timeLabel = section.querySelector('h2:nth-of-type(2)');
        timeLabel.insertAdjacentElement('afterend', container);
        
        // Hide the select element
        this.timeSelect.style.display = 'none';
        
        return container;
    }

    selectTimeSlot(time) {
        // Update hidden select
        this.timeSelect.value = time;
        
        // Update UI
        document.querySelectorAll('.time-slot-btn').forEach(btn => {
            btn.classList.remove('selected');
        });
        event.target.closest('.time-slot-btn').classList.add('selected');
        
        // Calculate price
        if (this.isFormValid()) {
            this.calculatePrice();
        }
    }
    
    async calculatePrice() {
        const serviceId = this.getSelectedService();
        const date = this.dateInput.value;
        const time = this.timeSelect.value;
        const duration = this.durationSelect.value;
        
        if (!serviceId || !date || !time || !duration) return;
        
        try {
            const response = await fetch(
                `/api/price?service_id=${serviceId}&date=${date}&time=${time}&duration=${duration}`
            );
            const data = await response.json();
            
            if (data.error) {
                this.showError(data.error);
                return;
            }
            
            this.displayPrice(data);
        } catch (error) {
            console.error('Error calculating price:', error);
            this.showError(this.getTranslation('error_calculating_price'));
        }
    }
    
    displayPrice(breakdown) {
        if (!this.priceDisplay) return;
        
        const html = `
            <div class="price-breakdown">
                <div class="price-row">
                    <span>${this.getTranslation('base_price')}:</span>
                    <span>${breakdown.base_price}€</span>
                </div>
                ${breakdown.multiplier !== 1 ? `
                <div class="price-row">
                    <span>${this.getTranslation('time_multiplier')} (${breakdown.multiplier}x):</span>
                    <span>+${(breakdown.total - breakdown.base_price).toFixed(2)}€</span>
                </div>
                ` : ''}
                <div class="price-row total">
                    <span>${this.getTranslation('total')}:</span>
                    <span class="price-value">${breakdown.total}€</span>
                </div>
            </div>
        `;
        
        this.priceDisplay.innerHTML = html;
        this.priceDisplay.style.display = 'block';
    }
    
    resetTimeSlots() {
        this.timeSelect.innerHTML = `<option value="">${this.getTranslation('select_time')}</option>`;
        this.timeSelect.disabled = true;
    }
    
    resetPrice() {
        if (this.priceDisplay) {
            this.priceDisplay.innerHTML = '';
            this.priceDisplay.style.display = 'none';
        }
    }
    
    isFormValid() {
        return this.getSelectedService() && 
               this.dateInput.value && 
               this.timeSelect.value && 
               this.durationSelect.value;
    }
    
    async onSubmit(e) {
        e.preventDefault();
        
        if (!this.validateForm()) {
            return;
        }
        
        this.setLoading(true);
        
        const formData = new FormData(this.form);
        formData.append('ajax', '1');
        
        try {
            const response = await fetch('/booking/submit', {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            
            if (data.success) {
                window.location.href = '/booking/success';
            } else {
                this.showErrors(data.errors);
            }
        } catch (error) {
            console.error('Error submitting booking:', error);
            this.showError(this.getTranslation('error_submitting'));
        } finally {
            this.setLoading(false);
        }
    }
    
    validateForm() {
        const errors = [];
        
        if (!this.getSelectedService()) {
            errors.push(this.getTranslation('error_service_required'));
        }
        
        if (!this.dateInput.value) {
            errors.push(this.getTranslation('error_date_required'));
        }
        
        if (!this.timeSelect.value) {
            errors.push(this.getTranslation('error_time_required'));
        }
        
        const name = document.getElementById('customer_name')?.value;
        if (!name || name.trim().length < 2) {
            errors.push(this.getTranslation('error_name_required'));
        }
        
        const phone = document.getElementById('customer_phone')?.value;
        if (!phone || phone.length < 10) {
            errors.push(this.getTranslation('error_phone_required'));
        }
        
        const email = document.getElementById('customer_email')?.value;
        if (email && !this.isValidEmail(email)) {
            errors.push(this.getTranslation('error_email_invalid'));
        }
        
        if (errors.length > 0) {
            this.showErrors(errors);
            return false;
        }
        
        return true;
    }
    
    isValidEmail(email) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
    }
    
    showError(message) {
        this.showErrors([message]);
    }
    
    showErrors(errors) {
        const errorContainer = document.getElementById('booking-errors') || this.createErrorContainer();
        
        errorContainer.innerHTML = errors.map(error => 
            `<div class="error-message">${error}</div>`
        ).join('');
        
        errorContainer.style.display = 'block';
        errorContainer.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        
        setTimeout(() => {
            errorContainer.style.display = 'none';
        }, 5000);
    }
    
    createErrorContainer() {
        const container = document.createElement('div');
        container.id = 'booking-errors';
        container.className = 'booking-errors';
        this.form.insertBefore(container, this.form.firstChild);
        return container;
    }
    
    setLoading(isLoading) {
        if (this.submitButton) {
            this.submitButton.disabled = isLoading;
            this.submitButton.textContent = isLoading 
                ? this.getTranslation('loading') 
                : this.getTranslation('submit_booking');
        }
        
        if (isLoading) {
            this.form.classList.add('loading');
        } else {
            this.form.classList.remove('loading');
        }
    }
    
    getTranslation(key) {
        const translations = window.bookingTranslations || {};
        return translations[key] || key;
    }
}

// Initialize booking form when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    new BookingForm();
});