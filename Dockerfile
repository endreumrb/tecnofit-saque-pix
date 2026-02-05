FROM hyperf/hyperf:8.2-alpine-v3.18-swoole

# Define timezone
ENV TIMEZONE=America/Sao_Paulo
RUN ln -snf /usr/share/zoneinfo/${TIMEZONE} /etc/localtime && echo ${TIMEZONE} > /etc/timezone

# Define diretório de trabalho
WORKDIR /opt/www

# Copia arquivos do projeto
COPY . /opt/www

# Instala dependências
RUN composer install --optimize-autoloader --no-interaction

# Expõe porta
EXPOSE 9501

# Comando de inicialização
ENTRYPOINT ["php", "bin/hyperf.php", "start"]