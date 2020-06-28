#!/bin/sh

mysql_ready() {
    mysqladmin ping -h $MYSQL_HOST -u $MYSQL_USER -p$MYSQL_PASSWORD > /dev/null 2>&1
}

while ! (mysql_ready)
do
    sleep 3
    echo "waiting for mysql database to be ready..."
done

echo "Importing sample data..."
unzip -q magento1-products.zip

cp -R magento1-products/media/* ./media/
cp -R magento1-products/skin/* ./skin/
chown -R nobody:nobody /var/www/html/media

# Import sample data
mysql -h $MYSQL_HOST -u $MYSQL_USER -p$MYSQL_PASSWORD $MYSQL_DATABASE < magento1-products/products.sql

echo "Installing Magento $MAGENTO_VERSION..."
php -f install.php -- --license_agreement_accepted "yes" --locale $MAGENTO_LOCALE --timezone $MAGENTO_TIMEZONE --default_currency $MAGENTO_DEFAULT_CURRENCY --db_host $MYSQL_HOST --db_name $MYSQL_DATABASE --db_user $MYSQL_USER --db_pass $MYSQL_PASSWORD --url $MAGENTO_URL --skip_url_validation "yes" --use_rewrites "no" --use_secure "no" --secure_base_url "" --use_secure_admin "no" --admin_firstname $MAGENTO_ADMIN_FIRSTNAME --admin_lastname $MAGENTO_ADMIN_LASTNAME --admin_email $MAGENTO_ADMIN_EMAIL --admin_username $MAGENTO_ADMIN_USERNAME --admin_password $MAGENTO_ADMIN_PASSWORD

echo "Creating Test Customer..."
php magento1-create-customer.php --email=$MAGENTO_CUSTOMER_EMAIL --password=$MAGENTO_CUSTOMER_PASSWORD

rm -rf magento1-products

# uncomment to configure magento to use redis cache
#sed -i 's/<active>false/<active>true/' app/etc/modules/Cm_RedisSession.xml
#sed -i -e '/<session_save><!\[CDATA\[files\]\]><\/session_save>/{r app/etc/redis.conf' -e 'd}' app/etc/local.xml