FROM trafex/alpine-nginx-php7

ARG MAGENTO_VERSION

LABEL maintainer="sezzle"
LABEL php_version="7.3.1"
LABEL magento_version=${MAGENTO_VERSION}

ENV INSTALL_DIR /var/www/html/

USER root

RUN apk --no-cache add less mysql-client sudo php7-simplexml php7-pdo_mysql php7-iconv php7-mcrypt php7-soap

RUN rm -f *

COPY ./deploy/install.sh /usr/local/bin/install
RUN chmod +x /usr/local/bin/install

ADD --chown=nobody:nobody ./deploy/magento1-* $INSTALL_DIR

USER nobody

RUN curl https://codeload.github.com/OpenMage/magento-mirror/zip/${MAGENTO_VERSION} -o magento.zip

RUN unzip -q magento.zip && \
    rm magento.zip && \
    mv magento-mirror-${MAGENTO_VERSION}/* $INSTALL_DIR && \
    rm -rf magento-mirror-${MAGENTO_VERSION}

VOLUME $INSTALL_DIR
