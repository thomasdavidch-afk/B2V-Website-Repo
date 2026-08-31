FROM nginx:alpine

# Copie de la configuration Nginx
COPY default.conf /etc/nginx/conf.d/default.conf

# Copie des fichiers sources dans le dossier public Nginx
COPY . /usr/share/nginx/html

EXPOSE 80

CMD ["nginx", "-g", "daemon off;"]