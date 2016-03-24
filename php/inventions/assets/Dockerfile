FROM ubuntu
MAINTAINER Hauke Bartsch

ENV VERSION 2.14
ENV TARBALL shellinabox-$VERSION.tar.gz

RUN apt-get update && \
    apt-get -qq -y install gcc make git libssl-dev libpam0g-dev zlib1g-dev dh-autoreconf && \
    apt-get clean

COPY assets/work.sh /root/work.sh

RUN cd /tmp && \
    git clone https://github.com/shellinabox/shellinabox && \
    cd /tmp/shellinabox && \
    autoreconf -i && \
    ./configure && \
    make && \
    make install && \
    mkdir /etc/shellinabox-css && \
    cp shellinabox/*.css /etc/shellinabox-css/ && \
    adduser processing && usermod -aG sudo processing && echo processing:processing | chpasswd \
    mkdir /root/storage \
    chmod +rx /root/work.sh

ADD setup.sh /setup.sh
RUN /setup.sh

CMD ["shellinaboxd", "-s", "/:LOGIN", "--disable-ssl", "--user-css", "Normal:+/etc/shellinabox-css/white-on-black.css,Reverse:-/etc/shellinabox-css/black-on-white.css"]
EXPOSE 4200
