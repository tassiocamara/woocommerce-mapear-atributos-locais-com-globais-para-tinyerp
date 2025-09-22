# Local 2 Global Attribute Mapper

Plugin do WooCommerce desenvolvido pela Evolury LTDA para mapear atributos locais de produtos para atributos globais (`pa_*`).

## Principais recursos

- Descoberta automática de atributos locais no produto.
- Mapeamento assistido para atributos globais existentes ou criação de novos atributos/termos.
- Pré-visualização (dry-run) com identificação do que será criado/atualizado.
- Conversão das variações com preservação de estoque/SKU/preço.
- Criação de templates reutilizáveis para aplicar em produtos futuros.
- Suporte a CLI (`wp local2global map`) e endpoint REST (`/local2global/v1/map`).

## Instalação

1. Copie a pasta do plugin para `wp-content/plugins/local2global-attribute-mapper`.
2. Ative em **Plugins** > **Local 2 Global Attribute Mapper**.

## Uso básico

1. Abra um produto no painel do WooCommerce.
2. Na aba **Atributos**, clique em **Mapear atributos locais → globais**.
3. Siga o assistente para escolher o atributo global e realizar o mapeamento dos valores.
4. Revise a pré-visualização e aplique.

## Linha de comando

```
wp local2global map --product=123 --attr="Cor:pa_cor" --term="Azul:azul" --create-missing=1 --apply-variations=1
```

## Requisitos

- WordPress 6.4+
- WooCommerce 8.6+
- PHP 8.1+

## Debug & Teste

### Configuração de Logs

Você pode ativar/desativar os logs do plugin em:

`Configurações > Local2Global > Ativar logs`

Implementação:
- Option: `local2global_logging_enabled` (`yes`|`no`, default `yes`)
- Checkbox envia sempre um hidden `no` + `yes` quando marcado, garantindo persistência correta.
- Logger atualiza dinamicamente a flag em runtime via hook `update_option_local2global_logging_enabled`.

Para forçar via código (ex.: mu-plugin):
```php
update_option( 'local2global_logging_enabled', 'no' ); // Desliga
update_option( 'local2global_logging_enabled', 'yes' ); // Religa
```

Observação: Erros internos críticos do WooCommerce podem continuar sendo registrados pelo core, mesmo com os logs do plugin desativados.

### Endpoints REST

Descoberta de atributos locais de um produto:

```bash
curl -s -H "Accept: application/json" -H "Cookie: $(wp user session-token 1 2>/dev/null || echo 'USE_SESSAO_ADMIN')" \
	"https://seusite.test/wp-json/local2global/v1/discover?product_id=123" | jq .
```

Aplicação do mapeamento (exemplo com um atributo):

```bash
curl -s -X POST "https://seusite.test/wp-json/local2global/v1/map" \
	-H "Content-Type: application/json" \
	-H "Cookie: (sessao admin)" \
	-d '{
		"product_id": 123,
		"mode": "apply",
		"mapping": [
			{
				"local_attr": "Cor",
				"local_label": "Cor",
				"target_tax": "pa_cor",
				"create_attribute": true,
				"terms": [
					{ "local_value": "Azul", "term_slug": "azul", "create": true },
					{ "local_value": "Vermelho", "term_slug": "vermelho", "create": true }
				]
			}
		],
		"options": { "auto_create_terms": true, "update_variations": true, "create_backup": true }
	}' | jq .
```

Para dry-run (pré-visualização), use `"mode": "dry_run"`.

### Logs

Os logs são gravados no logger do WooCommerce (`WooCommerce > Status > Logs`). Busque por entradas com `source=local2global`.

Principais marcadores de log:

| Evento | Descrição |
|--------|-----------|
| Evento | Descrição |
|--------|-----------|
| `apply.start` | Início da aplicação de um lote de atributos |
| `apply.options` | Opções normalizadas (auto_create_terms, update_variations, create_backup) |
| `attributes.snapshot.before` / `attributes.snapshot.after` | Estado bruto dos atributos antes/depois |
| `attribute.process.start` / `attribute.process.end` | Processamento de um atributo local específico |
| `replace_attribute.scan` | Lista candidatos (filtra já taxonômicos) para substituição do atributo local |
| `replace_attribute.success` | Substituição concluída para taxonomia alvo |
| `replace_attribute.not_found` | Atributo local não localizado pelo nome normalizado |
| `term.created` | Termo criado na taxonomia alvo |
| `term.reuse` | Termo existente reutilizado (source: lookup/cache_or_lookup) |
| `variation.slug_map_missing` | Valor de variação não mapeado no slug_map |
| `variation.update.summary` | Resultado por atributo (updated, skipped, total_variations, reasons, hydrate_mode) |
| `apply.term_assignment` | Atribuição de termos ao produto principal |
| `apply.completed` | Resumo final (terms, variações) |
| `variation.resync.start` | Início de reprocessamento isolado de variações |
| `variation.resync.summary` | Agregado de todas as taxonomias (updated, skipped, total_variations, reasons) |
| `variation.resync.completed` | Detalhes por taxonomia no resync |
| `permission.denied` | Falha de permissão em endpoint REST |

### Diagnóstico de Falhas Comuns

- Atributo não substituído: Verifique `replace_attribute.scan` e se o nome normalizado do local aparece; caso não, o nome no produto pode estar diferente (maiúsculas, espaços, acentos).
- Termos não criados: Confirme se `auto_create_terms` ou `create` está marcado no mapeamento; veja logs de `Termo criado.`.
- Variações não atualizadas: Cheque `variation.slug_map_missing` e valide se os valores originais contêm acentos ou variações de grafia não normalizadas.
- 403 em REST: Usuário precisa de `manage_woocommerce` ou `edit_products`.

### WP-CLI

Execução básica (exemplo conceitual – implementação CLI pode variar):

```bash
wp local2global map --product=123 \
	--attr="Cor:pa_cor" \
	--term="Azul:azul" \
	--term="Vermelho:vermelho" \
	--create-missing=1 --apply-variations=1 --backup=1
```

### Subcomando de Simulação

Cria automaticamente um produto variável de teste com atributos locais e executa o fluxo de mapeamento (dry-run ou apply):

```bash
wp local2global simulate \
	--attr="Cor:pa_cor" \
	--val="Azul:azul" --val="Vermelho:vermelho" \
	--variations=2 \
	--dry-run=0
```

Parâmetros:
- `--attr local:pa_slug` (repetível) – cria atributo local e mapeia para taxonomia alvo (criando-a se necessário).
- `--val valor_local:slug_global` (repetível) – valores e slugs alvo (criados se não existirem).
- `--variations N` – quantas variações gerar (baseadas no primeiro atributo).
- `--dry-run=1` – só simula sem aplicar.

Após execução, verifique logs com `source=local2global` para detalhes (`apply.completed`).

### Reprocessar Variações (CLI)

Reaplica apenas o ajuste das variações para taxonomias já mapeadas:

```bash
wp local2global variations-update --product=123
wp local2global variations-update --product=123 --tax=pa_cor --tax=pa_tamanho
```

### Endpoint REST de Reprocessamento de Variações

`POST /wp-json/local2global/v1/variations/update`

Body exemplo:
```json
{
	"product_id": 123,
	"taxonomies": ["pa_cor", "pa_tamanho"]
}
```
Resposta:
```json
{
	"ok": true,
	"corr_id": "...",
	"result": {
		"product_id":123,
		"taxonomies": {
			"pa_cor": {"updated":1, "skipped":2, "reasons": {"missing_source_meta":1,"already_ok":1,"no_slug_match":0}}
		},
		"aggregate": {"updated":1,"skipped":2,"reasons":{"missing_source_meta":1,"already_ok":1,"no_slug_match":0}}
	}
}
```

### Estratégia de Normalização

Todos os valores locais e de variações passam por normalização unificada (classe `Value_Normalizer`) removendo acentos e diacríticos para casar com slugs de termos.

### Interpretação de `variation.update.summary` e `variation.resync.summary`

Campos principais:
- `updated`: Quantidade de variações ajustadas.
- `skipped`: Variações ignoradas (ver reasons).
- `total_variations`: Total de variações examinadas para aquela taxonomia.
- `hydrate_mode` (boolean em variation.update.summary): Indica se o modo de hidratação estava ativo.

Razões (`reasons`):
- `missing_source_meta`: Não havia meta de origem; se `hydrate_mode=true`, plugin pode tentar inferir a partir de slug já aplicado ou título da variação.
- `no_slug_match`: Valor normalizado não correspondeu a nenhum slug mapeado.
- `already_ok`: Já continha meta target e nenhuma meta local.
- `hydrated`: (Somente quando `hydrate_variations` ativo) Variação atualizada via inferência sem meta local original.

`variation.resync.summary` agrega contagens somando `updated`, `skipped`, `total_variations` e razões de todas as taxonomias processadas.

### Modo de Hidratação de Variações (`hydrate_variations`)

Quando ativado (via `options.hydrate_variations` no endpoint `/map`, parâmetro `hydrate_variations` em `/variations/update`, ou flags CLI `--hydrate-variations=1` / `--hydrate=1`), o serviço tenta recuperar variações que perderam a meta local original:
1. Usa valor já existente em `attribute_pa_*` (se presente) para mapear novamente.
2. Caso vago, tenta extrair possível valor do título (`post_title`) da variação.
3. Normaliza e compara com o `slug_map` ativo; em caso de sucesso aplica a meta target e incrementa `hydrated`.

Isso reduz `missing_source_meta` após rodadas de limpeza onde a meta local foi removida antes da migração completa.

### Modo de Inferência Agressiva (`aggressive_hydrate_variations`)

Quando habilitado junto ao `hydrate_variations`, o plugin tenta inferir o termo correto mesmo quando há múltiplos termos possíveis (multi-termos) analisando:

1. Título da variação (`post_title`): correspondência de palavra inteira (normalizada) com nomes originais dos termos.
2. SKU (`_sku`): presença de fragmentos normalizados que batam com termos.
3. Padrões numéricos simples (ex: `180/90`, `42`, `44`) presentes no título/SKU que correspondam a termos numéricos.

Regras de decisão:
- Se exatamente 1 candidato é encontrado: atribui e contabiliza em `inferred`.
- Se mais de 1 candidato é encontrado: não aplica (para evitar erro) e contabiliza em `ambiguous_inference`.
- Se nenhum candidato: permanece como `missing_source_meta`.

Novas razões adicionadas em `reasons`:
- `inferred`: termo aplicado via inferência agressiva multi-termos.
- `ambiguous_inference`: múltiplos candidatos conflitantes; nenhuma atribuição feita.

Como habilitar:
- Endpoint `/map`: incluir em `options` `{ "aggressive_hydrate_variations": true }` (requer também `update_variations` e preferencialmente `hydrate_variations`).
- Endpoint `/variations/update`: enviar `aggressive_hydrate_variations=1` no corpo JSON.
- WP-CLI `map`: `--aggressive-hydrate-variations=1`.
- WP-CLI `variations-update`: `--aggressive=1`.

Observação: a inferência agressiva só roda quando há mais de um termo possível no `slug_map`; para caso de termo único a lógica básica já cobre.


