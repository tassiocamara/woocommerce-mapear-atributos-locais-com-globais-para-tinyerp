# Changelog

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
