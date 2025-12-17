<?php
/*
 PAINEL DE MELHORIA DE ANUNCIO (D001E) - CSS E JS ISOLADOS (SUFIXO _MEL)
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
if ($page < 1) $page = 1;

if (isset($_POST['pageSize'])) {
    $tmp = (int) $_POST['pageSize'];
    if ($tmp > 0) $limit = $tmp;
}

$offset = ($page - 1) * $limit;

$isAjax  = (isset($_POST['ajax']) && (int) $_POST['ajax'] === 1);
$apiMode = 0;

// INDICADOR VISUAL (Azul)
if (!$isAjax) {
    echo "<div style='
            position: fixed; bottom: 20px; right: 20px;
            background: #ffffff; color: #1f2937;
            padding: 10px 16px; border-radius: 50px;
            font-size: 12px; font-family: -apple-system, sans-serif; font-weight: 600;
            z-index: 999998; box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            border: 1px solid #f3f4f6; pointer-events: none; display:flex; align-items:center; gap:8px;
          '>
            <span style='background:#0098D3; width:8px; height:8px; border-radius:50%; display:inline-block;'></span>
            <span>Melhoria Anúncio: <strong style='color: #111827;'>ID {$C004_Id}</strong></span>
          </div>";
}

// =============================================================================
// [FUNC] FUNÇÕES DE CÁLCULO (SCORE) - COM SUFIXO _MEL
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
        if ($len < 30) { $n = 1; $regra = "< 30 chars"; }
        elseif ($len < 40) { $n = 2; $regra = "30-39 chars"; }
        elseif ($len < 50) { $n = 3; $regra = "40-49 chars"; }
        elseif ($len < 60) { $n = 4; $regra = "50-59 chars"; }
        elseif ($len < 90) { $n = 5; $regra = "60-89 chars"; }
        else { $n = 0; $regra = "> 90 chars"; }
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
        else { $n = 0; $regra = "> 4000 chars"; }
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
        elseif ($qtd < 5) { $n = 4; $regra = "3-4 imgs"; }
        elseif ($qtd <= 10) { $n = 5; $regra = "5-10 imgs"; }
        else { $n = 0; $regra = "> 10 imgs"; }
        return ['nota' => $n, 'valor' => $qtd . ' fotos', 'regra' => $regra, 'peso' => 3];
    }
}
if (!function_exists('analiseAtributosMel')) {
    function analiseAtributosMel($row) {
        $count = 0;
        if (!empty($row['D001E_EAN'])) $count++;
        if (!empty($row['D001E_garantia'])) $count++;
        if (!empty($row['D001E_peso'])) $count++;
        if (!empty($row['D001E_altura'])) $count++;
        if (!empty($row['D001E_largura'])) $count++;
        if (!empty($row['D001E_comprimento'])) $count++;
        $n = 0; $regra = "";
        if ($count < 2) { $n = 1; $regra = "< 2 atrib."; }
        elseif ($count < 4) { $n = 3; $regra = "2-3 atrib."; }
        elseif ($count < 7) { $n = 4; $regra = "4-6 atrib."; }
        elseif ($count < 20) { $n = 5; $regra = "7-19 atrib."; }
        else { $n = 5; $regra = "Completo"; }
        return ['nota' => $n, 'valor' => $count . ' preench.', 'regra' => $regra, 'peso' => 1];
    }
}
if (!function_exists('analiseImagemEspecialMel')) {
    function analiseImagemEspecialMel($row) {
        $nota = (int) ($row['D001E_pont_img_especial'] ?? 1);
        if ($nota < 1 || $nota > 5) $nota = 1;
        $regra = "";
        switch ($nota) {
            case 5: $regra = "Ambientadas, componentes, embalagem e ângulos"; break;
            case 4: $regra = "Componentes, embalagem e ângulos"; break;
            case 3: $regra = "Embalagem e ângulos"; break;
            case 2: $regra = "Alguns ângulos"; break;
            case 1: default: $regra = "Recortada/Zero ângulo ou pendente"; break;
        }
        return ['nota' => $nota, 'valor' => 'Manual (' . $nota . ')', 'regra' => $regra, 'peso' => 1];
    }
}
if (!function_exists('analiseVideoMel')) {
    function analiseVideoMel($row) {
        return ['nota' => 0, 'valor' => 'Sem Vídeo', 'regra' => 'Sem Short (0)', 'peso' => 1];
    }
}
if (!function_exists('getCorNotaMel')) {
    function getCorNotaMel($n) {
        switch ($n) {
            case 6: return "#0098D3";
            case 5: return "#10b981";
            case 4: return "#84cc16";
            case 3: return "#eab308";
            case 2: return "#fca5a5";
            default: return "#ef4444";
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

// =============================================================================
// [RENDER] FUNÇÃO DE LINHA (D001E)
// =============================================================================
function renderQualityRowMel($row) {
    $marca       = isset($row['D001E_Marca']) ? $row['D001E_Marca'] : '';
    $updateMarca = false;
    if (empty($marca) && !empty($row['D001E_Json_Nativo'])) {
        $marcaExtraida = extrairMarcaJsonAnyMarketMel($row['D001E_Json_Nativo']);
        if (!empty($marcaExtraida)) {
            $marca       = $marcaExtraida;
            $updateMarca = true;
        } else {
            $marca = "ND";
        }
    }

    // SCORE
    $resT  = analiseTituloMel($row['D001E_Titulo']);
    $resD  = analiseDescricaoMel($row['D001E_Descricao']);
    $resI  = analiseImagensMel($row);
    $resA  = analiseAtributosMel($row);
    $resIE = analiseImagemEspecialMel($row);
    $resV  = analiseVideoMel($row);

    $soma  = ($resT['nota'] * 3) + ($resD['nota'] * 3) + ($resI['nota'] * 3) + ($resIE['nota'] * 1) + ($resA['nota'] * 1) + ($resV['nota'] * 1);
    $final = floor($soma / 11);
    $final = max(1, min(5, $final));
    if ($final == 5 && $resV['nota'] > 0) $final = 6;

    // Updates no Banco (Silencioso)
    $idProd  = (int) $row['D001E_Id'];
    $sqlSets = [];
    if ($row['D001E_Status_Pontuacao'] != $final) $sqlSets[] = "D001E_Status_Pontuacao = $final";
    if ($row['D001E_pont_titulo'] != $resT['nota']) $sqlSets[] = "D001E_pont_titulo = {$resT['nota']}";
    if ($row['D001E_pont_desc'] != $resD['nota']) $sqlSets[] = "D001E_pont_desc = {$resD['nota']}";
    if ($row['D001E_pont_img'] != $resI['nota']) $sqlSets[] = "D001E_pont_img = {$resI['nota']}";
    if ($row['D001E_pont_espec'] != $resA['nota']) $sqlSets[] = "D001E_pont_espec = {$resA['nota']}";

    if ($updateMarca) {
        $marcaSafe = mysql_real_escape_string($marca);
        $sqlSets[] = "D001E_Marca = '$marcaSafe'";
    }

    if (!empty($sqlSets)) {
        $sqlUpdate = "UPDATE D001E SET " . implode(', ', $sqlSets) . " WHERE D001E_Id = $idProd";
        mysql_query($sqlUpdate);
    }

    // Cores e Labels
    switch ($final) {
        case 6: $c = "#0098D3"; $p = 100; $l = "Ótima"; break;
        case 5: $c = "#10b981"; $p = 85; $l = "Muito Boa"; break;
        case 4: $c = "#84cc16"; $p = 70; $l = "Boa"; break;
        case 3: $c = "#eab308"; $p = 50; $l = "Média"; break;
        case 2: $c = "#fca5a5"; $p = 30; $l = "Ruim"; break;
        default: $c = "#ef4444"; $p = 15; $l = "Muito Ruim"; break;
    }

    $imgCapa   = $row['D001E_Imagem_1'] ?: "https://via.placeholder.com/100x100?text=Sem+Img";
    $titulo    = htmlspecialchars($row['D001E_Titulo'], ENT_QUOTES);
    $skuRaw    = $row['D001E_D001_Codigo_Produto'];
    $sku       = htmlspecialchars($skuRaw, ENT_QUOTES);
    $descRaw   = $row['D001E_Descricao'];
    $marcaHtml = htmlspecialchars($marca, ENT_QUOTES);

    $specHtml = "";
    if (!empty($row['D001E_EAN'])) $specHtml .= "<b>EAN:</b> {$row['D001E_EAN']}<br>";
    if (!empty($row['D001E_garantia'])) $specHtml .= "<b>Gar:</b> {$row['D001E_garantia']}<br>";
    if (!empty($row['D001E_peso'])) $specHtml .= "<b>Peso:</b> {$row['D001E_peso']}<br>";
    if (!empty($row['D001E_altura'])) $specHtml .= "<b>Dim:</b> " . ($row['D001E_altura'] ?: 0) . "x" . ($row['D001E_largura'] ?: 0) . "x" . ($row['D001E_comprimento'] ?: 0);
    if (empty($specHtml)) $specHtml = "<span style='color:#bbb'>Vazio</span>";

    // --- DADOS D009 ---
    $freqVenda = !empty($row['D009_Frequencia_Venda']) ? $row['D009_Frequencia_Venda'] : '<b>0</b>';
    $custoVal  = isset($row['D009_Valor_Custo_Unitario']) ? (float) $row['D009_Valor_Custo_Unitario'] : 0;
    $estTab    = isset($row['D009_Quantidade_Estoque_Tabela']) ? (int) $row['D009_Quantidade_Estoque_Tabela'] : 0;
    $estLiq    = isset($row['D009_Quantidade_Estoque_Liquido']) ? (int) $row['D009_Quantidade_Estoque_Liquido'] : 0;

    $custoHtml  = ($custoVal > 0) ? "<span style='color:#0098D3; font-weight:700;'>R$ " . number_format($custoVal, 2, ',', '.') . "</span>" : "<b>0</b>";
    $estTabHtml = ($estTab > 0) ? $estTab : "<b>0</b>";
    $estLiqHtml = ($estLiq > 0) ? $estLiq : "<b>0</b>";

    return "
    <div class='quality-row-mel'>
        <div class='col-check'>
             <input type='checkbox' class='row-check' value='$idProd'>
        </div>
        
        <div class='thumb-box' onclick='abrirVisualizadorMel(\"$sku\")'>
            <img src='$imgCapa'>
        </div>
        
        <div class='col-info'>
            <div class='prod-title'>{$row['D001E_Titulo']}</div>
            <div class='prod-sub'>
                <span class='badge-sku'>$sku</span>
                <span class='badge-brand' title='$marcaHtml'>$marcaHtml</span>
            </div>
        </div>
        
        <div class='col-metrics'>
            <div class='metric-cell'><span class='lbl'>Freq</span> <span class='val'>$freqVenda</span></div>
            <div class='metric-cell'><span class='lbl'>Custo</span> <span class='val'>$custoHtml</span></div>
            <div class='metric-cell'><span class='lbl'>Tab</span> <span class='val'>$estTabHtml</span></div>
            <div class='metric-cell'><span class='lbl'>Liq</span> <span class='val'>$estLiqHtml</span></div>
        </div>
        
        <div class='col-box-scroll'>" . ($descRaw ?: '<em>Sem descrição</em>') . "</div>
        
        <div class='col-box-scroll'>$specHtml</div>
        
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
        
        <div class='col-score'>
            <div class='score-circle' style='--color:$c; --percent:$p;'>
                <span class='score-number'>$final</span>
            </div>
            <span class='score-label' style='color:$c'>$l</span>
        </div>
        
        <div class='col-actions' style='display:flex; flex-direction:column; align-items:center; justify-content:center; gap:5px;'>
             <button class='f-btn-send-single' onclick='enviarCorrecaoSingleMel(\"$idProd\")' title='Enviar para Correção' style='width:100%; border-radius:4px; padding:8px 0;'><i class='material-icons' style='font-size:18px'>build</i></button>
        </div>
    </div>";
}

// =============================================================================
// [AJAX] GERENCIADOR
// =============================================================================
if ($isAjax) {
    
    if (!function_exists('cleanInputMel')) {
        function cleanInputMel($data) {
            $data = trim($data);
            return mysql_real_escape_string($data);
        }
    }

    // [NOVO] ENVIAR PARA CORREÇÃO (D001E -> D001F)
    if (isset($_POST['action']) && $_POST['action'] === 'send_correction_mel') {
        $ids = isset($_POST['ids']) ? $_POST['ids'] : []; 
        if (!is_array($ids)) $ids = explode(',', $ids);
        
        $count = 0;
        foreach ($ids as $idE) {
            $idE = (int)$idE;
            if ($idE <= 0) continue;

            $sqlSrc = "SELECT * FROM D001E WHERE D001E_Id = $idE LIMIT 1";
            $rsSrc = mysql_query($sqlSrc);
            if ($rsSrc && mysql_num_rows($rsSrc) > 0) {
                $src = mysql_fetch_assoc($rsSrc);
                
                $sku = mysql_real_escape_string($src['D001E_D001_Codigo_Produto']);
                $check = mysql_query("SELECT D001F_Id FROM D001F WHERE D001F_D001_Codigo_Produto = '$sku'");
                
                if (mysql_num_rows($check) == 0) {
                    $cols = "D001F_D001_Id, D001F_D001_Codigo_Produto, D001F_Titulo, D001F_Marca, D001F_Descricao, 
                             D001F_Imagem_1, D001F_Imagem_2, D001F_Imagem_3, D001F_Imagem_4, D001F_Imagem_5, 
                             D001F_Imagem_6, D001F_Imagem_7, D001F_Imagem_8, D001F_Imagem_9, D001F_Imagem_10, 
                             D001F_EAN, D001F_garantia, D001F_peso, D001F_altura, D001F_largura, D001F_comprimento, D001F_ult_att";
                             
                    $vals = "'" . mysql_real_escape_string($src['D001E_D001_Id']) . "',
                             '" . mysql_real_escape_string($src['D001E_D001_Codigo_Produto']) . "',
                             '" . mysql_real_escape_string($src['D001E_Titulo']) . "',
                             '" . mysql_real_escape_string($src['D001E_Marca']) . "',
                             '" . mysql_real_escape_string($src['D001E_Descricao']) . "',
                             '" . mysql_real_escape_string($src['D001E_Imagem_1']) . "',
                             '" . mysql_real_escape_string($src['D001E_Imagem_2']) . "',
                             '" . mysql_real_escape_string($src['D001E_Imagem_3']) . "',
                             '" . mysql_real_escape_string($src['D001E_Imagem_4']) . "',
                             '" . mysql_real_escape_string($src['D001E_Imagem_5']) . "',
                             '" . mysql_real_escape_string($src['D001E_Imagem_6']) . "',
                             '" . mysql_real_escape_string($src['D001E_Imagem_7']) . "',
                             '" . mysql_real_escape_string($src['D001E_Imagem_8']) . "',
                             '" . mysql_real_escape_string($src['D001E_Imagem_9']) . "',
                             '" . mysql_real_escape_string($src['D001E_Imagem_10']) . "',
                             '" . mysql_real_escape_string($src['D001E_EAN']) . "',
                             '" . mysql_real_escape_string($src['D001E_garantia']) . "',
                             '" . mysql_real_escape_string($src['D001E_peso']) . "',
                             '" . mysql_real_escape_string($src['D001E_altura']) . "',
                             '" . mysql_real_escape_string($src['D001E_largura']) . "',
                             '" . mysql_real_escape_string($src['D001E_comprimento']) . "',
                             NOW()";
                             
                    mysql_query("INSERT INTO D001F ($cols) VALUES ($vals)");
                    $count++;
                }
            }
        }
        echo json_encode(['ok' => 1, 'msg' => "$count produtos enviados para a lista de Correção."]);
        exit;
    }

    // [EXPORTAÇÃO CSV - D001E]
    if (isset($_POST['action']) && $_POST['action'] === 'export_csv_mel') {
        
        $where = ["1=1"];
        if (!empty($_POST['f_tit'])) { $ft = cleanInputMel($_POST['f_tit']); $where[] = "T1.D001E_Titulo LIKE '%$ft%'"; }
        if (!empty($_POST['f_sku'])) { $fs = cleanInputMel($_POST['f_sku']); $where[] = "T1.D001E_D001_Codigo_Produto LIKE '%$fs%'"; }
        if (!empty($_POST['f_mar'])) { $fm = cleanInputMel($_POST['f_mar']); $where[] = "T1.D001E_Marca LIKE '%$fm%'"; }
        if (!empty($_POST['f_desc'])) { $fd = cleanInputMel($_POST['f_desc']); $where[] = "T1.D001E_Descricao LIKE '%$fd%'"; }
        if (!empty($_POST['f_spec'])) { $fsp = cleanInputMel($_POST['f_spec']); $where[] = "(T1.D001E_EAN LIKE '%$fsp%' OR T1.D001E_garantia LIKE '%$fsp%' OR T1.D001E_peso LIKE '%$fsp%')"; }

        if (isset($_POST['f_est_liq']) && $_POST['f_est_liq'] !== '') { $val = (int)$_POST['f_est_liq']; $where[] = "T2.D009_Quantidade_Estoque_Liquido = $val"; }
        if (isset($_POST['f_est_tab']) && $_POST['f_est_tab'] !== '') { $val = (int)$_POST['f_est_tab']; $where[] = "T2.D009_Quantidade_Estoque_Tabela = $val"; }
        if (!empty($_POST['f_freq'])) { $val = cleanInputMel($_POST['f_freq']); $where[] = "T2.D009_Frequencia_Venda LIKE '%$val%'"; }
        if (!empty($_POST['f_custo'])) { $val = (float)str_replace(',', '.', $_POST['f_custo']); $where[] = "T2.D009_Valor_Custo_Unitario = $val"; }

        if (!empty($_POST['f_sco'])) { $v = (int)$_POST['f_sco']; if($v>0) $where[] = "T1.D001E_Status_Pontuacao = $v"; }
        if (!empty($_POST['f_sc_tit'])) { $v = (int)$_POST['f_sc_tit']; if($v>0) $where[] = "T1.D001E_pont_titulo = $v"; }
        if (!empty($_POST['f_sc_desc'])) { $v = (int)$_POST['f_sc_desc']; if($v>0) $where[] = "T1.D001E_pont_desc = $v"; }
        if (!empty($_POST['f_sc_img'])) { $v = (int)$_POST['f_sc_img']; if($v>0) $where[] = "T1.D001E_pont_img = $v"; }
        if (!empty($_POST['f_sc_spec'])) { $v = (int)$_POST['f_sc_spec']; if($v>0) $where[] = "T1.D001E_pont_espec = $v"; }

        $whereStr = implode(" AND ", $where);

        $sqlCsv = "SELECT T1.* FROM D001E AS T1
                   LEFT JOIN D049 ON D049.D049_D001_Id = T1.D001E_D001_Id
                   LEFT JOIN D009 AS T2 ON (T2.D009_D049_Id = D049.D049_Id AND T2.D009_C004_Id = $C004_Id)
                   WHERE $whereStr
                   GROUP BY T1.D001E_Id
                   ORDER BY T1.D001E_Id ASC";

        $rsCsv = mysql_query($sqlCsv);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=produtos_quality_'.date('YmdHis').'.csv');

        $out = fopen('php://output', 'w');
        fputs($out, "\xEF\xBB\xBF");

        $header = [
            'D001E_Id','D001E_D001_Id','D001E_D001_Codigo_Produto','D001E_Titulo','D001E_Marca',
            'D001E_Descricao','D001E_Imagem_1','D001E_Imagem_2','D001E_Imagem_3','D001E_Imagem_4',
            'D001E_Imagem_5','D001E_Imagem_6','D001E_Imagem_7','D001E_Imagem_8','D001E_Imagem_9',
            'D001E_Imagem_10','D001E_Status_Pontuacao','D001E_EAN','D001E_garantia','D001E_peso',
            'D001E_alturavarchar','D001E_larguravarchar','D001E_comprimentovarchar','D001E_ult_att',
            'D001E_pont_titulo','D001E_pont_desc','D001E_pont_img','D001E_pont_espec'
        ];
        fputcsv($out, $header, ';');

        function simpleCleanMel($str) {
            if(is_null($str)) return '';
            $str = str_replace(["\r\n", "\r", "\n", "\t"], ' ', $str);
            return trim($str);
        }

        if ($rsCsv) {
            while ($row = mysql_fetch_assoc($rsCsv)) {
                $line = [
                    $row['D001E_Id'],
                    $row['D001E_D001_Id'],
                    simpleCleanMel($row['D001E_D001_Codigo_Produto']),
                    simpleCleanMel($row['D001E_Titulo']),
                    simpleCleanMel($row['D001E_Marca']),
                    simpleCleanMel($row['D001E_Descricao']),
                    $row['D001E_Imagem_1'], $row['D001E_Imagem_2'], $row['D001E_Imagem_3'], $row['D001E_Imagem_4'], $row['D001E_Imagem_5'],
                    $row['D001E_Imagem_6'], $row['D001E_Imagem_7'], $row['D001E_Imagem_8'], $row['D001E_Imagem_9'], $row['D001E_Imagem_10'],
                    $row['D001E_Status_Pontuacao'],
                    simpleCleanMel($row['D001E_EAN']),
                    simpleCleanMel($row['D001E_garantia']),
                    simpleCleanMel($row['D001E_peso']),
                    simpleCleanMel($row['D001E_altura']), 
                    simpleCleanMel($row['D001E_largura']), 
                    simpleCleanMel($row['D001E_comprimento']), 
                    $row['D001E_ult_att'],
                    $row['D001E_pont_titulo'],
                    $row['D001E_pont_desc'],
                    $row['D001E_pont_img'],
                    $row['D001E_pont_espec']
                ];
                fputcsv($out, $line, ';');
            }
        }
        fclose($out);
        exit;
    }

    header('Content-Type: application/json; charset=UTF-8');

    if (isset($_POST['action']) && $_POST['action'] === 'get_details_mel') {
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
                $marca = extrairMarcaJsonAnyMarketMel($row['D001E_Json_Nativo']);
            }
            $resT = analiseTituloMel($row['D001E_Titulo']);
            $resD = analiseDescricaoMel($row['D001E_Descricao']);
            $resI = analiseImagensMel($row);
            $resA = analiseAtributosMel($row);
            $imgs = [];
            for ($i = 1; $i <= 10; $i++) if (!empty($row["D001E_Imagem_$i"])) $imgs[] = $row["D001E_Imagem_$i"];
            if (empty($imgs)) $imgs[] = "https://via.placeholder.com/600x600?text=Sem+Imagem";
            $specs = [
                'EAN' => $row['D001E_EAN'] ?? '', 'Garantia' => $row['D001E_garantia'] ?? '',
                'Peso' => $row['D001E_peso'] ? $row['D001E_peso'] . ' kg' : '',
                'Altura' => $row['D001E_altura'] ? $row['D001E_altura'] . ' cm' : '',
                'Largura' => $row['D001E_largura'] ? $row['D001E_largura'] . ' cm' : '',
                'Comprimento' => $row['D001E_comprimento'] ? $row['D001E_comprimento'] . ' cm' : '',
            ];
            echo json_encode(['ok' => 1, 'titulo' => $row['D001E_Titulo'], 'sku' => $row['D001E_D001_Codigo_Produto'], 'marca' => $marca, 'desc' => $row['D001E_Descricao'], 'imgs' => $imgs, 'specs' => $specs,
                'scores' => ['tit' => $resT['nota'], 'desc' => $resD['nota'], 'img' => $resI['nota'], 'attr' => $resA['nota']]
            ]);
        } else { echo json_encode(['ok' => 0, 'msg' => 'Produto não encontrado']); }
        exit;
    }

    $where = ["1=1"];
    if (!empty($_POST['f_tit'])) { $ft = cleanInputMel($_POST['f_tit']); $where[] = "T1.D001E_Titulo LIKE '%$ft%'"; }
    if (!empty($_POST['f_sku'])) { $fs = cleanInputMel($_POST['f_sku']); $where[] = "T1.D001E_D001_Codigo_Produto LIKE '%$fs%'"; }
    if (!empty($_POST['f_mar'])) { $fm = cleanInputMel($_POST['f_mar']); $where[] = "T1.D001E_Marca LIKE '%$fm%'"; }
    if (!empty($_POST['f_desc'])) { $fd = cleanInputMel($_POST['f_desc']); $where[] = "T1.D001E_Descricao LIKE '%$fd%'"; }
    if (!empty($_POST['f_spec'])) { $fsp = cleanInputMel($_POST['f_spec']); $where[] = "(T1.D001E_EAN LIKE '%$fsp%' OR T1.D001E_garantia LIKE '%$fsp%' OR T1.D001E_peso LIKE '%$fsp%')"; }
    if (isset($_POST['f_est_liq']) && $_POST['f_est_liq'] !== '') { $val = (int)$_POST['f_est_liq']; $where[] = "T2.D009_Quantidade_Estoque_Liquido = $val"; }
    if (isset($_POST['f_est_tab']) && $_POST['f_est_tab'] !== '') { $val = (int)$_POST['f_est_tab']; $where[] = "T2.D009_Quantidade_Estoque_Tabela = $val"; }
    if (!empty($_POST['f_freq'])) { $val = cleanInputMel($_POST['f_freq']); $where[] = "T2.D009_Frequencia_Venda LIKE '%$val%'"; }
    if (!empty($_POST['f_custo'])) { $val = (float)str_replace(',', '.', $_POST['f_custo']); $where[] = "T2.D009_Valor_Custo_Unitario = $val"; }
    if (!empty($_POST['f_sco'])) { $v = (int)$_POST['f_sco']; if($v>0) $where[] = "T1.D001E_Status_Pontuacao = $v"; }
    if (!empty($_POST['f_sc_tit'])) { $v = (int)$_POST['f_sc_tit']; if($v>0) $where[] = "T1.D001E_pont_titulo = $v"; }
    if (!empty($_POST['f_sc_desc'])) { $v = (int)$_POST['f_sc_desc']; if($v>0) $where[] = "T1.D001E_pont_desc = $v"; }
    if (!empty($_POST['f_sc_img'])) { $v = (int)$_POST['f_sc_img']; if($v>0) $where[] = "T1.D001E_pont_img = $v"; }
    if (!empty($_POST['f_sc_spec'])) { $v = (int)$_POST['f_sc_spec']; if($v>0) $where[] = "T1.D001E_pont_espec = $v"; }

    $whereStr = implode(" AND ", $where);
    $totalRows = 0;
    $sqlCount = "SELECT COUNT(*) AS total FROM D001E AS T1 LEFT JOIN D049 ON D049.D049_D001_Id = T1.D001E_D001_Id LEFT JOIN D009 AS T2 ON (T2.D009_D049_Id = D049.D049_Id AND T2.D009_C004_Id = $C004_Id) WHERE $whereStr";
    $rsCount = mysql_query($sqlCount);
    if ($rsCount) { $r = mysql_fetch_assoc($rsCount); $totalRows = (int) ($r['total'] ?? 0); }

    $sql = "SELECT T1.*, T2.D009_Frequencia_Venda, T2.D009_Valor_Custo_Unitario, T2.D009_Quantidade_Estoque_Tabela, T2.D009_Quantidade_Estoque_Liquido
            FROM D001E AS T1 LEFT JOIN D049 ON D049.D049_D001_Id = T1.D001E_D001_Id LEFT JOIN D009 AS T2 ON (T2.D009_D049_Id = D049.D049_Id AND T2.D009_C004_Id = $C004_Id)
            WHERE $whereStr GROUP BY T1.D001E_Id ORDER BY T1.D001E_Id ASC LIMIT $limit OFFSET $offset";
    $rs = mysql_query($sql);
    $html = "";
    if ($rs) { while ($row = mysql_fetch_assoc($rs)) { $html .= renderQualityRowMel($row); } }

    echo json_encode(['ok' => 1, 'total' => $totalRows, 'page' => $page, 'pageSize' => $limit, 'html' => $html]);
    exit;
}

// =============================================================================
// [STYLE] CSS
// =============================================================================
$style = <<<STYLE
<style>
    :root { --bg-body: #f3f4f6; --card-bg: #ffffff; --text-color: #1f2937; --border-color: #e5e7eb; --score-6: #0098D3; --score-5: #10b981; --score-4: #84cc16; --score-3: #eab308; --score-2: #fca5a5; --score-1: #ef4444; --primary: #0098D3; }
    body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif; background-color: var(--bg-body); margin: 0; padding: 20px; color: var(--text-color); }
    .quality-list { max-width: 1600px; margin: 0 auto; margin-top: 20px; }
    
    .filter-container { background: #fff; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); padding: 16px; margin-bottom: 20px; max-width: 1600px; margin: 0 auto 20px auto; border: 1px solid #e5e7eb; }
    .filter-header { display: flex; justify-content: space-between; align-items: center; cursor: pointer; user-select: none; }
    .filter-title { font-size: 14px; font-weight: 700; color: #374151; display:flex; align-items:center; gap:8px; text-transform:uppercase; letter-spacing:0.05em; }
    .filter-icon { color: var(--primary); font-size: 20px; }
    .filter-chevron { transition: transform 0.2s; color: #9ca3af; }
    .filter-body { display: block; margin-top: 15px; border-top: 1px solid #f3f4f6; padding-top: 15px; }
    .filter-body.closed { display: none; }
    .filter-header.closed .filter-chevron { transform: rotate(-90deg); }
    
    .f-grid { display: grid; grid-template-columns: repeat(5, 1fr); gap: 10px; }
    .f-group { display: flex; flex-direction: column; gap: 4px; }
    .f-label { font-size: 11px; font-weight: 600; color: #6b7280; text-transform: uppercase; }
    .f-input { padding: 8px 10px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 12px; outline: none; transition: all 0.2s; width: 100%; }
    .f-input:focus { border-color: var(--primary); box-shadow: 0 0 0 2px rgba(0, 152, 211, 0.15); }
    .f-actions { grid-column: 1 / -1; display: flex; justify-content: flex-end; margin-top: 10px; padding-top: 10px; border-top: 1px solid #f3f4f6; gap: 10px; }
    
    .f-btn-apply { background: var(--primary); color: #fff; border: none; padding: 10px 24px; border-radius: 6px; font-weight: 700; cursor: pointer; font-size: 13px; display:flex; align-items:center; gap:6px; transition: background 0.2s; }
    .f-btn-apply:hover { background: #007bb5; }
    
    .f-btn-export { background: #10b981; color: #fff; border: none; padding: 10px 24px; border-radius: 6px; font-weight: 700; cursor: pointer; font-size: 13px; display:flex; align-items:center; gap:6px; transition: background 0.2s; }
    .f-btn-export:hover { background: #059669; }

    .f-btn-send { background: #f59e0b; color: #fff; border: none; padding: 10px 24px; border-radius: 6px; font-weight: 700; cursor: pointer; font-size: 13px; display:flex; align-items:center; gap:6px; transition: background 0.2s; }
    .f-btn-send:hover { background: #d97706; }
    
    .f-btn-send-single { background: #f59e0b; color: #fff; border: none; padding: 6px 12px; border-radius: 6px; cursor: pointer; transition: 0.2s; }
    .f-btn-send-single:hover { background: #d97706; }

    /* --- LAYOUT GRID ISOLADO (_mel) --- */
    .quality-header-mel, .quality-row-mel { 
        display: grid; 
        grid-template-columns: 30px 70px 1.4fr 1fr 1.2fr 0.8fr 40px 40px 40px 40px 70px 60px; 
        gap: 8px; 
        align-items: center; 
    }
    
    .quality-header-mel { 
        position: sticky; top: 0; z-index: 50; 
        background: #f9fafb; border-bottom: 2px solid #e5e7eb;
        padding: 12px 16px; 
        font-size: 11px; font-weight: 700; color: #6b7280; text-transform: uppercase; letter-spacing: 0.03em; 
        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    }
    
    .quality-header-mel > div { display: flex; align-items: center; justify-content: center; text-align: center; }
    .quality-header-mel > div:nth-child(3), .quality-header-mel > div:nth-child(5), .quality-header-mel > div:nth-child(6) { justify-content: flex-start; text-align: left; }
    
    .quality-row-mel { background: var(--card-bg); border-radius: 10px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); padding: 14px 16px; margin-bottom: 10px; border: 1px solid transparent; transition: all 0.2s; }
    .quality-row-mel:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.1); border-color: #d1d5db; }
    
    .thumb-box { width: 64px; height: 64px; border-radius: 8px; border: 1px solid #e5e7eb; padding: 3px; background: #fff; cursor: pointer; display: flex; align-items: center; justify-content: center; }
    .thumb-box img { width: 100%; height: 100%; object-fit: contain; }
    .col-info { display: flex; flex-direction: column; gap: 4px; overflow: hidden; }
    .prod-title { font-size: 13px; font-weight: 600; color: #111827; line-height: 1.3; }
    .prod-sub { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
    .prod-sku { font-size: 10px; color: #4b5563; background: #f3f4f6; padding: 2px 6px; border-radius: 4px; font-family: monospace; border: 1px solid #e5e7eb; }
    .prod-brand { font-size: 10px; font-weight: 700; color: var(--primary); white-space: nowrap; }
    .col-metrics { display: grid; grid-template-columns: 1fr 1fr; gap: 4px 10px; background: #f9fafb; padding: 6px 10px; border-radius: 8px; border: 1px solid #f3f4f6; }
    .metric-cell { display: flex; justify-content: space-between; align-items: center; font-size: 11px; }
    .metric-cell .lbl { color: #9ca3af; font-size: 10px; font-weight: 600; text-transform: uppercase; margin-right: 4px; }
    .metric-cell .val { color: #374151; }
    .col-box-scroll { font-size: 11px; color: #4b5563; max-height: 64px; overflow-y: auto; background: #fff; padding: 4px; line-height: 1.4; border-radius: 4px; border: 1px solid #f3f4f6; }
    .col-box-scroll::-webkit-scrollbar { width: 3px; }
    .col-box-scroll::-webkit-scrollbar-thumb { background: #d1d5db; }
    .mini-score-box { display: flex; flex-direction: column; align-items: center; cursor: help; }
    .mini-score-val { width: 28px; height: 28px; border-radius: 6px; background: #e5e7eb; color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 12px; }
    .col-score { display: flex; flex-direction: column; align-items: center; justify-content: center; }
    .score-circle { position: relative; width: 44px; height: 44px; border-radius: 50%; background: conic-gradient(var(--color) calc(var(--percent) * 1%), #e5e7eb 0); display: flex; align-items: center; justify-content: center; margin-bottom: 2px; }
    .score-circle::before { content: ""; position: absolute; width: 34px; height: 34px; border-radius: 50%; background: #ffffff; }
    .score-number { position: relative; font-size: 14px; font-weight: 800; z-index: 1; color: #111827; }
    .score-label { font-size: 9px; font-weight: 700; color: #6b7280; text-transform: uppercase; }
    
    #hardness-custom-tooltip { background: #ffffff; border: 1px solid #e4e6eb; box-shadow: 0 8px 20px rgba(0,0,0,0.15); border-radius: 8px; padding: 0; z-index: 999999; font-size: 12px; color: #111827; min-width: 230px; }
    .tt-table { width: 100%; border-collapse: collapse; }
    .tt-head { background: #f3f4f6; padding: 8px 12px; font-weight: 700; font-size: 11px; text-transform: uppercase; text-align: left; color: #4b5563; }
    .tt-row { border-bottom: 1px solid #f3f4f6; padding: 6px 12px; color: #6b7280; font-size: 11px; }
    .tt-val { border-bottom: 1px solid #f3f4f6; padding: 6px 12px; color: #111827; text-align: right; font-weight: 700; font-size: 11px; }
    .tt-foot td { background: #f0f9ff; font-weight: 800; color: var(--primary); padding: 8px 12px; }
    .header-tooltip-content { padding: 10px; }
    .header-tooltip-title { font-weight: 700; color: var(--primary); border-bottom: 1px solid #e5e7eb; padding-bottom: 6px; margin-bottom: 6px; font-size: 11px; text-transform: uppercase; }
    .header-rule-row { display: flex; justify-content: space-between; font-size: 11px; margin-bottom: 3px; color: #4b5563; }
    .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); z-index: 9999; justify-content: center; align-items: center; backdrop-filter: blur(3px); padding: 20px; }
    .modal-content { background: #fff; width: 100%; max-width: 1100px; height: 90%; border-radius: 12px; position: relative; display: flex; overflow: hidden; box-shadow: 0 20px 40px rgba(0,0,0,0.2); }
    .close-modal { position: absolute; top: 10px; right: 15px; font-size: 24px; cursor: pointer; z-index: 100; color: #9ca3af; }
    .close-modal:hover { color: #333; }
    .vis-thumbs { width: 100px; background: #f9fafb; padding: 10px; overflow-y: auto; display: flex; flex-direction: column; gap: 8px; border-right: 1px solid #e5e7eb; }
    .vis-mini { width: 100%; height: 70px; object-fit: contain; border: 2px solid transparent; border-radius: 6px; cursor: pointer; background: #fff; border: 1px solid #f1f1f1; }
    .vis-mini.active { border-color: var(--primary); }
    .vis-main { flex: 1; display: flex; justify-content: center; align-items: center; background: #fff; padding: 20px; position: relative; }
    .vis-main img { max-width: 100%; max-height: 100%; object-fit: contain; }
    .vis-score-badge { position: absolute; top: 15px; left: 15px; padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 700; color: #fff; background: var(--primary); z-index: 10; }
    .vis-info { width: 350px; border-left: 1px solid #e5e7eb; padding: 20px; overflow-y: auto; background: #fff; display: flex; flex-direction: column; gap: 15px; }
    .vis-h1 { font-size: 18px; font-weight: 700; margin: 0; color: #111827; line-height: 1.3; }
    .vis-chip { padding: 2px 8px; border-radius: 12px; font-size: 10px; font-weight: 700; color: #fff; background: var(--primary); display: inline-block; vertical-align: middle; margin-left: 6px; }
    .vis-meta { font-size: 12px; color: #6b7280; padding-bottom: 10px; border-bottom: 1px solid #e5e7eb; }
    .vis-btn-print { width: 100%; padding: 10px; background: #1f2937; color: #fff; border: none; border-radius: 6px; font-size: 12px; font-weight: 600; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 6px; }
    .vis-header-row { display: flex; justify-content: space-between; align-items: center; margin-bottom: 5px; font-weight: 700; font-size: 12px; color: #111827; }
    .vis-desc-box { font-size: 12px; line-height: 1.5; color: #4b5563; background: #f9fafb; padding: 10px; border-radius: 6px; border: 1px solid #e5e7eb; max-height: 150px; overflow-y: auto; }
    .vis-specs-table td { padding: 4px 0; border-bottom: 1px solid #f3f4f6; color: #4b5563; font-size: 12px; }
    #demoMel { padding: 20px 0; display:none; flex-wrap:wrap; align-items:center; justify-content:center; gap:5px; }
    #demoMel.active { display: flex; }
    #demoMel .pg-btn { border: 1px solid #d1d5db; background:#fff; padding:6px 12px; border-radius:6px; cursor:pointer; font-size:12px; font-weight:600; color:#374151; }
    #demoMel .pg-btn:hover { background: #f3f4f6; }
    #demoMel .pg-btn.active { background: var(--primary); border-color: var(--primary); color:#fff; }
    
    @media (max-width: 1200px) { 
        .f-grid { grid-template-columns: repeat(4, 1fr); }
        .quality-header-mel, .quality-row-mel { grid-template-columns: 30px 60px 1.4fr 160px 1.5fr 1fr 60px; gap: 8px; }
        .col-metrics { grid-template-columns: 1fr; gap: 2px; }
    }
</style>
<link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
STYLE;
if (!$apiMode) echo $style;

$tipTit  = "<div class='header-tooltip-content'><div class='header-tooltip-title'>REGRAS: TÍTULO (Peso 3)</div><div class='header-rule-row'><span>< 30 chars</span><span style='color:#ef4444'>1</span></div><div class='header-rule-row'><span>30 a 39 chars</span><span style='color:#eab308'>2</span></div><div class='header-rule-row'><span>40 a 49 chars</span><span style='color:#10b981'>3</span></div><div class='header-rule-row'><span>50 a 59 chars</span><span style='color:#10b981'>4</span></div><div class='header-rule-row'><span>60 a 89 chars</span><span style='color:#0098D3'>5</span></div></div>";
$tipDesc = "<div class='header-tooltip-content'><div class='header-tooltip-title'>REGRAS: DESCRIÇÃO (Peso 3)</div><div class='header-rule-row'><span>< 200 chars</span><span style='color:#ef4444'>1</span></div><div class='header-rule-row'><span>200 a 399</span><span style='color:#eab308'>2</span></div><div class='header-rule-row'><span>400 a 599</span><span style='color:#10b981'>3</span></div><div class='header-rule-row'><span>600 a 1999</span><span style='color:#10b981'>4</span></div><div class='header-rule-row'><span>2000 a 4000</span><span style='color:#0098D3'>5</span></div></div>";
$tipImg  = "<div class='header-tooltip-content'><div class='header-tooltip-title'>REGRAS: IMAGENS (Peso 3)</div><div class='header-rule-row'><span>< 2 imgs</span><span style='color:#ef4444'>1</span></div><div class='header-rule-row'><span>2 imgs</span><span style='color:#eab308'>3</span></div><div class='header-rule-row'><span>3 a 4 imgs</span><span style='color:#10b981'>4</span></div><div class='header-rule-row'><span>5 a 10 imgs</span><span style='color:#0098D3'>5</span></div><div class='header-rule-row'><span>> 10 imgs</span><span style='color:#ef4444'>0</span></div></div>";
$tipSpec = "<div class='header-tooltip-content'><div class='header-tooltip-title'>REGRAS: ATRIBUTOS (Peso 1)</div><div class='header-rule-row'><span>< 2 itens</span><span style='color:#ef4444'>1</span></div><div class='header-rule-row'><span>2 a 3 itens</span><span style='color:#eab308'>3</span></div><div class='header-rule-row'><span>4 a 6 itens</span><span style='color:#10b981'>4</span></div><div class='header-rule-row'><span>7 a 19 itens</span><span style='color:#0098D3'>5</span></div></div>";

// IDs com SUFIXO _mel
echo "
<div class='filter-container'>
    <div class='filter-header' onclick='toggleFilterBodyMel()'>
        <div class='filter-title'><i class='material-icons filter-icon'>tune</i> Filtros Avançados</div>
        <i class='material-icons filter-chevron' id='filterChevronMel'>expand_more</i>
    </div>
    <div class='filter-body' id='filterBodyMel'>
        <div class='f-grid'>
            <div class='f-group'><label class='f-label'>Título</label><input type='text' id='f_tit_mel' class='f-input' placeholder='Ex: Parafusadeira'></div>
            <div class='f-group'><label class='f-label'>SKU / Cód</label><input type='text' id='f_sku_mel' class='f-input' placeholder='Ex: 12345'></div>
            <div class='f-group'><label class='f-label'>Marca</label><input type='text' id='f_mar_mel' class='f-input' placeholder='Ex: Makita'></div>
            <div class='f-group'><label class='f-label'>Descrição</label><input type='text' id='f_desc_mel' class='f-input' placeholder='Contém...'></div>
            <div class='f-group'><label class='f-label'>Specs/EAN</label><input type='text' id='f_spec_mel' class='f-input' placeholder='Contém...'></div>
            <div class='f-group'><label class='f-label'>Est. Líquido (=)</label><input type='number' id='f_est_liq_mel' class='f-input' placeholder='Exato'></div>
            <div class='f-group'><label class='f-label'>Est. Tabela (=)</label><input type='number' id='f_est_tab_mel' class='f-input' placeholder='Exato'></div>
            <div class='f-group'><label class='f-label'>Frequência</label><input type='text' id='f_freq_mel' class='f-input' placeholder='Ex: 1'></div>
            <div class='f-group'><label class='f-label'>Custo (=)</label><input type='text' id='f_custo_mel' class='f-input' placeholder='Ex: 10,90'></div>
            <div class='f-group'><label class='f-label'>Nota Geral</label><select id='f_sco_mel' class='f-input'><option value='0'>Todas</option><option value='6'>6 - Ótima</option><option value='5'>5 - M.Boa</option><option value='4'>4 - Boa</option><option value='3'>3 - Média</option><option value='2'>2 - Ruim</option><option value='1'>1 - M.Ruim</option></select></div>
            <div class='f-group'><label class='f-label'>Nota Título</label><select id='f_sc_tit_mel' class='f-input'><option value='0'>Todas</option><option value='1'>1</option><option value='2'>2</option><option value='3'>3</option><option value='4'>4</option><option value='5'>5</option></select></div>
            <div class='f-group'><label class='f-label'>Nota Desc</label><select id='f_sc_desc_mel' class='f-input'><option value='0'>Todas</option><option value='1'>1</option><option value='2'>2</option><option value='3'>3</option><option value='4'>4</option><option value='5'>5</option></select></div>
            <div class='f-group'><label class='f-label'>Nota Img</label><select id='f_sc_img_mel' class='f-input'><option value='0'>Todas</option><option value='1'>1</option><option value='3'>3</option><option value='4'>4</option><option value='5'>5</option></select></div>
            <div class='f-group'><label class='f-label'>Nota Spec</label><select id='f_sc_spec_mel' class='f-input'><option value='0'>Todas</option><option value='1'>1</option><option value='3'>3</option><option value='4'>4</option><option value='5'>5</option></select></div>
        </div>
        <div class='f-actions'>
            <button class='f-btn-apply' onclick='applyFiltersMel()'><i class='material-icons'>search</i> Aplicar Filtros</button>
            <button class='f-btn-export' onclick='exportCSVMel()'><i class='material-icons'>file_download</i> Exportar CSV</button>
            <button class='f-btn-send' onclick='enviarCorrecaoMassaMel()'><i class='material-icons'>playlist_add_check</i> Enviar Selecionados</button>
        </div>
    </div>
</div>";

echo "<div class='quality-list'>";
// NOTE HEADER FIXO com layout de 9 colunas e class _mel
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

echo "<div id='contentMel'><div class='start-msg'><i class='material-icons'>tune</i><h2>Comece sua análise</h2><p>Utilize os filtros acima para buscar os produtos.</p></div></div>";

$ajaxUrl  = isset($_SERVER['REQUEST_URI']) ? htmlspecialchars($_SERVER['REQUEST_URI'], ENT_QUOTES, 'UTF-8') : '';
$sysDivId = isset($g['divId']) ? $g['divId'] : 'contentMel';
// SUFIXO _mel nos Inputs Hidden
echo "<input type='hidden' id='hardness_total_mel' value='0'>";
echo "<input type='hidden' id='hardness_pageSize_mel' value='" . (int) $limit . "'>";
echo "<input type='hidden' id='hardness_ajaxUrl_mel' value='" . $ajaxUrl . "'>";
echo "<input type='hidden' id='sys_base_divId_mel' value='" . $sysDivId . "'>";
echo "<div id='demoMel'></div></div>";
?>

<div id="modalVisMel" class="modal-overlay" onclick="if(event.target==this) fecharVisMel()">
    <div class="modal-content printable-area">
        <span class="close-modal" onclick="fecharVisMel()">×</span>
        <div class="vis-thumbs" id="visThumbsMel"></div>
        <div class="vis-main"><span class="vis-score-badge" id="visImgScoreMel">--</span><img id="visHeroMel" src=""></div>
        <div class="vis-info">
            <h1 class="vis-h1"><span id="visTitleMel">--</span><span class="vis-chip" id="visTitleScoreMel">--</span></h1>
            <div class="vis-meta">SKU: <strong id="visSkuMel">--</strong> | Marca: <strong id="visBrandMel">--</strong></div>
            <button class="vis-btn-print" onclick="imprimirConteudoModalMel()"><i class="material-icons">print</i> Imprimir Ficha Técnica</button>
            <div class="vis-header-row"><span>Descrição do Produto</span><span class="vis-chip" id="visDescScoreMel">--</span></div>
            <div class="vis-desc-box" id="visDescMel"></div>
            <div class="vis-specs-container"><div class="vis-header-row" style="margin-top:15px"><span>Especificações</span><span class="vis-chip" id="visAttrScoreMel">--</span></div><div id="visSpecsContentMel"></div></div>
        </div>
    </div>
</div>

<script>
    function toggleFilterBodyMel() {
        var b = document.getElementById('filterBodyMel');
        var c = document.getElementById('filterChevronMel');
        if (b.classList.contains('closed')) { b.classList.remove('closed'); c.style.transform = 'rotate(0deg)'; } else { b.classList.add('closed'); c.style.transform = 'rotate(-90deg)'; }
    }
    
    // TOOLTIP SCRIPT (Unico e Global - Verifica se já existe)
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
        getFilters: function() {
            // MAP: ID _mel -> $_POST['f_...']
            return {
                f_tit: jQuery('#f_tit_mel').val(),
                f_sku: jQuery('#f_sku_mel').val(),
                f_mar: jQuery('#f_mar_mel').val(),
                f_desc: jQuery('#f_desc_mel').val(),
                f_spec: jQuery('#f_spec_mel').val(),
                f_est_liq: jQuery('#f_est_liq_mel').val(),
                f_est_tab: jQuery('#f_est_tab_mel').val(),
                f_freq: jQuery('#f_freq_mel').val(),
                f_custo: jQuery('#f_custo_mel').val(),
                f_sco: jQuery('#f_sco_mel').val(),
                f_sc_tit: jQuery('#f_sc_tit_mel').val(),
                f_sc_desc: jQuery('#f_sc_desc_mel').val(),
                f_sc_img: jQuery('#f_sc_img_mel').val(),
                f_sc_spec: jQuery('#f_sc_spec_mel').val()
            };
        },
        loadData: function(p) {
            var pageSizeVal = jQuery('#hardness_pageSize_mel').val();
            var urlVal      = jQuery('#hardness_ajaxUrl_mel').val();
            var sysIdVal    = jQuery('#sys_base_divId_mel').val();

            p = parseInt(p, 10) || 1; 
            var filters = this.getFilters(); 
            var size = parseInt(pageSizeVal, 10) || 50; 
            
            if (sysIdVal && jQuery('#' + sysIdVal).length) jQuery('#' + sysIdVal).showLoading();
            
            jQuery.ajax({ 
                url: urlVal, 
                type: 'POST', 
                dataType: 'json', 
                data: jQuery.extend({ ajax: 1, page: p, pageSize: size }, filters), 
                success: function (r) { 
                    if (r && r.ok) { 
                        jQuery('#contentMel').html(r.html); 
                        pagerMel.render('demoMel', r.total, p, size, 'appMel.loadData'); 
                    } else { 
                        jQuery('#contentMel').html('<div class="start-msg">Sem resultados</div>'); 
                        jQuery('#demoMel').removeClass('active').html(''); 
                    } 
                }, 
                complete: function () { 
                    if (sysIdVal && jQuery('#' + sysIdVal).length) jQuery('#' + sysIdVal).hideLoading(); 
                } 
            });
        }
    };
    
    function applyFiltersMel() { appMel.loadData(1); }
    
    function exportCSVMel() {
        var filters = appMel.getFilters(); 
        var url = jQuery('#hardness_ajaxUrl_mel').val();
        var form = document.createElement('form'); form.method = 'POST'; form.action = url; form.target = '_blank';
        var i1 = document.createElement('input'); i1.name = 'ajax'; i1.value = '1'; form.appendChild(i1);
        var i2 = document.createElement('input'); i2.name = 'action'; i2.value = 'export_csv_mel'; form.appendChild(i2);
        for (var key in filters) { if (filters.hasOwnProperty(key)) { var inp = document.createElement('input'); inp.name = key; inp.value = filters[key]; form.appendChild(inp); } }
        document.body.appendChild(form); form.submit(); document.body.removeChild(form);
    }
    
    function enviarCorrecaoSingleMel(id) {
        if(confirm('Deseja enviar este produto para a lista de Correção?')) {
            enviarAjaxCorrecaoMel([id]);
        }
    }
    
    function enviarCorrecaoMassaMel() {
        var ids = [];
        jQuery('.row-check:checked').each(function() { ids.push(jQuery(this).val()); });
        if (ids.length === 0) { alert('Selecione pelo menos um item.'); return; }
        if(confirm('Enviar ' + ids.length + ' produtos para a lista de Correção?')) {
            enviarAjaxCorrecaoMel(ids);
        }
    }
    
    function enviarAjaxCorrecaoMel(ids) {
        var url = jQuery('#hardness_ajaxUrl_mel').val();
        var sysIdVal = jQuery('#sys_base_divId_mel').val();

        if (sysIdVal && jQuery('#' + sysIdVal).length) jQuery('#' + sysIdVal).showLoading();
        
        jQuery.ajax({
            url: url, type: 'POST', dataType: 'json',
            data: { ajax: 1, action: 'send_correction_mel', ids: ids },
            success: function(res) {
                alert(res.msg || 'Processado.');
                jQuery('.row-check').prop('checked', false);
            },
            complete: function() { if (sysIdVal && jQuery('#' + sysIdVal).length) jQuery('#' + sysIdVal).hideLoading(); }
        });
    }

    var allSelectedMel = false;
    function toggleSelectAllMel() {
        allSelectedMel = !allSelectedMel;
        jQuery('.row-check').prop('checked', allSelectedMel);
    }

    const mVisMel = document.getElementById('modalVisMel'), vThumbsMel = document.getElementById('visThumbsMel'), vHeroMel = document.getElementById('visHeroMel'), vTitleMel = document.getElementById('visTitleMel'), vSkuMel = document.getElementById('visSkuMel'), vBrandMel = document.getElementById('visBrandMel'), vDescMel = document.getElementById('visDescMel'), vSpecsMel = document.getElementById('visSpecsContentMel');
    const elTSMel = document.getElementById('visTitleScoreMel'), elDSMel = document.getElementById('visDescScoreMel'), elISMel = document.getElementById('visImgScoreMel'), elASMel = document.getElementById('visAttrScoreMel');
    function getMetaNotaMel(n) { n = Number(n); if (n === 6) return { c: '#0098D3', t: 'Ótima' }; if (n === 5) return { c: '#10b981', t: 'Muito Boa' }; if (n === 4) return { c: '#84cc16', t: 'Boa' }; if (n === 3) return { c: '#eab308', t: 'Média' }; if (n === 2) return { c: '#fca5a5', t: 'Ruim' }; return { c: '#ef4444', t: 'Muito Ruim' }; }
    
    function abrirVisualizadorMel(sku) {
        var url = document.getElementById('hardness_ajaxUrl_mel').value; 
        var sysIdVal = document.getElementById('sys_base_divId_mel').value;
        if (sysIdVal && typeof jQuery !== 'undefined' && jQuery('#' + sysIdVal).length) jQuery('#' + sysIdVal).showLoading();
        
        jQuery.ajax({ url: url, type: 'POST', dataType: 'json', data: { ajax: 1, action: 'get_details_mel', sku: sku }, success: function (res) { if (res.ok) { vTitleMel.innerText = res.titulo; vSkuMel.innerText = res.sku; vBrandMel.innerText = res.marca; vDescMel.innerHTML = res.desc ? res.desc : '<em>Sem descrição.</em>'; const mT = getMetaNotaMel(res.scores.tit); elTSMel.style.backgroundColor = mT.c; elTSMel.innerText = res.scores.tit + ' - ' + mT.t; const mD = getMetaNotaMel(res.scores.desc); elDSMel.style.backgroundColor = mD.c; elDSMel.innerText = res.scores.desc + ' - ' + mD.t; const mI = getMetaNotaMel(res.scores.img); elISMel.style.backgroundColor = mI.c; elISMel.innerText = 'Fotos: ' + res.scores.img + ' (' + mI.t + ')'; const mA = getMetaNotaMel(res.scores.attr); elASMel.style.backgroundColor = mA.c; elASMel.innerText = res.scores.attr + ' - ' + mA.t; vThumbsMel.innerHTML = ''; if (res.imgs.length > 0) vHeroMel.src = res.imgs[0]; res.imgs.forEach((url, idx) => { let img = document.createElement('img'); img.src = url; img.className = 'vis-mini'; if (idx === 0) img.classList.add('active'); img.onclick = () => { vHeroMel.src = url; document.querySelectorAll('.vis-mini').forEach(el => el.classList.remove('active')); img.classList.add('active'); }; vThumbsMel.appendChild(img); }); let h = '<table class="vis-specs-table">'; let has = false; if (res.specs.EAN) { h += `<tr><td><strong>EAN:</strong> ${res.specs.EAN}</td></tr>`; has = true; } if (res.specs.Garantia) { h += `<tr><td><strong>Garantia:</strong> ${res.specs.Garantia}</td></tr>`; has = true; } if (res.specs.Peso) { h += `<tr><td><strong>Peso:</strong> ${res.specs.Peso}</td></tr>`; has = true; } if (res.specs.Altura) { h += `<tr><td><strong>Altura:</strong> ${res.specs.Altura}</td></tr>`; has = true; } if (res.specs.Largura) { h += `<tr><td><strong>Largura:</strong> ${res.specs.Largura}</td></tr>`; has = true; } if (res.specs.Comprimento) { h += `<tr><td><strong>Comp.:</strong> ${res.specs.Comprimento}</td></tr>`; has = true; } h += '</table>'; vSpecsMel.innerHTML = has ? h : '<div style="color:#999;font-size:12px">Vazio</div>'; mVisMel.style.display = 'flex'; } else { alert(res.msg || 'Erro ao carregar'); } }, error: function () { alert('Erro na comunicação'); }, complete: function () { if (sysIdVal && typeof jQuery !== 'undefined' && jQuery('#' + sysIdVal).length) jQuery('#' + sysIdVal).hideLoading(); } });
    }
    function fecharVisMel() { mVisMel.style.display = 'none'; }
    function imprimirConteudoModalMel() { const f = document.createElement('iframe'); f.style.display = 'none'; document.body.appendChild(f); const d = f.contentWindow.document; const s = vSpecsMel.innerHTML; const c = `<html><head><style>body{font-family:Arial,sans-serif;padding:20px;color:#333}h1{font-size:24px;margin-bottom:5px}.meta{color:#666;font-size:12px;margin-bottom:20px;border-bottom:1px solid #ccc;padding-bottom:10px}.hero{text-align:center;margin-bottom:20px}.hero img{max-width:300px;max-height:300px}.desc{font-size:12px;line-height:1.5;margin-bottom:20px; text-align:justify;}.specs-box{border:1px solid #eee;padding:10px;border-radius:5px}.specs-box table{width:100%;font-size:12px}.specs-box td{padding:4px 0}</style></head><body><h1>${vTitleMel.innerText}</h1><div class="meta">SKU: ${vSkuMel.innerText} | ${vBrandMel.innerText}</div><div class="hero"><img src="${vHeroMel.src}"></div><h3>Descrição</h3><div class="desc">${vDescMel.innerHTML}</div><h3>Specs</h3><div class="specs-box">${s}</div></body></html>`; d.open(); d.write(c); d.close(); setTimeout(() => { f.contentWindow.print(); setTimeout(() => document.body.removeChild(f), 1000); }, 200); }
    document.addEventListener('keydown', e => { if (e.key === "Escape") fecharVisMel() });
</script>