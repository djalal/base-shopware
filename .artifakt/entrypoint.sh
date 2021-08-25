#!/bin/bash
set -e

echo ">>>>>>>>>>>>>> START CUSTOM ENTRYPOINT SCRIPT <<<<<<<<<<<<<<<<< "

echo "------------------------------------------------------------"
echo "The following build args are available:"
env
echo "------------------------------------------------------------"

line="* * * * * su -c '/var/www/html/.artifakt/refreshEnvVars.sh' -s /bin/sh www-data"
(crontab -u www-data -l; echo "$line" ) | crontab -u www-data -

echo "Creating all symbolic links"

PERSISTENT_FOLDER_LIST=("custom/plugins" "files" "config/jwt" "public/theme" "public/media" "public/thumbnail" "public/bundles" "public/sitemap") 
for persistent_folder in ${PERSISTENT_FOLDER_LIST[@]}; do
  echo Mount $persistent_folder directory
  rm -rf /var/www/html/$persistent_folder && \
    mkdir -p /data/$persistent_folder && \
    ln -sfn /data/$persistent_folder /var/www/html/$persistent_folder && \
    chown -h www-data:www-data /var/www/html/$persistent_folder /data/$persistent_folder
done

#echo "Creating the link for .env file"
#ln -snf /data/.env /var/www/html/
ln -snf /data/.uniqueid.txt /var/www/html/

echo "End of symbolic links creation"

is_installed=0
echo "Checking if the app is already installed"
check_if_installed=$(echo "SELECT count(*) AS TOTALNUMBEROFTABLES FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = \"shopware\";" | mysql -N -h $DATABASE_HOST -u $DATABASE_USER $DATABASE_NAME -p${DATABASE_PASSWORD})
if [[ $check_if_installed -gt 0 && $check_if_installed != "" ]]; then
  echo "App already installed"
  is_installed=1
fi

echo "RUNNING ON-INIT SCRIPTS"
for f in /tmp/rootfs/etc/shopware/scripts/on-init/*; do source $f; done

echo "RUNNING ON-INSTALL SCRIPTS"
if [ $is_installed -eq 0 ]; then
  for f in /tmp/rootfs/etc/shopware/scripts/on-install/*; do source $f; done
fi
echo "RUNNING ON-STARTUP SCRIPTS"
for f in /tmp/rootfs/etc/shopware/scripts/on-startup/*; do source $f; done

if [ $is_installed -eq 1 ]; then
  cp public/.htaccess.dist public/.htaccess
fi

#echo "Changing owner of html"
chown -R www-data:www-data /var/www/html /data

echo ">>>>>>>>>>>>>> END CUSTOM ENTRYPOINT SCRIPT <<<<<<<<<<<<<<<<< "
