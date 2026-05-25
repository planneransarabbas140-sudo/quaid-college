FROM php:8.4-cli

WORKDIR /app

RUN docker-php-ext-install pdo_mysql mysqli

COPY . .

RUN mkdir -p storage/sessions && chmod -R 775 storage start.sh

CMD ["sh", "./start.sh"]
