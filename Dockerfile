FROM php:8.4-apache

RUN docker-php-ext-install pdo_mysql \
    && for module in /etc/apache2/mods-enabled/mpm_*.load /etc/apache2/mods-enabled/mpm_*.conf; do \
        [ -e "$module" ] || continue; \
        case "$module" in \
            */mpm_prefork.*) ;; \
            *) rm -f "$module" ;; \
        esac; \
    done \
    && a2enmod mpm_prefork rewrite

# Never display PHP warnings or stack traces in public responses.
RUN printf 'display_errors=Off\nlog_errors=On\n' > /usr/local/etc/php/conf.d/zz-production.ini

WORKDIR /var/www/html
COPY . /var/www/html

# The app's uploads are persisted in MySQL on Railway. Keep writable local
# directories available for development or if filesystem storage is selected.
RUN mkdir -p uploads/profile_pics uploads/test_results uploads/private_documents \
    && chown -R www-data:www-data uploads

EXPOSE 8080

# Railway supplies PORT at runtime; make Apache listen on that port.
CMD ["sh", "-c", "for module in /etc/apache2/mods-enabled/mpm_*.load /etc/apache2/mods-enabled/mpm_*.conf; do [ -e \"$module\" ] || continue; case \"$module\" in */mpm_prefork.*) ;; *) rm -f \"$module\" ;; esac; done; a2enmod mpm_prefork rewrite >/dev/null; port=\"${PORT:-8080}\"; sed -i \"s/^Listen 80$/Listen ${port}/\" /etc/apache2/ports.conf; sed -i \"s/:80>/:${port}>/\" /etc/apache2/sites-available/000-default.conf; exec apache2-foreground"]
