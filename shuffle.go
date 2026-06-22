// shuffle.go - Перемешиватель списков на Go (CLI)
package main

import (
	"bufio"
	"encoding/csv"
	"encoding/json"
	"flag"
	"fmt"
	"math"
	"math/rand"
	"os"
	"strconv"
	"strings"
	"time"
)

// ========== АЛГОРИТМЫ ПЕРЕМЕШИВАНИЯ ==========
func fisherYatesShuffle(arr []interface{}, rng *rand.Rand) []interface{} {
	result := make([]interface{}, len(arr))
	copy(result, arr)
	for i := len(result) - 1; i > 0; i-- {
		j := rng.Intn(i + 1)
		result[i], result[j] = result[j], result[i]
	}
	return result
}

func sortKeyShuffle(arr []interface{}, rng *rand.Rand) []interface{} {
	result := make([]interface{}, len(arr))
	copy(result, arr)
	// Сортируем с использованием случайного ключа
	sort.Slice(result, func(i, j int) bool {
		return rng.Float64() < 0.5
	})
	return result
}

func builtinShuffle(arr []interface{}, rng *rand.Rand) []interface{} {
	// Используем Fisher-Yates
	return fisherYatesShuffle(arr, rng)
}

type Algorithm struct {
	Name string
	Func func([]interface{}, *rand.Rand) []interface{}
}

var algorithms = map[string]Algorithm{
	"1": {"Fisher-Yates (оптимальный)", fisherYatesShuffle},
	"2": {"Сортировка случайным ключом", sortKeyShuffle},
	"3": {"Встроенный (стандартный)", builtinShuffle},
}

// ========== СТАТИСТИКА ==========
func countInversions(arr []interface{}) int {
	inv := 0
	for i := 0; i < len(arr); i++ {
		for j := i + 1; j < len(arr); j++ {
			// Сравнение только для чисел
			if vi, ok := arr[i].(float64); ok {
				if vj, ok := arr[j].(float64); ok {
					if vi > vj {
						inv++
					}
				}
			}
		}
	}
	return inv
}

func entropy(arr []interface{}) float64 {
	if len(arr) == 0 {
		return 0.0
	}
	// Проверяем, все ли числа
	numbers := make([]float64, 0, len(arr))
	for _, v := range arr {
		if f, ok := v.(float64); ok {
			numbers = append(numbers, f)
		} else {
			return 0.0
		}
	}
	if len(numbers) == 0 {
		return 0.0
	}
	minVal := numbers[0]
	maxVal := numbers[0]
	for _, v := range numbers {
		if v < minVal {
			minVal = v
		}
		if v > maxVal {
			maxVal = v
		}
	}
	if maxVal == minVal {
		return 0.0
	}
	bins := 10
	hist := make([]int, bins)
	for _, v := range numbers {
		idx := int((v - minVal) / (maxVal - minVal) * float64(bins))
		if idx == bins {
			idx = bins - 1
		}
		hist[idx]++
	}
	ent := 0.0
	n := float64(len(numbers))
	for _, count := range hist {
		if count > 0 {
			p := float64(count) / n
			ent -= p * math.Log2(p)
		}
	}
	return ent
}

func getStats(arr []interface{}) map[string]interface{} {
	return map[string]interface{}{
		"length":     len(arr),
		"inversions": countInversions(arr),
		"entropy":    entropy(arr),
	}
}

// ========== ВВОД/ВЫВОД ==========
func parseList(input string) []interface{} {
	parts := strings.Split(input, ",")
	result := make([]interface{}, 0, len(parts))
	for _, p := range parts {
		p = strings.TrimSpace(p)
		if p == "" {
			continue
		}
		if val, err := strconv.Atoi(p); err == nil {
			result = append(result, float64(val))
		} else if val, err := strconv.ParseFloat(p, 64); err == nil {
			result = append(result, val)
		} else {
			result = append(result, p)
		}
	}
	return result
}

func formatList(arr []interface{}) string {
	parts := make([]string, len(arr))
	for i, v := range arr {
		switch v := v.(type) {
		case float64:
			if v == float64(int(v)) {
				parts[i] = fmt.Sprintf("%d", int(v))
			} else {
				parts[i] = fmt.Sprintf("%.2f", v)
			}
		default:
			parts[i] = fmt.Sprintf("%v", v)
		}
	}
	return "[" + strings.Join(parts, ", ") + "]"
}

func exportJSON(arr []interface{}, filename string) error {
	file, err := os.Create(filename)
	if err != nil {
		return err
	}
	defer file.Close()
	encoder := json.NewEncoder(file)
	encoder.SetIndent("", "  ")
	return encoder.Encode(arr)
}

func exportCSV(arr []interface{}, filename string) error {
	file, err := os.Create(filename)
	if err != nil {
		return err
	}
	defer file.Close()
	writer := csv.NewWriter(file)
	defer writer.Flush()
	writer.Write([]string{"value"})
	for _, v := range arr {
		writer.Write([]string{fmt.Sprintf("%v", v)})
	}
	return nil
}

func importList(filename string) ([]interface{}, error) {
	data, err := os.ReadFile(filename)
	if err != nil {
		return nil, err
	}
	if strings.HasSuffix(filename, ".json") {
		var arr []interface{}
		if err := json.Unmarshal(data, &arr); err != nil {
			return nil, err
		}
		return arr, nil
	} else {
		// CSV
		lines := strings.Split(string(data), "\n")
		var arr []interface{}
		for i, line := range lines {
			if i == 0 {
				continue // пропускаем заголовок
			}
			line = strings.TrimSpace(line)
			if line == "" {
				continue
			}
			if val, err := strconv.ParseFloat(line, 64); err == nil {
				arr = append(arr, val)
			} else {
				arr = append(arr, line)
			}
		}
		return arr, nil
	}
}

func generateRandomList(size int, minVal, maxVal int) []interface{} {
	arr := make([]interface{}, size)
	for i := 0; i < size; i++ {
		arr[i] = float64(rand.Intn(maxVal-minVal+1) + minVal)
	}
	return arr
}

// ========== ОСНОВНАЯ ЛОГИКА ==========
func interactive() {
	scanner := bufio.NewScanner(os.Stdin)
	fmt.Println("🎲 ПЕРЕМЕШИВАТЕЛЬ СПИСКОВ")
	fmt.Println("Введите список (через запятую):")
	fmt.Print("> ")
	scanner.Scan()
	input := scanner.Text()
	var data []interface{}
	if input == "" {
		fmt.Print("Размер случайного списка (по умолчанию 10): ")
		scanner.Scan()
		sizeStr := scanner.Text()
		size := 10
		if sizeStr != "" {
			size, _ = strconv.Atoi(sizeStr)
		}
		data = generateRandomList(size, 0, 100)
		fmt.Printf("Сгенерированный список: %s\n", formatList(data))
	} else {
		data = parseList(input)
		fmt.Printf("Исходный список: %s\n", formatList(data))
	}

	fmt.Println("\nВыберите алгоритм:")
	for key, alg := range algorithms {
		fmt.Printf("%s. %s\n", key, alg.Name)
	}
	fmt.Print("Ваш выбор (по умолчанию 1): ")
	scanner.Scan()
	algoChoice := scanner.Text()
	if algoChoice == "" || algorithms[algoChoice].Name == "" {
		algoChoice = "1"
	}
	algorithm := algorithms[algoChoice].Func

	fmt.Print("Введите seed (или оставьте пустым для случайного): ")
	scanner.Scan()
	seedStr := scanner.Text()
	var rng *rand.Rand
	if seedStr != "" {
		seed, err := strconv.ParseInt(seedStr, 10, 64)
		if err != nil {
			fmt.Println("Неверный seed, используется случайный.")
			rng = rand.New(rand.NewSource(time.Now().UnixNano()))
		} else {
			rng = rand.New(rand.NewSource(seed))
		}
	} else {
		rng = rand.New(rand.NewSource(time.Now().UnixNano()))
	}

	shuffled := algorithm(data, rng)
	fmt.Printf("\nПеремешанный список: %s\n", formatList(shuffled))

	stats := getStats(shuffled)
	fmt.Println("\n📊 Статистика:")
	fmt.Printf("  Длина: %d\n", stats["length"])
	fmt.Printf("  Инверсий: %d\n", stats["inversions"])
	fmt.Printf("  Энтропия: %.4f\n", stats["entropy"])

	fmt.Print("\nЭкспортировать результат? (y/n): ")
	scanner.Scan()
	exportChoice := scanner.Text()
	if exportChoice == "y" || exportChoice == "Y" {
		fmt.Print("Формат (json/csv): ")
		scanner.Scan()
		fmt.Print("Имя файла: ")
		scanner.Scan()
		filename := scanner.Text()
		if filename == "" {
			filename = "shuffled." + scanner.Text()
		}
		var err error
		if scanner.Text() == "json" {
			err = exportJSON(shuffled, filename)
		} else {
			err = exportCSV(shuffled, filename)
		}
		if err != nil {
			fmt.Printf("Ошибка: %v\n", err)
		} else {
			fmt.Printf("Экспортировано в %s\n", filename)
		}
	}
}

func cli() {
	var list string
	var file string
	var random int
	var algorithmKey string
	var seed int64
	var export string
	var statsFlag bool

	flag.StringVar(&list, "list", "", "Список через запятую")
	flag.StringVar(&file, "file", "", "Файл для импорта (JSON/CSV)")
	flag.IntVar(&random, "random", 0, "Сгенерировать случайный список")
	flag.StringVar(&algorithmKey, "algorithm", "1", "Алгоритм (1-3)")
	flag.Int64Var(&seed, "seed", 0, "Seed для воспроизводимости")
	flag.StringVar(&export, "export", "", "Экспорт в файл")
	flag.BoolVar(&statsFlag, "stats", false, "Показать статистику")
	flag.Parse()

	var data []interface{}
	if list != "" {
		data = parseList(list)
	} else if file != "" {
		var err error
		data, err = importList(file)
		if err != nil {
			fmt.Printf("Ошибка импорта: %v\n", err)
			return
		}
	} else if random > 0 {
		data = generateRandomList(random, 0, 100)
	} else {
		fmt.Println("Укажите --list, --file или --random")
		return
	}
	if len(data) == 0 {
		fmt.Println("Список пуст.")
		return
	}

	var rng *rand.Rand
	if seed != 0 {
		rng = rand.New(rand.NewSource(seed))
	} else {
		rng = rand.New(rand.NewSource(time.Now().UnixNano()))
	}

	algo, ok := algorithms[algorithmKey]
	if !ok {
		algo = algorithms["1"]
	}
	shuffled := algo.Func(data, rng)

	fmt.Printf("Исходный список: %s\n", formatList(data))
	fmt.Printf("Перемешанный список: %s\n", formatList(shuffled))
	if statsFlag {
		stats := getStats(shuffled)
		fmt.Println("\n📊 Статистика:")
		fmt.Printf("  Длина: %d\n", stats["length"])
		fmt.Printf("  Инверсий: %d\n", stats["inversions"])
		fmt.Printf("  Энтропия: %.4f\n", stats["entropy"])
	}
	if export != "" {
		var err error
		if strings.HasSuffix(export, ".json") {
			err = exportJSON(shuffled, export)
		} else {
			err = exportCSV(shuffled, export)
		}
		if err != nil {
			fmt.Printf("Ошибка экспорта: %v\n", err)
		} else {
			fmt.Printf("Экспортировано в %s\n", export)
		}
	}
}

func main() {
	if len(os.Args) > 1 {
		cli()
	} else {
		interactive()
	}
}
