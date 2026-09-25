// Loan Calculator Functionality
document.addEventListener('DOMContentLoaded', function() {
    const amountInput = document.getElementById('amount');
    const termSelect = document.getElementById('term_months');
    const monthlyPaymentElement = document.getElementById('monthly-payment');
    const totalPaymentElement = document.getElementById('total-payment');
    const totalInterestElement = document.getElementById('total-interest');
    
    // Default interest rate (can be adjusted based on credit score, loan type, etc.)
    const interestRate = 0.08; // 8% annual interest rate
    
    // Calculate loan details when amount or term changes
    function calculateLoan() {
        const amount = parseFloat(amountInput.value) || 0;
        const termMonths = parseInt(termSelect.value) || 0;
        
        if (amount > 0 && termMonths > 0) {
            // Calculate monthly interest rate
            const monthlyRate = interestRate / 12;
            
            // Calculate monthly payment using the formula: P * r * (1 + r)^n / ((1 + r)^n - 1)
            const monthlyPayment = amount * monthlyRate * Math.pow(1 + monthlyRate, termMonths) / (Math.pow(1 + monthlyRate, termMonths) - 1);
            
            // Calculate total payment and interest
            const totalPayment = monthlyPayment * termMonths;
            const totalInterest = totalPayment - amount;
            
            // Update the display
            monthlyPaymentElement.textContent = 'Kshs ' + monthlyPayment.toFixed(2);
            totalPaymentElement.textContent = 'Kshs ' + totalPayment.toFixed(2);
            totalInterestElement.textContent = 'Kshs ' + totalInterest.toFixed(2);
        } else {
            // Reset values if inputs are invalid
            monthlyPaymentElement.textContent = 'Kshs 0.00';
            totalPaymentElement.textContent = 'Kshs 0.00';
            totalInterestElement.textContent = 'Kshs 0.00';
        }
    }
    
    // Add event listeners to inputs
    amountInput.addEventListener('input', calculateLoan);
    termSelect.addEventListener('change', calculateLoan);
    
    // Initialize calculator
    calculateLoan();
});