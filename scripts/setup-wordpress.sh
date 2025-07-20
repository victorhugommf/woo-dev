#!/bin/bash

# Script para configuração inicial do WordPress com WooCommerce
# Este script deve ser executado após o WordPress estar rodando

echo "🚀 Iniciando configuração do WordPress..."

# Configurar WordPress se não estiver configurado
if ! wp core is-installed --allow-root 2>/dev/null; then
    echo "🔧 Configurando WordPress..."
    
    wp core install \
        --url="${WORDPRESS_SITE_URL:-http://localhost:8080}" \
        --title="WordPress Development Site" \
        --admin_user="${WORDPRESS_ADMIN_USER:-admin}" \
        --admin_password="${WORDPRESS_ADMIN_PASSWORD:-admin123}" \
        --admin_email="${WORDPRESS_ADMIN_EMAIL:-admin@localhost.com}" \
        --allow-root
    
    echo "✅ WordPress configurado com sucesso!"
else
    echo "ℹ️ WordPress já está configurado."
fi

# Instalar e ativar WooCommerce
echo "🛒 Instalando WooCommerce..."
if ! wp plugin is-installed woocommerce --allow-root; then
    wp plugin install woocommerce --activate --allow-root
    echo "✅ WooCommerce instalado e ativado!"
else
    echo "ℹ️ WooCommerce já está instalado."
    wp plugin activate woocommerce --allow-root
fi

# Instalar plugins úteis para desenvolvimento
echo "🔧 Instalando plugins de desenvolvimento..."

# Query Monitor para debugging
if ! wp plugin is-installed query-monitor --allow-root; then
    wp plugin install query-monitor --activate --allow-root
    echo "✅ Query Monitor instalado!"
fi

# Debug Bar
if ! wp plugin is-installed debug-bar --allow-root; then
    wp plugin install debug-bar --activate --allow-root
    echo "✅ Debug Bar instalado!"
fi


# WP Crontrol para gerenciar cron jobs
if ! wp plugin is-installed wp-crontrol --allow-root; then
    wp plugin install wp-crontrol --activate --allow-root
    echo "✅ WP Crontrol instalado!"
fi

# Elementor
echo "🧩 Instalando Elementor..."
if ! wp plugin is-installed elementor --allow-root; then
    wp plugin install elementor --activate --allow-root
    echo "✅ Elementor instalado!"
fi

# Configurar permalink structure
echo "🔗 Configurando permalinks..."
wp rewrite structure '/%postname%/' --allow-root
wp rewrite flush --allow-root

# Configurar timezone
echo "🕐 Configurando timezone..."
wp option update timezone_string 'America/Sao_Paulo' --allow-root

# Configurar configurações básicas do WooCommerce
echo "⚙️ Configurando WooCommerce..."
wp option update woocommerce_store_address "Rua Exemplo, 123" --allow-root
wp option update woocommerce_store_city "São Paulo" --allow-root
wp option update woocommerce_default_country "BR:SP" --allow-root
wp option update woocommerce_store_postcode "01234-567" --allow-root
wp option update woocommerce_currency "BRL" --allow-root
wp option update woocommerce_product_type "both" --allow-root
wp option update woocommerce_allow_tracking "no" --allow-root

# Criar páginas básicas do WooCommerce se não existirem
echo "📄 Criando páginas do WooCommerce..."
wp wc --allow-root tool run install_pages


# Configurar tema Hello Elementor
echo "🎨 Configurando tema Hello Elementor..."
if ! wp theme is-installed hello-elementor --allow-root; then
    wp theme install hello-elementor --activate --allow-root
    echo "✅ Tema Hello Elementor ativado!"
else
    wp theme activate hello-elementor --allow-root
    echo "ℹ️ Tema Hello Elementor já estava instalado e foi ativado!"
fi

# Brazilian Market on WooCommerce
echo "🇧🇷 Instalando WooCommerce Extra Checkout Fields for Brazil..."
if ! wp plugin is-installed woocommerce-extra-checkout-fields-for-brazil --allow-root; then
    wp plugin install woocommerce-extra-checkout-fields-for-brazil --activate --allow-root
    echo "✅ Plugin Brazilian Market instalado!"
else
    wp plugin activate woocommerce-extra-checkout-fields-for-brazil --allow-root
    echo "ℹ️ Plugin Brazilian Market já estava instalado e foi ativado!"
fi

# Criar usuário de teste para desenvolvimento
echo "👤 Criando usuário de teste..."
if ! wp user get testuser --allow-root 2>/dev/null; then
    wp user create testuser test@localhost.com \
        --role=customer \
        --user_pass=test123 \
        --first_name="Usuário" \
        --last_name="Teste" \
        --allow-root
    echo "✅ Usuário de teste criado (testuser/test123)!"
fi

# Configurar opções de desenvolvimento
echo "🛠️ Configurando opções de desenvolvimento..."
wp option update blog_public 0 --allow-root  # Desencorajar indexação
wp option update users_can_register 1 --allow-root  # Permitir registro

echo "🎉 Configuração do WordPress concluída!"
echo ""
echo "📋 Informações de acesso:"
echo "   WordPress: ${WORDPRESS_SITE_URL:-http://localhost:8080}"
echo "   Admin: ${WORDPRESS_ADMIN_USER:-admin} / ${WORDPRESS_ADMIN_PASSWORD:-admin123}"
echo "   Teste: testuser / test123"
echo "   phpMyAdmin: http://localhost:${PHPMYADMIN_PORT:-8081}"
echo ""

