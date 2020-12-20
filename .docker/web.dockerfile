FROM nginx:1.19.6

ADD vhost.conf /etc/nginx/conf.d/default.conf