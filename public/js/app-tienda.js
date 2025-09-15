// =================================================================================
// ARCHIVO JAVASCRIPT UNIFICADO Y OPTIMIZADO PARA LA TIENDA ESTE CAMBIO ES CRITICO YA QUE EL FICHERO ES GENERADO POR GEMINI
// Fecha de finalización: 28 de agosto de 2025
// =================================================================================

(function ($) {
    'use strict';

    // =================================================================================
    // SETUP Y VARIABLES
    // =================================================================================

    const doneTypingInterval = 500;
    const tallasPorColorCache = {}; // Caché para selectores de tallas por color.


    // =================================================================================
    // FUNCIONES DE UTILIDAD
    // =================================================================================

    // Función de utilidad para "debouncing"
    function delay(callback, ms) {
        let timer = 0;
        return function () {
            const context = this;
            const args = arguments;
            clearTimeout(timer);
            timer = setTimeout(function () {
                callback.apply(context, args);
            }, ms || 0);
        };
    }

    // AÑADIMOS DE NUEVO LA FUNCIÓN QUE FALTABA
    function delete_cookie(name) {
        // Usamos el método del plugin jQuery Cookie que ya está cargado
        $.removeCookie(name, { path: '/' });
    }

    // --- Utilidades de Cookies (versión eficiente) ---
    function addVistoRecientemente(codigoProducto) {
        const cookieName = 'vistosRecientemente';
        const maxItems = 4;
        let vistos = $.cookie(cookieName);
        let productos = vistos ? vistos.split(',') : [];

        if (productos.includes(String(codigoProducto))) {
            return;
        }
        productos.unshift(codigoProducto);
        if (productos.length > maxItems) {
            productos = productos.slice(0, maxItems);
        }
        $.cookie(cookieName, productos.join(','), { expires: 7, path: '/' });
    }

    // --- Utilidades de Filtros ---
    function setFiltroAtributo(valor) {
        const atributo = valor.attr("name");
        const url = urlFiltroAtributos.replace("cadenaAtributo", atributo);
        window.location.pathname = url;
    }

    function setFabricanteFiltro(valor) {
        const atributo = valor.attr("name");
        const url = urlFiltroFabricante.replace("cadenaFabricante", atributo);
        window.location.pathname = url;
    }

    function setFiltroOrden(valor) {
        const value = valor.val();
        const url = urlFiltroOrden.replace("cadenaOrden", value);
        window.location.pathname = url;
    }

    function setFiltroColor(valor) {
        const value = valor.data("filtro");
        const url = urlFiltroColor.replace("cadenaColor", value);
        window.location.pathname = url;
    }

    // --- Extensión de jQuery ---
    jQuery.fn.extend({
        disable: function (state) {
            return this.each(function () {
                this.disabled = state;
            });
        }
    });

    // =================================================================================
    // LÓGICA PRINCIPAL DE LA TIENDA
    // =================================================================================

    // Versión "debounced" y centralizada para la actualización de precios
    const debouncedCargaPrecio = delay(function () {
        cargaDivPrecio(null);
    }, doneTypingInterval);

    // Función optimizada para el sumatorio de cantidades
    function modificaValorPrecio(elemento) {
        debouncedCargaPrecio();
        const dataColor = elemento.data('color');
        if (!dataColor) return;

        if (!tallasPorColorCache[dataColor]) {
            tallasPorColorCache[dataColor] = $('input[data-color="' + dataColor + '"]');
        }
        let valorActual = 0;
        tallasPorColorCache[dataColor].each(function () {
            const val = parseInt($(this).val(), 10);
            if (!isNaN(val)) {
                valorActual += val;
            }
        });
        const $cantidadTotal = $('#cantidadTotalCamisetas_' + dataColor);
        if (valorActual > 0) {
            $cantidadTotal.text(valorActual + " uds");
        } else {
            $cantidadTotal.text("");
        }
    }

    function redondeValorImput(elemento) {
        const min = parseInt(elemento.data('min'), 10);
        const max = parseInt(elemento.data('max'), 10);
        const val = parseInt(elemento.val(), 10) || 0;
        const pack = parseInt(elemento.data('pack'), 10);

        if (val >= max) {
            const valFinal = (max % pack !== 0) ? max - (max % pack) : max;
            elemento.val(valFinal);
            elemento.tooltip("destroy").tooltip({
                animated: 'fade',
                placement: 'bottom',
                title: 'STOCK actual ' + max,
                html: false
            }).tooltip("enable").tooltip("show").tooltip("disable");
        } else {
            if (pack > 1 && (val % pack) !== 0) {
                elemento.val(val + pack - (val % pack));
                elemento.attr('title', 'Venta en pack de ' + pack + ' uds').tooltip("enable").tooltip("show").tooltip("disable");
            } else if (val < min) {
                elemento.val(min);
            }
        }
    }

    function actualizaDatosProducto() {
        if (typeof pintaTallas === 'function') pintaTallas();
        const arrayTallas = $('.talla').map(function () {
            return {
                referencia: $(this).attr('name'),
                cantidad: $(this).val()
            };
        }).get();

        const arrayTrabajos = [];
        const arrayPersonalizacionesExistentes = [];
        $('.conten').each(function () {
            const $contenedor = $(this);
            if ($contenedor.find('.nuevaPersonalizacion').is(':visible')) {
                let cantidad = $contenedor.find('.numColores').val();
                if (cantidad == null || cantidad == 0) cantidad = 1;

                if (cantidad > -1) {
                    const archivos = $contenedor.find('.file-subido').map(function() { return $(this).html(); }).get().join(',');
                    arrayTrabajos.push({
                        codigo: $contenedor.find('.personalizacionTipo').val(),
                        cantidad: cantidad,
                        ubicacion: $contenedor.find('.ubicacion').val(),
                        archivo: archivos,
                        observaciones: $contenedor.find('.observaciones').val()
                    });
                }
            } else {
                arrayPersonalizacionesExistentes.push($contenedor.find('.personalizacionTipo2').val());
            }
        });

        const dobladoEmbolsado = $("#doblembol").is(':checked');
        return JSON.stringify({
            productos: arrayTallas,
            trabajos: arrayTrabajos,
            doblado: dobladoEmbolsado,
            personalizacionExistente: arrayPersonalizacionesExistentes
        });
    }

    function cargaDivPrecio(url) {
        const finalUrl = url || $("#div_precio").data('path');
        if (!finalUrl) return;

        $.ajax({
            type: "POST",
            url: finalUrl,
            data: actualizaDatosProducto(),
            contentType: "application/json; charset=utf-8"
        }).done(function (data) {
            const $divPrecio = $("#div_precio");
            $divPrecio.html(data).show();

            if (!$divPrecio.is(":visible")) {
                if (typeof cargaPersonalizacionesRapidas === 'function') cargaPersonalizacionesRapidas();
                $('#div_serigrafia').show();
            }

            $('#circuloPrecioUnidad').show();
            const totalProductosHtml = $("#div_precio_total_productos").html() || '';
            const precioUnidadHtml = $("#div_precio_precio_unidad").html() || '';

            $('#circuloPrecioUnidad').html("<p style='font-size: 16px; margin-top: 5px; margin-bottom: 0px; text-align:center; height:22px ;text-overflow:ellipsis;overflow: hidden'>" + totalProductosHtml + "</p><p style='margin-top: 5px; margin-bottom: 0px; text-align: center'>La unidad sale a:</p><p style='font-size: 28px; margin-top: 0px;margin-bottom: 0px; text-align:center; color: white;'>" + precioUnidadHtml + "</p>");

            if (typeof muestraDivTalla === 'function') muestraDivTalla();
        });
    }

    function muestraDivTalla() {
        if ($('.colorProductoPresupuesto.active').length > 0) {
            $('#tallas').show();
        } else {
            $('#tallas').hide();
            $('#div_serigrafia').hide();
            $("#div_precio").hide();
            $("#circuloPrecioUnidad").hide();
        }
    }

    const pintaTallas = function () {
        $('.talla').each(function () {
            if ($(this).val() > 0) {
                $(this).removeClass('colorclaro').addClass('coloroscuro');
            } else {
                $(this).removeClass('coloroscuro').addClass('colorclaro');
            }
        });
    };

    function addCarrito(productId, cantidad) {
        let url = "{{path('ss_tienda_carrito_add',{'idProducto':'productoidvalue','cantidad':cantidadvalue})}}";
        url = url.replace("productoidvalue", productId);
        url = url.replace("cantidadvalue", cantidad);
    }

    // =================================================================================
    // MANEJADORES DE EVENTOS (EVENT HANDLERS)
    // =================================================================================

    // --- Lógica de filtros unificada (VERSIÓN ACTUALIZADA) ---
    /**
     * Función para el filtro de fabricante (y otros parámetros simples).
     * @param {string} key - El nombre del parámetro, ej: "fabricante"
     * @param {string} value - El valor del parámetro, ej: "6"
     */
    function toggleQueryParam(key, value) {
        const url = new URL(window.location.href);
        const params = url.searchParams;

        if (params.get(key) === value) {
            params.delete(key);
        } else {
            params.set(key, value);
        }

        url.search = params.toString();
        window.location.href = url.toString();
    }

    /**
     * Función para el filtro de color (CORREGIDA).
     * @param {string} colorValue - El valor del color a manipular, ej: "#eedb71"
     */
    function toggleColorFilter(colorValue) {
        const url = new URL(window.location.href);
        const params = url.searchParams;
        const key = 'colores[]';
        const currentColors = params.getAll(key);
        const isSelected = currentColors.includes(colorValue);

        if (isSelected) {
            // Si el color ya estaba seleccionado, lo quitamos.
            const newColors = currentColors.filter(c => c !== colorValue);
            params.delete(key);
            newColors.forEach(c => params.append(key, c));
        } else {
            // Si el color no estaba seleccionado, lo añadimos.
            // ANTES (INCORRECTO): params.append(key, c);
            // AHORA (CORRECTO):
            params.append(key, colorValue);
        }

        url.search = params.toString();
        window.location.href = url.toString();
    }

    /**
     * Función principal (VERSIÓN FINAL Y SIMPLIFICADA)
     */
    function manejarFiltro(elemento) {
        const $elemento = $(elemento);

        if ($elemento.is("select")) {
            setFiltroOrden($elemento);
            return;
        }

        $elemento.toggleClass("checked");

        // --- Lógica de Decisión Definitiva ---

        // 1. Si es un filtro de Color -> siempre usa toggleColorFilter
        if ($elemento.is("div")) {
            toggleColorFilter($elemento.data("filtro"));
            return;
        }

        // 2. Si es un filtro de Fabricante -> siempre usa toggleQueryParam
        if ($elemento.hasClass("checkFabricante")) {
            toggleQueryParam('fabricante', $elemento.attr("name"));
            return;
        }

        // 3. Si no es ninguno de los anteriores, es un Atributo que cambia la RUTA.
        // Para este caso SÍ necesitamos el contexto para saber QUÉ ruta construir.
        const context = $elemento.data('tkm-context');
        let url;

        if (context === 'search') {
            const cadenaBusca = $elemento.data('tkm-cadena');
            if ($elemento.is("input")) {
                const atributoId = $elemento.attr("name");
                url = urlBuscaAtributos.replace("cadenaAtributo", atributoId).replace("cadenaBusca", cadenaBusca);
            }
        } else { // Contexto 'browse'
            if ($elemento.is("input")) {
                setFiltroAtributo($elemento);
            }
        }

        if (url) {
            window.location.href = url;
        }
    }

    $(document).on('click', 'input[data-tkm-filtro], div[data-tkm-filtro], input[data-tkm-filtro-busca], div[data-tkm-filtro-busca]', function() {
        manejarFiltro(this);
    });
    $(document).on('change', 'select[data-tkm-filtro]', function() {
        manejarFiltro(this);
    });

    // --- Búsqueda en vivo ---
    let currentSearchRequest = null;
    $(document).on('keyup', '[data-url-buscar]', delay(function () {
        const $input = $(this);
        const str = $input.val();
        const $liveSearchContainer = $('#livesearch');
        if (str.length < 3) {
            $liveSearchContainer.html("").css('border', '0px');
            if (currentSearchRequest) {
                currentSearchRequest.abort();
            }
            return;
        }
        let url = $input.data('url-buscar');
        url = url.replace("cadenaNombre", encodeURIComponent(str));
        if (currentSearchRequest) {
            currentSearchRequest.abort();
        }
        currentSearchRequest = $.get(url).done(function (response) {
            $liveSearchContainer.html(response).css('border', '1px solid #A5ACB2');
        }).fail(function(jqXHR, textStatus) {
            if (textStatus !== 'abort') console.error("Error en la búsqueda:", textStatus);
        }).always(function() {
            currentSearchRequest = null;
        });
    }, 350));

    // --- Interacciones de producto y personalización ---
    $(document).on('change paste click', '.modificadorPrecio', function () {
        modificaValorPrecio($(this));
    });
    $(document).on('keyup blur', '.modificadorPrecio', function () {
        modificaValorPrecio($(this));
    });
    $(document).on('keyup', '.observaciones', debouncedCargaPrecio);
    $(document).on('blur', '[data-min_max]', function () {
        redondeValorImput($(this));
    });
    $(document).on('keyup', '[data-min_max]', delay(function () {
        redondeValorImput($(this));
        modificaValorPrecio(($(this)));
    }, 500));
    $(document).on('keydown', '[data-toggle=just_number]', function (e) {
        if ([46, 8, 9, 27, 13, 110, 190].includes(e.keyCode) ||
            (e.keyCode === 65 && e.ctrlKey) || (e.keyCode === 67 && e.ctrlKey) || (e.keyCode === 88 && e.ctrlKey) ||
            (e.keyCode >= 35 && e.keyCode <= 39)) {
            return;
        }
        if ((e.shiftKey || (e.keyCode < 48 || e.keyCode > 57)) && (e.keyCode < 96 || e.keyCode > 105)) {
            e.preventDefault();
        }
    });
    $(document).on('change', '.personalizacionTipo', function () {
        const $this = $(this);
        const path = $this.data('path');
        const numColores = $this.find(":selected").data("colores");
        const dataNumTintas = $this.find(":selected").data("numerotintas");
        const $coloresContainer = $this.closest('.conten').find('.colores');
        if (numColores > 0) {
            let cadena = "<label>Tintas:</label><select style='height: 40px;' class='modificadorPrecio numColores' data-path='" + path + "'>";
            if (!$this.hasClass('conTinta')) {
                cadena += "<option value='-1'>Número de Tintas</option>";
            }
            for (let i = 0; i < numColores; i++) {
                const numTinta = i + 1;
                const isSelected = (parseInt(dataNumTintas) === numTinta) ? " selected" : "";
                cadena += "<option value='" + numTinta + "'" + isSelected + ">" + numTinta + " Tintas</option>";
            }
            cadena += "</select>";
            $coloresContainer.html(cadena).show();
        } else {
            $coloresContainer.empty().hide();
        }
        cargaDivPrecio(path);
    });
    $(document).on('change paste keyup', '.pedidoRapido', delay(function() {
        const contenedor = $(this).closest('[data-path]');
        let valorVar = contenedor.find($('.prrapidoCant')).val();
        if (valorVar > 0) {
            $(".pr_hide").show(200); $(".pr_show").hide(200);
        } else {
            $(".pr_hide").hide(200); $(".pr_show").show(200); return;
        }
        const arrayTrabajos = [];
        $('.pedidoRapidoTintas').each(function () {
            const cantidad = $(this).val();
            if (cantidad > 0) arrayTrabajos.push({
                codigo: $(this).find(':selected').data('codigo'),
                cantidad: cantidad,
                ubicacion: $(this).data("ubicacion")
            });
        });
        $('.pedidoRapidoDoblado:checked').each(function () {
            arrayTrabajos.push({
                codigo: $(this).data("codigo"),
                cantidad: 1,
                ubicacion: ""
            });
        });
        const datosJSON = JSON.stringify({
            personalizacion: contenedor.data("personalizacion"),
            modelo: contenedor.data('modelo'),
            color: contenedor.find(".pedidoRapidoColor").val(),
            cantidad: valorVar,
            trabajos: arrayTrabajos,
        });
        $.ajax({
            type: "POST",
            url: contenedor.data('path'),
            data: datosJSON,
            contentType: "application/json; charset=utf-8"
        }).done(function (data) {
            $(".prrapidoprecio").html(data);
        });
    }, 350));


    // =================================================================================
    // EXPORTACIÓN DE FUNCIONES PÚBLICAS (si se llaman desde el HTML)
    // =================================================================================
    window.addVistoRecientemente = addVistoRecientemente;
    window.redondeValorImput = redondeValorImput;
    window.cargaDivPrecio = cargaDivPrecio;
    window.addCarrito = addCarrito;
    window.muestraDivTalla = muestraDivTalla; // <-- AÑADE ESTA LÍNEA
    window.delete_cookie = delete_cookie; // <-- LA EXPORTAMOS PARA QUE SEA PÚBLICA
    window.pintaTallas = pintaTallas; // <-- AÑADE ESTA LÍNEA


})(jQuery);