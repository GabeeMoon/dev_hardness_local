<?php
/*
 PAINEL DE MELHORIA DE ANUNCIO (D001E) - QUALITY SCORE 3.0
 ATUALIZADO: LOGICA MASTER POPULADOR + CONSOLE DE PROGRESSO
 */
namespace hardness;

global $g, $confUsuario;

// =============================================================================
// [1. CONFIGURAÇÃO E CONTEXTO]
// =============================================================================
error_reporting(E_ALL & ~E_NOTICE);
ini_set('display_errors', 1);

$qtdePorPagina = 40;
$limit         = $qtdePorPagina;

// Recuperação de variáveis de ambiente
$gDivRoot = isset($g['divRoot']) && $g['divRoot'] != "" ? $g['divRoot'] : (isset($_POST['sys_divRoot']) ? $_POST['sys_divRoot'] : (isset($_POST['divIdRoot']) ? $_POST['divIdRoot'] : ''));
$gDivId   = isset($g['divId']) && $g['divId'] != "" ? $g['divId'] : (isset($_POST['sys_divId']) ? $_POST['sys_divId'] : (isset($_POST['divId']) ? $_POST['divId'] : 'contentMel'));
$C004_Id  = isset($g['empresaAtual']) ? (int) $g['empresaAtual'] : 1;

// Paginação
$page = isset($_POST['page']) ? (int) $_POST['page'] : 1;
if ($page < 1) $page = 1;
if (isset($_POST['pageSize'])) {
    $tmp = (int) $_POST['pageSize'];
    if ($tmp > 0) $limit = $tmp;
}
$offset = ($page - 1) * $limit;
$isAjax = (isset($_POST['ajax']) && (int) $_POST['ajax'] === 1);
$apiMode = 0; 

// =============================================================================
// [2. FUNÇÕES DE LÓGICA (SCORE)]
// =============================================================================

if (!function_exists('extrairMarcaJsonAnyMarketMel')) {
    function extrairMarcaJsonAnyMarketMel($jsonString) {
        if (empty($jsonString)) return "";
        $obj = json_decode($jsonString);
        if (isset($obj->content[0]->brand->name)) return trim($obj->content[0]->brand->name);
        return "";
    }
}

if (!function_exists('analiseTituloMel')) {
    function analiseTituloMel($titulo) {
        $len = mb_strlen(trim($titulo));
        $n = 0; $regra = "";
        if ($len < 10) { $n = 1; $regra = "< 10 chars"; } 
        elseif ($len < 20) { $n = 2; $regra = "10-19 chars"; } 
        elseif ($len < 40) { $n = 3; $regra = "20-39 chars"; } 
        elseif ($len < 50) { $n = 4; $regra = "40-49 chars"; } 
        elseif ($len <= 60) { $n = 5; $regra = "50-60 chars"; } 
        else { $n = 0; $regra = "> 60 chars (Excesso)"; }
        return ['nota' => $n, 'valor' => $len . ' chars', 'regra' => $regra, 'peso' => 3];
    }
}

if (!function_exists('analiseDescricaoMel')) {
    function analiseDescricaoMel($html) {
        $txt = trim(strip_tags($html));
        $len = mb_strlen($txt);
        $n = 0; $regra = "";
        if ($len < 200) { $n = 1; $regra = "< 200 chars"; } 
        elseif ($len < 400) { $n = 2; $regra = "200-399 chars"; } 
        elseif ($len < 600) { $n = 3; $regra = "400-599 chars"; } 
        elseif ($len < 2000) { $n = 4; $regra = "600-1999 chars"; } 
        elseif ($len <= 4000) { $n = 5; $regra = "2000-4000 chars"; } 
        else { $n = 0; $regra = "> 4000 chars (Excesso)"; }
        return ['nota' => $n, 'valor' => $len . ' chars', 'regra' => $regra, 'peso' => 3];
    }
}

if (!function_exists('analiseImagensMel')) {
    function analiseImagensMel($row) {
        $qtd = 0;
        for ($i = 1; $i <= 10; $i++) if (!empty($row["D001E_Imagem_$i"])) $qtd++;
        $n = 0; $regra = "";
        if ($qtd < 2) { $n = 1; $regra = "< 2 imgs"; } 
        elseif ($qtd < 3) { $n = 3; $regra = "2 imgs"; } 
        elseif ($qtd < 4) { $n = 4; $regra = "3 imgs"; } 
        elseif ($qtd < 5) { $n = 4; $regra = "4 imgs"; } 
        elseif ($qtd <= 10) { $n = 5; $regra = "5-10 imgs"; } 
        else { $n = 0; $regra = "> 10 imgs (Excesso)"; }
        return ['nota' => $n, 'valor' => $qtd . ' fotos', 'regra' => $regra, 'peso' => 3];
    }
}

if (!function_exists('analiseAtributosMel')) {
    function analiseAtributosMel($row) {
        $count = 0;
        if (!empty($row['D001E_EAN']) && trim($row['D001E_EAN']) !== '') $count++;
        if (!empty($row['D001E_garantia']) && trim($row['D001E_garantia']) !== '') $count++;
        if (!empty($row['D001E_peso']) && trim($row['D001E_peso']) !== '') $count++;
        if (!empty($row['D001E_altura']) && trim($row['D001E_altura']) !== '') $count++;
        if (!empty($row['D001E_largura']) && trim($row['D001E_largura']) !== '') $count++;
        if (!empty($row['D001E_comprimento']) && trim($row['D001E_comprimento']) !== '') $count++;
        $n = 0; $regra = "";
        if ($count < 2) { $n = 1; $regra = "< 2 atrib."; } 
        elseif ($count < 4) { $n = 3; $regra = "2-3 atrib."; } 
        elseif ($count < 7) { $n = 4; $regra = "4-6 atrib."; } 
        else { $n = 5; $regra = "Completo (>=7)"; }
        return ['nota' => $n, 'valor' => $count . ' preench.', 'regra' => $regra, 'peso' => 1];
    }
}

if (!function_exists('getCorNotaMel')) {
    function getCorNotaMel($n) {
        switch ($n) {
            case 6: return "#0098D3"; case 5: return "#10b981"; case 4: return "#84cc16"; 
            case 3: return "#eab308"; case 2: return "#fca5a5"; case 1: default: return "#ef4444"; 
        }
    }
}

if (!function_exists('gerarTooltipHtmlMel')) {
    function gerarTooltipHtmlMel($titulo, $arrAnalise) {
        return "<table class='tt-table'>
            <tr><th colspan='2' class='tt-head'>ANÁLISE: $titulo</th></tr>
            <tr><td class='tt-row'>Valor Atual</td><td class='tt-val'>{$arrAnalise['valor']}</td></tr>
            <tr><td class='tt-row'>Regra</td><td class='tt-val'>{$arrAnalise['regra']}</td></tr>
            <tr><td class='tt-row'>Peso</td><td class='tt-val'>{$arrAnalise['peso']}</td></tr>
            <tr class='tt-foot'><td class='tt-row'>Nota Calc.</td><td class='tt-val'>{$arrAnalise['nota']}</td></tr>
        </table>";
    }
}

if (!function_exists('gerarTooltipGeralMel')) {
    function gerarTooltipGeralMel($resT, $resD, $resI, $resA) {
        $cT = getCorNotaMel($resT['nota']); $cD = getCorNotaMel($resD['nota']); $cI = getCorNotaMel($resI['nota']); $cA = getCorNotaMel($resA['nota']);
        return "<table class='tt-table'>
            <tr><th colspan='2' class='tt-head'>CÁLCULO (DIVISOR 10)</th></tr>
            <tr><td class='tt-row'>Título</td><td class='tt-val' style='color:$cT'>{$resT['nota']}</td></tr>
            <tr><td class='tt-row'>Descrição</td><td class='tt-val' style='color:$cD'>{$resD['nota']}</td></tr>
            <tr><td class='tt-row'>Imagens</td><td class='tt-val' style='color:$cI'>{$resI['nota']}</td></tr>
            <tr><td class='tt-row'>Atributos</td><td class='tt-val' style='color:$cA'>{$resA['nota']}</td></tr>
        </table>";
    }
}

// =============================================================================
// [3. RENDERIZAÇÃO DA LINHA (HTML)]
// =============================================================================
function renderQualityRowMel($row) {
    global $gDivRoot, $gDivId, $g;
    $con = isset($g['conexaoBanco']) ? $g['conexaoBanco'] : null;

    $marca = isset($row['D001E_Marca']) ? $row['D001E_Marca'] : '';
    $updateMarca = false;
    if (empty($marca) && !empty($row['D001E_Json_Nativo'])) {
        $marcaExtraida = extrairMarcaJsonAnyMarketMel($row['D001E_Json_Nativo']);
        if (!empty($marcaExtraida)) { $marca = $marcaExtraida; $updateMarca = true; } else { $marca = "ND"; }
    }

    $resT = analiseTituloMel($row['D001E_Sku_Titulo']);
    $resD = analiseDescricaoMel($row['D001E_Descricao']);
    $resI = analiseImagensMel($row);
    $resA = analiseAtributosMel($row);

    $soma  = ($resT['nota'] * 3) + ($resD['nota'] * 3) + ($resI['nota'] * 3) + ($resA['nota'] * 1);
    $final = floor($soma / 10);
    $final = max(1, min(5, $final));

    $idProd  = (int) $row['D001E_Id'];
    $sqlSets = [];
    if ($row['D001E_Status_Pontuacao'] != $final) $sqlSets[] = "D001E_Status_Pontuacao = $final";
    if ($row['D001E_pont_titulo'] != $resT['nota']) $sqlSets[] = "D001E_pont_titulo = {$resT['nota']}";
    if ($row['D001E_pont_desc'] != $resD['nota']) $sqlSets[] = "D001E_pont_desc = {$resD['nota']}";
    if ($row['D001E_pont_img'] != $resI['nota']) $sqlSets[] = "D001E_pont_img = {$resI['nota']}";
    if ($row['D001E_pont_espec'] != $resA['nota']) $sqlSets[] = "D001E_pont_espec = {$resA['nota']}";
    
    if ($updateMarca && $con) { 
        $marcaSafe = \mysqli_real_escape_string($con, $marca); 
        $sqlSets[] = "D001E_Marca = '$marcaSafe'"; 
    }
    
    if (!empty($sqlSets) && $con) { 
        $sqlUpdate = "UPDATE D001E SET " . implode(', ', $sqlSets) . " WHERE D001E_Id = $idProd"; 
        @\mysqli_query($con, $sqlUpdate); 
    }

    switch ($final) {
        case 6: $c = "#0098D3"; $p = 100; $l = "Ótima"; break;
        case 5: $c = "#10b981"; $p = 85; $l = "Muito Boa"; break;
        case 4: $c = "#84cc16"; $p = 70; $l = "Boa"; break;
        case 3: $c = "#eab308"; $p = 50; $l = "Média"; break;
        case 2: $c = "#fca5a5"; $p = 30; $l = "Ruim"; break;
        default: $c = "#ef4444"; $p = 15; $l = "Muito Ruim"; break;
    }

    $imgCapa   = $row['D001E_Imagem_1'] ?: "https://via.placeholder.com/100x100?text=Sem+Img";
    $titulo    = htmlspecialchars($row['D001E_Sku_Titulo'], ENT_QUOTES);
    $sku       = htmlspecialchars($row['D001E_D001_Codigo_Produto'], ENT_QUOTES);
    $descRaw   = $row['D001E_Descricao'];
    $marcaHtml = htmlspecialchars($marca, ENT_QUOTES);
    $idAny     = !empty($row['D001E_Id_Any']) ? $row['D001E_Id_Any'] : 'ND';

    $specHtml = "";
    if (!empty($row['D001E_EAN'])) $specHtml .= "<b>EAN:</b> {$row['D001E_EAN']}<br>";
    if (!empty($row['D001E_garantia'])) $specHtml .= "<b>Gar:</b> {$row['D001E_garantia']}<br>";
    if (!empty($row['D001E_peso'])) $specHtml .= "<b>Peso:</b> {$row['D001E_peso']}<br>";
    if (!empty($row['D001E_altura'])) $specHtml .= "<b>Dim:</b> " . ($row['D001E_altura'] ?: 0) . "x" . ($row['D001E_largura'] ?: 0) . "x" . ($row['D001E_comprimento'] ?: 0);
    if (empty($specHtml)) $specHtml = "<span style='color:#bbb'>Vazio</span>";

    $freqVenda  = !empty($row['D009_Frequencia_Venda']) ? $row['D009_Frequencia_Venda'] : '<b>0</b>';
    $custoVal   = isset($row['D009_Valor_Custo_Unitario']) ? (float) $row['D009_Valor_Custo_Unitario'] : 0;
    $estTab     = isset($row['D009_Quantidade_Estoque_Tabela']) ? (int) $row['D009_Quantidade_Estoque_Tabela'] : 0;
    $estLiq     = isset($row['D009_Quantidade_Estoque_Liquido']) ? (int) $row['D009_Quantidade_Estoque_Liquido'] : 0;
    $custoHtml  = ($custoVal > 0) ? "<span style='color:#0098D3; font-weight:700;'>R$ " . number_format($custoVal, 2, ',', '.') . "</span>" : "<b>0</b>";
    $estTabHtml = ($estTab > 0) ? $estTab : "<b>0</b>";
    $estLiqHtml = ($estLiq > 0) ? $estLiq : "<b>0</b>";
    $d001Id     = $row['D001E_D001_Id'];
    
    $jsForn = "abrirJanela(false, '{$gDivRoot}', '{$gDivId}', unique(), '', 'Anuncio', '/cad/cad002/content/form2/', '&acaoId=' + encodeURIComponent('{$d001Id}'), [700,400]); return false;";

    return "
    <div class='quality-row-mel'>
        <div class='col-check'><input type='checkbox' class='row-check' value='$idProd'></div>
        <div class='thumb-box' onclick='abrirVisualizadorMel(\"$sku\")'><img src='$imgCapa'></div>
        <div class='col-info'>
            <div class='prod-title'>{$row['D001E_Sku_Titulo']}</div>
            <div class='prod-sub'>
                <span class='badge-any' title='ID AnyMarket' style='cursor:pointer' onclick='window.open(\"https://app.anymarket.com.br/app-js/products/edit/$idAny\", \"_blank\"); event.stopPropagation();'>Id Any: $idAny</span>
                <span class='badge-sku' title='SKU Produto' style='cursor:pointer' onclick=\"$jsForn\">Sku: $sku</span>
                <span class='badge-brand' title='$marcaHtml'>Marca: $marcaHtml</span>
            </div>
        </div>
        <div class='col-metrics'>
            <div class='metric-cell'><span class='lbl'>Freq</span> <span class='val'>$freqVenda</span></div>
            <div class='metric-cell'><span class='lbl'>Custo</span> <span class='val'>$custoHtml</span></div>
            <div class='metric-cell'><span class='lbl'>Estq. Tab</span> <span class='val'>$estTabHtml</span></div>
            <div class='metric-cell'><span class='lbl'>Estq. Liq</span> <span class='val'>$estLiqHtml</span></div>
        </div>
        <div class='col-box-scroll desc-scroll-mel'>" . ($descRaw ?: '<em>Sem descrição</em>') . "</div>
        <div class='col-box-scroll spec-auto-mel'>$specHtml</div>
        <div class='mini-score-box' onmouseenter='window.showHTooltip(this)' onmousemove='window.moveHTooltip(event)' onmouseleave='window.hideHTooltip()'>
            <div class='mini-score-val' style='background:" . getCorNotaMel($resT['nota']) . "'>{$resT['nota']}</div>
            <div class='tooltip-hidden-content' style='display:none'>" . gerarTooltipHtmlMel("Título", $resT) . "</div>
        </div>
        <div class='mini-score-box' onmouseenter='window.showHTooltip(this)' onmousemove='window.moveHTooltip(event)' onmouseleave='window.hideHTooltip()'>
            <div class='mini-score-val' style='background:" . getCorNotaMel($resD['nota']) . "'>{$resD['nota']}</div>
            <div class='tooltip-hidden-content' style='display:none'>" . gerarTooltipHtmlMel("Descrição", $resD) . "</div>
        </div>
        <div class='mini-score-box' onmouseenter='window.showHTooltip(this)' onmousemove='window.moveHTooltip(event)' onmouseleave='window.hideHTooltip()'>
            <div class='mini-score-val' style='background:" . getCorNotaMel($resI['nota']) . "'>{$resI['nota']}</div>
            <div class='tooltip-hidden-content' style='display:none'>" . gerarTooltipHtmlMel("Imagens", $resI) . "</div>
        </div>
        <div class='mini-score-box' onmouseenter='window.showHTooltip(this)' onmousemove='window.moveHTooltip(event)' onmouseleave='window.hideHTooltip()'>
            <div class='mini-score-val' style='background:" . getCorNotaMel($resA['nota']) . "'>{$resA['nota']}</div>
            <div class='tooltip-hidden-content' style='display:none'>" . gerarTooltipHtmlMel("Atributos", $resA) . "</div>
        </div>
        <div class='col-score' style='cursor:help' onmouseenter='window.showHTooltip(this)' onmousemove='window.moveHTooltip(event)' onmouseleave='window.hideHTooltip()'>
            <div class='score-circle' style='--color:$c; --percent:$p;'>
                <span class='score-number'>$final</span>
            </div>
            <span class='score-label' style='color:$c'>$l</span>
            <div class='tooltip-hidden-content' style='display:none'>" . gerarTooltipGeralMel($resT, $resD, $resI, $resA) . "</div>
        </div>
        <div class='col-actions'>
             <button class='f-btn-send-single' onclick='enviarCorrecaoSingleMel(\"$idProd\")' title='Enviar para Melhoria'><i class='material-icons'>build</i></button>
             <button class='f-btn-sync_i' onclick='syncAnyMarketItemMel(\"$idProd\")' title='Atualizar com AnyMarket'><i class='material-icons'>sync</i></button>
        </div>
    </div>";
}

// =============================================================================
// [4. AJAX HANDLER (GERENCIADOR DE REQUISIÇÕES)]
// =============================================================================
if ($isAjax) {
    // Inicialização da conexão com tratamento de erros
    if (!isset($g['conexaoBanco']) || !$g['conexaoBanco'] || !($g['conexaoBanco'] instanceof \mysqli)) {
        $con = @\mysqli_connect($confUsuario['dbHost'], $confUsuario['dbUser'], $confUsuario['dbPass'], $confUsuario['dbDatabase']);
        $g['conexaoBanco'] = $con;
    }
    $con = $g['conexaoBanco'];

    if (!function_exists('cleanInputMel')) { function cleanInputMel($data) { global $g; return \mysqli_real_escape_string($g['conexaoBanco'], trim($data)); } }
    if (!function_exists('getSmartWhereMel')) {
        function getSmartWhereMel($col, $val, $mode = 'text') {
            global $g; $val = trim($val); if ($val === '') return null;
            if (strpos($val, ';') !== false) {
                $parts = explode(';', $val); $arr = []; $orLike = [];
                foreach($parts as $p) {
                    $p = trim($p); if($p === '') continue;
                    if ($mode === 'number') { $p = str_replace(',', '.', $p); if(is_numeric($p)) $arr[] = $p; } 
                    else { $cleanP = \mysqli_real_escape_string($g['conexaoBanco'], $p); $orLike[] = "$col LIKE '%$cleanP%'"; }
                }
                if ($mode === 'number' && !empty($arr)) return "$col IN (" . implode(',', $arr) . ")";
                if ($mode === 'text' && !empty($orLike)) return "(" . implode(" OR ", $orLike) . ")";
            }
            $op = ''; $cleanVal = $val;
            if (isset($val[0])) { if ($val[0] === '>') { $op = '>'; $cleanVal = substr($val, 1); } elseif ($val[0] === '<') { $op = '<'; $cleanVal = substr($val, 1); } elseif ($val[0] === '!') { $op = '!'; $cleanVal = substr($val, 1); } }
            $cleanVal = trim($cleanVal);
            if ($mode === 'number') {
                $cleanVal = str_replace(',', '.', $cleanVal); if (!is_numeric($cleanVal)) return null; 
                if ($op === '!') return "$col <> $cleanVal"; if ($op === '>' || $op === '<') return "$col $op $cleanVal"; return "$col = $cleanVal"; 
            }
            $safeVal = \mysqli_real_escape_string($g['conexaoBanco'], $cleanVal);
            if ($op === '!') return "$col NOT LIKE '%$safeVal%'"; if ($op === '>' || $op === '<') return "$col $op '$safeVal'"; return "$col LIKE '%$safeVal%'";
        }
    }

    // --- SYNC INDIVIDUAL E EM MASSA (JÁ ESTAVA CORRETO) ---
    if (isset($_POST['action']) && ($_POST['action'] === 'sync_anymarket_item_mel' || $_POST['action'] === 'sync_anymarket_massa_mel')) {
        $ids = isset($_POST['ids']) ? $_POST['ids'] : [$_POST['id']];
        if (!is_array($ids)) $ids = explode(',', $ids);
    
        if (!class_exists('API001') && !class_exists('hardness\\API001')) { @require_once('bibliotecas/classes/API001.php'); }
        if (!class_exists('GMP010') && !class_exists('hardness\\GMP010')) { @require_once('bibliotecas/classes/GMP010.php'); }
    
        $successCount = 0;
        try {
            $apiClass = class_exists('hardness\\API001') ? 'hardness\\API001' : 'API001'; $API001 = new $apiClass(); $token = $API001->executaProcesso(527);
            $baseUrl  = 'https://api.anymarket.com.br/v2';
            $gmpClass = class_exists('hardness\\GMP010') ? 'hardness\\GMP010' : 'GMP010'; 
            $pathLogs = isset($g['pathDados']) ? $g['pathDados'] : null;
            $apiManager = new $gmpClass($baseUrl, $token, 3, [], 'error_log', $pathLogs);
    
            foreach ($ids as $idMel) {
                $idMel = (int)$idMel;
                $rsData = \mysqli_query($con, "SELECT D001E_D001_Codigo_Produto FROM D001E WHERE D001E_Id = $idMel LIMIT 1");
                if (!$rsData || \mysqli_num_rows($rsData) == 0) continue;
                $rowSync = \mysqli_fetch_assoc($rsData);
                $skuSync = trim($rowSync['D001E_D001_Codigo_Produto']);
    
                $endpoint = "/products?sku=" . urlencode($skuSync);
                $resp = $apiManager->request($endpoint, 'GET', null, true, ['return_on_failure' => true]);
    
                if ($resp && isset($resp['code']) && $resp['code'] == 200) {
                    $bodyRaw = isset($resp['body']) ? $resp['body'] : null;
                    $body = is_array($bodyRaw) ? $bodyRaw : (json_decode($bodyRaw, true) ?: []);
                    if (!empty($body['content'][0])) {
                        $d = $body['content'][0];
                        $idAnySku = isset($d['id']) ? (int)$d['id'] : 0; 
                        
                        $titulo = isset($d['title']) ? $d['title'] : '';
                        $descricao = isset($d['description']) ? $d['description'] : '';
                        $marca = '';
                        if (!empty($d['brand']['name'])) $marca = $d['brand']['name'];
                        elseif (!empty($d['brand']['reducedName'])) $marca = $d['brand']['reducedName'];
                        elseif (!empty($d['brand']['partnerId'])) $marca = $d['brand']['partnerId'];

                        $garantia = isset($d['warrantyText']) ? $d['warrantyText'] : '';
                        $peso = isset($d['weight']) ? $d['weight'] : '';
                        $altura = isset($d['height']) ? $d['height'] : '';
                        $largura = isset($d['width']) ? $d['width'] : '';
                        $comprimento = isset($d['length']) ? $d['length'] : '';

                        $imagens = [];
                        if (!empty($d['images']) && is_array($d['images'])) {
                            foreach ($d['images'] as $img) if (!empty($img['url'])) $imagens[] = $img['url'];
                        }

                        $tituloSku = ''; $ean = '';
                        if (isset($d['skus']) && is_array($d['skus'])) {
                            if (!empty($d['skus'][0]['ean'])) $ean = $d['skus'][0]['ean'];
                            foreach ($d['skus'] as $s) {
                                if ((isset($s['partnerId']) && strval($s['partnerId']) === strval($skuSync)) || count($d['skus']) == 1) {
                                    $tituloSku = isset($s['title']) ? $s['title'] : '';
                                    if (!empty($s['ean'])) $ean = $s['ean'];
                                }
                            }
                        }

                        $sets = [];
                        if ($idAnySku > 0) $sets[] = "D001E_Id_Any = $idAnySku";
                        if (!empty($tituloSku)) { $ts = \mysqli_real_escape_string($con, $tituloSku); $sets[] = "D001E_Sku_Titulo = '$ts'"; }

                        $camposTexto = [
                            'D001E_Titulo' => $titulo, 'D001E_Descricao' => $descricao, 'D001E_Marca' => $marca, 'D001E_EAN' => $ean,
                            'D001E_garantia' => $garantia, 'D001E_peso' => $peso, 'D001E_altura' => $altura, 'D001E_largura' => $largura, 'D001E_comprimento' => $comprimento
                        ];
                        foreach ($camposTexto as $col => $val) {
                             $safeVal = \mysqli_real_escape_string($con, $val);
                             $sets[]  = "$col = '$safeVal'";
                        }
                        $imgsFinal = array_slice($imagens, 0, 10);
                        for ($i = 1; $i <= 10; $i++) {
                            $urlImg = isset($imgsFinal[$i - 1]) ? \mysqli_real_escape_string($con, $imgsFinal[$i - 1]) : '';
                            $sets[] = "D001E_Imagem_$i = '$urlImg'";
                        }
                        $sets[] = "D001E_ult_att = NOW()";

                        if (!empty($sets)) {
                            \mysqli_query($con, "UPDATE D001E SET " . implode(', ', $sets) . " WHERE D001E_Id = $idMel");
                            $successCount++;
                        }
                    }
                }
            }
            echo json_encode(['ok' => 1, 'msg' => "$successCount itens sincronizados com sucesso."]);
        } catch (\Exception $e) { echo json_encode(['ok' => 0, 'msg' => 'Erro: ' . $e->getMessage()]); }
        exit;
    }

    // --- SYNC AUTOMÁTICO COMPLETO (AGORA COM LÓGICA DO POPULADOR MASTER) ---
    if (isset($_POST['action']) && $_POST['action'] === 'sync_auto_full_mel') {
        header('Content-Type: application/json; charset=UTF-8');
        set_time_limit(180); 
        ob_start(); 
        
        if (!$con) { ob_end_clean(); echo json_encode(['ok' => 0, 'msg' => 'Sem conexao com banco (mysqli).']); exit; }

        // PASSO 1: UPDATE DE FLAGS E INSERÇÃO DO ESQUELETO (Ids, Codigo, Flag)
        $sqlUpdateForce = "UPDATE D001E e INNER JOIN D001 d ON e.D001E_D001_Id = d.D001_Id SET e.D001E_Flag_Ecommerce = d.D001_Flag_Ecommerce WHERE e.D001E_Flag_Ecommerce IS NULL OR e.D001E_Flag_Ecommerce = '' OR e.D001E_Flag_Ecommerce <> d.D001_Flag_Ecommerce";
        \mysqli_query($con, $sqlUpdateForce);
        $totalCorrigidos = \mysqli_affected_rows($con);

        $sqlInsertNovos = "INSERT INTO D001E (D001E_D001_Id, D001E_D001_Codigo_Produto, D001E_Flag_Ecommerce, D001E_ult_att) SELECT d.D001_Id, d.D001_Codigo_Produto, d.D001_Flag_Ecommerce, NOW() FROM D001 d WHERE (d.D001_Flag_Ecommerce = 'S' OR d.D001_Flag_Ecommerce = 's') AND NOT EXISTS (SELECT 1 FROM D001E e WHERE e.D001E_D001_Id = d.D001_Id)";
        \mysqli_query($con, $sqlInsertNovos);
        $totalInseridos = \mysqli_affected_rows($con);

        $details = [];
        if (!class_exists('hardness\API001') && !class_exists('API001')) { $pathAPI = 'bibliotecas/classes/API001.php'; if (file_exists($pathAPI)) require_once($pathAPI); }
        if (!class_exists('hardness\GMP010') && !class_exists('GMP010')) { $pathGMP = 'bibliotecas/classes/GMP010.php'; if (file_exists($pathGMP)) require_once($pathGMP); }

        try {
            $apiClass = class_exists('hardness\API001') ? 'hardness\API001' : 'API001'; $API001 = new $apiClass(); $token = $API001->executaProcesso(527);
            $baseUrl  = 'https://api.anymarket.com.br/v2';
            $gmpClass = class_exists('hardness\GMP010') ? 'hardness\GMP010' : 'GMP010'; $apiManager = new $gmpClass($baseUrl, $token, 3, [], 'error_log', null);

            $contador = 0;
            // PASSO 2: LOOP PARA POPULAR (Lógica do Master)
            while ($contador < 40) {
                // Pega quem está com ID Any vazio (ou seja, novo ou não processado)
                $sqlBusca = "SELECT * FROM D001E WHERE (D001E_Id_Any IS NULL OR D001E_Id_Any = '') ORDER BY D001E_ult_att ASC LIMIT 1";
                $rs = \mysqli_query($con, $sqlBusca);
                if (!$rs || \mysqli_num_rows($rs) == 0) break;

                $atual = \mysqli_fetch_assoc($rs); $idTable = (int)$atual['D001E_Id']; $sku = trim($atual['D001E_D001_Codigo_Produto']);
                
                if (empty($sku)) { \mysqli_query($con, "UPDATE D001E SET D001E_ult_att = NOW() WHERE D001E_Id = $idTable"); continue; }

                $logItem = ['sku' => $sku, 'idAny' => '-', 'status' => 'Erro', 'msg' => ''];
                
                try {
                    $endpoint = "/products?sku=" . urlencode($sku);
                    $resp = $apiManager->request($endpoint, 'GET', null, true, ['return_on_failure' => true]); $contador++;

                    if (isset($resp['code']) && $resp['code'] == 200) {
                        $bodyRaw = isset($resp['body']) ? $resp['body'] : null;
                        $body = is_array($bodyRaw) ? $bodyRaw : (json_decode($bodyRaw, true) ?: []);
                        
                        if (!empty($body['content'][0])) {
                            $d = $body['content'][0];
                            
                            // 2.1 Dados do Pai
                            $idAnySku  = isset($d['id']) ? (int)$d['id'] : 0;
                            $titulo    = isset($d['title']) ? $d['title'] : '';
                            $descricao = isset($d['description']) ? $d['description'] : '';
                            
                            $marca = '';
                            if (!empty($d['brand']['name'])) $marca = $d['brand']['name'];
                            elseif (!empty($d['brand']['reducedName'])) $marca = $d['brand']['reducedName'];
                            elseif (!empty($d['brand']['partnerId'])) $marca = $d['brand']['partnerId'];

                            $garantia    = isset($d['warrantyText']) ? $d['warrantyText'] : '';
                            $peso        = isset($d['weight']) ? $d['weight'] : '';
                            $altura      = isset($d['height']) ? $d['height'] : '';
                            $largura     = isset($d['width']) ? $d['width'] : '';
                            $comprimento = isset($d['length']) ? $d['length'] : '';

                            // 2.2 Imagens
                            $imagens = [];
                            if (!empty($d['images']) && is_array($d['images'])) {
                                foreach ($d['images'] as $img) if (!empty($img['url'])) $imagens[] = $img['url'];
                            }

                            // 2.3 Dados da Variação (SKU Específico)
                            $tituloSku = ''; $ean = '';
                            if (isset($d['skus']) && is_array($d['skus'])) {
                                if (!empty($d['skus'][0]['ean'])) $ean = $d['skus'][0]['ean'];
                                foreach ($d['skus'] as $s) {
                                    // A LÓGICA IMPORTANTE: Procura pelo SKU exato
                                    if ((isset($s['partnerId']) && strval($s['partnerId']) === strval($sku)) || count($d['skus']) == 1) {
                                        $tituloSku = isset($s['title']) ? $s['title'] : '';
                                        if (!empty($s['ean'])) $ean = $s['ean'];
                                    }
                                }
                            }
                            
                            $logItem['status'] = 'Sucesso'; $logItem['idAny'] = $idAnySku; $logItem['msg'] = 'Encontrado';

                            // 2.4 Prepara Update
                            $sets = [];
                            if ($idAnySku > 0) $sets[] = "D001E_Id_Any = $idAnySku";
                            if (!empty($tituloSku)) {
                                $ts = \mysqli_real_escape_string($con, $tituloSku);
                                $sets[] = "D001E_Sku_Titulo = '$ts'";
                            }

                            $camposTexto = [
                                'D001E_Titulo'      => $titulo,
                                'D001E_Descricao'   => $descricao,
                                'D001E_Marca'       => $marca,
                                'D001E_EAN'         => $ean,
                                'D001E_garantia'    => $garantia,
                                'D001E_peso'        => $peso,
                                'D001E_altura'      => $altura,
                                'D001E_largura'     => $largura,
                                'D001E_comprimento' => $comprimento
                            ];
                            foreach ($camposTexto as $col => $val) {
                                $safeVal = \mysqli_real_escape_string($con, $val);
                                $sets[]  = "$col = '$safeVal'";
                            }

                            $imgsFinal = array_slice($imagens, 0, 10);
                            for ($i = 1; $i <= 10; $i++) {
                                $urlImg = isset($imgsFinal[$i - 1]) ? \mysqli_real_escape_string($con, $imgsFinal[$i - 1]) : '';
                                $sets[] = "D001E_Imagem_$i = '$urlImg'";
                            }

                            $sets[] = "D001E_ult_att = NOW()";

                            if (!empty($sets)) {
                                \mysqli_query($con, "UPDATE D001E SET " . implode(', ', $sets) . " WHERE D001E_Id = $idTable");
                            }
                        }
                    }
                } catch (\Exception $e) { $logItem['msg'] = $e->getMessage(); }
                
                // Se não achou na API ou deu erro, atualiza a data mesmo assim para não travar o loop
                if ($logItem['status'] !== 'Sucesso') {
                    \mysqli_query($con, "UPDATE D001E SET D001E_ult_att = NOW() WHERE D001E_Id = $idTable");
                }
                
                $details[] = $logItem;
                usleep(500000);
            }
        } catch (\Exception $e) { $details[] = ['sku' => '-', 'idAny' => '-', 'status' => 'Fatal', 'msg' => $e->getMessage()]; }
        
        ob_end_clean();
        echo json_encode(['ok' => 1, 'summary' => ['flags' => $totalCorrigidos, 'novos' => $totalInseridos], 'details' => $details]); exit;
    }

    // --- ENVIO PARA CORREÇÃO (CORRIGIDO MYSQLI) ---
    if (isset($_POST['action']) && $_POST['action'] === 'send_correction_mel') {
        $ids = isset($_POST['ids']) ? $_POST['ids'] : []; if (!is_array($ids)) $ids = explode(',', $ids);
        $tipo = isset($_POST['tipo']) ? $_POST['tipo'] : 'corr';
        $obs = isset($_POST['obs']) ? \mysqli_real_escape_string($con, $_POST['obs']) : '';
        $tags = isset($_POST['tags']) ? \mysqli_real_escape_string($con, $_POST['tags']) : '';
        $count = 0;
        foreach ($ids as $idE) {
            $idE = (int)$idE; if ($idE <= 0) continue;
            $rsSrc = \mysqli_query($con, "SELECT * FROM D001E WHERE D001E_Id = $idE LIMIT 1");
            if ($rsSrc && \mysqli_num_rows($rsSrc) > 0) {
                $src = \mysqli_fetch_assoc($rsSrc); 
                $sku = \mysqli_real_escape_string($con, $src['D001E_D001_Codigo_Produto']);
                $check = \mysqli_query($con, "SELECT D001F_Id FROM D001F WHERE D001F_D001_Codigo_Produto = '$sku'");
                if (\mysqli_num_rows($check) == 0) {
                    $cols = "D001F_D001_Id, D001F_D001_Codigo_Produto, D001F_Id_Any, D001F_Titulo, D001F_Marca, D001F_Descricao, D001F_Imagem_1, D001F_Imagem_2, D001F_Imagem_3, D001F_Imagem_4, D001F_Imagem_5, D001F_Imagem_6, D001F_Imagem_7, D001F_Imagem_8, D001F_Imagem_9, D001F_Imagem_10, D001F_EAN, D001F_garantia, D001F_peso, D001F_altura, D001F_largura, D001F_comprimento, D001F_ult_att, D001F_Tipo, D001F_Obs, D001F_tags";
                    $vals = "'" . \mysqli_real_escape_string($con, $src['D001E_D001_Id']) . "','" . $sku . "','" . \mysqli_real_escape_string($con, $src['D001E_Id_Any']) . "','" . \mysqli_real_escape_string($con, $src['D001E_Sku_Titulo']) . "','" . \mysqli_real_escape_string($con, $src['D001E_Marca']) . "','" . \mysqli_real_escape_string($con, $src['D001E_Descricao']) . "','" . \mysqli_real_escape_string($con, $src['D001E_Imagem_1']) . "','" . \mysqli_real_escape_string($con, $src['D001E_Imagem_2']) . "','" . \mysqli_real_escape_string($con, $src['D001E_Imagem_3']) . "','" . \mysqli_real_escape_string($con, $src['D001E_Imagem_4']) . "','" . \mysqli_real_escape_string($con, $src['D001E_Imagem_5']) . "','" . \mysqli_real_escape_string($con, $src['D001E_Imagem_6']) . "','" . \mysqli_real_escape_string($con, $src['D001E_Imagem_7']) . "','" . \mysqli_real_escape_string($con, $src['D001E_Imagem_8']) . "','" . \mysqli_real_escape_string($con, $src['D001E_Imagem_9']) . "','" . \mysqli_real_escape_string($con, $src['D001E_Imagem_10']) . "','" . \mysqli_real_escape_string($con, $src['D001E_EAN']) . "','" . \mysqli_real_escape_string($con, $src['D001E_garantia']) . "','" . \mysqli_real_escape_string($con, $src['D001E_peso']) . "','" . \mysqli_real_escape_string($con, $src['D001E_altura']) . "','" . \mysqli_real_escape_string($con, $src['D001E_largura']) . "','" . \mysqli_real_escape_string($con, $src['D001E_comprimento']) . "', NOW(), '$tipo', '$obs', '$tags'";
                    if(\mysqli_query($con, "INSERT INTO D001F ($cols) VALUES ($vals)")) $count++;
                }
            }
        }
        echo json_encode(['ok' => 1, 'msg' => "$count produtos enviados ($tipo)."]); exit;
    }

    // --- EXPORT CSV (CORRIGIDO MYSQLI) ---
    if (isset($_POST['action']) && $_POST['action'] === 'export_csv_mel') {
        $where = ["T1.D001E_Flag_Ecommerce = 'S'"];
        if (!empty($_POST['f_tit'])) { $w = getSmartWhereMel("T1.D001E_Sku_Titulo", $_POST['f_tit'], 'text'); if($w) $where[] = $w; }
        if (!empty($_POST['f_sco'])) { $w = getSmartWhereMel("T1.D001E_Status_Pontuacao", $_POST['f_sco'], 'number'); if($w) $where[] = $w; }
        
        $whereStr = implode(" AND ", $where);
        $sqlCsv = "SELECT T1.* FROM D001E AS T1 LEFT JOIN D049 ON D049.D049_D001_Id = T1.D001E_D001_Id LEFT JOIN D009 AS T2 ON (T2.D009_D049_Id = D049.D049_Id AND T2.D009_C004_Id = $C004_Id) WHERE $whereStr GROUP BY T1.D001E_Id ORDER BY T1.D001E_Id ASC";
        $rsCsv = \mysqli_query($con, $sqlCsv);
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=produtos_quality_'.date('YmdHis').'.csv');
        $out = fopen('php://output', 'w'); fputs($out, "\xEF\xBB\xBF");
        fputcsv($out, ['ID', 'SKU', 'Titulo', 'NotaFinal'], ';');
        if ($rsCsv) { while ($row = \mysqli_fetch_assoc($rsCsv)) { fputcsv($out, [$row['D001E_Id'], $row['D001E_D001_Codigo_Produto'], $row['D001E_Sku_Titulo'], $row['D001E_Status_Pontuacao']], ';'); } }
        fclose($out); exit;
    }

    // --- DETALHES VISUALIZADOR (CORRIGIDO MYSQLI) ---
    if (isset($_POST['action']) && $_POST['action'] === 'get_details_mel') {
        $skuBusca = isset($_POST['sku']) ? \mysqli_real_escape_string($con, $_POST['sku']) : '';
        $sqlDet = "SELECT T1.* FROM D001E AS T1 WHERE T1.D001E_D001_Codigo_Produto = '$skuBusca' LIMIT 1";
        $rsDet  = \mysqli_query($con, $sqlDet);
        if ($rsDet && \mysqli_num_rows($rsDet) > 0) {
            $row = \mysqli_fetch_assoc($rsDet);
            $resT = analiseTituloMel($row['D001E_Sku_Titulo']); $resD = analiseDescricaoMel($row['D001E_Descricao']); $resI = analiseImagensMel($row); $resA = analiseAtributosMel($row);
            $imgs = []; for ($i = 1; $i <= 10; $i++) if (!empty($row["D001E_Imagem_$i"])) $imgs[] = $row["D001E_Imagem_$i"];
            $specs = ['EAN' => $row['D001E_EAN'], 'Garantia' => $row['D001E_garantia'], 'Peso' => $row['D001E_peso'], 'Altura' => $row['D001E_altura'], 'Largura' => $row['D001E_largura'], 'Comprimento' => $row['D001E_comprimento']];
            echo json_encode(['ok' => 1, 'titulo' => $row['D001E_Sku_Titulo'], 'sku' => $row['D001E_D001_Codigo_Produto'], 'marca' => $row['D001E_Marca'], 'desc' => $row['D001E_Descricao'], 'imgs' => $imgs, 'specs' => $specs, 'scores' => ['tit' => $resT['nota'], 'desc' => $resD['nota'], 'img' => $resI['nota'], 'attr' => $resA['nota']]]);
        } else { echo json_encode(['ok' => 0, 'msg' => 'Produto não encontrado']); }
        exit;
    }

    // --- GRID PRINCIPAL (CORRIGIDO MYSQLI) ---
    header('Content-Type: application/json; charset=UTF-8');
    $where = ["T1.D001E_Flag_Ecommerce = 'S'"];
    if (!empty($_POST['f_tit'])) { $w = getSmartWhereMel("T1.D001E_Sku_Titulo", $_POST['f_tit'], 'text'); if($w) $where[] = $w; }
    if (!empty($_POST['f_id_any'])) { $w = getSmartWhereMel("T1.D001E_Id_Any", $_POST['f_id_any'], 'number'); if($w) $where[] = $w; }
    if (!empty($_POST['f_sku'])) { $w = getSmartWhereMel("T1.D001E_D001_Codigo_Produto", $_POST['f_sku'], 'text'); if($w) $where[] = $w; }
    if (!empty($_POST['f_mar'])) { $w = getSmartWhereMel("T1.D001E_Marca", $_POST['f_mar'], 'text'); if($w) $where[] = $w; }
    if (!empty($_POST['f_desc'])) { $w = getSmartWhereMel("T1.D001E_Descricao", $_POST['f_desc'], 'text'); if($w) $where[] = $w; }
    if (!empty($_POST['f_spec'])) { $fsp = cleanInputMel($_POST['f_spec']); $where[] = "(T1.D001E_EAN LIKE '%$fsp%' OR T1.D001E_garantia LIKE '%$fsp%' OR T1.D001E_peso LIKE '%$fsp%')"; }
    if (isset($_POST['f_est_liq']) && $_POST['f_est_liq'] !== '') { $w = getSmartWhereMel("T2.D009_Quantidade_Estoque_Liquido", $_POST['f_est_liq'], 'number'); if($w) $where[] = $w; }
    if (isset($_POST['f_est_tab']) && $_POST['f_est_tab'] !== '') { $w = getSmartWhereMel("T2.D009_Quantidade_Estoque_Tabela", $_POST['f_est_tab'], 'number'); if($w) $where[] = $w; }
    if (!empty($_POST['f_freq'])) { $w = getSmartWhereMel("T2.D009_Frequencia_Venda", $_POST['f_freq'], 'number'); if($w) $where[] = $w; }
    if (!empty($_POST['f_custo'])) { $w = getSmartWhereMel("T2.D009_Valor_Custo_Unitario", $_POST['f_custo'], 'number'); if($w) $where[] = $w; }
    if (!empty($_POST['f_sco'])) { $w = getSmartWhereMel("T1.D001E_Status_Pontuacao", $_POST['f_sco'], 'number'); if($w) $where[] = $w; }
    if (!empty($_POST['f_sc_tit'])) { $w = getSmartWhereMel("T1.D001E_pont_titulo", $_POST['f_sc_tit'], 'number'); if($w) $where[] = $w; }
    if (!empty($_POST['f_sc_desc'])) { $w = getSmartWhereMel("T1.D001E_pont_desc", $_POST['f_sc_desc'], 'number'); if($w) $where[] = $w; }
    if (!empty($_POST['f_sc_img'])) { $w = getSmartWhereMel("T1.D001E_pont_img", $_POST['f_sc_img'], 'number'); if($w) $where[] = $w; }
    if (!empty($_POST['f_sc_spec'])) { $w = getSmartWhereMel("T1.D001E_pont_espec", $_POST['f_sc_spec'], 'number'); if($w) $where[] = $w; }

    $whereStr = implode(" AND ", $where);
    $totalRows = 0;
    $sqlCount = "SELECT COUNT(*) AS total FROM D001E AS T1 LEFT JOIN D049 ON D049.D049_D001_Id = T1.D001E_D001_Id LEFT JOIN D009 AS T2 ON (T2.D009_D049_Id = D049.D049_Id AND T2.D009_C004_Id = $C004_Id) WHERE $whereStr";
    $rsCount = \mysqli_query($con, $sqlCount);
    if ($rsCount) { $r = \mysqli_fetch_assoc($rsCount); $totalRows = (int) ($r['total'] ?? 0); }

    $sql = "SELECT T1.*, T2.D009_Frequencia_Venda, T2.D009_Valor_Custo_Unitario, T2.D009_Quantidade_Estoque_Tabela, T2.D009_Quantidade_Estoque_Liquido FROM D001E AS T1 LEFT JOIN D049 ON D049.D049_D001_Id = T1.D001E_D001_Id LEFT JOIN D009 AS T2 ON (T2.D009_D049_Id = D049.D049_Id AND T2.D009_C004_Id = $C004_Id) WHERE $whereStr GROUP BY T1.D001E_Id ORDER BY T1.D001E_Id ASC LIMIT $limit OFFSET $offset";
    $rs = \mysqli_query($con, $sql);
    $html = "";
    if ($rs) { while ($row = \mysqli_fetch_assoc($rs)) { $html .= renderQualityRowMel($row); } }
    echo json_encode(['ok' => 1, 'total' => $totalRows, 'page' => $page, 'pageSize' => $limit, 'html' => $html]); exit;
}

// =============================================================================
// [5. ESTILOS CSS]
// =============================================================================
$style = <<<STYLE
<style>
    :root { 
        --bg-body: #f3f4f6; --card-bg: #ffffff; --text-color: #1f2937; --border-color: #e5e7eb; 
        --score-6: #0098D3; --score-5: #10b981; --score-4: #84cc16; --score-3: #eab308; --score-2: #fca5a5; --score-1: #ef4444; 
        --primary: #0098D3; 
    }
    body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif; background-color: var(--bg-body); margin: 0; padding: 20px; color: var(--text-color); }
 /*    .quality-list { max-width: 1600px; margin: 0 auto; margin-top: 20px; } */
    .filter-container { background: #fff; border-radius: 8px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); padding: 16px; margin: 0px auto 0px auto; border: 1px solid #e5e7eb; }
    .filter-header { display: flex; justify-content: space-between; align-items: center; cursor: pointer; user-select: none; }
    .filter-title { font-size: 14px; font-weight: 700; color: #374151; display:flex; align-items:center; gap:8px; text-transform:uppercase; letter-spacing:0.05em; }
    .filter-icon { color: var(--primary); font-size: 20px; }
    .Con-icon {font-size: 20px; }
    .filter-chevron { transition: transform 0.2s; color: #9ca3af; }
    .filter-body { display: block; margin-top: 15px; border-top: 1px solid #f3f4f6; padding-top: 15px; }
    .filter-body.closed { display: none; }
    .filter-header.closed .filter-chevron { transform: rotate(-90deg); }
    .f-grid { display: grid; grid-template-columns: repeat(5, 1fr); gap: 10px; }
    .f-group { display: flex; flex-direction: column; gap: 4px; margin-left: 5px; }
    .f-label { font-size: 11px; font-weight: 600; color: #6b7280; text-transform: uppercase; }
    .f-input { padding: 8px 10px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 12px; outline: none; transition: all 0.2s; width: 100%; }
    .f-input:focus { border-color: var(--primary); box-shadow: 0 0 0 2px rgba(0, 152, 211, 0.15); }
    .f-actions { grid-column: 1 / -1; display: flex; justify-content: flex-end; margin-top: 10px; padding-top: 10px; border-top: 1px solid #f3f4f6; gap: 10px; }
    .f-btn-apply, .f-btn-clear, .f-btn-export, .f-btn-send, .f-btn-sync, .f-btn-sync_mel{border: none !important; padding: 10px 24px !important; border-radius: 8px !important; font-weight: 700 !important; cursor: pointer !important; font-size: 13px !important; display:flex !important; align-items:center !important; gap:6px !important; transition: background 0.2s !important; }

    .f-btn-apply,
    .f-btn-clear,
    .f-btn-export,
    .f-btn-send,
    .f-btn-export_can,
    .f-btn-send_env,
    .f-btn-sync,
    .f-btn-sync_mel {
        color: #474747 !important; 
        background: #f3f3f3 !important;    
        border-color: #4747470d !important; 
        width: 40px !important;
        height: 40px !important;
        border-radius: 8px !important;
        cursor: pointer !important;
        border-style: solid !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.1) !important;
        transition: all 0.2s ease !important; /* Transição suave para todos */
    }


    .f-btn-apply:hover {
        background: #eaf9ff !important;
        border-color: var(--primary) !important;
        color: var(--primary) !important;
    }

    .f-btn-clear:hover, 
    .f-btn-export_can:hover {
        background: #ffd5d5 !important;
        border-color: #ef4444 !important;
        color: #ef4444 !important;
    }

    .f-btn-export:hover, 
    .f-btn-send_env:hover {
        background: #e6fff7 !important;
        border-color: #10b981 !important;
        color: #10b981 !important;
    }


    .f-btn-send:hover {
        background: #e1dfff !important;
        border-color: #6366f1 !important;
        color: #6366f1 !important;
    }


    .f-btn-sync:hover {
        background: #ffeedb!important;
        border-color: #f59e0b !important;
        color: #f59e0b !important;
    }


    .f-btn-sync_mel:hover {
        background: #ededed !important;
        border-color: #52606f !important;
        color: #52606f !important;
    }
    .quality-header-mel, .quality-row-mel { display: grid; grid-template-columns: 30px 70px minmax(200px, 1.4fr) 1fr 1.2fr 0.8fr 45px 45px 45px 45px 70px 100px; gap: 12px; align-items: center; margin-bottom: 5px;}
    .quality-header-mel { position: sticky; top: 0; z-index: 50; background: #f9fafb; border-bottom: 2px solid #e5e7eb; padding: 12px 16px; font-size: 11px; font-weight: 700; color: #6b7280; text-transform: uppercase; letter-spacing: 0.03em; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
    .quality-header-mel > div { display: flex; align-items: center; justify-content: center; text-align: center; }
    .quality-header-mel > div:nth-child(3), .quality-header-mel > div:nth-child(5), .quality-header-mel > div:nth-child(6) { justify-content: flex-start; text-align: left; }
    .quality-row-mel { background: var(--card-bg); border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); padding: 14px 16px; margin-bottom: 10px; border: 1px solid #f1f1f1; transition: all 0.2s; }
    .quality-row-mel:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.1); border-color: #d1d5db; }
    .thumb-box { width: 64px; height: 64px; border-radius: 8px; border: 1px solid #e5e7eb; padding: 3px; background: #fff; cursor: pointer; display: flex; align-items: center; justify-content: center; }
    .thumb-box img { width: 100%; height: 100%; object-fit: contain; }
    .col-info { display: flex; flex-direction: column; gap: 4px; overflow: visible !important; justify-content: center; }
    .prod-title { font-size: 13px; font-weight: 600; color: #111827; line-height: 1.3; }
    .prod-sub { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; margin-top: 4px; }
    .badge-any, .badge-sku, .badge-brand { font-size: 10px; padding: 2px 6px; border-radius: 6px; font-family: monospace; font-weight: 700; display: inline-block; }
    .badge-any { color: #fff; background: #FF600F; border: 1px solid #e65100; }
    .badge-sku { color: #fff; background: #089BD4; border: 1px solid #0284c7; }
    .badge-brand { color: #374151; background: #f3f4f6; border: 1px solid #d1d5db; }
    .col-metrics { display: block; background: #f9fafb; padding: 8px 12px; border-radius: 8px; border: 1px solid #f3f4f6; min-height: 120px; align-content: center; }
    .metric-cell { display: flex; justify-content: space-between; align-items: center; font-size: 11px; padding: 4px 0; border-bottom: 1px dashed #e5e7eb; }
    .metric-cell:last-child { border-bottom: none; }
    .metric-cell .lbl { color: #9ca3af; font-weight: 600; text-transform: uppercase; }
    .metric-cell .val { color: #374151; font-weight: 600; }
    .col-box-scroll { font-size: 11px; color: #4b5563; background: #fff; padding: 8px; line-height: 1.4; border-radius: 8px; border: 1px solid #f3f4f6; text-align: left; }
    .desc-scroll-mel { max-height: 120px; overflow-y: auto; min-height: 120px; }
    .spec-auto-mel { height: auto; overflow: visible; display: flex; flex-direction: column; min-height: 120px; }
    .spec-auto-mel span { display: block; border-bottom: 1px solid #f9fafb; padding: 2px 0; }
    .col-box-scroll::-webkit-scrollbar { width: 4px; }
    .col-box-scroll::-webkit-scrollbar-thumb { background: #d1d5db; border-radius: 4px; }
    .mini-score-box { display: flex; flex-direction: column; align-items: center; cursor: help; }
    .mini-score-val { width: 32px; height: 32px; border-radius: 8px; background: #e5e7eb; color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 12px; box-shadow: 0 1px 2px rgba(0,0,0,0.1); }
    .col-score { display: flex; flex-direction: column; align-items: center; justify-content: center; }
    .score-circle { position: relative; width: 48px; height: 48px; border-radius: 50%; background: conic-gradient(var(--color) calc(var(--percent) * 1%), #e5e7eb 0); display: flex; align-items: center; justify-content: center; margin-bottom: 2px; }
    .score-circle::before { content: ""; position: absolute; width: 38px; height: 38px; border-radius: 50%; background: #ffffff; }
    .score-number { position: relative; font-size: 16px; font-weight: 800; z-index: 1; color: #111827; }
    .score-label { font-size: 9px; font-weight: 700; color: #6b7280; text-transform: uppercase; margin-top: 2px; }
    .col-actions { display: flex; justify-content: center; gap: 8px; align-items: center; height: 100%; }
    .f-btn-send-single{ width: 40px; height: 40px; border-radius: 8px; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all 0.2s ease; color: #4e6ff0; box-shadow: 0 1px 2px rgba(0, 0, 0, 0.1); border-style: solid; border-color: #4e6ff0; }
    .f-btn-sync_i { width: 40px; height: 40px; border-radius: 8px; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all 0.2s ease; color: #f0ad4e; box-shadow: 0 1px 2px rgba(0, 0, 0, 0.1); border-style: solid; border-color: #f0ad4e; }
    .f-btn-send-single { background: transparent; padding: 0 !important; margin: 0 !important;} 
    .f-btn-send-single:hover { background: #e3e8ff; transform: scale(1.05); }
    .f-btn-sync_i { background: transparent; padding: 0 !important; margin: 0 !important; } 
    .f-btn-sync_i:hover { background: #fff4e4; transform: scale(1.05); }
    .f-btn-send-single i, .f-btn-sync_i i { font-size: 18px; }
    #hardness-custom-tooltip { background: #ffffff; border: 1px solid #e4e6eb; box-shadow: 0 8px 20px rgba(0,0,0,0.15); border-radius: 8px; padding: 0; z-index: 999999; font-size: 12px; color: #111827; min-width: 230px; }
    .tt-table { width: 100%; border-collapse: collapse; }
    .tt-head { background: #f3f4f6; padding: 8px 12px; font-weight: 700; font-size: 11px; text-transform: uppercase; text-align: left; color: #4b5563; }
    .tt-row { border-bottom: 1px solid #f3f4f6; padding: 6px 12px; color: #6b7280; font-size: 11px; }
    .tt-val { border-bottom: 1px solid #f3f4f6; padding: 6px 12px; color: #111827; text-align: right; font-weight: 700; font-size: 11px; }
    .tt-foot td { background: #f0f9ff; font-weight: 800; color: var(--primary); padding: 8px 12px; }
    .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); z-index: 9999; justify-content: center; align-items: center; backdrop-filter: blur(3px); padding: 20px; }
    .modal-content { background: #fff; width: 100%; max-width: 1100px; height: 90%; border-radius: 8px; position: relative; display: flex; overflow: hidden; box-shadow: 0 20px 40px rgba(0,0,0,0.2); }
    .close-modal { position: absolute; top: 10px; right: 15px; font-size: 24px; cursor: pointer; z-index: 100; color: #9ca3af; }
    .close-modal:hover { color: #333; }
    .vis-thumbs { width: 100px; background: #f9fafb; padding: 10px; overflow-y: auto; display: flex; flex-direction: column; gap: 8px; border-right: 1px solid #e5e7eb; }
    .vis-mini { width: 100%; height: 70px; object-fit: contain; border: 2px solid transparent; border-radius: 8px; cursor: pointer; background: #fff; border: 1px solid #f1f1f1; }
    .vis-mini.active { border-color: var(--primary); }
    .vis-main { flex: 1; display: flex; justify-content: center; align-items: center; background: #fff; padding: 20px; position: relative; }
    .vis-main img { max-width: 100%; max-height: 100%; object-fit: contain; }
    .vis-score-badge { position: absolute; top: 15px; left: 15px; padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 700; color: #fff; background: var(--primary); z-index: 10; }
    .vis-info { width: 350px; border-left: 1px solid #e5e7eb; padding: 20px; overflow-y: auto; background: #fff; display: flex; flex-direction: column; gap: 15px; }
    .vis-h1 { font-size: 18px; font-weight: 700; margin: 0; color: #111827; line-height: 1.3; }
    .vis-chip { padding: 2px 8px; border-radius: 8px; font-size: 10px; font-weight: 700; color: #fff; background: var(--primary); display: inline-block; vertical-align: middle; margin-left: 6px; }
    .vis-meta { font-size: 12px; color: #6b7280; padding-bottom: 10px; border-bottom: 1px solid #e5e7eb; }
    .vis-header-row { display: flex; justify-content: space-between; align-items: center; margin-bottom: 5px; font-weight: 700; font-size: 12px; color: #111827; }
    .vis-desc-box { font-size: 12px; line-height: 1.5; color: #4b5563; background: #f9fafb; padding: 10px; border-radius: 8px; border: 1px solid #e5e7eb; max-height: 150px; overflow-y: auto; }
    .vis-specs-table td { padding: 4px 0; border-bottom: 1px solid #f3f4f6; color: #4b5563; font-size: 12px; }
    @media (max-width: 1400px) { .quality-header-mel, .quality-row-mel { grid-template-columns: 30px 60px 1.4fr 160px 1.5fr 1fr 45px 45px 45px 45px 70px 100px; gap: 8px; } }
    #demoMel { padding: 20px 0; display:none; flex-wrap:wrap; align-items:center; justify-content:center; gap:5px; }
    #demoMel.active { display: flex; }
    #demoMel .pg-btn { border: 1px solid #d1d5db; background:#fff; padding:6px 12px; border-radius:6px; cursor:pointer; font-size:12px; font-weight:600; color:#374151; text-decoration: none; }
    #demoMel .pg-btn.active { background: var(--primary); border-color: var(--primary); color:#fff; }
    .btn-sel-type { border: 2px solid #e5e7eb; background: #fff; padding: 20px; border-radius: 8px; font-size: 14px; font-weight: 700; color: #374151; cursor: pointer; transition: all 0.2s; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 8px; width: 140px; height: 120px; }
    .btn-sel-type:hover { border-color: var(--primary); background: #f0f9ff; color: var(--primary); transform: translateY(-3px); box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); }
    .btn-sel-type i { font-size: 32px; color: #9ca3af; transition: color 0.2s; }
    .btn-sel-type:hover i { color: var(--primary); }
    .obs-wrapper { width:100%; display:flex; flex-direction:column; gap:15px; position: relative; }
    .obs-input { width:100%; min-height:150px; max-height: 150px; overflow-y: auto; border:1px solid #d1d5db; border-radius:8px; padding:10px; font-size:14px; color:#374151; outline:none; transition:0.2s; font-family:inherit; background:#fff; line-height:1.5; }
    .obs-input:focus { border-color:var(--primary); box-shadow:0 0 0 4px rgba(0,152,211,0.1); }
    .obs-input:empty:before { content: attr(data-placeholder); color: #9ca3af; font-style: italic; }
    .char-counter { position: absolute; bottom: 10px; right: 15px; font-size: 11px; font-weight: 800; background: rgba(255,255,255,0.9); padding: 2px 6px; border-radius: 8px; }
    .char-green { color: #10b981; } .char-red { color: #ef4444; }
    .tag-grid { display:grid; grid-template-columns: 1fr 1fr; gap:10px; }
    .tag-check-item { display:flex; align-items:center; gap:8px; font-size:13px; color:#374151; cursor:pointer; padding:8px 10px; border:1px solid #e5e7eb; border-radius:6px; transition:0.1s; }
    .tag-check-item:hover { background:#f9fafb; border-color:#d1d5db; }
    .tag-check-item input { width:16px; height:16px; cursor:pointer; accent-color:var(--primary); }
    .res-dash-cards { display:flex; gap:15px; margin-bottom:20px; }
    .res-card { flex:1; background:#f9fafb; border:1px solid #e5e7eb; padding:15px; border-radius:8px; text-align:center; }
    .res-card-val { font-size:24px; font-weight:800; color:var(--primary); }
    .res-card-lbl { font-size:11px; color:#6b7280; text-transform:uppercase; font-weight:600; margin-top:5px; }
    .res-table-wrap { flex:1; overflow-y:auto; border:1px solid #e5e7eb; border-radius:8px; }
    .res-table { width:100%; border-collapse:collapse; font-size:12px; }
    .res-table th { background:#f3f4f6; text-align:left; padding:10px; font-weight:700; color:#374151; position:sticky; top:0; }
    .res-table td { padding:8px 10px; border-bottom:1px solid #f3f4f6; color:#4b5563; }
    .st-badge { padding:2px 8px; border-radius:12px; font-size:10px; font-weight:700; color:#fff; }
    .st-ok { background:#10b981; } .st-err { background:#ef4444; }
    .header-tooltip-content { padding: 10px; }
    .header-tooltip-title { font-weight: 700; color: var(--primary); border-bottom: 1px solid #e5e7eb; padding-bottom: 6px; margin-bottom: 6px; font-size: 11px; text-transform: uppercase; }
    .header-rule-row { display: flex; justify-content: space-between; font-size: 11px; margin-bottom: 3px; color: #4b5563; }
    
    /* CONSOLE DE PROGRESSO */
    .console-box { width: 100%; background: #1e1e1e; border-radius: 6px; padding: 15px; font-family: 'Consolas', 'Monaco', monospace; font-size: 12px; color: #d4d4d4; height: 300px; overflow-y: auto; border: 1px solid #333; margin-top: 15px; box-shadow: inset 0 0 10px rgba(0,0,0,0.5); }
    .console-line { margin-bottom: 4px; display: block; border-bottom: 1px solid #333; padding-bottom: 2px; }
    .console-time { color: #569cd6; margin-right: 8px; }
    .console-info { color: #d4d4d4; }
    .console-success { color: #6a9955; font-weight: bold; }
    .console-error { color: #f44747; font-weight: bold; }
    .console-warn { color: #dcdcaa; }
    .loading-spinner-box { display: flex; align-items: center; gap: 10px; margin-bottom: 10px; }
    .spinner-ring { width: 20px; height: 20px; border: 3px solid #f3f4f6; border-top: 3px solid var(--primary); border-radius: 50%; animation: spin 1s linear infinite; }
    @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
</style>
<link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
STYLE;
if (!$apiMode) echo $style;

// Tooltips para Header com Regras Claras
$tipTit  = "<div class='header-tooltip-content'><div class='header-tooltip-title'>REGRAS: TÍTULO (Peso 3)</div><div class='header-rule-row'><span>< 10 chars</span><span style='color:#ef4444'>1</span></div><div class='header-rule-row'><span>10 a 19 chars</span><span style='color:#fca5a5'>2</span></div><div class='header-rule-row'><span>20 a 39 chars</span><span style='color:#eab308'>3</span></div><div class='header-rule-row'><span>40 a 49 chars</span><span style='color:#84cc16'>4</span></div><div class='header-rule-row'><span>50 a 60 chars</span><span style='color:#10b981'>5</span></div><div class='header-rule-row'><span>> 60 chars</span><span style='color:#ef4444'>0</span></div></div>";
$tipDesc = "<div class='header-tooltip-content'><div class='header-tooltip-title'>REGRAS: DESCRIÇÃO (Peso 3)</div><div class='header-rule-row'><span>< 200 chars</span><span style='color:#ef4444'>1</span></div><div class='header-rule-row'><span>200 a 399</span><span style='color:#fca5a5'>2</span></div><div class='header-rule-row'><span>400 a 599</span><span style='color:#eab308'>3</span></div><div class='header-rule-row'><span>600 a 1999</span><span style='color:#84cc16'>4</span></div><div class='header-rule-row'><span>2000 a 4000</span><span style='color:#10b981'>5</span></div><div class='header-rule-row'><span>> 4000 chars</span><span style='color:#ef4444'>0</span></div></div>";
$tipImg  = "<div class='header-tooltip-content'><div class='header-tooltip-title'>REGRAS: IMAGENS (Peso 3)</div><div class='header-rule-row'><span>0 a 1 img</span><span style='color:#ef4444'>1</span></div><div class='header-rule-row'><span>2 imgs</span><span style='color:#eab308'>3</span></div><div class='header-rule-row'><span>3 imgs</span><span style='color:#84cc16'>4</span></div><div class='header-rule-row'><span>4 imgs</span><span style='color:#84cc16'>4</span></div><div class='header-rule-row'><span>5 a 10 imgs</span><span style='color:#10b981'>5</span></div><div class='header-rule-row'><span>> 10 imgs</span><span style='color:#ef4444'>0</span></div></div>";
$tipSpec = "<div class='header-tooltip-content'><div class='header-tooltip-title'>REGRAS: ATRIBUTOS (Peso 1)</div><div class='header-rule-row'><span>< 2 itens</span><span style='color:#ef4444'>1</span></div><div class='header-rule-row'><span>2 a 3 itens</span><span style='color:#eab308'>3</span></div><div class='header-rule-row'><span>4 a 6 itens</span><span style='color:#84cc16'>4</span></div><div class='header-rule-row'><span>>= 7 itens</span><span style='color:#10b981'>5</span></div></div>";

echo "
<div class='filter-container'>
    <div class='filter-header' onclick='toggleFilterBodyMel()'>
        <div class='filter-title'><i class='material-icons filter-icon'>tune</i> Filtros</div>
        <i class='material-icons filter-chevron' id='filterChevronMel'>expand_more</i>
    </div>
    <div class='filter-body' id='filterBodyMel'>
        <div class='f-grid'>
            <div class='f-group'><label class='f-label'>SKU / Cód</label><input type='text' id='f_sku_mel' class='f-input'></div>
            <div class='f-group'><label class='f-label'>ID AnyMarket</label><input type='text' id='f_id_any_mel' class='f-input'></div>
            <div class='f-group'><label class='f-label'>Título</label><input type='text' id='f_tit_mel' class='f-input'></div>
            <div class='f-group'><label class='f-label'>Marca</label><input type='text' id='f_mar_mel' class='f-input'></div>
            <div class='f-group'><label class='f-label'>Descrição</label><input type='text' id='f_desc_mel' class='f-input'></div>
            <div class='f-group'><label class='f-label'>Especs/EAN</label><input type='text' id='f_spec_mel' class='f-input'></div>
            <div class='f-group'><label class='f-label'>Estq. Líquido</label><input type='text' id='f_est_liq_mel' class='f-input'></div>
            <div class='f-group'><label class='f-label'>Estq. Tabela</label><input type='text' id='f_est_tab_mel' class='f-input'></div>
            <div class='f-group'><label class='f-label'>Frequência</label><input type='text' id='f_freq_mel' class='f-input'></div>
            <div class='f-group'><label class='f-label'>Custo</label><input type='text' id='f_custo_mel' class='f-input'></div>
            <div class='f-group'><label class='f-label'>Nota Título</label><input type='text' id='f_sc_tit_mel' class='f-input'></div>
            <div class='f-group'><label class='f-label'>Nota Desc</label><input type='text' id='f_sc_desc_mel' class='f-input'></div>
            <div class='f-group'><label class='f-label'>Nota Img</label><input type='text' id='f_sc_img_mel' class='f-input'></div>
            <div class='f-group'><label class='f-label'>Nota Especs</label><input type='text' id='f_sc_spec_mel' class='f-input'></div>
            <div class='f-group'><label class='f-label'>Nota Geral</label><input type='text' id='f_sco_mel' class='f-input'></div>
        </div>
        <div class='f-actions'>
            <button class='f-btn-apply' onclick='applyFiltersMel()' title='Aplicar Filtros'><i class='material-icons'>search</i></button>
            <button class='f-btn-clear' onclick='clearFiltersMel()' title='Limpar'><i class='material-icons'>backspace</i></button>
            <button class='f-btn-export' onclick='exportCSVMel()' title='Exportar CSV'><i class='material-icons'>file_download</i></button>
            <button class='f-btn-send' onclick='enviarCorrecaoMassaMel()' title='Enviar Selecionados'><i class='material-icons'>playlist_add_check</i></button>
            <button class='f-btn-sync' onclick='syncAnyMarketMassaMel()' title='Atualizar Selecionados da AnyMarket'><i class='material-icons'>sync</i></button>
            <button class='f-btn-sync_mel' onclick='openSyncModalMel()' title='Sincronizar Tudo'><i class='material-icons'>cloud_download</i></button>
        </div>
    </div>
</div>";

echo "<div class='quality-list'>";
echo "<div class='quality-header-mel'>
        <div style='cursor:pointer' onclick='toggleSelectAllMel()' title='Selecionar Todos'><i class='material-icons' style='font-size:16px'>check_box</i></div>
        <div>Foto</div><div>Produto / Marca</div><div>Métricas</div><div>Descrição</div><div>Especificações</div>
        <div style='cursor:help' onmouseenter='window.showHTooltip(this)' onmousemove='window.moveHTooltip(event)' onmouseleave='window.hideHTooltip()'>TÍTULO<div class='tooltip-hidden-content' style='display:none'>$tipTit</div></div>
        <div style='cursor:help' onmouseenter='window.showHTooltip(this)' onmousemove='window.moveHTooltip(event)' onmouseleave='window.hideHTooltip()'>DESC<div class='tooltip-hidden-content' style='display:none'>$tipDesc</div></div>
        <div style='cursor:help' onmouseenter='window.showHTooltip(this)' onmousemove='window.moveHTooltip(event)' onmouseleave='window.hideHTooltip()'>IMG<div class='tooltip-hidden-content' style='display:none'>$tipImg</div></div>
        <div style='cursor:help' onmouseenter='window.showHTooltip(this)' onmousemove='window.moveHTooltip(event)' onmouseleave='window.hideHTooltip()'>ESPEC<div class='tooltip-hidden-content' style='display:none'>$tipSpec</div></div>
        <div>Geral</div>
        <div>Ações</div>
      </div>";

echo "<div id='contentMel'><div class='start-msg' style='text-align:center; padding:50px; color:#9ca3af;'><i class='material-icons' style='font-size:48px; margin-bottom:10px; display:block;'>search</i><h2 style='font-size:18px; margin:0;'>Comece sua análise</h2><p>Utilize os filtros acima para carregar os produtos.</p></div></div>";

$ajaxUrl  = isset($_SERVER['REQUEST_URI']) ? htmlspecialchars($_SERVER['REQUEST_URI'], ENT_QUOTES, 'UTF-8') : '';
echo "<input type='hidden' id='hardness_total_mel' value='0'>";
echo "<input type='hidden' id='hardness_pageSize_mel' value='" . (int) $limit . "'>";
echo "<input type='hidden' id='hardness_ajaxUrl_mel' value='" . $ajaxUrl . "'>";
echo "<input type='hidden' id='sys_base_divRoot_mel' value='" . $gDivRoot . "'>";
echo "<input type='hidden' id='sys_base_divId_mel' value='" . $gDivId . "'>";
echo "<div id='demoMel'></div></div>";
?>

<div id="modalTipoEnvioMel" class="modal-overlay" onclick="if(event.target==this) fecharModalTipoMel()">
    <div class="modal-content" style="max-width: 450px; height: auto; flex-direction: column; align-items: center; padding: 30px; gap: 20px;">
        <span class="close-modal" onclick="fecharModalTipoMel()">×</span>
        <div style="text-align:center;">
            <h2 style="font-size:18px; color:#111827; margin:0 0 10px 0;">Selecione o Destino</h2>
            <p style="font-size:13px; color:#6b7280; margin:0;">Para qual fluxo deseja enviar os produtos selecionados?</p>
        </div>
        <div style="display:flex; gap: 20px; width: 100%; justify-content: center;">
            <button class="btn-sel-type" onclick="confirmarEnvioTipoMel('mod')">
                <i class="material-icons">edit_note</i> Modificação
            </button>
            <button class="btn-sel-type" onclick="confirmarEnvioTipoMel('corr')">
                <i class="material-icons">build_circle</i> Correção
            </button>
        </div>
    </div>
</div>

<div id="modalObsCorrecaoMel" class="modal-overlay">
    <div class="modal-content" style="max-width: 500px; height: auto; flex-direction: column; padding: 25px; gap: 15px; border-radius:16px;">
        <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #e5e7eb; padding-bottom:10px;">
             <h2 style="font-size:16px; font-weight:700; color:#111827; margin:0;">Detalhes da Correção</h2>
             <span class="close-modal" onclick="cancelarEnvioMel()">×</span>
        </div>
        <div class="obs-wrapper">
             <div>
                <label style="font-size:12px; font-weight:600; color:#374151; margin-bottom:8px; display:block;">Tags (Motivo)</label>
                <div class="tag-grid">
                    <label class="tag-check-item"><input type="checkbox" class="chk-tag-obs" value="1"> Imagem</label>
                    <label class="tag-check-item"><input type="checkbox" class="chk-tag-obs" value="2"> Título</label>
                    <label class="tag-check-item"><input type="checkbox" class="chk-tag-obs" value="3"> Descrição</label>
                    <label class="tag-check-item"><input type="checkbox" class="chk-tag-obs" value="4"> Pesos e Dim.</label>
                    <label class="tag-check-item"><input type="checkbox" class="chk-tag-obs" value="5"> Match</label>
                    <label class="tag-check-item"><input type="checkbox" class="chk-tag-obs" value="6"> Voltagem</label>
                    <label class="tag-check-item"><input type="checkbox" class="chk-tag-obs" value="7"> Cor</label>
                </div>
             </div>
             <div style="position:relative">
                <label style="font-size:12px; font-weight:600; color:#374151; margin-bottom:8px; display:block;">Observação</label>
                <div id="txtObsCorrecao" class="obs-input" contenteditable="true" data-placeholder="Escreva aqui detalhes sobre o problema..." oninput="updateCharCounterMel(this)"></div>
                <div class="char-counter char-green" id="charCounterMel">0 / 500</div>
             </div>
        </div>
        <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:10px;">
             <button class="f-btn-export_can" onclick="cancelarEnvioMel()"><i class='material-icons'>close</i></button>
             <button class="f-btn-send_env" onclick="finalizarEnvioCorrecao()"><i class='material-icons'>check</i></button>
        </div>
    </div>
</div>

<div id="modalVisMel" class="modal-overlay" onclick="if(event.target==this) fecharVisMel()">
    <div class="modal-content printable-area">
        <span class="close-modal" onclick="fecharVisMel()">×</span>
        <div class="vis-thumbs" id="visThumbsMel"></div>
        <div class="vis-main"><span class="vis-score-badge" id="visImgScoreMel">--</span><img id="visHeroMel" src=""></div>
        <div class="vis-info">
            <h1 class="vis-h1"><span id="visTitleMel">--</span><span class="vis-chip" id="visTitleScoreMel">--</span></h1>
            <div class="vis-meta">SKU: <strong id="visSkuMel">--</strong> | Marca: <strong id="visBrandMel">--</strong></div>
            <div class="vis-header-row"><span>Descrição do Produto</span><span class="vis-chip" id="visDescScoreMel">--</span></div>
            <div class="vis-desc-box" id="visDescMel"></div>
            <div class="vis-specs-container"><div class="vis-header-row" style="margin-top:15px"><span>Especificações</span><span class="vis-chip" id="visAttrScoreMel">--</span></div><div id="visSpecsContentMel"></div></div>
        </div>
    </div>
</div>

<div id="modalConfirmSyncMel" class="modal-overlay" onclick="if(event.target==this) fecharSyncModalMel()">
    <div class="modal-content" style="max-width: 450px; height: auto; flex-direction: column; align-items: center; padding: 30px; gap: 20px;">
        <span class="close-modal" onclick="fecharSyncModalMel()">×</span>
        <div style="text-align:center;">
            <h2 style="font-size:18px; color:#111827; margin:0 0 10px 0;">Sincronização Completa</h2>
            <p style="font-size:13px; color:#6b7280; margin:0;">Isso atualizará flags e buscará dados no AnyMarket. O processo pode demorar alguns instantes.</p>
        </div>
        <div style="display:flex; gap: 10px; width: 100%; justify-content: center;">
            <button class="f-btn-export_can" onclick="fecharSyncModalMel()"><i class='material-icons'>close</i></button>
            <button class="f-btn-send_env" onclick="executarSyncFullMel()"><i class='material-icons'>check</i></button>
        </div>
    </div>
</div>

<div id="modalProgressSyncMel" class="modal-overlay">
    <div class="modal-content" style="max-width: 700px; height: auto; flex-direction: column; padding: 25px;">
        <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #e5e7eb; padding-bottom:10px;">
             <div class="loading-spinner-box" id="headerProgressMel">
                 <div class="spinner-ring" id="spinnerSyncMel"></div>
                 <h2 style="font-size:16px; font-weight:700; color:#111827; margin:0;" id="titleSyncMel">Processando Sincronização...</h2>
             </div>
             <span class="close-modal" id="btnCloseSyncMel" onclick="fecharProgressSyncMel()" style="display:none;">×</span>
        </div>
        <div class="res-dash-cards" id="summarySyncMel" style="display:none; margin-top:15px;">
            <div class="res-card">
                <div class="res-card-val" id="valFlagsMel">0</div>
                <div class="res-card-lbl">Flags Corrigidas</div>
            </div>
            <div class="res-card">
                <div class="res-card-val" id="valNovosMel">0</div>
                <div class="res-card-lbl">Novos Inseridos</div>
            </div>
            <div class="res-card">
                <div class="res-card-val" id="valApiMel">0</div>
                <div class="res-card-lbl">Processados API</div>
            </div>
        </div>
        <div class="console-box" id="consoleLogMel"></div>
        <div style="display:flex; justify-content:flex-end; margin-top:15px;">
             <button class="f-btn-send_env" id="btnDoneSyncMel" onclick="fecharProgressSyncMel()" title="finalizar"><i class='material-icons'>check</i></button>
        </div>
    </div>
</div>

<script>
    function toggleFilterBodyMel() {
        var b = document.getElementById('filterBodyMel');
        var c = document.getElementById('filterChevronMel');
        if (b.classList.contains('closed')) { b.classList.remove('closed'); c.style.transform = 'rotate(0deg)'; } else { b.classList.add('closed'); c.style.transform = 'rotate(-90deg)'; }
    }
    
    if (typeof window.initHardnessTooltip === 'undefined') {
        window.initHardnessTooltip = true;
        var tipDiv = document.createElement('div'); tipDiv.id = 'hardness-custom-tooltip'; tipDiv.style.position = 'fixed'; tipDiv.style.display = 'none'; document.body.appendChild(tipDiv);
        window.showHTooltip = function (el) { var c = el.querySelector('.tooltip-hidden-content'); if (!c) return; var t = document.getElementById('hardness-custom-tooltip'); t.innerHTML = c.innerHTML; t.style.display = 'block'; };
        window.moveHTooltip = function (e) { var t = document.getElementById('hardness-custom-tooltip'); if (t && t.style.display === 'block') { var x = e.clientX + 15, y = e.clientY + 15; if (x + t.offsetWidth > window.innerWidth) x = e.clientX - t.offsetWidth - 5; if (y + t.offsetHeight > window.innerHeight) y = e.clientY - t.offsetHeight - 5; t.style.left = x + 'px'; t.style.top = y + 'px'; } };
        window.hideHTooltip = function () { var t = document.getElementById('hardness-custom-tooltip'); if (t) t.style.display = 'none'; };
    }
    
    var pagerMel = {
        render: function(targetId, total, current, size, callbackName) {
            var $t = jQuery('#' + targetId); var pages = Math.ceil(total / size);
            if (pages <= 1) { $t.removeClass('active').html(''); return; }
            var h = '', r = 2, start = Math.max(1, current - r), end = Math.min(pages, current + r);
            function btn(lbl, pg, cls) { return '<a href="javascript:void(0)" class="pg-btn ' + (cls||'') + '" onclick="'+callbackName+'('+pg+')">' + lbl + '</a>'; }
            if (current > 1) h += btn('<', current - 1);
            if (start > 1) { h += btn('1', 1, (current === 1 ? 'active' : '')); if (start > 2) h += '<span style="color:#999;padding:0 5px">...</span>'; }
            for (var i = start; i <= end; i++) { h += btn(i, i, (current === i ? 'active' : '')); }
            if (end < pages) { if (end < pages - 1) h += '<span style="color:#999;padding:0 5px">...</span>'; h += btn(pages, pages, (current === pages ? 'active' : '')); }
            if (current < pages) h += btn('>', current + 1);
            $t.addClass('active').html(h);
        }
    };
    
    var appMel = {
        // [NOVO] Chave única para o localStorage
        cacheKey: 'hardness_mel_filters_v1',

        getFilters: function() {
            return {
                f_tit: jQuery('#f_tit_mel').val(), f_id_any: jQuery('#f_id_any_mel').val(), f_sku: jQuery('#f_sku_mel').val(),
                f_mar: jQuery('#f_mar_mel').val(), f_desc: jQuery('#f_desc_mel').val(), f_spec: jQuery('#f_spec_mel').val(),
                f_est_liq: jQuery('#f_est_liq_mel').val(), f_est_tab: jQuery('#f_est_tab_mel').val(),
                f_freq: jQuery('#f_freq_mel').val(), f_custo: jQuery('#f_custo_mel').val(), f_sco: jQuery('#f_sco_mel').val(),
                f_sc_tit: jQuery('#f_sc_tit_mel').val(), f_sc_desc: jQuery('#f_sc_desc_mel').val(),
                f_sc_img: jQuery('#f_sc_img_mel').val(), f_sc_spec: jQuery('#f_sc_spec_mel').val()
            };
        },
        
        // [NOVO] Salva os filtros no navegador
        saveState: function() {
            var filters = this.getFilters();
            localStorage.setItem(this.cacheKey, JSON.stringify(filters));
        },

        // [NOVO] Carrega os filtros do navegador e preenche os inputs
        loadState: function() {
            var saved = localStorage.getItem(this.cacheKey);
            if (!saved) return false;
            try {
                var filters = JSON.parse(saved);
                var hasValue = false;
                for (var key in filters) {
                    if (filters.hasOwnProperty(key)) {
                        // Mapeia a chave do filtro para o ID do input (ex: f_tit -> #f_tit_mel)
                        var inputId = '#' + key + '_mel';
                        var val = filters[key];
                        if (jQuery(inputId).length) {
                            jQuery(inputId).val(val);
                            if(val !== '') hasValue = true;
                        }
                    }
                }
                return hasValue; // Retorna true se carregou algum filtro
            } catch (e) { console.error('Erro ao carregar cache Mel', e); return false; }
        },

        // [NOVO] Limpa o cache
        clearState: function() {
            localStorage.removeItem(this.cacheKey);
        },

        loadData: function(p) {
            var pageSizeVal = jQuery('#hardness_pageSize_mel').val();
            var urlVal      = jQuery('#hardness_ajaxUrl_mel').val();
            var sysIdVal    = jQuery('#sys_base_divId_mel').val();
            var sysRootVal  = jQuery('#sys_base_divRoot_mel').val();
            p = parseInt(p, 10) || 1; 
            var filters = this.getFilters(); 
            var size = parseInt(pageSizeVal, 10) || 50; 
            if (sysIdVal && jQuery('#' + sysIdVal).length) jQuery('#' + sysIdVal).showLoading();
            jQuery.ajax({ 
                url: urlVal, type: 'POST', dataType: 'json', 
                data: jQuery.extend({ ajax: 1, page: p, pageSize: size, sys_divRoot: sysRootVal, sys_divId: sysIdVal }, filters), 
                success: function (r) { if (r && r.ok) { jQuery('#contentMel').html(r.html); pagerMel.render('demoMel', r.total, p, size, 'appMel.loadData'); } else { jQuery('#contentMel').html('<div class="start-msg">Sem resultados</div>'); jQuery('#demoMel').removeClass('active').html(''); } }, 
                complete: function () { if (sysIdVal && jQuery('#' + sysIdVal).length) jQuery('#' + sysIdVal).hideLoading(); } 
            });
        }
    };
    
    function applyFiltersMel() { 
        appMel.saveState(); // [NOVO] Salva ao aplicar
        appMel.loadData(1); 
    }
    
    function clearFiltersMel() { 
        appMel.clearState(); // [NOVO] Limpa o cache
        jQuery('#filterBodyMel input').val(''); 
        jQuery('#filterBodyMel select').val(''); 
    }

    function exportCSVMel() {
        var filters = appMel.getFilters(); var url = jQuery('#hardness_ajaxUrl_mel').val(); var form = document.createElement('form'); form.method = 'POST'; form.action = url; form.target = '_blank';
        var i1 = document.createElement('input'); i1.name = 'ajax'; i1.value = '1'; form.appendChild(i1); var i2 = document.createElement('input'); i2.name = 'action'; i2.value = 'export_csv_mel'; form.appendChild(i2);
        for (var key in filters) { if (filters.hasOwnProperty(key)) { var inp = document.createElement('input'); inp.name = key; inp.value = filters[key]; form.appendChild(inp); } }
        document.body.appendChild(form); form.submit(); document.body.removeChild(form);
    }
    
    var idsPendingMel = [];
    function fecharModalTipoMel() { document.getElementById('modalTipoEnvioMel').style.display = 'none'; }
    function cancelarEnvioMel() { document.getElementById('modalTipoEnvioMel').style.display = 'none'; document.getElementById('modalObsCorrecaoMel').style.display = 'none'; idsPendingMel = []; jQuery('#txtObsCorrecao').text(''); jQuery('.chk-tag-obs').prop('checked', false); updateCharCounterMel(document.getElementById('txtObsCorrecao')); }
    function enviarCorrecaoSingleMel(id) { idsPendingMel = [id]; document.getElementById('modalTipoEnvioMel').style.display = 'flex'; }
    function enviarCorrecaoMassaMel() { var ids = []; jQuery('.row-check:checked').each(function() { ids.push(jQuery(this).val()); }); if (ids.length === 0) { alert('Selecione pelo menos um item.'); return; } idsPendingMel = ids; document.getElementById('modalTipoEnvioMel').style.display = 'flex'; }
    function confirmarEnvioTipoMel(tipo) { if (idsPendingMel.length > 0) { if (tipo === 'corr') { fecharModalTipoMel(); document.getElementById('modalObsCorrecaoMel').style.display = 'flex'; } else { enviarAjaxCorrecaoMel(idsPendingMel, tipo, '', ''); fecharModalTipoMel(); } } }
    function updateCharCounterMel(el) { var count = el.innerText.length; var display = document.getElementById('charCounterMel'); display.innerText = count + ' / 500'; if (count > 500) { display.className = 'char-counter char-red'; } else { display.className = 'char-counter char-green'; } }
    function finalizarEnvioCorrecao() { var el = document.getElementById('txtObsCorrecao'); var obs = el.innerText; if (obs.length > 500) { alert('Limite de 500 caracteres excedido!'); return; } var tags = []; jQuery('.chk-tag-obs:checked').each(function() { tags.push(jQuery(this).val()); }); var tagsStr = tags.join(','); if (idsPendingMel.length > 0) { enviarAjaxCorrecaoMel(idsPendingMel, 'corr', obs, tagsStr); } document.getElementById('modalObsCorrecaoMel').style.display = 'none'; }
    function enviarAjaxCorrecaoMel(ids, tipo, obs, tags) {
        var url = jQuery('#hardness_ajaxUrl_mel').val(); var sysIdVal = jQuery('#sys_base_divId_mel').val(); var sysRootVal = jQuery('#sys_base_divRoot_mel').val();
        if (sysIdVal && jQuery('#' + sysIdVal).length) jQuery('#' + sysIdVal).showLoading();
        jQuery.ajax({ url: url, type: 'POST', dataType: 'json', data: { ajax: 1, action: 'send_correction_mel', ids: ids, tipo: tipo, obs: obs, tags: tags, sys_divRoot: sysRootVal, sys_divId: sysIdVal }, success: function(res) { alert(res.msg || 'Processado.'); jQuery('.row-check').prop('checked', false); idsPendingMel = []; jQuery('#txtObsCorrecao').text(''); jQuery('.chk-tag-obs').prop('checked', false); updateCharCounterMel(document.getElementById('txtObsCorrecao')); }, complete: function() { if (sysIdVal && jQuery('#' + sysIdVal).length) jQuery('#' + sysIdVal).hideLoading(); } });
    }

    var allSelectedMel = false;
    function toggleSelectAllMel() { allSelectedMel = !allSelectedMel; jQuery('.row-check').prop('checked', allSelectedMel); }

    const mVisMel = document.getElementById('modalVisMel'), vThumbsMel = document.getElementById('visThumbsMel'), vHeroMel = document.getElementById('visHeroMel'), vTitleMel = document.getElementById('visTitleMel'), vSkuMel = document.getElementById('visSkuMel'), vBrandMel = document.getElementById('visBrandMel'), vDescMel = document.getElementById('visDescMel'), vSpecsMel = document.getElementById('visSpecsContentMel');
    const elTSMel = document.getElementById('visTitleScoreMel'), elDSMel = document.getElementById('visDescScoreMel'), elISMel = document.getElementById('visImgScoreMel'), elASMel = document.getElementById('visAttrScoreMel');
    function getMetaNotaMel(n) { n = Number(n); if (n === 6) return { c: '#0098D3', t: 'Ótima' }; if (n === 5) return { c: '#10b981', t: 'Muito Boa' }; if (n === 4) return { c: '#84cc16', t: 'Boa' }; if (n === 3) return { c: '#eab308', t: 'Média' }; if (n === 2) return { c: '#fca5a5', t: 'Ruim' }; return { c: '#ef4444', t: 'Muito Ruim' }; }
    function abrirVisualizadorMel(sku) {
        var url = document.getElementById('hardness_ajaxUrl_mel').value; var sysIdVal = document.getElementById('sys_base_divId_mel').value; var sysRootVal = document.getElementById('sys_base_divRoot_mel').value;
        if (sysIdVal && typeof jQuery !== 'undefined' && jQuery('#' + sysIdVal).length) jQuery('#' + sysIdVal).showLoading();
        jQuery.ajax({ url: url, type: 'POST', dataType: 'json', data: { ajax: 1, action: 'get_details_mel', sku: sku, sys_divRoot: sysRootVal, sys_divId: sysIdVal }, success: function (res) { if (res.ok) { vTitleMel.innerText = res.titulo; vSkuMel.innerText = res.sku; vBrandMel.innerText = res.marca; vDescMel.innerHTML = res.desc ? res.desc : '<em>Sem descrição.</em>'; const mT = getMetaNotaMel(res.scores.tit); elTSMel.style.backgroundColor = mT.c; elTSMel.innerText = res.scores.tit + ' - ' + mT.t; const mD = getMetaNotaMel(res.scores.desc); elDSMel.style.backgroundColor = mD.c; elDSMel.innerText = res.scores.desc + ' - ' + mD.t; const mI = getMetaNotaMel(res.scores.img); elISMel.style.backgroundColor = mI.c; elISMel.innerText = 'Fotos: ' + res.scores.img + ' (' + mI.t + ')'; const mA = getMetaNotaMel(res.scores.attr); elASMel.style.backgroundColor = mA.c; elASMel.innerText = res.scores.attr + ' - ' + mA.t; vThumbsMel.innerHTML = ''; if (res.imgs.length > 0) vHeroMel.src = res.imgs[0]; res.imgs.forEach((url, idx) => { let img = document.createElement('img'); img.src = url; img.className = 'vis-mini'; if (idx === 0) img.classList.add('active'); img.onclick = () => { vHeroMel.src = url; document.querySelectorAll('.vis-mini').forEach(el => el.classList.remove('active')); img.classList.add('active'); }; vThumbsMel.appendChild(img); }); let h = '<table class="vis-specs-table">'; let has = false; if (res.specs.EAN) { h += `<tr><td><strong>EAN:</strong> ${res.specs.EAN}</td></tr>`; has = true; } if (res.specs.Garantia) { h += `<tr><td><strong>Garantia:</strong> ${res.specs.Garantia}</td></tr>`; has = true; } if (res.specs.Peso) { h += `<tr><td><strong>Peso:</strong> ${res.specs.Peso}</td></tr>`; has = true; } if (res.specs.Altura) { h += `<tr><td><strong>Altura:</strong> ${res.specs.Altura}</td></tr>`; has = true; } if (res.specs.Largura) { h += `<tr><td><strong>Largura:</strong> ${res.specs.Largura}</td></tr>`; has = true; } if (res.specs.Comprimento) { h += `<tr><td><strong>Comp.:</strong> ${res.specs.Comprimento}</td></tr>`; has = true; } h += '</table>'; vSpecsMel.innerHTML = has ? h : '<div style="color:#999;font-size:12px">Vazio</div>'; mVisMel.style.display = 'flex'; } else { alert(res.msg || 'Erro ao carregar'); } }, error: function () { alert('Erro na comunicação'); }, complete: function () { if (sysIdVal && typeof jQuery !== 'undefined' && jQuery('#' + sysIdVal).length) jQuery('#' + sysIdVal).hideLoading(); } });
    }
    function fecharVisMel() { mVisMel.style.display = 'none'; }
    document.addEventListener('keydown', e => { if (e.key === "Escape") fecharVisMel() });

    function openSyncModalMel() { document.getElementById('modalConfirmSyncMel').style.display = 'flex'; }
    function fecharSyncModalMel() { document.getElementById('modalConfirmSyncMel').style.display = 'none'; }
    
    // --- FUNÇÕES DE LOG DO CONSOLE ---
    function addConsoleLog(msg, type = 'info') {
        const box = document.getElementById('consoleLogMel');
        const time = new Date().toLocaleTimeString();
        let colorClass = 'console-info';
        if (type === 'success') colorClass = 'console-success';
        if (type === 'error') colorClass = 'console-error';
        if (type === 'warn') colorClass = 'console-warn';
        const html = `<div class="console-line"><span class="console-time">[${time}]</span><span class="${colorClass}">${msg}</span></div>`;
        box.insertAdjacentHTML('beforeend', html);
        box.scrollTop = box.scrollHeight;
    }

    function fecharProgressSyncMel() {
        document.getElementById('modalProgressSyncMel').style.display = 'none';
        applyFiltersMel();
    }

    function executarSyncFullMel() {
        fecharSyncModalMel();
        const mProg = document.getElementById('modalProgressSyncMel');
        const mLog  = document.getElementById('consoleLogMel');
        
        mProg.style.display = 'flex';
        document.getElementById('spinnerSyncMel').style.display = 'block';
        document.getElementById('titleSyncMel').innerText = 'Sincronizando... Aguarde.';
        document.getElementById('titleSyncMel').style.color = '#111827';
        document.getElementById('summarySyncMel').style.display = 'none';
        document.getElementById('btnCloseSyncMel').style.display = 'none';
        document.getElementById('btnDoneSyncMel').style.display = 'none';
        mLog.innerHTML = '';

        addConsoleLog("Iniciando processo de sincronização em massa...", "info");
        addConsoleLog("Conectando ao banco de dados...", "info");
        setTimeout(() => { addConsoleLog("Verificando Flags de E-commerce e Novos Produtos...", "warn"); }, 500);
        setTimeout(() => { addConsoleLog("Consultando API AnyMarket (Isso pode levar alguns minutos)...", "warn"); }, 1500);

        var url = jQuery('#hardness_ajaxUrl_mel').val(); var sysIdVal = jQuery('#sys_base_divId_mel').val(); var sysRootVal = jQuery('#sys_base_divRoot_mel').val();

        jQuery.ajax({
            url: url, type: 'POST', dataType: 'json',
            data: { ajax: 1, action: 'sync_auto_full_mel', sys_divRoot: sysRootVal, sys_divId: sysIdVal },
            success: function(res) {
                if (res.ok) {
                    addConsoleLog("Resposta do servidor recebida com sucesso.", "success");
                    addConsoleLog("--------------------------------------------------", "info");
                    jQuery('#valFlagsMel').text(res.summary.flags);
                    jQuery('#valNovosMel').text(res.summary.novos);
                    
                    let successCount = 0;
                    if (res.details && res.details.length > 0) {
                        res.details.forEach(function(item) {
                            if (item.status === 'Sucesso') {
                                addConsoleLog(`[OK] SKU: ${item.sku} -> ID Any: ${item.idAny} (Atualizado)`, "success");
                                successCount++;
                            } else {
                                addConsoleLog(`[ERRO] SKU: ${item.sku} -> ${item.msg}`, "error");
                            }
                        });
                        addConsoleLog("--------------------------------------------------", "info");
                        addConsoleLog(`Processamento finalizado. ${successCount} itens atualizados via API.`, "info");
                    } else {
                        addConsoleLog("Nenhum item pendente de atualização na API (Fila vazia ou limite atingido).", "warn");
                    }
                    jQuery('#valApiMel').text(successCount);
                    document.getElementById('spinnerSyncMel').style.display = 'none';
                    document.getElementById('titleSyncMel').innerText = 'Processo Finalizado';
                    document.getElementById('titleSyncMel').style.color = '#10b981';
                    document.getElementById('summarySyncMel').style.display = 'flex';
                } else {
                    addConsoleLog("ERRO RETORNADO PELO SERVIDOR:", "error");
                    addConsoleLog(res.msg || 'Erro desconhecido', "error");
                    document.getElementById('spinnerSyncMel').style.display = 'none';
                    document.getElementById('titleSyncMel').innerText = 'Erro na Execução';
                    document.getElementById('titleSyncMel').style.color = '#ef4444';
                }
            },
            error: function(xhr, status, error) {
                addConsoleLog("FALHA DE COMUNICAÇÃO AJAX", "error");
                addConsoleLog(`Status: ${status} | Erro: ${error}`, "error");
                document.getElementById('spinnerSyncMel').style.display = 'none';
                document.getElementById('titleSyncMel').innerText = 'Falha Crítica';
                document.getElementById('titleSyncMel').style.color = '#ef4444';
            },
            complete: function() {
                document.getElementById('btnCloseSyncMel').style.display = 'block';
                document.getElementById('btnDoneSyncMel').style.display = 'block';
                addConsoleLog("Pode fechar esta janela.", "info");
            }
        });
    }

    function syncAnyMarketItemMel(id) {
        if(!confirm('Deseja buscar os dados mais recentes deste item na AnyMarket?')) return;
        var url = jQuery('#hardness_ajaxUrl_mel').val(); var sysId = jQuery('#sys_base_divId_mel').val(); var sysRoot = jQuery('#sys_base_divRoot_mel').val();
        if (sysId) jQuery('#' + sysId).showLoading();
        jQuery.ajax({
            url: url, type: 'POST', dataType: 'json',
            data: { ajax: 1, action: 'sync_anymarket_item_mel', id: id, sys_divRoot: sysRoot, sys_divId: sysId },
            success: function(res) { if (res.ok) { alert(res.msg); appMel.loadData(1); } else { alert('Erro: ' + res.msg); } },
            error: function() { alert('Erro de comunicação.'); },
            complete: function() { if (sysId) jQuery('#' + sysId).hideLoading(); }
        });
    }

    function syncAnyMarketMassaMel() {
        var checked = jQuery('.row-check:checked');
        if (checked.length === 0) { alert('Selecione pelo menos um item.'); return; }
        if (checked.length > 40) { alert('Limite de 40 itens por vez excedido (Selecionados: ' + checked.length + ').'); return; }
        if (!confirm('Deseja sincronizar os ' + checked.length + ' itens selecionados?')) return;
        var ids = []; checked.each(function() { ids.push(jQuery(this).val()); });
        var url = jQuery('#hardness_ajaxUrl_mel').val(); var sysId = jQuery('#sys_base_divId_mel').val(); var sysRoot = jQuery('#sys_base_divRoot_mel').val();
        if (sysId) jQuery('#' + sysId).showLoading();
        jQuery.ajax({
            url: url, type: 'POST', dataType: 'json',
            data: { ajax: 1, action: 'sync_anymarket_massa_mel', ids: ids, sys_divRoot: sysRoot, sys_divId: sysId },
            success: function(res) { if (res.ok) { alert(res.msg); appMel.loadData(1); } else { alert('Erro: ' + res.msg); } },
            error: function() { alert('Erro de comunicação.'); },
            complete: function() { if (sysId) jQuery('#' + sysId).hideLoading(); }
        });
    }

    // [NOVO] Inicialização: Tenta carregar cache ao iniciar o script
    jQuery(document).ready(function() {
        if (appMel.loadState()) {
            appMel.loadData(1);
        }
    });
</script>