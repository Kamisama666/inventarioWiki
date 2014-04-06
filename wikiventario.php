<?php
/**
* @author Kamisama666 
* @license GPL 2 (http://www.gnu.org/licenses/gpl.html)
*
* Crea un indice con todas las palabras que hayan sido traducidas con 
* el sonido de su pronunciación
*
* syntax: wikiventario.php <ruta_carpeta_glosario> <ruta_indice>
*
* #Codigos de error:
*	10:error en los parametros
*	11:error en los permisos
*	12:error al procesar los glosarios
*/


function get_valid_content($contarchivo) {
	/*
		Toma el selector de un archivo abierto para lectura
		de glosario y situa el cursor en la linea donde empieza
		el contenido que se ha de recoger
	*/
	$encontrado=false;
	while ( ($linea=fgets($contarchivo))!==false) {
		if (trim($linea)==="[[start:6-multimedia_glossaries|Back]]") {
			$encontrado=true;
			fgets($contarchivo);
			$linea2=trim(fgets($contarchivo));
			if (substr($linea2, 0, 1) !== '^') {
				$linea2=fgets($contarchivo);
			}
			break;
		}
	}
	return $encontrado;
}

function extract_content($cadenaraw,$indice) {
	/*
	Toma la cadena con los datos del glosario ya filtrado y el indice actual y devuelve
	el indice actualizado con las campos.
	$indice["palabras"]->las palabras
	$indice["rutas"]->rutas a los archivos mp3
	*/
	$campos=explode('|', $cadenaraw);

	foreach($campos as $campo) {
		if (strpos($campo, "{{mp3play>")===false) {
			continue;
		}
		$columnas=explode("{{mp3play>", $campo);
		$palabras=explode('/', $columnas[0]);
		$sonido=rtrim($columnas[1],'}');
		foreach ($palabras as $palabra) {
			$indice["palabras"][]=trim($palabra);
			$indice["rutas"][]=trim($sonido);
		}
	}
	return $indice;
}

function hasta_final($punteroa) {
	/*Toma el puntero de un archivo abierto para lectura y retorna
	su contenido desde la posicion actual hasta el final en un cadena
	ignorando saltos de linea.
	*/
	$hastafinal="";
	while( ($linea=fgets($punteroa))!==false ) {
		$hastafinal=$hastafinal.$linea;
	}
	return $hastafinal;
}

function palabra_repetida($referencia,$npalabra) {
	/*
	Devuelve true si $npalabra ya se encuentra en el archivo de indice
	pasado en $referencia. Devuelve false si no lo encuentra
	*/
	$npalabra=trim($npalabra);
	$coincide=false;
	rewind($referencia);
	while ( ($linea=fgets($referencia))!==false ) {
		$alinea=explode("||", $linea);
		$opalabra=$alinea[0];
		if ($opalabra===$npalabra) {
			$coincide=true;
			break;
		}
	}
	return $coincide;
} 


$nombrescript=$argv[0];

if (count($argv)<3) {
	echo "Error: Faltan parámetros\n";
	echo "uso: $nombrescript <ruta_carpeta_glosario> <ruta_indice>\n";
	exit(10);
}

$ruta=$argv[1];
$destino=$argv[2];

if (!file_exists($ruta) || !is_dir($ruta)) {
	echo "Error: No existe el directorio $ruta\n";
	echo "uso: $nombrescript <ruta_carpeta_glosario> <ruta_indice>\n";
	exit(10);
}
$ruta=rtrim($ruta,"/");
$glosariofl=array();
$directorio=opendir($ruta);
while (($archivo=readdir($directorio))!==false) {
	if (is_file($ruta."/".$archivo)) {
		$glosariofl[]=$ruta."/".$archivo;
	}
}
closedir($directorio);

$indicef=array("palabras"=>array(),"rutas"=>array());

foreach ($glosariofl as $fichero) {
	if (!is_readable($fichero)) {
		echo "Error: No es posible leer el archivo $archivo\n";
		echo "uso: $nombrescript <ruta_carpeta_glosario> <ruta_indice>\n";
		exit(11);
	}
	$fileref=fopen($fichero, 'r');
	$encontradof=get_valid_content($fileref);

	if (!$encontradof) {
		echo "Warning: No se ha encontrado el patron de busqueda dentro del archivo $fichero\n";
		continue;
	}
	$cadenatotal=trim(hasta_final($fileref),'|');
	
	fclose($fileref);
	$indicef=extract_content($cadenatotal,$indicef);

}


if (count($indicef["palabras"])!==count($indicef["rutas"])) {
	echo "Error: Ha ocurrido un error al procesar los glosarios, comprueba que tiene un formato valido\n";
	exit(12);
}

if (count($indicef["palabras"])<1) {
	echo "Error: No se ha encontrado ninguna palabra. Es posible que no existan glosarios o no tengan el formato adecuado\n";
	exit(12);
}
//Se elimina el antigua archivo quitar las palabras que se hayan borrado del glosario
unlink($destino);
$fdestino=fopen($destino, "a+");
if ($fdestino===false) {
	echo "Error: No se ha podido abrir el archivo destino $destino\n";
	echo "Revise los permisos del archivo\n";
	exit(11);
}

foreach ($indicef["palabras"] as $key => $mipalabra) {
	$miruta=trim($indicef["rutas"][$key]);
	$mipalabra=trim($mipalabra);
	if (!palabra_repetida($fdestino,$mipalabra)) {
		fseek($fdestino, filesize($destino));
		$cadenaw=$mipalabra."||".$miruta."\n";
		fwrite($fdestino, $cadenaw);
	}
	
	
}
fclose($fdestino);

?>
