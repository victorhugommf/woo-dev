#!/bin/bash

# Basic configuration
URL="http://localhost:8080"
TITLE="My Woo Dev Site"
ADMIN_USER="admin"
ADMIN_PASS="YourStrongPassw0rd123"
ADMIN_EMAIL="destilus.store@gmail.com"

# Store details
STORE_COUNTRY="BR"
STORE_STATE="SP"
STORE_CITY="HORTOLANDIA"
STORE_ADDRESS="AV 1, 205"
STORE_ADDRESS_2="LOTEAMENTO INDUSTRIAL ZETA"
STORE_POSTCODE="13184-801"

# Business info
RAZAO_SOCIAL="DIRECTO - COMERCIO E DISTRIBUICAO LTDA"
NOME_FANTASIA="DIRECTO"
CNPJ="17.634.917/0001-40"
STORE_EMAIL="destilus.store@gmail.com"
STORE_PHONE="+5511999999999" # placeholder

# Install WordPress core (optional, only if not yet installed)
wp core install \
  --url="$URL" \
  --title="$TITLE" \
  --admin_user="$ADMIN_USER" \
  --admin_password="$ADMIN_PASS" \
  --admin_email="$ADMIN_EMAIL"

# Install and activate plugins
wp plugin install woocommerce --activate
wp plugin install woocommerce-extra-checkout-fields-for-brazil --activate
wp plugin install wordpress-importer --activate
wp plugin install woocommerce-gateway-stripe --activate
wp plugin install woocommerce-bling --activate

# Install and activate theme
wp theme install storefront --activate --force

# Import sample data
wp import /tmp/sample_products.xml --authors=create

# WooCommerce configuration
wp option update woocommerce_onboarding_profile '{"completed":true}' --format=json
wp option update woocommerce_admin_install_timestamp $(date +%s)
wp option update woocommerce_admin_revenue_analytics_prompt_shown 1
wp option update blog_public 1
wp option update woocommerce_onboarding_homepage_visible 1
wp option update woocommerce_demo_store 0

# Localization
wp language core install pt_BR --activate
wp user meta update 1 locale pt_BR

# Store location
wp option update woocommerce_default_country "$STORE_COUNTRY:$STORE_STATE"
wp option update woocommerce_store_country "$STORE_COUNTRY"
wp option update woocommerce_store_state "$STORE_STATE"
wp option update woocommerce_store_address "$STORE_ADDRESS"
wp option update woocommerce_store_address_2 "$STORE_ADDRESS_2"
wp option update woocommerce_store_city "$STORE_CITY"
wp option update woocommerce_store_postcode "$STORE_POSTCODE"

# Business info
wp option update store_razao_social "$RAZAO_SOCIAL"
wp option update store_nome_fantasia "$NOME_FANTASIA"
wp option update store_cnpj "$CNPJ"
wp option update store_email "$STORE_EMAIL"
wp option update store_telefone "$STORE_PHONE"

# Country and shipping restrictions
wp option update woocommerce_allowed_countries "specific"
wp option update woocommerce_specific_allowed_countries '["BR"]' --format=json

wp option update woocommerce_ship_to_countries "specific"
wp option update woocommerce_specific_ship_to_countries '["BR"]' --format=json

# Currency and price formatting
wp option update woocommerce_currency BRL
wp option update woocommerce_price_num_decimals 2
wp option update woocommerce_price_thousand_sep '.'
wp option update woocommerce_price_decimal_sep ','
wp option update woocommerce_currency_pos left_space

# Unidade de peso e medida
wp option update woocommerce_weight_unit "kg"
wp option update woocommerce_dimension_unit "m"

echo "✅ WordPress WooCommerce environment configured for DIRECTO."
echo "👉 Log in to the admin panel to complete the setup, Stripe test mode, and other onboarding steps."