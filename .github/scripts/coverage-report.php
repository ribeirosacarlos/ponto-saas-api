<?php

/**
 * Gera um resumo (Markdown) de cobertura de testes a partir de um relatório
 * Clover XML, destacando os arquivos de app/ alterados no PR que têm pouca
 * ou nenhuma cobertura.
 *
 * Uso:
 *   php coverage-report.php <coverage.xml> "<arquivos-alterados-separados-por-virgula>"
 */

[, $cloverPath, $changedFilesArg] = $argv + [null, null, ''];

if (!$cloverPath || !is_file($cloverPath)) {
    echo "## 🧪 Relatório de Cobertura de Testes\n\n";
    echo "_Não foi possível gerar o relatório: arquivo `{$cloverPath}` não encontrado._\n";
    exit(0);
}

$xml = simplexml_load_file($cloverPath);
$projectMetrics = $xml->project->metrics;

$totalStatements = (int) ($projectMetrics['statements'] ?? 0);
$coveredStatements = (int) ($projectMetrics['coveredstatements'] ?? 0);
$overallPct = $totalStatements > 0
    ? round($coveredStatements / $totalStatements * 100, 2)
    : 100.0;

$repoRoot = rtrim((string) realpath(__DIR__.'/../..'), '/').'/';

$fileCoverage = [];

$collect = function ($file) use (&$fileCoverage, $repoRoot): void {
    $path = (string) $file['name'];
    $relative = str_starts_with($path, $repoRoot) ? substr($path, strlen($repoRoot)) : $path;

    $metrics = $file->metrics;
    $statements = (int) ($metrics['statements'] ?? 0);
    $covered = (int) ($metrics['coveredstatements'] ?? 0);
    $pct = $statements > 0 ? round($covered / $statements * 100, 2) : 100.0;

    $fileCoverage[$relative] = compact('covered', 'statements', 'pct');
};

foreach ($xml->project->package as $package) {
    foreach ($package->file as $file) {
        $collect($file);
    }
}

foreach ($xml->project->file as $file) {
    $collect($file);
}

$changedFiles = array_values(array_filter(array_map('trim', explode(',', $changedFilesArg))));

$lines = [];
$lines[] = '## 🧪 Relatório de Cobertura de Testes';
$lines[] = '';
$lines[] = "**Cobertura geral do projeto:** {$overallPct}% ({$coveredStatements}/{$totalStatements} statements)";
$lines[] = '';

if (empty($changedFiles)) {
    $lines[] = '_Nenhum arquivo em `app/` foi alterado neste PR._';
} else {
    $lines[] = '### Arquivos de `app/` alterados neste PR';
    $lines[] = '';
    $lines[] = '| Arquivo | Cobertura | Status |';
    $lines[] = '|---|---|---|';

    $needsAttention = [];

    foreach ($changedFiles as $file) {
        if (!isset($fileCoverage[$file])) {
            $lines[] = "| `{$file}` | — | ⚠️ sem dados de cobertura |";
            $needsAttention[] = $file;

            continue;
        }

        $info = $fileCoverage[$file];

        if ($info['statements'] === 0) {
            $status = 'ℹ️ sem código executável';
        } elseif ($info['pct'] < 50) {
            $status = '⚠️ cobertura baixa';
            $needsAttention[] = $file;
        } elseif ($info['pct'] < 80) {
            $status = '🟡 pode melhorar';
        } else {
            $status = '✅ ok';
        }

        $lines[] = "| `{$file}` | {$info['pct']}% ({$info['covered']}/{$info['statements']}) | {$status} |";
    }

    $lines[] = '';

    if (!empty($needsAttention)) {
        $lines[] = '### ⚠️ Possível falta de testes';
        $lines[] = '';
        $lines[] = 'Os arquivos abaixo foram alterados neste PR e têm pouca ou nenhuma cobertura de testes. Considere adicionar ou complementar os testes:';
        $lines[] = '';
        foreach ($needsAttention as $file) {
            $lines[] = "- `{$file}`";
        }
    } else {
        $lines[] = '✅ Todos os arquivos de `app/` alterados neste PR têm cobertura de testes razoável.';
    }
}

echo implode("\n", $lines)."\n";
