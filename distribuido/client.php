<?php

declare(strict_types=1);

$workersHosts = ['worker1:8001', 'worker2:8002'];
$caminhoArquivo = __DIR__ . '/texto.txt';
$diretorioTemporario = __DIR__ . '/tmp';

function dividirArquivo(string $caminhoArquivo, string $diretorioTemporario, int $quantidade): void
{
    echo PHP_EOL . "O arquivo será dividido em {$quantidade} parte(s), uma para cada worker:" . PHP_EOL;

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

function enviaParaWorkers(string $diretorio, array $workersHosts): int
{
    $runtimes = [];
    $futures = [];

    foreach ($workersHosts as $key => $worker) {
        $workerNum = $key + 1; // pequena gambiarra pra normalizar o nomw do worker

        $runtimes[$key] = new parallel\Runtime();
        $futures[$key] = $runtimes[$key]->run(
            function ($workerNum, $worker, $diretorio) {
                echo "[Thread {$workerNum}] Iniciando conexão com worker{$workerNum}..." . PHP_EOL;
                $client = stream_socket_client("tcp://{$worker}", $errorNum, $errorMsg, 10);

                if ($client === false) {
                    throw new UnexpectedValueException(
                        "[Thread {$workerNum}] Falha ao conectar ao worker{$workerNum}: {$errorNum} - {$errorMsg}" . PHP_EOL
                    );
                }

                $arquivo = $diretorio . '/pedaco_' . $workerNum . '.txt';
                echo "[Thread {$workerNum}] Lendo arquivo {$arquivo}..." . PHP_EOL;
                $handle = fopen($arquivo, 'rb');
                $texto = fread($handle, 1048576); //1Mb

                echo "[Thread {$workerNum}] Enviando dados ao worker..." . PHP_EOL;
                fwrite($client, base64_encode($texto));
                $resultado = stream_get_contents($client);
                echo "[Thread {$workerNum}] Resultado recebido!" . PHP_EOL;

                fclose($client);
                echo "[Thread {$workerNum}] Conexão com worker{$workerNum} encerrada." . PHP_EOL;

                return $resultado;
            },
            [
                $workerNum,
                $worker,
                $diretorio,
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

function run(string $caminhoArquivo, $workersHosts, string $diretorioTemporario): void
{
    echo PHP_EOL . 'Iniciando client.php' . PHP_EOL;
    echo "Arquivo para contar caracteres: {$caminhoArquivo}" . PHP_EOL;

    dividirArquivo($caminhoArquivo, $diretorioTemporario, count($workersHosts));
    $totalCaracteres = enviaParaWorkers($diretorioTemporario, $workersHosts);
    removeArquivosTemporarios($diretorioTemporario);

    echo PHP_EOL . "O arquivo {$caminhoArquivo} tem {$totalCaracteres} caracteres." . PHP_EOL;
}

run($caminhoArquivo, $workersHosts, $diretorioTemporario);
