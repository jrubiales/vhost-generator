#!/usr/bin/php
<?php

// Copyright 2024 Rubisof

// Licensed under the Apache License, Version 2.0 (the "License");
// you may not use this file except in compliance with the License.
// You may obtain a copy of the License at

// http://www.apache.org/licenses/LICENSE-2.0

// Unless required by applicable law or agreed to in writing, software
// distributed under the License is distributed on an "AS IS" BASIS,
// WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
// See the License for the specific language governing permissions and
// limitations under the License.

define('VHOST_TPL_FILE' , __DIR__ . '/../config/apache_vhost_tpl.conf');
define('WWW_PATH'       , '/var/www');
define('APACHE2_SITES'  , '/etc/apache2/sites-available');
define('SITE_FS_OWNER'  , 'debian');
define('SITE_FS_GROUP'  , 'www-data');

function main($argc, $argv) {

   $isWritableVhost        = true;
   $isWritableApacheSites  = true;

   if (!is_writable(WWW_PATH)) {
      echo '¡Atención! El directorio ' . WWW_PATH . " no existe o no se puede escribir en el.\n";
      $isWritableVhost = false;
   }

   if (!is_writable(APACHE2_SITES)) {
      echo '¡Atención! El directorio ' . APACHE2_SITES . " no existe o no se puede escribir en el. No se podrá guardar automáticamente la configuración en la configuración de Apache2, pero podrá generar el fichero de configuración.\n";
      $isWritableApacheSites = false;
   }

   $vhostFile = file_get_contents(VHOST_TPL_FILE);
   if (!$vhostFile) {
      fwrite(STDERR, "No se encuentra la plantilla de configuración del virtual host de apache2.\n");
      return 1;
   }

   $serverName = readline('Introduzca el hostname (ej. example.com): ');
   if ($serverName == '') {
      fwrite(STDERR, "Valor no válido para el parámetro hostname.\n");
      return 1;
   }
   $vhostFile = str_replace('{server_name}', $serverName, $vhostFile);

   $alias = readline("Introduzca el alias (ej. www.$serverName)? (opcional): ");
   if ($alias != '')
      $vhostFile = str_replace('{server_alias}', $alias, $vhostFile);
   else $vhostFile = str_replace("ServerAlias {server_alias}", '', $vhostFile);

   $defaultSitePath = WWW_PATH . "/$serverName";
   $sitePath = readline("Introduce el directorio donde se almacenará el proyecto web (opcional) [Por defecto=$defaultSitePath]: ");
   if ($sitePath == '')
      $sitePath = $defaultSitePath;

   $docRoot = readline("Introduce el directorio raiz donde se almacenará el punto de entrada de la web (ej. $sitePath/public) (opcional) [Por defecto=$sitePath]: ");
   if ($docRoot == '')
      $docRoot = $sitePath;
   $vhostFile = str_replace('{document_root}', $docRoot, $vhostFile);

   $resAutogenConf = 'n';
   if ($isWritableApacheSites) {
      do {
      $resAutogenConf = readline('¿Desea generar automáticamente el host virtual en ' . APACHE2_SITES . '? (s/n) [Por defecto=n]: ');
      if ($resAutogenConf == '')
         $resAutogenConf = 'n';
      } while($resAutogenConf != 's' && $resAutogenConf != 'n');
   }
   $autogenConf = $resAutogenConf == 's';

   $resAutogenDir = 'n';
   if ($isWritableVhost) {
      do {
      $resAutogenDir = readline('¿Desea generar automáticamente el árbol de directorios del proyecto en ' . WWW_PATH . '? (s/n) [Por defecto=n]: ');
      if ($resAutogenDir == '')
         $resAutogenDir = 'n';
      } while($resAutogenDir != 's' && $resAutogenDir != 'n');
   }
   $autogenDir = $resAutogenDir == 's';

   $filename = "$serverName.conf";
   if ($autogenConf) {
      $filename = APACHE2_SITES . '/' . $filename;
      if (!file_exists($filename))
         file_put_contents($filename, $vhostFile);
      else {
         fwrite(STDERR, "Error. Ya existe un sitio con el mismo nombre ($filename).\n");
         return 1;
      }
   } else {
      file_put_contents($filename, $vhostFile);
   }

   if ($autogenDir) {
      if (!file_exists($docRoot)) {
         $oldMask = umask(0);
         if (!mkdir($docRoot, 0770, true)) {
            fwrite(STDERR, "Error al crear el árbol de directorios $docRoot.\n");
            return 1;
         }
         chown($sitePath, SITE_FS_OWNER);
         chgrp($sitePath, SITE_FS_GROUP);
         chown($docRoot, SITE_FS_OWNER);
         chgrp($docRoot, SITE_FS_GROUP);
         umask($oldMask);
      } else {
         fwrite(STDERR, "Error. Ya existe un directorio con el mismo nombre ($docRoot).\n");
         return 1;
      }
   }

   if ($autogenConf) {
      exec("a2ensite $serverName");
      exec("systemctl reload apache2");
   }

   return 0;

}

$status=main($argc, $argv);
exit($status);
