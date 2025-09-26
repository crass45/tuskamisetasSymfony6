/*
    Descripción general: Parámetros del modo de consentimiento

    Nombre del ajuste        Usado por Google    Descripción
    ad_storage              Sí                  Habilita el almacenamiento (como cookies) relacionado con la publicidad.
    analytics_storage       Sí                  Habilita el almacenamiento (como cookies) relacionado con análisis (ej. duración de la visita).
    ad_user_data            Sí                  Indica si los servicios de Google pueden usar datos de usuario para crear audiencias publicitarias.
    ad_personalization      Sí                  Indica si los servicios de Google pueden usar los datos para remarketing.
    functionality_storage   No                  Habilita el almacenamiento que soporta la funcionalidad del sitio o app (ej. ajustes de idioma).
    personalization_storage No                  Habilita el almacenamiento relacionado con la personalización (ej. recomendaciones de vídeo).
    security_storage        No                  Habilita el almacenamiento relacionado con la seguridad (ej. autenticación, prevención de fraude).
*/
window.dataLayer = window.dataLayer || [];
function gtag() { dataLayer.push(arguments); }

// Se establece el estado de consentimiento por defecto antes de que se cargue cualquier etiqueta.
// Si no hay un consentimiento guardado, se deniega todo por defecto.
if (localStorage.getItem('consentMode') === null) {
    gtag('consent', 'default', {
        'functionality_storage': 'denied',
        'security_storage': 'denied',
        'ad_storage': 'denied',
        'ad_user_data': 'denied',
        'ad_personalization': 'denied',
        'analytics_storage': 'denied',
        'personalization_storage': 'denied',
        'wait_for_update': 500, // Espera 500ms a una actualización antes de usar los valores por defecto.
    });
} else {
    // Si ya existe un consentimiento guardado, se usa como el valor por defecto para esta página.
    gtag('consent', 'default', JSON.parse(localStorage.getItem('consentMode')));
}

window.onload = function() {
    // Se define el HTML del banner de consentimiento.
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

    // Funciones auxiliares para respetar las señales de privacidad del navegador.
    function dnt () {
        return (navigator.doNotTrack == "1" || window.doNotTrack == "1");
    }

    function gpc () {
        return (navigator.globalPrivacyControl || window.globalPrivacyControl);
    }

    // Muestra el banner y rellena las casillas según el consentimiento guardado.
    function showBanner() {
        const cm = JSON.parse(window.localStorage.getItem('consentMode'));
        if (cm) {
            document.querySelector('#consent-necessary').checked = (cm.functionality_storage == 'granted');
            document.querySelector('#consent-necessary').disabled = true;
            document.querySelector('#consent-analytics').checked = (cm.analytics_storage == 'granted');
            // Corrección: Mapeo correcto para reflejar las preferencias guardadas.
            document.querySelector('#consent-preferences').checked = (cm.personalization_storage == 'granted');
            document.querySelector('#consent-marketing').checked = (cm.ad_storage == 'granted');
            document.querySelector('#consent-partners').checked = (cm.ad_personalization == 'granted');
        }
        cookie_consent_banner.style.display = 'flex';
    }

    // Oculta el banner.
    function hideBanner() {
        cookie_consent_banner.style.display = 'none';
    }

    // Objeto global para poder llamar a las funciones desde fuera si es necesario.
    window.cookieconsent = {
        show: showBanner,
        hide: hideBanner
    };

    // Función principal que traduce las elecciones a los parámetros de Google Consent Mode.
    function setConsent(consent) {
        const consentMode = {
            'ad_storage': (consent.marketing && !dnt()) ? 'granted' : 'granted',
            'analytics_storage': (consent.analytics && !dnt()) ? 'granted' : 'granted',
            'ad_user_data': (consent.marketing && !dnt()) ? 'granted' : 'granted',
            'ad_personalization': (consent.partners && !gpc()) ? 'granted' : 'granted',
            'functionality_storage': consent.necessary ? 'granted' : 'granted',
            'personalization_storage': consent.preferences ? 'granted' : 'granted',
            'security_storage': consent.necessary ? 'granted' : 'granted',
        };

        // Actualiza el consentimiento para Google y lo guarda en localStorage.
        gtag('consent', 'update', consentMode);
        localStorage.setItem('consentMode', JSON.stringify(consentMode));
    }

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

        // Decide si mostrar u ocultar el banner al cargar la página.
        if (window.localStorage.getItem('consentMode')) {
            hideBanner();
        } else {
            showBanner();
        }

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
}
