<?php
/**
 * [SUMARIO] PAINEL DE QUALIDADE (V11 - VARIAVEL CENTRALIZADA DE PAGINAÇÃO)
 * ----------------------------------------------------------------------------
 * [CONFIG] .... Configurações PHP e Paginação (EDITAR AQUI A QTDE)
 * [STYLE] ..... CSS (Cores, Layout, Modal, Responsividade + Coluna Marca)
 * [JS_TOOL] ... Javascript do Tooltip e Modal (ORIGINAL)
 * [FUNC] ...... Funções de Análise, Cálculo e Extração JSON
 * [RENDER] .... Loop Principal e HTML da Lista (SEM SCROLL INFINITO)
 * ----------------------------------------------------------------------------
 */
namespace hardness;

global $g, $confUsuario;

// =============================================================================
// [CONFIG] CONFIGURAÇÕES + PAGINAÇÃO CENTRALIZADA
// =============================================================================
error_reporting(E_ALL & ~E_NOTICE);
ini_set('display_errors', 1);

// --- [CONFIGURAÇÃO DE ITENS POR PÁGINA] ---
// Altere este valor para mudar em todo o sistema (SQL e JS)
$qtdePorPagina = 75;
// ------------------------------------------

$limit = $qtdePorPagina;

// pagina atual via POST (AJAX) - fallback
$page = isset($_POST['page']) ? (int) $_POST['page'] : 1;
if ($page < 1)
    $page = 1;

// pageSize via POST (AJAX) - opcional (caso o JS envie algo diferente)
if (isset($_POST['pageSize'])) {
    $tmp = (int) $_POST['pageSize'];
    if ($tmp > 0)
        $limit = $tmp;
}

// offset legado (se alguém ainda chamar com GET offset)
$offset = 0;
if (isset($_GET['offset'])) {
    $offset = (int) $_GET['offset'];
    if ($offset < 0)
        $offset = 0;
}
else {
    $offset = ($page - 1) * $limit;
}

// modo ajax
$isAjax = (isset($_POST['ajax']) && (int) $_POST['ajax'] === 1);

// apiMode removido (não será usado mais para scroll/infinite)
$apiMode = 0;

// =============================================================================
// [FUNC] FUNÇÕES DE CÁLCULO + MARCA JSON (MANTIDAS)
// =============================================================================

function extrairMarcaJsonAnyMarket($jsonString)
{
    if (empty($jsonString))
        return "";
    $obj = json_decode($jsonString);
    if (isset($obj->content[0]->brand->name)) {
        return trim($obj->content[0]->brand->name);
    }
    return "";
}

function analiseTitulo($titulo)
{
    $len   = mb_strlen(trim($titulo));
    $n     = 0;
    $regra = "";
    if ($len < 30) {
        $n     = 1;
        $regra = "< 30 caracteres";
    }
    elseif ($len < 40) {
        $n     = 2;
        $regra = "30 a 39 caracteres";
    }
    elseif ($len < 50) {
        $n     = 3;
        $regra = "40 a 49 caracteres";
    }
    elseif ($len < 60) {
        $n     = 4;
        $regra = "50 a 59 caracteres";
    }
    elseif ($len < 90) {
        $n     = 5;
        $regra = "60 a 89 caracteres";
    }
    else {
        $n     = 0;
        $regra = "> 90 chars (Excesso)";
    }
    return ['nota' => $n, 'valor' => $len . ' chars', 'regra' => $regra, 'peso' => 3];
}

function analiseDescricao($html)
{
    $txt   = trim(strip_tags($html));
    $len   = mb_strlen($txt);
    $n     = 0;
    $regra = "";
    if ($len < 200) {
        $n     = 1;
        $regra = "< 200 caracteres";
    }
    elseif ($len < 400) {
        $n     = 2;
        $regra = "200 a 399 caracteres";
    }
    elseif ($len < 600) {
        $n     = 3;
        $regra = "400 a 599 caracteres";
    }
    elseif ($len < 2000) {
        $n     = 4;
        $regra = "600 a 1999 caracteres";
    }
    elseif ($len <= 4000) {
        $n     = 5;
        $regra = "2000 a 4000 caracteres";
    }
    else {
        $n     = 0;
        $regra = "> 4000 chars (Excesso)";
    }
    return ['nota' => $n, 'valor' => $len . ' chars', 'regra' => $regra, 'peso' => 3];
}

function analiseImagens($row)
{
    $qtd = 0;
    for ($i = 1; $i <= 10; $i++)
        if (!empty($row["D001E_Imagem_$i"]))
            $qtd++;
    $n     = 0;
    $regra = "";
    if ($qtd < 2) {
        $n     = 1;
        $regra = "< 2 imagens";
    }
    elseif ($qtd < 3) {
        $n     = 3;
        $regra = "2 imagens";
    }
    elseif ($qtd < 5) {
        $n     = 4;
        $regra = "3 a 4 imagens";
    }
    elseif ($qtd <= 10) {
        $n     = 5;
        $regra = "5 a 10 imagens";
    }
    else {
        $n     = 0;
        $regra = "> 10 imagens";
    }
    return ['nota' => $n, 'valor' => $qtd . ' fotos', 'regra' => $regra, 'peso' => 3];
}

function analiseAtributos($row)
{
    $count = 0;
    if (!empty($row['D001E_EAN']))
        $count++;
    if (!empty($row['D001E_garantia']))
        $count++;
    if (!empty($row['D001E_peso']))
        $count++;
    if (!empty($row['D001E_altura']))
        $count++;
    if (!empty($row['D001E_largura']))
        $count++;
    if (!empty($row['D001E_comprimento']))
        $count++;

    $n     = 0;
    $regra = "";
    if ($count < 2) {
        $n     = 1;
        $regra = "< 2 atributos";
    }
    elseif ($count < 4) {
        $n     = 3;
        $regra = "2 a 3 atributos";
    }
    elseif ($count < 7) {
        $n     = 4;
        $regra = "4 a 6 atributos";
    }
    elseif ($count < 20) {
        $n     = 5;
        $regra = "7 a 19 atributos";
    }
    else {
        $n     = 5;
        $regra = "Completo";
    }

    return ['nota' => $n, 'valor' => $count . ' preenchidos', 'regra' => $regra, 'peso' => 1];
}

function analiseImagemEspecial($row)
{
    $nota = (int) ($row['D001E_pont_img_especial'] ?? 1);
    if ($nota < 1 || $nota > 5)
        $nota = 1;
    $regra = "";
    switch ($nota) {
        case 5:
            $regra = "Ambientadas, componentes, embalagem e ângulos";
            break;
        case 4:
            $regra = "Componentes, embalagem e ângulos";
            break;
        case 3:
            $regra = "Embalagem e ângulos";
            break;
        case 2:
            $regra = "Alguns ângulos";
            break;
        case 1:
        default:
            $regra = "Recortada/Zero ângulo ou pendente";
            break;
    }
    return ['nota' => $nota, 'valor' => 'Manual (' . $nota . ')', 'regra' => $regra, 'peso' => 1];
}

function analiseVideo($row)
{
    return ['nota' => 0, 'valor' => 'Sem Vídeo', 'regra' => 'Sem Short (0)', 'peso' => 1];
}

function getCorNota($n)
{
    switch ($n) {
        case 6:
            return "var(--score-6)";
        case 5:
            return "var(--score-5)";
        case 4:
            return "var(--score-4)";
        case 3:
            return "var(--score-3)";
        case 2:
            return "var(--score-2)";
        default:
            return "var(--score-1)";
    }
}

function gerarTooltipHtml($titulo, $arrAnalise)
{
    return "<table class='tt-table'>
        <tr><th colspan='2' class='tt-head'>ANÁLISE: $titulo</th></tr>
        <tr><td class='tt-row'>Valor Atual</td><td class='tt-val'>{$arrAnalise['valor']}</td></tr>
        <tr><td class='tt-row'>Regra</td><td class='tt-val'>{$arrAnalise['regra']}</td></tr>
        <tr><td class='tt-row'>Peso</td><td class='tt-val'>{$arrAnalise['peso']}</td></tr>
        <tr class='tt-foot'><td class='tt-row'>Nota Calc.</td><td class='tt-val'>{$arrAnalise['nota']}</td></tr>
    </table>";
}

// =============================================================================
// [RENDER] FUNÇÃO DE LINHA (MANTIDA)
// =============================================================================
function renderQualityRow($row)
{
    // 1. Marca (com fallback JSON + update)
    $marca       = isset($row['D001E_Marca']) ? $row['D001E_Marca'] : '';
    $updateMarca = false;

    if (empty($marca) && !empty($row['D001E_Json_Nativo'])) {
        $marcaExtraida = extrairMarcaJsonAnyMarket($row['D001E_Json_Nativo']);
        if (!empty($marcaExtraida)) {
            $marca       = $marcaExtraida;
            $updateMarca = true;
        }
        else {
            $marca = "ND";
        }
    }

    // 2. Cálculos
    $resT  = analiseTitulo($row['D001E_Titulo']);
    $resD  = analiseDescricao($row['D001E_Descricao']);
    $resI  = analiseImagens($row);
    $resA  = analiseAtributos($row);
    $resIE = analiseImagemEspecial($row);
    $resV  = analiseVideo($row);

    $soma  = ($resT['nota'] * 3) + ($resD['nota'] * 3) + ($resI['nota'] * 3) + ($resIE['nota'] * 1) + ($resA['nota'] * 1) + ($resV['nota'] * 1);
    $final = floor($soma / 11);
    $final = max(1, min(5, $final));
    if ($final == 5 && $resV['nota'] > 0)
        $final = 6;

    // 3. UPDATE SQL (todas as pontuações + marca)
    $idProd  = (int) $row['D001E_Id'];
    $sqlSets = [];

    if ($row['D001E_Status_Pontuacao'] != $final)
        $sqlSets[] = "D001E_Status_Pontuacao = $final";
    if ($row['D001E_pont_titulo'] != $resT['nota'])
        $sqlSets[] = "D001E_pont_titulo = {$resT['nota']}";
    if ($row['D001E_pont_desc'] != $resD['nota'])
        $sqlSets[] = "D001E_pont_desc = {$resD['nota']}";
    if ($row['D001E_pont_img'] != $resI['nota'])
        $sqlSets[] = "D001E_pont_img = {$resI['nota']}";
    if ($row['D001E_pont_espec'] != $resA['nota'])
        $sqlSets[] = "D001E_pont_espec = {$resA['nota']}";

    if ($updateMarca) {
        $marcaSafe = mysql_real_escape_string($marca);
        $sqlSets[] = "D001E_Marca = '$marcaSafe'";
    }

    if (!empty($sqlSets)) {
        $sqlUpdate = "UPDATE D001E SET " . implode(', ', $sqlSets) . " WHERE D001E_Id = $idProd";
        mysql_query($sqlUpdate);
    }

    // 4. Visual
    switch ($final) {
        case 6:
            $c = "var(--score-6)";
            $p = 100;
            $l = "Ótima";
            break;
        case 5:
            $c = "var(--score-5)";
            $p = 85;
            $l = "Muito Boa";
            break;
        case 4:
            $c = "var(--score-4)";
            $p = 70;
            $l = "Boa";
            break;
        case 3:
            $c = "var(--score-3)";
            $p = 50;
            $l = "Média";
            break;
        case 2:
            $c = "var(--score-2)";
            $p = 30;
            $l = "Ruim";
            break;
        default:
            $c = "var(--score-1)";
            $p = 15;
            $l = "Muito Ruim";
            break;
    }

    $imgCapa   = $row['D001E_Imagem_1'] ?: "https://via.placeholder.com/100x100?text=Sem+Img";
    $titulo    = htmlspecialchars($row['D001E_Titulo'], ENT_QUOTES);
    $sku       = htmlspecialchars($row['D001E_D001_Codigo_Produto'], ENT_QUOTES);
    $descRaw   = $row['D001E_Descricao'];
    $marcaHtml = htmlspecialchars($marca, ENT_QUOTES);

    $specHtml = "";
    if (!empty($row['D001E_EAN']))
        $specHtml .= "<b>EAN:</b> {$row['D001E_EAN']}<br>";
    if (!empty($row['D001E_garantia']))
        $specHtml .= "<b>Gar:</b> {$row['D001E_garantia']}<br>";
    if (!empty($row['D001E_peso']))
        $specHtml .= "<b>Peso:</b> {$row['D001E_peso']}<br>";
    if (!empty($row['D001E_altura']) || !empty($row['D001E_largura']) || !empty($row['D001E_comprimento'])) {
        $specHtml .= "<b>Dim:</b> " . ($row['D001E_altura'] ?: 0) . "x" . ($row['D001E_largura'] ?: 0) . "x" . ($row['D001E_comprimento'] ?: 0);
    }
    if (empty($specHtml))
        $specHtml = "<em>Sem dados</em>";

    // JSONs p/ modal
    $imgs = [];
    for ($i = 1; $i <= 10; $i++)
        if (!empty($row["D001E_Imagem_$i"]))
            $imgs[] = $row["D001E_Imagem_$i"];
    if (empty($imgs))
        $imgs[] = "https://via.placeholder.com/600x600?text=Sem+Imagem";

    $jsonImgs = htmlspecialchars(json_encode($imgs), ENT_QUOTES, 'UTF-8');
    $jsonDesc = htmlspecialchars(json_encode($descRaw), ENT_QUOTES, 'UTF-8');
    $marcaJs  = htmlspecialchars($marca, ENT_QUOTES);

    $specs     = [
        'EAN'         => $row['D001E_EAN'] ?? '',
        'Garantia'    => $row['D001E_garantia'] ?? '',
        'Peso'        => $row['D001E_peso'] ? $row['D001E_peso'] . ' kg' : '',
        'Altura'      => $row['D001E_altura'] ? $row['D001E_altura'] . ' cm' : '',
        'Largura'     => $row['D001E_largura'] ? $row['D001E_largura'] . ' cm' : '',
        'Comprimento' => $row['D001E_comprimento'] ? $row['D001E_comprimento'] . ' cm' : '',
    ];
    $jsonSpecs = htmlspecialchars(json_encode($specs), ENT_QUOTES, 'UTF-8');

    return "
    <div class='quality-row'>
        <div class='thumb-box' onclick='abrirVisualizador($jsonImgs, \"$titulo\", \"$sku\", $jsonDesc, \"$marcaJs\", $jsonSpecs, {$resT['nota']}, {$resD['nota']}, {$resI['nota']}, {$resA['nota']})'><img src='$imgCapa'></div>
        <div class='col-product'><div class='prod-title'>{$row['D001E_Titulo']}</div><div class='prod-sku'>SKU: {$row['D001E_D001_Codigo_Produto']}</div></div>
        <div class='col-brand' title='$marcaHtml'>$marcaHtml</div>
        <div class='col-box-scroll'>" . ($descRaw ?: '<em>Sem descrição</em>') . "</div>
        <div class='col-box-scroll'>$specHtml</div>

        <div class='mini-score-box' onmouseenter='window.showHTooltip(this)' onmousemove='window.moveHTooltip(event)' onmouseleave='window.hideHTooltip()'>
            <div class='mini-score-val' style='background:" . getCorNota($resT['nota']) . "'>{$resT['nota']}</div>
            <div class='mini-score-lbl'>TÍTULO</div>
            <div class='tooltip-hidden-content' style='display:none'>" . gerarTooltipHtml("Título", $resT) . "</div>
        </div>

        <div class='mini-score-box' onmouseenter='window.showHTooltip(this)' onmousemove='window.moveHTooltip(event)' onmouseleave='window.hideHTooltip()'>
            <div class='mini-score-val' style='background:" . getCorNota($resD['nota']) . "'>{$resD['nota']}</div>
            <div class='mini-score-lbl'>DESC</div>
            <div class='tooltip-hidden-content' style='display:none'>" . gerarTooltipHtml("Descrição", $resD) . "</div>
        </div>

        <div class='mini-score-box' onmouseenter='window.showHTooltip(this)' onmousemove='window.moveHTooltip(event)' onmouseleave='window.hideHTooltip()'>
            <div class='mini-score-val' style='background:" . getCorNota($resI['nota']) . "'>{$resI['nota']}</div>
            <div class='mini-score-lbl'>IMG</div>
            <div class='tooltip-hidden-content' style='display:none'>" . gerarTooltipHtml("Imagens", $resI) . "</div>
        </div>

        <div class='mini-score-box' onmouseenter='window.showHTooltip(this)' onmousemove='window.moveHTooltip(event)' onmouseleave='window.hideHTooltip()'>
            <div class='mini-score-val' style='background:" . getCorNota($resA['nota']) . "'>{$resA['nota']}</div>
            <div class='mini-score-lbl'>ESPEC</div>
            <div class='tooltip-hidden-content' style='display:none'>" . gerarTooltipHtml("Atributos", $resA) . "</div>
        </div>

        <div class='col-score'>
            <div class='score-circle' style='--color:$c; --percent:$p;'><span class='score-number'>$final</span></div>
            <span class='score-label'>$l</span>
        </div>
    </div>";
}

// =============================================================================
// [AJAX] RETORNO JSON (ANTES DE IMPRIMIR CSS/JS/HTML)
// =============================================================================
if ($isAjax) {
    header('Content-Type: application/json; charset=UTF-8');

    $totalRows = 0;
    $rsCount   = mysql_query("SELECT COUNT(*) AS total FROM D001E");
    if ($rsCount) {
        $r         = mysql_fetch_assoc($rsCount);
        $totalRows = (int) ($r['total'] ?? 0);
    }

    $sql = "SELECT * FROM D001E ORDER BY D001E_Id ASC LIMIT $limit OFFSET $offset";
    $rs  = mysql_query($sql);

    $html = "";
    if ($rs) {
        while ($row = mysql_fetch_assoc($rs)) {
            $html .= renderQualityRow($row);
        }
    }

    echo json_encode([
        'ok'       => 1,
        'total'    => $totalRows,
        'page'     => $page,
        'pageSize' => $limit,
        'html'     => $html
    ]);
    exit;
}

// =============================================================================
// [STYLE] ESTILOS CSS (MANTIDOS) + PAGINAÇÃO
// =============================================================================
$style = <<<STYLE
<style>
    :root { --bg-page: #2F384D; --bg-card: #ffffff; --score-6: #004085; --score-5: #28a745; --score-4: #85e085; --score-3: #ffc107; --score-2: #ffadad; --score-1: #dc3545; --primary-color: #2F384D; }
    body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif; background-color: var(--bg-page); margin: 0; padding: 16px; color: #1f2433; }
    .quality-list { background: var(--bg-card); border-radius: 16px; box-shadow: 0 14px 30px rgba(0, 0, 0, 0.18); overflow: hidden; border: 1px solid rgba(255, 255, 255, 0.08); }

    .quality-header, .quality-row {
        display: grid;
        grid-template-columns: 70px 1.6fr 1.1fr 2.2fr 1.3fr 52px 52px 52px 52px 90px;
        gap: 12px;
        align-items: stretch;
    }
    .quality-header {
        background: #f5f6fa;
        padding: 12px 18px;
        font-size: 11px;
        font-weight: 700;
        color: #6b7280;
        border-bottom: 1px solid #e5e7eb;
        text-transform: uppercase;
        letter-spacing: 0.04em;
    }
    .quality-row {
        border-bottom: 1px solid #f0f1f5;
        padding: 12px 18px;
        transition: background 0.18s ease, transform 0.12s ease, box-shadow 0.12s ease;
        background: #ffffff;
    }
    .quality-row:nth-child(even) { background: #fbfcff; }
    .quality-row:hover {
        background-color: #f3f5ff;
        box-shadow: 0 8px 18px rgba(15, 23, 42, 0.08);
        transform: translateY(-1px);
    }

    #content { width: 100%; }

    .thumb-box {
        width: 62px; height: 62px;
        border-radius: 12px;
        border: 1px solid #e5e7eb;
        padding: 4px;
        background: linear-gradient(135deg, #ffffff, #f4f5fb);
        cursor: pointer;
        position: relative;
        transition: transform 0.15s ease, box-shadow 0.15s ease, border-color 0.15s ease;
        display: flex; align-items: center; justify-content: center;
    }
    .thumb-box img { width: 100%; height: 100%; object-fit: contain; }
    .thumb-box:hover {
        border-color: var(--primary-color);
        box-shadow: 0 6px 14px rgba(15, 23, 42, 0.25);
        transform: translateY(-1px) scale(1.03);
    }

    .col-product { display: flex; flex-direction: column; justify-content: center; }
    .prod-title { font-size: 13px; font-weight: 700; line-height: 1.3; margin-bottom: 4px; color: #111827; }
    .prod-sku {
        font-size: 10px; color: #6b7280; background: #eef2ff;
        padding: 3px 8px; border-radius: 999px;
        display: inline-flex; align-items: center; gap: 4px; font-weight: 600;
    }
    .prod-sku::before { content: "#"; font-weight: 700; color: var(--primary-color); }

    .col-brand {
        font-size: 11px; font-weight: 600; color: #4b5563;
        background: #fff8f0;
        padding: 4px 8px;
        border-radius: 6px;
        border: 1px solid #ffe0d0;
        display: flex; align-items: center; justify-content: center;
        text-align: center;
        height: fit-content; align-self: center;
        white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }

    .col-box-scroll {
        font-size: 11px; color: #4b5563; line-height: 1.4;
        max-height: 78px; overflow-y: auto;
        background: #f9fafb;
        padding: 8px 10px;
        border-radius: 10px;
        border: 1px solid #e5e7eb;
    }
    .col-box-scroll b { color: #111827; }
    .col-box-scroll em { color: #9ca3af; }

    .mini-score-box {
        display: flex; flex-direction: column;
        align-items: center; justify-content: center;
        cursor: help; position: relative; padding: 4px 0;
    }
    .mini-score-val {
        width: 30px; height: 30px;
        border-radius: 999px;
        background: #e5e7eb;
        display: flex; align-items: center; justify-content: center;
        font-weight: 700; font-size: 11px; color: #ffffff;
        margin-bottom: 3px;
        box-shadow: 0 2px 6px rgba(15, 23, 42, 0.16);
        transition: transform 0.15s ease, box-shadow 0.15s ease;
    }
    .mini-score-box:hover .mini-score-val {
        transform: translateY(-1px) scale(1.08);
        box-shadow: 0 4px 10px rgba(15, 23, 42, 0.3);
    }
    .mini-score-lbl {
        font-size: 8px; color: #6b7280;
        text-transform: uppercase; letter-spacing: 0.08em;
    }

    .col-score { display: flex; flex-direction: column; align-items: center; border-left: 1px solid #e5e7eb; padding-left: 8px; }
    .score-circle {
        position: relative; width: 52px; height: 52px;
        border-radius: 50%;
        background: conic-gradient(var(--color) calc(var(--percent) * 1%), #e5e7eb 0);
        display: flex; align-items: center; justify-content: center;
        margin-bottom: 4px;
        box-shadow: 0 4px 12px rgba(15, 23, 42, 0.22);
    }
    .score-circle::before {
        content: ""; position: absolute;
        width: 40px; height: 40px;
        border-radius: 50%; background: #ffffff;
    }
    .score-number { position: relative; font-size: 16px; font-weight: 800; z-index: 1; color: #111827; }
    .score-label { font-size: 9px; font-weight: 600; color: #374151; text-transform: uppercase; letter-spacing: 0.08em; }

    /* TOOLTIP (ORIGINAL) */
    #hardness-custom-tooltip {
        background: #ffffff;
        border: 1px solid #e4e6eb;
        box-shadow: 0 8px 24px rgba(15, 23, 42, 0.25);
        border-radius: 10px;
        padding: 0;
        z-index: 999999;
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
        font-size: 12px;
        color: #111827;
        min-width: 230px;
    }
    .tt-table { width: 100%; border-collapse: collapse; margin: 0; }
    .tt-head {
        background: #f3f4ff;
        border-bottom: 1px solid #e5e7eb;
        padding: 10px 12px;
        font-weight: 700;
        color: #1f2937;
        text-align: left;
        font-size: 11px;
        text-transform: uppercase;
        border-radius: 10px 10px 0 0;
        letter-spacing: 0.06em;
    }
    .tt-row { border-bottom: 1px solid #f3f4f6; padding: 8px 12px; color: #4b5563; font-size: 11px; }
    .tt-val { border-bottom: 1px solid #f3f4f6; padding: 8px 12px; color: #111827; text-align: right; font-weight: 600; font-size: 11px; }
    .tt-foot td {
        background: #f9fafb;
        font-weight: 800;
        color: var(--primary-color);
        border-top: 2px solid #e5e7eb;
        padding: 10px 12px;
        border-radius: 0 0 10px 10px;
    }
    .header-tooltip-content { padding: 10px 12px; }
    .header-tooltip-title {
        font-weight: 700;
        color: var(--primary-color);
        border-bottom: 2px solid #e5e7eb;
        padding-bottom: 8px;
        margin-bottom: 8px;
        font-size: 12px;
        text-transform: uppercase;
        letter-spacing: 0.08em;
    }
    .header-rule-row { display: flex; justify-content: space-between; font-size: 11px; margin-bottom: 4px; color: #4b5563; }
    .header-rule-val { font-weight: 700; }

    /* MODAL (ORIGINAL) */
    .modal-overlay {
        display: none; position: fixed; top: 0; left: 0;
        width: 100%; height: 100%;
        background: rgba(15, 23, 42, 0.88);
        z-index: 9999;
        justify-content: center; align-items: center;
        backdrop-filter: blur(5px);
        padding: 10px; box-sizing: border-box;
    }
    .modal-content {
        background: #ffffff;
        width: 100%; max-width: 1220px;
        height: 88%;
        border-radius: 18px;
        position: relative;
        display: flex;
        overflow: hidden;
        box-shadow: 0 24px 48px rgba(15, 23, 42, 0.55);
        border: 1px solid rgba(15, 23, 42, 0.15);
    }
    .close-modal {
        position: absolute; top: 14px; right: 18px;
        font-size: 26px; cursor: pointer; z-index: 100;
        color: #4b5563;
        width: 30px; height: 30px;
        border-radius: 999px;
        display: flex; align-items: center; justify-content: center;
        transition: background 0.15s ease, color 0.15s ease, transform 0.1s ease;
    }
    .close-modal:hover { background: #f3f4f6; color: #111827; transform: scale(1.05); }

    .vis-thumbs {
        width: 120px; background: #f5f6fa;
        padding: 20px 10px;
        overflow-y: auto;
        display: flex; flex-direction: column; gap: 10px;
        border-right: 1px solid #e5e7eb;
    }
    .vis-mini {
        width: 100%; height: 80px; object-fit: cover;
        border: 2px solid transparent; border-radius: 10px;
        cursor: pointer; background: #ffffff;
        transition: border-color 0.15s ease, transform 0.12s ease, box-shadow 0.12s ease;
    }
    .vis-mini:hover { transform: translateY(-1px); box-shadow: 0 4px 10px rgba(15, 23, 42, 0.25); }
    .vis-mini.active { border-color: var(--primary-color); box-shadow: 0 4px 10px rgba(15, 23, 42, 0.4); }

    .vis-main {
        flex: 1; display: flex; justify-content: center; align-items: center;
        background: radial-gradient(circle at top left, #f9fafb, #e5e7eb);
        padding: 30px; position: relative;
    }
    .vis-main img { max-width: 100%; max-height: 100%; object-fit: contain; }
    .vis-score-badge {
        position: absolute; top: 18px; left: 18px;
        padding: 4px 14px;
        border-radius: 999px;
        font-size: 11px; font-weight: 700;
        color: #ffffff; background: var(--primary-color);
        box-shadow: 0 4px 12px rgba(15, 23, 42, 0.35);
        z-index: 10; text-transform: uppercase; letter-spacing: 0.08em;
    }

    .vis-info {
        width: 360px;
        border-left: 1px solid #e5e7eb;
        padding: 24px 24px 20px 24px;
        overflow-y: auto;
        background: #ffffff;
        display: flex; flex-direction: column; gap: 14px;
    }
    .vis-h1 {
        font-size: 20px; font-weight: 700;
        margin: 0 0 4px 0;
        line-height: 1.3; color: #111827;
        display: flex; flex-wrap: wrap; align-items: center; gap: 8px;
    }
    .vis-chip {
        padding: 3px 10px;
        border-radius: 999px;
        font-size: 11px;
        font-weight: 700;
        color: #ffffff;
        background: var(--primary-color);
        white-space: nowrap;
        display: inline-block;
    }
    .vis-meta {
        font-size: 13px; color: #6b7280;
        margin-bottom: 6px;
        padding-bottom: 10px;
        border-bottom: 1px solid #e5e7eb;
    }
    .vis-specs-container { margin-top: 6px; padding-top: 10px; border-top: 1px solid #e5e7eb; }
    .vis-specs-table { width: 100%; border-collapse: collapse; font-size: 12px; }
    .vis-specs-table td { padding: 6px 0; border-bottom: 1px solid #f3f4f6; color: #4b5563; }
    .vis-specs-table td strong { color: #111827; font-weight: 600; display: inline-block; width: 100px; }

    .vis-btn-print {
        width: 100%;
        padding: 11px 12px;
        background: var(--primary-color);
        color: #ffffff;
        border: none;
        border-radius: 10px;
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
        display: flex; align-items: center; justify-content: center; gap: 8px;
        transition: background 0.15s ease, transform 0.1s ease, box-shadow 0.12s ease;
        margin-bottom: 8px;
    }
    .vis-btn-print:hover { background: #242b3c; transform: translateY(-1px); box-shadow: 0 6px 14px rgba(15, 23, 42, 0.35); }
    .vis-header-row { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; font-weight: 700; font-size: 13px; color: #111827; }
    .vis-desc-box {
        font-size: 13px; line-height: 1.6; color: #4b5563;
        background: #f9fafb;
        padding: 14px;
        border-radius: 10px;
        border: 1px solid #e5e7eb;
        max-height: 200px;
        overflow-y: auto;
    }

    ::-webkit-scrollbar { width: 6px; height: 6px; }
    ::-webkit-scrollbar-track { background: #eef2ff; border-radius: 999px; }
    ::-webkit-scrollbar-thumb { background: #9ca3af; border-radius: 999px; }
    ::-webkit-scrollbar-thumb:hover { background: #6b7280; }

    @media (max-width: 1200px) {
        .quality-header, .quality-row {
            grid-template-columns: 60px 1.4fr 1.0fr 2.0fr 1.1fr 44px 44px 44px 44px 72px;
            gap: 8px;
        }
        .vis-info { width: 320px; }
    }
    @media (max-width: 900px) {
        body { padding: 10px; }
        .quality-list { border-radius: 14px; }
        .quality-header { display: none; }
        .quality-row { grid-template-columns: 70px 1fr; grid-template-rows: auto; }
        .quality-row > div:nth-child(1) { grid-column: 1 / 2; grid-row: 1 / 2; }
        .quality-row > div:nth-child(2) { grid-column: 2 / 3; grid-row: 1 / 2; }
        .quality-row > div:nth-child(3) { grid-column: 1 / 3; grid-row: 2 / 3; }
        .quality-row > div:nth-child(4) { grid-column: 1 / 3; grid-row: 3 / 4; }
        .quality-row > div:nth-child(5) { grid-column: 1 / 3; grid-row: 4 / 5; }
        .quality-row > div:nth-child(6) { grid-column: 1 / 2; grid-row: 5 / 6; }
        .quality-row > div:nth-child(7) { grid-column: 2 / 3; grid-row: 5 / 6; }
        .quality-row > div:nth-child(8) { grid-column: 1 / 2; grid-row: 6 / 7; }
        .quality-row > div:nth-child(9) { grid-column: 2 / 3; grid-row: 6 / 7; }
        .quality-row > div:nth-child(10){ grid-column: 1 / 3; grid-row: 7 / 8; margin-top: 6px; }
        .col-score { border-left: none; border-top: 1px solid #e5e7eb; padding-left: 0; padding-top: 8px; }

        .modal-content { flex-direction: column; height: 92%; }
        .vis-thumbs {
            width: 100%; height: 90px;
            flex-direction: row;
            border-right: none;
            border-bottom: 1px solid #e5e7eb;
            padding: 10px;
        }
        .vis-mini { height: 70px; width: auto; }
        .vis-main { min-height: 40%; }
        .vis-info {
            width: 100%;
            border-left: none;
            border-top: 1px solid #e5e7eb;
        }
        .vis-score-badge { top: 10px; left: 10px; font-size: 10px; }
    }
    @media (max-width: 600px) {
        .modal-content { border-radius: 14px; }
        .vis-main { padding: 18px; }
        .vis-info { padding: 16px 16px 14px 16px; }
        .vis-h1 { font-size: 18px; }
        .vis-desc-box { max-height: 180px; }
        .thumb-box { width: 56px; height: 56px; }
        .col-box-scroll { max-height: 120px; }
    }

    /* PAGINAÇÃO (#demo) */
    #demo { padding: 12px 18px; background: #f5f6fa; border-top: 1px solid #e5e7eb; display:flex; flex-wrap:wrap; align-items:center; gap:6px; }
    #demo .pg-btn { border: 1px solid #e5e7eb; background:#fff; padding:6px 10px; border-radius:10px; cursor:pointer; font-size:12px; font-weight:800; color:#111827; }
    #demo .pg-btn.disabled { opacity: .45; cursor:not-allowed; }
    #demo .pg-btn.active { background: var(--primary-color); border-color: var(--primary-color); color:#fff; }
    #demo .pg-dots { padding: 0 4px; color:#9ca3af; font-weight:800; }
</style>
<link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
STYLE;

if (!$apiMode)
    echo $style;

// =============================================================================
// [JS_TOOL] JAVASCRIPT GERAL (TOOLTIP + MODAL ORIGINAL) (MANTIDO)
// =============================================================================
$jsTooltip = <<<JS
<script>
if (typeof window.initHardnessTooltip === 'undefined') {
    window.initHardnessTooltip = true;
    var tipDiv = document.createElement('div');
    tipDiv.id = 'hardness-custom-tooltip';
    tipDiv.style.position = 'fixed'; tipDiv.style.display = 'none';
    document.body.appendChild(tipDiv);

    window.showHTooltip = function(el) {
        var contentEl = el.querySelector('.tooltip-hidden-content');
        if (!contentEl) return;
        var content = contentEl.innerHTML;
        var tip = document.getElementById('hardness-custom-tooltip');
        tip.innerHTML = content; tip.style.display = 'block';
    };

    window.moveHTooltip = function(e) {
        var tip = document.getElementById('hardness-custom-tooltip');
        if(tip && tip.style.display === 'block') {
            var x = e.clientX + 15; var y = e.clientY + 15;
            if(x + tip.offsetWidth > window.innerWidth) x = e.clientX - tip.offsetWidth - 5;
            if(y + tip.offsetHeight > window.innerHeight) y = e.clientY - tip.offsetHeight - 5;
            tip.style.left = x + 'px'; tip.style.top = y + 'px';
        }
    };

    window.hideHTooltip = function() {
        var tip = document.getElementById('hardness-custom-tooltip');
        if(tip) tip.style.display = 'none';
    };
}
</script>
JS;

if (!$apiMode)
    echo $jsTooltip;

// =============================================================================
// [RENDER] LOOP (TELA NORMAL)
// =============================================================================
$totalRows = 0;
$rsCount   = mysql_query("SELECT COUNT(*) AS total FROM D001E");
if ($rsCount) {
    $r         = mysql_fetch_assoc($rsCount);
    $totalRows = (int) ($r['total'] ?? 0);
}

$sql = "SELECT * FROM D001E ORDER BY D001E_Id ASC LIMIT $limit OFFSET 0";
$rs  = mysql_query($sql);

echo "<div class='quality-list'>";

// HTML das Regras de Tooltip (Cabeçalho) – ORIGINAL (MANTIDO)
$tipTit  = "<div class='header-tooltip-content'><div class='header-tooltip-title'>REGRAS: TÍTULO (Peso 3)</div><div class='header-rule-row'><span>< 30 chars</span><span class='header-rule-val' style='color:#dc3545'>1</span></div><div class='header-rule-row'><span>30 a 39 chars</span><span class='header-rule-val' style='color:#ffc107'>2</span></div><div class='header-rule-row'><span>40 a 49 chars</span><span class='header-rule-val' style='color:#28a745'>3</span></div><div class='header-rule-row'><span>50 a 59 chars</span><span class='header-rule-val' style='color:#28a745'>4</span></div><div class='header-rule-row'><span>60 a 89 chars</span><span class='header-rule-val' style='color:#007bff'>5</span></div></div>";
$tipDesc = "<div class='header-tooltip-content'><div class='header-tooltip-title'>REGRAS: DESCRIÇÃO (Peso 3)</div><div class='header-rule-row'><span>< 200 chars</span><span class='header-rule-val' style='color:#dc3545'>1</span></div><div class='header-rule-row'><span>200 a 399</span><span class='header-rule-val' style='color:#ffc107'>2</span></div><div class='header-rule-row'><span>400 a 599</span><span class='header-rule-val' style='color:#28a745'>3</span></div><div class='header-rule-row'><span>600 a 1999</span><span class='header-rule-val' style='color:#28a745'>4</span></div><div class='header-rule-row'><span>2000 a 4000</span><span class='header-rule-val' style='color:#007bff'>5</span></div></div>";
$tipImg  = "<div class='header-tooltip-content'><div class='header-tooltip-title'>REGRAS: IMAGENS (Peso 3)</div><div class='header-rule-row'><span>< 2 imgs</span><span class='header-rule-val' style='color:#dc3545'>1</span></div><div class='header-rule-row'><span>2 imgs</span><span class='header-rule-val' style='color:#ffc107'>3</span></div><div class='header-rule-row'><span>3 a 4 imgs</span><span class='header-rule-val' style='color:#28a745'>4</span></div><div class='header-rule-row'><span>5 a 10 imgs</span><span class='header-rule-val' style='color:#007bff'>5</span></div><div class='header-rule-row'><span>> 10 imgs</span><span class='header-rule-val' style='color:#dc3545'>0</span></div></div>";
$tipSpec = "<div class='header-tooltip-content'><div class='header-tooltip-title'>REGRAS: ATRIBUTOS (Peso 1)</div><div class='header-rule-row'><span>< 2 itens</span><span class='header-rule-val' style='color:#dc3545'>1</span></div><div class='header-rule-row'><span>2 a 3 itens</span><span class='header-rule-val' style='color:#ffc107'>3</span></div><div class='header-rule-row'><span>4 a 6 itens</span><span class='header-rule-val' style='color:#28a745'>4</span></div><div class='header-rule-row'><span>7 a 19 itens</span><span class='header-rule-val' style='color:#007bff'>5</span></div><div style='font-size:10px;color:#999;margin-top:5px'>(EAN, Peso, Dimensões, Garantia)</div></div>";

echo "<div class='quality-header'>
        <div>Foto</div>
        <div>Produto & Título</div>
        <div>Marca</div>
        <div>Análise de Descrição</div>
        <div>Especificações</div>
        <div style='text-align:center; cursor:help'
             onmouseenter='window.showHTooltip(this)' onmousemove='window.moveHTooltip(event)' onmouseleave='window.hideHTooltip()'>
             TÍTULO<div class='tooltip-hidden-content' style='display:none'>$tipTit</div>
        </div>
        <div style='text-align:center; cursor:help'
             onmouseenter='window.showHTooltip(this)' onmousemove='window.moveHTooltip(event)' onmouseleave='window.hideHTooltip()'>
             DESC<div class='tooltip-hidden-content' style='display:none'>$tipDesc</div>
        </div>
        <div style='text-align:center; cursor:help'
             onmouseenter='window.showHTooltip(this)' onmousemove='window.moveHTooltip(event)' onmouseleave='window.hideHTooltip()'>
             IMG<div class='tooltip-hidden-content' style='display:none'>$tipImg</div>
        </div>
        <div style='text-align:center; cursor:help'
             onmouseenter='window.showHTooltip(this)' onmousemove='window.moveHTooltip(event)' onmouseleave='window.hideHTooltip()'>
             ESPEC<div class='tooltip-hidden-content' style='display:none'>$tipSpec</div>
        </div>
        <div style='text-align:center'>Geral</div>
      </div>";

echo "<div id='content'>";
if ($rs) {
    while ($row = mysql_fetch_assoc($rs)) {
        echo renderQualityRow($row);
    }
}
echo "</div>";

// inputs (sem PHP dentro de <script>)
$ajaxUrl = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
$ajaxUrl = htmlspecialchars($ajaxUrl, ENT_QUOTES, 'UTF-8');
echo "<input type='hidden' id='hardness_total' value='" . (int) $totalRows . "'>";

// AQUI: Usando a variável centralizada para o input hidden
echo "<input type='hidden' id='hardness_pageSize' value='" . (int) $limit . "'>";

echo "<input type='hidden' id='hardness_ajaxUrl' value='" . $ajaxUrl . "'>";

// container da paginação
echo "<div id='demo'></div>";

echo "</div>"; // fecha .quality-list
?>

<div id="modalVis" class="modal-overlay" onclick="if(event.target==this) fecharVis()">
    <div class="modal-content printable-area">
        <span class="close-modal" onclick="fecharVis()">×</span>
        <div class="vis-thumbs" id="visThumbs"></div>
        <div class="vis-main">
            <span class="vis-score-badge" id="visImgScore">--</span>
            <img id="visHero" src="">
        </div>
        <div class="vis-info">
            <h1 class="vis-h1"><span id="visTitle">--</span><span class="vis-chip" id="visTitleScore">--</span></h1>
            <div class="vis-meta">SKU: <strong id="visSku">--</strong> | Marca: <strong id="visBrand">--</strong></div>
            <button class="vis-btn-print" onclick="imprimirConteudoModal()"><i class="material-icons">print</i> Imprimir
                Ficha Técnica</button>
            <div class="vis-header-row"><span>Descrição do Produto</span><span class="vis-chip"
                    id="visDescScore">--</span></div>
            <div class="vis-desc-box" id="visDesc"></div>
            <div class="vis-specs-container">
                <div class="vis-header-row"><span>Especificações</span><span class="vis-chip"
                        id="visAttrScore">--</span></div>
                <div id="visSpecsContent"></div>
            </div>
        </div>
    </div>
</div>

<script>
    // =============================================================================
    // [PAGINAÇÃO] PLUGIN TIPO $('#demo').pagination({...}) + AJAX (sem window.location.*)
    // =============================================================================
    (function ($) {
        $.fn.pagination = function (options) {
            var opts = $.extend({
                dataSource: 0,          // aqui vai ser TOTAL (numero)
                pageSize: 50,           // PADRÃO GENÉRICO (será sobrescrito pelo PHP)
                autoHidePrevious: true,
                autoHideNext: true,
                callback: function (data, pagination) { }
            }, options || {});

            var $wrap = this;
            var totalNumber = parseInt(opts.dataSource, 10) || 0;
            var pageSize = parseInt(opts.pageSize, 10) || 50;
            var totalPages = Math.max(1, Math.ceil(totalNumber / pageSize));
            var current = 1;

            function buildBtn(label, page, cls) {
                cls = cls || '';
                return '<a href="javascript:void(0)" class="pg-btn ' + cls + '" data-page="' + page + '">' + label + '</a>';
            }

            function render() {
                var html = '';
                var prevDisabled = (current <= 1);
                var nextDisabled = (current >= totalPages);

                if (!(opts.autoHidePrevious && prevDisabled)) {
                    html += buildBtn('&lt;', current - 1, prevDisabled ? 'disabled' : '');
                }

                // janela de páginas: 1 ... x y z ... N
                var range = 2;
                var start = Math.max(1, current - range);
                var end = Math.min(totalPages, current + range);

                if (start > 1) {
                    html += buildBtn('1', 1, (current === 1 ? 'active' : ''));
                    if (start > 2) html += '<span class="pg-dots">...</span>';
                }

                for (var i = start; i <= end; i++) {
                    html += buildBtn(String(i), i, (current === i ? 'active' : ''));
                }

                if (end < totalPages) {
                    if (end < totalPages - 1) html += '<span class="pg-dots">...</span>';
                    html += buildBtn(String(totalPages), totalPages, (current === totalPages ? 'active' : ''));
                }

                if (!(opts.autoHideNext && nextDisabled)) {
                    html += buildBtn('&gt;', current + 1, nextDisabled ? 'disabled' : '');
                }

                $wrap.html(html);
            }

            function go(page) {
                page = parseInt(page, 10) || 1;
                if (page < 1) page = 1;
                if (page > totalPages) page = totalPages;
                current = page;
                render();

                var pagination = {
                    pageNumber: current,
                    pageSize: pageSize,
                    totalNumber: totalNumber,
                    totalPages: totalPages
                };
                opts.callback([], pagination);
            }

            $wrap.delegate('.pg-btn', 'click', function () {
                var $b = $(this);
                if ($b.hasClass('disabled') || $b.hasClass('active')) return;
                go($b.attr('data-page'));
            });

            render();
            go(1);
            return this;
        };
    })(jQuery);

    jQuery(function ($) {
        var total = parseInt($('#hardness_total').val(), 10) || 0;

        // AQUI: Pega o valor do input hidden (definido pelo PHP) com fallback genérico (50)
        var pageSize = parseInt($('#hardness_pageSize').val(), 10) || 50;

        var ajaxUrl = $('#hardness_ajaxUrl').val();

        var cache = {}; // cache por página (mini-cache simples)
        var CACHE_MAX = 2;
        var cacheOrder = [];

        function cachePut(page, html) {
            page = String(page);
            if (typeof cache[page] === 'undefined') cacheOrder.push(page);
            cache[page] = html;
            while (cacheOrder.length > CACHE_MAX) {
                var k = cacheOrder.shift();
                delete cache[k];
            }
        }
        function cacheGet(page) {
            page = String(page);
            if (typeof cache[page] === 'undefined') return null;
            return cache[page];
        }

        function loadPage(page) {
            page = parseInt(page, 10) || 1;

            var cached = cacheGet(page);
            if (cached !== null) {
                $('#content').html(cached);
                return;
            }

            $.ajax({
                url: ajaxUrl,
                type: 'POST',
                dataType: 'json',
                data: {
                    ajax: 1,
                    page: page,
                    pageSize: pageSize // Envia o valor correto
                },
                success: function (res) {
                    if (!res || !res.ok) return;
                    $('#content').html(res.html);
                    cachePut(page, res.html);
                }
            });
        }

        // salva primeira página no cache
        cachePut(1, $('#content').html());

        $('#demo').pagination({
            dataSource: total,
            pageSize: pageSize, // Usa a variável JS que veio do input hidden
            autoHidePrevious: true,
            autoHideNext: true,
            callback: function (data, pagination) {
                loadPage(pagination.pageNumber);
            }
        });
    });
</script>

<script>
    // =============================================================================
    // [MODAL] JS ORIGINAL (MANTIDO)
    // =============================================================================
    const mVis = document.getElementById('modalVis'),
        vThumbs = document.getElementById('visThumbs'),
        vHero = document.getElementById('visHero'),
        vTitle = document.getElementById('visTitle'),
        vSku = document.getElementById('visSku'),
        vBrand = document.getElementById('visBrand'),
        vDesc = document.getElementById('visDesc'),
        vSpecsContent = document.getElementById('visSpecsContent');
    const elTitScore = document.getElementById('visTitleScore'),
        elDescScore = document.getElementById('visDescScore'),
        elImgScore = document.getElementById('visImgScore'),
        elAttrScore = document.getElementById('visAttrScore');

    function getMetaNota(n) {
        n = Number(n);
        if (n === 6) return { cor: 'var(--score-6)', txt: 'Ótima' };
        if (n === 5) return { cor: 'var(--score-5)', txt: 'Muito Boa' };
        if (n === 4) return { cor: 'var(--score-4)', txt: 'Boa' };
        if (n === 3) return { cor: 'var(--score-3)', txt: 'Média' };
        if (n === 2) return { cor: 'var(--score-2)', txt: 'Ruim' };
        return { cor: 'var(--score-1)', txt: 'Muito Ruim' };
    }

    function abrirVisualizador(imgs, tit, sku, desc, brand, specs, nT, nD, nI, nA) {
        vTitle.innerText = tit;
        vSku.innerText = sku;
        vBrand.innerText = brand;

        vDesc.innerHTML = desc ? desc : '<em>Sem descrição.</em>';

        const mT = getMetaNota(nT); elTitScore.style.backgroundColor = mT.cor; elTitScore.innerText = nT + ' - ' + mT.txt;
        const mD = getMetaNota(nD); elDescScore.style.backgroundColor = mD.cor; elDescScore.innerText = nD + ' - ' + mD.txt;
        const mI = getMetaNota(nI); elImgScore.style.backgroundColor = mI.cor; elImgScore.innerText = 'Fotos: ' + nI + ' (' + mI.txt + ')';
        const mA = getMetaNota(nA); elAttrScore.style.backgroundColor = mA.cor; elAttrScore.innerText = nA + ' - ' + mA.txt;

        vThumbs.innerHTML = '';
        if (imgs.length > 0) vHero.src = imgs[0];

        imgs.forEach((url, idx) => {
            let img = document.createElement('img');
            img.src = url; img.className = 'vis-mini';
            if (idx === 0) img.classList.add('active');
            img.onclick = () => { vHero.src = url; document.querySelectorAll('.vis-mini').forEach(el => el.classList.remove('active')); img.classList.add('active'); };
            vThumbs.appendChild(img);
        });

        let html = '<table class="vis-specs-table">'; let has = false;
        if (specs.EAN) { html += `<tr><td><strong>EAN:</strong> ${specs.EAN}</td></tr>`; has = true; }
        if (specs.Garantia) { html += `<tr><td><strong>Garantia:</strong> ${specs.Garantia}</td></tr>`; has = true; }
        if (specs.Peso) { html += `<tr><td><strong>Peso:</strong> ${specs.Peso}</td></tr>`; has = true; }
        if (specs.Altura) { html += `<tr><td><strong>Altura:</strong> ${specs.Altura}</td></tr>`; has = true; }
        if (specs.Largura) { html += `<tr><td><strong>Largura:</strong> ${specs.Largura}</td></tr>`; has = true; }
        if (specs.Comprimento) { html += `<tr><td><strong>Comp.:</strong> ${specs.Comprimento}</td></tr>`; has = true; }
        html += '</table>';
        vSpecsContent.innerHTML = has ? html : '<div style="color:#999;font-size:12px">Vazio</div>';

        mVis.style.display = 'flex';
    }
    function fecharVis() { mVis.style.display = 'none'; }

    function imprimirConteudoModal() {
        const f = document.createElement('iframe'); f.style.display = 'none'; document.body.appendChild(f);
        const d = f.contentWindow.document;
        const s = document.getElementById('visSpecsContent').innerHTML;
        const c = `<html><head><style>
        body{font-family:Arial,sans-serif;padding:20px;color:#333}
        h1{font-size:24px;margin-bottom:5px}
        .meta{color:#666;font-size:12px;margin-bottom:20px;border-bottom:1px solid #ccc;padding-bottom:10px}
        .hero{text-align:center;margin-bottom:20px}
        .hero img{max-width:300px;max-height:300px}
        .desc{font-size:12px;line-height:1.5;margin-bottom:20px}
        .specs-box{border:1px solid #eee;padding:10px;border-radius:5px}
        .specs-box table{width:100%;font-size:12px}
        .specs-box td{padding:4px 0}
    </style></head><body>
    <h1>${vTitle.innerText}</h1>
    <div class="meta">SKU: ${vSku.innerText}</div>
    <div class="hero"><img src="${vHero.src}"></div>
    <h3>Descrição</h3><div class="desc">${vDesc.innerHTML}</div>
    <h3>Specs</h3><div class="specs-box">${s}</div>
    </body></html>`;
        d.open(); d.write(c); d.close();
        setTimeout(() => { f.contentWindow.print(); setTimeout(() => document.body.removeChild(f), 1000); }, 200);
    }
    document.addEventListener('keydown', e => { if (e.key === "Escape") fecharVis() });
</script>