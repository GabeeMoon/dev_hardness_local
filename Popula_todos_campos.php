<?php
/*
 POPULADOR D001E - MASTER (BASEADO EM IMAGEM NULL)
 */
namespace hardness;

error_reporting(E_ALL & ~E_NOTICE);
ini_set('display_errors', 1);

global $g, $confUsuario;

// =============================================================================
// 1) CARREGAR CLASSES
// =============================================================================
if (!class_exists('hardness\API001') && !class_exists('API001')) {
    $pathAPI = 'bibliotecas/classes/API001.php';
    if (file_exists($pathAPI)) require_once($pathAPI);
}
if (!class_exists('hardness\GMP010') && !class_exists('GMP010')) {
    $pathGMP = 'bibliotecas/classes/GMP010.php';
    if (file_exists($pathGMP)) require_once($pathGMP);
}

// =============================================================================
// 2) INICIALIZAR API
// =============================================================================
try {
    $apiClass = class_exists('hardness\API001') ? 'hardness\API001' : 'API001';
    $API001   = new $apiClass();
    $token    = $API001->executaProcesso(527);

    $baseUrl         = 'https://api.anymarket.com.br/v2';
    $globalPathDados = isset($g['pathDados']) ? $g['pathDados'] : null;
    
    $gmpClass   = class_exists('hardness\GMP010') ? 'hardness\GMP010' : 'GMP010';
    $apiManager = new $gmpClass($baseUrl, $token, 3, [], 'error_log', $globalPathDados);
} catch (\Exception $e) {
    return;
}

// =============================================================================
// 3) CONFIGURAÇÃO DO LOOP
// =============================================================================
$reqCount    = 0;
$maxRequests = 3000;
$loteTamanho = 200;

while (true) {
    if ($reqCount >= $maxRequests) break;

    // --- CONEXÃO ---
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

    // =========================================================================
    // 4) BUSCA E LEITURA DE DADOS
    //    Condição: Apenas se D001E_Imagem_1 estiver vazia
    // =========================================================================
    $sqlBusca = "SELECT * FROM D001E
                 WHERE (D001E_Imagem_1 IS NULL OR D001E_Imagem_1 = '')
                 ORDER BY D001E_ult_att ASC 
                 LIMIT 1";

    $rs = \mysqli_query($g['conexaoBanco'], $sqlBusca);
    if (!$rs || \mysqli_num_rows($rs) == 0) break;

    $atual   = \mysqli_fetch_assoc($rs);
    $idTable = (int)$atual['D001E_Id'];
    $idProd  = (int)$atual['D001E_D001_Id'];
    $sku     = trim($atual['D001E_D001_Codigo_Produto']);

    if (empty($sku)) {
        \mysqli_query($g['conexaoBanco'], "UPDATE D001E SET D001E_ult_att = NOW() WHERE D001E_Id = $idTable");
        continue;
    }

    // Variáveis para preenchimento
    $idAnySku    = 0;
    $tituloSku   = '';
    $titulo      = ''; 
    $descricao   = ''; 
    $imagens     = []; 
    $marca       = '';
    $ean         = ''; 
    $garantia    = ''; 
    $peso        = '';
    $altura      = ''; 
    $largura     = ''; 
    $comprimento = '';
    
    $rateLimitExceeded = false;

    // --- API REQUEST ---
    try {
        $endpoint = "/products?sku=" . urlencode($sku);
        $resp     = $apiManager->request($endpoint, 'GET', null, true, ['return_on_failure' => true]);
        $reqCount++;

        if (isset($resp['body']) && is_string($resp['body']) && strpos($resp['body'], 'API rate limit exceeded') !== false) {
            $rateLimitExceeded = true;
        } elseif (isset($resp['code']) && $resp['code'] == 429) {
            $rateLimitExceeded = true;
        }

        if ($rateLimitExceeded) {
            sleep(30);
            if (isset($g['conexaoBanco']) && $g['conexaoBanco'] instanceof \mysqli) @\mysqli_close($g['conexaoBanco']);
            $g['conexaoBanco'] = null;
            $reqCount--;
            continue;
        }

        $bodyRaw = isset($resp['body']) ? $resp['body'] : null;
        $body    = is_array($bodyRaw) ? $bodyRaw : (json_decode($bodyRaw, true) ?: []);

        // --- PARSE API ---
        if ($resp && isset($resp['code']) && $resp['code'] == 200 && !empty($body['content'][0])) {
            $d = $body['content'][0];

            // 1. Dados Básicos do Produto
            $titulo    = isset($d['title']) ? $d['title'] : '';
            $descricao = isset($d['description']) ? $d['description'] : '';
            
            // Marca
            if (!empty($d['brand']['name'])) $marca = $d['brand']['name'];
            elseif (!empty($d['brand']['reducedName'])) $marca = $d['brand']['reducedName'];
            elseif (!empty($d['brand']['partnerId'])) $marca = $d['brand']['partnerId'];

            // Specs
            $garantia    = isset($d['warrantyText']) ? $d['warrantyText'] : '';
            $peso        = isset($d['weight']) ? $d['weight'] : '';
            $altura      = isset($d['height']) ? $d['height'] : '';
            $largura     = isset($d['width']) ? $d['width'] : '';
            $comprimento = isset($d['length']) ? $d['length'] : '';

            // Imagens
            if (!empty($d['images']) && is_array($d['images'])) {
                foreach ($d['images'] as $img) {
                    if (!empty($img['url'])) $imagens[] = $img['url'];
                }
            }

            // 2. Dados do SKU (ID e EAN)
            if (isset($d['skus']) && is_array($d['skus'])) {
                if (!empty($d['skus'][0]['ean'])) $ean = $d['skus'][0]['ean'];
                
                foreach ($d['skus'] as $s) {
                    if ((isset($s['partnerId']) && $s['partnerId'] == $sku) || count($d['skus']) == 1) {
                        if (isset($s['id']) && is_numeric($s['id'])) {
                            $idAnySku  = (int) $d['id']; // ID do Produto
                            $tituloSku = isset($s['title']) ? $s['title'] : '';
                        }
                    }
                }
            }
        }
    } catch (\Exception $e) {}

    // --- FALLBACK IMAGENS (LOCAL) ---
    if (empty($imagens)) {
        // T172
        $r172 = \mysqli_query($g['conexaoBanco'], "SELECT T172_Id, T172_Nome_Arquivo FROM T172 WHERE T172_D001_Id = '$idProd' ORDER BY T172_Nome_Arquivo DESC");
        if ($r172) {
            while ($m = \mysqli_fetch_assoc($r172)) {
                $ext    = pathinfo($m['T172_Nome_Arquivo'], PATHINFO_EXTENSION);
                $dbName = isset($confUsuario['dbDatabase']) ? $confUsuario['dbDatabase'] : 'e229';
                $imagens[] = "/hardness3/dados_usuarios/{$dbName}/produtos/{$idProd}/fotos/{$m['T172_Id']}.$ext";
            }
        }
        // T144
        if (empty($imagens)) {
            $r144 = \mysqli_query($g['conexaoBanco'], "SELECT T144_Url FROM T144 WHERE T144_D001_Id = '$idProd'");
            if ($r144) {
                while ($m = \mysqli_fetch_assoc($r144)) {
                    if (!empty($m['T144_Url'])) $imagens[] = $m['T144_Url'];
                }
            }
        }
    }

    // --- FALLBACK TEXTOS (D001) ---
    if (empty($titulo) || empty($descricao)) {
        $rD = \mysqli_query($g['conexaoBanco'], "SELECT D001_Descricao FROM D001 WHERE D001_Id = '$idProd'");
        if ($rD && $r = \mysqli_fetch_assoc($rD)) {
            if (empty($titulo))    $titulo    = $r['D001_Descricao'];
            if (empty($descricao)) $descricao = $r['D001_Descricao'];
        }
    }

    // --- PREPARA UPDATE (DIFERENCIAL) ---
    // Atualiza tudo que encontrar, pois a imagem estava vazia
    $sets = [];

    // ID AnyMarket (Principal)
    if ($idAnySku > 0 && $idAnySku != $atual['D001E_Id_Any']) {
        $sets[] = "D001E_Id_Any = $idAnySku";
    }
    // Titulo SKU
    if (!empty($tituloSku) && $tituloSku !== $atual['D001E_Sku_Titulo']) {
        $ts = \mysqli_real_escape_string($g['conexaoBanco'], $tituloSku);
        $sets[] = "D001E_Sku_Titulo = '$ts'";
    }

    // Campos de Conteúdo
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
        // Atualiza se o valor novo não for vazio e for diferente do atual
        // OU se o atual estiver vazio, aceita qualquer coisa
        if ($val !== $atual[$col]) {
            $safeVal = \mysqli_real_escape_string($g['conexaoBanco'], $val);
            $sets[]  = "$col = '$safeVal'";
        }
    }

    // Imagens: aqui sobrescrevemos pois a condição de entrada foi "Imagem 1 vazia"
    $imgsFinal = array_slice($imagens, 0, 10);
    for ($i = 1; $i <= 10; $i++) {
        $url = isset($imgsFinal[$i - 1]) ? \mysqli_real_escape_string($g['conexaoBanco'], $imgsFinal[$i - 1]) : '';
        $sets[] = "D001E_Imagem_$i = '$url'";
    }

    // Atualiza data para mover para o fim da fila na ordenação ult_att
    $sets[] = "D001E_ult_att = NOW()";

    if (!empty($sets)) {
        $sqlUpdate = "UPDATE D001E SET " . implode(', ', $sets) . " WHERE D001E_Id = $idTable";
        \mysqli_query($g['conexaoBanco'], $sqlUpdate);
    } else {
        // Se nada mudou, apenas atualiza a data para não pegar no próximo loop imediato
        \mysqli_query($g['conexaoBanco'], "UPDATE D001E SET D001E_ult_att = NOW() WHERE D001E_Id = $idTable");
    }

    usleep(200000);
}

return;