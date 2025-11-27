// =================================================================================
// ARCHIVO JAVASCRIPT GLOBAL PARA TODA LA TIENDA
// Contiene únicamente la lógica común a todas las páginas.
// =================================================================================

(function ($) {
    'use strict';

    // --- Función de utilidad para "debouncing" ---
    function delay(callback, ms) {
        let timer = 0;
        return function () {
            const context = this, args = arguments;
            clearTimeout(timer);
            timer = setTimeout(() => callback.apply(context, args), ms || 0);
        };
    }

    // --- Búsqueda en vivo ---
    let currentSearchRequest = null;
    $(document).on('keyup', '[data-url-buscar]', delay(function () {
        const $input = $(this);
        const str = $input.val();
        const $liveSearchContainer = $('#livesearch');
        if (str.length < 3) {
            $liveSearchContainer.html("").css('border', '0px');
            if (currentSearchRequest) { currentSearchRequest.abort(); }
            return;
        }
        let url = $input.data('url-buscar').replace("cadenaNombre", encodeURIComponent(str));
        if (currentSearchRequest) { currentSearchRequest.abort(); }

        currentSearchRequest = $.get(url).done(function (response) {
            $liveSearchContainer.html(response).css('border', '1px solid #A5ACB2');
        }).fail(function(jqXHR, textStatus) {
            if (textStatus !== 'abort') console.error("Error en la búsqueda:", textStatus);
        }).always(function() {
            currentSearchRequest = null;
        });
    }, 350));

    // --- Lógica de la Interfaz General ---
    $(document).ready(function () {
        // Tooltips
        $('a[data-toggle="tooltip"]').tooltip({
            animated: 'fade',
            placement: 'bottom',
            html: false
        });

        // Menú hamburguesa
        $('#nav-icon1, #nav-icon2, #nav-icon3, #nav-icon4').click(function () {
            $(this).toggleClass('open');
        });

        // Desplegables del menú móvil
        $('.tkmPlus').click(function () {
            if ($(this).hasClass('open')) {
                $(this).toggleClass('open');
            } else {
                $('.tkmPlus.open').removeClass('open');
                $(this).toggleClass('open');
            }
        });

        // Barra lateral de filtros
        $('#sidebarCollapse').on('click', function () {
            $('#sidebar').toggleClass('active');
            $(this).toggleClass('active');
        });
    });
})(jQuery);

