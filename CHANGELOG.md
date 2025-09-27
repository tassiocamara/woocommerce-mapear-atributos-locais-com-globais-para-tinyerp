# Changelog

## 0.5.0 (2025-09-27)

### 🐛 CORREÇÕES CRÍTICAS
- **Fix: Campos de seleção na etapa "Mapear Valores para Termos"**
  - Resolvido erro 400 ao tentar carregar termos de atributos que serão criados
  - Implementada verificação prévia para `map.create_attribute = true`
  - Melhorado tratamento de erro na função `loadTermOptions()` com logging detalhado
  - Configuração automática de termos para criação quando atributo é novo
  - Garantia de que campos de seleção sempre tenham opção "Criar novo termo"

### ✨ MELHORIAS DE UX
- **Ocultação automática de botão após mapeamento**
  - Implementada função `hideButtonAfterMapping()` para feedback visual imediato
  - Botão é ocultado automaticamente após processamento bem-sucedido
  - Eliminada necessidade de refresh da página para atualizar interface
  - Prevenção de reprocessamento acidental de produtos já mapeados
  - Mantém consistência com lógica PHP existente de ocultação

### 🎨 INTERFACE LIMPA
- **Remoção de todos os ícones/emojis da interface**
  - Removidos ícones decorativos (✨, 🔗, ➕) dos campos de seleção
  - Removidas setas (←, →) dos botões de navegação
  - Removidos emojis de status dos arquivos de teste
  - Interface mais limpa, profissional e acessível
  - Melhor compatibilidade universal sem dependência de suporte a emojis

### 🔧 MELHORIAS TÉCNICAS
- Aprimorados event listeners para configuração automática de termos
- Implementado fallback adequado para atributos que serão criados
- Otimizações de performance na renderização de seletores
- Melhor sincronização entre frontend e backend

## 0.4.0 (2025-09-23)

### 🚀 REFATORAÇÃO COMPLETA
- **Otimização de performance**: Variation_Service reduzido de 972 → 769 linhas (-21%)
- **Limpeza de código**: Removidos 20+ arquivos temporários e de teste desnecessários
- **Métodos simplificados**: Eliminadas validações excessivas e logs de debug redundantes
- **Teste unificado**: Criado `tests/run-tests.php` único substituindo múltiplos arquivos de teste

### ✨ MELHORIAS DE PERFORMANCE
- **Métodos removidos**: `validate_final_persistence()`, `infer_missing_value()` (95 linhas)
- **Verificação otimizada**: `verify_immediate_persistence()` simplificado (49→22 linhas)
- **Persistência otimizada**: `force_individual_variation_save()` reduzido (84→54 linhas)
- **Cache management**: Funções WordPress protegidas para ambiente de teste

### 🔧 MELHORIAS TÉCNICAS
- **Taxa de sucesso**: 95.0% nos testes (19/20 verificações)
- **Namespace fixes**: Correções de namespace para funções WordPress em ambiente isolado
- **Error handling**: Proteções adicionais para execução em ambiente de teste
- **Code quality**: Todas as verificações de sintaxe PHP passando

### 📚 DOCUMENTAÇÃO
- **REFACTORING_SUMMARY.md**: Resumo completo da refatoração
- **Teste completo**: Combina verificação de arquivos + testes funcionais + análise de performance
- **Performance metrics**: Análise de tamanho de código e otimizações aplicadas

## 0.3.0 (2025-09-22)

### 🔥 BREAKING CHANGES
- **Removido completamente**: Templates reutilizáveis, backup/rollback, inferência agressiva, opções globais
- **Removidos serviços**: `Templates_Service`, `Rollback_Service` 
- **Removidas opções REST/CLI**: `auto_create_terms`, `update_variations`, `create_backup`, `hydrate_variations`, `aggressive_hydrate_variations`, `save_template_default`, `term_name`, `save_template`
- **Comportamento**: Plugin agora sempre atualiza variações automaticamente (sem configuração)

### ✨ NOVAS FUNCIONALIDADES
- **UI Simplificada**: Matriz de termos agora com seleção inline "Criar novo termo" em vez de campos manuais
- **Auto-mapeamento**: Sugestões automáticas de termos baseadas em similaridade (Levenshtein)
- **Visibilidade condicional**: Botão "Mapear atributos" só aparece quando produto tem atributos locais
- **Dry-run automático**: Pré-visualização executa automaticamente ao entrar na etapa
- **Recuperação de erros**: Interface com retry automático em caso de falhas na pré-visualização

### 🛠 MELHORIAS TÉCNICAS
- **Comportamento determinístico**: Sempre processa variações, remove dependência de flags comportamentais
- **Logging granular**: Novos eventos `dry_run.attribute.start/end`, `dry_run.term.existing/create/missing`, `apply.attribute.summary`
- **Consistência dry-run/apply**: Mesmo método `get_term_by()` para verificação de existência de termos
- **Correlação de logs**: IDs únicos para rastrear operações relacionadas
- **Discovery Service**: Detecção inteligente de atributos locais (não-taxonômicos)

### 🐛 CORREÇÕES CRÍTICAS
- **Dry-run travado**: Resolvido problema de UI ficando em "Calculando pré-visualização…"
- **Falsos erros**: Corrigido dry-run mostrando termos "missing" que existiam
- **Auto-trigger**: Pré-visualização dispara automaticamente sem clique manual
- **Preparação de termos**: Termos não selecionados automaticamente marcados para criação

### 🗑 REMOVIDO (Depreciação)
- Templates reutilizáveis e sistema de backup
- Opções de comportamento configuráveis  
- Hidratação e inferência agressiva de variações
- Campos manuais para nome/slug de termos na UI
- Configurações globais (exceto logging)

### 📝 LOGS DE DEPRECIAÇÃO
- REST/CLI registram `apply.deprecated_fields`, `dry_run.deprecated_fields` quando recebem campos antigos
- Campos depreciados são ignorados silenciosamente com log para diagnóstico

### 🎯 CONFIGURAÇÃO
- **Única opção restante**: `local2global_logging_enabled` (habilita/desabilita logs)
- **Remoção**: Página de configurações complexa substituída por toggle simples

### 📚 DOCUMENTAÇÃO
- README completamente reescrito para refletir simplificação
- CHANGELOG detalhado com breaking changes
- Documentação de APIs atualizadas

---

## 0.2.1
- Feat: Configurações globais persistentes para opções de mapeamento: `auto_create_terms`, `update_variations`, `create_backup`, `hydrate_variations`, `aggressive_hydrate_variations`, `save_template_default`.
- Feat: Página de Configurações consolidada em `Configurações > Local2Global` com bloco de ajuda contextual explicando quando usar cada opção.
- Feat: `normalize_options` agora aplica precedence `request > global default` garantindo consistência entre REST, UI e CLI.
- Docs: README atualizado com seção de "Configurações Globais" e regras de precedência.
- Internal: Wrapper seguro para acesso a `get_option` fora de ambiente WP em testes estáticos.

## 0.2.0
- Feat: Inferência agressiva de termos para variações (heurísticas título/SKU/padrões numéricos) com limites configuráveis via filtros.
- Feat: Hidratação de variações quando metadado local foi removido (fallback seguro).
- Feat: Novos motivos de resultado de variação (`hydrated`, `inferred`, `ambiguous_inference`).
- Feat: Métrica `updated_pct` por taxonomia e agregado em `variation.update.summary` e UI.
- Feat: Cache interno de termos/slug_map para reduzir consultas em resync de variações.
- Feat: Checkboxes de "Hidratar variações" e "Inferência agressiva" + tabela de resumo pós-aplicação na interface admin.
- Improvement: Tratamento silencioso de erros `term_exists` convertendo em `term.reuse` (fonte `term_exists`).
- Improvement: Deduplicação de logs `term.reuse` (lookup/cache/term_exists) evitando ruído.
- Docs: README atualizado com novas opções (REST, CLI, UI), motivos e métricas.
- Internal: Estrutura de logging enriquecida com `hydrate_mode`, `aggressive_mode` e cálculo percentual.

## 0.1.1
- Added: HPOS compatibility declaration.
- Improved: REST and admin UI now expose root-cause errors with correlation IDs, structured logging, payload validation and status codes.

## 0.1.0
- Initial release
- Core functionality for mapping local attributes to global taxonomies
- WooCommerce integration with product attribute management
- REST API endpoints for automated mapping
- CLI commands for bulk operations
- Basic logging and error handling