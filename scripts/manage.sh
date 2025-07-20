#!/bin/bash

# Script de gerenciamento do ambiente WordPress/WooCommerce Docker

set -e

# Cores para output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Função para exibir ajuda
show_help() {
    echo -e "${BLUE}WordPress/WooCommerce Docker - Script de Gerenciamento${NC}"
    echo ""
    echo "Uso: $0 [COMANDO]"
    echo ""
    echo "Comandos disponíveis:"
    echo "  start          - Iniciar todos os serviços"
    echo "  stop           - Parar todos os serviços"
    echo "  restart        - Reiniciar todos os serviços"
    echo "  setup          - Configurar WordPress inicial (executar após start)"
    echo "  logs           - Mostrar logs dos serviços"
    echo "  status         - Mostrar status dos containers"
    echo "  clean          - Limpar containers e volumes (CUIDADO!)"
    echo "  backup         - Fazer backup do banco de dados"
    echo "  restore        - Restaurar backup do banco de dados"
    echo "  wp             - Executar comandos WP-CLI"
    echo "  shell          - Abrir shell no container WordPress"
    echo "  mysql          - Abrir shell MySQL"
    echo "  help           - Mostrar esta ajuda"
    echo ""
}

# Função para verificar se Docker está rodando
check_docker() {
    if ! docker info >/dev/null 2>&1; then
        echo -e "${RED}❌ Docker não está rodando!${NC}"
        exit 1
    fi
}

# Função para iniciar serviços
start_services() {
    echo -e "${BLUE}🚀 Iniciando serviços...${NC}"
    docker compose up -d
    echo -e "${GREEN}✅ Serviços iniciados!${NC}"
    echo ""
    echo -e "${YELLOW}📋 Acesse:${NC}"
    echo "   WordPress: http://localhost:8080"
    echo "   phpMyAdmin: http://localhost:8081"
    echo ""
    echo -e "${YELLOW}💡 Execute '$0 setup' para configurar o WordPress${NC}"
}

# Função para parar serviços
stop_services() {
    echo -e "${BLUE}🛑 Parando serviços...${NC}"
    docker compose down
    echo -e "${GREEN}✅ Serviços parados!${NC}"
}

# Função para reiniciar serviços
restart_services() {
    echo -e "${BLUE}🔄 Reiniciando serviços...${NC}"
    docker compose restart
    echo -e "${GREEN}✅ Serviços reiniciados!${NC}"
}

# Função para configurar WordPress
setup_wordpress() {
    echo -e "${BLUE}⚙️ Configurando WordPress...${NC}"
    docker compose exec wpcli bash /scripts/setup-wordpress.sh
}

# Função para mostrar logs
show_logs() {
    echo -e "${BLUE}📋 Mostrando logs...${NC}"
    docker compose logs -f --tail=100
}

# Função para mostrar status
show_status() {
    echo -e "${BLUE}📊 Status dos containers:${NC}"
    docker compose ps
}

# Função para limpeza
clean_environment() {
    echo -e "${RED}⚠️ ATENÇÃO: Isso irá remover todos os containers e dados!${NC}"
    read -p "Tem certeza? (y/N): " -n 1 -r
    echo
    if [[ $REPLY =~ ^[Yy]$ ]]; then
        echo -e "${BLUE}🧹 Limpando ambiente...${NC}"
        docker compose down -v --remove-orphans
        docker system prune -f
        echo -e "${GREEN}✅ Ambiente limpo!${NC}"
    else
        echo -e "${YELLOW}❌ Operação cancelada${NC}"
    fi
}

# Função para backup
backup_database() {
    echo -e "${BLUE}💾 Fazendo backup do banco de dados...${NC}"
    BACKUP_FILE="backup_$(date +%Y%m%d_%H%M%S).sql"
    docker compose exec mysql mysqldump -u wordpress -pwordpress_password wordpress > "./backups/$BACKUP_FILE"
    echo -e "${GREEN}✅ Backup salvo em: ./backups/$BACKUP_FILE${NC}"
}

# Função para restaurar backup
restore_database() {
    echo -e "${BLUE}📥 Restaurando backup do banco de dados...${NC}"
    echo "Backups disponíveis:"
    ls -la ./backups/*.sql 2>/dev/null || echo "Nenhum backup encontrado"
    read -p "Digite o nome do arquivo de backup: " backup_file
    if [ -f "./backups/$backup_file" ]; then
        docker compose exec -T mysql mysql -u wordpress -pwordpress_password wordpress < "./backups/$backup_file"
        echo -e "${GREEN}✅ Backup restaurado!${NC}"
    else
        echo -e "${RED}❌ Arquivo de backup não encontrado!${NC}"
    fi
}

# Função para executar WP-CLI
run_wp_cli() {
    shift # Remove o primeiro argumento (wp)
    echo -e "${BLUE}🔧 Executando WP-CLI: wp $@${NC}"
    docker compose exec wpcli wp "$@" --allow-root
}

# Função para abrir shell WordPress
open_wp_shell() {
    echo -e "${BLUE}🐚 Abrindo shell no container WordPress...${NC}"
    docker compose exec wordpress bash
}

# Função para abrir shell MySQL
open_mysql_shell() {
    echo -e "${BLUE}🗄️ Abrindo shell MySQL...${NC}"
    docker compose exec mysql mysql -u wordpress -pwordpress_password wordpress
}

# Verificar se Docker está rodando
check_docker

# Criar diretório de backups se não existir
mkdir -p ./backups

# Processar comando
case "${1:-help}" in
    start)
        start_services
        ;;
    stop)
        stop_services
        ;;
    restart)
        restart_services
        ;;
    setup)
        setup_wordpress
        ;;
    logs)
        show_logs
        ;;
    status)
        show_status
        ;;
    clean)
        clean_environment
        ;;
    backup)
        backup_database
        ;;
    restore)
        restore_database
        ;;
    wp)
        run_wp_cli "$@"
        ;;
    shell)
        open_wp_shell
        ;;
    mysql)
        open_mysql_shell
        ;;
    help|--help|-h)
        show_help
        ;;
    *)
        echo -e "${RED}❌ Comando inválido: $1${NC}"
        echo ""
        show_help
        exit 1
        ;;
esac

