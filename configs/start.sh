#!/bin/bash

service php5-fpm restart
echo -e "READY! ENJOY YOUR SLAVES! AHAHAH ]:->\n"
nginx -g 'daemon off;'
