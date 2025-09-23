#!/bin/bash

# Script para gerar versão limpa do plugin
# Uso: ./build.sh [versão]

VERSION=${1:-$(grep "Version:" local2global-attribute-mapper.php | sed 's/.*Version: //' | tr -d ' ')}
PLUGIN_NAME="local2global-attribute-mapper"
BUILD_DIR="builds"
TEMP_DIR="$BUILD_DIR/temp"
FINAL_ZIP="$BUILD_DIR/$PLUGIN_NAME-v$VERSION.zip"

echo "🏗️  Gerando build limpo v$VERSION..."

# Criar diretório de build
mkdir -p "$BUILD_DIR"
rm -rf "$TEMP_DIR"
mkdir -p "$TEMP_DIR/$PLUGIN_NAME"

# Copiar arquivos essenciais (excluir desenvolvimento)
rsync -av --exclude-from='.buildignore' . "$TEMP_DIR/$PLUGIN_NAME/"

# Entrar no diretório temporário
cd "$TEMP_DIR"

# Gerar ZIP limpo
zip -r "../../$FINAL_ZIP" "$PLUGIN_NAME/"

# Voltar e limpar
cd ../..
rm -rf "$TEMP_DIR"

echo "✅ Build gerado: $FINAL_ZIP"
echo "📦 Tamanho: $(du -h "$FINAL_ZIP" | cut -f1)"
echo ""
echo "🚀 Para criar release no GitHub:"
echo "gh release create v$VERSION --title \"v$VERSION\" --notes \"Release notes\" \"$FINAL_ZIP\""