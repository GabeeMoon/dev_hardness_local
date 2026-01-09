<?php
/*
 PAINEL DE CORRECAO DE ANUNCIO (D001F) - 100% ISOLADO (SEM CONFLITO DE AJAX)
 COM CORREÇÃO DE PERDA DE CONTEXTO (DIVROOT/DIVID)
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

// --- [CORREÇÃO DE CONTEXTO - DIV ROOT E ID] ---
// Lógica aplicada para persistir a hierarquia de janelas do ERP
$gDivRoot = isset($g['divRoot']) && $g['divRoot'] != "" ? $g['divRoot'] : (isset($_POST['sys_divRoot']) ? $_POST['sys_divRoot'] : (isset($_POST['divIdRoot']) ? $_POST['divIdRoot'] : ''));
$gDivId   = isset($g['divId']) && $g['divId'] != "" ? $g['divId'] : (isset($_POST['sys_divId']) ? $_POST['sys_divId'] : (isset($_POST['divId']) ? $_POST['divId'] : 'contentCor'));

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

// =============================================================================
// [HELPER] FORMATAÇÃO INTELIGENTE (TEXTO -> HTML)
// =============================================================================
if (!function_exists('autoFormatHTMLMel')) {
    function autoFormatHTMLMel($text) {
        $text = trim($text);
        // Remove caracteres estranhos do Word
        $search = [chr(145), chr(146), chr(147), chr(148), chr(151)];
        $replace = ["'", "'", '"', '"', '-'];
        $text = str_replace($search, $replace, $text);

        // Se não tem tags HTML, assume texto plano e converte
        if (strpos($text, '<') === false && strpos($text, '>') === false) {
            return nl2br($text);
        }
        return $text;
    }
}

// =============================================================================
// [RENDER] FUNÇÃO DE LINHA EXCLUSIVA (D001F)
// =============================================================================
function renderCorrectionRowIsolado($row) {
    // [IMPORTANTE] Globais recuperadas para manter hierarquia ao abrir janela
    global $gDivRoot, $gDivId;

    $id        = (int) $row['D001F_Id'];
    $marca     = isset($row['D001F_Marca']) ? $row['D001F_Marca'] : 'ND';
    $imgCapa   = $row['D001F_Imagem_1'] ?: "https://via.placeholder.com/100x100?text=Sem+Img";

    $titulo    = htmlspecialchars($row['D001F_Titulo'], ENT_QUOTES); 
    $skuRaw    = $row['D001F_D001_Codigo_Produto'];
    $sku       = htmlspecialchars($skuRaw, ENT_QUOTES);
    $descRaw   = $row['D001F_Descricao'];
    $obsRaw    = $row['D001F_Obs'];
    $marcaHtml = htmlspecialchars($marca, ENT_QUOTES);
    $idAny     = !empty($row['D001F_Id_Any']) ? $row['D001F_Id_Any'] : 'ND';

    $tipoRaw = isset($row['D001F_Tipo']) ? $row['D001F_Tipo'] : 'corr';
    if ($tipoRaw === 'mod') {
        $tipoHtml = "<div class='badge-type badge-type-mod'><i class='material-icons'>edit_note</i><span>MODIFICAÇÃO</span></div>";
    } else {
        $tipoHtml = "<div class='badge-type badge-type-corr'><i class='material-icons'>build_circle</i><span>CORREÇÃO</span></div>";
    }

    $specHtml = "";
    if (!empty($row['D001F_EAN'])) $specHtml .= "<span><b>EAN:</b> {$row['D001F_EAN']}</span>";
    if (!empty($row['D001F_garantia'])) $specHtml .= "<span><b>Gar:</b> {$row['D001F_garantia']}</span>";
    if (!empty($row['D001F_peso'])) $specHtml .= "<span><b>Peso:</b> {$row['D001F_peso']}</span>";
    if (!empty($row['D001F_altura'])) $specHtml .= "<span><b>Dim:</b> " . ($row['D001F_altura'] ?: 0) . "x" . ($row['D001F_largura'] ?: 0) . "x" . ($row['D001F_comprimento'] ?: 0) . "</span>";
    if (empty($specHtml)) $specHtml = "<span style='color:#bbb'>Vazio</span>";

    $freqVenda = !empty($row['D009_Frequencia_Venda']) ? $row['D009_Frequencia_Venda'] : '0';
    $custoVal  = isset($row['D009_Valor_Custo_Unitario']) ? (float) $row['D009_Valor_Custo_Unitario'] : 0;
    $estTab    = isset($row['D009_Quantidade_Estoque_Tabela']) ? (int) $row['D009_Quantidade_Estoque_Tabela'] : 0;
    $estLiq    = isset($row['D009_Quantidade_Estoque_Liquido']) ? (int) $row['D009_Quantidade_Estoque_Liquido'] : 0;

    $custoHtml  = ($custoVal > 0) ? "R$ " . number_format($custoVal, 2, ',', '.') : "0";
    $estTabHtml = ($estTab > 0) ? $estTab : "0";
    $estLiqHtml = ($estLiq > 0) ? $estLiq : "0";

    // --- SISTEMA DE TAGS NUMÉRICO (PHP RENDER) ---
    $tagDefs = [
        1 => ['label' => 'IMAGEM',           'bg' => '#8b5cf6', 'br' => '#7c3aed'],
        2 => ['label' => 'TÍTULO',           'bg' => '#3b82f6', 'br' => '#2563eb'],
        3 => ['label' => 'DESCRIÇÃO',        'bg' => '#10b981', 'br' => '#059669'],
        4 => ['label' => 'PESOS E DIMENSÃO', 'bg' => '#f97316', 'br' => '#ea580c'],
        5 => ['label' => 'MATCH',            'bg' => '#ef4444', 'br' => '#dc2626'],
        6 => ['label' => 'VOLTAGEM',         'bg' => '#eab308', 'br' => '#ca8a04'],
        7 => ['label' => 'COR',              'bg' => '#ec4899', 'br' => '#db2777']
    ];

    $tagsHtml = "";
    if (!empty($row['D001F_tags'])) {
        $ids = explode(',', $row['D001F_tags']);
        $tagsHtml = "<div style='display:flex; gap:4px; flex-wrap:wrap !important; margin-top:6px; width:100%; box-sizing:border-box;'>";
        foreach($ids as $tid) {
            $tid = (int)trim($tid);
            if(isset($tagDefs[$tid])) {
                $tData = $tagDefs[$tid];
                $tagsHtml .= "<span style='background:{$tData['bg']}; color:#fff; border:1px solid {$tData['br']}; font-size:10px; padding:3px 7px; border-radius:10px; font-weight:700; white-space:nowrap; box-shadow:0 1px 2px rgba(0,0,0,0.1); display:inline-block; flex-shrink:0;'>{$tData['label']}</span>";
            }
        }
        $tagsHtml .= "</div>";
    }
    $d001Id    = $row['D001F_D001_Id'];

    // Monta JS com as globais seguras ($gDivRoot e $gDivId)
    $jsForn = "abrirJanela(false, '{$gDivRoot}', '{$gDivId}', unique(), '', 'Produto', '/cad/cad002/content/form2/', '&acaoId=' + encodeURIComponent('{$d001Id}'), [700,400]); return false;";

    return "
    <div class='quality-row' id='row_cor_{$id}'>
        <div class='col-check'>
             <input type='checkbox' class='row-check' value='{$id}'>
        </div>
        
        <div class='col-type'>$tipoHtml</div>
        <div class='thumb-box' onclick='abrirVisualizadorCor(\"$sku\")'>
            <img src='$imgCapa' alt='Capa'>
        </div>
        
        <div class='col-info' style='overflow:visible !important; height:auto !important; min-height:60px; display:flex; flex-direction:column; justify-content:center;'>
            <div class='prod-title'>{$row['D001F_Titulo']}</div>
            $tagsHtml
            <div class='prod-sub' style='margin-top:6px;'>
                <span class='badge-any' title='ID AnyMarket' style='cursor:pointer' onclick='window.open(\"https://app.anymarket.com.br/app-js/products/edit/$idAny\", \"_blank\"); event.stopPropagation();'>Id Any: $idAny</span>
                <span class='badge-sku' title='SKU Produto' style='cursor:pointer' onclick=\"{$jsForn}\">Sku: $sku</span>
                <span class='badge-brand' title='$marcaHtml'>Marca: $marcaHtml</span>
            </div>
        </div>

        <div class='col-box-scroll content-desc'>" . ($obsRaw ?: '<em>Sem observação</em>') . "</div>
        
        <div class='col-metrics'>
            <div class='metric-item' title='Frequência de Venda'><span class='m-lbl'>FREQ</span> <span class='m-val'>$freqVenda</span></div>
            <div class='metric-item' title='Custo Unitário'><span class='m-lbl'>CUSTO</span> <span class='m-val text-blue'>$custoHtml</span></div>
            <div class='metric-item' title='Estoque Tabela'><span class='m-lbl'>ESTQ.TAB</span> <span class='m-val'>$estTabHtml</span></div>
            <div class='metric-item' title='Estoque Líquido'><span class='m-lbl'>ESTQ.LIQ</span> <span class='m-val'>$estLiqHtml</span></div>
        </div>

        <div class='col-box-scroll content-desc'>" . ($descRaw ?: '<em>Sem descrição</em>') . "</div>
        
        <div class='col-box-scroll content-spec'>$specHtml</div>
        
        <div class='col-actions'>
             <button class='btn-action-icon btn-edit' onclick='abrirEditorCor(\"$id\")' title='Editar (Salvar Rascunho)'><i class='material-icons'>edit</i></button>
             <button class='btn-action-icon btn-sync' onclick='syncAnyMarketItem(\"$id\")' title='Atualizar Any' style='color:#f59e0b; border-color:#f59e0b;'><i class='material-icons'>sync</i></button>
             <button class='btn-action-icon btn-finish' onclick='finalizarCorrecao(\"$id\")' title='Finalizar e Baixar' style='color:#10b981; border-color:#10b981;'><i class='material-icons'>check_circle</i></button>
        </div>
    </div>";
}

// =============================================================================
// [AJAX] GERENCIADOR ISOLADO
// =============================================================================
if ($isAjax) {

    if (!function_exists('cleanInputCor')) {
        function cleanInputCor($data) {
            $data = trim($data);
            return mysql_real_escape_string($data);
        }
    }

    // [ACTION] SYNC ANYMARKET ITEM (INDIVIDUAL E MASSA)
    if (isset($_POST['action']) && ($_POST['action'] === 'sync_anymarket_item' || $_POST['action'] === 'sync_anymarket_massa')) {
        $ids = isset($_POST['ids']) ? $_POST['ids'] : [$_POST['id']];
        if (!is_array($ids)) $ids = explode(',', $ids);

        // CARREGAR CLASSES DE API
        if (!class_exists('API001') && !class_exists('hardness\\API001')) { @require_once('bibliotecas/classes/API001.php'); }
        if (!class_exists('GMP010') && !class_exists('hardness\\GMP010')) { @require_once('bibliotecas/classes/GMP010.php'); }

        $successCount = 0;
        try {
            $apiClass = class_exists('hardness\\API001') ? 'hardness\\API001' : 'API001';
            $API001   = new $apiClass();
            $token    = $API001->executaProcesso(527);
            $baseUrl  = 'https://api.anymarket.com.br/v2';
            $gmpClass = class_exists('hardness\\GMP010') ? 'hardness\\GMP010' : 'GMP010';
            $apiManager = new $gmpClass($baseUrl, $token, 3, [], 'error_log', $g['pathDados']);

            foreach ($ids as $idF) {
                $idF = (int)$idF;
                $rsData = mysql_query("SELECT D001F_D001_Codigo_Produto FROM D001F WHERE D001F_Id = $idF LIMIT 1");
                if (!$rsData || mysql_num_rows($rsData) == 0) continue;
                $rowSync = mysql_fetch_assoc($rsData);
                $skuSync = trim($rowSync['D001F_D001_Codigo_Produto']);

                $endpoint = "/products?sku=" . urlencode($skuSync);
                $resp = $apiManager->request($endpoint, 'GET', null, true, ['return_on_failure' => true]);

                if ($resp && isset($resp['code']) && $resp['code'] == 200) {
                    $bodyRaw = isset($resp['body']) ? $resp['body'] : null;
                    $body = is_array($bodyRaw) ? $bodyRaw : (json_decode($bodyRaw, true) ?: []);
                    
                    if (!empty($body['content'][0])) {
                        $d = $body['content'][0];
                        $titulo      = mysql_real_escape_string($d['title'] ?? '');
                        $descricao   = mysql_real_escape_string($d['description'] ?? '');
                        $garantia    = mysql_real_escape_string($d['warrantyText'] ?? '');
                        $peso        = mysql_real_escape_string($d['weight'] ?? '');
                        $altura      = mysql_real_escape_string($d['height'] ?? '');
                        $largura     = mysql_real_escape_string($d['width'] ?? '');
                        $comprimento = mysql_real_escape_string($d['length'] ?? '');
                        $ean         = !empty($d['skus'][0]) ? mysql_real_escape_string($d['skus'][0]['ean'] ?? '') : '';
                        $marca       = !empty($d['brand']['name']) ? mysql_real_escape_string($d['brand']['name']) : '';

                        $sets = ["D001F_Titulo = '$titulo'", "D001F_Descricao = '$descricao'", "D001F_Marca = '$marca'", "D001F_EAN = '$ean'", "D001F_garantia = '$garantia'", "D001F_peso = '$peso'", "D001F_altura = '$altura'", "D001F_largura = '$largura'", "D001F_comprimento = '$comprimento'", "D001F_ult_att = NOW()"];
                        if (!empty($d['images']) && is_array($d['images'])) {
                            for ($i = 1; $i <= 10; $i++) {
                                $urlImg = isset($d['images'][$i - 1]['url']) ? mysql_real_escape_string($d['images'][$i - 1]['url']) : '';
                                $sets[] = "D001F_Imagem_$i = '$urlImg'";
                            }
                        }
                        mysql_query("UPDATE D001F SET " . implode(', ', $sets) . " WHERE D001F_Id = $idF");
                        $successCount++;
                    }
                }
            }
            echo json_encode(['ok' => 1, 'msg' => "$successCount itens atualizados com dados da AnyMarket."]);
        } catch (\Exception $e) {
            echo json_encode(['ok' => 0, 'msg' => 'Erro: ' . $e->getMessage()]);
        }
        exit;
    }

    if (!function_exists('getSmartWhereCor')) {
        function getSmartWhereCor($col, $val, $mode = 'text') {
            $val = trim($val);
            if ($val === '') return null;
            if (strpos($val, ';') !== false) {
                $parts = explode(';', $val);
                $arr = []; $orLike = [];
                foreach($parts as $p) {
                    $p = trim($p); if($p === '') continue;
                    if ($mode === 'number') { $p = str_replace(',', '.', $p); if(is_numeric($p)) $arr[] = $p; } 
                    else { $cleanP = mysql_real_escape_string($p); $orLike[] = "$col LIKE '%$cleanP%'"; }
                }
                if ($mode === 'number' && !empty($arr)) return "$col IN (" . implode(',', $arr) . ")";
                if ($mode === 'text' && !empty($orLike)) return "(" . implode(" OR ", $orLike) . ")";
            }
            $op = ''; $cleanVal = $val;
            if (isset($val[0])) {
                if ($val[0] === '>') { $op = '>'; $cleanVal = substr($val, 1); }
                elseif ($val[0] === '<') { $op = '<'; $cleanVal = substr($val, 1); }
                elseif ($val[0] === '!') { $op = '!'; $cleanVal = substr($val, 1); }
            }
            $cleanVal = trim($cleanVal);
            if ($mode === 'number') {
                $cleanVal = str_replace(',', '.', $cleanVal);
                if (!is_numeric($cleanVal)) return null; 
                if ($op === '!') return "$col <> $cleanVal";
                if ($op === '>' || $op === '<') return "$col $op $cleanVal";
                return "$col = $cleanVal"; 
            }
            $safeVal = mysql_real_escape_string($cleanVal);
            if ($op === '!') return "$col NOT LIKE '%$safeVal%'";
            if ($op === '>' || $op === '<') return "$col $op '$safeVal'"; 
            return "$col LIKE '%$safeVal%'";
        }
    }

    if (!function_exists('buildWhereCor')) {
        function buildWhereCor($postData) {
            $where = ["1=1"];
            if (!empty($postData['f_tit'])) { $w = getSmartWhereCor("T1.D001F_Titulo", $postData['f_tit'], 'text'); if($w) $where[] = $w; }
            if (!empty($postData['f_id_any'])) { $w = getSmartWhereCor("T1.D001F_Id_Any", $postData['f_id_any'], 'number'); if($w) $where[] = $w; }
            if (!empty($postData['f_sku'])) { $w = getSmartWhereCor("T1.D001F_D001_Codigo_Produto", $postData['f_sku'], 'text'); if($w) $where[] = $w; }
            if (!empty($postData['f_mar'])) { $w = getSmartWhereCor("T1.D001F_Marca", $postData['f_mar'], 'text'); if($w) $where[] = $w; }
            if (!empty($postData['f_desc'])) { $w = getSmartWhereCor("T1.D001F_Descricao", $postData['f_desc'], 'text'); if($w) $where[] = $w; }
            if (!empty($postData['f_tipo'])) { $ft = cleanInputCor($postData['f_tipo']); $where[] = "T1.D001F_Tipo = '$ft'"; }
            
            if (!empty($postData['f_tags'])) { 
                $ftg = (int)$postData['f_tags']; 
                $where[] = "FIND_IN_SET('$ftg', T1.D001F_tags) > 0"; 
            }

            if (!empty($postData['f_spec'])) { $fsp = cleanInputCor($postData['f_spec']); $where[] = "(T1.D001F_EAN LIKE '%$fsp%' OR T1.D001F_garantia LIKE '%$fsp%' OR T1.D001F_peso LIKE '%$fsp%')"; }
            if (isset($postData['f_est_liq']) && $postData['f_est_liq'] !== '') { $w = getSmartWhereCor("T2.D009_Quantidade_Estoque_Liquido", $postData['f_est_liq'], 'number'); if($w) $where[] = $w; }
            if (isset($postData['f_est_tab']) && $postData['f_est_tab'] !== '') { $w = getSmartWhereCor("T2.D009_Quantidade_Estoque_Tabela", $postData['f_est_tab'], 'number'); if($w) $where[] = $w; }
            if (!empty($postData['f_freq'])) { $w = getSmartWhereCor("T2.D009_Frequencia_Venda", $postData['f_freq'], 'number'); if($w) $where[] = $w; }
            if (!empty($postData['f_custo'])) { $w = getSmartWhereCor("T2.D009_Valor_Custo_Unitario", $postData['f_custo'], 'number'); if($w) $where[] = $w; }
            return implode(" AND ", $where);
        }
    }

    // [ACTION] GET EDIT DATA
    if (isset($_POST['action']) && $_POST['action'] === 'get_edit_data_cor') {
        $id = (int)$_POST['id'];
        $sql = "SELECT * FROM D001F WHERE D001F_Id = $id LIMIT 1";
        $rs = mysql_query($sql);
        if ($rs && mysql_num_rows($rs) > 0) {
            $r = mysql_fetch_assoc($rs);
            $imgs = [];
            for($i=1; $i<=10; $i++) $imgs[$i] = $r["D001F_Imagem_$i"];
            echo json_encode(['ok' => 1, 'data' => $r, 'imgs' => $imgs]);
        } else {
            echo json_encode(['ok' => 0, 'msg' => 'Item não encontrado.']);
        }
        exit;
    }

    // [ACTION] SAVE EDIT CORREÇÃO
    if (isset($_POST['action']) && $_POST['action'] === 'save_edit_cor') {
        $idF = (int)$_POST['id'];
        
        if ($idF <= 0) { echo json_encode(['ok' => 0, 'msg' => 'ID inválido.']); exit; }

        $tit = cleanInputCor($_POST['titulo']);
        $descRaw = $_POST['desc'];
        $desc = mysql_real_escape_string($descRaw);
        
        $ean = cleanInputCor($_POST['ean']);
        $gar = cleanInputCor($_POST['gar']);
        $peso = cleanInputCor($_POST['peso']);
        $alt = cleanInputCor($_POST['alt']);
        $larg = cleanInputCor($_POST['larg']);
        $comp = cleanInputCor($_POST['comp']);
        
        $tagsRaw = trim($_POST['tags']);
        $tags = preg_replace('/[^0-9,]/', '', $tagsRaw);

        $sets = [];
        $sets[] = "D001F_Titulo = '$tit'";
        $sets[] = "D001F_Descricao = '$desc'";
        $sets[] = "D001F_EAN = '$ean'";
        $sets[] = "D001F_garantia = '$gar'";
        $sets[] = "D001F_peso = '$peso'";
        $sets[] = "D001F_altura = '$alt'";
        $sets[] = "D001F_largura = '$larg'";
        $sets[] = "D001F_comprimento = '$comp'";
        $sets[] = "D001F_tags = '$tags'"; 
        
        for($i=1; $i<=10; $i++) {
            $imgKey = "img_$i";
            if (isset($_POST[$imgKey])) {
                $val = cleanInputCor($_POST[$imgKey]);
                $sets[] = "D001F_Imagem_$i = '$val'";
            }
        }

        $setStr = implode(", ", $sets);
        $sqlUpdate = "UPDATE D001F SET $setStr WHERE D001F_Id = $idF";
        $resUp = mysql_query($sqlUpdate);

        if ($resUp) {
            echo json_encode(['ok' => 1, 'msg' => 'Rascunho salvo na correção.']);
        } else {
            echo json_encode(['ok' => 0, 'msg' => 'Erro ao salvar rascunho: ' . mysql_error()]);
        }
        exit;
    }

    // [ACTION] FINALIZAR CORREÇÃO
    if (isset($_POST['action']) && $_POST['action'] === 'finalize_cor') {
        $idF = (int)$_POST['id'];
        if ($idF <= 0) { echo json_encode(['ok'=>0, 'msg'=>'ID inválido']); exit; }

        $rsF = mysql_query("SELECT * FROM D001F WHERE D001F_Id = $idF LIMIT 1");
        if (!$rsF || mysql_num_rows($rsF) == 0) { echo json_encode(['ok'=>0, 'msg'=>'Registro não encontrado']); exit; }
        $rowF = mysql_fetch_assoc($rsF);
        
        $sku = $rowF['D001F_D001_Codigo_Produto'];

        $idUser   = (int)$g['usuarioAtual'];
        $nomeUser = 'Sistema';
        $rsU      = mysql_query("SELECT C007_Primeiro_Nome FROM C007 WHERE C007_Id = $idUser LIMIT 1");
        if ($rsU && mysql_num_rows($rsU) > 0) { $rU = mysql_fetch_assoc($rsU); $nomeUser = $rU['C007_Primeiro_Nome']; }

        $sets = [];
        $sets[] = "D001E_Sku_Titulo = '" . mysql_real_escape_string($rowF['D001F_Titulo']) . "'";
        $sets[] = "D001E_Descricao = '" . mysql_real_escape_string($rowF['D001F_Descricao']) . "'";
        $sets[] = "D001E_EAN = '" . mysql_real_escape_string($rowF['D001F_EAN']) . "'";
        $sets[] = "D001E_garantia = '" . mysql_real_escape_string($rowF['D001F_garantia']) . "'";
        $sets[] = "D001E_peso = '" . mysql_real_escape_string($rowF['D001F_peso']) . "'";
        $sets[] = "D001E_altura = '" . mysql_real_escape_string($rowF['D001F_altura']) . "'";
        $sets[] = "D001E_largura = '" . mysql_real_escape_string($rowF['D001F_largura']) . "'";
        $sets[] = "D001E_comprimento = '" . mysql_real_escape_string($rowF['D001F_comprimento']) . "'";
        
        for ($i=1; $i<=10; $i++) {
            $sets[] = "D001E_Imagem_$i = '" . mysql_real_escape_string($rowF["D001F_Imagem_$i"]) . "'";
        }

        $sets[] = "D001E_data_finalizado = NOW()";
        $sets[] = "D001E_usua_finalizado = '" . mysql_real_escape_string($nomeUser) . "'";
        $sets[] = "D001E_ult_att = NOW()";

        $sqlUp = "UPDATE D001E SET " . implode(', ', $sets) . " WHERE D001E_D001_Codigo_Produto = '" . mysql_real_escape_string($sku) . "'";
        
        if (mysql_query($sqlUp)) {
            mysql_query("DELETE FROM D001F WHERE D001F_Id = $idF");
            echo json_encode(['ok' => 1, 'msg' => 'Correção finalizada e baixada com sucesso!']);
        } else {
            echo json_encode(['ok' => 0, 'msg' => 'Erro ao atualizar tabela de melhoria: ' . mysql_error()]);
        }
        exit;
    }

    // [ACTION] PARSE IMPORT CSV (TRIAGEM)
    if (isset($_POST['action']) && $_POST['action'] === 'parse_import_csv_cor') {
        if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] != 0) {
            echo json_encode(['ok' => 0, 'msg' => 'Erro no upload do arquivo.']); exit;
        }
        $tmpName = $_FILES['csv_file']['tmp_name'];
        $handle = fopen($tmpName, 'r');
        if (!$handle) { echo json_encode(['ok' => 0, 'msg' => 'Não foi possível ler o arquivo.']); exit; }
        $header = fgetcsv($handle, 0, ';');
        if (substr($header[0], 0, 3) == pack('CCC', 0xef, 0xbb, 0xbf)) { $header[0] = substr($header[0], 3); }

        $protectedFields = ['D001F_Id', 'D001F_Tipo', 'D001F_Id_Any', 'D001F_D001_Id', 'D001F_D001_Codigo_Produto'];
        $changes = [];

        while (($row = fgetcsv($handle, 0, ';')) !== FALSE) {
            if (count($row) < count($header)) continue;
            $data = array_combine($header, $row);
            foreach($data as $k => $v) { if(substr($v, 0, 2) === '="') $data[$k] = substr($v, 2, -1); }

            $sku = trim($data['D001F_D001_Codigo_Produto']);
            $idF = (int)$data['D001F_Id'];

            if (empty($sku) || $idF <= 0) continue;

            // COMPARA COM D001E (PRODUÇÃO) PARA MOSTRAR DIFERENÇAS NA TRIAGEM
            $sqlE = "SELECT * FROM D001E WHERE D001E_D001_Codigo_Produto = '" . mysql_real_escape_string($sku) . "' LIMIT 1";
            $rsE = mysql_query($sqlE);
            if ($rsE && mysql_num_rows($rsE) > 0) {
                $curr = mysql_fetch_assoc($rsE);
                $diffs = [];
                $fieldsToCheck = [
                    'D001F_Titulo' => 'D001E_Sku_Titulo', 'D001F_Descricao' => 'D001E_Descricao',
                    'D001F_EAN' => 'D001E_EAN', 'D001F_garantia' => 'D001E_garantia',
                    'D001F_peso' => 'D001E_peso', 'D001F_altura' => 'D001E_altura',
                    'D001F_largura' => 'D001E_largura', 'D001F_comprimento' => 'D001E_comprimento'
                ];
                for($i=1;$i<=10;$i++) $fieldsToCheck["D001F_Imagem_$i"] = "D001E_Imagem_$i";

                foreach ($fieldsToCheck as $csvKey => $dbKey) {
                    if (isset($data[$csvKey]) && !in_array($csvKey, $protectedFields)) {
                        $newVal = trim($data[$csvKey]);
                        $oldVal = trim($curr[$dbKey]);
                        
                        if ($csvKey == 'D001F_Descricao') {
                            $newVal = autoFormatHTMLMel($newVal);
                        }

                        if ($newVal != $oldVal) {
                            $fieldName = str_replace('D001F_', '', $csvKey);
                            if($csvKey == 'D001F_Descricao') {
                                $safeOld = htmlspecialchars($oldVal, ENT_QUOTES); 
                                $safeNew = htmlspecialchars($newVal, ENT_QUOTES);
                                $displayHtml = "<button class='btn-diff-view' type='button' onclick='openDescriptionModal(`$safeOld`, `$safeNew`)'>Visualizar Alterações</button>";
                            } elseif($csvKey == 'D001F_Titulo') {
                                $safeOld = htmlspecialchars($oldVal, ENT_QUOTES); $safeNew = htmlspecialchars($newVal, ENT_QUOTES);
                                $displayHtml = "<button class='btn-diff-view' type='button' onclick='openDiffModal(\"$fieldName\", `$safeOld`, `$safeNew`)'>Comparar Título</button>";
                            } else {
                                $displayHtml = "<div class='diff-short'><span class='diff-old'>$oldVal</span> <i class='material-icons' style='font-size:10px'>arrow_forward</i> <span class='diff-new'>$newVal</span></div>";
                            }
                            $diffs[] = ['field' => $fieldName, 'html' => $displayHtml];
                        }
                    }
                }
                
                if (count($diffs) > 0 || !empty($data['D001F_Obs']) || !empty($data['D001F_tags'])) {
                    $payloadData = $data;
                    foreach($protectedFields as $pf) unset($payloadData[$pf]);
                    $payloadData['D001F_Id'] = $idF;
                    $payloadData['D001F_Descricao'] = isset($data['D001F_Descricao']) ? autoFormatHTMLMel($data['D001F_Descricao']) : '';
                    
                    if(count($diffs) == 0) {
                         $diffs[] = ['field' => 'Atualização', 'html' => 'Obs/Tags'];
                    }

                    $changes[] = ['idF' => $idF, 'sku' => $sku, 'title' => $curr['D001E_Sku_Titulo'], 'diffs' => $diffs, 'newData' => $payloadData];
                }
            }
        }
        fclose($handle);

        $html = '';
        if (count($changes) > 0) {
            foreach ($changes as $idx => $c) {
                $diffRows = '';
                foreach ($c['diffs'] as $d) {
                    $diffRows .= "<tr><td class='td-label'>{$d['field']}</td><td class='td-val'>{$d['html']}</td></tr>";
                }
                $jsonPayload = htmlspecialchars(json_encode($c['newData']), ENT_QUOTES, 'UTF-8');
                $html .= "
                <div class='triage-card'>
                    <div class='triage-header'>
                        <label class='triage-check-wrapper'><input type='checkbox' class='triage-check-input' value='$idx' checked><span class='triage-sku'>{$c['sku']}</span></label>
                        <div class='triage-title'>{$c['title']}</div>
                    </div>
                    <div class='triage-body'><table class='diff-table'>$diffRows</table></div>
                    <input type='text' id='payload_$idx' value='$jsonPayload' style='display:none'>
                </div>";
            }
        } else { $html = "<div class='empty-msg'>Nenhuma alteração detectada.</div>"; }
        echo json_encode(['ok' => 1, 'html' => $html, 'count' => count($changes)]);
        exit;
    }

    // [ACTION] COMMIT IMPORT (SALVA APENAS NO RASCUNHO D001F)
    if (isset($_POST['action']) && $_POST['action'] === 'apply_import_batch_cor') {
        $payloads = isset($_POST['rows']) ? $_POST['rows'] : [];
        if (empty($payloads)) { echo json_encode(['ok' => 0, 'msg' => 'Nada selecionado.']); exit; }

        $successCount = 0;
        
        foreach ($payloads as $json) {
            $data = json_decode($json, true);
            if (!$data) continue;

            $idF = (int)$data['D001F_Id'];
            if ($idF <= 0) continue;

            $sets = [];
            $map = [
                'D001F_Titulo' => 'D001F_Titulo', 
                'D001F_Descricao' => 'D001F_Descricao',
                'D001F_EAN' => 'D001F_EAN', 
                'D001F_garantia' => 'D001F_garantia',
                'D001F_peso' => 'D001F_peso', 
                'D001F_altura' => 'D001F_altura',
                'D001F_largura' => 'D001F_largura', 
                'D001F_comprimento' => 'D001F_comprimento',
                'D001F_Obs' => 'D001F_Obs',   
                'D001F_tags' => 'D001F_tags'  
            ];
            for($i=1;$i<=10;$i++) $map["D001F_Imagem_$i"] = "D001F_Imagem_$i";

            foreach ($map as $csvKey => $dbKey) {
                if (isset($data[$csvKey])) {
                    $val = mysql_real_escape_string(trim($data[$csvKey]));
                    $sets[] = "$dbKey = '$val'";
                }
            }
            
            $sets[] = "D001F_ult_att = NOW()";

            if (!empty($sets)) {
                $sqlUp = "UPDATE D001F SET " . implode(', ', $sets) . " WHERE D001F_Id = $idF";
                if (mysql_query($sqlUp)) {
                    $successCount++;
                }
            }
        }
        echo json_encode(['ok' => 1, 'msg' => "$successCount itens atualizados no RASCUNHO (Correção)."]);
        exit;
    }

    // [EXPORTAÇÃO CSV]
    if (isset($_POST['action']) && $_POST['action'] === 'export_csv_cor') {
        if (ob_get_level()) ob_end_clean();
        ini_set('display_errors', 0);
        
        $whereStr = buildWhereCor($_POST);
        $sqlCsv = "SELECT T1.* FROM D001F AS T1 LEFT JOIN D049 ON D049.D049_D001_Id = T1.D001F_D001_Id LEFT JOIN D009 AS T2 ON (T2.D009_D049_Id = D049.D049_Id AND T2.D009_C004_Id = $C004_Id) WHERE $whereStr GROUP BY T1.D001F_Id ORDER BY T1.D001F_Id ASC";
        $rsCsv = mysql_query($sqlCsv);
        
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=correcao_produtos_' . date('YmdHis') . '.csv');
        $out = fopen('php://output', 'w');
        fputs($out, "\xEF\xBB\xBF");
        
        $header = ['D001F_Id','D001F_Tipo','D001F_Id_Any','D001F_D001_Id','D001F_D001_Codigo_Produto','D001F_Titulo','D001F_Marca','D001F_Descricao','D001F_Obs','D001F_tags','D001F_Imagem_1','D001F_Imagem_2','D001F_Imagem_3','D001F_Imagem_4','D001F_Imagem_5','D001F_Imagem_6','D001F_Imagem_7','D001F_Imagem_8','D001F_Imagem_9','D001F_Imagem_10','D001F_EAN','D001F_garantia','D001F_peso','D001F_altura','D001F_largura','D001F_comprimento','D001F_ult_att'];
        fputcsv($out, $header, ';');
        
        if (!function_exists('cleanCSV')) { function cleanCSV($str) { if (is_null($str)) return ''; $str = str_replace(["\r\n", "\r", "\n", "\t"], ' ', $str); return trim($str); } }
        if (!function_exists('excelSafe')) { function excelSafe($val) { $val = cleanCSV($val); if(is_numeric($val) && strlen($val) > 8) return '="' . $val . '"'; return $val; } }
        
        if ($rsCsv) { while ($row = mysql_fetch_assoc($rsCsv)) {
            $line = [
                $row['D001F_Id'], $row['D001F_Tipo'], $row['D001F_Id_Any'], $row['D001F_D001_Id'], 
                excelSafe($row['D001F_D001_Codigo_Produto']), 
                cleanCSV($row['D001F_Titulo']), 
                cleanCSV($row['D001F_Marca']), 
                cleanCSV($row['D001F_Descricao']), 
                cleanCSV($row['D001F_Obs']), 
                cleanCSV($row['D001F_tags']),
                $row['D001F_Imagem_1'],$row['D001F_Imagem_2'],$row['D001F_Imagem_3'],$row['D001F_Imagem_4'],$row['D001F_Imagem_5'],$row['D001F_Imagem_6'],$row['D001F_Imagem_7'],$row['D001F_Imagem_8'],$row['D001F_Imagem_9'],$row['D001F_Imagem_10'], 
                excelSafe($row['D001F_EAN']), 
                cleanCSV($row['D001F_garantia']), 
                cleanCSV($row['D001F_peso']), 
                cleanCSV($row['D001F_altura']), 
                cleanCSV($row['D001F_largura']), 
                cleanCSV($row['D001F_comprimento']), 
                $row['D001F_ult_att']
            ];
            fputcsv($out, $line, ';');
        }}
        fclose($out);
        exit;
    }

    header('Content-Type: application/json; charset=UTF-8');

    // [AÇÃO MODAL VISUALIZADOR] 
    if (isset($_POST['action']) && $_POST['action'] === 'get_details_cor') {
        $skuBusca = isset($_POST['sku']) ? mysql_real_escape_string($_POST['sku']) : '';
        $sqlDet = "SELECT T1.* FROM D001F AS T1 WHERE T1.D001F_D001_Codigo_Produto = '$skuBusca' LIMIT 1";
        $rsDet  = mysql_query($sqlDet);
        if ($rsDet && mysql_num_rows($rsDet) > 0) {
            $row = mysql_fetch_assoc($rsDet);
            $marca = isset($row['D001F_Marca']) ? $row['D001F_Marca'] : 'ND';
            $imgs = []; for ($i = 1; $i <= 10; $i++) if (!empty($row["D001F_Imagem_$i"])) $imgs[] = $row["D001F_Imagem_$i"];
            if(empty($imgs)) $imgs[] = "https://via.placeholder.com/600x600?text=Sem+Imagem";
            
            // PREENCHENDO SPECS CORRETAMENTE
            $specs = [
                'EAN'         => $row['D001F_EAN'] ?? '',
                'Garantia'    => $row['D001F_garantia'] ?? '',
                'Peso'        => $row['D001F_peso'] ? $row['D001F_peso'] . ' kg' : '',
                'Altura'      => $row['D001F_altura'] ? $row['D001F_altura'] . ' cm' : '',
                'Largura'     => $row['D001F_largura'] ? $row['D001F_largura'] . ' cm' : '',
                'Comprimento' => $row['D001F_comprimento'] ? $row['D001F_comprimento'] . ' cm' : '',
            ];
            echo json_encode(['ok' => 1, 'titulo' => $row['D001F_Titulo'], 'sku' => $row['D001F_D001_Codigo_Produto'], 'marca' => $marca, 'desc' => $row['D001F_Descricao'], 'imgs' => $imgs, 'specs' => $specs]);
        } else { echo json_encode(['ok' => 0, 'msg' => 'Produto não encontrado']); }
        exit;
    }

    $whereStr = buildWhereCor($_POST);
    $totalRows = 0;
    $sqlCount = "SELECT COUNT(*) AS total FROM D001F AS T1 LEFT JOIN D049 ON D049.D049_D001_Id = T1.D001F_D001_Id LEFT JOIN D009 AS T2 ON (T2.D009_D049_Id = D049.D049_Id AND T2.D009_C004_Id = $C004_Id) WHERE $whereStr";
    $rsCount = mysql_query($sqlCount);
    if ($rsCount) { $r = mysql_fetch_assoc($rsCount); $totalRows = (int) ($r['total'] ?? 0); }

    $sql = "SELECT T1.*, T2.D009_Frequencia_Venda, T2.D009_Valor_Custo_Unitario, T2.D009_Quantidade_Estoque_Tabela, T2.D009_Quantidade_Estoque_Liquido FROM D001F AS T1 LEFT JOIN D049 ON D049.D049_D001_Id = T1.D001F_D001_Id LEFT JOIN D009 AS T2 ON (T2.D009_D049_Id = D049.D049_Id AND T2.D009_C004_Id = $C004_Id) WHERE $whereStr GROUP BY T1.D001F_Id ORDER BY T1.D001F_Id ASC LIMIT $limit OFFSET $offset";
    $rs = mysql_query($sql);
    $html = "";
    if ($rs) { while ($row = mysql_fetch_assoc($rs)) { $html .= renderCorrectionRowIsolado($row); } }
    echo json_encode(['ok' => 1, 'total' => $totalRows, 'page' => $page, 'pageSize' => $limit, 'html' => $html]);
    exit;
}

// =============================================================================
// [STYLE] CSS
// =============================================================================
$style = <<<STYLE
<style>
    :root { --primary: #0098D3; --primary-hover: #007bb5; --bg-body: #F3F4F6; --bg-card: #FFFFFF; --text-main: #1F2937; --text-sub: #6B7280; --border: #E5E7EB; --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05); --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06); }
    * { box-sizing: border-box; }
    body { font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; background-color: var(--bg-body); margin: 0; padding: 20px; color: var(--text-main); -webkit-font-smoothing: antialiased; }
    .quality-list { max-width: 1600px; margin: 0 auto; position: relative; }
    
    .filter-container { background: var(--bg-card); border-radius: 12px; box-shadow: var(--shadow-md); margin-bottom: 24px; max-width: 1600px; margin: 0 auto 24px auto; border: 1px solid var(--border); overflow: hidden; }
    .filter-header { padding: 16px 20px; display: flex; justify-content: space-between; align-items: center; cursor: pointer; background: #fff; border-bottom: 1px solid transparent; transition: background 0.2s; }
    .filter-header:hover { background: #f9fafb; }
    .filter-title { font-size: 15px; font-weight: 700; color: #374151; display:flex; align-items:center; gap:10px; }
    .filter-icon { color: var(--primary); }
    .filter-body { padding: 20px; background: #fff; border-top: 1px solid var(--border); display: block; animation: fadeIn 0.3s ease; }
    .filter-body.closed { display: none; }
    .f-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 16px; }
    .f-group { display: flex; flex-direction: column; gap: 6px; }
    .f-label { font-size: 11px; font-weight: 700; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em; }
    .f-input { padding: 10px 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 13px; outline: none; transition: all 0.2s; width: 100%; color: #374151; background: #f9fafb; }
    .f-input:focus { border-color: var(--primary); background: #fff; box-shadow: 0 0 0 3px rgba(0, 152, 211, 0.1); }
    .f-actions { display: flex; justify-content: flex-end; margin-top: 20px; padding-top: 15px; border-top: 1px solid #f3f4f6; gap: 12px; flex-wrap: wrap; }
    
    .f-btn-apply, .f-btn-export, .f-btn-import, .f-btn-clear, .f-btn-sync-massa, .f-btn-finish-massa { border: none; padding: 10px 20px; border-radius: 6px; font-weight: 600; cursor: pointer; font-size: 13px; display:flex; align-items:center; gap:8px; transition: all 0.2s; shadow: var(--shadow-sm); }
    .f-btn-apply { background: var(--primary); color: #fff; } .f-btn-apply:hover { background: var(--primary-hover); transform: translateY(-1px); }
    .f-btn-export { background: #fff; color: #374151; border: 1px solid #d1d5db; } .f-btn-export:hover { background: #f3f4f6; border-color: #9ca3af; }
    .f-btn-import { background: #10b981; color: #fff; } .f-btn-import:hover { background: #059669; transform: translateY(-1px); }
    .f-btn-clear { background: #ef4444; color: #fff; } .f-btn-clear:hover { background: #dc2626; transform: translateY(-1px); }
    .f-btn-sync-massa { background: #f59e0b; color: #fff; } .f-btn-sync-massa:hover { background: #d97706; transform: translateY(-1px); }
    
    /* [NOVO] PAGINAÇÃO CORRIGIDA - IGUAL MELHORIA */
    #demoCor { padding: 20px 0; display:none; flex-wrap:wrap; align-items:center; justify-content:center; gap:5px; }
    #demoCor.active { display: flex; }
    #demoCor .pg-btn { border: 1px solid #d1d5db; background:#fff; padding:6px 12px; border-radius:6px; cursor:pointer; font-size:12px; font-weight:600; color:#374151; text-decoration: none; }
    #demoCor .pg-btn:hover { background: #f3f4f6; }
    #demoCor .pg-btn.active { background: var(--primary); border-color: var(--primary); color:#fff; }

    .quality-header, .quality-row { display: grid; grid-template-columns: 30px 90px 70px 1.5fr 1fr 1fr 1.2fr 1fr 100px; gap: 12px; align-items: center; }
    .quality-header { position: sticky; top: 0; z-index: 100; padding: 12px 16px; font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.04em; background: #F3F4F6; border-bottom: 2px solid #e5e7eb; margin-bottom: 5px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); align-items:center; }
    .quality-header > div { text-align: center; } .quality-header > div:nth-child(4) { text-align: left; }
    .quality-row { background: var(--bg-card); border-radius: 8px; padding: 12px 16px; margin-bottom: 12px; border: 1px solid var(--border); transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1); box-shadow: var(--shadow-sm); min-height:85px; height:auto !important; }
    .quality-row:hover { transform: translateY(-2px); box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1); border-color: #cbd5e1; }
    .col-type { display: flex; justify-content: center; padding-top:5px; }
    .badge-type { display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 4px; padding: 6px 4px; border-radius: 6px; font-size: 9px; font-weight: 800; color: #fff; width: 100%; text-align: center; letter-spacing: 0.05em; }
    .badge-type i { font-size: 18px; }
    .badge-type-mod { background: #0ea5e9; border: 1px solid #0284c7; }
    .badge-type-corr { background: #f59e0b; border: 1px solid #d97706; }
    .thumb-box { width: 64px; height: 64px; border-radius: 8px; border: 1px solid #e5e7eb; padding: 2px; background: #fff; cursor: pointer; display: flex; align-items: center; justify-content: center; overflow: hidden; }
    .thumb-box img { width: 100%; height: 100%; object-fit: contain; transition: transform 0.3s; }
    .thumb-box:hover img { transform: scale(1.1); }
    .col-info { display: flex; flex-direction: column; gap: 6px; overflow: visible !important; height:auto !important; justify-content: center; }
    .prod-title { font-size: 13px; font-weight: 600; color: #111827; line-height: 1.4; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
    .prod-sub { display: flex; gap: 6px; align-items: center; flex-wrap: wrap; }
    .badge-sku { font-size: 10px; color: #4b5563; background: #f3f4f6; padding: 2px 6px; border-radius: 4px; font-family: 'Monaco', monospace; border: 1px solid #e5e7eb; }
    .badge-brand {font-size: 10px; color: #374151; background: #f3f4f6; padding: 2px 6px; border-radius: 4px; border: 1px solid #d1d5db;font-weight: 700;}
    .badge-any { font-size: 10px; color: #fff; background: #FF600F; padding: 2px 6px; border-radius: 4px; font-family: monospace; border: 1px solid #e65100; font-weight: 700; }
    .metric-item { display: flex; grid-template-columns: 1fr 1fr; gap: 4px 10px; background: #f9fafb; padding: 6px 10px; border-radius: 6px; border: 1px solid #f3f4f6; align-self: center; justify-content: space-between; align-items: center; font-size: 11px; padding-top: 10px; padding-bottom: 10px;}
    .m-lbl { color: #9ca3af; font-size: 11px; font-weight: 600; text-transform: uppercase; margin-right: 50px; }
    .m-val { color: #374151; }
    .m-val.text-blue { color: var(--primary); }
    .col-box-scroll { font-size: 11px; color: #4b5563; max-height: 70px; overflow-y: auto; background: #fff; padding: 4px 6px; line-height: 1.5; border-radius: 6px; border: 1px solid #f3f4f6; text-align: left; }
    .content-spec { max-height: none !important; height: auto !important; overflow: visible !important; display: flex; flex-direction: column; justify-content: center; }
    .content-spec span { display: block; border-bottom: 1px solid #f9fafb; padding: 2px 0; }
    .content-desc { min-height: 150px; overflow-y: auto; }
    .col-box-scroll::-webkit-scrollbar { width: 4px; } .col-box-scroll::-webkit-scrollbar-track { background: #f1f1f1; } .col-box-scroll::-webkit-scrollbar-thumb { background: #d1d5db; border-radius: 4px; }
    .col-actions { display: flex; justify-content: center; gap: 6px; align-items: center; }
    .btn-action-icon { background: transparent; border: 1px solid #e5e7eb; color: #6b7280; width: 32px; height: 32px; border-radius: 6px; cursor: pointer; transition: all 0.2s; display: flex; align-items: center; justify-content: center; }
    .btn-action-icon:hover { background: #fffbeb; color: #d97706; border-color: #fcd34d; transform: scale(1.05); }
    .btn-edit:hover { background: #e0f2fe; color: #0098D3; border-color: #0098D3; }
    .btn-sync:hover { background: #fff7ed; color: #f59e0b; border-color: #f59e0b; }
    .btn-finish:hover { background: #f0fdf4; color: #16a34a; border-color: #16a34a; }

    /* Modal EDITOR */
    .h-modal-content { background: #fff; width: 100%; max-width: 1200px; height: 90vh; border-radius: 12px; position: relative; display: flex; flex-direction: column; overflow: hidden; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25); }
    .h-modal-header { padding: 16px 24px; border-bottom: 1px solid #e5e7eb; background: #fff; display: flex; justify-content: space-between; align-items: flex-start; z-index: 10; position:relative; min-height:60px; height:auto; flex-wrap:wrap; }
    .h-header-left { display: flex; align-items: center; gap: 20px; flex: 1; flex-wrap:wrap; }
    .h-modal-title { font-size: 16px; font-weight: 800; color: #111827; display: flex; align-items: center; gap: 8px; white-space:nowrap; margin-right:15px; margin-bottom:5px; }
    .h-modal-body { flex: 1; display: flex; overflow: hidden; background: #f9fafb; }
    .h-col-left { width: 300px; background: #f3f4f6; border-right: 1px solid #e5e7eb; display: flex; flex-direction: column; overflow-y: auto; padding: 20px; gap: 15px; }
    .h-col-right { flex: 1; background: #fff; padding: 30px; overflow-y: auto; }
    .h-modal-footer { padding: 16px 24px; border-top: 1px solid #e5e7eb; background: #fff; display: flex; justify-content: flex-end; gap: 12px; z-index: 10; }
    .h-section-title { font-size: 11px; font-weight: 700; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 10px; display: block; }
    .h-card-img { background: #fff; border: 1px solid #e5e7eb; border-radius: 8px; padding: 10px; display: flex; align-items: center; gap: 10px; transition: 0.2s; box-shadow: 0 1px 2px rgba(0,0,0,0.03); }
    .h-thumb { width: 48px; height: 48px; border-radius: 6px; object-fit: contain; border: 1px solid #f3f4f6; flex-shrink: 0; }
    .h-input-img { border: none; background: transparent; font-size: 12px; color: #374151; width: 100%; outline: none; }
    .h-main-input { width: 100%; padding: 12px 16px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 14px; font-weight: 600; color: #1f2937; transition: all 0.2s; }
    .h-main-input:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(0, 152, 211, 0.1); outline: none; }
    .h-grid-specs { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; margin-bottom: 24px; }
    .h-field-group { display: flex; flex-direction: column; gap: 5px; }
    .h-label { font-size: 12px; font-weight: 600; color: #4b5563; }
    .h-input-sm { padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 13px; color: #374151; transition: 0.2s; width: 100%; }
    .h-input-sm:focus { border-color: var(--primary); outline: none; }
    .h-editor-box { border: 1px solid #d1d5db; border-radius: 8px; overflow: hidden; display: flex; flex-direction: column; min-height: 300px; }
    .h-toolbar { background: #f9fafb; border-bottom: 1px solid #e5e7eb; padding: 8px 12px; display: flex; gap: 8px; }
    .h-tool-btn { border: 1px solid transparent; background: transparent; padding: 6px 12px; border-radius: 6px; font-size: 12px; font-weight: 600; color: #6b7280; cursor: pointer; display: flex; align-items: center; gap: 6px; }
    .h-tool-btn.active { background: #e0f2fe; color: #0098D3; border-color: #bae6fd; }
    .h-content-area { flex: 1; position: relative; background: #fff; }
    #editDescPreview { padding: 20px; height: 100%; overflow-y: auto; outline: none; font-size: 14px; line-height: 1.6; color: #374151; }
    #editDescCode { width: 100%; height: 100%; padding: 20px; border: none; resize: none; font-family: 'Menlo', 'Monaco', monospace; font-size: 13px; background: #1e293b; color: #e2e8f0; display: none; }
    .h-btn { padding: 10px 20px; border-radius: 8px; font-size: 13px; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 8px; transition: 0.2s; border: 1px solid transparent; }
    .h-btn-cancel { background: #fff; border-color: #d1d5db; color: #374151; }
    .h-btn-save { background: #10b981; color: #fff; box-shadow: 0 1px 2px rgba(0,0,0,0.05); }

    /* Modal TRIAGEM (DESIGN REFEITO) */
    #modalImportCor .h-modal-content { max-width: 900px; }
    .triage-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); overflow: hidden; }
    .triage-header { background: #f9fafb; padding: 12px 16px; border-bottom: 1px solid #e5e7eb; display: flex; align-items: center; justify-content: space-between; }
    .triage-check-wrapper { display: flex; align-items: center; gap: 10px; cursor: pointer; }
    .triage-check-input { width: 18px; height: 18px; cursor: pointer; }
    .triage-sku { font-weight: 700; font-family: monospace; color: #111827; font-size: 13px; background: #e5e7eb; padding: 2px 6px; border-radius: 4px; }
    .triage-title { font-size: 12px; color: #6b7280; max-width: 500px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .triage-body { padding: 15px; }
    .diff-table { width: 100%; border-collapse: collapse; font-size: 12px; }
    .diff-table th { text-align: left; padding: 8px; border-bottom: 1px solid #f3f4f6; color: #9ca3af; font-size: 10px; text-transform: uppercase; }
    .diff-table td { padding: 8px; vertical-align: top; border-bottom: 1px solid #f3f4f6; }
    .td-label { font-weight: 700; color: #374151; width: 120px; }
    .diff-short { display: flex; align-items: center; gap: 10px; font-size: 13px; }
    .diff-old { color: #ef4444; text-decoration: line-through; background: #fef2f2; padding: 2px 6px; border-radius: 4px; }
    .diff-new { color: #10b981; font-weight: 700; background: #ecfdf5; padding: 2px 6px; border-radius: 4px; }
    .h-btn-xs { padding: 6px 12px; background: #e0f2fe; color: #0284c7; border: 1px solid #bae6fd; border-radius: 6px; font-size: 11px; font-weight: 700; cursor: pointer; }
    .h-btn-xs:hover { background: #bae6fd; }
    .btn-diff-view { border: 1px solid #bae6fd; background: #e0f2fe; color: #0284c7; padding: 6px 12px; border-radius: 6px; cursor: pointer; font-size: 11px; font-weight: 600; display: inline-flex; align-items: center; gap: 5px; }
    .diff-overlay { position: fixed; top:0; left:0; width:100%; height:100%; z-index:100000; background:rgba(0,0,0,0.5); display:none; justify-content:center; align-items:center; }
    .diff-box-modal { background:#fff; width:900px; height:80vh; border-radius:8px; display:flex; flex-direction:column; box-shadow:0 25px 50px rgba(0,0,0,0.25); }
    .diff-header { padding:20px; border-bottom:1px solid #eee; display:flex; justify-content:space-between; align-items:center; }
    .diff-title { font-size:16px; font-weight:700; color:#111827; }
    .diff-content { flex:1; padding:20px; overflow-y:auto; display:flex; flex-direction:column; gap:20px; }
    .diff-container-box { border: 1px solid #e5e7eb; border-radius: 8px; overflow: hidden; background: #fff; }
    .diff-box-head { background: #f8fafc; border-bottom: 1px solid #e5e7eb; padding: 10px 15px; font-size: 12px; font-weight: 700; color: #64748b; text-transform: uppercase; }
    .diff-box-body { padding: 20px; font-size: 14px; line-height: 1.6; color: #334155; }
    .html-view-content { padding: 20px; }
    .diff-word-added { background-color: #dcfce7; color: #166534; border-bottom: 2px solid #22c55e; padding: 0 2px; }
    .diff-word-removed { background-color: #fee2e2; color: #991b1b; text-decoration: line-through; opacity: 0.7; padding: 0 2px; }

    /* TAGS SYSTEM STYLES */
    .h-tags-wrapper { display: flex; align-items: center; gap: 5px; flex-wrap:wrap; margin-bottom:5px; max-width:100%; }
    .btn-add-tag { background: #f3f4f6; border: 1px dashed #9ca3af; color: #6b7280; width: 26px; height: 26px; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; transition:0.2s; font-weight:bold; font-size:14px; line-height:1; flex-shrink:0; }
    .btn-add-tag:hover { background: #e5e7eb; color: #374151; border-color: #6b7280; }
    .tag-container { display: flex; gap: 6px; flex-wrap: wrap; }
    
    /* [FIX] Cor Padrão e FLEX-SHRINK:0 para não cortar */
    .tag-pill { background-color: #6b7280; color: #fff; padding: 3px 8px; border-radius: 12px; display: flex; align-items: center; gap: 4px; box-shadow: 0 1px 2px rgba(0,0,0,0.1); cursor:default; font-size: 10px; font-weight: 700; flex-shrink: 0; white-space:nowrap; }
    
    .tag-pill i { font-size: 10px; cursor: pointer; opacity: 0.7; }
    .tag-pill i:hover { opacity: 1; }
    
    .add-tag-dropdown-area { position: relative; display:inline-block; }
    .tag-menu { position: absolute; top: 110%; left: 0; background: #fff; border: 1px solid #e5e7eb; box-shadow: 0 4px 15px -3px rgba(0,0,0,0.15); border-radius: 6px; padding: 5px; display: none; z-index: 100; min-width: 160px; flex-direction: column; gap: 2px; }
    .tag-menu.active { display: flex; animation: fadeIn 0.1s ease; }
    .tag-option { padding: 6px 10px; font-size: 11px; font-weight: 600; cursor: pointer; border-radius: 4px; transition: 0.1s; color: #374151; display:flex; align-items:center; gap:8px; }
    .tag-option:hover { background: #f3f4f6; }
    .tag-option.hidden { display: none !important; }
    .tag-dot { width: 8px; height: 8px; border-radius: 50%; display: inline-block; }

    /* CORES DAS TAGS */
    .tag-imagem { background-color: #8b5cf6; } 
    .tag-titulo { background-color: #3b82f6; } 
    .tag-descricao { background-color: #10b981; } 
    .tag-pesos-e-dimensao { background-color: #f97316; } 
    .tag-match { background-color: #ef4444; } 
    .tag-voltagem { background-color: #eab308; } 
    .tag-cor { background-color: #ec4899; } 
    
    .dot-imagem { background-color: #8b5cf6; }
    .dot-titulo { background-color: #3b82f6; }
    .dot-descricao { background-color: #10b981; }
    .dot-pesos-e-dimensao { background-color: #f97316; }
    .dot-match { background-color: #ef4444; }
    .dot-voltagem { background-color: #eab308; }
    .dot-cor { background-color: #ec4899; }

    /* [NOVO] MODAIS MODERNOS */
    @keyframes hModalPop { from { opacity: 0; transform: scale(0.95); } to { opacity: 1; transform: scale(1); } }
    .h-dialog-box {
        background: #fff; width: 100%; max-width: 400px; height: auto;
        border-radius: 12px; padding: 25px;
        display: flex; flex-direction: column; align-items: center; text-align: center;
        box-shadow: 0 20px 40px -5px rgba(0,0,0,0.2), 0 0 0 1px rgba(0,0,0,0.05);
        animation: hModalPop 0.2s cubic-bezier(0.16, 1, 0.3, 1);
    }
    .modal-overlay { 
        display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; 
        background: rgba(255,255,255,0.4); z-index: 99999; 
        justify-content: center; align-items: center; 
        backdrop-filter: blur(5px); padding: 20px; 
    }
    .close-modal { font-size: 24px; cursor: pointer; transition: 0.2s; color: #9ca3af; display:flex; align-items:center; justify-content:center; width:30px; height:30px; border-radius:50%; } 
    .close-modal:hover { color: #1f2937; background:#f3f4f6; }
    
    @media (max-width: 1400px) { .quality-header, .quality-row { grid-template-columns: 30px 90px 70px 1.5fr 1fr 1.2fr 1fr 100px; gap: 8px; } }
    @media (max-width: 1200px) { 
        .quality-header { display: none; }
        .quality-row { grid-template-columns: 70px 1fr 1fr; grid-template-areas: "thumb info info" "thumb metrics metrics" "desc desc desc" "spec spec spec" "action action action"; gap: 10px; height: auto !important; }
        .col-type { display:none; } .thumb-box { grid-area: thumb; } .col-info { grid-area: info; } .col-metrics { grid-area: metrics; } .content-desc { grid-area: desc; max-height: 60px; } .content-spec { grid-area: spec; max-height: 60px; } .col-actions { grid-area: action; justify-content: flex-end; padding-right: 10px; }
    }
    @keyframes fadeIn { from { opacity: 0; transform: translateY(-5px); } to { opacity: 1; transform: translateY(0); } }

    /* CSS DO MODAL DE VISUALIZAÇÃO COM SUFIXO -COR */
    .vis-thumbs-cor { width: 110px; background: #f9fafb; padding: 15px; overflow-y: auto; display: flex; flex-direction: column; gap: 10px; border-right: 1px solid #e5e7eb; }
    .vis-mini-cor { width: 100%; height: 80px; object-fit: contain; border-radius: 6px; cursor: pointer; background: #fff; border: 1px solid #e5e7eb; transition: all 0.2s; }
    .vis-mini-cor.active, .vis-mini-cor:hover { border-color: var(--primary); box-shadow: 0 0 0 2px rgba(0, 152, 211, 0.2); }
    .vis-main-cor { flex: 1; display: flex; justify-content: center; align-items: center; background: #fff; padding: 30px; position: relative; }
    .vis-main-cor img { max-width: 100%; max-height: 100%; object-fit: contain; filter: drop-shadow(0 10px 8px rgba(0,0,0,0.04)); }
    .vis-info-cor { width: 360px; border-left: 1px solid #e5e7eb; padding: 30px; overflow-y: auto; background: #fff; display: flex; flex-direction: column; gap: 20px; }
    .vis-h1-cor { font-size: 20px; font-weight: 800; margin: 0; color: #111827; line-height: 1.3; }
    .vis-meta-cor { font-size: 13px; color: #6b7280; padding-bottom: 15px; border-bottom: 1px solid #e5e7eb; }
    .vis-meta-cor strong { color: #374151; }
    .vis-header-row-cor { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; font-weight: 700; font-size: 12px; color: #374151; text-transform: uppercase; letter-spacing: 0.05em; }
    .vis-desc-box-cor { font-size: 13px; line-height: 1.6; color: #4b5563; background: #f9fafb; padding: 15px; border-radius: 8px; border: 1px solid #e5e7eb; max-height: 200px; overflow-y: auto; }
    .vis-specs-table-cor { width: 100%; border-collapse: collapse; }
    .vis-specs-table-cor td { padding: 8px 0; border-bottom: 1px solid #f3f4f6; color: #4b5563; font-size: 13px; }
    .vis-specs-table-cor td strong { color: #1f2937; margin-right: 5px; }
    .vis-btn-print-cor { width: 100%; padding: 12px; background: #1f2937; color: #fff; border: none; border-radius: 8px; font-size: 13px; font-weight: 600; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; transition: background 0.2s; margin-top: auto; }
    .vis-btn-print-cor:hover { background: #111827; }

    /* [FIX] CLASSE QUE FALTAVA PARA O VISUALIZADOR */
    .modal-content { 
        background: #fff; width: 100%; max-width: 1100px; height: 85vh; 
        border-radius: 12px; position: relative; display: flex; 
        overflow: hidden; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25); 
    }

</style>
<link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
STYLE;
if (!$apiMode) echo $style;

echo "
<div class='filter-container'>
    <div class='filter-header' onclick='toggleFilterBodyCor()'>
        <div class='filter-title'><i class='material-icons filter-icon'>tune</i> Filtros</div>
        <i class='material-icons filter-chevron' id='filterChevronCor'>expand_more</i>
    </div>
    <div class='filter-body' id='filterBodyCor'>
        <div class='f-grid'>
            <div class='f-group'><label class='f-label'>SKU / Cód</label><input type='text' id='cor_sku' class='f-input'></div>
            <div class='f-group'><label class='f-label'>ID AnyMarket</label><input type='text' id='f_id_any_cor' class='f-input'></div>
            <div class='f-group'><label class='f-label'>Título</label><input type='text' id='cor_tit' class='f-input'></div>
            <div class='f-group'><label class='f-label'>Marca</label><input type='text' id='cor_mar' class='f-input'></div>
            <div class='f-group'><label class='f-label'>Tipo</label><select id='cor_tipo' class='f-input'><option value=''>Todos</option><option value='mod'>Modificação</option><option value='corr'>Correção</option></select></div>
            <div class='f-group'><label class='f-label'>Descrição</label><input type='text' id='cor_desc' class='f-input'></div>
            <div class='f-group'><label class='f-label'>Especs</label><input type='text' id='cor_spec' class='f-input'></div>
            <div class='f-group'><label class='f-label'>Est. Líquido</label><input type='text' id='cor_est_liq' class='f-input'></div>
            <div class='f-group'><label class='f-label'>Est. Tabela</label><input type='text' id='cor_est_tab' class='f-input'></div>
            <div class='f-group'><label class='f-label'>Frequência</label><input type='text' id='cor_freq' class='f-input'></div>
            <div class='f-group'><label class='f-label'>Custo</label><input type='text' id='cor_custo' class='f-input'></div>
            
            <div class='f-group'><label class='f-label'>Tags</label><select id='cor_tags' class='f-input'><option value=''>Todas</option><option value='1'>Imagem</option><option value='2'>Título</option><option value='3'>Descrição</option><option value='4'>Pesos e Dimensão</option><option value='5'>Match</option><option value='6'>Voltagem</option><option value='7'>Cor</option></select></div>
        </div>
        <div class='f-actions'>
            <button class='f-btn-apply' onclick='applyFiltersCor()' title='Aplicar Filtros'><i class='material-icons'>search</i></button>
            <button class='f-btn-clear' onclick='clearFiltersCor()' title='Limpar'><i class='material-icons'>backspace</i></button>
            <button class='f-btn-sync-massa' onclick='syncAnyMarketMassa()' title='Atualizar Selecionados'><i class='material-icons'>sync</i></button>
            <button class='f-btn-export' onclick='exportCSVCor()' title='Exportar CSV'><i class='material-icons'>file_download</i></button>
            <button class='f-btn-import' onclick='triggerImportCor()' title='Importar CSV'><i class='material-icons'>upload_file</i></button>
            <input type='file' id='fileImportCor' style='display:none' accept='.csv' onchange='processFileCor(this)'>
        </div>
    </div>
</div>";

echo "<div class='quality-list'>";
echo "<div class='quality-header'>
        <div style='cursor:pointer' onclick='toggleSelectAllCor()' title='Selecionar Todos'><i class='material-icons' style='font-size:16px'>check_box</i></div>
        <div>Tipo</div><div>Foto</div><div>Produto / Marca</div><div>Obs</div><div>Métricas</div><div>Descrição</div><div>Especificações</div><div class='center'>Ação</div>
      </div>";
echo "<div id='contentCor'><div class='start-msg' style='text-align:center; padding:50px; color:#9ca3af;'><i class='material-icons' style='font-size:48px; margin-bottom:10px; display:block;'>search</i><h2 style='font-size:18px; margin:0;'>Comece sua análise</h2><p>Utilize os filtros acima para carregar os produtos.</p></div></div>";

$ajaxUrl  = isset($_SERVER['REQUEST_URI']) ? htmlspecialchars($_SERVER['REQUEST_URI'], ENT_QUOTES, 'UTF-8') : '';
// sysDivId não pode ser fixo aqui se veio de fora, mas o default serve
$sysDivId = isset($g['divId']) && $g['divId'] != "" ? $g['divId'] : 'contentCor';

echo "<input type='hidden' id='hardness_total_cor' value='0'>";
echo "<input type='hidden' id='hardness_pageSize_cor' value='" . (int) $limit . "'>";
echo "<input type='hidden' id='hardness_ajaxUrl_cor' value='" . $ajaxUrl . "'>";
// [CORREÇÃO] OUTPUT DOS HIDDEN INPUTS DE CONTEXTO
echo "<input type='hidden' id='sys_base_divRoot_cor' value='" . $gDivRoot . "'>";
echo "<input type='hidden' id='sys_base_divId_cor' value='" . $gDivId . "'>";
echo "<div id='demoCor'></div></div>";
?>

<div id="modalVisCor" class="modal-overlay" onclick="if(event.target==this) fecharVisCor()">
    <div class="modal-content printable-area">
        <span class="close-modal" onclick="fecharVisCor()">×</span>
        <div class="vis-thumbs-cor" id="visThumbsCor"></div>
        <div class="vis-main-cor"><img id="visHeroCor" src=""></div>
        <div class="vis-info-cor">
            <div><h1 class="vis-h1-cor"><span id="visTitleCor">--</span></h1><div class="vis-meta-cor">SKU: <strong id="visSkuCor">--</strong> | Marca: <strong id="visBrandCor">--</strong></div></div>
            <div><div class="vis-header-row-cor"><span>Descrição</span></div><div class="vis-desc-box-cor" id="visDescCor"></div></div>
            <div class="vis-specs-container-cor"><div class="vis-header-row-cor" style="margin-top:10px"><span>Especificações</span></div><div id="visSpecsContentCor"></div></div>
            <button class="vis-btn-print-cor" onclick="imprimirConteudoModalCor()"><i class="material-icons">print</i> Imprimir Ficha Técnica</button>
        </div>
    </div>
</div>

<div id="modalEditCor" class="modal-overlay">
    <div class="h-modal-content">
        <div class="h-modal-header">
            <div class="h-header-left">
                <div class="h-modal-title"><i class="material-icons" style="color:var(--primary)">edit_note</i> Editor de Anúncio</div>
                
                <div class="h-tags-wrapper">
                    <input type="hidden" id="editTags">
                    <div class="tag-container" id="tagListContainer"></div>
                    
                    <div class="add-tag-dropdown-area">
                        <div class="btn-add-tag" onclick="toggleTagMenu()" title="Adicionar Tag">+</div>
                        <div class="tag-menu" id="tagMenu">
                            <div class="tag-option" data-val="1" onclick="addTag(1)"><span class="tag-dot" style="background:#8b5cf6"></span> IMAGEM</div>
                            <div class="tag-option" data-val="2" onclick="addTag(2)"><span class="tag-dot" style="background:#3b82f6"></span> TÍTULO</div>
                            <div class="tag-option" data-val="3" onclick="addTag(3)"><span class="tag-dot" style="background:#10b981"></span> DESCRIÇÃO</div>
                            <div class="tag-option" data-val="4" onclick="addTag(4)"><span class="tag-dot" style="background:#f97316"></span> PESOS E DIMENSÃO</div>
                            <div class="tag-option" data-val="5" onclick="addTag(5)"><span class="tag-dot" style="background:#ef4444"></span> MATCH</div>
                            <div class="tag-option" data-val="6" onclick="addTag(6)"><span class="tag-dot" style="background:#eab308"></span> VOLTAGEM</div>
                            <div class="tag-option" data-val="7" onclick="addTag(7)"><span class="tag-dot" style="background:#ec4899"></span> COR</div>
                        </div>
                    </div>
                </div>
            </div>

            <span class="close-modal" onclick="fecharEditorCor()">×</span>
        </div>
        <div class="h-modal-body">
            <div class="h-col-left">
                <span class="h-section-title">Imagens do Anúncio</span>
                <div id="editImgsContainer" style="display:flex; flex-direction:column; gap:10px;"></div>
            </div>
            <div class="h-col-right">
                <input type="hidden" id="editIdCor"><input type="hidden" id="editSkuCor">
                <div class="h-field-group" style="margin-bottom:20px;">
                    <label class="h-label">Título do Produto</label>
                    <input type="text" id="editTitulo" class="h-main-input" placeholder="Título completo">
                </div>
                <div class="h-grid-specs">
                    <div class="h-field-group"><label class="h-label">EAN</label><input type="text" id="editEan" class="h-input-sm"></div>
                    <div class="h-field-group"><label class="h-label">Garantia</label><input type="text" id="editGar" class="h-input-sm"></div>
                    <div class="h-field-group"><label class="h-label">Peso (kg)</label><input type="text" id="editPeso" class="h-input-sm"></div>
                    <div class="h-field-group"><label class="h-label">Alt (cm)</label><input type="text" id="editAlt" class="h-input-sm"></div>
                    <div class="h-field-group"><label class="h-label">Larg (cm)</label><input type="text" id="editLarg" class="h-input-sm"></div>
                    <div class="h-field-group"><label class="h-label">Comp (cm)</label><input type="text" id="editComp" class="h-input-sm"></div>
                </div>
                <div style="flex:1; display:flex; flex-direction:column;">
                    <label class="h-label" style="margin-bottom:5px;">Descrição</label>
                    <div class="h-editor-box">
                        <div class="h-toolbar">
                            <button class="h-tool-btn active" id="btnDescVisual" onclick="toggleDescMode('visual')"><i class="material-icons" style="font-size:14px">visibility</i> Visual</button>
                            <button class="h-tool-btn" id="btnDescCode" onclick="toggleDescMode('code')"><i class="material-icons" style="font-size:14px">code</i> HTML / Código</button>
                        </div>
                        <div class="h-content-area">
                            <div id="editDescPreview" contenteditable="true"></div>
                            <input type="text" id="editDescCode">
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="h-modal-footer">
            <button class="h-btn h-btn-cancel" onclick="fecharEditorCor()">Cancelar</button>
            <button class="h-btn h-btn-save" onclick="salvarEdicaoCor()"><i class="material-icons" style="font-size:16px; color:#fff;">save</i> Salvar Rascunho</button>
        </div>
    </div>
</div>

<div id="modalImportCor" class="modal-overlay">
    <div class="h-modal-content" style="height: 80vh;">
        <div class="h-modal-header">
            <div class="h-modal-title"><i class="material-icons" style="color:#10b981">playlist_add_check</i> Revisão de Importação</div>
            <span class="close-modal" onclick="jQuery('#modalImportCor').fadeOut(200)">×</span>
        </div>
        <div class="h-modal-body" style="background:#f9fafb; padding:20px;">
            <div style="flex:1; overflow-y:auto; padding-right:10px;" id="triageBodyCor">
                </div>
        </div>
        <div class="h-modal-footer">
            <div style="margin-right:auto; font-size:12px; color:#6b7280; display:flex; align-items:center; gap:5px;">
                <i class="material-icons" style="font-size:14px">info</i> <span>Os itens marcados serão atualizados no banco e removidos da lista.</span>
            </div>
            <button class="h-btn h-btn-cancel" onclick="jQuery('#modalImportCor').fadeOut(200)">Cancelar</button>
            <button class="h-btn h-btn-save" onclick="confirmImportCor()">Confirmar Importação</button>
        </div>
    </div>
</div>

<div id="diffModal" class="diff-overlay">
    <div class="diff-box-modal">
        <div class="diff-header">
            <span class="diff-title" id="diffFieldTitle">Visualização</span>
            <i class="material-icons" style="cursor:pointer" onclick="jQuery('#diffModal').fadeOut(100)">close</i>
        </div>
        <div class="diff-content" id="diffModalContent">
            </div>
    </div>
</div>

<div id="hModalAlert" class="modal-overlay">
    <div class="h-dialog-box">
        <div style="margin-bottom:15px; color:#f59e0b;"><i class="material-icons" style="font-size:36px">info</i></div>
        <h2 id="hAlertTitle" style="font-size:18px; font-weight:800; color:#111827; margin:0 0 10px 0;">Aviso</h2>
        <p id="hAlertMsg" style="font-size:14px; color:#4b5563; margin-bottom:25px; line-height:1.5;"></p>
        <button class="h-btn h-btn-save" style="justify-content:center; width:100%;" onclick="closeHAlert()">OK, Entendi</button>
    </div>
</div>

<div id="hModalConfirm" class="modal-overlay">
    <div class="h-dialog-box">
        <div style="margin-bottom:15px; color:#0098D3;"><i class="material-icons" style="font-size:36px">help_outline</i></div>
        <h2 id="hConfirmTitle" style="font-size:18px; font-weight:800; color:#111827; margin:0 0 10px 0;">Confirmação</h2>
        <p id="hConfirmMsg" style="font-size:14px; color:#4b5563; margin-bottom:25px; line-height:1.5;"></p>
        <div style="display:flex; gap:12px; justify-content:center; width:100%;">
            <button class="h-btn h-btn-cancel" style="justify-content:center; flex:1;" onclick="closeHConfirm()">Cancelar</button>
            <button id="btnHConfirmAction" class="h-btn h-btn-save" style="justify-content:center; flex:1;">Confirmar</button>
        </div>
    </div>
</div>

<div id="modalProgress" class="modal-overlay" style="z-index: 100000;">
  <div class="h-dialog-box" style="text-align:left; width:400px;">
    <h3 style="margin:0 0 10px; color:#111827; font-size:16px; font-weight:700;">Processando...</h3>
    <div style="width:100%; background:#e5e7eb; height:20px; border-radius:10px; overflow:hidden;">
       <div id="progBarFill" style="width:0%; height:100%; background:#f59e0b; transition:width 0.2s;"></div>
    </div>
    <div id="progText" style="font-size:12px; color:#6b7280; margin-top:8px; font-weight:600; text-align:center;">0 / 0</div>
  </div>
</div>

<script>
    // --- FUNÇÕES DE SELEÇÃO ---
    var allSelectedCor = false;
    function toggleSelectAllCor() {
        allSelectedCor = !allSelectedCor;
        jQuery('.row-check').prop('checked', allSelectedCor);
    }

    // --- SINCRONIZAÇÃO ANYMARKET ---
    function syncAnyMarketItem(id) {
        showHConfirm('Atualizar Any', 'Deseja buscar os dados mais recentes deste item na AnyMarket?', function() {
            var url = jQuery('#hardness_ajaxUrl_cor').val();
            var sysId = jQuery('#sys_base_divId_cor').val();
            // [CORREÇÃO] Recuperando e passando o contexto correto
            var sysRoot = jQuery('#sys_base_divRoot_cor').val();

            if (sysId) jQuery('#' + sysId).showLoading();

            jQuery.ajax({
                url: url, type: 'POST', dataType: 'json',
                data: { ajax: 1, action: 'sync_anymarket_item', id: id, sys_divRoot: sysRoot, sys_divId: sysId },
                success: function(res) {
                    if (res.ok) { showHAlert('Sucesso', res.msg); appCor.loadData(1); } 
                    else { showHAlert('Erro', res.msg); }
                },
                error: function() { showHAlert('Erro', 'Erro de comunicação.'); },
                complete: function() { if (sysId) jQuery('#' + sysId).hideLoading(); }
            });
        });
    }

    function syncAnyMarketMassa() {
        var checked = jQuery('.row-check:checked');
        if (checked.length === 0) { showHAlert('Aviso', 'Selecione pelo menos um item.'); return; }
        if (checked.length > 40) { showHAlert('Aviso', 'Selecione no máximo 40 itens por vez.'); return; }

        var ids = [];
        checked.each(function() { ids.push(jQuery(this).val()); });

        showHConfirm('Atualizar em Massa', 'Deseja sincronizar os ' + ids.length + ' itens selecionados com a AnyMarket?', function() {
            // Abre Modal Progresso
            jQuery('#modalProgress').fadeIn(200).css('display', 'flex');
            
            var total = ids.length;
            var current = 0;
            var successes = 0;
            var errors = 0;
            
            // [CORREÇÃO] Variáveis de contexto para massa
            var url = jQuery('#hardness_ajaxUrl_cor').val();
            var sysId = jQuery('#sys_base_divId_cor').val();
            var sysRoot = jQuery('#sys_base_divRoot_cor').val();

            function processQueue() {
                if (ids.length === 0) {
                    // Fim
                    setTimeout(function(){
                         jQuery('#modalProgress').fadeOut(200);
                         showHAlert('Concluído', 'Processo finalizado. Sucessos: ' + successes + ', Erros: ' + errors);
                         appCor.loadData(1);
                    }, 500);
                    return;
                }

                var id = ids.shift(); // Pega o próximo

                jQuery.ajax({
                    url: url, type: 'POST', dataType: 'json',
                    data: { ajax: 1, action: 'sync_anymarket_item', id: id, sys_divRoot: sysRoot, sys_divId: sysId }, // Reusa a ação individual com contexto
                    success: function(res) {
                        if(res.ok) successes++; else errors++;
                    },
                    error: function() { errors++; },
                    complete: function() {
                        current++;
                        var pct = Math.round((current / total) * 100);
                        jQuery('#progBarFill').css('width', pct + '%');
                        jQuery('#progText').text(current + ' / ' + total);
                        processQueue(); // Chama o próximo
                    }
                });
            }
            
            processQueue(); // Inicia
        });
    }

    function toggleFilterBodyCor() {
        var b = document.getElementById('filterBodyCor');
        var c = document.getElementById('filterChevronCor');
        if (b.classList.contains('closed')) { b.classList.remove('closed'); c.style.transform = 'rotate(0deg)'; } else { b.classList.add('closed'); c.style.transform = 'rotate(-90deg)'; }
    }
    
    // [PAGINAÇÃO CORRIGIDA] - Usa o estilo < e >
    var pagerCor = {
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
    
    var appCor = {
        getFilters: function() {
            return {
                f_sku: jQuery('#cor_sku').val(), f_id_any: jQuery('#f_id_any_cor').val(), f_tit: jQuery('#cor_tit').val(), f_mar: jQuery('#cor_mar').val(), f_tipo: jQuery('#cor_tipo').val(), f_desc: jQuery('#cor_desc').val(), f_spec: jQuery('#cor_spec').val(), f_est_liq: jQuery('#cor_est_liq').val(), f_est_tab: jQuery('#cor_est_tab').val(), f_freq: jQuery('#cor_freq').val(), f_custo: jQuery('#cor_custo').val(), f_tags: jQuery('#cor_tags').val()
            };
        },
        loadData: function(p) {
            var pageSizeVal = jQuery('#hardness_pageSize_cor').val();
            var urlVal      = jQuery('#hardness_ajaxUrl_cor').val();
            var sysIdVal    = jQuery('#sys_base_divId_cor').val();
            // [CORREÇÃO] Recuperando root para round-trip
            var sysRootVal  = jQuery('#sys_base_divRoot_cor').val();

            p = parseInt(p, 10) || 1; 
            var filters = this.getFilters(); 
            var size = parseInt(pageSizeVal, 10) || 50; 
            if (sysIdVal && jQuery('#' + sysIdVal).length) jQuery('#' + sysIdVal).showLoading();
            
            // [CORREÇÃO] Passando sys_divRoot e sys_divId no payload
            jQuery.ajax({ 
                url: urlVal, type: 'POST', dataType: 'json', 
                data: jQuery.extend({ ajax: 1, page: p, pageSize: size, sys_divRoot: sysRootVal, sys_divId: sysIdVal }, filters), 
                success: function (r) { 
                    if (r && r.ok) { jQuery('#contentCor').html(r.html); pagerCor.render('demoCor', r.total, p, size, 'appCor.loadData'); } else { jQuery('#contentCor').html('<div class="start-msg" style="text-align:center;padding:40px;color:#999">Nenhum resultado encontrado.</div>'); jQuery('#demoCor').removeClass('active').html(''); } 
                }, 
                complete: function () { if (sysIdVal && jQuery('#' + sysIdVal).length) jQuery('#' + sysIdVal).hideLoading(); } 
            });
        }
    };

    function saveFiltersCor() { var filters = appCor.getFilters(); localStorage.setItem('cached_filters_cor', JSON.stringify(filters)); }
    function loadFiltersCor() { var cached = localStorage.getItem('cached_filters_cor'); if (cached) { var filters = JSON.parse(cached); jQuery('#cor_sku').val(filters.f_sku); jQuery('#f_id_any_cor').val(filters.f_id_any); jQuery('#cor_tit').val(filters.f_tit); jQuery('#cor_mar').val(filters.f_mar); jQuery('#cor_tipo').val(filters.f_tipo); jQuery('#cor_desc').val(filters.f_desc); jQuery('#cor_spec').val(filters.f_spec); jQuery('#cor_est_liq').val(filters.f_est_liq); jQuery('#cor_est_tab').val(filters.f_est_tab); jQuery('#cor_freq').val(filters.f_freq); jQuery('#cor_custo').val(filters.f_custo); jQuery('#cor_tags').val(filters.f_tags); var hasValue = false; for(var key in filters) { if(filters[key]) { hasValue = true; break; } } if(hasValue) appCor.loadData(1); } }
    function applyFiltersCor() { saveFiltersCor(); appCor.loadData(1); }
    // EXPORTAR CSV
    function exportCSVCor() {
        var filters = appCor.getFilters(); 
        var url = jQuery('#hardness_ajaxUrl_cor').val();
        var form = document.createElement('form'); form.method = 'POST'; form.action = url; form.target = '_blank';
        var i1 = document.createElement('input'); i1.name = 'ajax'; i1.value = '1'; form.appendChild(i1);
        var i2 = document.createElement('input'); i2.name = 'action'; i2.value = 'export_csv_cor'; form.appendChild(i2);
        for (var key in filters) { if (filters.hasOwnProperty(key)) { var inp = document.createElement('input'); inp.name = key; inp.value = filters[key]; form.appendChild(inp); } }
        document.body.appendChild(form); form.submit(); document.body.removeChild(form);
    }
    function clearFiltersCor() { jQuery('#filterBodyCor input').val(''); jQuery('#filterBodyCor select').val(''); localStorage.removeItem('cached_filters_cor'); }

    // [MODAIS ALERT/CONFIRM HANDLERS...]
    let currentConfirmCallback = null;
    function showHAlert(title, msg) { document.getElementById('hAlertTitle').innerText = title; document.getElementById('hAlertMsg').innerText = msg; jQuery('#hModalAlert').fadeIn(200).css('display', 'flex'); }
    function closeHAlert() { jQuery('#hModalAlert').fadeOut(200); }
    function showHConfirm(title, msg, callback) { document.getElementById('hConfirmTitle').innerText = title; document.getElementById('hConfirmMsg').innerText = msg; currentConfirmCallback = callback; jQuery('#hModalConfirm').fadeIn(200).css('display', 'flex'); }
    function closeHConfirm() { jQuery('#hModalConfirm').fadeOut(200); currentConfirmCallback = null; }
    document.getElementById('btnHConfirmAction').onclick = function() { if (currentConfirmCallback) currentConfirmCallback(); closeHConfirm(); };

    function triggerImportCor() { jQuery('#fileImportCor').click(); }
    function processFileCor(input) { if(input.files && input.files[0]) { var url = jQuery('#hardness_ajaxUrl_cor').val(); var sysId = jQuery('#sys_base_divId_cor').val(); var fd = new FormData(); fd.append('ajax', 1); fd.append('action', 'parse_import_csv_cor'); fd.append('csv_file', input.files[0]); if(sysId) jQuery('#'+sysId).showLoading(); jQuery.ajax({ url: url, type: 'POST', data: fd, contentType: false, processData: false, dataType: 'json', success: function(res) { if(res.ok) { jQuery('#triageBodyCor').html(res.html); jQuery('#modalImportCor').fadeIn(200).css('display', 'flex'); } else { showHAlert('Erro', res.msg); } input.value = ''; }, error: function() { showHAlert('Erro', 'Erro ao processar arquivo.'); input.value = ''; }, complete: function() { if(sysId) jQuery('#'+sysId).hideLoading(); } }); } }
    function openHtmlModal(htmlContent) { jQuery('#diffFieldTitle').text('Visualização do Novo Layout'); jQuery('#diffModalContent').html('<div class="diff-container-box"><div class="diff-box-head" style="color:#15803d;">Novo Conteúdo Renderizado (Como ficará no site)</div><div class="diff-box-body">' + htmlContent + '</div></div>'); jQuery('#diffModal').fadeIn(200).css('display', 'flex'); }
    function openDescriptionModal(oldText, newText) { jQuery('#diffFieldTitle').text('Visualização de Alterações (Descrição)'); var oldWords = oldText.split(/\s+/); var newWords = newText.split(/\s+/); var outputHtml = ''; var oldSet = new Set(oldWords); newWords.forEach(function(word) { if (!oldSet.has(word)) { outputHtml += '<span class="diff-word-added">' + word + '</span> '; } else { outputHtml += word + ' '; } }); jQuery('#diffModalContent').html('<div class="diff-container-box"><div class="diff-box-head" style="color:#15803d;">Novo Conteúdo (Alterações em Verde)</div><div class="diff-box-body" style="white-space: pre-wrap;">' + outputHtml + '</div></div>'); jQuery('#diffModal').fadeIn(200).css('display', 'flex'); }
    function openDiffModal(field, oldVal, newVal) { jQuery('#diffFieldTitle').text('Comparativo: ' + field); var oldArr = oldVal.split(' '); var newArr = newVal.split(' '); var diffHtml = ''; newArr.forEach(function(word){ if(oldVal.indexOf(word) === -1) diffHtml += '<span class="diff-word-added">'+word+'</span> '; else diffHtml += word + ' '; }); var oldHtml = ''; oldArr.forEach(function(word){ if(newVal.indexOf(word) === -1) oldHtml += '<span class="diff-word-removed">'+word+'</span> '; else oldHtml += word + ' '; }); jQuery('#diffModalContent').html('<div class="diff-container-box" style="margin-bottom:15px;"><div class="diff-box-head" style="color:#b91c1c;">Original</div><div class="diff-box-body">' + oldHtml + '</div></div><div class="diff-container-box"><div class="diff-box-head" style="color:#15803d;">Novo (Alterações destacadas)</div><div class="diff-box-body">' + diffHtml + '</div></div>'); jQuery('#diffModal').fadeIn(200).css('display', 'flex'); }
    function confirmImportCor() { var rows = []; jQuery('.triage-check-input:checked').each(function() { var idx = jQuery(this).val(); var payload = jQuery('#payload_' + idx).val(); if(payload) rows.push(payload); }); if(rows.length === 0) { showHAlert('Atenção', 'Selecione pelo menos um item para importar.'); return; } showHConfirm('Importar', 'Confirma a atualização de ' + rows.length + ' itens?', function() { var url = jQuery('#hardness_ajaxUrl_cor').val(); var sysId = jQuery('#sys_base_divId_cor').val(); if(sysId) jQuery('#'+sysId).showLoading(); jQuery.ajax({ url: url, type: 'POST', dataType: 'json', data: { ajax: 1, action: 'apply_import_batch_cor', rows: rows }, success: function(res) { if(res.ok) { jQuery('#modalImportCor').fadeOut(200); showHAlert('Sucesso', res.msg); appCor.loadData(1); } else { showHAlert('Erro', res.msg); } }, complete: function() { if(sysId) jQuery('#'+sysId).hideLoading(); } }); }); }
    
    function abrirEditorCor(id) { 
        var url = jQuery('#hardness_ajaxUrl_cor').val(); 
        var sysId = jQuery('#sys_base_divId_cor').val(); 
        // Recuperar root apenas se necessário passar, mas como é só leitura aqui, ok.
        
        if (sysId) jQuery('#' + sysId).showLoading(); 
        jQuery.ajax({ 
            url: url, type: 'POST', dataType: 'json', 
            data: { ajax: 1, action: 'get_edit_data_cor', id: id }, 
            success: function(res) { 
                if(res.ok) { 
                    var d = res.data; jQuery('#editIdCor').val(d.D001F_Id); jQuery('#editSkuCor').val(d.D001F_D001_Codigo_Produto); jQuery('#editTitulo').val(d.D001F_Titulo); jQuery('#editEan').val(d.D001F_EAN); jQuery('#editGar').val(d.D001F_garantia); jQuery('#editPeso').val(d.D001F_peso); jQuery('#editAlt').val(d.D001F_altura); jQuery('#editLarg').val(d.D001F_largura); jQuery('#editComp').val(d.D001F_comprimento); jQuery('#editTags').val(d.D001F_tags || ''); renderTags(); jQuery('#editDescPreview').html(d.D001F_Descricao); jQuery('#editDescCode').val(d.D001F_Descricao); toggleDescMode('visual'); var imgHtml = ''; for(var i=1; i<=10; i++) { var val = res.imgs[i] || ''; imgHtml += '<div class="h-card-img"><img src="'+(val?val:'https://via.placeholder.com/48')+'" class="h-thumb" id="prevImg'+i+'"><input type="text" class="h-input-img img-inp" data-idx="'+i+'" value="'+val+'" placeholder="URL Imagem '+i+'" onchange="updatePrevImg('+i+',this.value)"></div>'; } jQuery('#editImgsContainer').html(imgHtml); jQuery('#modalEditCor').fadeIn(200).css('display', 'flex'); 
                } else { showHAlert('Erro', res.msg); } 
            }, 
            complete: function() { if (sysId) jQuery('#' + sysId).hideLoading(); } 
        }); 
    }
    
    function toggleTagMenu() { var m = document.getElementById('tagMenu'); if (m.classList.contains('active')) m.classList.remove('active'); else m.classList.add('active'); }
    const TAG_DEFS = { 1: { label: 'IMAGEM', bg: '#8b5cf6', br: '#7c3aed' }, 2: { label: 'TÍTULO', bg: '#3b82f6', br: '#2563eb' }, 3: { label: 'DESCRIÇÃO', bg: '#10b981', br: '#059669' }, 4: { label: 'PESOS E DIMENSÃO', bg: '#f97316', br: '#ea580c' }, 5: { label: 'MATCH', bg: '#ef4444', br: '#dc2626' }, 6: { label: 'VOLTAGEM', bg: '#eab308', br: '#ca8a04' }, 7: { label: 'COR', bg: '#ec4899', br: '#db2777' } };
    function renderTags() { var raw = document.getElementById('editTags').value; var list = raw.split(',').map(s => s.trim()).filter(s => s !== ''); var html = ''; jQuery('.tag-option').removeClass('hidden'); list.forEach(function(tid) { var def = TAG_DEFS[tid]; if(def) { var style = 'background:'+def.bg+'; color:#fff; border:1px solid '+def.br+';'; html += '<div class="tag-pill" style="'+style+'">'+def.label+' <i class="material-icons" onclick="removeTag('+tid+')">close</i></div>'; jQuery('.tag-option[data-val="'+tid+'"]').addClass('hidden'); } }); document.getElementById('tagListContainer').innerHTML = html; }
    function addTag(tagId) { var raw = document.getElementById('editTags').value; var list = raw.split(',').map(s => s.trim()).filter(s => s !== ''); var sid = String(tagId); var exists = false; list.forEach(function(t){ if(t === sid) exists = true; }); if(!exists) { list.push(sid); document.getElementById('editTags').value = list.join(','); renderTags(); } toggleTagMenu(); }
    function removeTag(tagId) { var raw = document.getElementById('editTags').value; var list = raw.split(',').map(s => s.trim()).filter(s => s !== ''); var sid = String(tagId); var newList = list.filter(function(t){ return t !== sid; }); document.getElementById('editTags').value = newList.join(','); renderTags(); }
    document.addEventListener('click', function(e) { var m = document.getElementById('tagMenu'); var btn = document.querySelector('.btn-add-tag'); if (!m || !btn) return; if (!m.contains(e.target) && !btn.contains(e.target)) { m.classList.remove('active'); } });
    function updatePrevImg(idx, val) { var src = val ? val : 'https://via.placeholder.com/48'; document.getElementById('prevImg'+idx).src = src; }
    function toggleDescMode(mode) { var visual = document.getElementById('editDescPreview'), code = document.getElementById('editDescCode'), btnV = document.getElementById('btnDescVisual'), btnC = document.getElementById('btnDescCode'); if(mode === 'code') { code.value = visual.innerHTML; visual.style.display = 'none'; code.style.display = 'block'; btnV.classList.remove('active'); btnC.classList.add('active'); } else { visual.innerHTML = code.value; code.style.display = 'none'; visual.style.display = 'block'; btnC.classList.remove('active'); btnV.classList.add('active'); } }
    function fecharEditorCor() { jQuery('#modalEditCor').fadeOut(200); }
    
    function salvarEdicaoCor() { 
        showHConfirm('Salvar Alterações', 'Salvar alterações como rascunho na lista de correção?', function() { 
            var descFinal = (document.getElementById('editDescCode').style.display === 'block') ? document.getElementById('editDescCode').value : document.getElementById('editDescPreview').innerHTML; 
            var data = { 
                ajax: 1, action: 'save_edit_cor', id: jQuery('#editIdCor').val(), sku: jQuery('#editSkuCor').val(), titulo: jQuery('#editTitulo').val(), desc: descFinal, ean: jQuery('#editEan').val(), gar: jQuery('#editGar').val(), peso: jQuery('#editPeso').val(), alt: jQuery('#editAlt').val(), larg: jQuery('#editLarg').val(), comp: jQuery('#editComp').val(), tags: jQuery('#editTags').val() 
            }; 
            jQuery('.img-inp').each(function() { var idx = jQuery(this).data('idx'); data['img_'+idx] = jQuery(this).val(); }); 
            
            var url = jQuery('#hardness_ajaxUrl_cor').val();
            var sysId = jQuery('#sys_base_divId_cor').val();
            // [CORREÇÃO] Passando Root e ID
            var sysRoot = jQuery('#sys_base_divRoot_cor').val();
            data.sys_divRoot = sysRoot;
            data.sys_divId = sysId;
            
            if (sysId) jQuery('#' + sysId).showLoading(); 
            jQuery.ajax({ 
                url: url, type: 'POST', dataType: 'json', data: data, 
                success: function(res) { 
                    if(res.ok) { fecharEditorCor(); appCor.loadData(1); showHAlert('Sucesso', res.msg); } else { showHAlert('Erro', 'Erro: ' + res.msg); } 
                }, 
                error: function() { showHAlert('Erro', 'Erro de comunicação ao salvar.'); }, 
                complete: function() { if (sysId) jQuery('#' + sysId).hideLoading(); } 
            }); 
        }); 
    }
    
    function finalizarCorrecao(id) { 
        showHConfirm('Finalizar', 'Deseja FINALIZAR esta correção? Isso atualizará o produto original e removerá este item da lista.', function() { 
            var url = jQuery('#hardness_ajaxUrl_cor').val();
            var sysId = jQuery('#sys_base_divId_cor').val(); 
            var sysRoot = jQuery('#sys_base_divRoot_cor').val();
            
            if (sysId) jQuery('#' + sysId).showLoading(); 
            jQuery.ajax({ 
                url: url, type: 'POST', dataType: 'json', 
                data: { ajax: 1, action: 'finalize_cor', id: id, sys_divRoot: sysRoot, sys_divId: sysId }, 
                success: function(res) { 
                    if(res.ok) { jQuery('#row_cor_'+id).fadeOut(300, function(){ jQuery(this).remove(); }); showHAlert('Sucesso', res.msg); } else { showHAlert('Erro', 'Erro: ' + res.msg); } 
                }, 
                complete: function() { if (sysId) jQuery('#' + sysId).hideLoading(); } 
            }); 
        }); 
    }
    
    const mVisCor = document.getElementById('modalVisCor'), vThumbsCor = document.getElementById('visThumbsCor'), vHeroCor = document.getElementById('visHeroCor'), vTitleCor = document.getElementById('visTitleCor'), vSkuCor = document.getElementById('visSkuCor'), vBrandCor = document.getElementById('visBrandCor'), vDescCor = document.getElementById('visDescCor'), vSpecsCor = document.getElementById('visSpecsContentCor');
    
    function abrirVisualizadorCor(sku) { 
        var url = document.getElementById('hardness_ajaxUrl_cor').value;
        var sysId = document.getElementById('sys_base_divId_cor').value; 
        var sysRoot = document.getElementById('sys_base_divRoot_cor').value; 

        if (sysId && typeof jQuery !== 'undefined' && jQuery('#' + sysId).length) jQuery('#' + sysId).showLoading(); 
        jQuery.ajax({ 
            url: url, type: 'POST', dataType: 'json', 
            data: { ajax: 1, action: 'get_details_cor', sku: sku, sys_divRoot: sysRoot, sys_divId: sysId }, 
            success: function (res) { 
                if (res.ok) { 
                    vTitleCor.innerText = res.titulo; vSkuCor.innerText = res.sku; vBrandCor.innerText = res.marca; vDescCor.innerHTML = res.desc ? res.desc : '<em>Sem descrição.</em>'; vThumbsCor.innerHTML = ''; if (res.imgs.length > 0) vHeroCor.src = res.imgs[0]; res.imgs.forEach((url, idx) => { let img = document.createElement('img'); img.src = url; img.className = 'vis-mini-cor'; if (idx === 0) img.classList.add('active'); img.onclick = () => { vHeroCor.src = url; document.querySelectorAll('.vis-mini-cor').forEach(el => el.classList.remove('active')); img.classList.add('active'); }; vThumbsCor.appendChild(img); }); let h = '<table class="vis-specs-table-cor">'; let has = false; if (res.specs.EAN) { h += `<tr><td><strong>EAN:</strong> ${res.specs.EAN}</td></tr>`; has = true; } if (res.specs.Garantia) { h += `<tr><td><strong>Garantia:</strong> ${res.specs.Garantia}</td></tr>`; has = true; } if (res.specs.Peso) { h += `<tr><td><strong>Peso:</strong> ${res.specs.Peso}</td></tr>`; has = true; } if (res.specs.Altura) { h += `<tr><td><strong>Altura:</strong> ${res.specs.Altura}</td></tr>`; has = true; } if (res.specs.Largura) { h += `<tr><td><strong>Largura:</strong> ${res.specs.Largura}</td></tr>`; has = true; } if (res.specs.Comprimento) { h += `<tr><td><strong>Comp.:</strong> ${res.specs.Comprimento}</td></tr>`; has = true; } h += '</table>'; vSpecsCor.innerHTML = has ? h : '<div style="color:#999;font-size:12px">Vazio</div>'; mVisCor.style.display = 'flex'; 
                } else { showHAlert('Erro', res.msg || 'Erro ao carregar'); } 
            }, 
            error: function () { showHAlert('Erro', 'Erro na comunicação'); }, 
            complete: function () { if (sysId && typeof jQuery !== 'undefined' && jQuery('#' + sysId).length) jQuery('#' + sysId).hideLoading(); } 
        }); 
    }
    function fecharVisCor() { mVisCor.style.display = 'none'; }
    function imprimirConteudoModalCor() { const f = document.createElement('iframe'); f.style.display = 'none'; document.body.appendChild(f); const d = f.contentWindow.document; const s = vSpecsCor.innerHTML; const c = `<html><head><style>body{font-family:Arial,sans-serif;padding:20px;color:#333}h1{font-size:20px;margin-bottom:5px}.meta{color:#666;font-size:12px;margin-bottom:20px;border-bottom:1px solid #ccc;padding-bottom:10px}.hero{text-align:center;margin-bottom:20px}.hero img{max-width:300px;max-height:300px}.desc{font-size:12px;line-height:1.5;margin-bottom:20px; text-align:justify;}.specs-box{border:1px solid #eee;padding:10px;border-radius:5px}.specs-box table{width:100%;font-size:12px}.specs-box td{padding:4px 0}</style></head><body><h1>${vTitleCor.innerText}</h1><div class="meta">SKU: ${vSkuCor.innerText} | ${vBrandCor.innerText}</div><div class="hero"><img src="${vHeroCor.src}"></div><h3>Descrição</h3><div class="desc">${vDescCor.innerHTML}</div><h3>Specs</h3><div class="specs-box">${s}</div></body></html>`; d.open(); d.write(c); d.close(); setTimeout(() => { f.contentWindow.print(); setTimeout(() => document.body.removeChild(f), 1000); }, 200); }
    document.addEventListener('keydown', e => { if (e.key === "Escape") { fecharVisCor(); fecharEditorCor(); jQuery('#modalImportCor').fadeOut(200); jQuery('#diffModal').fadeOut(100); } });

    jQuery(document).ready(function() {
        loadFiltersCor();
    });
</script>