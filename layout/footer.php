</div> <!-- Fin de .container -->

<!-- Contenedor para Notificaciones Flotantes en la Esquina Inferior Derecha -->
<div id="toast-container"></div>

<footer style="text-align: center; padding: 20px; font-size: 0.8rem; color: var(--so-gray-text); border-top: 1px solid var(--so-gray-border); margin-top: auto;">
    <p>&copy; <?= date('Y') ?> <?= SITE_NAME ?> - Sistema Administrativo Delivery</p>
</footer>

<script>
let ultimoToastTiempo = 0;
let ultimoMensajeToast = '';

function mostrarToast(mensaje, tipo = 'warning', forzar = false) {
    const ahora = Date.now();
    if (!forzar && mensaje === ultimoMensajeToast && (ahora - ultimoToastTiempo) < 4000) {
        return;
    }

    ultimoToastTiempo = ahora;
    ultimoMensajeToast = mensaje;

    const container = document.getElementById('toast-container');
    const toastsActuales = container.querySelectorAll('.toast');
    if (toastsActuales.length >= 2) {
        toastsActuales[0].remove();
    }

    const toast = document.createElement('div');
    toast.className = `toast toast-${tipo}`;
    
    let icono = '⚠️';
    if (tipo === 'error') icono = '❌';
    if (tipo === 'success') icono = '✅';

    toast.innerHTML = `
        <div style="display:flex; align-items:center; gap:8px;">
            <span style="font-size: 1.1rem;">${icono}</span>
            <span>${mensaje}</span>
        </div>
        <small style="cursor:pointer; margin-left:12px; font-weight:bold; opacity:0.8;" onclick="this.parentElement.remove()">✕</small>
    `;
    
    container.appendChild(toast);

    setTimeout(() => {
        if (toast.parentElement) {
            toast.style.opacity = '0';
            toast.style.transition = 'opacity 0.5s ease';
            setTimeout(() => toast.remove(), 500);
        }
    }, 5000);
}

document.addEventListener('DOMContentLoaded', function() {
    
    // Asterisco automático en obligatorios
    document.querySelectorAll('input[required], select[required], textarea[required]').forEach(campo => {
        const id = campo.id;
        if (id) {
            const label = document.querySelector(`label[for="${id}"]`);
            if (label && !label.querySelector('.required-asterisk')) {
                const asterisk = document.createElement('span');
                asterisk.className = 'required-asterisk';
                asterisk.textContent = ' *';
                label.appendChild(asterisk);
            }
        }
    });

    // Control silencioso de entradas numéricas (Sin toasts molestos al teclear)
    document.querySelectorAll('input[type="number"], input[name*="monto"], input[name*="costo"], input[name*="precio"], input[name*="telefono"]').forEach(input => {
        
        input.addEventListener('keydown', function(e) {
            if (['Backspace', 'Tab', 'Enter', 'ArrowLeft', 'ArrowRight', 'Delete'].includes(e.key)) {
                return;
            }
            // Bloqueo silencioso del signo menos (-)
            if (e.key === '-' || e.code === 'NumpadSubtract') {
                e.preventDefault();
                return;
            }
            // Permitir un punto decimal
            if (e.key === '.' && this.type === 'number' && !this.value.includes('.')) {
                return;
            }
            // Bloqueo silencioso de caracteres no numéricos
            if (!/^[0-9]$/.test(e.key)) {
                e.preventDefault();
            }
        });

        input.addEventListener('input', function() {
            if (this.value < 0 || this.value.includes('-')) {
                this.value = Math.abs(parseFloat(this.value)) || '';
            }
        });
    });

    // Intercepción al intentar Enviar / Guardar
    document.querySelectorAll('form').forEach(form => {
        form.addEventListener('submit', function(e) {
            let esValido = true;
            let primerCampoFaltante = null;
            const requeridos = form.querySelectorAll('[required]');

            requeridos.forEach(campo => {
                campo.classList.remove('input-error');

                if (!campo.value.trim()) {
                    esValido = false;
                    campo.classList.add('input-error');
                    if (!primerCampoFaltante) primerCampoFaltante = campo;
                }
            });

            if (!esValido) {
                e.preventDefault();
                mostrarToast('Por favor, completa los campos obligatorios (*), están marcados en rojo.', 'warning', true);
                if (primerCampoFaltante) {
                    primerCampoFaltante.focus();
                }
            }
        });
    });
});
</script>

</body>
</html>