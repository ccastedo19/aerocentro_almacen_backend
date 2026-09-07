<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Recepción de Materiales - {{ $orden->numero_orden }}</title>
    <style>
        @page {
            size: letter portrait;
            margin: 32px 22px 35px 22px;
        }
        * {
            box-sizing: border-box;
            font-family: sans-serif;
        }
        body {
            font-family: sans-serif;
            font-size: 9.5px;
            color: #000000;
            line-height: 1.3;
            margin: 0;
            padding: 0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }

        /* ── Header Principal ──────────────────────────────── */
        .header-table {
            width: 100%;
            border: 1.5px solid #000000;
            border-collapse: collapse;
            background-color: #ffffff;
        }
        .header-table td {
            border: 1px solid #000000;
            vertical-align: middle;
            padding: 6px 8px;
        }
        .logo-cell {
            width: 26%;
            text-align: center;
        }
        .title-cell {
            width: 48%;
            text-align: center;
        }
        .title-main {
            font-family: sans-serif;
            font-size: 14px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            line-height: 1.25;
            color: #000000;
        }
        .title-sub {
            font-family: sans-serif;
            font-size: 8.5px;
            margin-top: 4px;
            font-weight: bold;
            color: #000000;
            letter-spacing: 0.2px;
        }
        .meta-cell {
            width: 26%;
            padding: 6px 10px !important;
            vertical-align: middle !important;
            background-color: #ffffff;
        }
        .meta-item {
            font-size: 9px;
            line-height: 1.3;
        }
        .meta-label {
            font-size: 8px;
            font-weight: bold;
            text-transform: uppercase;
            color: #000000;
            display: block;
        }
        .meta-value {
            font-size: 10.5px;
            font-weight: bold;
            color: #000000;
        }

        /* ── Datos de Cliente / Motor ─── */
        .info-table {
            width: 100%;
            border-left: 1.5px solid #000000;
            border-right: 1.5px solid #000000;
            border-bottom: 1.5px solid #000000;
            border-top: none !important;
            border-collapse: collapse;
            background-color: #ffffff;
        }
        .info-table tr,
        .info-table td {
            border-top: none !important;
        }
        .info-table td {
            border: 1px solid #000000;
            padding: 5px 6px;
            vertical-align: middle;
        }
        .field-label {
            display: block;
            font-size: 8px;
            font-weight: bold;
            text-transform: uppercase;
            color: #000000;
            margin-bottom: 2px;
            letter-spacing: 0.3px;
            text-align: left;
        }
        .field-val {
            font-size: 10.5px;
            font-weight: bold;
            color: #000000;
            text-transform: uppercase;
            min-height: 15px;
            text-align: center;
        }

        /* ── Tabla de Componentes / Ítems ─────────────────── */
        .items-table {
            width: 100%;
            border-left: 1.5px solid #000000;
            border-right: 1.5px solid #000000;
            border-bottom: 1.5px solid #000000;
            border-top: none !important;
            border-collapse: collapse;
            background-color: #ffffff;
        }

        .items-table thead {
            display: table-header-group;
        }
        .items-table thead tr,
        .items-table thead th {
            border-top: none !important;
        }
        .items-table tr {
            page-break-inside: avoid;
        }
        .items-table th {
            background-color: #ffffff;
            color: #000000;
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
            padding: 6px 6px;
            border: 1px solid #000000;
            text-align: center;
            letter-spacing: 0.3px;
        }
        .items-table td {
            padding: 4px 6px;
            font-size: 9px;
            border: 1px solid #000000;
            vertical-align: middle;
            color: #000000;
            background-color: #ffffff;
        }
        .items-table tr.empty-row td {
            height: 15px;
            padding: 2px 4px;
            background-color: #ffffff;
            color: #000000;
        }
        .cell-part-number {
            font-family: sans-serif;
            font-size: 10px;
            letter-spacing: 0.3px;
            text-align: center;
            color: #000000;
        }
        .cell-descripcion {
            font-family: sans-serif;
            font-size: 9.5px;
            text-align: left;
            padding-left: 8px !important;
            color: #000000;
        }
        .cell-serie {
            font-family: sans-serif;
            font-size: 9.5px;
            font-weight: normal;
            text-align: center;
            color: #000000 !important;
        }
        .text-center {
            text-align: center;
        }
        .text-left {
            text-align: left;
        }
        .text-right {
            text-align: right;
        }

        /* ── Firmas ────────────────────────────────────────── */
        .signatures-table {
            width: 100%;
            margin-top: 90px;
            border-collapse: collapse;
            page-break-inside: avoid;
        }
        .signatures-table td {
            width: 45%;
            vertical-align: top;
            text-align: center;
            padding: 0 25px;
        }
        .signature-box {
            padding-top: 5px;
            border-top: 1.5px solid #000000;
        }
        .signature-title {
            font-size: 9.5px;
            font-weight: bold;
            text-transform: uppercase;
            color: #000000;
            margin-bottom: 2px;
            letter-spacing: 0.3px;
        }
        .signature-subtitle {
            font-size: 8px;
            color: #000000;
            text-transform: uppercase;
            font-weight: bold;
            letter-spacing: 0.3px;
        }

        /* ── Footer ────────────────────────────────────────── */
        .doc-footer-table {
            position: fixed;
            bottom: -15px;
            left: 0px;
            right: 0px;
            height: 20px;
            border-top: 0.5px solid #000000;
            padding-top: 4px;
            border-collapse: collapse;
        }
        .doc-footer-table td {
            border: none;
            font-size: 7.5px;
            color: #000000;
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
                    <img src="data:image/jpeg;base64,{{ $logoBase64 }}" style="max-height: 52px; max-width: 155px;" alt="Aerocentro" />
                @else
                    <div style="font-weight: bold; font-size: 16px; letter-spacing: 0.5px; color: #0284c7;">AEROCENTRO</div>
                    <div style="font-size: 8px; color: #000000; font-weight: bold;">AIR SERVICES</div>
                @endif
            </td>

            <td class="title-cell">
                <div class="title-main">
                    RECEPCIÓN DE MATERIALES,<br>
                    COMPONENTES, EQUIPOS Y<br>
                    HERRAMIENTAS
                </div>
                <div class="title-sub">
                    FORMULARIO: H12-INS-01
                </div>
            </td>

            <td class="meta-cell">
                <div class="meta-item">
                    <span class="meta-label">Fecha de Emisión:</span>
                    <span class="meta-value">{{ $orden->created_at->format('d/m/Y') }}</span>
                </div>
            </td>
        </tr>
    </table>

    <!-- 2. Datos del Cliente y Motor -->
    <table class="info-table">
        <tr>
            <td style="width: 33%;">
                <span class="field-label">MARCA</span>
                <div class="field-val">{{ ucfirst($orden->marca) }}</div>
            </td>
            <td style="width: 33%;">
                <span class="field-label">MODELO</span>
                <div class="field-val">{{ $orden->modelo }}</div>
            </td>
            <td style="width: 34%;">
                <span class="field-label">SERIE</span>
                <div class="field-val">{{ $orden->serie }}</div>
            </td>
        </tr>
        <tr>
            <td style="width: 30%;">
                <span class="field-label">Nº ORDEN TRABAJO</span>
                <div class="field-val">{{ $orden->numero_orden }}</div>
            </td>
            <td style="width: 45%;">
                <span class="field-label">PROPIETARIO</span>
                <div class="field-val">{{ $orden->cliente->nombre_completo ?? 'N/A' }}</div>
            </td>
            <td style="width: 25%;">
                <span class="field-label">MATRICULA</span>
                <div class="field-val">{{ $orden->matricula ?? 'N/A' }}</div>
            </td>
        </tr>
    </table>

    <!-- 3. Tabla de Componentes / Ítems -->
    <table class="items-table">
        <thead>
            <tr>
                <th style="width: 5%;">ITEM</th>
                <th style="width: 21%;">PART NUMBER</th>
                <th style="width: 34%; text-align: left; padding-left: 8px;">DESCRIPCIÓN</th>
                <th style="width: 5%;">CANT</th>
                <th style="width: 15%;">SERIE</th>
                <th style="width: 30%;">OBSERVACIÓN</th>
            </tr>
        </thead>
        <tbody>
            @foreach($orden->items as $idx => $item)
                <tr>
                    <td class="text-center" style="font-weight: bold;">{{ $item->numero_item ?? ($idx + 1) }}</td>
                    <td class="cell-part-number">{{ $item->part_number ?? '-' }}</td>
                    <td class="cell-descripcion">{{ $item->componente }}</td>
                    <td class="text-center" style="font-weight: bold;">{{ $item->cantidad }}</td>
                    <td class="cell-serie">{{ $item->serie ?? '-' }}</td>
                    <td class="text-left">{{ $item->observacion ?? '-' }}</td>
                </tr>
            @endforeach

            {{-- Completar hasta un mínimo de 30 ítems --}}
            @php
                $filasVacias = max(0, 30 - count($orden->items));
            @endphp
            @for($i = 0; $i < $filasVacias; $i++)
                @php
                    $numFila = count($orden->items) + $i + 1;
                @endphp
                <tr class="empty-row">
                    <td class="text-center" style="font-size: 8px;">{{ $numFila }}</td>
                    <td>&nbsp;</td>
                    <td>&nbsp;</td>
                    <td>&nbsp;</td>
                    <td>&nbsp;</td>
                    <td>&nbsp;</td>
                </tr>
            @endfor
        </tbody>
    </table>

    <!-- 4. Firmas -->
    <table class="signatures-table">
        <tr>
            <td>
                <div class="signature-box">
                    <div class="signature-title">ENTREGADO CONFORME</div>
                    <div class="signature-subtitle">Firma y Aclaración</div>
                </div>
            </td>
            <td style="width: 10%;"></td>
            <td>
                <div class="signature-box">
                    <div class="signature-title">RECIBIDO CONFORME</div>
                    <div class="signature-subtitle">Firma y Aclaración</div>
                </div>
            </td>
        </tr>
    </table>

    <!-- 5. Pie de página fijo al fondo -->
    <table class="doc-footer-table">
        <tr>
            <td style="text-align: left;">
                Documento oficial emitido por el Sistema Aerocentro Almacén &bull; {{ now()->format('d/m/Y H:i:s') }}
            </td>
            <td style="text-align: right; width: 120px;">
                Página 1 de 1
            </td>
        </tr>
    </table>

    <script type="text/php">
        if (isset($pdf)) {
            $pdf->page_script(function ($pageNumber, $pageCount, $canvas, $fontMetrics) {
                $text = "Página " . $pageNumber . " de " . $pageCount;
                $font = $fontMetrics->getFont("sans-serif", "normal");
                $size = 7.5;
                $width = $fontMetrics->getTextWidth($text, $font, $size);
                $x = $canvas->get_width() - 22 - $width;
                $y = $canvas->get_height() - 20;
                $canvas->text($x, $y, $text, $font, $size, array(0,0,0));
            });
        }
    </script>

</body>
</html>