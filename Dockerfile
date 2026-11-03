# Static copy of oddjobprosllc.com served by Apache + PHP (PHP only for lead-webhook.php).
FROM php:8.3-apache
RUN a2enmod rewrite headers expires deflate \
 && sed -ri 's!/var/www/html!/var/www/site/public!g' /etc/apache2/sites-available/000-default.conf /etc/apache2/apache2.conf \
 && sed -ri 's!AllowOverride None!AllowOverride All!g' /etc/apache2/apache2.conf
COPY site/ /var/www/site/public/
COPY form-fields.json /var/www/site/form-fields.json
# LEADS_SERVICE_URL (the shared lead service) is read from the container environment.
RUN echo 'PassEnv LEADS_SERVICE_URL' > /etc/apache2/conf-enabled/leads-env.conf
EXPOSE 80
