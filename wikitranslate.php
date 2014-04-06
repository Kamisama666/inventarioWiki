<?php 
/**
*
* @author Kamisama666
* @license GPL 2 (http://www.gnu.org/licenses/gpl.html)
*
* Sustituye en todos los archivos de la wiki aquellas palabras registradas en el indice
* por el patron del plugin translate
*
* uso: wikitranslate.php <ruta_carpeta_wiki> <ruta_indice> (<ruta_exclusion>) (<ruta_backup>)
*
* Codigos de error:
*	10:error en los parametros
*	11:error en los permisos
*
* Ejemplo de uso:
* php -f wikitranslate.php /root/public_html/data/pages/start prueba.txt /root/public_html/data/pages/start/6-multimedia_glossaries /root/workspace/backupwiki/
*
*/

function logEvent($evento) {
	global $logfile;
	$evento=trim($evento);
	$fecha=date("M d h:i:s");
	$evento=$fecha.": ".$evento."\n";
	file_put_contents($logfile, $evento,FILE_APPEND);
}

function substituir($texto) {
	/*
	Toma una cadena de texto filtrada en $texto y la sustituye usando el archivo
	definido en $archivoindice. Devuelve el texto substituido.
	*/
	global $archivoindice;
	global $name;
	if (!is_file($archivoindice) || !is_readable($archivoindice)) {
		//esto no debería pasar nunca, ha de comprobarse antes
		return false;
	}
	$indice=fopen($archivoindice, "r");
	while ( ($indicel=fgets($indice))!==false ) {
		$indicea=explode("||", $indicel);
		$palabrao=trim($indicea[0]);
		$mp3=trim($indicea[1]);

		if (false!==strpos($texto, $palabrao)) {
			logEvent("---Sustituyendo $palabrao en $name");
			$nveces=substr_count($texto, $palabrao);
			$palabran="{{translate>$palabrao||$mp3}}";
			$transformacion=array($palabrao=>$palabran);
			$texto=strtr($texto,$transformacion);
		}
	}
	
	return $texto;
}

function filtrar($cadena) {
	/*
	Toma una linea en $cadena y retorna true si es necesario filtrarla y false si no
	*/
	$cadena=trim($cadena);
	$filtrar=true;
	global $nofiltrar;

	//patrones simples que evitan la sustitucion en la linea entera
	if (substr($cadena, 0, 2)==="==") {
		$filtrar=false;
	}
	if ($cadena==="\n" || $cadena==="") {
		$filtrar=false;
	}
	else if (substr($cadena, 0, 1)==="*") {
		$filtrar=false;
	}
	else if (substr($cadena, 0, 4)==="* [[") {
		$filtrar=false;
	}
	else if (substr($cadena, 1,1)==="|" && ( (strpos($cadena,"^",1)!==false) || (strpos($cadena,"|",1)!==false) ) ) {
		$filtrar=false;	
	}
	else if (substr($cadena, 1,1)==="^" && ( (strpos($cadena,"^",1)!==false) || (strpos($cadena,"|",1)!==false) ) ) {
		$filtrar=false;	
	}
	else if (strpos($cadena,"<WRAP")!==false) {
		$filtrar=false;
	}
	else if (strpos($cadena,"</WRAP>")!==false) {
		$filtrar=false;
	}
	//Se usa $nofiltrar para cuando un patron indice que no se ha de filtrar hasta encontrarse su
	//terminacion
	else if ( (strpos($cadena, "</code>")!==false) || (strpos($cadena, "</html>")!==false) || (strpos($cadena, "</php>")!==false) ) {
		$nofiltrar=false;
		$filtrar=false;
	}
	else if ($nofiltrar!==false) {
		$filtrar=false;
	}
	else if ( (strpos($cadena, "<code")!==false) || (strpos($cadena, "<html>")!==false) || (strpos($cadena, "<php>")!==false) ) {
		$nofiltrar=true;
		$filtrar=false;
	}
	return $filtrar;
}

function get_cadena_filtrada($cadena) {
	/*
	Toma una cadena filtrada en $cadena y la devuelve filtrada (sustituyendo las palabras)
	allí donde sea seguro.
	*/
	$buffertotal="";
	$currentbuffer="";
	$excepcion=false;
	for ($i=0;$i<strlen($cadena);$i++) {
		if (substr($cadena, $i,2)=="[[") {
			$excepcion=true;
			$taginit="[[";
			$tagend="]]";
		}
		else if (substr($cadena, $i,2)=="((") {
			$excepcion=true;
			$taginit="((";
			$tagend="))";
		}
		else if (substr($cadena, $i,2)=="{{") {
			$excepcion=true;
			$taginit="{{";
			$tagend="}}";
		}

		if ($excepcion!==false) {
			if ($currentbuffer!=="") {
				$substituido=substituir($currentbuffer);
				if ($substituido!==false) {
					//filtra el texto alamacenado antes de la excepcion si lo hay
					$buffertotal.=$substituido;
					$currentbuffer="";	
				}
				else {
					echo "Error: El archivo indice no existe o no se tiene los permisos aducuandos\n";
					logEvent("Error: El archivo indice no existe o no se tiene los permisos aducuandos");
					exit(10);
				}
			}
			//calcula la posicion de los caracteres que cierran la excepcion
			$siguiente=strpos($cadena, $tagend,$i)+(strlen($tagend)-1);

			if ($siguiente!==false) {
				//calcula la distancia desde la posicion actual has los caracteres de cierre
				$distancia=($siguiente-$i)+1;
				//lo guarda sin fintral
				$buffertotal.=substr($cadena, $i,$distancia);
				//pone el indicador al final de la excepcion
				$i=$siguiente;
			}
			else {
				//Si no encuentra el caracter de cierre se va al final de la linea sin filtrar
				$taginitsize=strlen($taginit);
				$currentbuffer.=substr($cadena, $i,$taginitsize);
				$i=($i+$taginitsize)-1;
			}
			$excepcion=false;
		}
		else {
			$currentbuffer.=$cadena[$i];
		}
	}
	if ($currentbuffer!=="") {
		//filtra el texto alamacenado al terminar de recorrer la linea si lo hay
		$substituido=substituir($currentbuffer);
		if ($substituido!==false) {	
			$buffertotal.=$substituido;
		}
		else {
			echo "Error: El archivo indice no existe o no se tiene los permisos adecuados\n";
			logEvent("Error: El archivo indice no existe o no se tiene los permisos adecuados");
			exit(10);
		}
	}
	return $buffertotal;
}

function get_contenido_filtrado($name) {
	/*
		Toma el nombre de un archivo y devuelve su contenido substituido
	*/
	$texto_filtrado="";
	if (!is_writable($name)) {
    		echo "No se tiene permisos de escritura para el archivo $name\n";
    		logEvent("No se tiene permisos de escritura para el archivo $name");
    		exit(11);
    }
    $glosario=fopen($name, "r");
    while (false!==($linea=fgets($glosario))) {
    	if (filtrar($linea)) {
    		$texto_filtrado.=get_cadena_filtrada($linea);
    	}
    	else {
    		$texto_filtrado.=$linea;
    	}
    }
    fclose($glosario);
    return $texto_filtrado;
}


function dobackup($fullname,$rutabackup) {
	/*
	Realiza una copia de seguridad del archivo cuyo path absoluto se pasa en $fullname
	en el directorio pasado en $rutabackup
	*/
	if (!is_writable($rutabackup)) {
		echo "No se tienen los permisos adecuados en el directorio de backup $rutabackup\n";
		logEvent("No se tienen los permisos adecuados en el directorio de backup $rutabackup");
		exit(11);
	}
	//Define el numero máximo de backups de cada fichero
	$maxnumbackups=5;
	$name=basename($fullname);
	$namelen=strlen($name);
	$backupdir=opendir($rutabackup);
	$oldbackups=array();
	while (false!==($contdir=readdir($backupdir))) {
		if (is_file($rutabackup.'/'.$contdir) && strlen($contdir)>=$namelen) {
			$pos=strpos($contdir, ".txt")+4;
			$contlimpio=substr($contdir,0,$pos);
			if ($contlimpio===$name) {
				$oldbackups[]=$contdir;
			}
		}
	}
	closedir($backupdir);
	if (sizeof($oldbackups)>=$maxnumbackups) {
		//Eliminar la backup más antigua
		$masantigua=get_old_backup($oldbackups,$namelen);
		unlink($rutabackup.'/'.$masantigua);
	}
	//formato fecha: unixtime
	$marcafecha=time();
	$backupname=$rutabackup.'/'.$name.$marcafecha;
	copy($fullname, $backupname);
	logEvent("Se ha realizado un backup de $name en $backupname");
}

function get_old_backup($oldbackups,$namelen) {
	/*
		Toma un array de nombres de backup en $oldbackups y en tamaño
		del nombre del arcivo original en $namelen. Devuelve el
		backup mas antiguo
	*/
	$masantigua=$oldbackups[0];
	$fechamasantigua=(int)substr($masantigua, $namelen);
	for ($i=1;$i<sizeof($oldbackups);$i++) {
		$currentbackup=$oldbackups[$i];
		$currentfecha=(int)substr($currentbackup, $namelen);
		if ($currentfecha<$fechamasantigua) {
			$fechamasantigua=$currentfecha;
			$masantigua=$currentbackup;
		}
	}
	return $masantigua;
}

function rglob($pattern='*', $path='', $flags = 0) {
    /*
    Lista recursivamente los archivos del directorio en $path
    */
    $paths=glob($path.'*', GLOB_MARK|GLOB_ONLYDIR|GLOB_NOSORT);
    $files=glob($path.$pattern, $flags);
    foreach ($paths as $path) {
        $files=array_merge($files,rglob($pattern, $path, $flags));
    }
    return $files;
}


$nombrescript=$argv[0];

if (count($argv)<3) {
	echo "Error: Faltan parámetros\n";
	echo "uso: $nombrescript <ruta_carpeta_wiki> <ruta_indice> (<ruta_exclusion>) (<ruta_backup>)\n";
	exit(10);
}

//Esta variable es necesaria pues es usada como global por alguna de las funciones
$nofiltrar=false;
$logfile="wikitranslatelog.txt";

$path=$argv[1];
$archivoindice=$argv[2];

logEvent("Comienzo de ejecucion");

if (isset($argv[3])) {
	$rutaexc=$argv[3];
}
if (isset($argv[4])) {
	$rutabackup=$argv[4];
	if (!is_dir($rutabackup)) {
		echo "Error: El directorio de backup $rutabackup no existe\n";
		logEvent("Error: El directorio de backup $rutabackup no existe");
		exit(10);
	}
}

if (!is_dir($path)) {
	echo "Error: el directorio $path no es valido\n";
	logEvent("Error: el directorio $path no es valido");
	exit(10);
}

if (!is_file($archivoindice)) {
	echo "Error: el file $path no es valido\n";
	logEvent("Error: el file $path no es valido");
	exit(10);
}

$objects = rglob('*',$path,0);
foreach($objects as $name){
	
	if (!is_file($name)) {
		continue;
	}
    if (!is_writeable($name)) {
    	echo "Error: No se tienen los permisos adecuados en el archivo $name\n";
    	logEvent("Error: No se tienen los permisos adecuados en el archivo $name");
    	exit(11);
    }
    
    if (isset($rutaexc)) {
    	if (strpos($name, substr($rutaexc,1))) {
    		continue;
    	}
    }
    logEvent("Entrando en $name>");
    $contenido=get_contenido_filtrado($name);
   	
    if (isset($rutabackup)) {
    	dobackup($name,$rutabackup);
    }

    file_put_contents($name, $contenido);
    logEvent("Saliendo de $name>");
}


?>