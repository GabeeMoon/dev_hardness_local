<?php
namespace hardness;

// =============================================================================
// SINCRONIZADOR D001 -> D001E (FORÇAR ATUALIZAÇÃO DE NULOS)
// =============================================================================

error_reporting(E_ALL & ~E_NOTICE);
ini_set('display_errors', 1);
set_time_limit(0);

global $g, $confUsuario;

// 1) GARANTIA DE CONEXÃO
$precisaConectar = false;
if (!isset($g['conexaoBanco']) || !$g['conexaoBanco']) {
    $precisaConectar = true;
} elseif ($g['conexaoBanco'] instanceof \mysqli && !@\mysqli_ping($g['conexaoBanco'])) {
    $precisaConectar = true;
}

if ($precisaConectar) {
    if (isset($g['conexaoBanco']) && $g['conexaoBanco'] instanceof \mysqli) @\mysqli_close($g['conexaoBanco']);
    $con = @\mysqli_connect($confUsuario['dbHost'], $confUsuario['dbUser'], $confUsuario['dbPass'], $confUsuario['dbDatabase']);
    if ($con) {
        $g['conexaoBanco'] = $con;
    } else {
        die("Erro critico: Sem conexao com banco.");
    }
}
$con = $g['conexaoBanco'];


// 2) ETAPA 1: FORÇAR ATUALIZAÇÃO DA FLAG (CORREÇÃO DOS NULOS)
$sqlUpdateForce = "
    UPDATE D001E e
    INNER JOIN D001 d ON e.D001E_D001_Id = d.D001_Id
    SET e.D001E_Flag_Ecommerce = d.D001_Flag_Ecommerce
    WHERE e.D001E_Flag_Ecommerce IS NULL 
       OR e.D001E_Flag_Ecommerce = '' 
       OR e.D001E_Flag_Ecommerce <> d.D001_Flag_Ecommerce
";
\mysqli_query($con, $sqlUpdateForce);
$totalCorrigidos = \mysqli_affected_rows($con);


// 3) ETAPA 2: INSERÇÃO DE NOVOS PRODUTOS (FLAG 'S')
$sqlInsertNovos = "
    INSERT INTO D001E (
        D001E_D001_Id, 
        D001E_D001_Codigo_Produto, 
        D001E_Flag_Ecommerce, 
        D001E_ult_att
    )
    SELECT 
        d.D001_Id, 
        d.D001_Codigo_Produto, 
        d.D001_Flag_Ecommerce, 
        NOW()
    FROM D001 d
    WHERE (d.D001_Flag_Ecommerce = 'S' OR d.D001_Flag_Ecommerce = 's')
    AND d.D001_Flag_Ecommerce = 'S'
    AND NOT EXISTS (
        SELECT 1 FROM D001E e WHERE e.D001E_D001_Id = d.D001_Id
    )
";
\mysqli_query($con, $sqlInsertNovos);
$totalInseridos = \mysqli_affected_rows($con);


// RETORNO
echo "CORREÇÃO EFETUADA:<br>";
echo "1. Flags Corrigidas (Incluindo Nulos): <b>$totalCorrigidos</b><br>";
echo "2. Novos Produtos Inseridos: <b>$totalInseridos</b><br>";

return;