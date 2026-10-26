FROM php:8.2-apache

# PDO MySQL driver for the database connection
RUN docker-php-ext-install pdo_mysql \
    && a2enmod headers

# Serve the project root (index.php redirects to /frontend)
WORKDIR /var/www/html
COPY . /var/www/html

# Apache settings: Render provides the port via $PORT; block non-public files
RUN printf '%s\n' \
    '<Directory /var/www/html>' \
    '    AllowOverride None' \
    '    Require all granted' \
    '</Directory>' \
    '<DirectoryMatch "^/var/www/html/(database|backend/includes|backend/PHPMailer)">' \
    '    Require all denied' \
    '</DirectoryMatch>' \
    '<FilesMatch "^(test_db|test_email|import_students)\.php$">' \
    '    Require all denied' \
    '</FilesMatch>' \
    '<Directory /var/www/html/backend/uploads>' \
    '    php_admin_flag engine off' \
    '</Directory>' \
    > /etc/apache2/conf-available/safespeak.conf \
    && a2enconf safespeak \
    && sed -ri 's/Listen 80/Listen ${PORT}/' /etc/apache2/ports.conf \
    && sed -ri 's/<VirtualHost \*:80>/<VirtualHost *:${PORT}>/' /etc/apache2/sites-available/000-default.conf \
    && mkdir -p backend/uploads/evidence backend/uploads/id_verification backend/uploads/terms \
    && chown -R www-data:www-data backend/uploads

ENV PORT=10000
EXPOSE 10000
