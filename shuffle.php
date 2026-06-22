<?php
// shuffle.php - Перемешиватель списков на PHP (CLI + веб)
// CLI: php shuffle.php --list "1,2,3,4,5" --algorithm 1
// Веб: откройте как HTML

// ========== АЛГОРИТМЫ ПЕРЕМЕШИВАНИЯ ==========
function fisherYates($arr, $rng) {
    $result = $arr;
    for ($i = count($result) - 1; $i > 0; $i--) {
        $j = $rng(0, $i);
        $tmp = $result[$i];
        $result[$i] = $result[$j];
        $result[$j] = $tmp;
    }
    return $result;
}

function sortKeyShuffle($arr, $rng) {
    $result = $arr;
    usort($result, function($a, $b) use ($rng) {
        return $rng(0, 1) < 0.5 ? -1 : 1;
    });
    return $result;
}

function builtinShuffle($arr, $rng) {
    return fisherYates($arr, $rng);
}

$algorithms = [
    '1' => ['name' => 'Fisher-Yates (оптимальный)', 'func' => 'fisherYates'],
    '2' => ['name' => 'Сортировка случайным ключом', 'func' => 'sortKeyShuffle'],
    '3' => ['name' => 'Встроенный (стандартный)', 'func' => 'builtinShuffle'],
];

// ========== СТАТИСТИКА ==========
function countInversions($arr) {
    $inv = 0;
    $n = count($arr);
    for ($i = 0; $i < $n; $i++) {
        for ($j = $i + 1; $j < $n; $j++) {
            if ($arr[$i] > $arr[$j]) $inv++;
        }
    }
    return $inv;
}

function entropy($arr) {
    if (empty($arr) || !is_numeric($arr[0])) return 0.0;
    $min = min($arr);
    $max = max($arr);
    if ($max == $min) return 0.0;
    $bins = 10;
    $hist = array_fill(0, $bins, 0);
    foreach ($arr as $x) {
        $idx = (int)(($x - $min) / ($max - $min) * $bins);
        if ($idx == $bins) $idx = $bins - 1;
        $hist[$idx]++;
    }
    $ent = 0.0;
    $n = count($arr);
    foreach ($hist as $count) {
        if ($count > 0) {
            $p = $count / $n;
            $ent -= $p * log($p, 2);
        }
    }
    return $ent;
}

function getStats($arr) {
    return [
        'length' => count($arr),
        'inversions' => countInversions($arr),
        'entropy' => entropy($arr),
    ];
}

// ========== ВВОД/ВЫВОД ==========
function parseList($input) {
    $parts = array_map('trim', explode(',', $input));
    $result = [];
    foreach ($parts as $p) {
        if (is_numeric($p)) {
            if (strpos($p, '.') !== false) $result[] = (float)$p;
            else $result[] = (int)$p;
        } else {
            $result[] = $p;
        }
    }
    return $result;
}

function formatList($arr) {
    return '[' . implode(', ', array_map(function($x) { return is_string($x) ? '"' . $x . '"' : $x; }, $arr)) . ']';
}

function exportJSON($arr, $filename) {
    file_put_contents($filename, json_encode($arr, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

function exportCSV($arr, $filename) {
    $f = fopen($filename, 'w');
    fputcsv($f, ['value']);
    foreach ($arr as $val) fputcsv($f, [$val]);
    fclose($f);
}

function importList($filename) {
    $content = file_get_contents($filename);
    if (strpos($filename, '.json') !== false) {
        return json_decode($content, true);
    } else {
        $lines = array_map('trim', explode("\n", $content));
        $result = [];
        for ($i = 1; $i < count($lines); $i++) {
            if ($lines[$i] !== '') $result[] = $lines[$i];
        }
        return $result;
    }
}

function generateRandomList($size, $minVal = 0, $maxVal = 100) {
    $result = [];
    for ($i = 0; $i < $size; $i++) {
        $result[] = rand($minVal, $maxVal);
    }
    return $result;
}

function getInput($prompt) {
    echo $prompt;
    return trim(fgets(STDIN));
}

if (php_sapi_name() === 'cli') {
    $options = getopt("", ["list:", "file:", "random:", "algorithm:", "seed:", "export:", "stats"]);
    if (isset($options['list']) || isset($options['file']) || isset($options['random'])) {
        $data = [];
        if (isset($options['list'])) {
            $data = parseList($options['list']);
        } elseif (isset($options['file'])) {
            $data = importList($options['file']);
        } elseif (isset($options['random'])) {
            $size = (int)$options['random'];
            $data = generateRandomList($size);
        }
        if (empty($data)) { echo "Список пуст.\n"; exit; }
        $algoKey = isset($options['algorithm']) ? $options['algorithm'] : '1';
        if (!isset($algorithms[$algoKey])) $algoKey = '1';
        $func = $algorithms[$algoKey]['func'];
        $rng = function($min, $max) {
            static $seed = null;
            if ($seed === null && isset($options['seed'])) {
                $seed = (int)$options['seed'];
                srand($seed);
            }
            return rand($min, $max);
        };
        if (isset($options['seed'])) {
            $seed = (int)$options['seed'];
            srand($seed);
            $rng = function($min, $max) use ($seed) {
                return rand($min, $max);
            };
        } else {
            $rng = function($min, $max) {
                return rand($min, $max);
            };
        }
        $shuffled = $func($data, $rng);
        echo "Исходный список: " . formatList($data) . "\n";
        echo "Перемешанный список: " . formatList($shuffled) . "\n";
        if (isset($options['stats'])) {
            $stats = getStats($shuffled);
            echo "\n📊 Статистика:\n";
            echo "  Длина: {$stats['length']}\n";
            echo "  Инверсий: {$stats['inversions']}\n";
            echo "  Энтропия: " . number_format($stats['entropy'], 4) . "\n";
        }
        if (isset($options['export'])) {
            if (strpos($options['export'], '.json') !== false) exportJSON($shuffled, $options['export']);
            else exportCSV($shuffled, $options['export']);
            echo "Экспортировано в {$options['export']}\n";
        }
    } else {
        // Интерактивный режим
        echo "🎲 ПЕРЕМЕШИВАТЕЛЬ СПИСКОВ\n";
        echo "Введите список (через запятую):\n";
        $input = getInput("> ");
        if (empty($input)) {
            echo "Список пуст. Сгенерируем случайный.\n";
            $size = (int)getInput("Размер случайного списка (по умолчанию 10): ") ?: 10;
            $data = generateRandomList($size);
            echo "Сгенерированный список: " . formatList($data) . "\n";
        } else {
            $data = parseList($input);
            echo "Исходный список: " . formatList($data) . "\n";
        }

        echo "\nВыберите алгоритм:\n";
        foreach ($algorithms as $key => $alg) {
            echo "$key. {$alg['name']}\n";
        }
        $algoChoice = getInput("Ваш выбор (по умолчанию 1): ") ?: '1';
        if (!isset($algorithms[$algoChoice])) $algoChoice = '1';
        $func = $algorithms[$algoChoice]['func'];

        $seedInput = getInput("Введите seed (или оставьте пустым для случайного): ");
        if ($seedInput !== '') {
            $seed = (int)$seedInput;
            srand($seed);
            $rng = function($min, $max) { return rand($min, $max); };
        } else {
            $rng = function($min, $max) { return rand($min, $max); };
        }

        $shuffled = $func($data, $rng);
        echo "\nПеремешанный список: " . formatList($shuffled) . "\n";

        $stats = getStats($shuffled);
        echo "\n📊 Статистика:\n";
        echo "  Длина: {$stats['length']}\n";
        echo "  Инверсий: {$stats['inversions']}\n";
        echo "  Энтропия: " . number_format($stats['entropy'], 4) . "\n";

        $export = getInput("\nЭкспортировать результат? (y/n): ");
        if (strtolower($export) == 'y') {
            $fmt = getInput("Формат (json/csv): ");
            $filename = getInput("Имя файла: ");
            if (empty($filename)) $filename = "shuffled.$fmt";
            if ($fmt == 'json') exportJSON($shuffled, $filename);
            else exportCSV($shuffled, $filename);
            echo "Экспортировано в $filename\n";
        }
    }
    exit;
}

// ========== ВЕБ-ИНТЕРФЕЙС ==========
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🎲 Перемешиватель списков (PHP)</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #f4f7fb; margin: 20px; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 20px; border-radius: 16px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { text-align: center; }
        .form-group { margin-bottom: 15px; }
        label { display: inline-block; width: 120px; }
        input, select, button { padding: 6px; border-radius: 4px; border: 1px solid #ccc; }
        button { background: #3498db; color: white; border: none; cursor: pointer; padding: 6px 20px; }
        button:hover { background: #2980b9; }
        .result { background: #ecf0f1; padding: 15px; border-radius: 8px; margin-top: 20px; }
        .stats { font-family: monospace; }
    </style>
</head>
<body>
<div class="container">
    <h1>🎲 Перемешиватель списков (PHP)</h1>
    <form method="GET">
        <div class="form-group">
            <label>Список чисел:</label>
            <input type="text" name="list" placeholder="1,2,3,4,5" value="<?= isset($_GET['list']) ? htmlspecialchars($_GET['list']) : '' ?>">
        </div>
        <div class="form-group">
            <label>Алгоритм:</label>
            <select name="algorithm">
                <?php foreach ($algorithms as $key => $alg): ?>
                    <option value="<?= $key ?>" <?= isset($_GET['algorithm']) && $_GET['algorithm'] == $key ? 'selected' : '' ?>><?= $alg['name'] ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>Seed (опционально):</label>
            <input type="number" name="seed" value="<?= isset($_GET['seed']) ? htmlspecialchars($_GET['seed']) : '' ?>">
        </div>
        <div class="form-group">
            <label>Показать статистику:</label>
            <input type="checkbox" name="stats" <?= isset($_GET['stats']) ? 'checked' : '' ?>>
        </div>
        <button type="submit">Перемешать</button>
        <a href="?export_csv=1&<?= http_build_query($_GET) ?>">📥 CSV</a>
        <a href="?export_json=1&<?= http_build_query($_GET) ?>">📥 JSON</a>
    </form>

    <?php if (isset($_GET['export_csv']) || isset($_GET['export_json'])): 
        $list = $_GET['list'] ?? '';
        if ($list) {
            $data = parseList($list);
            if ($data) {
                $algoKey = $_GET['algorithm'] ?? '1';
                if (!isset($algorithms[$algoKey])) $algoKey = '1';
                $func = $algorithms[$algoKey]['func'];
                $seed = isset($_GET['seed']) ? (int)$_GET['seed'] : null;
                if ($seed !== null) srand($seed);
                $rng = function($min, $max) { return rand($min, $max); };
                $shuffled = $func($data, $rng);
                if (isset($_GET['export_csv'])) {
                    header('Content-Type: text/csv');
                    header('Content-Disposition: attachment; filename="shuffled.csv"');
                    exportCSV($shuffled, 'php://output');
                    exit;
                } else {
                    header('Content-Type: application/json');
                    echo json_encode($shuffled, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                    exit;
                }
            }
        }
    endif; ?>

    <?php if (isset($_GET['list']) && !empty($_GET['list'])): 
        $data = parseList($_GET['list']);
        if ($data):
            $algoKey = $_GET['algorithm'] ?? '1';
            if (!isset($algorithms[$algoKey])) $algoKey = '1';
            $func = $algorithms[$algoKey]['func'];
            $seed = isset($_GET['seed']) ? (int)$_GET['seed'] : null;
            if ($seed !== null) srand($seed);
            $rng = function($min, $max) { return rand($min, $max); };
            $shuffled = $func($data, $rng);
    ?>
        <div class="result">
            <p><strong>Исходный список:</strong> <?= formatList($data) ?></p>
            <p><strong>Перемешанный список:</strong> <?= formatList($shuffled) ?></p>
            <?php if (isset($_GET['stats'])): 
                $stats = getStats($shuffled);
            ?>
                <div class="stats">
                    <h4>📊 Статистика:</h4>
                    <p>Длина: <?= $stats['length'] ?></p>
                    <p>Инверсий: <?= $stats['inversions'] ?></p>
                    <p>Энтропия: <?= number_format($stats['entropy'], 4) ?></p>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; endif; ?>
</div>
</body>
</html>
