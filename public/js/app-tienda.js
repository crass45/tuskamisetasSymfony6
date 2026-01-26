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

    $(document).on('change', '.personalizacionTipo', function() {
        var $row = $(this).closest('.personalization-row');
        var $optionSelected = $(this).find('option:selected');

        // Recuperamos los datos. Antes era un array de strings, ahora es un array de objetos
        var areas = $optionSelected.data('areas');
        var $ubicacionSelect = $row.find('.ubicacion');
        var $previewContainer = $row.find('.area-preview'); // El contenedor de la foto

        // Limpiamos el select
        $ubicacionSelect.empty();

        if (areas && areas.length > 0) {
            $.each(areas, function(index, area) {
                // DETECCIÓN INTELIGENTE:
                // Si 'area' es un objeto (nueva lógica), usamos area.name
                // Si 'area' es texto (lógica antigua por si acaso), usamos area directamente
                var nombre = (typeof area === 'object' && area !== null) ? area.name : area;
                var img = (typeof area === 'object' && area !== null) ? area.img : '';
                var w = (typeof area === 'object' && area !== null) ? area.width : '';
                var h = (typeof area === 'object' && area !== null) ? area.height : '';

                // Creamos la opción usando el NOMBRE para el texto y value
                var $opt = $('<option>', {
                    value: nombre,
                    text: nombre,
                    'data-img': img,
                    'data-width': w,
                    'data-height': h
                });
                $ubicacionSelect.append($opt);
            });

            // Forzamos el evento change para que se cargue la foto de la primera opción
            $ubicacionSelect.trigger('change');
        } else {
            $ubicacionSelect.append('<option value="">Sin ubicaciones definidas</option>');
            if($previewContainer.length) $previewContainer.hide();
        }

        // Actualizamos el input oculto de max-colores si lo usas
        var maxColores = $optionSelected.data('max-colores');
        // 2. GESTIÓN DE TINTAS (Max Colores en Combo)
        var maxColores = parseInt($optionSelected.data('max-colores')) || 0;
        var $tintasContainer = $row.find('.tintas-container');
        var $tintasSelect = $row.find('.tintas'); // Ahora es un select

        // Limpiamos las opciones anteriores
        $tintasSelect.empty();

        if (maxColores > 1) {
            // Generamos las opciones desde 1 hasta maxColores
            for (var i = 1; i <= maxColores; i++) {
                var texto = i + (i === 1 ? ' Color' : ' Colores');
                $tintasSelect.append($('<option>', {
                    value: i,
                    text: texto
                }));
            }

            // Mostramos el selector
            $tintasContainer.show();
        } else {
            // Si el máximo es 0 o 1 (ej: impresión digital, bordado simple o sin definir)
            // Ponemos 1 por defecto y ocultamos
            $tintasSelect.append('<option value="1">1 Color</option>');
            $tintasContainer.hide();
        }
    });

// NUEVO: Evento para cambiar la foto cuando cambias la ubicación
    $(document).on('change', '.ubicacion', function() {
        var $row = $(this).closest('.personalization-row');
        var $selectedOption = $(this).find('option:selected');
        var imgSrc = $selectedOption.data('img');
        var w = $selectedOption.data('width');
        var h = $selectedOption.data('height');

        var $previewContainer = $row.find('.area-preview');

        // Si no existe el contenedor (porque no has actualizado el twig aún), no hacemos nada
        if ($previewContainer.length === 0) return;

        if (imgSrc && imgSrc !== '') {
            $previewContainer.find('.area-img').attr('src', imgSrc);

            var textoMedidas = '';
            if (w > 0 || h > 0) {
                textoMedidas = 'Medidas máx: ' + (parseFloat(w)||0) + ' x ' + (parseFloat(h)||0) + ' cm';
            }
            $previewContainer.find('.area-dims').text(textoMedidas);

            $previewContainer.slideDown();
        } else {
            $previewContainer.slideUp();
        }
    });

})(jQuery);