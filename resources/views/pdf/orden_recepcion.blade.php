<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Recepción de Materiales - {{ $orden->numero_orden }}</title>
    <style>
        @page {
            size: letter portrait;
            margin: 18px 18px 25px 18px;
        }
        * {
            box-sizing: border-box;
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
        }
        body {
            color: #0f172a;
            line-height: 1.2;
            margin: 0;
            padding: 0;
            background-color: #ffffff;
        }

        /* ── Header Principal ──────────────────────────────── */
        .header-table {
            width: 100%;
            border: 1.5px solid #7890a8;
            border-collapse: collapse;
            margin-bottom: 6px;
            background-color: #ffffff;
        }
        .header-table td {
            border: 1px solid #7890a8;
            vertical-align: middle;
            padding: 4px 6px;
        }
        .logo-cell {
            width: 24%;
            text-align: center;
        }
        .title-cell {
            width: 52%;
            text-align: center;
        }
        .dark-banner {
            background-color: #1b365d;
            color: #ffffff;
            font-weight: bold;
            font-size: 11px;
            padding: 6px 10px;
            border-radius: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            text-align: center;
        }
        .title-sub-1 {
            font-size: 10px;
            font-weight: bold;
            color: #475569;
            margin-top: 3px;
            letter-spacing: 0.3px;
            text-transform: uppercase;
        }
        .title-sub-2 {
            font-size: 9px;
            font-weight: bold;
            color: #4c535d;
            margin-top: 1px;
        }
        .meta-cell {
            width: 24%;
            text-align: right;
            font-size: 8.5px;
            color: #1e293b;
        }
        .meta-label {
            font-weight: bold;
            text-transform: uppercase;
            color: #334155;
            font-size:12px
        }
        .meta-value {
            font-size: 12px;
            font-weight: bold;
            color: #0f172a;
            display: block;
            margin-top: 1px;
        }

        /* ── Banner Secciones ──────────────────────────────── */
        .section-header {
            background-color: #b8cbd9;
            border: 1px solid #7890a8;
            color: #0f172a;
            font-size: 10px;
            font-weight: bold;
            text-align: center;
            padding: 4px 6px;
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }

        /* ── Datos de Cliente / Motor ──────────────────────── */
        .info-container {
            border: 1px solid #7890a8;
            border-top: none;
            background-color: #f1f5f9;
            padding: 5px 6px 6px 6px;
            margin-bottom: 8.5px;
        }
        .info-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 4px 2px;
        }
        .info-table td {
            vertical-align: top;
            padding: 0;
        }

        .properity-section td {
            padding-bottom: 10px !important;
        }

        .field-label {
            display: block;
            font-size: 9.5px;
            font-weight: bold;
            text-transform: uppercase;
            color: #1e293b;
            margin-bottom: 2px;
            letter-spacing: 0.2px;
        }
        .field-pill {
            background-color: #ffffff;
            border: 1.5px solid #94a3b8;
            border-radius: 6px;
            padding: 3px 6px;
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
            text-align: center;
            color: #0f172a;
            min-height: 16px;
            line-height: 1.2;
        }

        /* ── Tabla de Componentes / Ítems ─────────────────── */
        .items-table {
            width: 100%;
            border-collapse: collapse;
            border: 1px solid #7890a8;
            background-color: #ffffff;
            margin-bottom: 6px;
        }
        .items-table thead {
            display: table-row-group;
        }
        .items-table th {
            background-color: #cfdbe6;
            color: #0f172a;
            font-size: 10px;
            font-weight: bold;
            text-transform: uppercase;
            padding: 5px 4px;
            border: 1px solid #7890a8;
            text-align: center;
            letter-spacing: 0.2px;
        }
        .items-table td {
            padding: 4px 5px;
            font-size: 10.5px;
            border: 1px solid #cbd5e1;
            vertical-align: middle;
            color: #0f172a;
        }
        .items-table tr:nth-child(even) {
            background-color: #f8fafc;
        }
        .items-table tr.empty-row td {
            height: 15px;
            padding: 1px 4px;
            font-size: 8px;
            color: #94a3b8;
        }

        .cell-item {
            text-align: center;
            font-weight: bold;
        }
        .cell-part-number {
            font-size: 12px !important;
            font-weight: 500;
            text-align: center;
            color: #0f172a;
        }
        .cell-descripcion {
            font-size: 12px !important;
            text-align: left;
            padding-left: 6px !important;
            color: #0f172a;
        }
        .cell-cant {
            text-align: center;
            font-weight: bold;
        }
        .cell-serie {
            font-size: 12px !important;
            text-align: center;
            color: #0f172a;
        }
        .cell-observacion {
            font-size: 12px !important;
            text-align: left;
            color: #334155;
        }

        /* ── Sección de Firmas ─────────────────────────────── */
        .signatures-container {
            border: 1px solid #7890a8;
            background-color: #ffffff;
            page-break-inside: avoid;
            margin-top: 4px;
        }
        .signatures-table {
            width: 100%;
            border-collapse: collapse;
        }
        .signatures-table th {
            background-color: #cfdbe6;
            color: #0f172a;
            font-size: 8px;
            font-weight: bold;
            text-transform: uppercase;
            padding: 4px 4px;
            border: 1px solid #7890a8;
            text-align: center;
        }
        .signatures-table td {
            border: 1px solid #7890a8;
            height: 42px;
            vertical-align: bottom;
            text-align: center;
            padding: 4px;
        }

        /* ── Footer ────────────────────────────────────────── */
        .doc-footer-table {
            position: fixed;
            bottom: 2px;
            left: 6px;
            right: 6px;
            height: 16px;
            border-top: 0.5px solid #94a3b8;
            padding-top: 2px;
            border-collapse: collapse;
        }
        .doc-footer-table td {
            border: none;
            font-size: 7.5px;
            color: #475569;
            padding: 0;
            vertical-align: middle;
        }
    </style>
</head>
<body>

    @php
        $logoPath = public_path('images/logo_aerocentro.jpg');
        $logoBase64 = file_exists($logoPath) ? base64_encode(file_get_contents($logoPath)) : null;
    @endphp

    <!-- 1. Cabecera Principal -->
    <table class="header-table">
        <tr>
            <td class="logo-cell">
                @if($logoBase64)
                    <img src="data:image/jpeg;base64,{{ $logoBase64 }}" style="max-height: 48px; max-width: 150px;" alt="Aerocentro" />
                @else
                    <div style="font-weight: bold; font-size: 16px; letter-spacing: 0.5px; color: #1e3a5f;">AEROCENTRO</div>
                    <div style="font-size: 8px; color: #475569; font-weight: bold;">AIR SERVICES</div>
                @endif
            </td>

            <td class="title-cell">
                <div class="dark-banner">
                    @if(($orden->tipo ?? 'motor') === 'ndt')
                        LISTADO DE PARTES ENVIADAS AL TALLER DE NDT
                    @else
                        RECEPCIÓN DE MATERIALES, COMPONENTES, EQUIPOS Y HERRAMIENTAS
                    @endif
                </div>
                <!-- <div class="title-sub-2">
                    FORMULARIO: {{ $orden->numero_orden }}
                </div> -->
            </td>

            <td class="meta-cell">
                <span class="meta-label">FECHA DE EMISIÓN:</span>
                <span class="meta-value">{{ $orden->created_at->format('d/m/Y') }}</span>
            </td>
        </tr>
    </table>

    <!-- 2. Datos del Proyecto y Propietario -->
    <div class="section-header">
        @if(($orden->tipo ?? 'motor') === 'ndt')
            DATOS DEL PROYECTO Y Cliente
        @else
           DATOS DEL CLIENTE Y MOTOR
        @endif
    </div>
    <div class="info-container">
        <table class="info-table">
            <tr class="properity-section">
                <td style="width: 33%;">
                    <span class="field-label">MARCA</span>
                    <div class="field-pill">{{ ucfirst($orden->marca) }}</div>
                </td>
                <td style="width: 34%;">
                    <span class="field-label">MODELO</span>
                    <div class="field-pill">{{ $orden->modelo }}</div>
                </td>
                <td style="width: 33%;">
                    <span class="field-label">SERIE</span>
                    <div class="field-pill">{{ $orden->serie }}</div>
                </td>
            </tr>
            <tr>
                <td style="width: 33%;">
                    <span class="field-label">Nº ORDEN TRABAJO</span>
                    <div class="field-pill">{{ $orden->numero_orden }}</div>
                </td>
                <td style="width: 44%;">
                    <span class="field-label">PROPIETARIO</span>
                    <div class="field-pill">{{ $orden->cliente->nombre_completo ?? 'N/A' }}</div>
                </td>
                <td style="width: 23%;">
                    <span class="field-label">MATRICULA</span>
                    <div class="field-pill">{{ $orden->matricula ?? 'N/A' }}</div>
                </td>
            </tr>
        </table>
    </div>

    <!-- 3. Tabla de Componentes / Ítems -->
    <table class="items-table">
        <thead>
            <tr>
                <th style="width: 5%;">ITEM</th>
                <th style="width: 20%;">PART NUMBER</th>
                <th style="width: 35%; text-align: left; padding-left: 6px;">DESCRIPCIÓN</th>
                <th style="width: 6%;">CANT</th>
                <th style="width: 15%;">SERIE</th>
                <th style="width: 19%;">OBSERVACIÓN</th>
            </tr>
        </thead>
        <tbody>
            @foreach($orden->items as $idx => $item)
                <tr>
                    <td class="cell-item">{{ $item->numero_item ?? ($idx + 1) }}</td>
                    <td class="cell-part-number">{{ !empty(trim($item->part_number ?? '')) ? $item->part_number : '-' }}</td>
                    <td class="cell-descripcion">{{ $item->componente }}</td>
                    <td class="cell-cant">{{ $item->cantidad }}</td>
                    <td class="cell-serie">{{ !empty(trim($item->serie ?? '')) ? $item->serie : '-' }}</td>
                    <td class="cell-observacion">{{ !empty(trim($item->observacion ?? '')) ? $item->observacion : '-' }}</td>
                </tr>
            @endforeach

            {{-- Completar filas vacías hasta mínimo 24 o 28 filas según ajuste visual --}}
            @php
                $filasVacias = max(0, 24 - count($orden->items));
            @endphp
            @for($i = 0; $i < $filasVacias; $i++)
                @php
                    $numFila = count($orden->items) + $i + 1;
                @endphp
                <tr class="empty-row">
                    <td class="cell-item" style="font-size: 8px; font-weight: normal;">{{ $numFila }}</td>
                    <td>&nbsp;</td>
                    <td>&nbsp;</td>
                    <td>&nbsp;</td>
                    <td>&nbsp;</td>
                    <td>&nbsp;</td>
                </tr>
            @endfor
        </tbody>
    </table>

    <!-- 4. Sección de Firmas (Estilo Formulario Imagen) -->
    <div class="section-header" style="border-bottom: none;">
        VALIDACIÓN Y FIRMAS
    </div>
    <div class="signatures-container">
        <table class="signatures-table">
            <thead>
                <tr>
                    <th style="width: 35%;">RECIBIDO POR (NOMBRE Y FIRMA)</th>
                    <th style="width: 15%;">FECHA DE RECEPCIÓN</th>
                    <th style="width: 35%;">CONFIRMADO POR (TÉCNICO / SUPERVISOR)</th>
                    <th style="width: 15%;">FECHA</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>&nbsp;</td>
                    <td>&nbsp;</td>
                    <td>&nbsp;</td>
                    <td>&nbsp;</td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- 5. Pie de página dinámico por script -->
    <script type="text/php">
        if (isset($pdf)) {
            $pdf->page_script(function ($pageNumber, $pageCount, $canvas, $fontMetrics) {
                $font = $fontMetrics->getFont("sans-serif", "normal");
                $size = 7.5;
                $color = array(0.28, 0.33, 0.41); // #475569

                // Línea superior del footer
                $yLine = $canvas->get_height() - 20;
                $canvas->line(18, $yLine, $canvas->get_width() - 18, $yLine, array(0.58, 0.64, 0.72), 0.5);

                // Coordenada Y alineada para ambos textos
                $yText = $canvas->get_height() - 14;

                // Texto a la izquierda
                $leftText = "Documento oficial emitido por el Sistema Aerocentro Almacén \xE2\x80\xA2 " . date('d/m/Y H:i:s');
                $canvas->text(18, $yText, $leftText, $font, $size, $color);

                // Texto a la derecha (Página X de Y)
                $rightText = "Página " . $pageNumber . " de " . $pageCount;
                $width = $fontMetrics->getTextWidth($rightText, $font, $size);
                $xRight = $canvas->get_width() - 18 - $width;
                $canvas->text($xRight, $yText, $rightText, $font, $size, $color);
            });
        }
    </script>

</body>
</html>