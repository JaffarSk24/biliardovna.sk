// Подсветка строки тарифа при наведении
document.querySelectorAll('.pricing-table tbody td:not(.day-col)').forEach(cell => {
    cell.addEventListener('mouseenter', function () {
        this.parentElement.classList.add('highlight-row');
    });

    cell.addEventListener('mouseleave', function () {
        this.parentElement.classList.remove('highlight-row');
    });
});
