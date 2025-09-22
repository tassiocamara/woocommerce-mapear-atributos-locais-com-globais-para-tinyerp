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

