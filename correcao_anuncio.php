<?php
/*
 PAINEL DE CORRECAO DE ANUNCIO (D001F) - 100% ISOLADO (SEM CONFLITO DE AJAX)
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

// INDICADOR VISUAL (Laranja)
if (!$isAjax) {
    echo "<div style='
            position: fixed; bottom: 20px; right: 20px;
            background: #ffffff; color: #1f2937;
            padding: 10px 16px; border-radius: 50px;
            font-size: 12px; font-family: -apple-system, sans-serif; font-weight: 600;
            z-index: 999998; box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            border: 1px solid #f3f4f6; pointer-events: none; display:flex; align-items:center; gap:8px;
          '>
            <span style='background:#f59e0b; width:8px; height:8px; border-radius:50%; display:inline-block;'></span>
            <span>Painel Correção: <strong style='color: #111827;'>ID {$C004_Id}</strong></span>
          </div>";
}

// =============================================================================
// [RENDER] FUNÇÃO DE LINHA EXCLUSIVA (D001F)
// =============================================================================
function renderCorrectionRowIsolado($row) {
    $marca = isset($row['D001F_Marca']) ? $row['D001F_Marca'] : 'ND';
    $imgCapa = $row['D001F_Imagem_1'] ?: "https://via.placeholder.com/100x100?text=Sem+Img";

    $titulo    = htmlspecialchars($row['D001F_Titulo'], ENT_QUOTES);
    $skuRaw    = $row['D001F_D001_Codigo_Produto'];
    $sku       = htmlspecialchars($skuRaw, ENT_QUOTES);
    $descRaw   = $row['D001F_Descricao'];
    $marcaHtml = htmlspecialchars($marca, ENT_QUOTES);

    // Specs
    $specHtml = "";
    if (!empty($row['D001F_EAN'])) $specHtml .= "<span><b>EAN:</b> {$row['D001F_EAN']}</span>";
    if (!empty($row['D001F_garantia'])) $specHtml .= "<span><b>Gar:</b> {$row['D001F_garantia']}</span>";
    if (!empty($row['D001F_peso'])) $specHtml .= "<span><b>Peso:</b> {$row['D001F_peso']}</span>";
    if (!empty($row['D001F_altura'])) $specHtml .= "<span><b>Dim:</b> " . ($row['D001F_altura'] ?: 0) . "x" . ($row['D001F_largura'] ?: 0) . "x" . ($row['D001F_comprimento'] ?: 0) . "</span>";
    if (empty($specHtml)) $specHtml = "<span style='color:#bbb'>Vazio</span>";

    // Métricas (D009)
    $freqVenda = !empty($row['D009_Frequencia_Venda']) ? $row['D009_Frequencia_Venda'] : '0';
    $custoVal  = isset($row['D009_Valor_Custo_Unitario']) ? (float) $row['D009_Valor_Custo_Unitario'] : 0;
    $estTab    = isset($row['D009_Quantidade_Estoque_Tabela']) ? (int) $row['D009_Quantidade_Estoque_Tabela'] : 0;
    $estLiq    = isset($row['D009_Quantidade_Estoque_Liquido']) ? (int) $row['D009_Quantidade_Estoque_Liquido'] : 0;

    $custoHtml  = ($custoVal > 0) ? "R$ " . number_format($custoVal, 2, ',', '.') : "0";
    $estTabHtml = ($estTab > 0) ? $estTab : "0";
    $estLiqHtml = ($estLiq > 0) ? $estLiq : "0";

    // Layout SEM PONTOS (Bolinhas verdes/amarelas removidas)
    return "
    <div class='quality-row'>
        <div class='thumb-box' onclick='abrirVisualizadorCor(\"$sku\")'>
            <img src='$imgCapa' alt='Capa'>
        </div>
        
        <div class='col-info'>
            <div class='prod-title'>{$row['D001F_Titulo']}</div>
            <div class='prod-sub'>
                <span class='badge-sku'>$sku</span>
                <span class='badge-brand' title='$marcaHtml'>$marcaHtml</span>
            </div>
        </div>
        
        <div class='col-metrics'>
            <div class='metric-item' title='Frequência de Venda'><span class='m-lbl'>FREQ</span> <span class='m-val'>$freqVenda</span></div>
            <div class='metric-item' title='Custo Unitário'><span class='m-lbl'>CUSTO</span> <span class='m-val text-blue'>$custoHtml</span></div>
            <div class='metric-item' title='Estoque Tabela'><span class='m-lbl'>TAB</span> <span class='m-val'>$estTabHtml</span></div>
            <div class='metric-item' title='Estoque Líquido'><span class='m-lbl'>LIQ</span> <span class='m-val'>$estLiqHtml</span></div>
        </div>

        <div class='col-box-scroll content-desc'>" . ($descRaw ?: '<em>Sem descrição</em>') . "</div>
        
        <div class='col-box-scroll content-spec'>$specHtml</div>
        
        <div class='col-actions'>
             <button class='btn-action-icon' onclick='abrirVisualizadorCor(\"$sku\")' title='Visualizar Detalhes'>
                <i class='material-icons'>visibility</i>
             </button>
        </div>
    </div>";
}

// =============================================================================
// [AJAX] GERENCIADOR ISOLADO
// =============================================================================
if ($isAjax) {

    // Função local para limpar input
    if (!function_exists('cleanInputCor')) {
        function cleanInputCor($data) {
            $data = trim($data);
            return mysql_real_escape_string($data);
        }
    }

    // Função de filtro exclusiva para CORREÇÃO
    if (!function_exists('buildWhereCor')) {
        function buildWhereCor($postData) {
            $where = ["1=1"];
            
            // Note que recebemos 'f_tit' do JS, mas validamos aqui
            if (!empty($postData['f_tit'])) { $ft = cleanInputCor($postData['f_tit']); $where[] = "T1.D001F_Titulo LIKE '%$ft%'"; }
            if (!empty($postData['f_sku'])) { $fs = cleanInputCor($postData['f_sku']); $where[] = "T1.D001F_D001_Codigo_Produto LIKE '%$fs%'"; }
            if (!empty($postData['f_mar'])) { $fm = cleanInputCor($postData['f_mar']); $where[] = "T1.D001F_Marca LIKE '%$fm%'"; }
            if (!empty($postData['f_desc'])) { $fd = cleanInputCor($postData['f_desc']); $where[] = "T1.D001F_Descricao LIKE '%$fd%'"; }
            if (!empty($postData['f_spec'])) { $fsp = cleanInputCor($postData['f_spec']); $where[] = "(T1.D001F_EAN LIKE '%$fsp%' OR T1.D001F_garantia LIKE '%$fsp%' OR T1.D001F_peso LIKE '%$fsp%')"; }

            if (isset($postData['f_est_liq']) && $postData['f_est_liq'] !== '') { $val = (int)$postData['f_est_liq']; $where[] = "T2.D009_Quantidade_Estoque_Liquido = $val"; }
            if (isset($postData['f_est_tab']) && $postData['f_est_tab'] !== '') { $val = (int)$postData['f_est_tab']; $where[] = "T2.D009_Quantidade_Estoque_Tabela = $val"; }
            if (!empty($postData['f_freq'])) { $val = cleanInputCor($postData['f_freq']); $where[] = "T2.D009_Frequencia_Venda LIKE '%$val%'"; }
            if (!empty($postData['f_custo'])) { $val = (float)str_replace(',', '.', $postData['f_custo']); $where[] = "T2.D009_Valor_Custo_Unitario = $val"; }

            return implode(" AND ", $where);
        }
    }

    // [EXPORTAÇÃO CSV - Ação Renomeada: export_csv_cor]
    if (isset($_POST['action']) && $_POST['action'] === 'export_csv_cor') {
        $whereStr = buildWhereCor($_POST);

        $sqlCsv = "SELECT T1.* FROM D001F AS T1
                   LEFT JOIN D049 ON D049.D049_D001_Id = T1.D001F_D001_Id
                   LEFT JOIN D009 AS T2 ON (T2.D009_D049_Id = D049.D049_Id AND T2.D009_C004_Id = $C004_Id)
                   WHERE $whereStr
                   GROUP BY T1.D001F_Id
                   ORDER BY T1.D001F_Id ASC";

        $rsCsv = mysql_query($sqlCsv);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=correcao_produtos_' . date('YmdHis') . '.csv');

        $out = fopen('php://output', 'w');
        fputs($out, "\xEF\xBB\xBF");

        $header = [
            'D001F_Id', 'D001F_D001_Id', 'D001F_D001_Codigo_Produto', 'D001F_Titulo', 'D001F_Marca',
            'D001F_Descricao', 'D001F_Imagem_1', 'D001F_Imagem_2', 'D001F_Imagem_3', 'D001F_Imagem_4',
            'D001F_Imagem_5', 'D001F_Imagem_6', 'D001F_Imagem_7', 'D001F_Imagem_8', 'D001F_Imagem_9',
            'D001F_Imagem_10', 'D001F_EAN', 'D001F_garantia', 'D001F_peso', 'D001F_altura',
            'D001F_largura', 'D001F_comprimento', 'D001F_ult_att'
        ];
        fputcsv($out, $header, ';');

        function simpleCleanCor($str) {
            if (is_null($str)) return '';
            $str = str_replace(["\r\n", "\r", "\n", "\t"], ' ', $str);
            return trim($str);
        }

        if ($rsCsv) {
            while ($row = mysql_fetch_assoc($rsCsv)) {
                $line = [
                    $row['D001F_Id'], $row['D001F_D001_Id'], simpleCleanCor($row['D001F_D001_Codigo_Produto']),
                    simpleCleanCor($row['D001F_Titulo']), simpleCleanCor($row['D001F_Marca']), simpleCleanCor($row['D001F_Descricao']),
                    $row['D001F_Imagem_1'], $row['D001F_Imagem_2'], $row['D001F_Imagem_3'], $row['D001F_Imagem_4'],
                    $row['D001F_Imagem_5'], $row['D001F_Imagem_6'], $row['D001F_Imagem_7'], $row['D001F_Imagem_8'],
                    $row['D001F_Imagem_9'], $row['D001F_Imagem_10'], simpleCleanCor($row['D001F_EAN']),
                    simpleCleanCor($row['D001F_garantia']), simpleCleanCor($row['D001F_peso']), simpleCleanCor($row['D001F_altura']),
                    simpleCleanCor($row['D001F_largura']), simpleCleanCor($row['D001F_comprimento']), $row['D001F_ult_att']
                ];
                fputcsv($out, $line, ';');
            }
        }
        fclose($out);
        exit;
    }

    header('Content-Type: application/json; charset=UTF-8');

    // GET DETAILS (D001F) - Ação Renomeada
    if (isset($_POST['action']) && $_POST['action'] === 'get_details_cor') {
        $skuBusca = isset($_POST['sku']) ? mysql_real_escape_string($_POST['sku']) : '';
        $sqlDet = "SELECT T1.* FROM D001F AS T1 WHERE T1.D001F_D001_Codigo_Produto = '$skuBusca' LIMIT 1";
        $rsDet  = mysql_query($sqlDet);
        if ($rsDet && mysql_num_rows($rsDet) > 0) {
            $row   = mysql_fetch_assoc($rsDet);
            $marca = isset($row['D001F_Marca']) ? $row['D001F_Marca'] : 'ND';
            $imgs = [];
            for ($i = 1; $i <= 10; $i++) if (!empty($row["D001F_Imagem_$i"])) $imgs[] = $row["D001F_Imagem_$i"];
            if (empty($imgs)) $imgs[] = "https://via.placeholder.com/600x600?text=Sem+Imagem";
            
            $specs = [
                'EAN'         => $row['D001F_EAN'] ?? '',
                'Garantia'    => $row['D001F_garantia'] ?? '',
                'Peso'        => $row['D001F_peso'] ? $row['D001F_peso'] . ' kg' : '',
                'Altura'      => $row['D001F_altura'] ? $row['D001F_altura'] . ' cm' : '',
                'Largura'     => $row['D001F_largura'] ? $row['D001F_largura'] . ' cm' : '',
                'Comprimento' => $row['D001F_comprimento'] ? $row['D001F_comprimento'] . ' cm' : '',
            ];
            echo json_encode([
                'ok'     => 1,
                'titulo' => $row['D001F_Titulo'],
                'sku'    => $row['D001F_D001_Codigo_Produto'],
                'marca'  => $marca,
                'desc'   => $row['D001F_Descricao'],
                'imgs'   => $imgs,
                'specs'  => $specs
            ]);
        } else {
            echo json_encode(['ok' => 0, 'msg' => 'Produto não encontrado']);
        }
        exit;
    }

    // LISTAGEM PRINCIPAL
    $whereStr = buildWhereCor($_POST);
    
    // COUNT
    $totalRows = 0;
    $sqlCount = "SELECT COUNT(*) AS total FROM D001F AS T1 
                 LEFT JOIN D049 ON D049.D049_D001_Id = T1.D001F_D001_Id 
                 LEFT JOIN D009 AS T2 ON (T2.D009_D049_Id = D049.D049_Id AND T2.D009_C004_Id = $C004_Id) 
                 WHERE $whereStr";
    $rsCount  = mysql_query($sqlCount);
    if ($rsCount) {
        $r         = mysql_fetch_assoc($rsCount);
        $totalRows = (int) ($r['total'] ?? 0);
    }

    // DATA
    $sql  = "SELECT T1.*, T2.D009_Frequencia_Venda, T2.D009_Valor_Custo_Unitario, T2.D009_Quantidade_Estoque_Tabela, T2.D009_Quantidade_Estoque_Liquido
             FROM D001F AS T1 
             LEFT JOIN D049 ON D049.D049_D001_Id = T1.D001F_D001_Id 
             LEFT JOIN D009 AS T2 ON (T2.D009_D049_Id = D049.D049_Id AND T2.D009_C004_Id = $C004_Id)
             WHERE $whereStr 
             GROUP BY T1.D001F_Id 
             ORDER BY T1.D001F_Id ASC LIMIT $limit OFFSET $offset";
    $rs   = mysql_query($sql);
    $html = "";
    if ($rs) {
        while ($row = mysql_fetch_assoc($rs)) {
            $html .= renderCorrectionRowIsolado($row);
        }
    }

    echo json_encode(['ok' => 1, 'total' => $totalRows, 'page' => $page, 'pageSize' => $limit, 'html' => $html]);
    exit;
}

// =============================================================================
// [STYLE] CSS (LAYOUT 6 COLUNAS)
// =============================================================================
$style = <<<STYLE
<style>
    :root { 
        --primary: #0098D3; 
        --primary-hover: #007bb5;
        --bg-body: #F3F4F6;
        --bg-card: #FFFFFF; 
        --text-main: #1F2937; 
        --text-sub: #6B7280; 
        --border: #E5E7EB; 
        --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
        --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
    }
    * { box-sizing: border-box; }
    body { font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; background-color: var(--bg-body); margin: 0; padding: 20px; color: var(--text-main); -webkit-font-smoothing: antialiased; }
    
    .quality-list { max-width: 1600px; margin: 0 auto; position: relative; }
    
    /* FILTERS PANEL */
    .filter-container { 
        background: var(--bg-card); border-radius: 12px; 
        box-shadow: var(--shadow-md); 
        margin-bottom: 24px; max-width: 1600px; margin: 0 auto 24px auto; border: 1px solid var(--border); overflow: hidden;
    }
    .filter-header { 
        padding: 16px 20px; display: flex; justify-content: space-between; align-items: center; 
        cursor: pointer; background: #fff; border-bottom: 1px solid transparent; transition: background 0.2s;
    }
    .filter-header:hover { background: #f9fafb; }
    .filter-title { font-size: 15px; font-weight: 700; color: #374151; display:flex; align-items:center; gap:10px; }
    .filter-icon { color: var(--primary); }
    .filter-body { padding: 20px; background: #fff; border-top: 1px solid var(--border); display: block; animation: fadeIn 0.3s ease; }
    .filter-body.closed { display: none; }
    
    .f-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 16px; }
    .f-group { display: flex; flex-direction: column; gap: 6px; }
    .f-label { font-size: 11px; font-weight: 700; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em; }
    .f-input { 
        padding: 10px 12px; border: 1px solid #d1d5db; border-radius: 6px; 
        font-size: 13px; outline: none; transition: all 0.2s; width: 100%; color: #374151; background: #f9fafb;
    }
    .f-input:focus { border-color: var(--primary); background: #fff; box-shadow: 0 0 0 3px rgba(0, 152, 211, 0.1); }
    
    .f-actions { display: flex; justify-content: flex-end; margin-top: 20px; padding-top: 15px; border-top: 1px solid #f3f4f6; gap: 12px; flex-wrap: wrap; }
    .f-btn-apply, .f-btn-export {
        border: none; padding: 10px 20px; border-radius: 6px; font-weight: 600; cursor: pointer; font-size: 13px; display:flex; align-items:center; gap:8px; transition: all 0.2s; shadow: var(--shadow-sm);
    }
    .f-btn-apply { background: var(--primary); color: #fff; } .f-btn-apply:hover { background: var(--primary-hover); transform: translateY(-1px); }
    .f-btn-export { background: #fff; color: #374151; border: 1px solid #d1d5db; } .f-btn-export:hover { background: #f3f4f6; border-color: #9ca3af; }
    
    /* GRID LAYOUT - 6 COLS */
    .quality-header, .quality-row { 
        display: grid; 
        grid-template-columns: 70px 1.5fr 1fr 1.2fr 1fr 60px; 
        gap: 12px; 
        align-items: center;
    }

    /* HEADER FIXO */
    .quality-header { 
        position: sticky; top: 0; z-index: 100;
        padding: 12px 16px; 
        font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.04em; 
        background: #F3F4F6;
        border-bottom: 2px solid #e5e7eb;
        margin-bottom: 5px; 
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
    }
    .quality-header > div { text-align: left; }
    .quality-header > div.center { text-align: center; justify-content: center; display: flex; }

    /* ROW CARD */
    .quality-row { 
        background: var(--bg-card); border-radius: 8px; 
        padding: 12px 16px; margin-bottom: 12px; border: 1px solid var(--border); 
        transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        box-shadow: var(--shadow-sm);
    }
    .quality-row:hover { transform: translateY(-2px); box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1); border-color: #cbd5e1; }
    
    .thumb-box { 
        width: 64px; height: 64px; border-radius: 8px; border: 1px solid #e5e7eb; padding: 2px; background: #fff; 
        cursor: pointer; display: flex; align-items: center; justify-content: center; overflow: hidden;
    }
    .thumb-box img { width: 100%; height: 100%; object-fit: contain; transition: transform 0.3s; }
    .thumb-box:hover img { transform: scale(1.1); }
    
    .col-info { display: flex; flex-direction: column; gap: 6px; overflow: hidden; }
    .prod-title { font-size: 13px; font-weight: 600; color: #111827; line-height: 1.4; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
    .prod-sub { display: flex; gap: 6px; align-items: center; flex-wrap: wrap; }
    .badge-sku { font-size: 10px; color: #4b5563; background: #f3f4f6; padding: 2px 6px; border-radius: 4px; font-family: 'Monaco', monospace; border: 1px solid #e5e7eb; }
    .badge-brand { font-size: 10px; font-weight: 700; color: #fff; background: var(--primary); padding: 2px 6px; border-radius: 4px; max-width: 100px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

    .col-metrics { display: grid; grid-template-columns: 1fr 1fr; gap: 4px 10px; background: #f9fafb; padding: 6px 10px; border-radius: 6px; border: 1px solid #f3f4f6; }
    .metric-item { display: flex; justify-content: space-between; align-items: center; font-size: 11px; }
    .m-lbl { color: #9ca3af; font-size: 9px; font-weight: 700; text-transform: uppercase; }
    .m-val { color: #374151; font-weight: 600; }
    .m-val.text-blue { color: var(--primary); }

    .col-box-scroll { 
        font-size: 11px; color: #4b5563; max-height: 70px; overflow-y: auto; background: #fff; 
        padding: 4px 6px; line-height: 1.5; border-radius: 6px; border: 1px solid #f3f4f6; 
    }
    .content-spec span { display: block; border-bottom: 1px solid #f9fafb; padding: 2px 0; }
    /* Scrollbar fina */
    .col-box-scroll::-webkit-scrollbar { width: 4px; }
    .col-box-scroll::-webkit-scrollbar-track { background: #f1f1f1; }
    .col-box-scroll::-webkit-scrollbar-thumb { background: #d1d5db; border-radius: 4px; }
    .col-box-scroll::-webkit-scrollbar-thumb:hover { background: #9ca3af; }

    .col-actions { display: flex; justify-content: center; }
    .btn-action-icon { 
        background: transparent; border: 1px solid #e5e7eb; color: #6b7280; 
        width: 36px; height: 36px; border-radius: 6px; cursor: pointer; transition: all 0.2s;
        display: flex; align-items: center; justify-content: center;
    }
    .btn-action-icon:hover { background: #fffbeb; color: #d97706; border-color: #fcd34d; transform: scale(1.05); }

    /* Modal */
    .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 9999; justify-content: center; align-items: center; backdrop-filter: blur(4px); padding: 20px; }
    .modal-content { background: #fff; width: 100%; max-width: 1100px; height: 85vh; border-radius: 16px; position: relative; display: flex; overflow: hidden; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25); }
    .close-modal { position: absolute; top: 15px; right: 20px; font-size: 28px; cursor: pointer; z-index: 100; color: #9ca3af; transition: color 0.2s; }
    .close-modal:hover { color: #1f2937; }
    
    .vis-thumbs { width: 110px; background: #f9fafb; padding: 15px; overflow-y: auto; display: flex; flex-direction: column; gap: 10px; border-right: 1px solid #e5e7eb; }
    .vis-mini { width: 100%; height: 80px; object-fit: contain; border-radius: 6px; cursor: pointer; background: #fff; border: 1px solid #e5e7eb; transition: all 0.2s; }
    .vis-mini.active, .vis-mini:hover { border-color: var(--primary); box-shadow: 0 0 0 2px rgba(0, 152, 211, 0.2); }
    
    .vis-main { flex: 1; display: flex; justify-content: center; align-items: center; background: #fff; padding: 30px; position: relative; }
    .vis-main img { max-width: 100%; max-height: 100%; object-fit: contain; filter: drop-shadow(0 10px 8px rgba(0,0,0,0.04)); }
    
    .vis-info { width: 360px; border-left: 1px solid #e5e7eb; padding: 30px; overflow-y: auto; background: #fff; display: flex; flex-direction: column; gap: 20px; }
    .vis-h1 { font-size: 20px; font-weight: 800; margin: 0; color: #111827; line-height: 1.3; }
    .vis-meta { font-size: 13px; color: #6b7280; padding-bottom: 15px; border-bottom: 1px solid #e5e7eb; }
    .vis-meta strong { color: #374151; }
    
    .vis-header-row { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; font-weight: 700; font-size: 12px; color: #374151; text-transform: uppercase; letter-spacing: 0.05em; }
    .vis-desc-box { font-size: 13px; line-height: 1.6; color: #4b5563; background: #f9fafb; padding: 15px; border-radius: 8px; border: 1px solid #e5e7eb; max-height: 200px; overflow-y: auto; }
    
    .vis-specs-table { width: 100%; border-collapse: collapse; }
    .vis-specs-table td { padding: 8px 0; border-bottom: 1px solid #f3f4f6; color: #4b5563; font-size: 13px; }
    .vis-specs-table td strong { color: #1f2937; margin-right: 5px; }
    
    .vis-btn-print { width: 100%; padding: 12px; background: #1f2937; color: #fff; border: none; border-radius: 8px; font-size: 13px; font-weight: 600; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; transition: background 0.2s; margin-top: auto; }
    .vis-btn-print:hover { background: #111827; }

    /* Pager */
    #demoCor { padding: 30px 0; display:none; flex-wrap:wrap; align-items:center; justify-content:center; gap:6px; }
    #demoCor.active { display: flex; }
    #demoCor .pg-btn { border: 1px solid #e5e7eb; background:#fff; padding:8px 14px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; color:#374151; text-decoration: none; transition: all 0.2s; }
    #demoCor .pg-btn:hover { background: #f9fafb; border-color: #d1d5db; }
    #demoCor .pg-btn.active { background: var(--primary); border-color: var(--primary); color:#fff; }

    /* Responsive */
    @media (max-width: 1400px) {
        .quality-header, .quality-row { 
            grid-template-columns: 70px 1.2fr 1fr 1fr 1fr 60px; 
            gap: 8px;
        }
    }
    @media (max-width: 1200px) { 
        .quality-header { display: none; }
        .quality-row { 
            grid-template-columns: 70px 1fr 1fr; 
            grid-template-areas: 
                "thumb info info"
                "thumb metrics metrics"
                "desc desc desc"
                "spec spec spec"
                "action action action";
            gap: 10px; height: auto;
        }
        .thumb-box { grid-area: thumb; }
        .col-info { grid-area: info; }
        .col-metrics { grid-area: metrics; }
        .content-desc { grid-area: desc; max-height: 60px; }
        .content-spec { grid-area: spec; max-height: 60px; }
        .col-actions { grid-area: action; justify-content: flex-end; padding-right: 10px; }
    }
    @keyframes fadeIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
</style>
<link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
STYLE;
if (!$apiMode) echo $style;

// NOTE OS IDs PREFIXADOS COM "cor_"
echo "
<div class='filter-container'>
    <div class='filter-header' onclick='toggleFilterBodyCor()'>
        <div class='filter-title'><i class='material-icons filter-icon'>tune</i> Filtros Avançados (Correção)</div>
        <i class='material-icons filter-chevron' id='filterChevronCor'>expand_more</i>
    </div>
    <div class='filter-body' id='filterBodyCor'>
        <div class='f-grid'>
            <div class='f-group'><label class='f-label'>Título</label><input type='text' id='cor_tit' class='f-input' placeholder='Ex: Parafusadeira'></div>
            <div class='f-group'><label class='f-label'>SKU / Cód</label><input type='text' id='cor_sku' class='f-input' placeholder='Ex: 12345'></div>
            <div class='f-group'><label class='f-label'>Marca</label><input type='text' id='cor_mar' class='f-input' placeholder='Ex: Bosch'></div>
            <div class='f-group'><label class='f-label'>Descrição</label><input type='text' id='cor_desc' class='f-input' placeholder='Contém...'></div>
            <div class='f-group'><label class='f-label'>Specs/EAN</label><input type='text' id='cor_spec' class='f-input' placeholder='Contém...'></div>
            <div class='f-group'><label class='f-label'>Est. Líquido (=)</label><input type='number' id='cor_est_liq' class='f-input' placeholder='Exato'></div>
            <div class='f-group'><label class='f-label'>Est. Tabela (=)</label><input type='number' id='cor_est_tab' class='f-input' placeholder='Exato'></div>
            <div class='f-group'><label class='f-label'>Frequência</label><input type='text' id='cor_freq' class='f-input' placeholder='Ex: A'></div>
            <div class='f-group'><label class='f-label'>Custo (=)</label><input type='text' id='cor_custo' class='f-input' placeholder='Ex: 10,90'></div>
        </div>
        <div class='f-actions'>
            <button class='f-btn-apply' onclick='applyFiltersCor()'><i class='material-icons'>search</i> Aplicar Filtros</button>
            <button class='f-btn-export' onclick='exportCSVCor()'><i class='material-icons'>file_download</i> Exportar CSV</button>
        </div>
    </div>
</div>";

echo "<div class='quality-list'>";
// NOTE HEADER FIXO com layout de 6 colunas
echo "<div class='quality-header'>
        <div>Foto</div>
        <div>Produto / Marca</div>
        <div>Métricas</div>
        <div>Descrição</div>
        <div>Especificações</div>
        <div class='center'>Ação</div>
      </div>";

echo "<div id='contentCor'><div class='start-msg' style='text-align:center; padding:50px; color:#9ca3af;'><i class='material-icons' style='font-size:48px; margin-bottom:10px; display:block;'>search</i><h2 style='font-size:18px; margin:0;'>Comece sua análise</h2><p>Utilize os filtros acima para carregar os produtos.</p></div></div>";

$ajaxUrl  = isset($_SERVER['REQUEST_URI']) ? htmlspecialchars($_SERVER['REQUEST_URI'], ENT_QUOTES, 'UTF-8') : '';
$sysDivId = isset($g['divId']) ? $g['divId'] : 'contentCor';
// AQUI ESTAVA O ERRO - IDs UNICOS AGORA PARA NÃO PEGAR O DO OUTRO PAINEL
echo "<input type='hidden' id='hardness_total_cor' value='0'>";
echo "<input type='hidden' id='hardness_pageSize_cor' value='" . (int) $limit . "'>";
echo "<input type='hidden' id='hardness_ajaxUrl_cor' value='" . $ajaxUrl . "'>";
echo "<input type='hidden' id='sys_base_divId_cor' value='" . $sysDivId . "'>";
echo "<div id='demoCor'></div></div>";
?>

<div id="modalVisCor" class="modal-overlay" onclick="if(event.target==this) fecharVisCor()">
    <div class="modal-content printable-area">
        <span class="close-modal" onclick="fecharVisCor()">×</span>
        <div class="vis-thumbs" id="visThumbsCor"></div>
        <div class="vis-main"><img id="visHeroCor" src=""></div>
        <div class="vis-info">
            <div>
                <h1 class="vis-h1"><span id="visTitleCor">--</span></h1>
                <div class="vis-meta">SKU: <strong id="visSkuCor">--</strong> | Marca: <strong id="visBrandCor">--</strong></div>
            </div>
            
            <div>
                <div class="vis-header-row"><span>Descrição</span></div>
                <div class="vis-desc-box" id="visDescCor"></div>
            </div>
            
            <div class="vis-specs-container">
                <div class="vis-header-row" style="margin-top:10px"><span>Especificações</span></div>
                <div id="visSpecsContentCor"></div>
            </div>

            <button class="vis-btn-print" onclick="imprimirConteudoModalCor()"><i class="material-icons">print</i> Imprimir Ficha Técnica</button>
        </div>
    </div>
</div>

<script>
    // NAMESPACE ISOLADO "Cor"
    function toggleFilterBodyCor() {
        var b = document.getElementById('filterBodyCor');
        var c = document.getElementById('filterChevronCor');
        if (b.classList.contains('closed')) { b.classList.remove('closed'); c.style.transform = 'rotate(0deg)'; } else { b.classList.add('closed'); c.style.transform = 'rotate(-90deg)'; }
    }
    
    var pagerCor = {
        render: function(targetId, total, current, size, callbackName) {
            var $t = jQuery('#' + targetId); var pages = Math.ceil(total / size);
            if (pages <= 1) { $t.removeClass('active').html(''); return; }
            var h = '', r = 2, start = Math.max(1, current - r), end = Math.min(pages, current + r);
            function btn(lbl, pg, cls) { return '<a href="javascript:void(0)" class="pg-btn ' + (cls||'') + '" onclick="'+callbackName+'('+pg+')">' + lbl + '</a>'; }
            if (current > 1) h += btn('Anterior', current - 1);
            if (start > 1) { h += btn('1', 1, (current === 1 ? 'active' : '')); if (start > 2) h += '<span style="color:#999;padding:0 5px">...</span>'; }
            for (var i = start; i <= end; i++) { h += btn(i, i, (current === i ? 'active' : '')); }
            if (end < pages) { if (end < pages - 1) h += '<span style="color:#999;padding:0 5px">...</span>'; h += btn(pages, pages, (current === pages ? 'active' : '')); }
            if (current < pages) h += btn('Próxima', current + 1);
            $t.addClass('active').html(h);
        }
    };
    
    var appCor = {
        getFilters: function() {
            // MAP: ID #cor_tit -> $_POST['f_tit']
            return {
                f_tit: jQuery('#cor_tit').val(),
                f_sku: jQuery('#cor_sku').val(),
                f_mar: jQuery('#cor_mar').val(),
                f_desc: jQuery('#cor_desc').val(),
                f_spec: jQuery('#cor_spec').val(),
                f_est_liq: jQuery('#cor_est_liq').val(),
                f_est_tab: jQuery('#cor_est_tab').val(),
                f_freq: jQuery('#cor_freq').val(),
                f_custo: jQuery('#cor_custo').val()
            };
        },
        loadData: function(p) {
            // CORRIGIDO: Busca pageSize e URL dos IDs com sulfixo _cor
            var pageSizeVal = jQuery('#hardness_pageSize_cor').val();
            var urlVal      = jQuery('#hardness_ajaxUrl_cor').val();
            var sysIdVal    = jQuery('#sys_base_divId_cor').val();

            p = parseInt(p, 10) || 1; 
            var filters = this.getFilters(); 
            var size = parseInt(pageSizeVal, 10) || 50; 
            
            if (sysIdVal && jQuery('#' + sysIdVal).length) jQuery('#' + sysIdVal).showLoading();
            
            // CHAMA appCor.loadData (Isolado)
            jQuery.ajax({ 
                url: urlVal, 
                type: 'POST', 
                dataType: 'json', 
                data: jQuery.extend({ ajax: 1, page: p, pageSize: size }, filters), 
                success: function (r) { 
                    if (r && r.ok) { 
                        jQuery('#contentCor').html(r.html); 
                        pagerCor.render('demoCor', r.total, p, size, 'appCor.loadData'); 
                    } else { 
                        jQuery('#contentCor').html('<div class="start-msg" style="text-align:center;padding:40px;color:#999">Nenhum resultado encontrado.</div>'); 
                        jQuery('#demoCor').removeClass('active').html(''); 
                    } 
                }, 
                complete: function () { 
                    if (sysIdVal && jQuery('#' + sysIdVal).length) jQuery('#' + sysIdVal).hideLoading(); 
                } 
            });
        }
    };
    
    function applyFiltersCor() { appCor.loadData(1); }
    
    function exportCSVCor() {
        var filters = appCor.getFilters(); 
        var url = jQuery('#hardness_ajaxUrl_cor').val(); // ID CORRIGIDO
        var form = document.createElement('form'); form.method = 'POST'; form.action = url; form.target = '_blank';
        var i1 = document.createElement('input'); i1.name = 'ajax'; i1.value = '1'; form.appendChild(i1);
        var i2 = document.createElement('input'); i2.name = 'action'; i2.value = 'export_csv_cor'; form.appendChild(i2);
        for (var key in filters) { if (filters.hasOwnProperty(key)) { var inp = document.createElement('input'); inp.name = key; inp.value = filters[key]; form.appendChild(inp); } }
        document.body.appendChild(form); form.submit(); document.body.removeChild(form);
    }
    
    const mVisCor = document.getElementById('modalVisCor'), vThumbsCor = document.getElementById('visThumbsCor'), vHeroCor = document.getElementById('visHeroCor'), vTitleCor = document.getElementById('visTitleCor'), vSkuCor = document.getElementById('visSkuCor'), vBrandCor = document.getElementById('visBrandCor'), vDescCor = document.getElementById('visDescCor'), vSpecsCor = document.getElementById('visSpecsContentCor');
    
    function abrirVisualizadorCor(sku) {
        var url = document.getElementById('hardness_ajaxUrl_cor').value; // ID CORRIGIDO
        var sysId = document.getElementById('sys_base_divId_cor').value; // ID CORRIGIDO
        
        if (sysId && typeof jQuery !== 'undefined' && jQuery('#' + sysId).length) jQuery('#' + sysId).showLoading();
        
        jQuery.ajax({ url: url, type: 'POST', dataType: 'json', data: { ajax: 1, action: 'get_details_cor', sku: sku }, success: function (res) { if (res.ok) { vTitleCor.innerText = res.titulo; vSkuCor.innerText = res.sku; vBrandCor.innerText = res.marca; vDescCor.innerHTML = res.desc ? res.desc : '<em>Sem descrição.</em>'; vThumbsCor.innerHTML = ''; if (res.imgs.length > 0) vHeroCor.src = res.imgs[0]; res.imgs.forEach((url, idx) => { let img = document.createElement('img'); img.src = url; img.className = 'vis-mini'; if (idx === 0) img.classList.add('active'); img.onclick = () => { vHeroCor.src = url; document.querySelectorAll('.vis-mini').forEach(el => el.classList.remove('active')); img.classList.add('active'); }; vThumbsCor.appendChild(img); }); let h = '<table class="vis-specs-table">'; let has = false; if (res.specs.EAN) { h += `<tr><td><strong>EAN:</strong> ${res.specs.EAN}</td></tr>`; has = true; } if (res.specs.Garantia) { h += `<tr><td><strong>Garantia:</strong> ${res.specs.Garantia}</td></tr>`; has = true; } if (res.specs.Peso) { h += `<tr><td><strong>Peso:</strong> ${res.specs.Peso}</td></tr>`; has = true; } if (res.specs.Altura) { h += `<tr><td><strong>Altura:</strong> ${res.specs.Altura}</td></tr>`; has = true; } if (res.specs.Largura) { h += `<tr><td><strong>Largura:</strong> ${res.specs.Largura}</td></tr>`; has = true; } if (res.specs.Comprimento) { h += `<tr><td><strong>Comp.:</strong> ${res.specs.Comprimento}</td></tr>`; has = true; } h += '</table>'; vSpecsCor.innerHTML = has ? h : '<div style="color:#999;font-size:12px">Vazio</div>'; mVisCor.style.display = 'flex'; } else { alert(res.msg || 'Erro ao carregar'); } }, error: function () { alert('Erro na comunicação'); }, complete: function () { if (sysId && typeof jQuery !== 'undefined' && jQuery('#' + sysId).length) jQuery('#' + sysId).hideLoading(); } });
    }
    function fecharVisCor() { mVisCor.style.display = 'none'; }
    function imprimirConteudoModalCor() { const f = document.createElement('iframe'); f.style.display = 'none'; document.body.appendChild(f); const d = f.contentWindow.document; const s = vSpecsCor.innerHTML; const c = `<html><head><style>body{font-family:Arial,sans-serif;padding:20px;color:#333}h1{font-size:20px;margin-bottom:5px}.meta{color:#666;font-size:12px;margin-bottom:20px;border-bottom:1px solid #ccc;padding-bottom:10px}.hero{text-align:center;margin-bottom:20px}.hero img{max-width:300px;max-height:300px}.desc{font-size:12px;line-height:1.5;margin-bottom:20px; text-align:justify;}.specs-box{border:1px solid #eee;padding:10px;border-radius:5px}.specs-box table{width:100%;font-size:12px}.specs-box td{padding:4px 0}</style></head><body><h1>${vTitleCor.innerText}</h1><div class="meta">SKU: ${vSkuCor.innerText} | ${vBrandCor.innerText}</div><div class="hero"><img src="${vHeroCor.src}"></div><h3>Descrição</h3><div class="desc">${vDescCor.innerHTML}</div><h3>Specs</h3><div class="specs-box">${s}</div></body></html>`; d.open(); d.write(c); d.close(); setTimeout(() => { f.contentWindow.print(); setTimeout(() => document.body.removeChild(f), 1000); }, 200); }
    document.addEventListener('keydown', e => { if (e.key === "Escape") fecharVisCor() });
</script>