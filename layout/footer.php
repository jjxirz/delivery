<?php if (isset($_SESSION['usuario_id'])): ?>
    </main> <!-- Fin de .so-main-content -->
</div> <!-- Fin de .so-layout-wrapper -->
<?php else: ?>
    </div> <!-- Fin de .container -->
<?php endif; ?>

<!-- Contenedor para Notificaciones Flotantes en la Esquina Inferior Derecha -->
<div id="toast-container"></div>

<footer style="text-align: center; padding: 16px; font-size: 0.8rem; color: var(--so-gray-text); border-top: 1px solid var(--so-gray-border); background-color: #f8f9f9;">
    <p style="margin:0;">&copy; <?= date('Y') ?> <?= SITE_NAME ?> - Sistema Administrativo Delivery</p>
</footer>

<?php if (isset($_SESSION['usuario_id'])): ?>
    <?php require_once __DIR__ . '/modal_mapa.php'; ?>
<?php endif; ?>

<script>
if (typeof lucide !== 'undefined') {
    lucide.createIcons();
}

// Cerrar cualquier modal al hacer clic fuera de su contenido (.modal-backdrop)
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('modal-backdrop')) {
        if (e.target.id === 'modalMapaGlobal' && typeof cerrarModalMapaGlobal === 'function') {
            cerrarModalMapaGlobal();
            return;
        }
        e.target.classList.remove('show');
    }
});

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal-backdrop.show').forEach(modal => modal.classList.remove('show'));
    }
});
</script>
</body>
</html>