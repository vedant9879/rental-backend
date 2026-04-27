FROM php:8.2-cli

WORKDIR /app

COPY . .

RUN docker-php-ext-install mysqli

CMD sh -c "php -S 0.0.0.0:$PORT -t /app"
