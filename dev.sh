#!/bin/bash

# Script de desenvolvimento para Local2Global
# Uso: ./dev.sh [comando]

set -e

case "${1:-help}" in
    "install")
        echo "🚀 Instalando dependências de desenvolvimento..."
        composer install
        echo "✅ Dependências instaladas!"
        echo "📝 Configure seu VS Code para usar os stubs:"
        echo "   - Recarregue a janela do VS Code (Cmd/Ctrl + Shift + P -> Developer: Reload Window)"
        echo "   - Os erros de 'undefined function' devem desaparecer"
        ;;
    
    "test")
        echo "🧪 Executando testes..."
        php tests/run-tests.php
        ;;
    
    "analyse")
        echo "🔍 Executando análise estática (PHPStan)..."
        php -d memory_limit=512M vendor/bin/phpstan analyse --no-progress
        ;;
    
    "analyse-file")
        if [ -z "$2" ]; then
            echo "❌ Erro: Especifique um arquivo"
            echo "   Uso: ./dev.sh analyse-file src/Services/Mapping_Service.php"
            exit 1
        fi
        echo "🔍 Analisando arquivo: $2"
        php -d memory_limit=512M vendor/bin/phpstan analyse --no-progress --level=1 "$2"
        ;;
    
    "fix-permissions")
        echo "🔧 Corrigindo permissões..."
        chmod +x dev.sh
        echo "✅ Permissões corrigidas!"
        ;;
    
    "status")
        echo "📊 Status do ambiente de desenvolvimento:"
        echo ""
        echo "📦 Composer:"
        if [ -f composer.lock ]; then
            echo "  ✅ Dependências instaladas"
        else
            echo "  ❌ Execute: ./dev.sh install"
        fi
        
        echo ""
        echo "🔧 Stubs:"
        if [ -d vendor/php-stubs ]; then
            echo "  ✅ WordPress stubs: $(ls vendor/php-stubs/ | tr '\n' ' ')"
        else
            echo "  ❌ Stubs não encontrados"
        fi
        
        echo ""
        echo "🎯 VS Code:"
        if [ -f .vscode/settings.json ]; then
            echo "  ✅ Configurações do Intelephense aplicadas"
        else
            echo "  ⚠️  Sem configurações específicas"
        fi
        ;;
    
    "clean")
        echo "🧹 Limpando cache e arquivos temporários..."
        rm -rf vendor/
        rm -f composer.lock
        echo "✅ Limpeza concluída! Execute ./dev.sh install para reinstalar"
        ;;
    
    "help"|*)
        echo "🛠️  Script de desenvolvimento Local2Global"
        echo ""
        echo "Comandos disponíveis:"
        echo "  install          - Instala dependências de desenvolvimento"
        echo "  test            - Executa suite de testes"
        echo "  analyse         - Executa PHPStan em todo o código"
        echo "  analyse-file    - Executa PHPStan em arquivo específico"
        echo "  status          - Mostra status do ambiente"
        echo "  clean           - Remove dependências e cache"
        echo "  fix-permissions - Corrige permissões do script"
        echo "  help            - Mostra esta ajuda"
        echo ""
        echo "Exemplo:"
        echo "  ./dev.sh install"
        echo "  ./dev.sh analyse-file src/Services/Mapping_Service.php"
        ;;
esac