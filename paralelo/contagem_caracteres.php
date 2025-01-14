<?php

declare(strict_types=1);

$workers = (int)$argv[1] ?: 4;
$caminhoArquivo = __DIR__ . "/texto.txt";
$diretorioTemporario = __DIR__ . "/tmp";

function dividirArquivo(string $caminhoArquivo, string $diretorioTemporario, int $quantidade): void
{
    echo PHP_EOL . "O arquivo será dividido em {$quantidade} parte(s), uma para cada thread:" . PHP_EOL;

    $tamanhoArquivo = filesize($caminhoArquivo);
    $tamanhoPedaco = $tamanhoArquivo / $quantidade;
    $handle = fopen($caminhoArquivo, 'rb');

    $i = 1;
    while (!feof($handle) && $i <= $quantidade) {
        $buffer = fread($handle, (int)round($tamanhoPedaco));
        $nomePedacoArquivo = $diretorioTemporario . '/pedaco_' . $i . '.txt';
        $fw = fopen($nomePedacoArquivo, 'wb');
        fwrite($fw, $buffer);
        fclose($fw);
        $i++;

        echo "Arquivo gerado: {$nomePedacoArquivo}" . PHP_EOL;
    }

    fclose($handle);
}

function contagemCaracteres(string $diretorio, int $quantidadeThreads): int
{
    $runtimes = [];
    $futures = [];

    for ($i = 1; $i <= $quantidadeThreads; $i++) {
        $runtimes[$i] = new parallel\Runtime();
        $futures[$i] = $runtimes[$i]->run(
            function ($diretorio, $i) {
                echo PHP_EOL . "Iniciando thread {$i}" . PHP_EOL;

                $handle = fopen($diretorio . '/pedaco_' . $i . '.txt', 'rb');
                $texto = fread($handle, 1048576); //1Mb
                $count = strlen($texto);

                echo PHP_EOL . "Fim thread {$i} após contar {$count}" . PHP_EOL;

                return $count;
            },
            [
                $diretorio,
                $i,
            ]
        );
    }

    return array_reduce(
        $futures,
        static fn(int $total, parallel\Future $future) => $total + $future->value(),
        0
    );
}

function removeArquivosTemporarios(string $diretorioTemporario)
{
    array_map('unlink', glob($diretorioTemporario . "/*"));
}

function run(string $caminhoArquivo, int $workers, string $diretorioTemporario): void
{
    echo "Arquivo para contar caracteres: {$caminhoArquivo}" . PHP_EOL;
    echo "Quantidade de threads a serem iniciadas: {$workers}" . PHP_EOL;

    dividirArquivo($caminhoArquivo, $diretorioTemporario, $workers);
    $totalCaracteres = contagemCaracteres($diretorioTemporario, $workers);
    removeArquivosTemporarios($diretorioTemporario);

    echo PHP_EOL . "O arquivo{$caminhoArquivo} tem {$totalCaracteres} caracteres." . PHP_EOL;
}

run($caminhoArquivo, $workers, $diretorioTemporario);
