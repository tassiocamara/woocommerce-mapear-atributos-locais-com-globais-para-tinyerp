# Changelog

## 0.2.1 (unreleased)
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
