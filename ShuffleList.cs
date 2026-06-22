// ShuffleList.cs - Перемешиватель списков на C# (CLI)
using System;
using System.Collections.Generic;
using System.IO;
using System.Linq;
using System.Text.Json;
using System.Threading.Tasks;

namespace ShuffleList
{
    class Program
    {
        private static readonly Random random = new Random();

        // ========== АЛГОРИТМЫ ПЕРЕМЕШИВАНИЯ ==========
        static List<T> FisherYates<T>(List<T> list, Random rng)
        {
            var result = new List<T>(list);
            for (int i = result.Count - 1; i > 0; i--)
            {
                int j = rng.Next(i + 1);
                (result[i], result[j]) = (result[j], result[i]);
            }
            return result;
        }

        static List<T> SortKeyShuffle<T>(List<T> list, Random rng)
        {
            var result = new List<T>(list);
            result.Sort((a, b) => rng.NextDouble() < 0.5 ? -1 : 1);
            return result;
        }

        static List<T> BuiltinShuffle<T>(List<T> list, Random rng)
        {
            var result = new List<T>(list);
            // Используем Fisher-Yates с нашим генератором
            return FisherYates(result, rng);
        }

        static Dictionary<string, (string name, Func<List<object>, Random, List<object>> func)> algorithms =
            new Dictionary<string, (string, Func<List<object>, Random, List<object>>)>
        {
            {"1", ("Fisher-Yates (оптимальный)", (list, rng) => FisherYates(list, rng).Cast<object>().ToList())},
            {"2", ("Сортировка случайным ключом", (list, rng) => SortKeyShuffle(list, rng).Cast<object>().ToList())},
            {"3", ("Встроенный (стандартный)", (list, rng) => BuiltinShuffle(list, rng).Cast<object>().ToList())}
        };

        // ========== СТАТИСТИКА ==========
        static int CountInversions<T>(List<T> list) where T : IComparable<T>
        {
            int inv = 0;
            for (int i = 0; i < list.Count; i++)
                for (int j = i + 1; j < list.Count; j++)
                    if (list[i].CompareTo(list[j]) > 0) inv++;
            return inv;
        }

        static double Entropy(List<double> list)
        {
            if (list.Count == 0) return 0;
            double min = list.Min();
            double max = list.Max();
            if (max == min) return 0;
            int bins = 10;
            int[] hist = new int[bins];
            foreach (double x in list)
            {
                int idx = (int)((x - min) / (max - min) * bins);
                if (idx == bins) idx = bins - 1;
                hist[idx]++;
            }
            double ent = 0;
            double n = list.Count;
            foreach (int count in hist)
                if (count > 0)
                {
                    double p = count / n;
                    ent -= p * Math.Log2(p);
                }
            return ent;
        }

        static (int length, int inversions, double entropy) GetStats(List<object> list)
        {
            int length = list.Count;
            int inv = 0;
            double ent = 0.0;
            if (length > 0 && list[0] is IComparable)
            {
                var comparableList = list.Cast<IComparable>().ToList();
                inv = CountInversions(comparableList);
            }
            if (length > 0 && list[0] is double || list[0] is int)
            {
                var nums = list.Select(x => Convert.ToDouble(x)).ToList();
                ent = Entropy(nums);
            }
            return (length, inv, ent);
        }

        // ========== ВВОД/ВЫВОД ==========
        static List<object> ParseList(string input)
        {
            var parts = input.Split(',', StringSplitOptions.RemoveEmptyEntries);
            var result = new List<object>();
            foreach (var p in parts)
            {
                string trimmed = p.Trim();
                if (int.TryParse(trimmed, out int i)) result.Add(i);
                else if (double.TryParse(trimmed, out double d)) result.Add(d);
                else result.Add(trimmed);
            }
            return result;
        }

        static string FormatList(List<object> list) => $"[{string.Join(", ", list)}]";

        static void ExportJSON(List<object> list, string filename)
        {
            string json = JsonSerializer.Serialize(list, new JsonSerializerOptions { WriteIndented = true });
            File.WriteAllText(filename, json);
        }

        static void ExportCSV(List<object> list, string filename)
        {
            using var sw = new StreamWriter(filename);
            sw.WriteLine("value");
            foreach (var item in list) sw.WriteLine(item);
        }

        static List<object> ImportList(string filename)
        {
            string content = File.ReadAllText(filename);
            if (filename.EndsWith(".json"))
                return JsonSerializer.Deserialize<List<object>>(content);
            else
            {
                var lines = content.Split('\n', StringSplitOptions.RemoveEmptyEntries);
                var result = new List<object>();
                for (int i = 1; i < lines.Length; i++)
                {
                    string line = lines[i].Trim();
                    if (int.TryParse(line, out int v)) result.Add(v);
                    else if (double.TryParse(line, out double d)) result.Add(d);
                    else result.Add(line);
                }
                return result;
            }
        }

        static List<object> GenerateRandomList(int size, int minVal, int maxVal)
        {
            var rnd = new Random();
            var list = new List<object>();
            for (int i = 0; i < size; i++)
                list.Add(rnd.Next(minVal, maxVal + 1));
            return list;
        }

        static async Task Interactive()
        {
            Console.WriteLine("🎲 ПЕРЕМЕШИВАТЕЛЬ СПИСКОВ");
            Console.WriteLine("Введите список (через запятую):");
            Console.Write("> ");
            string input = Console.ReadLine();
            List<object> data;
            if (string.IsNullOrWhiteSpace(input))
            {
                Console.Write("Размер случайного списка (по умолчанию 10): ");
                string sizeStr = Console.ReadLine();
                int size = string.IsNullOrEmpty(sizeStr) ? 10 : int.Parse(sizeStr);
                data = GenerateRandomList(size, 0, 100);
                Console.WriteLine($"Сгенерированный список: {FormatList(data)}");
            }
            else
            {
                data = ParseList(input);
                Console.WriteLine($"Исходный список: {FormatList(data)}");
            }

            Console.WriteLine("\nВыберите алгоритм:");
            foreach (var kv in algorithms)
                Console.WriteLine($"{kv.Key}. {kv.Value.name}");
            Console.Write("Ваш выбор (по умолчанию 1): ");
            string algoChoice = Console.ReadLine();
            if (string.IsNullOrEmpty(algoChoice) || !algorithms.ContainsKey(algoChoice)) algoChoice = "1";
            var algo = algorithms[algoChoice];

            Console.Write("Введите seed (или оставьте пустым для случайного): ");
            string seedStr = Console.ReadLine();
            Random rng;
            if (!string.IsNullOrEmpty(seedStr) && long.TryParse(seedStr, out long seed))
                rng = new Random((int)seed);
            else
                rng = new Random();

            var shuffled = algo.func(data, rng);
            Console.WriteLine($"\nПеремешанный список: {FormatList(shuffled)}");

            var stats = GetStats(shuffled);
            Console.WriteLine("\n📊 Статистика:");
            Console.WriteLine($"  Длина: {stats.length}");
            Console.WriteLine($"  Инверсий: {stats.inversions}");
            Console.WriteLine($"  Энтропия: {stats.entropy:F4}");

            Console.Write("\nЭкспортировать результат? (y/n): ");
            if (Console.ReadLine()?.ToLower() == "y")
            {
                Console.Write("Формат (json/csv): ");
                string fmt = Console.ReadLine();
                Console.Write("Имя файла: ");
                string filename = Console.ReadLine();
                if (string.IsNullOrEmpty(filename)) filename = $"shuffled.{fmt}";
                if (fmt == "json") ExportJSON(shuffled, filename);
                else ExportCSV(shuffled, filename);
                Console.WriteLine($"Экспортировано в {filename}");
            }
        }

        static void Main(string[] args)
        {
            if (args.Length > 0)
            {
                string list = null, file = null, algorithm = "1", export = null;
                int randomSize = 0;
                long seed = 0;
                bool statsFlag = false;
                for (int i = 0; i < args.Length; i++)
                {
                    if (args[i] == "--list") list = args[++i];
                    else if (args[i] == "--file") file = args[++i];
                    else if (args[i] == "--random") randomSize = int.Parse(args[++i]);
                    else if (args[i] == "--algorithm") algorithm = args[++i];
                    else if (args[i] == "--seed") seed = long.Parse(args[++i]);
                    else if (args[i] == "--export") export = args[++i];
                    else if (args[i] == "--stats") statsFlag = true;
                }
                List<object> data;
                if (list != null) data = ParseList(list);
                else if (file != null) data = ImportList(file);
                else if (randomSize > 0) data = GenerateRandomList(randomSize, 0, 100);
                else { Console.WriteLine("Укажите --list, --file или --random"); return; }
                if (data.Count == 0) { Console.WriteLine("Список пуст."); return; }

                Random rng = seed != 0 ? new Random((int)seed) : new Random();
                var algo = algorithms.ContainsKey(algorithm) ? algorithms[algorithm] : algorithms["1"];
                var shuffled = algo.func(data, rng);

                Console.WriteLine($"Исходный список: {FormatList(data)}");
                Console.WriteLine($"Перемешанный список: {FormatList(shuffled)}");
                if (statsFlag)
                {
                    var stats = GetStats(shuffled);
                    Console.WriteLine("\n📊 Статистика:");
                    Console.WriteLine($"  Длина: {stats.length}");
                    Console.WriteLine($"  Инверсий: {stats.inversions}");
                    Console.WriteLine($"  Энтропия: {stats.entropy:F4}");
                }
                if (export != null)
                {
                    if (export.EndsWith(".json")) ExportJSON(shuffled, export);
                    else ExportCSV(shuffled, export);
                    Console.WriteLine($"Экспортировано в {export}");
                }
            }
            else
            {
                Interactive().Wait();
            }
        }
    }
}
