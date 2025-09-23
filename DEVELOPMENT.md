# Guia de Desenvolvimento - Local2Global

Este documento descreve o ambiente de desenvolvimento configurado para o plugin.

## 🛠️ Configuração do Ambiente

### Dependências Instaladas

- **WordPress Stubs**: Definições de funções e classes do WordPress
- **WooCommerce Stubs**: Definições específicas do WooCommerce  
- **PHPStan**: Análise estática de código
- **PHPStan WordPress Extension**: Regras específicas para WordPress

### Stubs e Autocomplete

O ambiente está configurado para eliminar erros de "undefined function" no VS Code:

```json
// .vscode/settings.json
{
  "intelephense.environment.includePaths": [
    "./vendor/php-stubs/wordpress-stubs",
    "./vendor/php-stubs/woocommerce-stubs"
  ]
}
```

Isso fornece autocomplete para:
- ✅ `add_action()`, `add_filter()`, `get_option()`
- ✅ `wc_get_product()`, `WC_Product`, `WC_Product_Attribute`
- ✅ Classes e interfaces do WordPress/WooCommerce
- ✅ Constantes globais (`ABSPATH`, `WP_DEBUG`, etc.)

## 🚀 Scripts de Desenvolvimento

Use o script `./dev.sh` para tarefas comuns:

```bash
# Verificar status do ambiente
./dev.sh status

# Executar testes
./dev.sh test

# Análise estática (quando configurado)
./dev.sh analyse

# Analisar arquivo específico
./dev.sh analyse-file src/Services/Mapping_Service.php

# Reinstalar dependências
./dev.sh clean
./dev.sh install
```

## 📁 Estrutura de Arquivos

```
├── composer.json          # Dependências de desenvolvimento
├── phpstan.neon           # Configuração de análise estática
├── .vscode/settings.json   # Configurações do VS Code
├── .gitignore             # Ignora vendor/ e arquivos temporários
├── dev.sh                 # Script utilitário de desenvolvimento
└── vendor/                # Dependências (não versionado)
    ├── php-stubs/         # Stubs do WordPress e WooCommerce
    └── phpstan/           # Ferramenta de análise
```

## 🎯 Benefícios

### Antes (com erros)
```php
// ❌ VS Code mostra: "Undefined function 'add_action'"
add_action('init', function() {
    // ❌ "Undefined function 'wc_get_product'"
    $product = wc_get_product(123);
});
```

### Depois (com stubs)
```php
// ✅ Autocomplete e tipagem funcionando
add_action('init', function() {
    // ✅ Autocomplete para WC_Product métodos
    $product = wc_get_product(123);
    if ($product instanceof WC_Product) {
        $attributes = $product->get_attributes(); // ✅ Tipado
    }
});
```

## 🔧 Configuração Manual (caso necessário)

Se os stubs não estiverem funcionando:

1. **Recarregar VS Code**: `Cmd/Ctrl + Shift + P` → "Developer: Reload Window"

2. **Verificar configuração**: Confirme que `.vscode/settings.json` tem:
   ```json
   {
     "intelephense.environment.includePaths": [
       "./vendor/php-stubs/wordpress-stubs",
       "./vendor/php-stubs/woocommerce-stubs"
     ]
   }
   ```

3. **Reinstalar dependências**:
   ```bash
   ./dev.sh clean
   ./dev.sh install
   ```

## 📝 Análise de Código

O PHPStan está configurado mas pode ter problemas de memória. Para uso básico:

```bash
# Verificar um arquivo específico
php -d memory_limit=512M vendor/bin/phpstan analyse --level=1 src/Services/Mapping_Service.php
```

## 🎉 Resultado

Com essa configuração, você deve ter:
- ✅ Zero erros de "undefined function" no VS Code
- ✅ Autocomplete completo para WordPress/WooCommerce
- ✅ Tipagem adequada para classes e métodos
- ✅ Ambiente profissional de desenvolvimento