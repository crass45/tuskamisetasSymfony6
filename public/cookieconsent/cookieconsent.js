/*
    Descripción general: Parámetros del modo de consentimiento
*/



// --- LÓGICA DE UPDATE INMEDIATO ---
// Esta parte se ejecuta en cuanto carga el archivo, para "desbloquear" a Google lo antes posible.
(function() {
    var savedConsent = JSON.parse(localStorage.getItem('consentMode'));

    // Si existe consentimiento y la versión es correcta, hacemos el UPDATE
    if (savedConsent !== null && savedConsent.version === CURRENT_CONSENT_VERSION) {
        gtag('consent', 'update', {
            'ad_storage': 'granted', // CORREGIDO
            'analytics_storage': 'granted', // CORREGIDO
            'ad_user_data': 'granted', // CORREGIDO
            'ad_personalization': 'granted', // CORREGIDO
            'functionality_storage': 'granted', // CORREGIDO
            'personalization_storage': 'granted', // CORREGIDO
            'security_storage': 'granted', // CORREGIDO
            'version': CURRENT_CONSENT_VERSION // AÑADIDO EL CONTROL DE VERSIÓN
        });
    }
})();
// --- FIN LÓGICA UPDATE ---

window.onload = function() {
    // Se define el HTML del banner de consentimiento. (SU CÓDIGO ORIGINAL)
    const cookie_consent_banner_dom = `
    <div id="cookie-consent-banner" class="cookie-consent-banner">
        <h3>Valoramos tu privacidad</h3>
        <p>Usamos cookies para mejorar tu experiencia de navegación y analizar nuestro tráfico.\n Al hacer clic en "Aceptar todo" das tu consentimiento a <a href="https://www.tuskamisetas.com/es/politica-de-cookies">nuestro uso de las cookies</a>.</p>
        <div class="cookie-consent-options">
          <label><input id="consent-necessary" type="checkbox" value="Necessary" checked disabled>Necesarias</label>
          <label><input id="consent-analytics" type="checkbox" value="Analytics" >Analítica</label>
          <label><input id="consent-marketing" type="checkbox" value="Marketing" >Marketing</label>
          <label><input id="consent-preferences" type="checkbox" value="Preferences" >Preferencias</label>
          <label><input id="consent-partners" type="checkbox" value="Partners">Otras</label>
        </div>
        <div class="cookie-consent-buttons">
          <button id="cookie-consent-btn-reject-all" class="cookie-consent-button btn-grayscale">Rechazar todo</button>
          <button id="cookie-consent-btn-accept-some" class="cookie-consent-button btn-outline">Guardar preferencias</button>
          <button id="cookie-consent-btn-accept-all" class="cookie-consent-button btn-success">Aceptar todo</button>
        </div>
    </div>
  `;

    // Se inyecta el banner en el cuerpo del documento.
    document.body.insertAdjacentHTML('beforeend', cookie_consent_banner_dom);
    const cookie_consent_banner = document.body.lastElementChild;

    // Funciones auxiliares (mantienen su código original)
    function dnt () {
        return (navigator.doNotTrack == "1" || window.doNotTrack == "1");
    }

    function gpc () {
        return (navigator.globalPrivacyControl || window.globalPrivacyControl);
    }

    // --- showBanner CORREGIDA y Fusionada ---
    function showBanner() {
        const cm = JSON.parse(window.localStorage.getItem('consentMode'));

        // 1. Ocultar si existe y la versión es correcta (NO mostrar el banner)
        if (cm !== null && cm.version === CURRENT_CONSENT_VERSION) {
            hideBanner();
            return;
        }

        // 2. Si no existe o la versión es incorrecta, cargamos los inputs y mostramos el banner.
        if (cm) {
            document.querySelector('#consent-necessary').checked = (cm.functionality_storage == 'granted');
            document.querySelector('#consent-necessary').disabled = true;

            // Lógica para rellenar las casillas según el estado guardado (SU CÓDIGO ORIGINAL)
            document.querySelector('#consent-analytics').checked = (cm.analytics_storage == 'granted');
            document.querySelector('#consent-preferences').checked = (cm.personalization_storage == 'granted');
            document.querySelector('#consent-marketing').checked = (cm.ad_storage == 'granted');
            document.querySelector('#consent-partners').checked = (cm.ad_personalization == 'granted');
        }

        // 3. Muestra el banner
        cookie_consent_banner.style.display = 'flex';
    }
    // --- Fin showBanner Corregida ---

    // Oculta el banner.
    function hideBanner() {
        cookie_consent_banner.style.display = 'none';
    }

    // Objeto global para poder llamar a las funciones desde fuera si es necesario.
    window.cookieconsent = {
        show: showBanner,
        hide: hideBanner
    };

    // --- setConsent CORREGIDA y Fusionada (Funcionalidad de Versión) ---
    function setConsent(consent) {
        const consentMode = {
            'ad_storage': (consent.marketing && !dnt()) ? 'granted' : 'denied', // CORREGIDO
            'analytics_storage': (consent.analytics && !dnt()) ? 'granted' : 'denied', // CORREGIDO
            'ad_user_data': (consent.marketing && !dnt()) ? 'granted' : 'denied', // CORREGIDO
            'ad_personalization': (consent.partners && !gpc()) ? 'granted' : 'denied', // CORREGIDO
            'functionality_storage': consent.necessary ? 'granted' : 'denied', // CORREGIDO
            'personalization_storage': consent.preferences ? 'granted' : 'denied', // CORREGIDO
            'security_storage': consent.necessary ? 'granted' : 'denied', // CORREGIDO
            'version': CURRENT_CONSENT_VERSION // AÑADIDO EL CONTROL DE VERSIÓN
        };

        // Actualiza el consentimiento para Google y lo guarda en localStorage.
        gtag('consent', 'update', consentMode);
        localStorage.setItem('consentMode', JSON.stringify(consentMode));
    }
    // --- Fin setConsent Corregida ---


    if (cookie_consent_banner) {
        // Permite que cualquier elemento con esta clase pueda volver a abrir el banner.
        Array.from(document.querySelectorAll('.cookie-consent-banner-open')).map(btn => {
            btn.addEventListener('click', () => {
                showBanner();
            });
        });

        // Lógica para los tres botones del banner.
        cookie_consent_banner.querySelector('#cookie-consent-btn-accept-all').addEventListener('click', () => {
            setConsent({
                necessary: true,
                analytics: true,
                preferences: true,
                marketing: true,
                partners: true
            });
            hideBanner();
        });

        cookie_consent_banner.querySelector('#cookie-consent-btn-accept-some').addEventListener('click', () => {
            setConsent({
                necessary: true,
                analytics: document.querySelector('#consent-analytics').checked,
                preferences: document.querySelector('#consent-preferences').checked,
                marketing: document.querySelector('#consent-marketing').checked,
                partners: document.querySelector('#consent-partners').checked
            });
            hideBanner();
        });

        cookie_consent_banner.querySelector('#cookie-consent-btn-reject-all').addEventListener('click', () => {
            setConsent({
                necessary: true,
                analytics: false,
                preferences: false,
                marketing: false,
                partners: false
            });
            hideBanner();
        });
    }
    // Llama a showBanner al final para iniciar el proceso de mostrar/ocultar
    showBanner();
}