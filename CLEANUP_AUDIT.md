# 🔍 AUDITORIA DE ARQUIVOS DO PLUGIN

## 📂 ARQUIVOS PRINCIPAIS (MANTER)
- `local2global-attribute-mapper.php` - Arquivo principal do plugin
- `src/Plugin.php` - Classe principal
- `src/Services/Mapping_Service.php` - Serviço core de mapeamento
- `src/Services/Discovery_Service.php` - Descoberta de atributos
- `src/Services/Term_Service.php` - Gerenciamento de termos
- `src/Services/Variation_Service.php` - **ARQUIVO CORRIGIDO** (manter)
- `src/Admin/UI.php` - Interface administrativa
- `src/Admin/Settings.php` - Configurações
- `src/Rest/Rest_Controller.php` - API REST
- `src/Cli/CLI_Command.php` - Comandos CLI
- `src/Utils/Logger.php` - Sistema de logs
- `src/Utils/Value_Normalizer.php` - Normalização de valores
- `src/Setup/Activator.php` - Ativação do plugin

## 📁 ARQUIVOS DE ASSETS (MANTER)
- `assets/css/admin.css` - Estilos administrativos
- `assets/js/admin.js` - Scripts administrativos

## 📋 ARQUIVOS DE CONFIGURAÇÃO (MANTER)
- `composer.json` - Dependências PHP
- `phpstan.neon` - Análise estática
- `build.sh` - Script de build
- `.buildignore` - Arquivos ignorados no build
- `.gitignore` - Controle de versão
- `.vscode/settings.json` - Configurações VS Code

## 📚 DOCUMENTAÇÃO (CONSOLIDAR)
- `README.md` - **MANTER** (principal)
- `DOCUMENTATION.md` - **MANTER** (técnica)
- `CHANGELOG.md` - **MANTER** (histórico)
- `LICENSE` - **MANTER** (licença)
- `MELHORIAS_VARIACOES.md` - **REMOVER** (temp de desenvolvimento)
- `ATOMIC_PERSISTENCE_FIX.md` - **REMOVER** (temp de desenvolvimento)
- `LOG_OPTIMIZATION_SUMMARY.md` - **REMOVER** (temp de desenvolvimento)

## 🗑️ ARQUIVOS DE TESTE/DEBUG (REMOVER)
- `test_atomic_fix.php` - **REMOVER**
- `test_optimized_logs.php` - **REMOVER**
- `validate_fix.php` - **REMOVER**
- `test-improvements.sh` - **REMOVER**
- `dev.sh` - **REMOVER**

## 📁 DIRETÓRIO tests/ (LIMPAR)
### Manter apenas:
- `tests/run-tests.php` - **MANTER** (será o teste unificado)
- `tests/stubs/woocommerce.php` - **MANTER** (necessário para testes)

### Remover:
- `tests/analyze-pa-cor-problem.php` - **REMOVER**
- `tests/test-persistence-fix.php` - **REMOVER**
- `tests/test-duplicate-values-fix.php` - **REMOVER**
- `tests/test-simulation-1832.php` - **REMOVER**
- `tests/final-demo.php` - **REMOVER**
- `tests/test-error-fix.php` - **REMOVER**
- `tests/test-wc-sync-fix.php` - **REMOVER**
- `tests/test-final-persistence-fix.php` - **REMOVER**
- `tests/test-diagnostic-advanced.php` - **REMOVER**
- `tests/test-debug-logs.sh` - **REMOVER**
- `tests/test-variations-improvements.php` - **REMOVER**
- `tests/check-logs.sh` - **REMOVER**

## ❌ ARQUIVOS DUPLICADOS (REMOVER)
- `src/Services/Variation_Service_V2.php` - **REMOVER** (versão alternativa, usar principal)

## 📊 RESUMO DA LIMPEZA
- **Arquivos a remover**: ~20 arquivos
- **Espaço economizado**: Significativo
- **Complexidade reduzida**: Alta
- **Funcionalidade mantida**: 100%