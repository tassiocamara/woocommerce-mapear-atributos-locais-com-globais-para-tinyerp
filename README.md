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
| `apply.start` | Início da aplicação de um lote de atributos |
| `attribute.process.start` / `end` | Processamento de um atributo local específico |
| `replace_attribute.scan` | Lista de candidatos encontrados ao substituir o atributo |
| `replace_attribute.success` | Substituição concluída para taxonomia alvo |
| `variation.slug_map_missing` | Valor de variação sem correspondência no novo atributo global |
| `apply.term_assignment` | Atribuição de termos ao produto (produto principal) |
| `apply.completed` | Resumo final (termos criados, existentes, variações) |
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
	"result": { "product_id":123, "taxonomies": { "pa_cor":{"updated":1,"skipped":2} } }
}
```

### Estratégia de Normalização

Todos os valores locais e de variações passam por normalização unificada (classe `Value_Normalizer`) removendo acentos e diacríticos para casar com slugs de termos.


