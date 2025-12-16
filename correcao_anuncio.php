<?php
/**
 * [SUMARIO] PAINEL DE MELHORIAS (V1 - BASEADO NA V32)
 * ----------------------------------------------------------------------------
 * [TABELA] .... D001F (Sem pontuação)
 * [CONFIG] .... Configurações
 * [STYLE] ..... CSS (Barra Horizontal, Cores Guimepa, Header Fixo)
 * [JS_TOOL] ... Tooltip, Modal, Paginação e Exportação CSV
 * [RENDER] .... Loop Principal e Exportação Bruta
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
            🏢 Melhorias Anúncio: <span style='color: #0098D3; font-size:13px;'>ID {$C004_Id}</span>
          </div>";
}

// =============================================================================
// [FUNC] FUNÇÕES AUXILIARES
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

// =============================================================================
// [RENDER] FUNÇÃO DE LINHA
// =============================================================================
function renderImprovementRow($row)
{
    // Tratamento de Marca (caso precise extrair de algum JSON legado ou use a coluna direta)
    $marca = isset($row['D001F_Marca']) ? $row['D001F_Marca'] : 'ND';

    // Imagem
    $imgCapa = $row['D001F_Imagem_1'] ?: "https://via.placeholder.com/100x100?text=Sem+Img";

    // Dados Básicos
    $titulo    = htmlspecialchars($row['D001F_Titulo'], ENT_QUOTES);
    $skuRaw    = $row['D001F_D001_Codigo_Produto'];
    $sku       = htmlspecialchars($skuRaw, ENT_QUOTES);
    $descRaw   = $row['D001F_Descricao'];
    $marcaHtml = htmlspecialchars($marca, ENT_QUOTES);

    // Specs
    $specHtml = "";
    if (!empty($row['D001F_EAN']))
        $specHtml .= "<b>EAN:</b> {$row['D001F_EAN']}<br>";
    if (!empty($row['D001F_garantia']))
        $specHtml .= "<b>Gar:</b> {$row['D001F_garantia']}<br>";
    if (!empty($row['D001F_peso']))
        $specHtml .= "<b>Peso:</b> {$row['D001F_peso']}<br>";
    if (!empty($row['D001F_altura']))
        $specHtml .= "<b>Dim:</b> " . ($row['D001F_altura'] ?: 0) . "x" . ($row['D001F_largura'] ?: 0) . "x" . ($row['D001F_comprimento'] ?: 0);
    if (empty($specHtml))
        $specHtml = "<span style='color:#bbb'>Vazio</span>";

    // --- DADOS D009 (Métricas) ---
    $freqVenda = !empty($row['D009_Frequencia_Venda']) ? $row['D009_Frequencia_Venda'] : '<b>0</b>';
    $custoVal  = isset($row['D009_Valor_Custo_Unitario']) ? (float) $row['D009_Valor_Custo_Unitario'] : 0;
    $estTab    = isset($row['D009_Quantidade_Estoque_Tabela']) ? (int) $row['D009_Quantidade_Estoque_Tabela'] : 0;
    $estLiq    = isset($row['D009_Quantidade_Estoque_Liquido']) ? (int) $row['D009_Quantidade_Estoque_Liquido'] : 0;

    $custoHtml  = ($custoVal > 0) ? "<span style='color:#0098D3; font-weight:700;'>R$ " . number_format($custoVal, 2, ',', '.') . "</span>" : "<b>0</b>";
    $estTabHtml = ($estTab > 0) ? $estTab : "<b>0</b>";
    $estLiqHtml = ($estLiq > 0) ? $estLiq : "<b>0</b>";

    return "
    <div class='quality-row'>
        <div class='thumb-box' onclick='abrirVisualizador(\"$sku\")'><img src='$imgCapa'></div>
        
        <div class='col-info'>
            <div class='prod-title'>{$row['D001F_Titulo']}</div>
            <div class='prod-sub'>
                <span class='prod-sku'>SKU: {$row['D001F_D001_Codigo_Produto']}</span>
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
        
        <div style='display:flex; align-items:center; justify-content:center;'>
             <button class='pg-btn' onclick='abrirVisualizador(\"$sku\")' title='Visualizar'><i class='material-icons' style='font-size:16px'>visibility</i></button>
        </div>
    </div>";
}

// =============================================================================
// [AJAX] GERENCIADOR
// =============================================================================
if ($isAjax) {

    function cleanInput($data)
    {
        $data = trim($data);
        return mysql_real_escape_string($data);
    }

    // [EXPORTAÇÃO CSV BLINDADA]
    if (isset($_POST['action']) && $_POST['action'] === 'export_csv') {

        // RECUPERA FILTROS
        $where = ["1=1"];
        if (!empty($_POST['f_tit'])) {
            $ft      = cleanInput($_POST['f_tit']);
            $where[] = "T1.D001F_Titulo LIKE '%$ft%'";
        }
        if (!empty($_POST['f_sku'])) {
            $fs      = cleanInput($_POST['f_sku']);
            $where[] = "T1.D001F_D001_Codigo_Produto LIKE '%$fs%'";
        }
        if (!empty($_POST['f_mar'])) {
            $fm      = cleanInput($_POST['f_mar']);
            $where[] = "T1.D001F_Marca LIKE '%$fm%'";
        }
        if (!empty($_POST['f_desc'])) {
            $fd      = cleanInput($_POST['f_desc']);
            $where[] = "T1.D001F_Descricao LIKE '%$fd%'";
        }
        // Busca generica em specs
        if (!empty($_POST['f_spec'])) {
            $fsp     = cleanInput($_POST['f_spec']);
            $where[] = "(T1.D001F_EAN LIKE '%$fsp%' OR T1.D001F_garantia LIKE '%$fsp%' OR T1.D001F_peso LIKE '%$fsp%')";
        }

        // Numéricos Exatos
        if (isset($_POST['f_est_liq']) && $_POST['f_est_liq'] !== '') {
            $val     = (int) $_POST['f_est_liq'];
            $where[] = "T2.D009_Quantidade_Estoque_Liquido = $val";
        }
        if (isset($_POST['f_est_tab']) && $_POST['f_est_tab'] !== '') {
            $val     = (int) $_POST['f_est_tab'];
            $where[] = "T2.D009_Quantidade_Estoque_Tabela = $val";
        }
        if (!empty($_POST['f_freq'])) {
            $val     = cleanInput($_POST['f_freq']);
            $where[] = "T2.D009_Frequencia_Venda LIKE '%$val%'";
        }
        if (!empty($_POST['f_custo'])) {
            $val     = (float) str_replace(',', '.', $_POST['f_custo']);
            $where[] = "T2.D009_Valor_Custo_Unitario = $val";
        }

        $whereStr = implode(" AND ", $where);

        $sqlCsv = "SELECT T1.* FROM D001F AS T1
                   LEFT JOIN D049 ON D049.D049_D001_Id = T1.D001F_D001_Id
                   LEFT JOIN D009 AS T2 ON (T2.D009_D049_Id = D049.D049_Id AND T2.D009_C004_Id = $C004_Id)
                   WHERE $whereStr
                   GROUP BY T1.D001F_Id
                   ORDER BY T1.D001F_Id ASC";

        $rsCsv = mysql_query($sqlCsv);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=melhorias_produtos_' . date('YmdHis') . '.csv');

        $out = fopen('php://output', 'w');
        fputs($out, "\xEF\xBB\xBF"); // BOM para Excel

        // CABEÇALHO DO CSV (EXATO DO BANCO D001F)
        $header = [
            'D001F_Id',
            'D001F_D001_Id',
            'D001F_D001_Codigo_Produto',
            'D001F_Titulo',
            'D001F_Marca',
            'D001F_Descricao',
            'D001F_Imagem_1',
            'D001F_Imagem_2',
            'D001F_Imagem_3',
            'D001F_Imagem_4',
            'D001F_Imagem_5',
            'D001F_Imagem_6',
            'D001F_Imagem_7',
            'D001F_Imagem_8',
            'D001F_Imagem_9',
            'D001F_Imagem_10',
            'D001F_EAN',
            'D001F_garantia',
            'D001F_peso',
            'D001F_altura',
            'D001F_largura',
            'D001F_comprimento',
            'D001F_ult_att'
        ];
        fputcsv($out, $header, ';');

        // SÓ REMOVE QUEBRA DE LINHA PARA NÃO QUEBRAR O EXCEL
        function simpleClean($str)
        {
            if (is_null($str))
                return '';
            $str = str_replace(["\r\n", "\r", "\n", "\t"], ' ', $str);
            return trim($str);
        }

        if ($rsCsv) {
            while ($row = mysql_fetch_assoc($rsCsv)) {
                // Monta array na ordem exata do header
                $line = [
                    $row['D001F_Id'],
                    $row['D001F_D001_Id'],
                    simpleClean($row['D001F_D001_Codigo_Produto']),
                    simpleClean($row['D001F_Titulo']),
                    simpleClean($row['D001F_Marca']),
                    simpleClean($row['D001F_Descricao']),
                    $row['D001F_Imagem_1'],
                    $row['D001F_Imagem_2'],
                    $row['D001F_Imagem_3'],
                    $row['D001F_Imagem_4'],
                    $row['D001F_Imagem_5'],
                    $row['D001F_Imagem_6'],
                    $row['D001F_Imagem_7'],
                    $row['D001F_Imagem_8'],
                    $row['D001F_Imagem_9'],
                    $row['D001F_Imagem_10'],
                    simpleClean($row['D001F_EAN']),
                    simpleClean($row['D001F_garantia']),
                    simpleClean($row['D001F_peso']),
                    simpleClean($row['D001F_altura']),
                    simpleClean($row['D001F_largura']),
                    simpleClean($row['D001F_comprimento']),
                    $row['D001F_ult_att']
                ];
                fputcsv($out, $line, ';');
            }
        }
        fclose($out);
        exit;
    }

    // CONTINUAÇÃO PARA REQUISIÇÕES JSON (SEARCH/MODAL)
    header('Content-Type: application/json; charset=UTF-8');

    if (isset($_POST['action']) && $_POST['action'] === 'get_details') {
        $skuBusca = isset($_POST['sku']) ? mysql_real_escape_string($_POST['sku']) : '';
        // NOTA: D001F aqui
        $sqlDet = "SELECT T1.*, 
                          T2.D009_Frequencia_Venda, 
                          T2.D009_Valor_Custo_Unitario, 
                          T2.D009_Quantidade_Estoque_Tabela, 
                          T2.D009_Quantidade_Estoque_Liquido
                    FROM D001F AS T1
                    LEFT JOIN D049 ON D049.D049_D001_Id = T1.D001F_D001_Id
                    LEFT JOIN D009 AS T2 ON (T2.D009_D049_Id = D049.D049_Id AND T2.D009_C004_Id = $C004_Id)
                    WHERE T1.D001F_D001_Codigo_Produto = '$skuBusca' LIMIT 1";
        $rsDet  = mysql_query($sqlDet);
        if ($rsDet && mysql_num_rows($rsDet) > 0) {
            $row   = mysql_fetch_assoc($rsDet);
            $marca = isset($row['D001F_Marca']) ? $row['D001F_Marca'] : 'ND';

            $imgs = [];
            for ($i = 1; $i <= 10; $i++)
                if (!empty($row["D001F_Imagem_$i"]))
                    $imgs[] = $row["D001F_Imagem_$i"];
            if (empty($imgs))
                $imgs[] = "https://via.placeholder.com/600x600?text=Sem+Imagem";

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
        }
        else {
            echo json_encode(['ok' => 0, 'msg' => 'Produto não encontrado']);
        }
        exit;
    }

    // SEARCH & FILTER
    $where = ["1=1"];
    // Filtros de Texto
    if (!empty($_POST['f_tit'])) {
        $ft      = cleanInput($_POST['f_tit']);
        $where[] = "T1.D001F_Titulo LIKE '%$ft%'";
    }
    if (!empty($_POST['f_sku'])) {
        $fs      = cleanInput($_POST['f_sku']);
        $where[] = "T1.D001F_D001_Codigo_Produto LIKE '%$fs%'";
    }
    if (!empty($_POST['f_mar'])) {
        $fm      = cleanInput($_POST['f_mar']);
        $where[] = "T1.D001F_Marca LIKE '%$fm%'";
    }
    if (!empty($_POST['f_desc'])) {
        $fd      = cleanInput($_POST['f_desc']);
        $where[] = "T1.D001F_Descricao LIKE '%$fd%'";
    }
    if (!empty($_POST['f_spec'])) {
        $fsp     = cleanInput($_POST['f_spec']);
        $where[] = "(T1.D001F_EAN LIKE '%$fsp%' OR T1.D001F_garantia LIKE '%$fsp%' OR T1.D001F_peso LIKE '%$fsp%')";
    }

    // Numéricos Exatos (Tabela D009 não muda, pois é metrics)
    if (isset($_POST['f_est_liq']) && $_POST['f_est_liq'] !== '') {
        $val     = (int) $_POST['f_est_liq'];
        $where[] = "T2.D009_Quantidade_Estoque_Liquido = $val";
    }
    if (isset($_POST['f_est_tab']) && $_POST['f_est_tab'] !== '') {
        $val     = (int) $_POST['f_est_tab'];
        $where[] = "T2.D009_Quantidade_Estoque_Tabela = $val";
    }
    if (!empty($_POST['f_freq'])) {
        $val     = cleanInput($_POST['f_freq']);
        $where[] = "T2.D009_Frequencia_Venda LIKE '%$val%'";
    }
    if (!empty($_POST['f_custo'])) {
        $val     = (float) str_replace(',', '.', $_POST['f_custo']);
        $where[] = "T2.D009_Valor_Custo_Unitario = $val";
    }

    $whereStr  = implode(" AND ", $where);
    $totalRows = 0;

    // SQL COUNT
    $sqlCount = "SELECT COUNT(*) AS total FROM D001F AS T1 
                 LEFT JOIN D049 ON D049.D049_D001_Id = T1.D001F_D001_Id 
                 LEFT JOIN D009 AS T2 ON (T2.D009_D049_Id = D049.D049_Id AND T2.D009_C004_Id = $C004_Id) 
                 WHERE $whereStr";
    $rsCount  = mysql_query($sqlCount);
    if ($rsCount) {
        $r         = mysql_fetch_assoc($rsCount);
        $totalRows = (int) ($r['total'] ?? 0);
    }

    // SQL LIST
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
            $html .= renderImprovementRow($row);
        }
    }

    echo json_encode(['ok' => 1, 'total' => $totalRows, 'page' => $page, 'pageSize' => $limit, 'html' => $html]);
    exit;
}

// =============================================================================
// [STYLE] CSS
// =============================================================================
$style = <<<STYLE
<style>
    :root { --bg-body: #f3f4f6; --card-bg: #ffffff; --text-color: #1f2937; --border-color: #e5e7eb; --primary: #0098D3; }
    body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif; background-color: var(--bg-body); margin: 0; padding: 20px; color: var(--text-color); }
    .quality-list { max-width: 1600px; margin: 0 auto; margin-top: 20px; }
    
    /* FILTRO CONTAINER */
    .filter-container { background: #fff; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); padding: 16px; margin-bottom: 20px; max-width: 1600px; margin: 0 auto 20px auto; border: 1px solid #e5e7eb; }
    .filter-header { display: flex; justify-content: space-between; align-items: center; cursor: pointer; user-select: none; }
    .filter-title { font-size: 14px; font-weight: 700; color: #374151; display:flex; align-items:center; gap:8px; text-transform:uppercase; letter-spacing:0.05em; }
    .filter-icon { color: var(--primary); font-size: 20px; }
    .filter-chevron { transition: transform 0.2s; color: #9ca3af; }
    .filter-body { display: block; margin-top: 15px; border-top: 1px solid #f3f4f6; padding-top: 15px; }
    .filter-body.closed { display: none; }
    .filter-header.closed .filter-chevron { transform: rotate(-90deg); }
    
    /* GRID FILTROS */
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
    
    /* TABELA */
    /* Layout Novo: Foto | Prod | Metrics | Desc | Specs | Action */
    .quality-header, .quality-row { display: grid; grid-template-columns: 70px 1.5fr 1fr 1.5fr 1fr 60px; gap: 12px; align-items: center; }
    
    .quality-header { 
        position: sticky; top: 0; z-index: 50; 
        background: #f9fafb; border-bottom: 2px solid #e5e7eb;
        padding: 12px 16px; 
        font-size: 11px; font-weight: 700; color: #6b7280; text-transform: uppercase; letter-spacing: 0.03em; 
        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    }
    .quality-header > div { display: flex; align-items: center; justify-content: center; text-align: center; }
    .quality-header > div:nth-child(2), .quality-header > div:nth-child(4), .quality-header > div:nth-child(5) { justify-content: flex-start; text-align: left; }
    
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
    
    .start-msg { text-align: center; padding: 80px 20px; color: #9ca3af; background: #fff; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
    .start-msg i { font-size: 64px; color: #e5e7eb; margin-bottom: 15px; }
    .start-msg h2 { font-size: 20px; margin-bottom: 8px; color: #374151; }
    
    .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); z-index: 9999; justify-content: center; align-items: center; backdrop-filter: blur(3px); padding: 20px; }
    .modal-content { background: #fff; width: 100%; max-width: 1100px; height: 90%; border-radius: 12px; position: relative; display: flex; overflow: hidden; box-shadow: 0 20px 40px rgba(0,0,0,0.2); }
    .close-modal { position: absolute; top: 10px; right: 15px; font-size: 24px; cursor: pointer; z-index: 100; color: #9ca3af; }
    .close-modal:hover { color: #333; }
    .vis-thumbs { width: 100px; background: #f9fafb; padding: 10px; overflow-y: auto; display: flex; flex-direction: column; gap: 8px; border-right: 1px solid #e5e7eb; }
    .vis-mini { width: 100%; height: 70px; object-fit: contain; border: 2px solid transparent; border-radius: 6px; cursor: pointer; background: #fff; border: 1px solid #f1f1f1; }
    .vis-mini.active { border-color: var(--primary); }
    .vis-main { flex: 1; display: flex; justify-content: center; align-items: center; background: #fff; padding: 20px; position: relative; }
    .vis-main img { max-width: 100%; max-height: 100%; object-fit: contain; }
    .vis-score-badge { display:none; } /* Sem score */
    .vis-info { width: 350px; border-left: 1px solid #e5e7eb; padding: 20px; overflow-y: auto; background: #fff; display: flex; flex-direction: column; gap: 15px; }
    .vis-h1 { font-size: 18px; font-weight: 700; margin: 0; color: #111827; line-height: 1.3; }
    .vis-chip { display:none; } /* Sem chip de nota */
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
        .quality-header, .quality-row { grid-template-columns: 60px 1.4fr 160px 1.5fr 1fr 60px; gap: 8px; }
        .col-metrics { grid-template-columns: 1fr; gap: 2px; }
    }
    @media (max-width: 900px) {
        .f-grid { grid-template-columns: repeat(2, 1fr); }
        .quality-header { display: none; }
        .quality-row { display: flex; flex-direction: column; align-items: stretch; gap: 10px; position: relative; padding-top: 15px; }
        .thumb-box { align-self: flex-start; }
        .col-info { margin-left: 70px; margin-top: -70px; min-height: 60px; justify-content: center; }
        .col-metrics { display: flex; justify-content: space-between; flex-wrap: wrap; margin-top: 10px; }
    }
</style>
<link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
STYLE;
if (!$apiMode)
    echo $style;

echo "
<div class='filter-container'>
    <div class='filter-header' onclick='toggleFilterBody()'>
        <div class='filter-title'><i class='material-icons filter-icon'>tune</i> Filtros Avançados (Melhorias)</div>
        <i class='material-icons filter-chevron' id='filterChevron'>expand_more</i>
    </div>
    <div class='filter-body' id='filterBody'>
        <div class='f-grid'>
            <div class='f-group'><label class='f-label'>Título</label><input type='text' id='f_tit' class='f-input' placeholder='Ex: Parafusadeira'></div>
            <div class='f-group'><label class='f-label'>SKU / Cód</label><input type='text' id='f_sku' class='f-input' placeholder='Ex: 12345'></div>
            <div class='f-group'><label class='f-label'>Marca</label><input type='text' id='f_mar' class='f-input' placeholder='Ex: Bosch'></div>
            <div class='f-group'><label class='f-label'>Descrição</label><input type='text' id='f_desc' class='f-input' placeholder='Contém...'></div>
            <div class='f-group'><label class='f-label'>Specs/EAN</label><input type='text' id='f_spec' class='f-input' placeholder='Contém...'></div>
            <div class='f-group'><label class='f-label'>Est. Líquido (=)</label><input type='number' id='f_est_liq' class='f-input' placeholder='Exato'></div>
            <div class='f-group'><label class='f-label'>Est. Tabela (=)</label><input type='number' id='f_est_tab' class='f-input' placeholder='Exato'></div>
            <div class='f-group'><label class='f-label'>Frequência</label><input type='text' id='f_freq' class='f-input' placeholder='Ex: A'></div>
            <div class='f-group'><label class='f-label'>Custo (=)</label><input type='text' id='f_custo' class='f-input' placeholder='Ex: 10,90'></div>
        </div>
        <div class='f-actions'>
            <button class='f-btn-apply' onclick='applyFilters()'><i class='material-icons'>search</i> Aplicar Filtros</button>
            <button class='f-btn-export' onclick='exportCSV()'><i class='material-icons'>file_download</i> Exportar CSV</button>
        </div>
    </div>
</div>";

echo "<div class='quality-list'>";
echo "<div class='quality-header'>
        <div>Foto</div><div>Produto / Marca</div><div>Métricas</div><div>Descrição</div><div>Especificações</div><div>Ver</div>
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
        <div class="vis-main"><img id="visHero" src=""></div>
        <div class="vis-info">
            <h1 class="vis-h1"><span id="visTitle">--</span></h1>
            <div class="vis-meta">SKU: <strong id="visSku">--</strong> | Marca: <strong id="visBrand">--</strong></div>
            <button class="vis-btn-print" onclick="imprimirConteudoModal()"><i class="material-icons">print</i> Imprimir Ficha Técnica</button>
            <div class="vis-header-row"><span>Descrição do Produto</span></div>
            <div class="vis-desc-box" id="visDesc"></div>
            <div class="vis-specs-container"><div class="vis-header-row" style="margin-top:15px"><span>Especificações</span></div><div id="visSpecsContent"></div></div>
        </div>
    </div>
</div>

<script>
    function toggleFilterBody() {
        var b = document.getElementById('filterBody');
        var c = document.getElementById('filterChevron');
        if (b.classList.contains('closed')) { b.classList.remove('closed'); c.style.transform = 'rotate(0deg)'; } else { b.classList.add('closed'); c.style.transform = 'rotate(-90deg)'; }
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
                f_est_liq: jQuery('#f_est_liq').val(), f_est_tab: jQuery('#f_est_tab').val(), f_freq: jQuery('#f_freq').val(), f_custo: jQuery('#f_custo').val()
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
    const mVis = document.getElementById('modalVis'), vThumbs = document.getElementById('visThumbs'), vHero = document.getElementById('visHero'), vTitle = document.getElementById('visTitle'), vSku = document.getElementById('visSku'), vBrand = document.getElementById('visBrand'), vDesc = document.getElementById('visDesc'), vSpecs = document.getElementById('visSpecsContent');
    function abrirVisualizador(sku) {
        var url = document.getElementById('hardness_ajaxUrl').value; var sysId = document.getElementById('sys_base_divId').value; if (sysId && typeof jQuery !== 'undefined' && jQuery('#' + sysId).length) jQuery('#' + sysId).showLoading();
        jQuery.ajax({ url: url, type: 'POST', dataType: 'json', data: { ajax: 1, action: 'get_details', sku: sku }, success: function (res) { if (res.ok) { vTitle.innerText = res.titulo; vSku.innerText = res.sku; vBrand.innerText = res.marca; vDesc.innerHTML = res.desc ? res.desc : '<em>Sem descrição.</em>'; vThumbs.innerHTML = ''; if (res.imgs.length > 0) vHero.src = res.imgs[0]; res.imgs.forEach((url, idx) => { let img = document.createElement('img'); img.src = url; img.className = 'vis-mini'; if (idx === 0) img.classList.add('active'); img.onclick = () => { vHero.src = url; document.querySelectorAll('.vis-mini').forEach(el => el.classList.remove('active')); img.classList.add('active'); }; vThumbs.appendChild(img); }); let h = '<table class="vis-specs-table">'; let has = false; if (res.specs.EAN) { h += `<tr><td><strong>EAN:</strong> ${res.specs.EAN}</td></tr>`; has = true; } if (res.specs.Garantia) { h += `<tr><td><strong>Garantia:</strong> ${res.specs.Garantia}</td></tr>`; has = true; } if (res.specs.Peso) { h += `<tr><td><strong>Peso:</strong> ${res.specs.Peso}</td></tr>`; has = true; } if (res.specs.Altura) { h += `<tr><td><strong>Altura:</strong> ${res.specs.Altura}</td></tr>`; has = true; } if (res.specs.Largura) { h += `<tr><td><strong>Largura:</strong> ${res.specs.Largura}</td></tr>`; has = true; } if (res.specs.Comprimento) { h += `<tr><td><strong>Comp.:</strong> ${res.specs.Comprimento}</td></tr>`; has = true; } h += '</table>'; vSpecs.innerHTML = has ? h : '<div style="color:#999;font-size:12px">Vazio</div>'; mVis.style.display = 'flex'; } else { alert(res.msg || 'Erro ao carregar'); } }, error: function () { alert('Erro na comunicação'); }, complete: function () { if (sysId && typeof jQuery !== 'undefined' && jQuery('#' + sysId).length) jQuery('#' + sysId).hideLoading(); } });
    }
    function fecharVis() { mVis.style.display = 'none'; }
    function imprimirConteudoModal() { const f = document.createElement('iframe'); f.style.display = 'none'; document.body.appendChild(f); const d = f.contentWindow.document; const s = vSpecs.innerHTML; const c = `<html><head><style>body{font-family:Arial,sans-serif;padding:20px;color:#333}h1{font-size:24px;margin-bottom:5px}.meta{color:#666;font-size:12px;margin-bottom:20px;border-bottom:1px solid #ccc;padding-bottom:10px}.hero{text-align:center;margin-bottom:20px}.hero img{max-width:300px;max-height:300px}.desc{font-size:12px;line-height:1.5;margin-bottom:20px}.specs-box{border:1px solid #eee;padding:10px;border-radius:5px}.specs-box table{width:100%;font-size:12px}.specs-box td{padding:4px 0}</style></head><body><h1>${vTitle.innerText}</h1><div class="meta">SKU: ${vSku.innerText}</div><div class="hero"><img src="${vHero.src}"></div><h3>Descrição</h3><div class="desc">${vDesc.innerHTML}</div><h3>Specs</h3><div class="specs-box">${s}</div></body></html>`; d.open(); d.write(c); d.close(); setTimeout(() => { f.contentWindow.print(); setTimeout(() => document.body.removeChild(f), 1000); }, 200); }
    document.addEventListener('keydown', e => { if (e.key === "Escape") fecharVis() });
</script>