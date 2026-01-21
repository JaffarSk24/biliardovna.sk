document.querySelectorAll('.pricing-table tbody td:not(.time-col)').forEach(cell => {
    cell.addEventListener('mouseenter', function() {
        const columnIndex = this.cellIndex;
        const row = this.parentElement;
        
        row.classList.add('highlight-row');
        
        document.querySelectorAll(`.pricing-table tbody tr`).forEach(tr => {
            tr.children[columnIndex]?.classList.add('highlight-col');
        });
        
        const table = this.closest('table');
        const headerCell = table.querySelector(`thead th:nth-child(${columnIndex + 1})`);
        headerCell?.classList.add('highlight-header');
    });
    
    cell.addEventListener('mouseleave', function() {
        document.querySelectorAll('.highlight-row, .highlight-col, .highlight-header').forEach(el => {
            el.classList.remove('highlight-row', 'highlight-col', 'highlight-header');
        });
    });
});