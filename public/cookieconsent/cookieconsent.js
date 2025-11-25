/*
    Descripción general: Parámetros del modo de consentimiento
*/

// Define la versión actual de la política de consentimiento.
const CURRENT_CONSENT_VERSION = 'v2';

window.dataLayer = window.dataLayer || [];
function gtag() { dataLayer.push(arguments); }

// --- Lógica de inicialización modificada para control de versión ---
const savedConsent = JSON.parse(localStorage.getItem('consentMode'));

if (savedConsent === null || savedConsent.version !== CURRENT_CONSENT_VERSION) {
    // Si no hay consentimiento guardado O si la versión es antigua,
    // se establece el estado de consentimiento más restrictivo por defecto.
    gtag('consent', 'default', {
        'functionality_storage': 'granted',
        'security_storage': 'granted',
        'ad_storage': 'denied',
        'ad_user_data': 'denied',
        'ad_personalization': 'denied',
        'analytics_storage': 'denied',
        'personalization_storage': 'denied',
        'wait_for_update': 500,
    });
} else {
    // Si la versión es correcta, carga el consentimiento guardado del usuario
    gtag('consent', 'default', savedConsent);
}
// --- Fin de la lógica de inicialización modificada ---


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


    // ================================================================
    // == INICIO: CÓDIGO PARA RECHAZO IMPLÍCITO POR NAVEGACIÓN ==
    // ================================================================
    // Solo se activa este mecanismo si el usuario no ha dado su consentimiento todavía.
    if (localStorage.getItem('consentMode') === null) {

        // 1. Se define cuántos clics se considerarán como "navegación" antes de rechazar.
        const clickThreshold = 2;
        let navigationClicks = 0;

        // 2. Función que maneja cada clic en la página.
        function handleImpliedConsent(event) {
            // Si el clic ocurre DENTRO del banner, se ignora.
            if (event.target.closest('#cookie-consent-banner')) {
                return;
            }

            navigationClicks++;

            // 3. Si se alcanza el umbral de clics, se considera un rechazo implícito.
            if (navigationClicks >= clickThreshold) {
                console.log('Consentimiento rechazado implícitamente por navegación.');

                // Se llama a la función setConsent con las mismas opciones que el botón "Rechazar todo".
                setConsent({
                    necessary: true,
                    analytics: false,
                    preferences: false,
                    marketing: false,
                    partners: false
                });

                // Se oculta el banner.
                // hideBanner();

                // 4. IMPORTANTE: Se desactiva el listener para que no siga contando.
                document.removeEventListener('click', handleImpliedConsent);
            }
        }

        // 5. Se añade el listener para que "escuche" todos los clics en el documento.
        document.addEventListener('click', handleImpliedConsent);
    }
    // ================================================================
    // == FIN: CÓDIGO PARA RECHAZO IMPLÍCITO POR NAVEGACIÓN ==
    // ================================================================


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