<?php
// =============================================================================
// POPULADOR D001E - ATUALIZAÇÃO DO ID SKU ANYMARKET E TÍTULO
// =============================================================================

error_reporting(E_ALL & ~E_NOTICE);
ini_set('display_errors', 1);

global $g, $confUsuario;

// 1) CARREGAR CLASSES
if (!class_exists('API001') && !class_exists('hardness\\API001')) {
    require_once('bibliotecas/classes/API001.php');
}
if (!class_exists('GMP010') && !class_exists('hardness\\GMP010')) {
    require_once('bibliotecas/classes/GMP010.php');
}

// 2) INICIALIZAR API
try {
    $apiClass = class_exists('hardness\\API001') ? 'hardness\\API001' : 'API001';
    $API001   = new $apiClass();
    $token    = $API001->executaProcesso(527);

    $baseUrl         = 'https://api.anymarket.com.br/v2';
    $globalPathDados = isset($g['pathDados']) ? $g['pathDados'] : null;
    $gmpClass        = class_exists('hardness\\GMP010') ? 'hardness\\GMP010' : 'GMP010';
    $apiManager      = new $gmpClass($baseUrl, $token, 3, [], 'error_log', $globalPathDados);
} catch (\Exception $e) {
    return;
}

// 3) CONFIGURAÇÃO DO LOOP
$reqCount     = 0;
$maxRequests  = 3000;
$loteTamanho  = 200;

while (true) {
    if ($reqCount >= $maxRequests) break;

    $precisaConectar = false;

    if ($reqCount > 0 && $reqCount % $loteTamanho == 0) {
        $precisaConectar = true;
        sleep(10);
    }

    if (!isset($g['conexaoBanco']) || !$g['conexaoBanco'] || !($g['conexaoBanco'] instanceof \mysqli)) {
        $precisaConectar = true;
    } elseif (!@\mysqli_ping($g['conexaoBanco'])) {
        $precisaConectar = true;
    }

    if ($precisaConectar) {
        if (isset($g['conexaoBanco']) && $g['conexaoBanco'] instanceof \mysqli) @\mysqli_close($g['conexaoBanco']);
        $con = @\mysqli_connect($confUsuario['dbHost'], $confUsuario['dbUser'], $confUsuario['dbPass'], $confUsuario['dbDatabase']);
        if ($con) {
            $g['conexaoBanco'] = $con;
        } else {
            sleep(2);
            continue;
        }
    }

    // 4) BUSCA 1 PRODUTO
    $sqlBusca = "
        SELECT D001E_Id, D001E_D001_Id, D001E_D001_Codigo_Produto
        FROM D001E
        WHERE (D001E_Id_Any IS NULL OR D001E_Id_Any = 0 OR D001E_Id_Any = '')
        ORDER BY D001E_Id ASC
        LIMIT 1
    ";

    $rs = \mysqli_query($g['conexaoBanco'], $sqlBusca);
    
    if (!$rs || \mysqli_num_rows($rs) == 0) break;

    $row     = \mysqli_fetch_assoc($rs);
    $idTable = $row['D001E_Id'];
    $idProd  = $row['D001E_D001_Id'];
    $sku     = trim($row['D001E_D001_Codigo_Produto']);

    if (empty($sku)) {
        \mysqli_query($g['conexaoBanco'], "UPDATE D001E SET D001E_ult_att = NOW() WHERE D001E_Id = $idTable");
        continue;
    }

    $idAnySku = 0;
    $tituloSku = '';
    $rateLimitExceeded = false;

    try {
        $endpoint = "/products?sku=" . urlencode($sku);
        $resp = $apiManager->request($endpoint, 'GET', null, true, ['return_on_failure' => true]);
        $reqCount++;

        if (isset($resp['body']) && is_string($resp['body']) && strpos($resp['body'], 'API rate limit exceeded') !== false) {
            $rateLimitExceeded = true;
        } elseif (isset($resp['code']) && $resp['code'] == 429) {
            $rateLimitExceeded = true;
        }

        if ($rateLimitExceeded) {
            sleep(30);
            if(isset($g['conexaoBanco']) && $g['conexaoBanco'] instanceof \mysqli) @\mysqli_close($g['conexaoBanco']);
            $g['conexaoBanco'] = null;
            $reqCount--;
            continue;
        }

        $bodyRaw = isset($resp['body']) ? $resp['body'] : null;
        $body = is_array($bodyRaw) ? $bodyRaw : (json_decode($bodyRaw, true) ?: []);

        // CAPTURA DO ID DO SKU E TITULO
        if ($resp && isset($resp['code']) && $resp['code'] == 200 && !empty($body['content'][0])) {
            $prod = $body['content'][0];
            
            // Itera sobre os SKUs para achar o que tem o partnerId igual ao SKU buscado
            if (isset($prod['skus']) && is_array($prod['skus'])) {
                foreach ($prod['skus'] as $s) {
                    // Verifica se o partnerId do SKU da API bate com o SKU do nosso banco
                    // Ou se só tem 1 SKU, assume que é ele mesmo
                    if ((isset($s['partnerId']) && $s['partnerId'] == $sku) || count($prod['skus']) == 1) {
                        if (isset($s['id']) && is_numeric($s['id'])) {
                            // [MODIFICADO]: Pega o ID do Produto ($prod['id']) ao invés do ID do SKU ($s['id'])
                            $idAnySku = (int) $prod['id']; 
                            $tituloSku = isset($s['title']) ? $s['title'] : '';
                            break; // Achou, para o loop
                        }
                    }
                }
            }
        }
    } catch (\Exception $e) {}

    // Se não achou ID do SKU, marca att e pula
    if ($idAnySku <= 0) {
        \mysqli_query($g['conexaoBanco'], "UPDATE D001E SET D001E_ult_att = NOW() WHERE D001E_Id = $idTable");
        usleep(200000); 
        continue;
    }

    $tituloSafe = \mysqli_real_escape_string($g['conexaoBanco'], $tituloSku);

    $sqlUpdate = "
        UPDATE D001E
        SET
            D001E_Id_Any = $idAnySku,
            D001E_Sku_Titulo = '$tituloSafe',
            D001E_ult_att = NOW()
        WHERE D001E_Id = $idTable
    ";
    \mysqli_query($g['conexaoBanco'], $sqlUpdate);

    usleep(200000); 
}

return;
?>