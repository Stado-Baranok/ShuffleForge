// ShuffleList.java - Перемешиватель списков на Java (CLI)
import java.io.*;
import java.nio.file.*;
import java.util.*;
import java.util.function.*;
import java.util.stream.*;

public class ShuffleList {
    private static final Scanner scanner = new Scanner(System.in);
    private static final Random random = new Random();

    // ========== АЛГОРИТМЫ ПЕРЕМЕШИВАНИЯ ==========
    interface ShuffleFunc<T> {
        List<T> shuffle(List<T> list, Random rng);
    }

    static <T> List<T> fisherYates(List<T> list, Random rng) {
        List<T> result = new ArrayList<>(list);
        for (int i = result.size() - 1; i > 0; i--) {
            int j = rng.nextInt(i + 1);
            Collections.swap(result, i, j);
        }
        return result;
    }

    static <T> List<T> sortKeyShuffle(List<T> list, Random rng) {
        List<T> result = new ArrayList<>(list);
        result.sort((a, b) -> rng.nextBoolean() ? -1 : 1);
        return result;
    }

    static <T> List<T> builtinShuffle(List<T> list, Random rng) {
        List<T> result = new ArrayList<>(list);
        Collections.shuffle(result, rng);
        return result;
    }

    static class Algorithm<T> {
        String name;
        ShuffleFunc<T> func;
        Algorithm(String name, ShuffleFunc<T> func) {
            this.name = name;
            this.func = func;
        }
    }

    private static final Map<String, Algorithm<Object>> ALGORITHMS = new LinkedHashMap<>();
    static {
        ALGORITHMS.put("1", new Algorithm<>("Fisher-Yates (оптимальный)", ShuffleList::fisherYates));
        ALGORITHMS.put("2", new Algorithm<>("Сортировка случайным ключом", ShuffleList::sortKeyShuffle));
        ALGORITHMS.put("3", new Algorithm<>("Встроенный (стандартный)", ShuffleList::builtinShuffle));
    }

    // ========== СТАТИСТИКА ==========
    static <T extends Comparable<T>> int countInversions(List<T> list) {
        int inv = 0;
        for (int i = 0; i < list.size(); i++) {
            for (int j = i + 1; j < list.size(); j++) {
                if (list.get(i).compareTo(list.get(j)) > 0) inv++;
            }
        }
        return inv;
    }

    static double entropy(List<Double> list) {
        if (list.isEmpty()) return 0.0;
        double min = list.stream().min(Double::compare).orElse(0.0);
        double max = list.stream().max(Double::compare).orElse(0.0);
        if (max == min) return 0.0;
        int bins = 10;
        int[] hist = new int[bins];
        for (double x : list) {
            int idx = (int)((x - min) / (max - min) * bins);
            if (idx == bins) idx = bins - 1;
            hist[idx]++;
        }
        double ent = 0.0;
        double n = list.size();
        for (int count : hist) {
            if (count > 0) {
                double p = count / n;
                ent -= p * (Math.log(p) / Math.log(2));
            }
        }
        return ent;
    }

    static <T> Map<String, Object> getStats(List<T> list) {
        Map<String, Object> stats = new HashMap<>();
        stats.put("length", list.size());
        if (!list.isEmpty() && list.get(0) instanceof Number) {
            List<Double> nums = list.stream().map(x -> ((Number)x).doubleValue()).collect(Collectors.toList());
            stats.put("inversions", countInversions((List<Comparable>) list));
            stats.put("entropy", entropy(nums));
        } else {
            stats.put("inversions", 0);
            stats.put("entropy", 0.0);
        }
        return stats;
    }

    // ========== ВВОД/ВЫВОД ==========
    static List<Object> parseList(String input) {
        String[] parts = input.split(",");
        List<Object> result = new ArrayList<>();
        for (String p : parts) {
            p = p.trim();
            if (p.isEmpty()) continue;
            try {
                int val = Integer.parseInt(p);
                result.add(val);
            } catch (NumberFormatException e) {
                try {
                    double val = Double.parseDouble(p);
                    result.add(val);
                } catch (NumberFormatException ex) {
                    result.add(p);
                }
            }
        }
        return result;
    }

    static String formatList(List<?> list) {
        return list.toString();
    }

    static void exportJSON(List<?> list, String filename) throws IOException {
        String json = new com.google.gson.GsonBuilder().setPrettyPrinting().create().toJson(list);
        Files.write(Paths.get(filename), json.getBytes());
    }

    static void exportCSV(List<?> list, String filename) throws IOException {
        try (PrintWriter pw = new PrintWriter(filename)) {
            pw.println("value");
            for (Object o : list) pw.println(o);
        }
    }

    static List<Object> importList(String filename) throws IOException {
        String content = new String(Files.readAllBytes(Paths.get(filename)));
        if (filename.endsWith(".json")) {
            // Используем Gson
            return new com.google.gson.Gson().fromJson(content, List.class);
        } else {
            // CSV
            String[] lines = content.split("\n");
            List<Object> result = new ArrayList<>();
            for (int i = 1; i < lines.length; i++) {
                String line = lines[i].trim();
                if (line.isEmpty()) continue;
                try { result.add(Double.parseDouble(line)); }
                catch (NumberFormatException e) { result.add(line); }
            }
            return result;
        }
    }

    static List<Object> generateRandomList(int size, int minVal, int maxVal) {
        List<Object> list = new ArrayList<>();
        Random rnd = new Random();
        for (int i = 0; i < size; i++) {
            list.add(rnd.nextInt(maxVal - minVal + 1) + minVal);
        }
        return list;
    }

    // ========== ОСНОВНАЯ ЛОГИКА ==========
    static void interactive() throws IOException {
        System.out.println("🎲 ПЕРЕМЕШИВАТЕЛЬ СПИСКОВ");
        System.out.println("Введите список (через запятую):");
        System.out.print("> ");
        String input = scanner.nextLine().trim();
        List<Object> data;
        if (input.isEmpty()) {
            System.out.print("Размер случайного списка (по умолчанию 10): ");
            String sizeStr = scanner.nextLine().trim();
            int size = sizeStr.isEmpty() ? 10 : Integer.parseInt(sizeStr);
            data = generateRandomList(size, 0, 100);
            System.out.println("Сгенерированный список: " + formatList(data));
        } else {
            data = parseList(input);
            System.out.println("Исходный список: " + formatList(data));
        }

        System.out.println("\nВыберите алгоритм:");
        for (Map.Entry<String, Algorithm<Object>> entry : ALGORITHMS.entrySet()) {
            System.out.println(entry.getKey() + ". " + entry.getValue().name);
        }
        System.out.print("Ваш выбор (по умолчанию 1): ");
        String algoChoice = scanner.nextLine().trim();
        if (algoChoice.isEmpty() || !ALGORITHMS.containsKey(algoChoice)) algoChoice = "1";
        Algorithm<Object> algo = ALGORITHMS.get(algoChoice);

        System.out.print("Введите seed (или оставьте пустым для случайного): ");
        String seedStr = scanner.nextLine().trim();
        Random rng;
        if (!seedStr.isEmpty()) {
            try {
                long seed = Long.parseLong(seedStr);
                rng = new Random(seed);
            } catch (NumberFormatException e) {
                rng = new Random();
            }
        } else {
            rng = new Random();
        }

        List<Object> shuffled = algo.func.shuffle(data, rng);
        System.out.println("\nПеремешанный список: " + formatList(shuffled));

        Map<String, Object> stats = getStats(shuffled);
        System.out.println("\n📊 Статистика:");
        System.out.println("  Длина: " + stats.get("length"));
        System.out.println("  Инверсий: " + stats.get("inversions"));
        System.out.println("  Энтропия: " + stats.get("entropy"));

        System.out.print("\nЭкспортировать результат? (y/n): ");
        String exportChoice = scanner.nextLine().trim().toLowerCase();
        if (exportChoice.equals("y")) {
            System.out.print("Формат (json/csv): ");
            String fmt = scanner.nextLine().trim();
            System.out.print("Имя файла: ");
            String filename = scanner.nextLine().trim();
            if (filename.isEmpty()) filename = "shuffled." + fmt;
            if (fmt.equals("json")) exportJSON(shuffled, filename);
            else exportCSV(shuffled, filename);
            System.out.println("Экспортировано в " + filename);
        }
    }

    static void cli(String[] args) throws IOException {
        String list = null, file = null, algorithm = "1", export = null;
        int randomSize = 0;
        long seed = 0;
        boolean statsFlag = false;

        for (int i = 0; i < args.length; i++) {
            if (args[i].equals("--list")) list = args[++i];
            else if (args[i].equals("--file")) file = args[++i];
            else if (args[i].equals("--random")) randomSize = Integer.parseInt(args[++i]);
            else if (args[i].equals("--algorithm")) algorithm = args[++i];
            else if (args[i].equals("--seed")) seed = Long.parseLong(args[++i]);
            else if (args[i].equals("--export")) export = args[++i];
            else if (args[i].equals("--stats")) statsFlag = true;
        }

        List<Object> data;
        if (list != null) {
            data = parseList(list);
        } else if (file != null) {
            data = importList(file);
        } else if (randomSize > 0) {
            data = generateRandomList(randomSize, 0, 100);
        } else {
            System.out.println("Укажите --list, --file или --random");
            return;
        }
        if (data.isEmpty()) {
            System.out.println("Список пуст.");
            return;
        }

        Random rng = (seed != 0) ? new Random(seed) : new Random();
        Algorithm<Object> algo = ALGORITHMS.getOrDefault(algorithm, ALGORITHMS.get("1"));
        List<Object> shuffled = algo.func.shuffle(data, rng);

        System.out.println("Исходный список: " + formatList(data));
        System.out.println("Перемешанный список: " + formatList(shuffled));
        if (statsFlag) {
            Map<String, Object> stats = getStats(shuffled);
            System.out.println("\n📊 Статистика:");
            System.out.println("  Длина: " + stats.get("length"));
            System.out.println("  Инверсий: " + stats.get("inversions"));
            System.out.println("  Энтропия: " + stats.get("entropy"));
        }
        if (export != null) {
            if (export.endsWith(".json")) exportJSON(shuffled, export);
            else exportCSV(shuffled, export);
            System.out.println("Экспортировано в " + export);
        }
    }

    public static void main(String[] args) throws IOException {
        if (args.length > 0) {
            cli(args);
        } else {
            interactive();
        }
    }
}
