document.addEventListener('turbo:before-cache', () => {
    for (const popup of document.querySelectorAll('.popup')) popup.style.display = 'none';
});
