<?php
/*
 POPULADOR D001E - MASTER (OTIMIZADO PARA RATE LIMIT)
 */
namespace hardness;

error_reporting(E_ALL & ~E_NOTICE);
ini_set('display_errors', 1);
set_time_limit(0);

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
    echo "ERRO API: " . $e->getMessage() . "\n";
    return;
}

// =============================================================================
// 3) CONEXÃO ÚNICA COM BANCO
// =============================================================================
if (!isset($g['conexaoBanco']) || !$g['conexaoBanco'] || !($g['conexaoBanco'] instanceof \mysqli)) {
    $con = @\mysqli_connect($confUsuario['dbHost'], $confUsuario['dbUser'], $confUsuario['dbPass'], $confUsuario['dbDatabase']);
    if (!$con) {
        echo "ERRO: Sem conexão com banco.\n";
        return;
    }
    $g['conexaoBanco'] = $con;
}
$con = $g['conexaoBanco'];

// =============================================================================
// 4) CONFIGURAÇÃO DO RATE LIMIT (1.5s = ~40/minuto)
// =============================================================================
$delayEntreRequisicoes = 1500000; // 1.5 segundos em microsegundos
$maxRequests = 40; // Limite por execução
$contador = 0;

echo "INICIANDO POPULADOR D001E...\n";

// =============================================================================
// 5) LOOP PRINCIPAL
// =============================================================================
while ($contador < $maxRequests) {
    // Busca próximo produto sem ID AnyMarket
    $sqlBusca = "SELECT * FROM D001E 
                 WHERE (D001E_Id_Any IS NULL OR D001E_Id_Any = '')
                 ORDER BY D001E_ult_att ASC 
                 LIMIT 1";
    
    $rs = \mysqli_query($con, $sqlBusca);
    if (!$rs || \mysqli_num_rows($rs) == 0) {
        echo "Nenhum produto pendente encontrado.\n";
        break;
    }
    
    $atual   = \mysqli_fetch_assoc($rs);
    $idTable = (int)$atual['D001E_Id'];
    $idProd  = (int)$atual['D001E_D001_Id'];
    $sku     = trim($atual['D001E_D001_Codigo_Produto']);
    
    if (empty($sku)) {
        // Atualiza data e pula
        \mysqli_query($con, "UPDATE D001E SET D001E_ult_att = NOW() WHERE D001E_Id = $idTable");
        echo "[$contador] SKU vazio, pulando...\n";
        continue;
    }
    
    echo "[$contador] Processando SKU: $sku... ";
    
    // Variáveis para armazenar dados da API
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
    
    // =========================================================================
    // 6) REQUISIÇÃO À API ANYMARKET
    // =========================================================================
    try {
        $endpoint = "/products?sku=" . urlencode($sku);
        $resp = $apiManager->request($endpoint, 'GET', null, true, ['return_on_failure' => true]);
        $contador++;
        
        // Verificar rate limit
        if (isset($resp['code']) && $resp['code'] == 429) {
            echo "RATE LIMIT EXCEDIDO! Aguardando 60 segundos...\n";
            sleep(60);
            $contador--; // Não conta esta tentativa
            continue;
        }
        
        $body = isset($resp['body']) ? (is_array($resp['body']) ? $resp['body'] : json_decode($resp['body'], true)) : [];
        
        // Processar resposta da API
        if (isset($resp['code']) && $resp['code'] == 200 && !empty($body['content'][0])) {
            $d = $body['content'][0];
            
            // ID do produto
            if (isset($d['id']) && is_numeric($d['id'])) {
                $idAnySku = (int) $d['id'];
            }
            
            // Dados básicos
            $titulo    = isset($d['title']) ? $d['title'] : '';
            $descricao = isset($d['description']) ? $d['description'] : '';
            
            // Marca
            if (!empty($d['brand']['name'])) {
                $marca = $d['brand']['name'];
            } elseif (!empty($d['brand']['reducedName'])) {
                $marca = $d['brand']['reducedName'];
            } elseif (!empty($d['brand']['partnerId'])) {
                $marca = $d['brand']['partnerId'];
            }
            
            // Especificações
            $garantia    = isset($d['warrantyText']) ? $d['warrantyText'] : '';
            $peso        = isset($d['weight']) ? $d['weight'] : '';
            $altura      = isset($d['height']) ? $d['height'] : '';
            $largura     = isset($d['width']) ? $d['width'] : '';
            $comprimento = isset($d['length']) ? $d['length'] : '';
            
            // Imagens
            if (!empty($d['images']) && is_array($d['images'])) {
                foreach ($d['images'] as $img) {
                    if (!empty($img['url'])) {
                        $imagens[] = $img['url'];
                    }
                }
            }
            
            // SKU específico e EAN
            if (!empty($d['skus']) && is_array($d['skus'])) {
                foreach ($d['skus'] as $s) {
                    if (isset($s['partnerId']) && $s['partnerId'] == $sku) {
                        $tituloSku = isset($s['title']) ? $s['title'] : '';
                    }
                    if (!empty($s['ean']) && empty($ean)) {
                        $ean = $s['ean'];
                    }
                }
                // Se não achou pelo partnerId, pega o primeiro
                if (empty($tituloSku) && !empty($d['skus'][0]['title'])) {
                    $tituloSku = $d['skus'][0]['title'];
                }
            }
            
            echo "OK (ID Any: $idAnySku)\n";
        } else {
            echo "Não encontrado na API\n";
        }
    } catch (\Exception $e) {
        echo "ERRO na requisição: " . $e->getMessage() . "\n";
    }
    
    // =========================================================================
    // 7) FALLBACK PARA DADOS LOCAIS
    // =========================================================================
    
    // Fallback para imagens (T172)
    if (empty($imagens)) {
        $r172 = \mysqli_query($con, "SELECT T172_Id, T172_Nome_Arquivo FROM T172 WHERE T172_D001_Id = '$idProd' ORDER BY T172_Nome_Arquivo DESC");
        if ($r172) {
            while ($m = \mysqli_fetch_assoc($r172)) {
                $ext = pathinfo($m['T172_Nome_Arquivo'], PATHINFO_EXTENSION);
                $dbName = isset($confUsuario['dbDatabase']) ? $confUsuario['dbDatabase'] : 'e229';
                $imagens[] = "/hardness3/dados_usuarios/{$dbName}/produtos/{$idProd}/fotos/{$m['T172_Id']}.$ext";
            }
        }
    }
    
    // Fallback para imagens (T144)
    if (empty($imagens)) {
        $r144 = \mysqli_query($con, "SELECT T144_Url FROM T144 WHERE T144_D001_Id = '$idProd'");
        if ($r144) {
            while ($m = \mysqli_fetch_assoc($r144)) {
                if (!empty($m['T144_Url'])) {
                    $imagens[] = $m['T144_Url'];
                }
            }
        }
    }
    
    // Fallback para textos (D001)
    if (empty($titulo) || empty($descricao)) {
        $rD = \mysqli_query($con, "SELECT D001_Descricao FROM D001 WHERE D001_Id = '$idProd'");
        if ($rD && $r = \mysqli_fetch_assoc($rD)) {
            if (empty($titulo))    $titulo = $r['D001_Descricao'];
            if (empty($descricao)) $descricao = $r['D001_Descricao'];
        }
    }
    
    // =========================================================================
    // 8) ATUALIZAÇÃO NO BANCO
    // =========================================================================
    $sets = [];
    
    // ID AnyMarket
    if ($idAnySku > 0) {
        $sets[] = "D001E_Id_Any = $idAnySku";
    }
    
    // Título do SKU
    if (!empty($tituloSku)) {
        $ts = \mysqli_real_escape_string($con, $tituloSku);
        $sets[] = "D001E_Sku_Titulo = '$ts'";
    }
    
    // Demais campos
    $campos = [
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
    
    foreach ($campos as $col => $val) {
        if ($val !== $atual[$col]) {
            $safeVal = \mysqli_real_escape_string($con, $val);
            $sets[] = "$col = '$safeVal'";
        }
    }
    
    // Imagens (máximo 10)
    $imgsFinal = array_slice($imagens, 0, 10);
    for ($i = 1; $i <= 10; $i++) {
        $url = isset($imgsFinal[$i - 1]) ? \mysqli_real_escape_string($con, $imgsFinal[$i - 1]) : '';
        $sets[] = "D001E_Imagem_$i = '$url'";
    }
    
    // Sempre atualiza data
    $sets[] = "D001E_ult_att = NOW()";
    
    if (!empty($sets)) {
        $sqlUpdate = "UPDATE D001E SET " . implode(', ', $sets) . " WHERE D001E_Id = $idTable";
        \mysqli_query($con, $sqlUpdate);
    } else {
        // Se não houve alterações, apenas atualiza data
        \mysqli_query($con, "UPDATE D001E SET D001E_ult_att = NOW() WHERE D001E_Id = $idTable");
    }
    
    // =========================================================================
    // 9) DELAY PARA RESPEITAR RATE LIMIT
    // =========================================================================
    if ($contador < $maxRequests) {
        usleep($delayEntreRequisicoes);
    }
}

echo "PROCESSO CONCLUÍDO. Requisições realizadas: $contador\n";
return;