<?php
/**
 * [SUMARIO] PAINEL DE QUALIDADE (V21 - 4 COLUNAS DE DADOS D009)
 * ----------------------------------------------------------------------------
 * [CONFIG] .... Configurações, Empresa Global
 * [STYLE] ..... CSS (Grid expandido para 4 colunas de dados)
 * [JS_TOOL] ... Javascript Tooltip
 * [FUNC] ...... Funções de Análise
 * [RENDER] .... Loop Principal (SQL com novos campos)
 * ----------------------------------------------------------------------------
 */
namespace hardness;

global $g, $confUsuario;

// =============================================================================
// [CONFIG]
// =============================================================================
error_reporting(E_ALL & ~E_NOTICE);
ini_set('display_errors', 1);

$qtdePorPagina = 150;
$limit         = $qtdePorPagina;

// --- [EMPRESA ATUAL] ---
$C004_Id = isset($g['empresaAtual']) ? (int) $g['empresaAtual'] : 1;

$page = isset($_POST['page']) ? (int) $_POST['page'] : 1;
if ($page < 1)
    $page = 1;

if (isset($_POST['pageSize'])) {
    $tmp = (int) $_POST['pageSize'];
    if ($tmp > 0)
        $limit = $tmp;
}

$offset = 0;
if (isset($_GET['offset'])) {
    $offset = (int) $_GET['offset'];
    if ($offset < 0)
        $offset = 0;
}
else {
    $offset = ($page - 1) * $limit;
}

$isAjax  = (isset($_POST['ajax']) && (int) $_POST['ajax'] === 1);
$apiMode = 0;

// INDICADOR VISUAL (Apenas se não for AJAX)
if (!$isAjax) {
    echo "<div style='
            position: fixed; bottom: 15px; right: 15px;
            background: rgba(17, 24, 39, 0.95); color: #fff;
            padding: 8px 14px; border-radius: 8px;
            font-size: 12px; font-family: sans-serif; font-weight: 600;
            z-index: 999999; box-shadow: 0 4px 12px rgba(0,0,0,0.3);
            border: 1px solid rgba(255,255,255,0.15); pointer-events: none;
          '>
            🏢 Empresa Ativa: <span style='color: #4ade80; font-size:13px;'>ID {$C004_Id}</span>
          </div>";
}

// =============================================================================
// [FUNC] FUNÇÕES DE CÁLCULO
// =============================================================================
function extrairMarcaJsonAnyMarket($jsonString)
{
    if (empty($jsonString))
        return "";
    $obj = json_decode($jsonString);
    if (isset($obj->content[0]->brand->name))
        return trim($obj->content[0]->brand->name);
    return "";
}
function analiseTitulo($titulo)
{
    $len   = mb_strlen(trim($titulo));
    $n     = 0;
    $regra = "";
    if ($len < 30) {
        $n     = 1;
        $regra = "< 30 chars";
    }
    elseif ($len < 40) {
        $n     = 2;
        $regra = "30-39 chars";
    }
    elseif ($len < 50) {
        $n     = 3;
        $regra = "40-49 chars";
    }
    elseif ($len < 60) {
        $n     = 4;
        $regra = "50-59 chars";
    }
    elseif ($len < 90) {
        $n     = 5;
        $regra = "60-89 chars";
    }
    else {
        $n     = 0;
        $regra = "> 90 chars";
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
        $regra = "< 200 chars";
    }
    elseif ($len < 400) {
        $n     = 2;
        $regra = "200-399 chars";
    }
    elseif ($len < 600) {
        $n     = 3;
        $regra = "400-599 chars";
    }
    elseif ($len < 2000) {
        $n     = 4;
        $regra = "600-1999 chars";
    }
    elseif ($len <= 4000) {
        $n     = 5;
        $regra = "2000-4000 chars";
    }
    else {
        $n     = 0;
        $regra = "> 4000 chars";
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
        $regra = "< 2 imgs";
    }
    elseif ($qtd < 3) {
        $n     = 3;
        $regra = "2 imgs";
    }
    elseif ($qtd < 5) {
        $n     = 4;
        $regra = "3-4 imgs";
    }
    elseif ($qtd <= 10) {
        $n     = 5;
        $regra = "5-10 imgs";
    }
    else {
        $n     = 0;
        $regra = "> 10 imgs";
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
        $regra = "< 2 atrib.";
    }
    elseif ($count < 4) {
        $n     = 3;
        $regra = "2-3 atrib.";
    }
    elseif ($count < 7) {
        $n     = 4;
        $regra = "4-6 atrib.";
    }
    elseif ($count < 20) {
        $n     = 5;
        $regra = "7-19 atrib.";
    }
    else {
        $n     = 5;
        $regra = "Completo";
    }
    return ['nota' => $n, 'valor' => $count . ' preench.', 'regra' => $regra, 'peso' => 1];
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
// [RENDER] FUNÇÃO DE LINHA
// =============================================================================
function renderQualityRow($row)
{
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

    // Updates
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
    $skuRaw    = $row['D001E_D001_Codigo_Produto'];
    $sku       = htmlspecialchars($skuRaw, ENT_QUOTES);
    $descRaw   = $row['D001E_Descricao'];
    $marcaHtml = htmlspecialchars($marca, ENT_QUOTES);

    $specHtml = "";
    if (!empty($row['D001E_EAN']))
        $specHtml .= "<b>EAN:</b> {$row['D001E_EAN']}<br>";
    if (!empty($row['D001E_garantia']))
        $specHtml .= "<b>Gar:</b> {$row['D001E_garantia']}<br>";
    if (!empty($row['D001E_peso']))
        $specHtml .= "<b>Peso:</b> {$row['D001E_peso']}<br>";
    if (!empty($row['D001E_altura']))
        $specHtml .= "<b>Dim:</b> " . ($row['D001E_altura'] ?: 0) . "x" . ($row['D001E_largura'] ?: 0) . "x" . ($row['D001E_comprimento'] ?: 0);
    if (empty($specHtml))
        $specHtml = "<em>Sem dados</em>";

    // --- DADOS D009 (4 COLUNAS) ---
    $freqVenda = !empty($row['D009_Frequencia_Venda']) ? $row['D009_Frequencia_Venda'] : '-';
    $custoVal  = isset($row['D009_Valor_Custo_Unitario']) ? (float) $row['D009_Valor_Custo_Unitario'] : 0;
    $estTab    = isset($row['D009_Quantidade_Estoque_Tabela']) ? (int) $row['D009_Quantidade_Estoque_Tabela'] : 0;
    $estLiq    = isset($row['D009_Quantidade_Estoque_Liquido']) ? (int) $row['D009_Quantidade_Estoque_Liquido'] : 0;

    $custoHtml = ($custoVal > 0) ? "<span style='color:#dc3545; font-weight:600;'>R$ " . number_format($custoVal, 2, ',', '.') . "</span>" : "<span style='color:#999'>-</span>";
    // ------------------------------

    return "
    <div class='quality-row'>
        <div class='thumb-box' onclick='abrirVisualizador(\"$sku\")'><img src='$imgCapa'></div>
        <div class='col-product'><div class='prod-title'>{$row['D001E_Titulo']}</div><div class='prod-sku'>SKU: {$row['D001E_D001_Codigo_Produto']}</div></div>
        <div class='col-brand' title='$marcaHtml'>$marcaHtml</div>
        
        <div class='col-center' style='font-weight:700; color:#4b5563;'>$freqVenda</div>
        <div class='col-center'>$custoHtml</div>
        <div class='col-center'>$estTab</div>
        <div class='col-center' style='font-weight:700; color:#000;'>$estLiq</div>
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
// [AJAX] GERENCIADOR
// =============================================================================
if ($isAjax) {
    header('Content-Type: application/json; charset=UTF-8');

    // MODAL
    if (isset($_POST['action']) && $_POST['action'] === 'get_details') {
        $skuBusca = isset($_POST['sku']) ? mysql_real_escape_string($_POST['sku']) : '';

        $sqlDet = "SELECT T1.*, 
                          T2.D009_Frequencia_Venda, 
                          T2.D009_Valor_Custo_Unitario, 
                          T2.D009_Quantidade_Estoque_Tabela, 
                          T2.D009_Quantidade_Estoque_Liquido
                   FROM D001E AS T1
                   LEFT JOIN D049 ON D049.D049_D001_Id = T1.D001E_D001_Id
                   LEFT JOIN D009 AS T2 ON (T2.D009_D049_Id = D049.D049_Id AND T2.D009_C004_Id = $C004_Id)
                   WHERE T1.D001E_D001_Codigo_Produto = '$skuBusca' LIMIT 1";
        $rsDet  = mysql_query($sqlDet);

        if ($rsDet && mysql_num_rows($rsDet) > 0) {
            $row = mysql_fetch_assoc($rsDet);

            $marca = isset($row['D001E_Marca']) ? $row['D001E_Marca'] : '';
            if (empty($marca) && !empty($row['D001E_Json_Nativo'])) {
                $marca = extrairMarcaJsonAnyMarket($row['D001E_Json_Nativo']);
            }

            $resT = analiseTitulo($row['D001E_Titulo']);
            $resD = analiseDescricao($row['D001E_Descricao']);
            $resI = analiseImagens($row);
            $resA = analiseAtributos($row);

            $imgs = [];
            for ($i = 1; $i <= 10; $i++)
                if (!empty($row["D001E_Imagem_$i"]))
                    $imgs[] = $row["D001E_Imagem_$i"];
            if (empty($imgs))
                $imgs[] = "https://via.placeholder.com/600x600?text=Sem+Imagem";

            $specs = [
                'EAN'         => $row['D001E_EAN'] ?? '',
                'Garantia'    => $row['D001E_garantia'] ?? '',
                'Peso'        => $row['D001E_peso'] ? $row['D001E_peso'] . ' kg' : '',
                'Altura'      => $row['D001E_altura'] ? $row['D001E_altura'] . ' cm' : '',
                'Largura'     => $row['D001E_largura'] ? $row['D001E_largura'] . ' cm' : '',
                'Comprimento' => $row['D001E_comprimento'] ? $row['D001E_comprimento'] . ' cm' : '',
            ];

            echo json_encode([
                'ok'     => 1,
                'titulo' => $row['D001E_Titulo'],
                'sku'    => $row['D001E_D001_Codigo_Produto'],
                'marca'  => $marca,
                'desc'   => $row['D001E_Descricao'],
                'imgs'   => $imgs,
                'specs'  => $specs,
                'scores' => [
                    'tit'  => $resT['nota'],
                    'desc' => $resD['nota'],
                    'img'  => $resI['nota'],
                    'attr' => $resA['nota']
                ]
            ]);
        }
        else {
            echo json_encode(['ok' => 0, 'msg' => 'Produto não encontrado']);
        }
        exit;
    }

    // LISTAGEM PADRÃO
    $totalRows = 0;
    $rsCount   = mysql_query("SELECT COUNT(*) AS total FROM D001E");
    if ($rsCount) {
        $r         = mysql_fetch_assoc($rsCount);
        $totalRows = (int) ($r['total'] ?? 0);
    }

    // --- SQL LISTA (ATUALIZADO PARA 4 COLUNAS D009) ---
    $sql = "SELECT T1.*, 
                   T2.D009_Frequencia_Venda, 
                   T2.D009_Valor_Custo_Unitario,
                   T2.D009_Quantidade_Estoque_Tabela,
                   T2.D009_Quantidade_Estoque_Liquido
            FROM D001E AS T1
            LEFT JOIN D049 ON D049.D049_D001_Id = T1.D001E_D001_Id
            LEFT JOIN D009 AS T2 ON (T2.D009_D049_Id = D049.D049_Id AND T2.D009_C004_Id = $C004_Id)
            GROUP BY T1.D001E_Id
            ORDER BY T1.D001E_Id ASC LIMIT $limit OFFSET $offset";
    // --------------------------------------------------

    $rs   = mysql_query($sql);
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
// [STYLE] CSS
// =============================================================================
$style = <<<STYLE
<style>
    :root { --bg-page: #2F384D; --bg-card: #ffffff; --score-6: #004085; --score-5: #28a745; --score-4: #85e085; --score-3: #ffc107; --score-2: #ffadad; --score-1: #dc3545; --primary-color: #2F384D; }
    body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif; background-color: var(--bg-page); margin: 0; padding: 16px; color: #1f2433; }
    .quality-list { background: var(--bg-card); border-radius: 16px; box-shadow: 0 14px 30px rgba(0, 0, 0, 0.18); overflow: hidden; border: 1px solid rgba(255, 255, 255, 0.08); }
    
    /* GRID ATUALIZADO: 4 COLUNAS DE DADOS NO MEIO */
    /* Foto | Prod | Marca | Freq | Custo | Tab | Liq | Desc | Espec | Scores... */
    .quality-header, .quality-row { 
        display: grid; 
        grid-template-columns: 70px 1.4fr 0.7fr 0.5fr 0.6fr 0.5fr 0.5fr 1.2fr 0.9fr 48px 48px 48px 48px 80px; 
        gap: 8px; 
        align-items: stretch; 
    }
    
    .quality-header { background: #f5f6fa; padding: 12px 18px; font-size: 11px; font-weight: 700; color: #6b7280; border-bottom: 1px solid #e5e7eb; text-transform: uppercase; letter-spacing: 0.04em; }
    .quality-row { border-bottom: 1px solid #f0f1f5; padding: 12px 18px; transition: background 0.18s ease; background: #ffffff; }
    .quality-row:nth-child(even) { background: #fbfcff; }
    .quality-row:hover { background-color: #f3f5ff; }
    
    #content { width: 100%; }
    .thumb-box { width: 62px; height: 62px; border-radius: 12px; border: 1px solid #e5e7eb; padding: 4px; background: linear-gradient(135deg, #ffffff, #f4f5fb); cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all 0.2s; }
    .thumb-box img { width: 100%; height: 100%; object-fit: contain; }
    .thumb-box:hover { border-color: var(--primary-color); transform: scale(1.05); }
    .col-product { display: flex; flex-direction: column; justify-content: center; }
    .prod-title { font-size: 13px; font-weight: 700; margin-bottom: 4px; color: #111827; }
    .prod-sku { font-size: 10px; color: #6b7280; background: #eef2ff; padding: 3px 8px; border-radius: 999px; display: inline-flex; font-weight: 600; }
    .col-brand { font-size: 11px; font-weight: 600; color: #4b5563; background: #fff8f0; padding: 4px 8px; border-radius: 6px; border: 1px solid #ffe0d0; display: flex; align-items: center; justify-content: center; height: fit-content; align-self: center; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .col-box-scroll { font-size: 11px; color: #4b5563; max-height: 78px; overflow-y: auto; background: #f9fafb; padding: 8px 10px; border-radius: 10px; border: 1px solid #e5e7eb; }
    
    .col-center { display:flex; align-items:center; justify-content:center; font-size:11px; color:#111827; text-align:center; }

    .mini-score-box { display: flex; flex-direction: column; align-items: center; justify-content: center; cursor: help; position: relative; padding: 4px 0; }
    .mini-score-val { width: 30px; height: 30px; border-radius: 999px; background: #e5e7eb; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 11px; color: #ffffff; margin-bottom: 3px; }
    .mini-score-lbl { font-size: 8px; color: #6b7280; text-transform: uppercase; }
    .col-score { display: flex; flex-direction: column; align-items: center; border-left: 1px solid #e5e7eb; padding-left: 8px; }
    .score-circle { position: relative; width: 52px; height: 52px; border-radius: 50%; background: conic-gradient(var(--color) calc(var(--percent) * 1%), #e5e7eb 0); display: flex; align-items: center; justify-content: center; margin-bottom: 4px; }
    .score-circle::before { content: ""; position: absolute; width: 40px; height: 40px; border-radius: 50%; background: #ffffff; }
    .score-number { position: relative; font-size: 16px; font-weight: 800; z-index: 1; color: #111827; }
    .score-label { font-size: 9px; font-weight: 600; color: #374151; text-transform: uppercase; }

    /* TOOLTIP */
    #hardness-custom-tooltip { background: #ffffff; border: 1px solid #e4e6eb; box-shadow: 0 8px 24px rgba(15, 23, 42, 0.25); border-radius: 10px; padding: 0; z-index: 999999; font-size: 12px; color: #111827; min-width: 230px; }
    .tt-table { width: 100%; border-collapse: collapse; margin: 0; }
    .tt-head { background: #f3f4ff; border-bottom: 1px solid #e5e7eb; padding: 10px 12px; font-weight: 700; font-size: 11px; text-transform: uppercase; }
    .tt-row { border-bottom: 1px solid #f3f4f6; padding: 8px 12px; color: #4b5563; font-size: 11px; }
    .tt-val { border-bottom: 1px solid #f3f4f6; padding: 8px 12px; color: #111827; text-align: right; font-weight: 600; font-size: 11px; }
    .tt-foot td { background: #f9fafb; font-weight: 800; color: var(--primary-color); border-top: 2px solid #e5e7eb; padding: 10px 12px; border-radius: 0 0 10px 10px; }
    .header-tooltip-content { padding: 10px 12px; }
    .header-tooltip-title { font-weight: 700; color: var(--primary-color); border-bottom: 2px solid #e5e7eb; padding-bottom: 8px; margin-bottom: 8px; font-size: 12px; text-transform: uppercase; }
    .header-rule-row { display: flex; justify-content: space-between; font-size: 11px; margin-bottom: 4px; color: #4b5563; }

    /* MODAL */
    .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.88); z-index: 9999; justify-content: center; align-items: center; backdrop-filter: blur(5px); padding: 10px; box-sizing: border-box; }
    .modal-content { background: #ffffff; width: 100%; max-width: 1220px; height: 88%; border-radius: 18px; position: relative; display: flex; overflow: hidden; box-shadow: 0 24px 48px rgba(15, 23, 42, 0.55); border: 1px solid rgba(15, 23, 42, 0.15); }
    .close-modal { position: absolute; top: 14px; right: 18px; font-size: 26px; cursor: pointer; z-index: 100; color: #4b5563; width: 30px; height: 30px; border-radius: 999px; display: flex; align-items: center; justify-content: center; transition: all 0.2s; }
    .close-modal:hover { background: #f3f4f6; color: #111827; }
    .vis-thumbs { width: 120px; background: #f5f6fa; padding: 20px 10px; overflow-y: auto; display: flex; flex-direction: column; gap: 10px; border-right: 1px solid #e5e7eb; }
    .vis-mini { width: 100%; height: 80px; object-fit: cover; border: 2px solid transparent; border-radius: 10px; cursor: pointer; background: #ffffff; }
    .vis-mini.active { border-color: var(--primary-color); box-shadow: 0 4px 10px rgba(15, 23, 42, 0.4); }
    .vis-main { flex: 1; display: flex; justify-content: center; align-items: center; background: radial-gradient(circle at top left, #f9fafb, #e5e7eb); padding: 30px; position: relative; }
    .vis-main img { max-width: 100%; max-height: 100%; object-fit: contain; }
    .vis-score-badge { position: absolute; top: 18px; left: 18px; padding: 4px 14px; border-radius: 999px; font-size: 11px; font-weight: 700; color: #ffffff; background: var(--primary-color); z-index: 10; text-transform: uppercase; }
    .vis-info { width: 360px; border-left: 1px solid #e5e7eb; padding: 24px 24px 20px 24px; overflow-y: auto; background: #ffffff; display: flex; flex-direction: column; gap: 14px; }
    .vis-h1 { font-size: 20px; font-weight: 700; margin: 0 0 4px 0; line-height: 1.3; color: #111827; display: flex; flex-wrap: wrap; align-items: center; gap: 8px; }
    .vis-chip { padding: 3px 10px; border-radius: 999px; font-size: 11px; font-weight: 700; color: #ffffff; background: var(--primary-color); white-space: nowrap; display: inline-block; }
    .vis-meta { font-size: 13px; color: #6b7280; margin-bottom: 6px; padding-bottom: 10px; border-bottom: 1px solid #e5e7eb; }
    .vis-specs-container { margin-top: 6px; padding-top: 10px; border-top: 1px solid #e5e7eb; }
    .vis-specs-table { width: 100%; border-collapse: collapse; font-size: 12px; }
    .vis-specs-table td { padding: 6px 0; border-bottom: 1px solid #f3f4f6; color: #4b5563; }
    .vis-btn-print { width: 100%; padding: 11px 12px; background: var(--primary-color); color: #ffffff; border: none; border-radius: 10px; font-size: 13px; font-weight: 600; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; margin-bottom: 8px; }
    .vis-header-row { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; font-weight: 700; font-size: 13px; color: #111827; }
    .vis-desc-box { font-size: 13px; line-height: 1.6; color: #4b5563; background: #f9fafb; padding: 14px; border-radius: 10px; border: 1px solid #e5e7eb; max-height: 200px; overflow-y: auto; }
    
    @media (max-width: 1200px) { .quality-header, .quality-row { grid-template-columns: 60px 1.4fr 0.7fr 0.5fr 0.6fr 0.5fr 0.5fr 1.0fr 0.9fr 44px 44px 44px 44px 72px; gap: 8px; } .vis-info { width: 320px; } }
    @media (max-width: 900px) {
        .quality-header { display: none; }
        .quality-row { grid-template-columns: 70px 1fr; grid-template-rows: auto; }
        .quality-row > div:nth-child(1) { grid-column: 1 / 2; grid-row: 1 / 2; }
        .quality-row > div:nth-child(2) { grid-column: 2 / 3; grid-row: 1 / 2; }
        .quality-row > div:nth-child(3) { grid-column: 1 / 3; grid-row: 2 / 3; }
        
        /* 4 Colunas no mobile */
        .quality-row > div:nth-child(4) { grid-column: 1 / 2; grid-row: 3 / 4; text-align:left; justify-content:flex-start; font-weight:bold; }
        .quality-row > div:nth-child(5) { grid-column: 2 / 3; grid-row: 3 / 4; text-align:left; justify-content:flex-start; }
        .quality-row > div:nth-child(6) { grid-column: 1 / 2; grid-row: 4 / 5; text-align:left; justify-content:flex-start; }
        .quality-row > div:nth-child(7) { grid-column: 2 / 3; grid-row: 4 / 5; text-align:left; justify-content:flex-start; }
        
        .quality-row > div:nth-child(8) { grid-column: 1 / 3; grid-row: 5 / 6; }
        .quality-row > div:nth-child(9) { grid-column: 1 / 3; grid-row: 6 / 7; }
        
        .quality-row > div:nth-child(10){ grid-column: 1 / 2; grid-row: 7 / 8; }
        .quality-row > div:nth-child(11){ grid-column: 2 / 3; grid-row: 7 / 8; }
        .quality-row > div:nth-child(12){ grid-column: 1 / 2; grid-row: 8 / 9; }
        .quality-row > div:nth-child(13){ grid-column: 2 / 3; grid-row: 8 / 9; }
        .quality-row > div:nth-child(14){ grid-column: 1 / 3; grid-row: 9 / 10; margin-top: 6px; }
        
        .col-score { border-left: none; border-top: 1px solid #e5e7eb; padding-left: 0; padding-top: 8px; }
        .modal-content { flex-direction: column; height: 92%; }
        .vis-thumbs { width: 100%; height: 90px; flex-direction: row; border-right: none; border-bottom: 1px solid #e5e7eb; padding: 10px; }
        .vis-info { width: 100%; border-left: none; border-top: 1px solid #e5e7eb; }
    }
    #demo { padding: 12px 18px; background: #f5f6fa; border-top: 1px solid #e5e7eb; display:flex; flex-wrap:wrap; align-items:center; gap:6px; }
    #demo .pg-btn { border: 1px solid #e5e7eb; background:#fff; padding:6px 10px; border-radius:10px; cursor:pointer; font-size:12px; font-weight:800; color:#111827; }
    #demo .pg-btn.disabled { opacity: .45; cursor:not-allowed; }
    #demo .pg-btn.active { background: var(--primary-color); border-color: var(--primary-color); color:#fff; }
</style>
<link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
STYLE;
if (!$apiMode)
    echo $style;

// =============================================================================
// [RENDER] HTML ESTRUTURA
// =============================================================================
$tipTit  = "<div class='header-tooltip-content'><div class='header-tooltip-title'>REGRAS: TÍTULO (Peso 3)</div><div class='header-rule-row'><span>< 30 chars</span><span class='header-rule-val' style='color:#dc3545'>1</span></div><div class='header-rule-row'><span>30 a 39 chars</span><span class='header-rule-val' style='color:#ffc107'>2</span></div><div class='header-rule-row'><span>40 a 49 chars</span><span class='header-rule-val' style='color:#28a745'>3</span></div><div class='header-rule-row'><span>50 a 59 chars</span><span class='header-rule-val' style='color:#28a745'>4</span></div><div class='header-rule-row'><span>60 a 89 chars</span><span class='header-rule-val' style='color:#007bff'>5</span></div></div>";
$tipDesc = "<div class='header-tooltip-content'><div class='header-tooltip-title'>REGRAS: DESCRIÇÃO (Peso 3)</div><div class='header-rule-row'><span>< 200 chars</span><span class='header-rule-val' style='color:#dc3545'>1</span></div><div class='header-rule-row'><span>200 a 399</span><span class='header-rule-val' style='color:#ffc107'>2</span></div><div class='header-rule-row'><span>400 a 599</span><span class='header-rule-val' style='color:#28a745'>3</span></div><div class='header-rule-row'><span>600 a 1999</span><span class='header-rule-val' style='color:#28a745'>4</span></div><div class='header-rule-row'><span>2000 a 4000</span><span class='header-rule-val' style='color:#007bff'>5</span></div></div>";
$tipImg  = "<div class='header-tooltip-content'><div class='header-tooltip-title'>REGRAS: IMAGENS (Peso 3)</div><div class='header-rule-row'><span>< 2 imgs</span><span class='header-rule-val' style='color:#dc3545'>1</span></div><div class='header-rule-row'><span>2 imgs</span><span class='header-rule-val' style='color:#ffc107'>3</span></div><div class='header-rule-row'><span>3 a 4 imgs</span><span class='header-rule-val' style='color:#28a745'>4</span></div><div class='header-rule-row'><span>5 a 10 imgs</span><span class='header-rule-val' style='color:#007bff'>5</span></div><div class='header-rule-row'><span>> 10 imgs</span><span class='header-rule-val' style='color:#dc3545'>0</span></div></div>";
$tipSpec = "<div class='header-tooltip-content'><div class='header-tooltip-title'>REGRAS: ATRIBUTOS (Peso 1)</div><div class='header-rule-row'><span>< 2 itens</span><span class='header-rule-val' style='color:#dc3545'>1</span></div><div class='header-rule-row'><span>2 a 3 itens</span><span class='header-rule-val' style='color:#ffc107'>3</span></div><div class='header-rule-row'><span>4 a 6 itens</span><span class='header-rule-val' style='color:#28a745'>4</span></div><div class='header-rule-row'><span>7 a 19 itens</span><span class='header-rule-val' style='color:#007bff'>5</span></div></div>";

echo "<div class='quality-list'>";
echo "<div class='quality-header'>
        <div>Foto</div><div>Produto & Título</div><div>Marca</div>
        <div style='text-align:center'>Freq.</div><div style='text-align:center'>Custo</div><div style='text-align:center'>Est.Tab</div><div style='text-align:center'>Liq</div>
        <div>Análise de Descrição</div><div>Especificações</div>
        <div style='text-align:center; cursor:help' onmouseenter='window.showHTooltip(this)' onmousemove='window.moveHTooltip(event)' onmouseleave='window.hideHTooltip()'>TÍTULO<div class='tooltip-hidden-content' style='display:none'>$tipTit</div></div>
        <div style='text-align:center; cursor:help' onmouseenter='window.showHTooltip(this)' onmousemove='window.moveHTooltip(event)' onmouseleave='window.hideHTooltip()'>DESC<div class='tooltip-hidden-content' style='display:none'>$tipDesc</div></div>
        <div style='text-align:center; cursor:help' onmouseenter='window.showHTooltip(this)' onmousemove='window.moveHTooltip(event)' onmouseleave='window.hideHTooltip()'>IMG<div class='tooltip-hidden-content' style='display:none'>$tipImg</div></div>
        <div style='text-align:center; cursor:help' onmouseenter='window.showHTooltip(this)' onmousemove='window.moveHTooltip(event)' onmouseleave='window.hideHTooltip()'>ESPEC<div class='tooltip-hidden-content' style='display:none'>$tipSpec</div></div>
        <div style='text-align:center'>Geral</div>
      </div>";

$totalRows = 0;
$rsCount   = mysql_query("SELECT COUNT(*) AS total FROM D001E");
if ($rsCount) {
    $r         = mysql_fetch_assoc($rsCount);
    $totalRows = (int) ($r['total'] ?? 0);
}

// --- SQL INICIAL (4 COLUNAS D009 + D049 PONTE + FILTRO EMPRESA) ---
$sql = "SELECT T1.*, 
               T2.D009_Frequencia_Venda, 
               T2.D009_Valor_Custo_Unitario,
               T2.D009_Quantidade_Estoque_Tabela, 
               T2.D009_Quantidade_Estoque_Liquido
        FROM D001E AS T1
        LEFT JOIN D049 ON D049.D049_D001_Id = T1.D001E_D001_Id
        LEFT JOIN D009 AS T2 ON (T2.D009_D049_Id = D049.D049_Id AND T2.D009_C004_Id = $C004_Id)
        GROUP BY T1.D001E_Id
        ORDER BY T1.D001E_Id ASC LIMIT $limit OFFSET 0";
// ------------------------------------------------------------------

$rs = mysql_query($sql);

echo "<div id='content'>";
if ($rs) {
    while ($row = mysql_fetch_assoc($rs)) {
        echo renderQualityRow($row);
    }
}
echo "</div>";

// Inputs Ocultos
$ajaxUrl  = isset($_SERVER['REQUEST_URI']) ? htmlspecialchars($_SERVER['REQUEST_URI'], ENT_QUOTES, 'UTF-8') : '';
$sysDivId = isset($g['divId']) ? $g['divId'] : 'content';
echo "<input type='hidden' id='hardness_total' value='" . (int) $totalRows . "'>";
echo "<input type='hidden' id='hardness_pageSize' value='" . (int) $limit . "'>";
echo "<input type='hidden' id='hardness_ajaxUrl' value='" . $ajaxUrl . "'>";
echo "<input type='hidden' id='sys_base_divId' value='" . $sysDivId . "'>";
echo "<div id='demo'></div></div>"; // Fim list
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
    // [TOOLTIP]
    // =============================================================================
    if (typeof window.initHardnessTooltip === 'undefined') {
        window.initHardnessTooltip = true;
        var tipDiv = document.createElement('div');
        tipDiv.id = 'hardness-custom-tooltip';
        tipDiv.style.position = 'fixed'; tipDiv.style.display = 'none';
        document.body.appendChild(tipDiv);
        window.showHTooltip = function (el) {
            var c = el.querySelector('.tooltip-hidden-content'); if (!c) return;
            var t = document.getElementById('hardness-custom-tooltip');
            t.innerHTML = c.innerHTML; t.style.display = 'block';
        };
        window.moveHTooltip = function (e) {
            var t = document.getElementById('hardness-custom-tooltip');
            if (t && t.style.display === 'block') {
                var x = e.clientX + 15, y = e.clientY + 15;
                if (x + t.offsetWidth > window.innerWidth) x = e.clientX - t.offsetWidth - 5;
                if (y + t.offsetHeight > window.innerHeight) y = e.clientY - t.offsetHeight - 5;
                t.style.left = x + 'px'; t.style.top = y + 'px';
            }
        };
        window.hideHTooltip = function () { var t = document.getElementById('hardness-custom-tooltip'); if (t) t.style.display = 'none'; };
    }

    // =============================================================================
    // [PAGINATION PLUGIN + MAIN JS]
    // =============================================================================
    (function ($) {
        $.fn.pagination = function (o) {
            var opt = $.extend({ dataSource: 0, pageSize: 50, autoHidePrevious: true, autoHideNext: true, callback: function () { } }, o || {});
            var $w = this, total = parseInt(opt.dataSource, 10) || 0, size = parseInt(opt.pageSize, 10) || 50, pages = Math.max(1, Math.ceil(total / size)), cur = 1;
            function btn(l, p, c) { return '<a href="javascript:void(0)" class="pg-btn ' + (c || '') + '" data-page="' + p + '">' + l + '</a>'; }
            function ren() {
                var h = '', prev = (cur <= 1), next = (cur >= pages), r = 2, s = Math.max(1, cur - r), e = Math.min(pages, cur + r);
                if (!(opt.autoHidePrevious && prev)) h += btn('&lt;', cur - 1, prev ? 'disabled' : '');
                if (s > 1) { h += btn('1', 1, cur === 1 ? 'active' : ''); if (s > 2) h += '<span class="pg-dots">...</span>'; }
                for (var i = s; i <= e; i++) h += btn(String(i), i, cur === i ? 'active' : '');
                if (e < pages) { if (e < pages - 1) h += '<span class="pg-dots">...</span>'; h += btn(String(pages), pages, cur === pages ? 'active' : ''); }
                if (!(opt.autoHideNext && next)) h += btn('&gt;', cur + 1, next ? 'disabled' : '');
                $w.html(h);
            }
            function go(p) {
                p = parseInt(p, 10) || 1; if (p < 1) p = 1; if (p > pages) p = pages; cur = p; ren();
                opt.callback([], { pageNumber: cur, pageSize: size, totalNumber: total, totalPages: pages });
            }
            $w.delegate('.pg-btn', 'click', function () { var $b = $(this); if (!$b.hasClass('disabled') && !$b.hasClass('active')) go($b.attr('data-page')); });
            ren(); go(1); return this;
        };
    })(jQuery);

    jQuery(function ($) {
        var total = parseInt($('#hardness_total').val(), 10) || 0, size = parseInt($('#hardness_pageSize').val(), 10) || 50, url = $('#hardness_ajaxUrl').val(), sysId = $('#sys_base_divId').val();
        var cache = {}, CMAX = 2, cOrd = [];

        function ld(p) {
            p = parseInt(p, 10) || 1;
            if (sysId && $('#' + sysId).length) $('#' + sysId).showLoading();
            setTimeout(function () {
                var c = cache[String(p)];
                if (c) { $('#content').html(c); if (sysId && $('#' + sysId).length) $('#' + sysId).hideLoading(); return; }
                $.ajax({
                    url: url, type: 'POST', dataType: 'json', data: { ajax: 1, page: p, pageSize: size }, success: function (r) {
                        if (r && r.ok) { $('#content').html(r.html); cache[String(p)] = r.html; cOrd.push(String(p)); if (cOrd.length > CMAX) delete cache[cOrd.shift()]; }
                    }, complete: function () { if (sysId && $('#' + sysId).length) $('#' + sysId).hideLoading(); }
                });
            }, 1000);
        }
        cache[1] = $('#content').html();
        $('#demo').pagination({ dataSource: total, pageSize: size, autoHidePrevious: true, autoHideNext: true, callback: function (d, pg) { ld(pg.pageNumber); } });
    });

    // =============================================================================
    // [MODAL AJAX]
    // =============================================================================
    const mVis = document.getElementById('modalVis'), vThumbs = document.getElementById('visThumbs'), vHero = document.getElementById('visHero'), vTitle = document.getElementById('visTitle'), vSku = document.getElementById('visSku'), vBrand = document.getElementById('visBrand'), vDesc = document.getElementById('visDesc'), vSpecs = document.getElementById('visSpecsContent');
    const elTS = document.getElementById('visTitleScore'), elDS = document.getElementById('visDescScore'), elIS = document.getElementById('visImgScore'), elAS = document.getElementById('visAttrScore');

    function getMetaNota(n) {
        n = Number(n);
        if (n === 6) return { c: 'var(--score-6)', t: 'Ótima' };
        if (n === 5) return { c: 'var(--score-5)', t: 'Muito Boa' };
        if (n === 4) return { c: 'var(--score-4)', t: 'Boa' };
        if (n === 3) return { c: 'var(--score-3)', t: 'Média' };
        if (n === 2) return { c: 'var(--score-2)', t: 'Ruim' };
        return { c: 'var(--score-1)', t: 'Muito Ruim' };
    }

    function abrirVisualizador(sku) {
        var url = document.getElementById('hardness_ajaxUrl').value;
        var sysId = document.getElementById('sys_base_divId').value;
        if (sysId && typeof jQuery !== 'undefined' && jQuery('#' + sysId).length) jQuery('#' + sysId).showLoading();

        jQuery.ajax({
            url: url, type: 'POST', dataType: 'json',
            data: { ajax: 1, action: 'get_details', sku: sku },
            success: function (res) {
                if (res.ok) {
                    vTitle.innerText = res.titulo;
                    vSku.innerText = res.sku;
                    vBrand.innerText = res.marca;
                    vDesc.innerHTML = res.desc ? res.desc : '<em>Sem descrição.</em>';

                    const mT = getMetaNota(res.scores.tit); elTS.style.backgroundColor = mT.c; elTS.innerText = res.scores.tit + ' - ' + mT.t;
                    const mD = getMetaNota(res.scores.desc); elDS.style.backgroundColor = mD.c; elDS.innerText = res.scores.desc + ' - ' + mD.t;
                    const mI = getMetaNota(res.scores.img); elIS.style.backgroundColor = mI.c; elIS.innerText = 'Fotos: ' + res.scores.img + ' (' + mI.t + ')';
                    const mA = getMetaNota(res.scores.attr); elAS.style.backgroundColor = mA.c; elAS.innerText = res.scores.attr + ' - ' + mA.t;

                    vThumbs.innerHTML = '';
                    if (res.imgs.length > 0) vHero.src = res.imgs[0];
                    res.imgs.forEach((url, idx) => {
                        let img = document.createElement('img');
                        img.src = url; img.className = 'vis-mini';
                        if (idx === 0) img.classList.add('active');
                        img.onclick = () => { vHero.src = url; document.querySelectorAll('.vis-mini').forEach(el => el.classList.remove('active')); img.classList.add('active'); };
                        vThumbs.appendChild(img);
                    });

                    let h = '<table class="vis-specs-table">'; let has = false;
                    if (res.specs.EAN) { h += `<tr><td><strong>EAN:</strong> ${res.specs.EAN}</td></tr>`; has = true; }
                    if (res.specs.Garantia) { h += `<tr><td><strong>Garantia:</strong> ${res.specs.Garantia}</td></tr>`; has = true; }
                    if (res.specs.Peso) { h += `<tr><td><strong>Peso:</strong> ${res.specs.Peso}</td></tr>`; has = true; }
                    if (res.specs.Altura) { h += `<tr><td><strong>Altura:</strong> ${res.specs.Altura}</td></tr>`; has = true; }
                    if (res.specs.Largura) { h += `<tr><td><strong>Largura:</strong> ${res.specs.Largura}</td></tr>`; has = true; }
                    if (res.specs.Comprimento) { h += `<tr><td><strong>Comp.:</strong> ${res.specs.Comprimento}</td></tr>`; has = true; }
                    h += '</table>';
                    vSpecs.innerHTML = has ? h : '<div style="color:#999;font-size:12px">Vazio</div>';

                    mVis.style.display = 'flex';
                } else { alert(res.msg || 'Erro ao carregar'); }
            },
            error: function () { alert('Erro na comunicação'); },
            complete: function () { if (sysId && typeof jQuery !== 'undefined' && jQuery('#' + sysId).length) jQuery('#' + sysId).hideLoading(); }
        });
    }
    function fecharVis() { mVis.style.display = 'none'; }
    function imprimirConteudoModal() {
        const f = document.createElement('iframe'); f.style.display = 'none'; document.body.appendChild(f);
        const d = f.contentWindow.document; const s = vSpecs.innerHTML;
        const c = `<html><head><style>body{font-family:Arial,sans-serif;padding:20px;color:#333}h1{font-size:24px;margin-bottom:5px}.meta{color:#666;font-size:12px;margin-bottom:20px;border-bottom:1px solid #ccc;padding-bottom:10px}.hero{text-align:center;margin-bottom:20px}.hero img{max-width:300px;max-height:300px}.desc{font-size:12px;line-height:1.5;margin-bottom:20px}.specs-box{border:1px solid #eee;padding:10px;border-radius:5px}.specs-box table{width:100%;font-size:12px}.specs-box td{padding:4px 0}</style></head><body><h1>${vTitle.innerText}</h1><div class="meta">SKU: ${vSku.innerText}</div><div class="hero"><img src="${vHero.src}"></div><h3>Descrição</h3><div class="desc">${vDesc.innerHTML}</div><h3>Specs</h3><div class="specs-box">${s}</div></body></html>`;
        d.open(); d.write(c); d.close(); setTimeout(() => { f.contentWindow.print(); setTimeout(() => document.body.removeChild(f), 1000); }, 200);
    }
    document.addEventListener('keydown', e => { if (e.key === "Escape") fecharVis() });
</script>