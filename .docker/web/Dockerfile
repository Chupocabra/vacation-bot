FROM nginx:stable-alpine

WORKDIR /app

COPY ./public /app/public
COPY ./.docker/nginx/server.conf /etc/nginx/conf.d/default.conf
