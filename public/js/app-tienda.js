// =================================================================================
// ARCHIVO JAVASCRIPT GLOBAL PARA TODA LA TIENDA
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

    // --- Lógica de Vistos Recientemente (Cookies) ---
    function addVistoRecientemente(referencia) {
        if (!referencia) return;

        // Leemos las cookies actuales
        var v1 = $.cookie('vistosRecientemente1');
        var v2 = $.cookie('vistosRecientemente2');
        var v3 = $.cookie('vistosRecientemente3');

        // Si el producto actual ya es el último visto, no hacemos nada
        if (v1 === referencia) return;

        console.log("Guardando producto visto: " + referencia); // DEBUG

        // Rotamos las referencias
        if (v3 && v3 !== referencia) $.cookie('vistosRecientemente4', v3, { expires: 30, path: '/' });
        if (v2 && v2 !== referencia) $.cookie('vistosRecientemente3', v2, { expires: 30, path: '/' });
        if (v1 && v1 !== referencia) $.cookie('vistosRecientemente2', v1, { expires: 30, path: '/' });

        // Guardamos el actual
        $.cookie('vistosRecientemente1', referencia, { expires: 30, path: '/' });
    }

    // --- Lógica Principal (DOM LISTO) ---
    $(document).ready(function () {

        // ... (tus tooltips, menú hamburguesa, etc.) ...

        // ==========================================================
        // CORRECCIÓN: Ejecutar esto DENTRO de .ready()
        // ==========================================================
        var $imgProducto = $('#imagen-producto');

        // DEBUG: Comprobamos si encuentra la imagen
        console.log("Buscando #imagen-producto... Encontrados: " + $imgProducto.length);

        if ($imgProducto.length > 0) {
            var referencia = $imgProducto.data('id');
            if (referencia) {
                addVistoRecientemente(referencia);
            }
        }
    });

})(jQuery);