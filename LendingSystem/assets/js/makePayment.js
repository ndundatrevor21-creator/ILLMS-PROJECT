document.addEventListener('DOMContentLoaded', function() {
    const paymentMethodSelect = document.getElementById('payment_method');
    const paymentDetailsDiv = document.getElementById('payment-details');
    
    // Show payment details based on selected payment method
    paymentMethodSelect.addEventListener('change', function() {
        const selectedMethod = this.value;
        
        if (!selectedMethod) {
            paymentDetailsDiv.innerHTML = '';
            paymentDetailsDiv.classList.remove('active');
            return;
        }
        
        paymentDetailsDiv.classList.add('active');
        
        // Generate form fields based on payment method
        let detailsHTML = '';
        
        switch (selectedMethod) {
            case 'Credit Card':
            case 'Debit Card':
                detailsHTML = `
                    <div class="form-group">
                        <label for="card_number">Card Number</label>
                        <input type="text" id="card_number" class="form-control" placeholder="XXXX XXXX XXXX XXXX">
                    </div>
                    <div class="form-row">
                        <div class="form-group half">
                            <label for="expiry_date">Expiry Date</label>
                            <input type="text" id="expiry_date" class="form-control" placeholder="MM/YY">
                        </div>
                        <div class="form-group half">
                            <label for="cvv">CVV</label>
                            <input type="text" id="cvv" class="form-control" placeholder="XXX">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="card_name">Name on Card</label>
                        <input type="text" id="card_name" class="form-control">
                    </div>
                `;
                break;
                
            case 'Bank Transfer':
                detailsHTML = `
                    <div class="form-group">
                        <label for="account_number">Account Number</label>
                        <input type="text" id="account_number" class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="routing_number">Routing Number</label>
                        <input type="text" id="routing_number" class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="account_name">Account Holder Name</label>
                        <input type="text" id="account_name" class="form-control">
                    </div>
                `;
                break;
                
            case 'PayPal':
                detailsHTML = `
                    <div class="form-group">
                        <label for="paypal_email">PayPal Email</label>
                        <input type="email" id="paypal_email" class="form-control">
                    </div>
                    <p class="note">You will be redirected to PayPal to complete your payment.</p>
                `;
                break;
        }
        
        paymentDetailsDiv.innerHTML = detailsHTML;
    });
});