#!/usr/bin/env node
// shuffle.js - Перемешиватель списков на JavaScript (Node.js CLI + веб)
/**
 * Поддерживает: 3 алгоритма, seed, экспорт/импорт, статистику.
 */
const fs = require('fs');
const readline = require('readline');
const rl = readline.createInterface({
    input: process.stdin,
    output: process.stdout
});

// ========== АЛГОРИТМЫ ПЕРЕМЕШИВАНИЯ ==========
function fisherYates(arr, rng) {
    const result = [...arr];
    for (let i = result.length - 1; i > 0; i--) {
        const j = Math.floor(rng() * (i + 1));
        [result[i], result[j]] = [result[j], result[i]];
    }
    return result;
}

function sortKeyShuffle(arr, rng) {
    return [...arr].sort(() => rng() - 0.5);
}

function builtinShuffle(arr, rng) {
    // Используем Fisher-Yates с переданным генератором
    return fisherYates(arr, rng);
}

const ALGORITHMS = {
    '1': { name: 'Fisher-Yates (оптимальный)', func: fisherYates },
    '2': { name: 'Сортировка случайным ключом', func: sortKeyShuffle },
    '3': { name: 'Встроенный (стандартный)', func: builtinShuffle },
};

// ========== СТАТИСТИКА ==========
function countInversions(arr) {
    let inv = 0;
    for (let i = 0; i < arr.length; i++) {
        for (let j = i + 1; j < arr.length; j++) {
            if (arr[i] > arr[j]) inv++;
        }
    }
    return inv;
}

function entropy(arr) {
    if (!arr.length || typeof arr[0] !== 'number') return 0;
    const min = Math.min(...arr);
    const max = Math.max(...arr);
    if (min === max) return 0;
    const bins = 10;
    const hist = Array(bins).fill(0);
    for (const x of arr) {
        let idx = Math.floor((x - min) / (max - min) * bins);
        if (idx === bins) idx = bins - 1;
        hist[idx]++;
    }
    let ent = 0;
    const n = arr.length;
    for (const count of hist) {
        if (count > 0) {
            const p = count / n;
            ent -= p * Math.log2(p);
        }
    }
    return ent;
}

function getStats(arr) {
    return {
        length: arr.length,
        inversions: countInversions(arr),
        entropy: entropy(arr),
    };
}

// ========== ВВОД/ВЫВОД ==========
function parseList(input) {
    return input.split(',').map(item => {
        item = item.trim();
        if (!isNaN(item)) return Number(item);
        return item;
    });
}

function formatList(arr) {
    return '[' + arr.map(x => typeof x === 'string' ? `"${x}"` : x).join(', ') + ']';
}

function exportJSON(arr, filename) {
    fs.writeFileSync(filename, JSON.stringify(arr, null, 2), 'utf8');
}

function exportCSV(arr, filename) {
    const lines = arr.map(x => `${x}`).join('\n');
    fs.writeFileSync(filename, `value\n${lines}`, 'utf8');
}

function importList(filename) {
    const content = fs.readFileSync(filename, 'utf8');
    if (filename.endsWith('.json')) {
        return JSON.parse(content);
    } else {
        // CSV
        const lines = content.split('\n').filter(l => l.trim());
        if (lines.length === 1) {
            // Possibly comma-separated values in one line
            return parseList(lines[0]);
        } else {
            return lines.slice(1).map(l => {
                const val = l.trim();
                if (!isNaN(val)) return Number(val);
                return val;
            });
        }
    }
}

function generateRandomList(size, minVal = 0, maxVal = 100) {
    return Array.from({ length: size }, () => Math.floor(Math.random() * (maxVal - minVal + 1)) + minVal);
}

// ========== ОСНОВНАЯ ЛОГИКА ==========
function prompt(query) {
    return new Promise(resolve => rl.question(query, resolve));
}

async function interactive() {
    console.log('🎲 ПЕРЕМЕШИВАТЕЛЬ СПИСКОВ');
    console.log('Введите список (через запятую):');
    let input = await prompt('> ');
    let data;
    if (!input.trim()) {
        console.log('Список пуст. Сгенерируем случайный.');
        const size = parseInt(await prompt('Размер случайного списка (по умолчанию 10): ') || '10');
        data = generateRandomList(size);
        console.log(`Сгенерированный список: ${formatList(data)}`);
    } else {
        data = parseList(input);
        console.log(`Исходный список: ${formatList(data)}`);
    }

    console.log('\nВыберите алгоритм:');
    for (const key in ALGORITHMS) {
        console.log(`${key}. ${ALGORITHMS[key].name}`);
    }
    let algoChoice = await prompt('Ваш выбор (по умолчанию 1): ') || '1';
    if (!(algoChoice in ALGORITHMS)) algoChoice = '1';
    const algorithm = ALGORITHMS[algoChoice].func;

    const seedInput = await prompt('Введите seed (или оставьте пустым для случайного): ');
    let rng;
    if (seedInput) {
        const seed = parseInt(seedInput);
        rng = () => { // простой генератор на основе seed (mulberry32)
            let s = seed;
            return function() {
                s |= 0; s = s + 0x6D2B79F5 | 0;
                let t = Math.imul(s ^ s >>> 15, 1 | s);
                t = t + Math.imul(t ^ t >>> 7, 61 | t) ^ t;
                return ((t ^ t >>> 14) >>> 0) / 4294967296;
            };
        }();
    } else {
        rng = Math.random;
    }

    const shuffled = algorithm(data, rng);
    console.log(`\nПеремешанный список: ${formatList(shuffled)}`);

    const stats = getStats(shuffled);
    console.log(`\n📊 Статистика:`);
    console.log(`  Длина: ${stats.length}`);
    console.log(`  Инверсий: ${stats.inversions}`);
    console.log(`  Энтропия: ${stats.entropy.toFixed(4)}`);

    const exportChoice = await prompt('\nЭкспортировать результат? (y/n): ');
    if (exportChoice.toLowerCase() === 'y') {
        const fmt = await prompt('Формат (json/csv): ');
        let filename = await prompt('Имя файла: ');
        if (!filename) filename = `shuffled.${fmt}`;
        if (fmt === 'json') exportJSON(shuffled, filename);
        else exportCSV(shuffled, filename);
        console.log(`Экспортировано в ${filename}`);
    }
    rl.close();
}

function cli() {
    const args = process.argv.slice(2);
    const parsed = {};
    for (let i = 0; i < args.length; i++) {
        if (args[i] === '--list') parsed.list = args[++i];
        else if (args[i] === '--file') parsed.file = args[++i];
        else if (args[i] === '--random') parsed.random = parseInt(args[++i]);
        else if (args[i] === '--algorithm') parsed.algorithm = args[++i];
        else if (args[i] === '--seed') parsed.seed = parseInt(args[++i]);
        else if (args[i] === '--export') parsed.export = args[++i];
        else if (args[i] === '--stats') parsed.stats = true;
    }
    let data;
    if (parsed.list) {
        data = parseList(parsed.list);
    } else if (parsed.file) {
        data = importList(parsed.file);
    } else if (parsed.random) {
        data = generateRandomList(parsed.random);
    } else {
        console.log('Укажите --list, --file или --random');
        process.exit(1);
    }
    let rng;
    if (parsed.seed !== undefined) {
        let s = parsed.seed;
        rng = () => {
            s |= 0; s = s + 0x6D2B79F5 | 0;
            let t = Math.imul(s ^ s >>> 15, 1 | s);
            t = t + Math.imul(t ^ t >>> 7, 61 | t) ^ t;
            return ((t ^ t >>> 14) >>> 0) / 4294967296;
        };
    } else {
        rng = Math.random;
    }
    const algoKey = parsed.algorithm || '1';
    const algorithm = ALGORITHMS[algoKey]?.func || ALGORITHMS['1'].func;
    const shuffled = algorithm(data, rng);
    console.log(`Исходный список: ${formatList(data)}`);
    console.log(`Перемешанный список: ${formatList(shuffled)}`);
    if (parsed.stats) {
        const stats = getStats(shuffled);
        console.log(`\n📊 Статистика:`);
        console.log(`  Длина: ${stats.length}`);
        console.log(`  Инверсий: ${stats.inversions}`);
        console.log(`  Энтропия: ${stats.entropy.toFixed(4)}`);
    }
    if (parsed.export) {
        if (parsed.export.endsWith('.json')) exportJSON(shuffled, parsed.export);
        else exportCSV(shuffled, parsed.export);
        console.log(`Экспортировано в ${parsed.export}`);
    }
}

if (require.main === module) {
    if (process.argv.length > 2) {
        cli();
    } else {
        interactive().catch(console.error);
    }
}

// ========== Браузерная версия ==========
if (typeof window !== 'undefined') {
    window.ALGORITHMS = ALGORITHMS;
    window.fisherYates = fisherYates;
    window.sortKeyShuffle = sortKeyShuffle;
    window.builtinShuffle = builtinShuffle;
    window.getStats = getStats;
    window.generateRandomList = generateRandomList;
    window.parseList = parseList;
    window.formatList = formatList;
}
