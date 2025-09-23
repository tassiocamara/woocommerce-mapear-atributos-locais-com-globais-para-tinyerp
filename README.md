# Local 2 Global Attribute Mapper

Plugin do WooCommerce para converter atributos locais em atributos globais (taxonomias `pa_*`) com atualizações automáticas de variações. **Versão 0.3.0** - simplificada com comportamento determinístico e UX melhorada.

## ✨ Principais funcionalidades

- 🔍 **Descoberta automática** de atributos locais não-taxonômicos
- 🎯 **Mapeamento assistido** para taxonomias globais (existentes ou novas)
- 👀 **Pré-visualização automática** do que será criado/atualizado
- ⚡ **Auto-mapeamento inteligente** com sugestões baseadas em similaridade
- 🔄 **Atualização determinística** de variações (sempre executada)
- 📊 **Logs estruturados** com correlação de operações
- 🎛️ **APIs REST e CLI** para automação
- 📱 **Interface responsiva** com recuperação de erros

## 🚀 Novidades da versão 0.3.0

### ✅ Melhorias da UX
- **Visibilidade condicional**: Botão aparece apenas quando necessário
- **Seleção inline**: "Criar novo termo" direto no select, sem campos manuais
- **Dry-run automático**: Pré-visualização executa automaticamente
- **Recuperação de erros**: Interface com retry em caso de falhas

### 🏗️ Simplificação arquitetural
- **Comportamento determinístico**: Sempre atualiza variações
- **Remoção de complexidade**: Sem templates, backups ou flags comportamentais
- **Única configuração**: Toggle de logging

### 🔧 Melhorias técnicas
- **Auto-mapeamento**: Sugestões baseadas em algoritmo Levenshtein
- **Logging granular**: Eventos detalhados para debug
- **Correlação**: IDs únicos para rastrear operações relacionadas
- **Consistência**: Mesma lógica entre dry-run e apply

## 📋 Requisitos

- **WordPress**: 6.4+
- **WooCommerce**: 8.6+ 
- **PHP**: 8.1+

## 🛠️ Instalação

1. Faça upload da pasta do plugin para `wp-content/plugins/`
2. Ative em **Plugins** > **Local 2 Global Attribute Mapper**
3. Configure se necessário em **Configurações** > **Local2Global**

## 🎮 Uso básico

### Interface Admin

1. Edite um produto no WooCommerce
2. Na aba **Atributos**, clique em **"Mapear atributos locais → globais"**
   - ⚠️ O botão só aparece se houver atributos locais
3. Siga o assistente de 5 etapas:
   - **Descoberta**: Visualize atributos locais detectados
   - **Seleção**: Escolha taxonomia global (existente ou nova)
   - **Mapeamento**: Associe valores a termos (com auto-sugestões)
   - **Pré-visualização**: Revise o que será criado/atualizado
   - **Aplicação**: Execute e acompanhe o progresso

### CLI

```bash
# Mapeamento básico
wp local2global map --product=123 --attr="Cor:pa_cor" --term="Azul:azul"

# Dry-run (pré-visualização)
wp local2global map --product=123 --attr="Cor:pa_cor" --term="Azul:azul" --dry-run

# Resync de variações específico
wp local2global variations resync --product=123 --taxonomy=pa_cor
```

### REST API

```bash
# Descobrir atributos locais
GET /wp-json/local2global/v1/discover?product_id=123

# Dry-run
POST /wp-json/local2global/v1/map
{
  "product_id": 123,
  "mode": "dry-run", 
  "mapping": [{
    "local_attr": "Cor",
    "target_tax": "pa_cor",
    "create_attribute": false,
    "terms": [{"local_value": "Azul", "term_slug": "azul", "create": false}]
  }]
}

# Aplicar
POST /wp-json/local2global/v1/map
{
  "product_id": 123,
  "mode": "apply",
  "mapping": [...]
}
```

## 🔧 Configuração

### Único toggle disponível

**Configurações** > **Local2Global** > **Ativar logs**

- ✅ **Habilitado**: Logs detalhados em debug.log
- ❌ **Desabilitado**: Apenas logs de erro críticos

### Constantes WordPress

```php
// Forçar logs (sobrescreve configuração)
define('L2G_DEBUG', true);
```

## 📝 Estrutura de logs

### Eventos principais

```
discover.start / discover.end - Descoberta de atributos
dry_run.start / dry_run.end - Pré-visualização
apply.start / apply.end - Aplicação
```

### Eventos granulares

```
dry_run.attribute.start - Início processamento atributo
dry_run.term.existing - Termo já existe  
dry_run.term.create - Termo será criado
dry_run.term.missing - Termo obrigatório não existe
apply.attribute.summary - Resumo por atributo aplicado
```

### Correlação

Todos os logs incluem `corr_id` para rastrear operações relacionadas:

```
[2025-09-22 10:30:15] apply.start {"corr_id": "abc123", "product_id": 456}
[2025-09-22 10:30:16] apply.attribute.summary {"corr_id": "abc123", "taxonomy": "pa_cor"}  
[2025-09-22 10:30:17] apply.end {"corr_id": "abc123", "success": true}
```

## 🗑️ Breaking Changes (0.3.0)

### Removidos completamente
- ❌ Templates reutilizáveis
- ❌ Sistema de backup/rollback  
- ❌ Hidratação e inferência agressiva
- ❌ Opções comportamentais (auto_create_terms, update_variations, etc.)
- ❌ Configurações globais complexas

### APIs depreciadas
- ❌ `term_name` em REST/CLI (use auto-derivação)
- ❌ `save_template` em REST/CLI (removido)
- ❌ Flags comportamentais em CLI (removidos)

> ⚠️ APIs antigas geram logs `*.deprecated_fields` e são ignoradas

## 🐛 Solução de problemas

### Botão não aparece
- ✅ Verifique se produto tem atributos locais (não-taxonômicos)
- ✅ Confirme que o produto não tem apenas atributos `pa_*`

### Dry-run trava em "Calculando..."
- ✅ Aguarde auto-execução (3-5 segundos)
- ✅ Use botão "Tentar novamente" se necessário
- ✅ Verifique logs para erros específicos

### Falsos erros "termo missing"
- ✅ Versão 0.3.0 corrige inconsistência dry-run vs apply
- ✅ Ambos usam `get_term_by()` para verificação

### Performance com muitos produtos
- ✅ Use CLI para operações em lote
- ✅ Desative logs em produção se desnecessário
- ✅ Considere cache de termos via filtros WP

## 📚 Desenvolvimento

### Arquitetura

```
src/
├── Services/           # Lógica de negócio
│   ├── Discovery_Service.php    # Detecta atributos locais  
│   ├── Mapping_Service.php      # Orquestra dry-run/apply
│   ├── Term_Service.php         # Gestão de termos/taxonomias
│   └── Variation_Service.php    # Atualização de variações
├── Admin/             # Interface WP Admin
│   ├── Settings.php   # Página de configurações  
│   └── UI.php         # Modal e scripts
├── Rest/              # API REST
├── Cli/               # Comandos WP-CLI
└── Utils/             # Utilitários
    ├── Logger.php     # Logging estruturado
    └── Value_Normalizer.php  # Normalização de valores
```

### Extensibilidade

```php
// Customizar auto-mapeamento
add_filter('local2global_similarity_threshold', function($threshold) {
    return 0.8; // Mais restritivo (padrão: 0.5)
});

// Interceptar eventos de logging  
add_action('local2global_log', function($event, $context, $corr_id) {
    // Custom logging logic
}, 10, 3);

// Modificar normalização de valores
add_filter('local2global_normalize_value', function($normalized, $original) {
    return custom_normalize($original);
}, 10, 2);
```

## 📞 Suporte

- 🐛 **Issues**: [GitHub Issues](#)
- 📖 **Documentação**: [Wiki do projeto](#)  
- 💬 **Discussões**: [GitHub Discussions](#)

## 📄 Licença

GPL v2 or later - veja [LICENSE](LICENSE) para detalhes.

---

**Versão 0.3.0** - Simplificado, determinístico e confiável ✨