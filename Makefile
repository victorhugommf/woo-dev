# WordPress/WooCommerce Docker Development Environment
# Makefile para comandos rápidos

.PHONY: help start stop restart setup logs status clean backup restore shell mysql wp

# Comando padrão
help: ## Mostrar esta ajuda
	@echo "WordPress/WooCommerce Docker - Comandos Disponíveis:"
	@echo ""
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-15s\033[0m %s\n", $$1, $$2}'
	@echo ""
	@echo "Para mais opções, use: ./scripts/manage.sh help"

start: ## Iniciar todos os serviços
	@./scripts/manage.sh start

stop: ## Parar todos os serviços
	@./scripts/manage.sh stop

restart: ## Reiniciar todos os serviços
	@./scripts/manage.sh restart

setup: ## Configurar WordPress inicial (executar após start)
	@./scripts/manage.sh setup

logs: ## Mostrar logs dos serviços
	@./scripts/manage.sh logs

status: ## Mostrar status dos containers
	@./scripts/manage.sh status

clean: ## Limpar containers e volumes (CUIDADO!)
	@./scripts/manage.sh clean

backup: ## Fazer backup do banco de dados
	@./scripts/manage.sh backup

restore: ## Restaurar backup do banco de dados
	@./scripts/manage.sh restore

shell: ## Abrir shell no container WordPress
	@./scripts/manage.sh shell

mysql: ## Abrir shell MySQL
	@./scripts/manage.sh mysql

# Comandos WP-CLI
wp-list-plugins: ## Listar plugins instalados
	@./scripts/manage.sh wp plugin list

wp-list-themes: ## Listar temas instalados
	@./scripts/manage.sh wp theme list

wp-flush-cache: ## Limpar cache do WordPress
	@./scripts/manage.sh wp cache flush

wp-update-core: ## Atualizar WordPress core
	@./scripts/manage.sh wp core update

wp-create-user: ## Criar novo usuário (uso: make wp-create-user USER=nome EMAIL=email@example.com)
	@./scripts/manage.sh wp user create $(USER) $(EMAIL) --role=administrator --prompt=pass

# Comandos de desenvolvimento
dev-reset: ## Reset completo para desenvolvimento (limpa tudo e reconfigura)
	@echo "🔄 Fazendo reset completo do ambiente..."
	@make clean
	@make start
	@sleep 10
	@make setup
	@echo "✅ Reset completo concluído!"

dev-quick-start: ## Início rápido (start + setup)
	@echo "🚀 Início rápido do ambiente..."
	@make start
	@sleep 10
	@make setup

# Comandos de monitoramento
watch-logs: ## Acompanhar logs em tempo real
	@docker compose logs -f

watch-wp-logs: ## Acompanhar apenas logs do WordPress
	@docker compose logs -f wordpress

watch-mysql-logs: ## Acompanhar apenas logs do MySQL
	@docker compose logs -f mysql

