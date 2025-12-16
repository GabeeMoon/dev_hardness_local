<?php
/*
 PAINEL DE MELHORIA DE ANUNCIO
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

// INDICADOR VISUAL
if (!$isAjax) {
    echo "<div style='
            position: fixed; bottom: 15px; right: 15px;
            background: #fff; color: #333;
            padding: 8px 14px; border-radius: 8px;
            font-size: 12px; font-family: sans-serif; font-weight: 600;
            z-index: 999998; box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            border: 1px solid #ddd; pointer-events: none;
          '>
            🏢 Melhoria Anúncio: <span style='color: #0098D3; font-size:13px;'>ID {$C004_Id}</span>
          </div>";
}

// =============================================================================
// [FUNC] FUNÇÕES DE CÁLCULO (SCORE)
// =============================================================================
function extrairMarcaJsonAnyMarket($jsonString) {
    if (empty($jsonString)) return "";
    $obj = json_decode($jsonString);
    if (isset($obj->content[0]->brand->name)) return trim($obj->content[0]->brand->name);
    return "";
}
function analiseTitulo($titulo) {
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
function analiseDescricao($html) {
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
function analiseImagens($row) {
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
function analiseAtributos($row) {
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
function analiseImagemEspecial($row) {
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
function analiseVideo($row) {
    return ['nota' => 0, 'valor' => 'Sem Vídeo', 'regra' => 'Sem Short (0)', 'peso' => 1];
}
function getCorNota($n) {
    switch ($n) {
        case 6: return "#0098D3";
        case 5: return "#10b981";
        case 4: return "#84cc16";
        case 3: return "#eab308";
        case 2: return "#fca5a5";
        default: return "#ef4444";
    }
}
function gerarTooltipHtml($titulo, $arrAnalise) {
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
function renderQualityRow($row) {
    $marca       = isset($row['D001E_Marca']) ? $row['D001E_Marca'] : '';
    $updateMarca = false;
    if (empty($marca) && !empty($row['D001E_Json_Nativo'])) {
        $marcaExtraida = extrairMarcaJsonAnyMarket($row['D001E_Json_Nativo']);
        if (!empty($marcaExtraida)) {
            $marca       = $marcaExtraida;
            $updateMarca = true;
        } else {
            $marca = "ND";
        }
    }

    // SCORE
    $resT  = analiseTitulo($row['D001E_Titulo']);
    $resD  = analiseDescricao($row['D001E_Descricao']);
    $resI  = analiseImagens($row);
    $resA  = analiseAtributos($row);
    $resIE = analiseImagemEspecial($row);
    $resV  = analiseVideo($row);

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
    <div class='quality-row'>
        <div style='display:flex; align-items:center; justify-content:center;'>
             <input type='checkbox' class='row-check' value='$idProd' style='transform:scale(1.2); cursor:pointer;'>
        </div>
        
        <div class='thumb-box' onclick='abrirVisualizador(\"$sku\")'><img src='$imgCapa'></div>
        
        <div class='col-info'>
            <div class='prod-title'>{$row['D001E_Titulo']}</div>
            <div class='prod-sub'>
                <span class='prod-sku'>SKU: {$row['D001E_D001_Codigo_Produto']}</span>
                <span class='prod-brand' title='$marcaHtml'>$marcaHtml</span>
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
            <div class='mini-score-val' style='background:" . getCorNota($resT['nota']) . "'>{$resT['nota']}</div>
            <div class='tooltip-hidden-content' style='display:none'>" . gerarTooltipHtml("Título", $resT) . "</div>
        </div>
        <div class='mini-score-box' onmouseenter='window.showHTooltip(this)' onmousemove='window.moveHTooltip(event)' onmouseleave='window.hideHTooltip()'>
            <div class='mini-score-val' style='background:" . getCorNota($resD['nota']) . "'>{$resD['nota']}</div>
            <div class='tooltip-hidden-content' style='display:none'>" . gerarTooltipHtml("Descrição", $resD) . "</div>
        </div>
        <div class='mini-score-box' onmouseenter='window.showHTooltip(this)' onmousemove='window.moveHTooltip(event)' onmouseleave='window.hideHTooltip()'>
            <div class='mini-score-val' style='background:" . getCorNota($resI['nota']) . "'>{$resI['nota']}</div>
            <div class='tooltip-hidden-content' style='display:none'>" . gerarTooltipHtml("Imagens", $resI) . "</div>
        </div>
        <div class='mini-score-box' onmouseenter='window.showHTooltip(this)' onmousemove='window.moveHTooltip(event)' onmouseleave='window.hideHTooltip()'>
            <div class='mini-score-val' style='background:" . getCorNota($resA['nota']) . "'>{$resA['nota']}</div>
            <div class='tooltip-hidden-content' style='display:none'>" . gerarTooltipHtml("Atributos", $resA) . "</div>
        </div>
        
        <div class='col-score'>
            <div class='score-circle' style='--color:$c; --percent:$p;'><span class='score-number'>$final</span></div>
            <span class='score-label'>$l</span>
        </div>
        
        <div class='col-actions' style='display:flex; flex-direction:column; align-items:center; justify-content:center; gap:5px;'>
             <button class='f-btn-send-single' onclick='enviarCorrecaoSingle(\"$idProd\")' title='Enviar para Correção' style='width:100%; border-radius:4px; padding:8px 0;'><i class='material-icons' style='font-size:18px'>build</i></button>
        </div>
    </div>";
}

// =============================================================================
// [AJAX] GERENCIADOR
// =============================================================================
if ($isAjax) {
    
    function cleanInput($data) {
        $data = trim($data);
        return mysql_real_escape_string($data);
    }

    // [NOVO] ENVIAR PARA CORREÇÃO (D001E -> D001F)
    if (isset($_POST['action']) && $_POST['action'] === 'send_correction') {
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

    // [EXPORTAÇÃO CSV]
    if (isset($_POST['action']) && $_POST['action'] === 'export_csv') {
        
        $where = ["1=1"];
        if (!empty($_POST['f_tit'])) { $ft = cleanInput($_POST['f_tit']); $where[] = "T1.D001E_Titulo LIKE '%$ft%'"; }
        if (!empty($_POST['f_sku'])) { $fs = cleanInput($_POST['f_sku']); $where[] = "T1.D001E_D001_Codigo_Produto LIKE '%$fs%'"; }
        if (!empty($_POST['f_mar'])) { $fm = cleanInput($_POST['f_mar']); $where[] = "T1.D001E_Marca LIKE '%$fm%'"; }
        if (!empty($_POST['f_desc'])) { $fd = cleanInput($_POST['f_desc']); $where[] = "T1.D001E_Descricao LIKE '%$fd%'"; }
        if (!empty($_POST['f_spec'])) { $fsp = cleanInput($_POST['f_spec']); $where[] = "(T1.D001E_EAN LIKE '%$fsp%' OR T1.D001E_garantia LIKE '%$fsp%' OR T1.D001E_peso LIKE '%$fsp%')"; }

        if (isset($_POST['f_est_liq']) && $_POST['f_est_liq'] !== '') { $val = (int)$_POST['f_est_liq']; $where[] = "T2.D009_Quantidade_Estoque_Liquido = $val"; }
        if (isset($_POST['f_est_tab']) && $_POST['f_est_tab'] !== '') { $val = (int)$_POST['f_est_tab']; $where[] = "T2.D009_Quantidade_Estoque_Tabela = $val"; }
        if (!empty($_POST['f_freq'])) { $val = cleanInput($_POST['f_freq']); $where[] = "T2.D009_Frequencia_Venda LIKE '%$val%'"; }
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

        function simpleClean($str) {
            if(is_null($str)) return '';
            $str = str_replace(["\r\n", "\r", "\n", "\t"], ' ', $str);
            return trim($str);
        }

        if ($rsCsv) {
            while ($row = mysql_fetch_assoc($rsCsv)) {
                $line = [
                    $row['D001E_Id'],
                    $row['D001E_D001_Id'],
                    simpleClean($row['D001E_D001_Codigo_Produto']),
                    simpleClean($row['D001E_Titulo']),
                    simpleClean($row['D001E_Marca']),
                    simpleClean($row['D001E_Descricao']),
                    $row['D001E_Imagem_1'], $row['D001E_Imagem_2'], $row['D001E_Imagem_3'], $row['D001E_Imagem_4'], $row['D001E_Imagem_5'],
                    $row['D001E_Imagem_6'], $row['D001E_Imagem_7'], $row['D001E_Imagem_8'], $row['D001E_Imagem_9'], $row['D001E_Imagem_10'],
                    $row['D001E_Status_Pontuacao'],
                    simpleClean($row['D001E_EAN']),
                    simpleClean($row['D001E_garantia']),
                    simpleClean($row['D001E_peso']),
                    simpleClean($row['D001E_altura']), 
                    simpleClean($row['D001E_largura']), 
                    simpleClean($row['D001E_comprimento']), 
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
    if (!empty($_POST['f_tit'])) { $ft = cleanInput($_POST['f_tit']); $where[] = "T1.D001E_Titulo LIKE '%$ft%'"; }
    if (!empty($_POST['f_sku'])) { $fs = cleanInput($_POST['f_sku']); $where[] = "T1.D001E_D001_Codigo_Produto LIKE '%$fs%'"; }
    if (!empty($_POST['f_mar'])) { $fm = cleanInput($_POST['f_mar']); $where[] = "T1.D001E_Marca LIKE '%$fm%'"; }
    if (!empty($_POST['f_desc'])) { $fd = cleanInput($_POST['f_desc']); $where[] = "T1.D001E_Descricao LIKE '%$fd%'"; }
    if (!empty($_POST['f_spec'])) { $fsp = cleanInput($_POST['f_spec']); $where[] = "(T1.D001E_EAN LIKE '%$fsp%' OR T1.D001E_garantia LIKE '%$fsp%' OR T1.D001E_peso LIKE '%$fsp%')"; }
    if (isset($_POST['f_est_liq']) && $_POST['f_est_liq'] !== '') { $val = (int)$_POST['f_est_liq']; $where[] = "T2.D009_Quantidade_Estoque_Liquido = $val"; }
    if (isset($_POST['f_est_tab']) && $_POST['f_est_tab'] !== '') { $val = (int)$_POST['f_est_tab']; $where[] = "T2.D009_Quantidade_Estoque_Tabela = $val"; }
    if (!empty($_POST['f_freq'])) { $val = cleanInput($_POST['f_freq']); $where[] = "T2.D009_Frequencia_Venda LIKE '%$val%'"; }
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
    if ($rs) { while ($row = mysql_fetch_assoc($rs)) { $html .= renderQualityRow($row); } }

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

    /* Layout Novo: Check(30) | Foto(70) | Prod(1.4) | Metrics(1) | Desc(1.2) | Specs(0.8) | Scores(4*40) | Geral(70) | Actions(60) */
    /* Total ajustado para caber bem */
    .quality-header, .quality-row { 
        display: grid; 
        grid-template-columns: 30px 70px 1.4fr 1fr 1.2fr 0.8fr 40px 40px 40px 40px 70px 60px; 
        gap: 8px; 
        align-items: center; 
    }
    
    .quality-header { 
        position: sticky; top: 0; z-index: 50; 
        background: #f9fafb; border-bottom: 2px solid #e5e7eb;
        padding: 12px 16px; 
        font-size: 11px; font-weight: 700; color: #6b7280; text-transform: uppercase; letter-spacing: 0.03em; 
        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    }
    
    .quality-header > div { display: flex; align-items: center; justify-content: center; text-align: center; }
    .quality-header > div:nth-child(3), .quality-header > div:nth-child(5), .quality-header > div:nth-child(6) { justify-content: flex-start; text-align: left; }
    
    .quality-row { background: var(--card-bg); border-radius: 10px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); padding: 14px 16px; margin-bottom: 10px; border: 1px solid transparent; transition: all 0.2s; }
    .quality-row:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.1); border-color: #d1d5db; }
    
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
    #demo { padding: 20px 0; display:none; flex-wrap:wrap; align-items:center; justify-content:center; gap:5px; }
    #demo.active { display: flex; }
    #demo .pg-btn { border: 1px solid #d1d5db; background:#fff; padding:6px 12px; border-radius:6px; cursor:pointer; font-size:12px; font-weight:600; color:#374151; }
    #demo .pg-btn:hover { background: #f3f4f6; }
    #demo .pg-btn.active { background: var(--primary); border-color: var(--primary); color:#fff; }
    @media (max-width: 1200px) { 
        .f-grid { grid-template-columns: repeat(4, 1fr); }
        .quality-header, .quality-row { grid-template-columns: 30px 60px 1.4fr 160px 1.5fr 1fr 60px; gap: 8px; }
        .col-metrics { grid-template-columns: 1fr; gap: 2px; }
    }
    @media (max-width: 900px) {
        .f-grid { grid-template-columns: repeat(2, 1fr); }
        .quality-header { display: none; }
        .quality-row { display: flex; flex-direction: column; align-items: stretch; gap: 10px; position: relative; padding-top: 15px; }
        .thumb-box { align-self: flex-start; }
        .col-info { margin-left: 70px; margin-top: -70px; min-height: 60px; justify-content: center; }
        .col-metrics { display: flex; justify-content: space-between; flex-wrap: wrap; margin-top: 10px; }
        .col-score { position: absolute; top: 10px; right: 10px; }
        .mini-score-box { display: inline-block; margin: 0 5px; }
    }
</style>
<link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
STYLE;
if (!$apiMode) echo $style;

$tipTit  = "<div class='header-tooltip-content'><div class='header-tooltip-title'>REGRAS: TÍTULO (Peso 3)</div><div class='header-rule-row'><span>< 30 chars</span><span style='color:#ef4444'>1</span></div><div class='header-rule-row'><span>30 a 39 chars</span><span style='color:#eab308'>2</span></div><div class='header-rule-row'><span>40 a 49 chars</span><span style='color:#10b981'>3</span></div><div class='header-rule-row'><span>50 a 59 chars</span><span style='color:#10b981'>4</span></div><div class='header-rule-row'><span>60 a 89 chars</span><span style='color:#0098D3'>5</span></div></div>";
$tipDesc = "<div class='header-tooltip-content'><div class='header-tooltip-title'>REGRAS: DESCRIÇÃO (Peso 3)</div><div class='header-rule-row'><span>< 200 chars</span><span style='color:#ef4444'>1</span></div><div class='header-rule-row'><span>200 a 399</span><span style='color:#eab308'>2</span></div><div class='header-rule-row'><span>400 a 599</span><span style='color:#10b981'>3</span></div><div class='header-rule-row'><span>600 a 1999</span><span style='color:#10b981'>4</span></div><div class='header-rule-row'><span>2000 a 4000</span><span style='color:#0098D3'>5</span></div></div>";
$tipImg  = "<div class='header-tooltip-content'><div class='header-tooltip-title'>REGRAS: IMAGENS (Peso 3)</div><div class='header-rule-row'><span>< 2 imgs</span><span style='color:#ef4444'>1</span></div><div class='header-rule-row'><span>2 imgs</span><span style='color:#eab308'>3</span></div><div class='header-rule-row'><span>3 a 4 imgs</span><span style='color:#10b981'>4</span></div><div class='header-rule-row'><span>5 a 10 imgs</span><span style='color:#0098D3'>5</span></div><div class='header-rule-row'><span>> 10 imgs</span><span style='color:#ef4444'>0</span></div></div>";
$tipSpec = "<div class='header-tooltip-content'><div class='header-tooltip-title'>REGRAS: ATRIBUTOS (Peso 1)</div><div class='header-rule-row'><span>< 2 itens</span><span style='color:#ef4444'>1</span></div><div class='header-rule-row'><span>2 a 3 itens</span><span style='color:#eab308'>3</span></div><div class='header-rule-row'><span>4 a 6 itens</span><span style='color:#10b981'>4</span></div><div class='header-rule-row'><span>7 a 19 itens</span><span style='color:#0098D3'>5</span></div></div>";

echo "
<div class='filter-container'>
    <div class='filter-header' onclick='toggleFilterBody()'>
        <div class='filter-title'><i class='material-icons filter-icon'>tune</i> Filtros Avançados</div>
        <i class='material-icons filter-chevron' id='filterChevron'>expand_more</i>
    </div>
    <div class='filter-body' id='filterBody'>
        <div class='f-grid'>
            <div class='f-group'><label class='f-label'>Título</label><input type='text' id='f_tit' class='f-input' placeholder='Ex: Parafusadeira'></div>
            <div class='f-group'><label class='f-label'>SKU / Cód</label><input type='text' id='f_sku' class='f-input' placeholder='Ex: 12345'></div>
            <div class='f-group'><label class='f-label'>Marca</label><input type='text' id='f_mar' class='f-input' placeholder='Ex: Makita'></div>
            <div class='f-group'><label class='f-label'>Descrição</label><input type='text' id='f_desc' class='f-input' placeholder='Contém...'></div>
            <div class='f-group'><label class='f-label'>Specs/EAN</label><input type='text' id='f_spec' class='f-input' placeholder='Contém...'></div>
            <div class='f-group'><label class='f-label'>Est. Líquido (=)</label><input type='number' id='f_est_liq' class='f-input' placeholder='Exato'></div>
            <div class='f-group'><label class='f-label'>Est. Tabela (=)</label><input type='number' id='f_est_tab' class='f-input' placeholder='Exato'></div>
            <div class='f-group'><label class='f-label'>Frequência</label><input type='text' id='f_freq' class='f-input' placeholder='Ex: 1'></div>
            <div class='f-group'><label class='f-label'>Custo (=)</label><input type='text' id='f_custo' class='f-input' placeholder='Ex: 10,90'></div>
            <div class='f-group'><label class='f-label'>Nota Geral</label><select id='f_sco' class='f-input'><option value='0'>Todas</option><option value='6'>6 - Ótima</option><option value='5'>5 - M.Boa</option><option value='4'>4 - Boa</option><option value='3'>3 - Média</option><option value='2'>2 - Ruim</option><option value='1'>1 - M.Ruim</option></select></div>
            <div class='f-group'><label class='f-label'>Nota Título</label><select id='f_sc_tit' class='f-input'><option value='0'>Todas</option><option value='1'>1</option><option value='2'>2</option><option value='3'>3</option><option value='4'>4</option><option value='5'>5</option></select></div>
            <div class='f-group'><label class='f-label'>Nota Desc</label><select id='f_sc_desc' class='f-input'><option value='0'>Todas</option><option value='1'>1</option><option value='2'>2</option><option value='3'>3</option><option value='4'>4</option><option value='5'>5</option></select></div>
            <div class='f-group'><label class='f-label'>Nota Img</label><select id='f_sc_img' class='f-input'><option value='0'>Todas</option><option value='1'>1</option><option value='3'>3</option><option value='4'>4</option><option value='5'>5</option></select></div>
            <div class='f-group'><label class='f-label'>Nota Spec</label><select id='f_sc_spec' class='f-input'><option value='0'>Todas</option><option value='1'>1</option><option value='3'>3</option><option value='4'>4</option><option value='5'>5</option></select></div>
        </div>
        <div class='f-actions'>
            <button class='f-btn-apply' onclick='applyFilters()'><i class='material-icons'>search</i> Aplicar Filtros</button>
            <button class='f-btn-export' onclick='exportCSV()'><i class='material-icons'>file_download</i> Exportar CSV</button>
            <button class='f-btn-send' onclick='enviarCorrecaoMassa()'><i class='material-icons'>playlist_add_check</i> Enviar Selecionados</button>
        </div>
    </div>
</div>";

echo "<div class='quality-list'>";
echo "<div class='quality-header'>
        <div style='cursor:pointer' onclick='toggleSelectAll()' title='Selecionar Todos'><i class='material-icons' style='font-size:16px'>check_box</i></div>
        <div>Foto</div><div>Produto / Marca</div><div>Métricas</div><div>Descrição</div><div>Especificações</div>
        <div style='cursor:help' onmouseenter='window.showHTooltip(this)' onmousemove='window.moveHTooltip(event)' onmouseleave='window.hideHTooltip()'>TÍTULO<div class='tooltip-hidden-content' style='display:none'>$tipTit</div></div>
        <div style='cursor:help' onmouseenter='window.showHTooltip(this)' onmousemove='window.moveHTooltip(event)' onmouseleave='window.hideHTooltip()'>DESC<div class='tooltip-hidden-content' style='display:none'>$tipDesc</div></div>
        <div style='cursor:help' onmouseenter='window.showHTooltip(this)' onmousemove='window.moveHTooltip(event)' onmouseleave='window.hideHTooltip()'>IMG<div class='tooltip-hidden-content' style='display:none'>$tipImg</div></div>
        <div style='cursor:help' onmouseenter='window.showHTooltip(this)' onmousemove='window.moveHTooltip(event)' onmouseleave='window.hideHTooltip()'>ESPEC<div class='tooltip-hidden-content' style='display:none'>$tipSpec</div></div>
        <div>Geral</div>
        <div>Ações</div>
      </div>";

echo "<div id='content'><div class='start-msg'><i class='material-icons'>tune</i><h2>Comece sua análise</h2><p>Utilize os filtros acima para buscar os produtos.</p></div></div>";

$ajaxUrl  = isset($_SERVER['REQUEST_URI']) ? htmlspecialchars($_SERVER['REQUEST_URI'], ENT_QUOTES, 'UTF-8') : '';
$sysDivId = isset($g['divId']) ? $g['divId'] : 'content';
echo "<input type='hidden' id='hardness_total' value='0'>";
echo "<input type='hidden' id='hardness_pageSize' value='" . (int) $limit . "'>";
echo "<input type='hidden' id='hardness_ajaxUrl' value='" . $ajaxUrl . "'>";
echo "<input type='hidden' id='sys_base_divId' value='" . $sysDivId . "'>";
echo "<div id='demo'></div></div>";
?>

<div id="modalVis" class="modal-overlay" onclick="if(event.target==this) fecharVis()">
    <div class="modal-content printable-area">
        <span class="close-modal" onclick="fecharVis()">×</span>
        <div class="vis-thumbs" id="visThumbs"></div>
        <div class="vis-main"><span class="vis-score-badge" id="visImgScore">--</span><img id="visHero" src=""></div>
        <div class="vis-info">
            <h1 class="vis-h1"><span id="visTitle">--</span><span class="vis-chip" id="visTitleScore">--</span></h1>
            <div class="vis-meta">SKU: <strong id="visSku">--</strong> | Marca: <strong id="visBrand">--</strong></div>
            <button class="vis-btn-print" onclick="imprimirConteudoModal()"><i class="material-icons">print</i> Imprimir Ficha Técnica</button>
            <div class="vis-header-row"><span>Descrição do Produto</span><span class="vis-chip" id="visDescScore">--</span></div>
            <div class="vis-desc-box" id="visDesc"></div>
            <div class="vis-specs-container"><div class="vis-header-row" style="margin-top:15px"><span>Especificações</span><span class="vis-chip" id="visAttrScore">--</span></div><div id="visSpecsContent"></div></div>
        </div>
    </div>
</div>

<script>
    function toggleFilterBody() {
        var b = document.getElementById('filterBody');
        var c = document.getElementById('filterChevron');
        if (b.classList.contains('closed')) { b.classList.remove('closed'); c.style.transform = 'rotate(0deg)'; } else { b.classList.add('closed'); c.style.transform = 'rotate(-90deg)'; }
    }
    
    if (typeof window.initHardnessTooltip === 'undefined') {
        window.initHardnessTooltip = true;
        var tipDiv = document.createElement('div'); tipDiv.id = 'hardness-custom-tooltip'; tipDiv.style.position = 'fixed'; tipDiv.style.display = 'none'; document.body.appendChild(tipDiv);
        window.showHTooltip = function (el) { var c = el.querySelector('.tooltip-hidden-content'); if (!c) return; var t = document.getElementById('hardness-custom-tooltip'); t.innerHTML = c.innerHTML; t.style.display = 'block'; };
        window.moveHTooltip = function (e) { var t = document.getElementById('hardness-custom-tooltip'); if (t && t.style.display === 'block') { var x = e.clientX + 15, y = e.clientY + 15; if (x + t.offsetWidth > window.innerWidth) x = e.clientX - t.offsetWidth - 5; if (y + t.offsetHeight > window.innerHeight) y = e.clientY - t.offsetHeight - 5; t.style.left = x + 'px'; t.style.top = y + 'px'; } };
        window.hideHTooltip = function () { var t = document.getElementById('hardness-custom-tooltip'); if (t) t.style.display = 'none'; };
    }
    
    var pager = {
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
    
    var app = {
        getFilters: function() {
            return {
                f_tit: jQuery('#f_tit').val(), f_sku: jQuery('#f_sku').val(), f_mar: jQuery('#f_mar').val(), f_desc: jQuery('#f_desc').val(), f_spec: jQuery('#f_spec').val(),
                f_est_liq: jQuery('#f_est_liq').val(), f_est_tab: jQuery('#f_est_tab').val(), f_freq: jQuery('#f_freq').val(), f_custo: jQuery('#f_custo').val(),
                f_sco: jQuery('#f_sco').val(), f_sc_tit: jQuery('#f_sc_tit').val(), f_sc_desc: jQuery('#f_sc_desc').val(), f_sc_img: jQuery('#f_sc_img').val(), f_sc_spec: jQuery('#f_sc_spec').val()
            };
        },
        loadData: function(p) {
            p = parseInt(p, 10) || 1; var filters = this.getFilters(); var size = parseInt(jQuery('#hardness_pageSize').val(), 10) || 50; var url = jQuery('#hardness_ajaxUrl').val(); var sysId = jQuery('#sys_base_divId').val();
            if (sysId && jQuery('#' + sysId).length) jQuery('#' + sysId).showLoading();
            jQuery.ajax({ url: url, type: 'POST', dataType: 'json', data: jQuery.extend({ ajax: 1, page: p, pageSize: size }, filters), success: function (r) { if (r && r.ok) { jQuery('#content').html(r.html); pager.render('demo', r.total, p, size, 'app.loadData'); } else { jQuery('#content').html('<div class="start-msg">Sem resultados</div>'); jQuery('#demo').removeClass('active').html(''); } }, complete: function () { if (sysId && jQuery('#' + sysId).length) jQuery('#' + sysId).hideLoading(); } });
        }
    };
    
    function applyFilters() { app.loadData(1); }
    
    function exportCSV() {
        var filters = app.getFilters(); var url = jQuery('#hardness_ajaxUrl').val();
        var form = document.createElement('form'); form.method = 'POST'; form.action = url; form.target = '_blank';
        var i1 = document.createElement('input'); i1.name = 'ajax'; i1.value = '1'; form.appendChild(i1);
        var i2 = document.createElement('input'); i2.name = 'action'; i2.value = 'export_csv'; form.appendChild(i2);
        for (var key in filters) { if (filters.hasOwnProperty(key)) { var inp = document.createElement('input'); inp.name = key; inp.value = filters[key]; form.appendChild(inp); } }
        document.body.appendChild(form); form.submit(); document.body.removeChild(form);
    }
    
    // ENVIO PARA CORREÇÃO (INDIVIDUAL E EM MASSA)
    function enviarCorrecaoSingle(id) {
        if(confirm('Deseja enviar este produto para a lista de Correção?')) {
            enviarAjaxCorrecao([id]);
        }
    }
    
    function enviarCorrecaoMassa() {
        var ids = [];
        jQuery('.row-check:checked').each(function() { ids.push(jQuery(this).val()); });
        if (ids.length === 0) { alert('Selecione pelo menos um item.'); return; }
        if(confirm('Enviar ' + ids.length + ' produtos para a lista de Correção?')) {
            enviarAjaxCorrecao(ids);
        }
    }
    
    function enviarAjaxCorrecao(ids) {
        var url = jQuery('#hardness_ajaxUrl').val();
        var sysId = jQuery('#sys_base_divId').val();
        if (sysId && jQuery('#' + sysId).length) jQuery('#' + sysId).showLoading();
        
        jQuery.ajax({
            url: url, type: 'POST', dataType: 'json',
            data: { ajax: 1, action: 'send_correction', ids: ids },
            success: function(res) {
                alert(res.msg || 'Processado.');
                // Opcional: Recarregar a lista para limpar checkboxes
                // app.loadData(1);
                jQuery('.row-check').prop('checked', false);
            },
            complete: function() { if (sysId && jQuery('#' + sysId).length) jQuery('#' + sysId).hideLoading(); }
        });
    }

    // TOGGLE SELECT ALL
    var allSelected = false;
    function toggleSelectAll() {
        allSelected = !allSelected;
        jQuery('.row-check').prop('checked', allSelected);
    }

    const mVis = document.getElementById('modalVis'), vThumbs = document.getElementById('visThumbs'), vHero = document.getElementById('visHero'), vTitle = document.getElementById('visTitle'), vSku = document.getElementById('visSku'), vBrand = document.getElementById('visBrand'), vDesc = document.getElementById('visDesc'), vSpecs = document.getElementById('visSpecsContent');
    const elTS = document.getElementById('visTitleScore'), elDS = document.getElementById('visDescScore'), elIS = document.getElementById('visImgScore'), elAS = document.getElementById('visAttrScore');
    function getMetaNota(n) { n = Number(n); if (n === 6) return { c: '#0098D3', t: 'Ótima' }; if (n === 5) return { c: '#10b981', t: 'Muito Boa' }; if (n === 4) return { c: '#84cc16', t: 'Boa' }; if (n === 3) return { c: '#eab308', t: 'Média' }; if (n === 2) return { c: '#fca5a5', t: 'Ruim' }; return { c: '#ef4444', t: 'Muito Ruim' }; }
    function abrirVisualizador(sku) {
        var url = document.getElementById('hardness_ajaxUrl').value; var sysId = document.getElementById('sys_base_divId').value; if (sysId && typeof jQuery !== 'undefined' && jQuery('#' + sysId).length) jQuery('#' + sysId).showLoading();
        jQuery.ajax({ url: url, type: 'POST', dataType: 'json', data: { ajax: 1, action: 'get_details', sku: sku }, success: function (res) { if (res.ok) { vTitle.innerText = res.titulo; vSku.innerText = res.sku; vBrand.innerText = res.marca; vDesc.innerHTML = res.desc ? res.desc : '<em>Sem descrição.</em>'; const mT = getMetaNota(res.scores.tit); elTS.style.backgroundColor = mT.c; elTS.innerText = res.scores.tit + ' - ' + mT.t; const mD = getMetaNota(res.scores.desc); elDS.style.backgroundColor = mD.c; elDS.innerText = res.scores.desc + ' - ' + mD.t; const mI = getMetaNota(res.scores.img); elIS.style.backgroundColor = mI.c; elIS.innerText = 'Fotos: ' + res.scores.img + ' (' + mI.t + ')'; const mA = getMetaNota(res.scores.attr); elAS.style.backgroundColor = mA.c; elAS.innerText = res.scores.attr + ' - ' + mA.t; vThumbs.innerHTML = ''; if (res.imgs.length > 0) vHero.src = res.imgs[0]; res.imgs.forEach((url, idx) => { let img = document.createElement('img'); img.src = url; img.className = 'vis-mini'; if (idx === 0) img.classList.add('active'); img.onclick = () => { vHero.src = url; document.querySelectorAll('.vis-mini').forEach(el => el.classList.remove('active')); img.classList.add('active'); }; vThumbs.appendChild(img); }); let h = '<table class="vis-specs-table">'; let has = false; if (res.specs.EAN) { h += `<tr><td><strong>EAN:</strong> ${res.specs.EAN}</td></tr>`; has = true; } if (res.specs.Garantia) { h += `<tr><td><strong>Garantia:</strong> ${res.specs.Garantia}</td></tr>`; has = true; } if (res.specs.Peso) { h += `<tr><td><strong>Peso:</strong> ${res.specs.Peso}</td></tr>`; has = true; } if (res.specs.Altura) { h += `<tr><td><strong>Altura:</strong> ${res.specs.Altura}</td></tr>`; has = true; } if (res.specs.Largura) { h += `<tr><td><strong>Largura:</strong> ${res.specs.Largura}</td></tr>`; has = true; } if (res.specs.Comprimento) { h += `<tr><td><strong>Comp.:</strong> ${res.specs.Comprimento}</td></tr>`; has = true; } h += '</table>'; vSpecs.innerHTML = has ? h : '<div style="color:#999;font-size:12px">Vazio</div>'; mVis.style.display = 'flex'; } else { alert(res.msg || 'Erro ao carregar'); } }, error: function () { alert('Erro na comunicação'); }, complete: function () { if (sysId && typeof jQuery !== 'undefined' && jQuery('#' + sysId).length) jQuery('#' + sysId).hideLoading(); } });
    }
    function fecharVis() { mVis.style.display = 'none'; }
    function imprimirConteudoModal() { const f = document.createElement('iframe'); f.style.display = 'none'; document.body.appendChild(f); const d = f.contentWindow.document; const s = vSpecs.innerHTML; const c = `<html><head><style>body{font-family:Arial,sans-serif;padding:20px;color:#333}h1{font-size:24px;margin-bottom:5px}.meta{color:#666;font-size:12px;margin-bottom:20px;border-bottom:1px solid #ccc;padding-bottom:10px}.hero{text-align:center;margin-bottom:20px}.hero img{max-width:300px;max-height:300px}.desc{font-size:12px;line-height:1.5;margin-bottom:20px}.specs-box{border:1px solid #eee;padding:10px;border-radius:5px}.specs-box table{width:100%;font-size:12px}.specs-box td{padding:4px 0}</style></head><body><h1>${vTitle.innerText}</h1><div class="meta">SKU: ${vSku.innerText}</div><div class="hero"><img src="${vHero.src}"></div><h3>Descrição</h3><div class="desc">${vDesc.innerHTML}</div><h3>Specs</h3><div class="specs-box">${s}</div></body></html>`; d.open(); d.write(c); d.close(); setTimeout(() => { f.contentWindow.print(); setTimeout(() => document.body.removeChild(f), 1000); }, 200); }
    document.addEventListener('keydown', e => { if (e.key === "Escape") fecharVis() });
</script>