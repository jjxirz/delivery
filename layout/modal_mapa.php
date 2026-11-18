<!-- Leaflet CSS & JS (se carga una sola vez) -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<style>
    .modal-header-icon-btn {
        background: transparent;
        border: none;
        color: #525960;
        font-size: 1.4rem;
        line-height: 1;
        width: 32px;
        height: 32px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 4px;
        cursor: pointer;
        padding: 0;
        transition: background-color 0.15s, color 0.15s;
    }
    .modal-header-icon-btn:hover {
        background-color: #e3e6e8;
        color: #0c0d0e;
    }
    .modal-header-icon-btn.close-danger:hover {
        background-color: #fde8e8;
        color: #c81e1e;
    }

    .skeleton-map-box {
        width: 100%;
        height: 360px;
        border-radius: 3px;
        background: linear-gradient(90deg, #eff0f1 25%, #e3e6e8 50%, #eff0f1 75%);
        background-size: 200% 100%;
        animation: skeletonLoading 1.3s infinite linear;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #6a737c;
        font-size: 0.88rem;
        font-weight: 500;
    }
    @keyframes skeletonLoading {
        0% { background-position: 200% 0; }
        100% { background-position: -200% 0; }
    }

    .modal-map-expanded {
        max-width: 94vw !important;
        width: 94vw !important;
        height: 90vh !important;
        display: flex;
        flex-direction: column;
    }
    .modal-map-expanded .modal-body {
        flex: 1;
        display: flex;
        flex-direction: column;
    }
    .modal-map-expanded #leafletMapGlobal,
    .modal-map-expanded #mapaGlobalSkeleton {
        flex: 1;
        height: 100% !important;
        min-height: 450px;
    }
</style>

<!-- Modal Único de Seguimiento GPS en Vivo -->
<div class="modal-backdrop" id="modalMapaGlobal">
    <div class="modal-content" id="modalMapaGlobalContent" style="max-width: 680px; transition: all 0.2s;">
        <div class="modal-header" style="display: flex; justify-content: space-between; align-items: center; gap: 10px;">
            <h3 id="mapaGlobalTitulo" style="font-size: 1rem; margin: 0;">🗺️ Seguimiento de Ruta en Tiempo Real</h3>
            
            <div style="display: flex; align-items: center; gap: 6px;">
                <a id="mapaGlobalBtnExterno" href="#" target="_blank" class="btn" style="font-size: 0.78rem; padding: 4px 10px; margin-right: 4px;">
                    Abrir en Google Maps &nearr;
                </a>
                <button type="button" id="btnAmpliarMapaGlobal" class="modal-header-icon-btn" title="Ampliar mapa" onclick="toggleAmpliarMapaGlobal()">
                    &#x26F6;
                </button>
                <button type="button" class="modal-header-icon-btn close-danger" title="Cerrar" onclick="cerrarModalMapaGlobal()">
                    &times;
                </button>
            </div>
        </div>
        <div class="modal-body">
            <div style="margin-bottom: 10px; font-size: 0.85rem; line-height: 1.45;">
                <strong>Cliente:</strong> <span id="mapaGlobalCliente"></span> · <span id="mapaGlobalDireccion" style="color:#6a737c;"></span><br>
                <strong>Repartidor asignado:</strong> <span id="mapaGlobalRepartidor" style="color:#2f6f44; font-weight:600;"></span>
            </div>

            <div id="mapaGlobalSkeleton" class="skeleton-map-box">
                Obteniendo señal GPS del repartidor y trazando ruta...
            </div>

            <div id="leafletMapGlobal" style="width: 100%; height: 360px; border-radius: 3px; border: 1px solid #d6d9dc; display: none;"></div>
        </div>
    </div>
</div>

<script>
let mapaGlobalInstancia = null;
let marcadorGlobalRep = null;
let intervaloGpsGlobal = null;

function toggleAmpliarMapaGlobal() {
    const modalBox = document.getElementById('modalMapaGlobalContent');
    const btn = document.getElementById('btnAmpliarMapaGlobal');
    modalBox.classList.toggle('modal-map-expanded');
    
    const expandido = modalBox.classList.contains('modal-map-expanded');
    btn.innerHTML = expandido ? '&#x29C9;' : '&#x26F6;';
    btn.title = expandido ? 'Reducir mapa' : 'Ampliar mapa';

    if (mapaGlobalInstancia) {
        setTimeout(() => mapaGlobalInstancia.invalidateSize(), 220);
    }
}

function abrirMapaRuta(info) {
    if (intervaloGpsGlobal) clearInterval(intervaloGpsGlobal);

    document.getElementById('modalMapaGlobalContent').classList.remove('modal-map-expanded');
    const btnAmpliar = document.getElementById('btnAmpliarMapaGlobal');
    btnAmpliar.innerHTML = '&#x26F6;';
    btnAmpliar.title = 'Ampliar mapa';

    document.getElementById('mapaGlobalTitulo').innerText = '🗺️ Ruta en Vivo #' + info.codigo;
    document.getElementById('mapaGlobalCliente').innerText = info.cliente;
    document.getElementById('mapaGlobalDireccion').innerText = info.direccion;
    document.getElementById('mapaGlobalRepartidor').innerText = info.repartidor;
    document.getElementById('mapaGlobalBtnExterno').href = `https://www.google.com/maps/dir/?api=1&origin=13.692940,-89.218190&destination=${info.lat},${info.lng}&travelmode=driving`;

    const skeleton = document.getElementById('mapaGlobalSkeleton');
    const mapDiv = document.getElementById('leafletMapGlobal');

    skeleton.style.display = 'flex';
    mapDiv.style.display = 'none';
    document.getElementById('modalMapaGlobal').classList.add('show');

    const endpointGps = '<?= BASE_URL ?>modules/pedidos/gps_tracker.php?pedido_id=' + info.id;

    fetch(endpointGps)
        .then(res => res.json())
        .then(gps => {
            skeleton.style.display = 'none';
            mapDiv.style.display = 'block';

            if (mapaGlobalInstancia) {
                mapaGlobalInstancia.remove();
            }

            mapaGlobalInstancia = L.map('leafletMapGlobal').setView(gps.actual, 13);

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; OpenStreetMap'
            }).addTo(mapaGlobalInstancia);

            L.marker(gps.origen).addTo(mapaGlobalInstancia).bindPopup('<b>🏪 Comercio Origen</b>');

            const puntosRutaCompleta = [...gps.trazado];
            if (gps.paradas_activas && gps.paradas_activas.length > 0) {
                gps.paradas_activas.forEach((parada, idx) => {
                    puntosRutaCompleta.push(parada.coords);
                    const etiqueta = parada.es_actual
                        ? `<b>📍 Destino Seleccionado (#${parada.codigo})</b><br>${parada.cliente}`
                        : `<b>📦 Parada #${idx + 1} del Repartidor (#${parada.codigo})</b><br>${parada.cliente} (${parada.estado})`;

                    L.marker(parada.coords).addTo(mapaGlobalInstancia).bindPopup(etiqueta);
                    if (!parada.es_actual) {
                        L.circleMarker(parada.coords, {
                            radius: 9,
                            color: '#0074cc',
                            fillColor: '#379fef',
                            fillOpacity: 0.45
                        }).addTo(mapaGlobalInstancia);
                    }
                });
            } else {
                puntosRutaCompleta.push(gps.destino);
                L.marker(gps.destino).addTo(mapaGlobalInstancia).bindPopup('<b>📍 Destino:</b> ' + info.cliente);
            }

            const totalParadas = gps.paradas_activas ? gps.paradas_activas.length : 1;
            marcadorGlobalRep = L.marker(gps.actual).addTo(mapaGlobalInstancia)
                .bindPopup(`<b>🛵 ${gps.repartidor}</b><br><small>${gps.ruta_nombre} · ${totalParadas} envío(s) en ruta (${gps.timestamp})</small>`)
                .openPopup();

            const rutaLinea = L.polyline(puntosRutaCompleta, {
                color: '#f48024',
                weight: 4,
                dashArray: '6, 6'
            }).addTo(mapaGlobalInstancia);

            mapaGlobalInstancia.fitBounds(rutaLinea.getBounds(), { padding: [35, 35] });

            intervaloGpsGlobal = setInterval(() => {
                fetch(endpointGps)
                    .then(r => r.json())
                    .then(nuevoGps => {
                        if (marcadorGlobalRep && nuevoGps.actual) {
                            marcadorGlobalRep.setLatLng(nuevoGps.actual);
                            marcadorGlobalRep.setPopupContent(
                                `<b>🛵 ${nuevoGps.repartidor}</b><br><small>${nuevoGps.ruta_nombre} · Act: ${nuevoGps.timestamp}</small>`
                            );
                        }
                    });
            }, 4000);
        });
}

function cerrarModalMapaGlobal() {
    if (intervaloGpsGlobal) {
        clearInterval(intervaloGpsGlobal);
        intervaloGpsGlobal = null;
    }
    document.getElementById('modalMapaGlobal').classList.remove('show');
}
</script>