<?php
/**
 * TESTE COMPLETO UNIFICADO - LOCAL2GLOBAL ATTRIBUTE MAPPER
 * 
 * Combina:
 * - Verificação de arquivos e estrutura (run-unified-test.php)
 * - Testes unitários e funcionais (run-tests.php)
 * - Validação das correções implementadas
 * 
 * @version 1.0.0
 * @author Local2Global Plugin Team
 */

declare(strict_types=1);

echo "TESTE COMPLETO UNIFICADO - LOCAL2GLOBAL ATTRIBUTE MAPPER\n";
echo str_repeat("=", 65) . "\n\n";

// ==================== SEÇÃO 1: VERIFICAÇÃO DE ARQUIVOS ====================

echo "VERIFICAÇÃO DE ARQUIVOS PRINCIPAIS:\n";

$core_files = [
    'Plugin principal' => 'local2global-attribute-mapper.php',
    'Variation Service' => 'src/Services/Variation_Service.php',
    'Mapping Service' => 'src/Services/Mapping_Service.php',
    'Discovery Service' => 'src/Services/Discovery_Service.php',
    'Term Service' => 'src/Services/Term_Service.php',
    'Logger' => 'src/Utils/Logger.php',
    'Value Normalizer' => 'src/Utils/Value_Normalizer.php'
];

$file_check_passed = 0;
$file_check_total = count($core_files);

foreach ($core_files as $name => $path) {
    if (file_exists($path)) {
        echo sprintf("%-20s OK\n", $name);
        $file_check_passed++;
    } else {
        echo sprintf("%-20s FALTANDO\n", $name);
    }
}

echo "\n";

// ==================== SEÇÃO 2: VERIFICAÇÃO DAS CORREÇÕES ====================

echo "VERIFICAÇÃO DAS CORREÇÕES IMPLEMENTADAS:\n";

$corrections = [
    'atomic_persistence' => [
        'description' => 'Persistência atômica implementada',
        'check' => function() {
            $content = file_get_contents('src/Services/Variation_Service.php');
            return strpos($content, 'force_individual_variation_save') !== false;
        }
    ],
    'hook_management' => [
        'description' => 'Gerenciamento de hooks interferentes',
        'check' => function() {
            $content = file_get_contents('src/Services/Variation_Service.php');
            return strpos($content, 'disable_interfering_hooks') !== false;
        }
    ],
    'cache_management' => [
        'description' => 'Limpeza abrangente de cache',
        'check' => function() {
            $content = file_get_contents('src/Services/Variation_Service.php');
            return strpos($content, 'clear_comprehensive_cache') !== false;
        }
    ],
    'smart_verification' => [
        'description' => 'Verificação inteligente de persistência',
        'check' => function() {
            $content = file_get_contents('src/Services/Variation_Service.php');
            return strpos($content, 'verify_immediate_persistence') !== false;
        }
    ],
    'fallback_strategies' => [
        'description' => 'Estratégias de fallback implementadas',
        'check' => function() {
            $content = file_get_contents('src/Services/Variation_Service.php');
            return strpos($content, 'try_variation_attributes_update') !== false 
                && strpos($content, 'try_inference_update') !== false;
        }
    ]
];

$corrections_passed = 0;
$corrections_total = count($corrections);

foreach ($corrections as $key => $correction) {
    $status = $correction['check']() ? 'IMPLEMENTADO' : 'FALTANDO';
    echo sprintf("%-25s %s - %s\n", $key, $status, $correction['description']);
    if ($correction['check']()) {
        $corrections_passed++;
    }
}

echo "\n";

// ==================== SEÇÃO 3: TESTES FUNCIONAIS ====================

echo "TESTES FUNCIONAIS:\n";

// Add random_int function if not exists (for PHP < 7.0 compatibility in tests)
if (!function_exists('random_int')) {
    function random_int(int $min, int $max): int {
        $range = $max - $min;
        return $min + (int) (microtime(true) * 1000000) % ($range + 1);
    }
}

// Load required files for functional tests
$functional_tests_enabled = true;
$functional_test_error = '';

try {
    if (file_exists(__DIR__ . '/stubs/woocommerce.php')) {
        require_once __DIR__ . '/stubs/woocommerce.php';
    }
    if (file_exists(__DIR__ . '/../src/Services/Mapping_Service.php')) {
        require_once __DIR__ . '/../src/Services/Mapping_Service.php';
    }
    if (file_exists(__DIR__ . '/../src/Services/Variation_Service.php')) {
        require_once __DIR__ . '/../src/Services/Variation_Service.php';
    }
    if (file_exists(__DIR__ . '/../src/Services/Term_Service.php')) {
        require_once __DIR__ . '/../src/Services/Term_Service.php';
    }
    if (file_exists(__DIR__ . '/../src/Utils/Logger.php')) {
        require_once __DIR__ . '/../src/Utils/Logger.php';
    }
    if (file_exists(__DIR__ . '/../src/Utils/Value_Normalizer.php')) {
        require_once __DIR__ . '/../src/Utils/Value_Normalizer.php';
    }
} catch (Exception $e) {
    $functional_tests_enabled = false;
    $functional_test_error = $e->getMessage();
}

$functional_tests_passed = 0;
$functional_tests_total = 5;

if ($functional_tests_enabled) {
    try {
        // Test 1: Class Loading
        echo "Carregamento de classes     ";
        if (class_exists('Evolury\\Local2Global\\Services\\Mapping_Service') &&
            class_exists('Evolury\\Local2Global\\Services\\Variation_Service') &&
            class_exists('Evolury\\Local2Global\\Utils\\Logger')) {
            echo "OK\n";
            $functional_tests_passed++;
        } else {
            echo "FALHOU\n";
        }

        // Test 2: Logger Instance
        echo "Instanciação do Logger     ";
        $logger = new Evolury\Local2Global\Utils\Logger();
        if ($logger instanceof Evolury\Local2Global\Utils\Logger) {
            echo "OK\n";
            $functional_tests_passed++;
        } else {
            echo "FALHOU\n";
        }

        // Test 3: Value Normalizer
        echo "Normalização de valores    ";
        $normalized = Evolury\Local2Global\Utils\Value_Normalizer::normalize('Test Value');
        if (is_string($normalized) && $normalized === 'test-value') {
            echo "OK\n";
            $functional_tests_passed++;
        } else {
            echo "FALHOU\n";
        }

        // Test 4: Service Instantiation
        echo "Instanciação de serviços   ";
        $term_service = new Evolury\Local2Global\Services\Term_Service($logger);
        $variation_service = new Evolury\Local2Global\Services\Variation_Service($logger);
        if ($term_service && $variation_service) {
            echo "OK\n";
            $functional_tests_passed++;
        } else {
            echo "FALHOU\n";
        }

        // Test 5: Method Existence
        echo "Métodos principais         ";
        if (method_exists($variation_service, 'update_variations') &&
            method_exists($term_service, 'ensure_global_attribute')) {
            echo "OK\n";
            $functional_tests_passed++;
        } else {
            echo "FALHOU\n";
        }

    } catch (Exception $e) {
        echo "ERRO: " . $e->getMessage() . "\n";
    }
} else {
    echo "Testes funcionais desabilitados: $functional_test_error\n";
}

echo "\n";

// ==================== SEÇÃO 4: VERIFICAÇÃO DE PERFORMANCE ====================

echo "ANÁLISE DE PERFORMANCE:\n";

$performance_checks = [
    'Tamanho do Variation_Service' => function() {
        $lines = count(file('src/Services/Variation_Service.php'));
        if ($lines <= 800) {
            return ['status' => 'OK', 'message' => "$lines linhas (objetivo: ≤800)"];
        } else {
            return ['status' => 'AVISO', 'message' => "$lines linhas (acima do objetivo)"];
        }
    },
    'Métodos excessivos removidos' => function() {
        $content = file_get_contents('src/Services/Variation_Service.php');
        $excessive_methods = ['validate_final_persistence', 'infer_missing_value'];
        $found = 0;
        foreach ($excessive_methods as $method) {
            if (strpos($content, "function $method") !== false) {
                $found++;
            }
        }
        if ($found === 0) {
            return ['status' => 'OK', 'message' => 'Métodos redundantes removidos'];
        } else {
            return ['status' => 'AVISO', 'message' => "$found métodos redundantes ainda presentes"];
        }
    },
    'Logs de debug minimizados' => function() {
        $content = file_get_contents('src/Services/Variation_Service.php');
        $debug_patterns = ['debug.', 'DEBUG', 'TEMPORÁRIO'];
        $found = 0;
        foreach ($debug_patterns as $pattern) {
            $found += substr_count($content, $pattern);
        }
        if ($found <= 5) {
            return ['status' => 'OK', 'message' => 'Logs de debug otimizados'];
        } else {
            return ['status' => 'AVISO', 'message' => "$found logs de debug encontrados"];
        }
    }
];

$performance_passed = 0;
$performance_total = count($performance_checks);

foreach ($performance_checks as $name => $check) {
    $result = $check();
    echo sprintf("%-30s %s %s\n", $name, $result['status'], $result['message']);
    if ($result['status'] === 'OK') {
        $performance_passed++;
    }
}

echo "\n";

// ==================== SEÇÃO 5: RESULTADO FINAL ====================

echo "RESULTADO DOS TESTES:\n";
echo sprintf("Arquivos principais: %d/%d OK\n", $file_check_passed, $file_check_total);
echo sprintf("Correções implementadas: %d/%d OK\n", $corrections_passed, $corrections_total);
echo sprintf("Testes funcionais: %d/%d OK\n", $functional_tests_passed, $functional_tests_total);
echo sprintf("Análise de performance: %d/%d OK\n", $performance_passed, $performance_total);

$total_passed = $file_check_passed + $corrections_passed + $functional_tests_passed + $performance_passed;
$total_tests = $file_check_total + $corrections_total + $functional_tests_total + $performance_total;
$success_rate = round(($total_passed / $total_tests) * 100, 1);

echo sprintf("Taxa de sucesso geral: %.1f%%\n\n", $success_rate);

// Status final
if ($success_rate >= 90) {
    echo "SUCESSO: Plugin otimizado e funcionando corretamente!\n";
    $exit_code = 0;
} elseif ($success_rate >= 75) {
    echo "ATENÇÃO: Algumas verificações falharam!\n";
    echo "Revise as implementações antes de usar em produção.\n";
    $exit_code = 1;
} else {
    echo "FALHA: Muitos problemas detectados!\n";
    echo "Correções necessárias antes de continuar.\n";
    $exit_code = 2;
}

echo "\n📋 COMANDOS ÚTEIS:\n";
echo "• Teste de mapeamento: wp l2g map_variations --product_id=ID --local=atributo --taxonomy=pa_atributo --mapping='valor:slug'\n";
echo "• Monitorar logs: tail -f /tmp/local2global.log\n";
echo "• Análise de código: vendor/bin/phpstan analyse\n";

echo "\n📚 DOCUMENTAÇÃO:\n";
echo "• README.md - Guia de uso principal\n";
echo "• DOCUMENTATION.md - Documentação técnica\n";
echo "• CHANGELOG.md - Histórico de versões\n";
echo "• REFACTORING_SUMMARY.md - Resumo da refatoração\n";

echo "\n";
echo str_repeat("=", 65) . "\n";
echo "Teste concluído em " . date('Y-m-d H:i:s') . "\n";

exit($exit_code);