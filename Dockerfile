# Static copy of oddjobprosllc.com served by Apache + PHP (PHP only for lead-webhook.php).
FROM php:8.3-apache
RUN a2enmod rewrite headers expires deflate \
 && sed -ri 's!/var/www/html!/var/www/site/public!g' /etc/apache2/sites-available/000-default.conf /etc/apache2/apache2.conf \
 && sed -ri 's!AllowOverride None!AllowOverride All!g' /etc/apache2/apache2.conf
COPY site/ /var/www/site/public/
COPY form-fields.json /var/www/site/form-fields.json
# LEADS_API_URL, LEADS_API_KEY and LEADS_SITE_NAME are read from the container environment.
RUN echo 'PassEnv LEADS_API_URL LEADS_API_KEY LEADS_SITE_NAME' > /etc/apache2/conf-enabled/leads-env.conf
EXPOSE 80
