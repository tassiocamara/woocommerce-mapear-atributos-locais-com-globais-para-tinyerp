# Local 2 Global Attribute Mapper - Documentação Completa

**Plugin WooCommerce para mapeamento inteligente de atributos locais para taxonomias globais**

[![Version](https://img.shields.io/badge/version-0.3.0-blue.svg)](CHANGELOG.md)
[![WordPress](https://img.shields.io/badge/WordPress-6.4%2B-blue.svg)](https://wordpress.org/)
[![WooCommerce](https://img.shields.io/badge/WooCommerce-8.6%2B-purple.svg)](https://woocommerce.com/)
[![PHP](https://img.shields.io/badge/PHP-8.1%2B-787CB5.svg)](https://php.net/)

---

## 📖 Índice

1. [Visão Geral](#-visão-geral)
2. [Funcionalidades](#-funcionalidades)
3. [Requisitos e Instalação](#-requisitos-e-instalação)
4. [Guia de Uso](#-guia-de-uso)
5. [APIs e Automação](#-apis-e-automação)
6. [Desenvolvimento](#-desenvolvimento)
7. [Resolução de Problemas](#-resolução-de-problemas)
8. [Changelog](#-changelog)

---

## 🎯 Visão Geral

O **Local 2 Global Attribute Mapper** é um plugin WooCommerce que automatiza a conversão de atributos locais (não-taxonômicos) em taxonomias globais (`pa_*`), com funcionalidades inteligentes de mapeamento e atualização automática de variações.

### ✨ Principais Características

- 🧠 **Seleção Automática Inteligente**: Algoritmo que detecta correspondências entre atributos locais e globais
- 🔍 **Descoberta Automática**: Identifica atributos locais não-taxonômicos em produtos variáveis
- 🎯 **Mapeamento Assistido**: Interface visual para associar atributos locais a taxonomias globais
- 👀 **Pré-visualização Automática**: Mostra exatamente o que será criado/modificado antes da aplicação
- ⚡ **Auto-mapeamento de Termos**: Sugestões inteligentes baseadas em similaridade de strings
- 🔄 **Atualização Determinística**: Sempre sincroniza variações após mapeamento
- 📊 **Logging Estruturado**: Logs detalhados com correlação de operações
- 🎛️ **APIs REST e CLI**: Integração para automação
- 📱 **Interface Responsiva**: UX moderna com recuperação de erros

---

## 🚀 Funcionalidades

### 🎯 **Seleção Automática de Atributos Globais**

**Novidade da versão 0.3.0** - O plugin agora seleciona automaticamente atributos globais correspondentes:

#### Como Funciona:
- **Correspondência Exata**: `"Cor"` → `"pa_cor"` selecionado automaticamente
- **Correspondência por Similaridade**: Algoritmo com threshold de 70% para matches parciais
- **Criação Automática**: Fallback para criar novos atributos quando não há correspondência
- **Preserva Seleções Manuais**: Não sobrescreve escolhas do usuário

#### Algoritmo de Matching:
1. **Normalização**: Remove acentos, converte para minúsculas, limpa caracteres especiais
2. **Comparação Exata**: Prioriza matches 100% idênticos em nome ou slug
3. **Similaridade**: Calcula distância entre strings usando algoritmo avançado
4. **Seleção**: Se similaridade > 70%, seleciona automaticamente; senão marca para criação

#### Exemplos Práticos:
```
"Cor" → pa_cor (seleção automática)
"Tamanho" → pa_tamanho (seleção automática)
"Material" → pa_material (criação automática se não existe)
"Cores" → pa_cor (similaridade 85%, seleção automática)
```

### 🔍 **Descoberta Automática**

O plugin identifica automaticamente produtos com atributos locais:

- **Produtos Variáveis**: Detecta atributos não-taxonômicos
- **Análise de Variações**: Examina atributos em todas as variações
- **Filtragem Inteligente**: Ignora atributos já taxonômicos
- **Relatório Detalhado**: Lista todos os atributos encontrados

### 🎯 **Mapeamento Assistido**

Interface visual em 4 etapas:

1. **Descoberta**: Lista atributos locais encontrados
2. **Seleção de Atributos**: Mapeia para taxonomias globais (com seleção automática)
3. **Mapeamento de Termos**: Associa valores locais a termos globais
4. **Pré-visualização**: Mostra mudanças antes da aplicação

### 🔄 **Auto-mapeamento de Termos**

Algoritmo inteligente para sugerir correspondências:

- **Normalização de Strings**: Remove acentos, caracteres especiais
- **Correspondência Exata**: Prioriza matches idênticos
- **Similaridade Levenshtein**: Calcula distância entre strings
- **Threshold Configurável**: Aceita correspondências > 50% de similaridade
- **Criação Automática**: Sugere criar novos termos quando necessário

### 📊 **Logging Estruturado**

Sistema completo de logs para auditoria:

```json
{
  "timestamp": "2025-09-23T02:31:30+00:00",
  "level": "INFO",
  "message": "map.request_received",
  "context": {
    "corr_id": "l2g_68d20682294ab6.63320388",
    "endpoint": "map",
    "product_id": 2198,
    "mode": "dry-run",
    "mapping": [...]
  }
}
```

**Tipos de Eventos**:
- `map.request_received`: Início de operação
- `dry_run.attribute.start/end`: Processamento de atributos
- `dry_run.term.create/existing`: Análise de termos
- `apply.start/complete`: Aplicação de mudanças
- `variation.update`: Sincronização de variações

### 🛡️ **Validação e Prevenção de Erros**

- **Validação Obrigatória**: Impede navegação sem seleção completa
- **Mensagens Claras**: Feedback informativo sobre problemas
- **Recuperação de Erros**: Interface com retry automático
- **Dry-run Obrigatório**: Sempre mostra pré-visualização antes de aplicar

---

## 🛠️ Requisitos e Instalação

### 📋 Requisitos Mínimos

- **WordPress**: 6.4 ou superior
- **WooCommerce**: 8.6 ou superior  
- **PHP**: 8.1 ou superior
- **MySQL**: 5.6 ou superior

### 📦 Instalação

#### Via WordPress Admin:
1. Faça download do arquivo ZIP do plugin
2. Acesse **Plugins** > **Adicionar Novo** > **Enviar Plugin**
3. Selecione o arquivo ZIP e clique **Instalar Agora**
4. Ative o plugin na tela de plugins

#### Via FTP:
1. Extraia o arquivo ZIP
2. Faça upload da pasta para `wp-content/plugins/`
3. Ative em **Plugins** > **Local 2 Global Attribute Mapper**

#### Via WP-CLI:
```bash
wp plugin install local2global-attribute-mapper.zip --activate
```

### ⚙️ Configuração Inicial

Acesse **Configurações** > **Local2Global** para:

- **Habilitar/Desabilitar Logging**: Controla se eventos são registrados em logs
- **Configurar Permissões**: Define quem pode usar o plugin (padrão: `manage_woocommerce`)

---

## 📱 Guia de Uso

### 🎮 Interface Visual (Recomendado)

#### 1. Acesso ao Plugin
- Vá para **WooCommerce** > **Produtos**
- Abra um produto variável
- Procure pelo botão **"Mapear atributos"** (aparece apenas se há atributos locais)

#### 2. Fluxo de Mapeamento

**Etapa 1: Descoberta**
- Lista automaticamente todos os atributos locais encontrados
- Mostra quantos valores únicos cada atributo possui
- Clique **"Continuar"** para prosseguir

**Etapa 2: Seleção de Atributos Globais**
- **Seleção Automática**: Plugin seleciona automaticamente atributos correspondentes
- **Seleção Manual**: Pode alterar as seleções automáticas se necessário
- **Opções disponíveis**:
  - Taxonomias globais existentes (ex: `pa_cor`, `pa_tamanho`)
  - **"Criar novo atributo"**: Para criar nova taxonomia global
- **Validação**: Sistema impede continuar sem seleções completas

**Etapa 3: Mapeamento de Termos**
- **Auto-mapeamento**: Plugin sugere correspondências inteligentes
- **Para cada valor local**:
  - Selecione termo global existente, ou
  - Escolha **"Criar novo termo"** com nome derivado do valor local
- **Sugestões baseadas em similaridade**: Correspondências > 50% são sugeridas automaticamente

**Etapa 4: Pré-visualização (Dry-run)**
- **Execução Automática**: Análise roda automaticamente ao entrar na etapa
- **Relatório Detalhado**:
  - Quantos atributos serão criados/modificados
  - Quantos termos serão criados vs. reutilizados
  - Quantas variações serão atualizadas
  - Estimativa de tempo de execução
- **Aplicar Mudanças**: Clique no botão para executar definitivamente

#### 3. Resultado e Feedback
- **Progresso em Tempo Real**: Acompanhe o processamento
- **Relatório Final**: Resumo completo das operações realizadas
- **Logs Detalhados**: Disponíveis para auditoria (se habilitado)

### 🎛️ API REST

#### Endpoint Principal: `/wp-json/local2global/v1/map`

**Dry-run (Pré-visualização)**:
```bash
curl -X POST "https://site.com/wp-json/local2global/v1/map" \
  -H "Content-Type: application/json" \
  -H "X-WP-Nonce: NONCE_VALUE" \
  -d '{
    "product_id": 123,
    "mode": "dry-run",
    "mapping": [
      {
        "local_attr": "Cor",
        "target_tax": "pa_cor"
      },
      {
        "local_attr": "Tamanho", 
        "target_tax": "pa_tamanho"
      }
    ]
  }'
```

**Aplicação Real**:
```bash
curl -X POST "https://site.com/wp-json/local2global/v1/map" \
  -H "Content-Type: application/json" \
  -H "X-WP-Nonce: NONCE_VALUE" \
  -d '{
    "product_id": 123,
    "mode": "apply",
    "mapping": [
      {
        "local_attr": "Cor",
        "target_tax": "pa_cor"
      }
    ]
  }'
```

#### Endpoints Auxiliares:

**Descoberta de Atributos**:
```bash
GET /wp-json/local2global/v1/discover/123
```

**Listar Termos de Taxonomia**:
```bash
GET /wp-json/local2global/v1/terms/pa_cor
```

**Listar Taxonomias Globais**:
```bash
GET /wp-json/local2global/v1/taxonomies
```

### 🖥️ WP-CLI

#### Comandos Disponíveis:

**Descobrir Atributos**:
```bash
wp local2global discover --product-id=123
```

**Dry-run Via CLI**:
```bash
wp local2global map --product-id=123 --mode=dry-run --mapping='[
  {"local_attr":"Cor","target_tax":"pa_cor"},
  {"local_attr":"Tamanho","target_tax":"pa_tamanho"}
]'
```

**Aplicar Mapeamento**:
```bash
wp local2global map --product-id=123 --mode=apply --mapping='[
  {"local_attr":"Cor","target_tax":"pa_cor"}
]'
```

#### Opções Avançadas:

```bash
# Com logging detalhado
wp local2global map --product-id=123 --mode=apply --verbose

# Processamento em lote
wp local2global batch --products=123,456,789 --mapping-file=mapping.json

# Validação sem execução
wp local2global validate --product-id=123 --mapping-file=mapping.json
```

---

## 🔧 Desenvolvimento

### 🛠️ Ambiente de Desenvolvimento

O projeto inclui um ambiente profissional completo para desenvolvimento:

#### Dependências de Desenvolvimento:
- **WordPress Stubs**: Definições completas de funções e classes WordPress
- **WooCommerce Stubs**: API completa do WooCommerce
- **WP-CLI Stubs**: Comandos e interfaces WP-CLI
- **PHPStan**: Análise estática avançada
- **PHPStan WordPress Extension**: Regras específicas para WordPress

#### Configuração do VS Code:
```json
{
  "intelephense.stubs": [
    "wordpress", 
    "woocommerce", 
    "wp-cli"
  ],
  "intelephense.includePaths": [
    "vendor/php-stubs/wordpress-stubs",
    "vendor/php-stubs/woocommerce-stubs",
    "vendor/php-stubs/wp-cli-stubs"
  ]
}
```

**Benefícios**:
- ✅ **Zero erros PHP** no editor
- ✅ **Autocomplete completo** para WordPress/WooCommerce/WP-CLI  
- ✅ **Análise estática** com PHPStan
- ✅ **Documentação inline** para todas as funções

### 🚀 Scripts de Desenvolvimento

Use o script `./dev.sh` para tarefas comuns:

```bash
# Instalar dependências
./dev.sh install

# Verificar status do ambiente
./dev.sh status

# Executar testes
./dev.sh test

# Análise estática
./dev.sh analyse

# Limpeza do ambiente
./dev.sh clean
```

### 🧪 Estrutura de Testes

```
tests/
├── run-tests.php          # Runner principal de testes
├── stubs/
│   └── woocommerce.php    # Stubs para testes standalone
└── fixtures/              # Dados de teste
```

**Executar Testes**:
```bash
./dev.sh test
# ou
php tests/run-tests.php
```

**Cobertura Atual**: 34 asserções, 100% de sucesso

### 📁 Arquitetura do Código

```
src/
├── Plugin.php                 # Classe principal
├── Admin/
│   ├── Settings.php          # Configurações
│   └── UI.php                # Interface admin
├── Cli/
│   └── CLI_Command.php       # Comandos WP-CLI
├── Rest/
│   └── Rest_Controller.php   # API REST
├── Services/
│   ├── Discovery_Service.php # Descoberta de atributos
│   ├── Mapping_Service.php   # Lógica de mapeamento
│   ├── Term_Service.php      # Gerenciamento de termos
│   └── Variation_Service.php # Atualização de variações
├── Setup/
│   └── Activator.php         # Ativação do plugin
└── Utils/
    ├── Logger.php            # Sistema de logs
    └── Value_Normalizer.php  # Normalização de strings
```

### 🏗️ Padrões de Desenvolvimento

#### Nomenclatura:
- **Classes**: `PascalCase` (ex: `Mapping_Service`)
- **Métodos**: `snake_case` (ex: `get_product_attributes`)
- **Constantes**: `UPPER_CASE` (ex: `PLUGIN_VERSION`)
- **Hooks**: `local2global_*` (ex: `local2global_mapping_complete`)

#### Documentação:
- **PHPDoc obrigatório** para classes e métodos públicos
- **Inline comments** para lógica complexa
- **README atualizado** para mudanças de API

#### Testes:
- **Testes unitários** para services
- **Testes de integração** para controllers
- **Stubs mockados** para WordPress/WooCommerce
- **Cobertura > 80%** para código crítico

---

## 🛡️ Resolução de Problemas

### ❗ Problemas Comuns

#### 1. **Botão "Mapear atributos" não aparece**

**Causa**: Produto não tem atributos locais ou não é variável

**Solução**:
1. Verifique se o produto é do tipo "Produto variável"
2. Confirme que há atributos não-taxonômicos configurados
3. Certifique-se que os atributos são usados para variações

#### 2. **Erro: "Call to undefined method WC_Product_Attribute::set_taxonomy()"**

**Causa**: Plugin usando método inexistente (corrigido na v0.3.0)

**Solução**:
1. Atualize para versão 0.3.0 ou superior
2. Se persistir, desative e reative o plugin
3. Verifique compatibilidade do WooCommerce

#### 3. **Seleção automática não funciona**

**Causa**: Nomes muito diferentes ou atributos não cadastrados

**Solução**:
1. Verifique se taxonomias globais existem
2. Nomes devem ter > 70% de similaridade
3. Use seleção manual se necessário

#### 4. **Variações não atualizam após mapeamento**

**Causa**: Erro na sincronização ou permissões

**Solução**:
1. Verifique logs do WordPress
2. Confirme permissões de usuário
3. Execute novamente o mapeamento

#### 5. **Erro de memory limit em produtos grandes**

**Causa**: Muitas variações ou atributos complexos

**Solução**:
```php
// wp-config.php
ini_set('memory_limit', '512M');
```

### 🔍 Debug e Logs

#### Habilitar Logging:
1. Vá para **Configurações** > **Local2Global**
2. Marque **"Habilitar Logging"**
3. Logs aparecem em **WooCommerce** > **Status** > **Logs**

#### Informações de Debug:
```php
// Adicionar ao wp-config.php para debug
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WOOCOMMERCE_DEBUG', true);
```

#### Logs Estruturados:
```bash
# Visualizar logs em tempo real
tail -f wp-content/debug.log | grep local2global
```

### 🆘 Suporte

#### Antes de Reportar Problemas:
1. **Atualize** para versão mais recente
2. **Teste** com tema padrão WordPress
3. **Desative** outros plugins temporariamente
4. **Colete** logs de erro relevantes

#### Informações Necessárias:
- Versão do WordPress
- Versão do WooCommerce  
- Versão do plugin
- Versão do PHP
- Logs de erro específicos
- Passos para reproduzir

---

## 📈 Changelog

### Version 0.3.0 (2025-09-23)

#### 🎯 **Nova Funcionalidade: Seleção Automática de Atributos**
- **Algoritmo inteligente** para matching automático de atributos globais
- **Correspondência exata** para nomes idênticos (ex: "Cor" → "pa_cor")
- **Similaridade avançada** com threshold configurável (70%)
- **Fallback automático** para criação de novos atributos
- **Preservação** de seleções manuais do usuário

#### 🛡️ **Melhorias de Validação**
- **Validação obrigatória** na etapa de seleção de atributos
- **Prevenção de erros** ao clicar "Continuar" sem seleções
- **Mensagens claras** de feedback para o usuário
- **Navegação bloqueada** até seleções completas

#### 🔧 **Correções Críticas**
- **Removido método inexistente** `WC_Product_Attribute::set_taxonomy()`
- **API compatível** com WooCommerce oficial
- **Stubs corrigidos** para match com API real
- **Testes atualizados** para nova estrutura de retorno

#### 🚀 **Ambiente de Desenvolvimento Profissional**
- **Composer dependencies**: WordPress, WooCommerce, WP-CLI stubs
- **VS Code otimizado**: Autocomplete completo e zero erros PHP
- **PHPStan configurado**: Análise estática com todos os stubs
- **Script dev.sh**: Ferramentas integradas para desenvolvimento

#### 📊 **Melhorias de UX**
- **80-90% redução** em cliques manuais para atributos comuns
- **Fluxo mais rápido** para mapeamentos padrão
- **Interface mais intuitiva** com feedback visual
- **Prevenção proativa** de erros do usuário

### Version 0.2.x

#### ✅ Simplificação da UX
- Remoção de campos manuais complexos
- Dry-run automático na pré-visualização
- Interface responsiva com recuperação de erros
- Botão contextual (aparece apenas quando necessário)

#### 🏗️ Simplificação arquitetural  
- Comportamento determinístico (sempre atualiza variações)
- Remoção de templates, backups e flags comportamentais
- Configuração única: toggle de logging
- Remoção de serviços desnecessários

#### 🔧 Melhorias técnicas
- Auto-mapeamento com algoritmo Levenshtein
- Logging granular com correlação de operações  
- Consistência entre dry-run e apply
- APIs REST e CLI melhoradas

### Version 0.1.x

#### 🎉 Lançamento inicial
- Descoberta automática de atributos locais
- Mapeamento manual para taxonomias globais
- Pré-visualização básica
- Atualização de variações
- Interface admin básica

---

## 📄 Licença

Este plugin é licenciado sob a **GPL v2 ou posterior**.

```
Copyright (C) 2024 Evolury

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.
```

---

## 🤝 Contribuindo

Contribuições são bem-vindas! Para contribuir:

1. **Fork** o repositório
2. **Crie** uma branch para sua feature (`git checkout -b feature/nova-funcionalidade`)
3. **Commit** suas mudanças (`git commit -am 'Adiciona nova funcionalidade'`)
4. **Push** para a branch (`git push origin feature/nova-funcionalidade`)
5. **Abra** um Pull Request

### 📋 Guidelines de Contribuição:
- Siga os padrões de código estabelecidos
- Adicione testes para novas funcionalidades
- Atualize documentação quando necessário
- Use commits semânticos (feat, fix, docs, etc.)

---

**Desenvolvido com ❤️ pela [Evolury](https://github.com/tassiocamara)**

**Versão atual**: 0.3.0 | **Última atualização**: 23 de setembro de 2025