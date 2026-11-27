</main>

<footer class="site-footer">
    EDUVEX Academy; Architected & Built by SH, SJ, and YX
</footer>

<script
    src="<?= h(url('assets/js/main.js')) ?>"
    defer>
</script>

<!-- Dark / Light Mode Switcher Script -->
<script>
document.addEventListener('DOMContentLoaded', () => {
    const body = document.body;
    const checkbox = document.getElementById('theme-checkbox');

    const savedTheme = localStorage.getItem('eduvex_theme');
    if (savedTheme === 'light') {
        body.classList.add('light-mode');
        if (checkbox) checkbox.checked = true;
    }
});

function toggleTheme() {
    const body = document.body;
    body.classList.toggle('light-mode');
    
    if (body.classList.contains('light-mode')) {
        localStorage.setItem('eduvex_theme', 'light');
    } else {
        localStorage.setItem('eduvex_theme', 'dark');
    }
}
</script>

</body>
</html>