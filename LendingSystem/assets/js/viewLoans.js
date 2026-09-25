document.addEventListener('DOMContentLoaded', function() {
    // Elements
    const searchInput = document.getElementById('searchInput');
    const statusFilter = document.getElementById('statusFilter');
    const tableRows = document.querySelectorAll('.loans-table tbody tr');
    
    // Search functionality
    searchInput.addEventListener('input', filterLoans);
    
    // Status filter functionality
    statusFilter.addEventListener('change', filterLoans);
    
    // Filter loans based on search input and status filter
    function filterLoans() {
        const searchTerm = searchInput.value.toLowerCase();
        const statusValue = statusFilter.value.toLowerCase();
        
        tableRows.forEach(row => {
            const rowText = row.textContent.toLowerCase();
            const rowStatus = row.getAttribute('data-status').toLowerCase();
            
            // Check if row matches both search term and status filter
            const matchesSearch = searchTerm === '' || rowText.includes(searchTerm);
            const matchesStatus = statusValue === '' || rowStatus === statusValue;
            
            // Show/hide row based on filters
            if (matchesSearch && matchesStatus) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    }
});